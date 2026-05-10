<?php

namespace Smstraffic\Sender;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Error;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\MessageService\Sender\Base;
use Bitrix\MessageService\Sender\Result\SendMessage;
use Smstraffic\SmartDelivery\ApiClient;

Loc::loadMessages(__FILE__);

/**
 * Провайдер отправки SMS для модуля «Служба сообщений» ({@see \Bitrix\MessageService}) через HTTP API
 * SmartDelivery (SMS Traffic BY).
 *
 * Реализует контракт {@see \Bitrix\MessageService\Sender\Base}: идентификатор, отображаемые имена,
 * проверка готовности к работе, список имён отправителя (originator) и фактическая отправка
 * {@see sendMessage}. Номера нормализуются до цифр через запятую; тело сообщения дополнительно
 * обрабатывается методом базового класса {@see \Bitrix\MessageService\Sender\Base::prepareMessageBodyForSend}
 * (кодировка/ограничения длины по правилам ядра).
 *
 * Настройки читаются из модуля `smstraffic` (логин, пароль, originators, `rus`, маршрут, базовые URL API).
 */
final class SmartDelivery extends Base
{
    /**
     * Уникальный идентификатор провайдера в настройках сайта («Почта и СМС») и в внутренних вызовах MessageService.
     */
    public const ID = 'smstraffic_smartdelivery';

    /** См. {@see ApiClient}: префикс ключей в таблице опций Битрикс. */
    private const MID = 'smstraffic';

    /**
     * @return string Всегда {@see self::ID}.
     */
    public function getId()
    {
        return self::ID;
    }

    /**
     * Полное имя провайдера в выпадающих списках административной части.
     *
     * @return string Локализованная строка (ключ `SMSTRAFFIC_SENDER_NAME`) или запасной текст на английском.
     */
    public function getName()
    {
        $m = Loc::getMessage('SMSTRAFFIC_SENDER_NAME');
        return $m !== null && $m !== '' ? $m : 'SMS Traffic (SmartDelivery BY)';
    }

    /**
     * Краткое имя (подпись) провайдера в интерфейсе.
     *
     * @return string Локализованная строка (ключ `SMSTRAFFIC_SENDER_SHORT`) или доменное имя по умолчанию.
     */
    public function getShortName()
    {
        $m = Loc::getMessage('SMSTRAFFIC_SENDER_SHORT');
        return $m !== null && $m !== '' ? $m : 'smstraffic.by';
    }

    /**
     * Проверяет, можно ли использовать провайдер для отправки.
     *
     * Условия: подключён модуль `messageservice`, в настройках `smstraffic` заданы непустые `login` и `password`.
     *
     * @return bool `true`, если отправку можно инициировать; иначе провайдер скрыт или неактивен в логике ядра.
     */
    public function canUse()
    {
        if (!Loader::includeModule('messageservice')) {
            return false;
        }

        $login = trim((string)Option::get(self::MID, 'login', ''));
        $password = trim((string)Option::get(self::MID, 'password', ''));

        return $login !== '' && $password !== '';
    }

