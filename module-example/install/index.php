<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\EventManager;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Application;
use Bitrix\Main\Request;
use Bitrix\Main\Server;

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

    /** @var Server */
    protected Server $server;

    /** @var Request */
    protected Request $request;

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

        $context = Application::getInstance()->getContext();
        $this->server = $context->getServer();
        $this->request = $context->getRequest();
    }

    public function DoInstall(): bool
    {
        global $APPLICATION;

        if (!$this->isVersionD7()) {
            $APPLICATION->ThrowException(Loc::getMessage('#MESS#_INSTALL_ERROR_VERSION'));
            return false;
        }

        $this->InstallDB();
        $this->InstallEvents();
        $this->InstallFiles();

        ModuleManager::registerModule($this->MODULE_ID);

        return true;
    }

    /**
     * @throws Exception
     */
    public function DoUninstall()
    {
        global $APPLICATION, $step, $obModule;

        if ($step < 2) {
            $APPLICATION->IncludeAdminFile(GetMessage('#MESS#_INSTALL_TITLE'), __DIR__ . '/unstep1.php');
        } elseif ($step == 2) {
            $GLOBALS['CACHE_MANAGER']->CleanAll();
            ModuleManager::unRegisterModule($this->MODULE_ID);

            $this->UnInstallDB(['savedata' => $_REQUEST['savedata'] ?? false]);
            $this->UnInstallEvents();
            $this->UnInstallFiles();

            $obModule = $this;
            $APPLICATION->IncludeAdminFile(GetMessage('#MESS#_INSTALL_TITLE'), __DIR__ . '/unstep2.php');
        }
    }

    public function InstallDB($arParams = []): bool
    {
        return true;
    }

    /**
     * @throws Exception
     */
    public function UnInstallDB($arParams = []): bool
    {
        Option::delete($this->MODULE_ID);
        return true;
    }

    public function InstallEvents(): bool
    {
        // Include module
        EventManager::getInstance()->registerEventHandler('main', 'OnPageStart', $this->MODULE_ID);
        return true;
    }

    public function UnInstallEvents(): bool
    {
        // Include module
        EventManager::getInstance()->unRegisterEventHandler('main', 'OnPageStart', $this->MODULE_ID);
        return true;
    }

    public function InstallFiles($arParams = []): bool
    {
        CopyDirFiles(__DIR__ . '/admin/', $this->server->getDocumentRoot() . '/bitrix/admin', true, true);
        return true;
    }

    public function UnInstallFiles(): bool
    {
        /*
         * Нужно самостоятельно, внимательно написать удаление скопированных файлов
         * DeleteDirFilesEx – Удаляет рекурсивно указанный каталог (файл)
         * DeleteDirFiles – Удаляет из каталога все файлы, которые содержатся в другом каталоге. Функция не работает рекурсивно.
         */
        return true;
    }

    private function isVersionD7(): bool
    {
        return version_compare(ModuleManager::getVersion("main"), "14.00.00") >= 0;
    }
}
