<?php

/**
 * Точка подключения модуля `smstraffic`: регистрация автозагрузки классов в пространстве имён `Smstraffic`.
 *
 * Подключается ядром при `Loader::includeModule('smstraffic')`. Карта соответствует физическим файлам в `lib/`.
 */

use Bitrix\Main\Loader;
use Smstraffic\Handlers;
use Smstraffic\Sender\SmartDelivery;
use Smstraffic\SmartDelivery\ApiClient;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

Loader::registerAutoLoadClasses(
    'smstraffic',
    [
        Handlers::class => 'lib/handlers.php',
        SmartDelivery::class => 'lib/sender/smartdelivery.php',
        ApiClient::class => 'lib/smartdelivery/apiclient.php',
    ]
);
