<?php

namespace Smstraffic\SmartDelivery;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Web\HttpClient;

/**
 * Низкоуровневый клиент HTTP POST к SmartDelivery (`multi.php`).
 *
 * Обязанности:
 * - читает учётные данные и базовые URL API из настроек модуля (`login`, `password`,
 *   `api_base_primary`, `api_base_secondary`);
 * - объединяет переданные поля с `login`/`password`, отбрасывает не скалярные и `null`;
 * - сначала вызывает основной endpoint; при отказе с кодом провайдера `1000` или при сетевой
 *   ошибке HTTP-клиента повторяет запрос на резервный базовый URL;
 * - разбирает XML-ответ (`result`, `code`, `description`) в унифицированный массив с флагом `success`.
 *
 * Таймауты настраиваются через {@see setTimeouts} (по умолчанию 15 с на соединение, 35 с на ответ).
 */
final class ApiClient
{
    /** Идентификатор модуля в `Option::get` / админ-настройках. */
    private const MID = 'smstraffic';

    /** Таймаут установки TCP-соединения (сек.), передаётся в {@see HttpClient}. */
    private int $socketTimeout = 15;

    /** Таймаут чтения ответа (сек.), передаётся в {@see HttpClient}. */
    private int $streamTimeout = 35;

    /**
     * Задаёт таймауты HTTP-запроса. Нулевые и отрицательные значения заменяются на значения по умолчанию.
     *
     * @param int $socketTimeout Секунды до установки соединения (по умолчанию 15).
     * @param int $streamTimeout Секунды на получение тела ответа (по умолчанию 35).
     */
    public function setTimeouts(int $socketTimeout, int $streamTimeout): self
    {
        $this->socketTimeout = $socketTimeout > 0 ? $socketTimeout : 15;
        $this->streamTimeout = $streamTimeout > 0 ? $streamTimeout : 35;

        return $this;
    }

    /**
     * Выполняет POST на `…/multi.php`: сначала primary URL, при необходимости — secondary.
     *
     * К полям запроса автоматически добавляются `login` и `password` из настроек модуля.
     * Ключи с не-скалярными значениями и `null` удаляются; остальные приводятся к строке.
     *
     * @param array<string, scalar|null> $postFields Параметры тела запроса SmartDelivery
     *        (например `phones`, `message`, `rus`, опционально `originator`, `route`, `routeGroupId`).
     *
     * @return array{
     *     success:bool,
     *     code?:int|string,
     *     result?:string,
     *     description?:string,
     *     http_status:int,
     *     raw?:string,
     *     error?: string
     * } Результат: при успешном HTTP и корректном XML с `result=OK` и `code=0` (или пустым кодом) —
     *        `success=true`. Иначе `success=false`; текст ошибки в `description`, `error` или в `raw`.
     */
    public function send(array $postFields): array
    {
        $login = (string)Option::get(self::MID, 'login', '');
        $password = (string)Option::get(self::MID, 'password', '');

        $fields = array_merge(
            ['login' => $login, 'password' => $password],
            $postFields
        );

        foreach ($fields as $k => $v) {
            if ($v === null) {
                unset($fields[$k]);
                continue;
            }
            if (!is_scalar($v)) {
                unset($fields[$k]);
                continue;
            }
            $fields[$k] = (string)$v;
        }

        $primaryBase = self::normalizeBaseUrl((string)Option::get(
            self::MID,
            'api_base_primary',
            'https://sds.smstraffic.by/smartdelivery-in'
        ));
        $secondaryBase = self::normalizeBaseUrl((string)Option::get(
            self::MID,
            'api_base_secondary',
            'https://sds2.smstraffic.by/smartdelivery-in'
        ));

        $first = $this->requestPrimary($fields, $primaryBase);
        if ($this->shouldRetryOnAlternative($first)) {
            return $this->executePost($secondaryBase . '/multi.php', $fields);
        }

        return $first;
    }

    /**
     * Отправляет запрос на основной (primary) базовый URL из настроек.
     *
     * @param array<string, string> $fields Уже нормализованные строковые поля POST.
     *
     * @return array<string, mixed> Тот же формат, что у {@see executePost}.
     */
    private function requestPrimary(array $fields, string $primaryBase): array
    {
        return $this->executePost($primaryBase . '/multi.php', $fields);
    }

