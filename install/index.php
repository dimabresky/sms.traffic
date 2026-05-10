<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\EventManager;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;

Loc::loadMessages(__FILE__);

class smstraffic extends \CModule
{
    /** @var string */
    public $MODULE_ID = 'smstraffic';

    /** @var string */
    public $MODULE_VERSION;

    /** @var string */
    public $MODULE_VERSION_DATE;

    /** @var string */
    public $MODULE_NAME;

    /** @var string */
    public $MODULE_DESCRIPTION;

    public function __construct()
    {
        $arModuleVersion = [];
        include __DIR__ . '/version.php';
        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];

        $this->MODULE_NAME = Loc::getMessage('SMSTRAFFIC_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('SMSTRAFFIC_MODULE_DESC');
        if (!$this->MODULE_NAME) {
            $this->MODULE_NAME = 'SMS Traffic SmartDelivery (BY)';
        }
        if (!$this->MODULE_DESCRIPTION) {
            $this->MODULE_DESCRIPTION = 'SMS via SmartDelivery API for Bitrix MessageService';
        }
    }

    /**
     * @return bool
     */
    public function DoInstall(): bool
    {
        ModuleManager::registerModule($this->MODULE_ID);

        EventManager::getInstance()->registerEventHandler(
            'messageservice',
            'onGetSmsSenders',
            $this->MODULE_ID,
            '\\Smstraffic\\Handlers',
            'onGetSmsSenders'
        );

        return true;
    }

    /**
     * @return bool
     */
    public function DoUninstall(): bool
    {
        EventManager::getInstance()->unRegisterEventHandler(
            'messageservice',
            'onGetSmsSenders',
            $this->MODULE_ID,
            '\\Smstraffic\\Handlers',
            'onGetSmsSenders'
        );

        Option::delete($this->MODULE_ID);

        ModuleManager::unRegisterModule($this->MODULE_ID);

        return true;
    }
}
