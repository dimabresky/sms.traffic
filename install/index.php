<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\EventManager;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;

Loc::loadMessages(__FILE__);

/**
 * Установочный класс локального модуля `sms.traffic`: регистрация в Битрикс и привязка к событию SMS-провайдера.
 *
 * Имя класса — `sms_traffic`: точки в MODULE_ID заменяются на подчёркивания (требование ядра).
 *
 * При установке модуль регистрируется в реестре, на событие `messageservice` → `onGetSmsSenders` вешается
 * статический метод {@see \SmsTraffic\Handlers::onGetSmsSenders}. При удалении обработчик снимается,
 * все опции модуля удаляются ({@see \Bitrix\Main\Config\Option::delete}).
 */
class sms_traffic extends \CModule
{
    /** @var string */
    public $MODULE_ID = 'sms.traffic';

    /** @var string */
    public $MODULE_VERSION;

    /** @var string */
    public $MODULE_VERSION_DATE;

    /** @var string */
    public $MODULE_NAME;

    /** @var string Текст описания в списке модулей; из языкового файла или запасная строка на английском. */
    public $MODULE_DESCRIPTION;

    /**
     * Подгружает `version.php`, заполняет свойства версии и локализованные имя/описание модуля.
     */
    public function __construct()
    {
        $arModuleVersion = [];
        include __DIR__ . '/version.php';
        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];

        $this->MODULE_NAME = Loc::getMessage('SMS_TRAFFIC_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('SMS_TRAFFIC_MODULE_DESC');
        if (!$this->MODULE_NAME) {
            $this->MODULE_NAME = 'SMS Traffic SmartDelivery (BY)';
        }
        if (!$this->MODULE_DESCRIPTION) {
            $this->MODULE_DESCRIPTION = 'SMS via SmartDelivery API for Bitrix MessageService';
        }
    }

    /**
     * Устанавливает модуль: регистрация в `ModuleManager` и события `onGetSmsSenders` для `messageservice`.
     *
     * После установки нужно задать логин/пароль в настройках модуля и выбрать провайдер в разделе
     * «Почта и СМС» главного модуля (идентификатор отправителя — `sms_traffic_smartdelivery`).
     *
     * @return bool `true` при успешной регистрации (ошибки ядра в типичном сценарии не ожидаются).
     */
    public function DoInstall(): bool
    {
        ModuleManager::registerModule($this->MODULE_ID);

        EventManager::getInstance()->registerEventHandler(
            'messageservice',
            'onGetSmsSenders',
            $this->MODULE_ID,
            '\\SmsTraffic\\Handlers',
            'onGetSmsSenders'
        );

        return true;
    }

    /**
     * Удаляет модуль: снимает обработчик события, очищает опции `sms.traffic`, снимает регистрацию модуля.
     *
     * @return bool `true` после удаления из реестра модулей и опций.
     */
    public function DoUninstall(): bool
    {
        EventManager::getInstance()->unRegisterEventHandler(
            'messageservice',
            'onGetSmsSenders',
            $this->MODULE_ID,
            '\\SmsTraffic\\Handlers',
            'onGetSmsSenders'
        );

        Option::delete($this->MODULE_ID);

        ModuleManager::unRegisterModule($this->MODULE_ID);

        return true;
    }
}
