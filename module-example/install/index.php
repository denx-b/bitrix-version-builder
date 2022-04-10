<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\EventManager;
use Bitrix\Main\ModuleManager;

Loc::loadMessages(__FILE__);

if (class_exists('dbogdanoff_example')) {
    return;
}

class dbogdanoff_example extends CModule
{
    public $MODULE_ID = 'dbogdanoff.example';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $MODULE_GROUP_RIGHTS = 'Y';

    public function __construct()
    {
        $arModuleVersion = [];

        require __DIR__ . '/version.php';

        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];

        $this->MODULE_NAME = Loc::getMessage('#MESS#_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('#MESS#_MODULE_DESCRIPTION');

        $this->PARTNER_NAME = Loc::getMessage('#MESS#_PARTNER_NAME');
        $this->PARTNER_URI = Loc::getMessage('#MESS#_PARTNER_URI');
    }

    public function DoInstall()
    {
        global $APPLICATION;

        if ($this->isVersionD7()) {
            $this->InstallDB();
            $this->InstallEvents();
            $this->InstallFiles();

            ModuleManager::registerModule($this->MODULE_ID);
        } else {
            $APPLICATION->ThrowException(Loc::getMessage('#MESS#_INSTALL_ERROR_VERSION'));
        }
    }

    public function DoUninstall()
    {
        global $APPLICATION, $step, $obModule;

        if ($step < 2) {
            $APPLICATION->IncludeAdminFile(GetMessage('#MESS#_INSTALL_TITLE'), __DIR__ . '/unstep1.php');
        } elseif ($step == 2) {
            $GLOBALS['CACHE_MANAGER']->CleanAll();
            ModuleManager::unRegisterModule($this->MODULE_ID);

            $this->UnInstallDB(['savedata' => $_REQUEST['savedata']]);
            $this->UnInstallEvents();
            $this->UnInstallFiles();

            $obModule = $this;
            $APPLICATION->IncludeAdminFile(GetMessage('#MESS#_INSTALL_TITLE'), __DIR__ . '/unstep2.php');
        }
    }

    public function InstallDB($arParams = [])
    {
        return true;
    }

    public function UnInstallDB($arParams = [])
    {
        return true;
    }

    public function InstallEvents()
    {
        // Include module
        EventManager::getInstance()->registerEventHandler('main', 'OnPageStart', $this->MODULE_ID);
        return true;
    }

    public function UnInstallEvents()
    {
        // Include module
        EventManager::getInstance()->unRegisterEventHandler('main', 'OnPageStart', $this->MODULE_ID);
        return true;
    }

    public function InstallFiles($arParams = [])
    {
        CopyDirFiles(__DIR__ . '/admin/', $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin', true);
        return true;
    }

    public function UnInstallFiles()
    {
        DeleteDirFiles(__DIR__ . '/admin/', $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin');
        return true;
    }

    private function isVersionD7()
    {
        return CheckVersion(ModuleManager::getVersion('main'), '14.00.00');
    }
}
