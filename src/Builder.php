<?php

namespace VersionBuilder;

use Gitonomy\Git\Commit;
use Gitonomy\Git\Repository;
use Gitonomy\Git\ReferenceBag;
use Gitonomy\Git\Reference\Tag;
use Gitonomy\Git\Diff\File;

class Builder extends Repository
{
    /** @var string */
    protected $descriptionUpdate = '';

    public function __construct()
    {
        $dir = $this->findModulePath(__DIR__);
        parent::__construct($dir);
    }

    /**
     * @param $baseDir
     * @param int $i
     * @return string
     */
    protected function findModulePath($baseDir, int $i = 0): string
    {
        if (++$i > 20) {
            exit('Version Builder Error: Bitrix module not found');
        }
        $path = $baseDir . '/..';
        if (file_exists($path . '/install/version.php')) {
            return realpath($path);
        }
        return $this->findModulePath($path);
    }

    /**
     * Возвращает два промежуточных хеша самого нового тега и предыдущего по истории
     * @return array
     */
    public function getHashesBetweenLastTags(): array
    {
        $arHashes = [];
        $refBag = new ReferenceBag($this);
        foreach ($refBag->getTags() as $tag) {
            /** @var Tag $tag */
            $arHashes[$tag->getTaggerDate()->format('U')] = $tag->getCommit()->getFixedShortHash();
        }

        if (count($arHashes) < 2) {
            return [];
        }

        krsort($arHashes);
        $curr = array_shift($arHashes);
        $prev = array_shift($arHashes);
        $this->setDescriptionUpdate($curr);

        return [
            'curr' => $curr,
            'prev' => $prev
        ];
    }

    /**
     * Установка описания изменений версии
     *
     * @param string $hash
     * @return void
     */
    protected function setDescriptionUpdate(string $hash)
    {
        $log = $this->getLog(null, null, 0, 1);
        /** @var Commit $commit */
        $commit = $log->getCommits()[0];
        $this->descriptionUpdate = $commit->getSubjectMessage();
    }

    /**
     * @return string
     */
    public function getDescriptionUpdate(): string
    {
        return $this->descriptionUpdate;
    }

    /**
     * Возвращает список изменённых файлов между указанными хешами
     * Первым должен быть хеш более старого коммита
     *
     * @param string $first
     * @param string $second
     * @return array
     */
    public function getFilesBetweenHash(string $first, string $second = ''): array
    {
        $arFiles = [];
        $arExcludeMask = ['.last_version', '.versions', 'bitrix-version-builder', '.gitignore', 'vendor', 'composer'];
        $argument = $second ? $first . '..' . $second : $first;
        $diff = $this->getDiff($argument);
        foreach ($diff->getFiles() as $fileDiff) {
            /** @var $fileDiff File */
            if ($this->strposa($fileDiff->getName(), $arExcludeMask) !== false) {
                continue;
            }
            $arFiles[] = [
                'path' => $fileDiff->getName(),
                'content' => $fileDiff->getNewBlob()->getContent()
            ];
        }
        return $arFiles;
    }

    /**
     * Возвращает текущую версию модуля
     * @return string
     */
    public function getCurrentModuleVersion(): string
    {
        $version = '';
        $versionFile = $this->getPath() . '/install/version.php';
        if (file_exists($versionFile)) {
            require_once $versionFile;
            if (isset($arModuleVersion)) {
                $version = $arModuleVersion['VERSION'];
            }
        }
        return $version;
    }

    /**
     * @param $path
     * @return void
     */
    public function removeDirectory($path)
    {
        $files = glob($path . '/' . '{,.}[!.,!..]*', GLOB_BRACE);
        foreach ($files as $file) {
            if (in_array(basename($file), ['.', '..'])) {
                continue;
            }
            is_dir($file) ? $this->removeDirectory($file) : unlink($file);
        }
        rmdir($path);
    }

    /**
     * @param $haystack
     * @param array $needles
     * @param int $offset
     * @return false|mixed
     */
    protected function strposa($haystack, array $needles = [], int $offset = 0)
    {
        $chr = [];
        foreach ($needles as $needle) {
            $res = strpos($haystack, $needle, $offset);
            if ($res !== false) {
                $chr[$needle] = $res;
            }
        }
        if (empty($chr)) {
            return false;
        }
        return min($chr);
    }
}
