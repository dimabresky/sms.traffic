<?php

namespace Smstraffic\SmartDelivery;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Web\HttpClient;

/**
 * HTTP(S) отправка через SmartDelivery multi.php + повтор при code 1000 / сетевой ошибке.
 */
final class ApiClient
{
    private const MID = 'smstraffic';

    private int $socketTimeout = 15;

    private int $streamTimeout = 35;

    public function setTimeouts(int $socketTimeout, int $streamTimeout): self
    {
        $this->socketTimeout = $socketTimeout > 0 ? $socketTimeout : 15;
        $this->streamTimeout = $streamTimeout > 0 ? $streamTimeout : 35;

        return $this;
    }

    /**
     * @param array<string, scalar|null> $postFields уже готовые пары параметров API (кроме login/password при необходимости — они добавятся здесь).
     *
     * @return array{
     *     success:bool,
     *     code?:int|string,
     *     result?:string,
     *     description?:string,
     *     http_status:int,
     *     raw?:string,
     *     error?: string
     * }
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
     * @param array<string, string> $fields
     *
     * @return array<string, mixed>
     */
    private function requestPrimary(array $fields, string $primaryBase): array
    {
        return $this->executePost($primaryBase . '/multi.php', $fields);
    }

    /**
     * @param array<string, string> $fields
     *
     * @return array<string, mixed>
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
     * @return array<string, mixed>
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
     * @param array<string, mixed> $firstPass
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

    private static function normalizeBaseUrl(string $url): string
    {
        $url = trim($url);
        $url = rtrim($url, '/');

        return $url;
    }
}
