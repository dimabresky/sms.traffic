<?php

/**
 * Значения опций модуля `sms.traffic` по умолчанию (подмешиваются при первом обращении к настройкам).
 *
 * Ключи соответствуют полям формы в `options.php` и параметрам, которые читает
 * {@see \SmsTraffic\SmartDelivery\ApiClient} и {@see \SmsTraffic\Sender\SmartDelivery}.
 */

return [
    'login' => '',
    'password' => '',
    'originators' => '',
    'rus' => '5',
    'route' => '',
    'route_group_id' => '',
    'api_base_primary' => 'https://sds.smstraffic.by/smartdelivery-in',
    'api_base_secondary' => 'https://sds2.smstraffic.by/smartdelivery-in',
];
