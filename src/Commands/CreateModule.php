<?php

namespace VersionBuilder\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use VersionBuilder\Builder;

class CreateModule extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'bitrix:create-module';

    /** @var Builder */
    protected $builder;

    /** @var QuestionHelper */
    protected $questionHelper;

    protected $vendor = 'bitrix';

    protected $module = 'iblock';

    protected $moduleNameRu = '';

    protected $moduleDescriptionRu = '';

    protected $partnerName = 'bitrix';

    protected $partnerWebSite = 'https://1c-bitrix.ru/';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->builder = new Builder();
        $this->questionHelper = $this->getHelper('question');

        $dirName = preg_replace('/[_\-а-я\s+]/', '', basename($this->builder->getPath()));
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
            $np = str_replace($rp, $this->builder->getPath(), $path);
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
}
