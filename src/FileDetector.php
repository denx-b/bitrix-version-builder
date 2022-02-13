<?php

namespace VersionBuilder;

use Exception;
use Gitonomy\Git\Diff\File;

class FileDetector
{
    /** @var Builder */
    protected $repository;

    protected $command = 'git ls-files';

    /**
     * @param Builder $repository
     */
    public function __construct(Builder $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Возвращает список файлов необходимых для сборки обновления
     *
     * @return array
     * @throws Exception
     */
    public function getFiles(): array
    {
        $hashes = $this->repository->getHashes();
        $version = $this->repository->getModuleVersion();
        $countTags = $this->repository->getCountTags();

        /*
         * Даже если есть один тег, то нам всё ещё непонятно, каким брать предыдущий хеш
         * Поэтому с одним тегом берём все файлы
         */
        if ($version === '.last_version' || $countTags < 2) {
            return $this->getAllFiles($hashes['newer']);
        }

        return $this->getFilesBetweenHashes($hashes['newer'], $hashes['older']);
    }

    /**
     * @param string $newer
     * @param string $older
     * @return array
     * @throws Exception
     */
    protected function getFilesBetweenHashes(string $newer, string $older = ''): array
    {
        $hasVersion = false;
        $arExcludeMask = $this->repository->getExcludeMask();

        /** @var File[] $files */
        $older = $older ?: $newer;
        $this->command = 'git diff --name-only ' . $older . '^..' . $newer;
        $diff = $this->repository->getDiff($older . '^..' . $newer);
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
                'content' => $this->repository->show($newer, 'install/version.php')
            ];
        }

        return $arFiles;
    }

    /**
     * @param string $hash
     * @return array
     * @throws Exception
     */
    protected function getAllFiles(string $hash): array
    {
        $arExcludeMask = $this->repository->getExcludeMask();

        $revision = $this->repository->getRevision($hash)->getRepository();
        $files = preg_split("/\r\n|\n|\r/", $revision->run('ls-files'));

        $arFiles = [];
        foreach ($files as $file) {
            if (Helper::strposa($file, $arExcludeMask) !== false || !$file) {
                continue;
            }
            $arFiles[] = [
                'path' => $file,
                'content' => $this->repository->show($hash, $file)
            ];
        }

        return $arFiles;
    }

    /**
     * @return string
     */
    public function getCommand(): string
    {
        return $this->command;
    }
}
