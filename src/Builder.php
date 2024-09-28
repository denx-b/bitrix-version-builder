<?php

namespace VersionBuilder;

use Exception;
use Gitonomy\Git\Commit;
use Gitonomy\Git\Repository;
use Gitonomy\Git\ReferenceBag;
use Gitonomy\Git\Reference\Tag;

class Builder extends Repository
{
    /** @var string описание обновления */
    protected $descriptionUpdate = '';

    /** @var string версия обновления */
    protected $moduleVersion = '.last_version';

    /** @var string имя архива */
    protected $archiveName = '.last_version.zip';

    /** @var string путь до директории с архивами обновлений */
    protected $versionsDirectoryPath = '';

    /** @var int кол-во тегов */
    protected $countTags = 0;

    /**
     * @throws Exception
     */
    public function __construct()
    {
        // Поиск пути до корня модуля и вызов родительского конструктора
        parent::__construct($this->findModuleDir(__DIR__));
        // Создаём директорию куда будут складываться архивы обновлений
        $this->createVersionsDirectory();
    }

    /**
     * Метод "взбирается" вверх по директориям и ищет корень модуля
     * За корень модуля считаем наличие install/version.php
     *
     * @param $baseDir
     * @param int $i
     * @return string
     * @throws Exception
     */
    protected function findModuleDir($baseDir, int $i = 0): string
    {
        if (++$i > 20) {
            throw new Exception('Version Builder: Error bitrix module not found');
        }

        // Путь на уровень выше
        $path = realpath($baseDir . '/..');

        // Один из способов определения корня модуля по композеру с bitrix-version-builder
        $composer = false;
        if (file_exists($path . '/composer.json')) {
            $composer = strpos(file_get_contents($path . '/composer.json'), 'bitrix-version-builder') !== false;
        }

        // Второй способ – это наличие файла version.php
        $version = file_exists($path . '/install/version.php');

        // Но так как библиотека работает на основании истории версий, то обязательно наличие гита
        if (($version || $composer) && !file_exists($path . '/src/Builder.php')) {
            if (!file_exists($path . '/.git')) {
                throw new Exception('Version Builder: Error git repository not found ' . $path);
            }
            return $path;
        }

        // Рекурсивная работа функции
        return $this->findModuleDir($path);
    }

    /**
     * Возвращает два промежуточных хеша, чтобы в дальнейшем межжду ними по diff можно было вытащить изменённые файлы
     * Все возможные варианты поиска хешей описаны в issue:
     *     https://github.com/denx-b/bitrix-version-builder/issues/4#issuecomment-1037250826
     *
     * Также в методе определяется название архива обновления, вариантов два: .last_version.zip или 0.0.0.zip
     * Если в истории git есть хотябы один тег, архив именуется 0.0.0.zip, если тегов нет, то .last_version.zip
     *
     * @return array
     * @throws Exception
     */
    public function getHashes(): array
    {
        /** @var String[] $arTags */
        $arTags = [];
        $refBag = new ReferenceBag($this);
        foreach ($refBag->getTags() as $tag) {
            /** @var Tag $tag */
            $u = $tag->getLog()->getCommits()[0]->getAuthorDate()->format('U');
            $arTags[$u] = $tag->getCommit()->getFixedShortHash();
        }

        krsort($arTags);

        $newerTagHash = $olderTagHash = '';
        if (count($arTags) === 1) {
            $newerTagHash = array_values($arTags)[0];
            $this->countTags = 1;
        } elseif (count($arTags) > 1) {
            $newerTagHash = array_shift($arTags);
            $olderTagHash = array_shift($arTags);
            $this->countTags = 2;
        }

        if ($olderTagHash) {
            $this->setModuleVersion($newerTagHash);
            $this->setArchiveNameByVersion();
        }
        
        /** @var Commit[] $commits */
        $log = $this->getLog();
        $commits = $log->getCommits();
        if (!$log->count()) {
            throw new Exception('Version Builder: Error there are no commits');
        } else {
            if ($log->count() === 1) {
                return [
                    'newer' => $commits[0]->getFixedShortHash(),
                    'older' => ''
                ];
            }
        }

        $newer = $newerTagHash ?: $commits[0]->getFixedShortHash();

        $prev = $older = $found = '';
        foreach ($commits as $commit) {
            /*
             * Этим условием происходит выбор нужной ревизии
             * Если выбирать ревизию через Repository::getRevision(), то теряются комиты исправленные через --amend
             */
            if ($commit->getFixedShortHash() === $newerTagHash) {
                $found = true;
            }
            if ($newerTagHash && $commit->getFixedShortHash() !== $newerTagHash && $found !== true) {
                continue;
            }

            /*
             * Пустым значение $olderTagHash мы делаем вывод, что нет второго тега
             * Отсюда принимается, решение, что самый older у нас – это последний комит в истории
             */
            if (!$olderTagHash) {
                $older = $commit->getFixedShortHash();
                continue;
            }

            /*
             * Второй тег есть и older'ом должен выступить комит следующий за тегом
             * Поэтому как только мы дошли до тега, то значением older выступает значение prev предыдущей итерации цикла
             * И сразу можно прерывать цикл break;
             */
            if ($commit->getFixedShortHash() === $olderTagHash) {
                $older = $prev;
                break;
            }

            $prev = $commit->getFixedShortHash();
        }

        $this->setDescriptionUpdate();

        return [
            'newer' => $newer,
            'older' => $older
        ];
    }

