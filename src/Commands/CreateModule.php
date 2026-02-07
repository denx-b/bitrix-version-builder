<?php

namespace VersionBuilder\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class CreateModule extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'bitrix:create-module';

    /** @var QuestionHelper */
    protected $questionHelper;

    /** @var string */
    protected $modulePath = '';

    protected $vendor = 'bitrix';

    protected $module = 'iblock';

    protected $moduleNameRu = '';

    protected $moduleDescriptionRu = '';

    protected $partnerName = 'bitrix';

    protected $partnerWebSite = 'https://1c-bitrix.ru/';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->modulePath = realpath(getcwd()) ?: getcwd();
        $this->questionHelper = $this->getHelper('question');

        $dirName = preg_replace('/[_\-а-я\s+]/', '', basename($this->modulePath));
        if (preg_match('/(\S+)\.(\S+)/', $dirName, $matches)) {
            $this->vendor = $matches[1];
            $this->module = $matches[2];
            $this->partnerName = $this->vendor;
        } else {
            $this->module = $dirName;
        }

        $question = new Question('Vendor [' . $this->vendor . ']: ', $this->vendor);
        $this->vendor = $this->questionHelper->ask($input, $output, $question);

        $question = new Question('Module [' . $this->module . ']: ', $this->module);
        $this->module = $this->questionHelper->ask($input, $output, $question);

        $question = new Question('Module name ru [Name]: ', 'Name');
        $this->moduleNameRu = $this->questionHelper->ask($input, $output, $question);

        $question = new Question('Module description ru [Desc]: ', 'Desc');
        $this->moduleDescriptionRu = $this->questionHelper->ask($input, $output, $question);

        $question = new Question('Partner name [' . $this->partnerName . ']: ', $this->partnerName);
        $this->partnerName = $this->questionHelper->ask($input, $output, $question);

        $question = new Question('Partner website address [' . $this->vendor . '.ru]: ', $this->vendor . '.ru');
        $this->partnerWebSite = $this->questionHelper->ask($input, $output, $question);

        $this->ensureGitRepository($output);

        if ($this->getDirContents(__DIR__ . '/../../module-example') !== true) {
            $output->writeln('Check permissions. Module structure not created.');
            return Command::FAILURE;
        }

        $output->writeln('Module structure successfully generated.');
        return Command::SUCCESS;
    }

    /**
     * @param $dir
     * @param array $results
     * @return bool
     */
    protected function getDirContents($dir, array &$results = []): bool
    {
        $files = scandir($dir);
        foreach ($files as $value) {
            $path = realpath($dir . DIRECTORY_SEPARATOR . $value);
            $rp = realpath(__DIR__ . '/../../module-example/');
            $np = str_replace($rp, $this->modulePath, $path);
            if (is_dir($path)) {
                // Создание раздела
                if ($value !== "." && $value !== "..") {
                    if (!file_exists($np)) {
                        if (mkdir($np, 0755, true) === false) {
                            return false;
                        }
                    }
                    $this->getDirContents($path, $results);
                    $results[] = $path;
                }
            } else {
                // Создание файла
                $arReplace = [
                    '#MESS#' => strtoupper($this->vendor) . '_' . strtoupper($this->module),
                    'dbogdanoff_example' => $this->vendor . '_' . $this->module,
                    'dbogdanoff.example' => $this->vendor . '.' . $this->module,
                    '2019-12-04 19:00:00' => date('Y-m-d H:i:s'),
                    'name_ru' => $this->moduleNameRu,
                    'desc_ru' => $this->moduleDescriptionRu,
                    'partner_name' => $this->partnerName,
                    'partner_uri' => $this->partnerWebSite
                ];
                $data = file_get_contents($path);
                $data = str_replace(array_flip($arReplace), $arReplace, $data);
                if (file_put_contents($np, $data) === false) {
                    return false;
                }
                $results[] = $path;
            }
        }
        return true;
    }

    /**
     * @param OutputInterface $output
     * @return void
     */
    protected function ensureGitRepository(OutputInterface $output): void
    {
        if (file_exists($this->modulePath . '/.git')) {
            return;
        }

        if (!$this->isGitAvailable()) {
            $output->writeln('<comment>Warning: Git не найден в системе. Для корректной работы сборки версий нужен инициализированный git в корне модуля.</comment>');
            return;
        }

        $command = 'git init ' . escapeshellarg($this->modulePath) . ' 2>&1';
        exec($command, $commandOutput, $resultCode);
        if ($resultCode !== 0) {
            $output->writeln('<comment>Warning: Не удалось выполнить git init. Для корректной работы сборки версий нужен инициализированный git в корне модуля.</comment>');
            return;
        }

        $output->writeln('Git repository initialized in module root.');
    }

    /**
     * @return bool
     */
    protected function isGitAvailable(): bool
    {
        exec('git --version 2>&1', $commandOutput, $resultCode);
        return $resultCode === 0;
    }
}
