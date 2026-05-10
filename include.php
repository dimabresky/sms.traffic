<?php

/**
 * Точка подключения модуля `sms.traffic`: регистрация автозагрузки классов в пространстве имён `SmsTraffic`.
 *
 * Подключается ядром при `Loader::includeModule('sms.traffic')`. Карта соответствует физическим файлам в `lib/`.
 */

use Bitrix\Main\Loader;
use SmsTraffic\Handlers;
use SmsTraffic\Sender\SmartDelivery;
use SmsTraffic\SmartDelivery\ApiClient;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

Loader::registerAutoLoadClasses(
    'sms.traffic',
    [
        Handlers::class => 'lib/handlers.php',
        SmartDelivery::class => 'lib/sender/smartdelivery.php',
        ApiClient::class => 'lib/smartdelivery/apiclient.php',
    ]
);
