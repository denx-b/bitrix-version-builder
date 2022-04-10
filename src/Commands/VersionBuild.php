<?php

namespace VersionBuilder\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class VersionBuild extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'bitrix:version-build';

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $builder = new \VersionBuilder\Builder();
            $fileDetector = new \VersionBuilder\FileDetector($builder);
            $files = $fileDetector->getFiles();
            // output
            $output->writeln([
                'Starting Version Builder work:',
                $fileDetector->getCommand() . PHP_EOL
            ]);
        } catch (\Exception $e) {
            // output
            $output->writeln($e->getMessage());
            return Command::FAILURE;
        }

        $directory = $builder->getPathDir() . '/' . $builder->getModuleVersion();
        if (!file_exists($directory)) {
            if (!mkdir($directory, 0755, true)) {
                // output
                $output->writeln('Version Builder: Failed to create directory');
                return Command::FAILURE;
            }
        }

        $archiveName = $builder->getPathDir() . '/' . $builder->getArchiveName();
        if (file_exists($archiveName)) {
            unlink($archiveName);
        }

        $zip = new \ZipArchive();
        if ($zip->open($archiveName, \ZipArchive::CREATE) !== true) {
            // output
            $output->writeln('Version Builder: Unable to open ' . $archiveName);
            return Command::FAILURE;
        }
        // output
        $output->writeln('Files: ');
        foreach ($files as $file) {
            // Создание директории версии
            $destDir = $directory . '/' . dirname($file['path']);
            $destFile = $directory . '/' . $file['path'];
            if (!file_exists($destDir)) {
                if (!mkdir($destDir, 0755, true)) {
                    // output
                    $output->writeln('Version Builder: Failed to create directory ' . dirname($file['path']));
                    return Command::FAILURE;
                }
            }
            // Копируем файлы
            $needConvert = strpos($file['path'], 'lang/ru') !== false;
            $content = $needConvert ? iconv('UTF-8', 'CP1251', $file['content']) : $file['content'];
            file_put_contents($destFile, $content);
            // Добавляем файлы в архив
            if ($zip->addFile($destFile, $builder->getModuleVersion() . '/' . $file['path'])) {
                // output
                $output->writeln($file['path']);
            }
        }

        // output
        $output->writeln(['', 'Result: ']);

        $description = $builder->getDescriptionUpdate();
        $zip->addFromString($builder->getModuleVersion() . '/description.ru', iconv('UTF-8', 'CP1251', $description));
        if (!$zip->close()) {
            // output
            $output->writeln('Version Builder: Build version NOT created');
            return Command::FAILURE;
        } else {
            // output
            $output->writeln('Version Builder: Archive created ' . $builder->getArchiveName() . ' successfully');
        }

        \VersionBuilder\Helper::removeDirectory($directory);
        // output
        $output->writeln([
            'Version Builder: Time ' . round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 6) . ' sec.',
            ''
        ]);

        return Command::SUCCESS;
    }
}
