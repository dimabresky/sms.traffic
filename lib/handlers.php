<?php

namespace SmsTraffic;

use SmsTraffic\Sender\SmartDelivery;

/**
 * Обработчики событий модуля интеграции с SMS Traffic (SmartDelivery).
 *
 * Регистрируется при установке на событие модуля `messageservice`: **`onGetSmsSenders`**.
 * Ядро запрашивает список классов-отправителей SMS для «Службы сообщений». Возвращаемый экземпляр
 * {@see SmartDelivery} появляется в админке настройки SMS рядом с другими провайдерами.
 */
final class Handlers
{
    /**
     * Возвращает провайдер отправки SMS через HTTP API SmartDelivery (SMS Traffic BY).
     *
     * Идентификатор отправителя в UI: {@see SmartDelivery::ID} (`sms_traffic_smartdelivery`).
     * Фактическая доступность отправки определяется в {@see SmartDelivery::canUse()}
     * (наличие логина/пароля в настройках модуля и подключённый `messageservice`).
     *
     * @return SmartDelivery[] Обычно один элемент; ядро перечисляет все зарегистрированные отправители.
     */
    public static function onGetSmsSenders(): array
    {
        return [new SmartDelivery()];
    }
}
