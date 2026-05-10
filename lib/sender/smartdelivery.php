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
 * Провайдер SMS для MessageService (SmartDelivery / SMS Traffic BY).
 */
final class SmartDelivery extends Base
{
    public const ID = 'smstraffic_smartdelivery';

    private const MID = 'smstraffic';

    public function getId()
    {
        return self::ID;
    }

    public function getName()
    {
        $m = Loc::getMessage('SMSTRAFFIC_SENDER_NAME');
        return $m !== null && $m !== '' ? $m : 'SMS Traffic (SmartDelivery BY)';
    }

    public function getShortName()
    {
        $m = Loc::getMessage('SMSTRAFFIC_SENDER_SHORT');
        return $m !== null && $m !== '' ? $m : 'smstraffic.by';
    }

    public function canUse()
    {
        if (!Loader::includeModule('messageservice')) {
            return false;
        }

        $login = trim((string)Option::get(self::MID, 'login', ''));
        $password = trim((string)Option::get(self::MID, 'password', ''));

        return $login !== '' && $password !== '';
    }

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
     * @param array<string, mixed> $messageFields
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

    private function resolveRusMode(): string
    {
        $v = (string)Option::get(self::MID, 'rus', '5');
        if ($v === '0' || $v === '1' || $v === '5') {
            return $v;
        }

        return '5';
    }

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