    /**
     * Выполняет один HTTP POST и разбирает ответ либо фиксирует сбой транспорта.
     *
     * @param array<string, string> $fields Пары ключ-значение для тела POST (все значения — строки).
     *
     * @return array<string, mixed> При ошибке HTTP: `success=false`, `http_status`, `raw`, `error`.
     *         При ответе 2xx: поля от {@see parseReplyXml} плюс `http_status`.
     */
    private function executePost(string $url, array $fields): array
    {
        $http = new HttpClient([
            'socketTimeout' => $this->socketTimeout,
            'streamTimeout' => $this->streamTimeout,
        ]);

        $http->setCharset('UTF-8');

        $ok = $http->query(HttpClient::HTTP_POST, $url, $fields);
        $status = $http->getStatus();
        $resultBody = $http->getResult();

        if (!$ok || $status < 200 || $status >= 300) {
            $errParts = [];
            foreach ($http->getError() as $msg) {
                if ($msg !== '' && $msg !== null) {
                    $errParts[] = $msg;
                }
            }

            return [
                'success' => false,
                'http_status' => $status,
                'raw' => $resultBody,
                'error' => $errParts ? implode('; ', $errParts) : 'HTTP request failed',
            ];
        }

        return array_merge(['http_status' => $status], $this->parseReplyXml($resultBody));
    }

    /**
     * Разбирает XML-ответ SmartDelivery в структуру для верхнего уровня {@see send}.
     *
     * Ожидаются элементы верхнего уровня: `result` (OK/ERROR), `code` (число; 0 — без ошибки API),
     * `description`. Пустое тело и невалидный XML трактуются как ошибка.
     *
     * @return array<string, mixed> Массив с ключами `success`, при необходимости `code`, `result`,
     *         `description`, `http_status` (200 для успешного HTTP), опционально `raw`.
     */
    private function parseReplyXml(string $xml): array
    {
        $trimmed = trim($xml);
        if ($trimmed === '') {
            return [
                'success' => false,
                'description' => 'Empty response body',
                'http_status' => 200,
            ];
        }

        libxml_use_internal_errors(true);
        $sx = @simplexml_load_string($trimmed);
        libxml_clear_errors();

        if ($sx === false) {
            return [
                'success' => false,
                'description' => 'Invalid XML in response',
                'http_status' => 200,
                'raw' => $trimmed,
            ];
        }

        $resultStr = strtoupper(trim((string)($sx->result ?? '')));
        $codeVal = isset($sx->code) ? (int)(string)$sx->code : null;
        $description = (string)($sx->description ?? '');

        if ($resultStr === 'ERROR') {
            return [
                'success' => false,
                'code' => $codeVal ?? -1,
                'result' => (string)$sx->result,
                'description' => $description !== '' ? $description : 'ERROR',
                'http_status' => 200,
                'raw' => $trimmed,
            ];
        }

        if (($codeVal !== null && $codeVal !== 0)) {
            return [
                'success' => false,
                'code' => $codeVal,
                'result' => (string)$sx->result,
                'description' => $description !== '' ? $description : 'API code ' . $codeVal,
                'http_status' => 200,
                'raw' => $trimmed,
            ];
        }

        if ($resultStr === 'OK' || $resultStr === '') {
            return [
                'success' => true,
                'code' => $codeVal ?? 0,
                'result' => (string)($sx->result ?? 'OK'),
                'description' => $description,
                'http_status' => 200,
                'raw' => $trimmed,
            ];
        }

        return [
            'success' => false,
            'code' => $codeVal ?? -1,
            'result' => (string)$sx->result,
            'description' => $description !== '' ? $description : 'Unexpected API response',
            'http_status' => 200,
            'raw' => $trimmed,
        ];
    }

    /**
     * Определяет, нужно ли повторить запрос на резервный (secondary) базовый URL.
     *
     * Повтор выполняется, если первый ответ не успешен и выполняется одно из условий:
     * - в теле ответа указан код API `1000` (типичный сигнал переключения/перегрузки у провайдера);
     * - или зафиксирована транспортная ошибка (`error` не пустой), например таймаут или обрыв.
     *
     * @param array<string, mixed> $firstPass Результат первого вызова {@see executePost}.
     */
    private function shouldRetryOnAlternative(array $firstPass): bool
    {
        if (($firstPass['success'] ?? false) === true) {
            return false;
        }

        $code = $firstPass['code'] ?? null;
        if ($code !== null && (int)$code === 1000) {
            return true;
        }

        if (!empty($firstPass['error'])) {
            return true;
        }

        return false;
    }

    /**
     * Обрезает пробелы и завершающий слэш у базового URL, чтобы корректно конкатенировать `/multi.php`.
     */
    private static function normalizeBaseUrl(string $url): string
    {
        $url = trim($url);
        $url = rtrim($url, '/');

        return $url;
    }
}