    /**
     * Возвращает список допустимых имён отправителя (originator) для выбора в SMS-шлюзе.
     *
     * Берётся многострочное поле `originators` из настроек (разделители: перевод строки, запятая).
     * Каждая непустая строка становится парой `id`/`name`. Если список пуст, возвращается один пункт
     * `id=default` с подписью из языкового файла или строкой `default`.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public function getFromList()
    {
        $parsed = [];
        $raw = (string)Option::get(self::MID, 'originators', '');
        $parts = preg_split('/\r\n|\r|\n|,/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        if (is_array($parts)) {
            foreach ($parts as $item) {
                $name = trim((string)$item);
                if ($name === '') {
                    continue;
                }
                $parsed[] = ['id' => $name, 'name' => $name];
            }
        }

        if ($parsed === []) {
            $fallback = Loc::getMessage('SMSTRAFFIC_SENDER_DEFAULT_FROM_LABEL');
            if ($fallback === null || $fallback === '') {
                $fallback = 'default';
            }

            return [
                [
                    'id' => 'default',
                    'name' => $fallback,
                ],
            ];
        }

        return $parsed;
    }

    /**
     * Отправляет одно SMS (или несколько получателей в одном запросе API, если в `MESSAGE_TO` передан список).
     *
     * Ожидаемые ключи `$messageFields` (как передаёт MessageService): `MESSAGE_TO`, `MESSAGE_BODY`, `MESSAGE_FROM`.
     * При выборе отправителя `default` поле `originator` в API не передаётся. Дополнительно подставляются
     * `rus`, при заполненности — `route`, `routeGroupId` из настроек модуля. Таймауты HTTP наследуются
     * от свойств базового класса (`socketTimeout`, `streamTimeout`).
     *
     * @param array<string, mixed> $messageFields Поля сообщения от ядра MessageService.
     *
     * @return SendMessage Результат с ошибками {@see SendMessage::addError} или {@see SendMessage::setAccepted} при успехе.
     */
    public function sendMessage(array $messageFields)
    {
        $result = new SendMessage();

        if (!$this->canUse()) {
            $msg = Loc::getMessage('SMSTRAFFIC_ERR_CAN_USE');
            $result->addError(new Error($msg !== null && $msg !== '' ? $msg : 'SMS Traffic: check module settings (login/password).'));

            return $result;
        }

        $toRaw = (string)($messageFields['MESSAGE_TO'] ?? '');
        $phones = $this->normalizePhonesForApi($toRaw);
        if ($phones === '') {
            $msg = Loc::getMessage('SMSTRAFFIC_ERR_PHONE');
            $result->addError(new Error($msg !== null && $msg !== '' ? $msg : 'Recipient phone is empty or invalid.'));

            return $result;
        }

        $body = $this->prepareMessageBodyForSend((string)($messageFields['MESSAGE_BODY'] ?? ''));
        if ($body === '') {
            $msg = Loc::getMessage('SMSTRAFFIC_ERR_BODY');
            $result->addError(new Error($msg !== null && $msg !== '' ? $msg : 'Message body is empty.'));

            return $result;
        }

        $from = (string)($messageFields['MESSAGE_FROM'] ?? '');
        if ($from === 'default') {
            $from = '';
        }

        $post = [
            'phones' => $phones,
            'message' => $body,
            'rus' => $this->resolveRusMode(),
        ];

        if ($from !== '') {
            $post['originator'] = $from;
        }

        $route = trim((string)Option::get(self::MID, 'route', ''));
        if ($route !== '') {
            $post['route'] = $route;
        }

        $routeGroup = trim((string)Option::get(self::MID, 'route_group_id', ''));
        if ($routeGroup !== '') {
            $post['routeGroupId'] = $routeGroup;
        }

        $client = (new ApiClient())->setTimeouts((int)$this->socketTimeout, (int)$this->streamTimeout);
        $api = $client->send($post);

        if (($api['success'] ?? false) !== true) {
            $message = (string)($api['description'] ?? $api['error'] ?? 'SmartDelivery request failed');
            $code = $api['code'] ?? null;
            if ($code !== null && $code !== '') {
                $message .= ' [code ' . $code . ']';
            }
            $result->addError(new Error($message));

            return $result;
        }

        $result->setAccepted();

        return $result;
    }

    /**
     * Возвращает значение параметра кодировки/транслитерации `rus` для API SmartDelivery.
     *
     * Допустимые значения в настройках: `0` (транслит), `1`, `5` (кириллица/Unicode). Любое другое приводится к `5`.
     */
    private function resolveRusMode(): string
    {
        $v = (string)Option::get(self::MID, 'rus', '5');
        if ($v === '0' || $v === '1' || $v === '5') {
            return $v;
        }

        return '5';
    }

    /**
     * Преобразует получателей из строки Bitrix в формат `phones` API (цифры только, через запятую).
     *
     * Поддерживается несколько номеров, разделённых запятой с опциональными пробелами. Нецифровые символы отбрасываются.
     *
     * @param string $messageTo Значение `MESSAGE_TO` (один номер или список через запятую).
     */
    private function normalizePhonesForApi(string $messageTo): string
    {
        $parts = preg_split('/\s*,\s*/', $messageTo, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($parts) || $parts === []) {
            $parts = [$messageTo];
        }

        $normalized = [];
        foreach ($parts as $p) {
            $digits = preg_replace('/\D+/', '', (string)$p);
            if ($digits !== '') {
                $normalized[] = $digits;
            }
        }

        return implode(',', $normalized);
    }
}
