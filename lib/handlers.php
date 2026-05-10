<?php

namespace Smstraffic;

use Smstraffic\Sender\SmartDelivery;

/**
 * Интеграция с модулем «Служба сообщений»: регистрация SMS-провайдера.
 */
final class Handlers
{
    /**
     * @return SmartDelivery[]
     */
    public static function onGetSmsSenders(): array
    {
        return [new SmartDelivery()];
    }
}