    /**
     * Метод создаёт корневую директорию для архивов версий
     *
     * @return void
     * @throws Exception
     */
    protected function createVersionsDirectory()
    {
        $this->setVersionsDirectoryPath();
        $directory = $this->getPathDir();
        if (!file_exists($directory)) {
            if (!mkdir($directory, 0755, true)) {
                throw new Exception('Version Builder: Failed to create /.versions/ directory, check access to module directory' . PHP_EOL);
            }
        }
    }

    /**
     * Установка описания изменений версии
     */
    protected function setDescriptionUpdate()
    {
        $log = $this->getLog(null, null, 0, 1);
        /** @var Commit $commit */
        $commit = $log->getCommits()[0];
        $this->descriptionUpdate = $commit->getSubjectMessage();
    }

    /**
     * Установка версии на основании конкретной ревизии из файла install/version.php
     *
     * @param string $hash
     * @return void
     * @throws Exception
     */
    protected function setModuleVersion(string $hash)
    {
        $content = $this->show($hash, 'install/version.php');
        $destPath = $this->getPathDir() . '/version.php';
        if (!file_put_contents($destPath, $content)) {
            throw new Exception('Version Builder: Failed to create file ' . $destPath . PHP_EOL);
        }

        require_once $destPath;
        if (!isset($arModuleVersion['VERSION']) || !$arModuleVersion['VERSION']) {
            throw new Exception('Version Builder: Error install/version.php file does\'t have value $arModuleVersion["VERSION"]' . PHP_EOL);
        }

        $this->moduleVersion = $arModuleVersion['VERSION'];
        unlink($destPath);
    }

    /**
     * @param string $hash
     * @param string $file
     * @return string
     * @throws Exception
     */
    public function show(string $hash, string $file): string
    {
        try {
            return $this->run('show', [$hash . ':' . $file]);
        } catch (Exception $e) {
            throw new Exception('Version Builder: Failed to get file ' . $file . ' value');
        }
    }

    /**
     * Установка имени архива по номеу версии
     */
    protected function setArchiveNameByVersion()
    {
        $this->archiveName = $this->getModuleVersion() . '.zip';
    }

    /**
     * Установка пути директории хранения версий
     */
    protected function setVersionsDirectoryPath()
    {
        $this->versionsDirectoryPath = $this->getPath() . '/.versions';
    }

    /**
     * @return string
     */
    public function getDescriptionUpdate(): string
    {
        return $this->descriptionUpdate;
    }

    /**
     * @return string
     */
    public function getModuleVersion(): string
    {
        return $this->moduleVersion;
    }

    /**
     * @return string
     */
    public function getArchiveName(): string
    {
        return $this->archiveName;
    }

    /**
     * @return string
     */
    public function getPathDir(): string
    {
        return $this->versionsDirectoryPath;
    }

    /**
     * @return string[]
     */
    public function getExcludeMask(): array
    {
        return ['.last_version', '.versions', 'bitrix-version-builder', '.gitignore', '.idea', 'vendor', 'composer', 'DS_Store', 'README.md'];
    }

    /**
     * @return int
     */
    public function getCountTags(): int
    {
        return $this->countTags;
    }
}
