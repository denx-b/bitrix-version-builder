<?php

namespace VersionBuilder;

use Exception;
use Gitonomy\Git\Commit;
use Gitonomy\Git\Repository;
use Gitonomy\Git\ReferenceBag;
use Gitonomy\Git\Reference\Tag;
use Gitonomy\Git\Diff\File;

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
     * Метод "взбирается" вверх по директориям и ищем корень модуля
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
        $path = $baseDir . '/..';
        if (file_exists($path . '/install/version.php')) {
            $dir = realpath($path);
            if (!file_exists($dir . '/.git')) {
                throw new Exception('Version Builder: Error git repository not found ' . $dir);
            }
            return $dir;
        }
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
        $arTags = [];
        $refBag = new ReferenceBag($this);
        foreach ($refBag->getTags() as $tag) {
            /** @var Tag $tag */
            $arTags[$tag->getTaggerDate()->format('U')] = $tag->getCommit()->getFixedShortHash();
        }

        $newerTagHash = '';
        $olderTagHash = '';
        if (count($arTags) === 1) {
            $newerTagHash = array_values($arTags)[0];
        } elseif (count($arTags) > 1) {
            krsort($arTags);
            $newerTagHash = array_shift($arTags);
            $olderTagHash = array_shift($arTags);
        }

        if ($newerTagHash) {
            $log = $this->getRevision($newerTagHash)->getLog();
            $this->setModuleVersion($newerTagHash);
            $this->setArchiveNameByVersion();
        } else {
            $log = $this->getLog();
        }

        /** @var Commit[] $commits */
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

        $prev = '';
        $older = '';
        foreach ($commits as $commit) {
            if (!$olderTagHash) {
                $older = $commit->getFixedShortHash();
                continue;
            }

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
     * Возвращает список изменённых файлов между указанными хешами
     *
     * @param string $newer
     * @param string $older
     * @return array
     */
    public function getFilesBetweenHash(string $newer, string $older = ''): array
    {
        if ($this->moduleVersion === '.last_version') {
            return $this->getFilesFromDir($newer);
        }
        return $this->getFilesFromGitonomy($newer, $older);
    }

    protected function getFilesFromGitonomy(string $newer, string $older = ''): array
    {
        $hasVersion = false;
        $arExcludeMask = $this->getExcludeMask();

        /** @var File[] $files */
        $argument = $older ? $older . '..' . $newer : $newer;
        $diff = $this->getDiff($argument);
        $files = $diff->getFiles();

        $arFiles = [];
        foreach ($files as $fileDiff) {
            if (Helper::strposa($fileDiff->getName(), $arExcludeMask) !== false) {
                continue;
            }
            $arFiles[] = [
                'path' => $fileDiff->getName(),
                'content' => $fileDiff->getNewBlob()->getContent()
            ];

            if (strpos($fileDiff->getName(), 'install/version.php') !== false) {
                $hasVersion = true;
            }
        }

        /*
         * Файл install/version.php обязательный для обновлений
         * Если по какой-либо причине он не попал в diff, включаем его принудительно
         */
        if ($hasVersion === false) {
            $arFiles[] = [
                'path' => 'install/version.php',
                'content' => $this->run('show', [$newer . ':install/version.php'])
            ];
        }

        return $arFiles;
    }

    protected function getFilesFromDir(string $hash): array
    {
        $arExcludeMask = $this->getExcludeMask();

        $revision = $this->getRevision($hash)->getRepository();
        $files = preg_split("/\r\n|\n|\r/", $revision->run('ls-files'));

        $arFiles = [];
        foreach ((array)$files as $file) {
            if (Helper::strposa($file, $arExcludeMask) !== false || !$file) {
                continue;
            }
            $arFiles[] = [
                'path' => $file,
                'content' => file_get_contents($this->getPath() . '/' . $file)
            ];
        }

        return $arFiles;
    }

    /**
     * @return void
     * @throws Exception
     * Метод создаёт корневую директорию для архивов версий
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
        try {
            $content = $this->run('show', [$hash . ':install/version.php']);
        } catch (Exception $e) {
            throw new Exception('Version Builder: Error revision ' . $hash . ' does\'t have file install/version.php' . PHP_EOL);
        }

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
        return ['.last_version', '.versions', 'bitrix-version-builder', '.gitignore', 'vendor', 'composer'];
    }
}
