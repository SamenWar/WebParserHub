<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Question\ChoiceQuestion;

class ListAndSelectDirectory extends Command
{
    protected $signature = 'list:select-directory';
    protected $description = 'List all directories and allow user to select one to view its files';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $directories = Storage::disk('local')->directories('models');
        $directoryNames = array_map('basename', $directories);

        if (empty($directoryNames)) {
            $this->info('No directories found.');
            return;
        }

        $helper = $this->getHelper('question');
        $directoryQuestion = new ChoiceQuestion(
            'Please select a directory (use arrow keys to navigate):',
            $directoryNames,
            0
        );
        $directoryQuestion->setErrorMessage('Directory %s is invalid.');

        $directoryName = $helper->ask($this->input, $this->output, $directoryQuestion);

        $this->info("You have selected: $directoryName");

        $files = Storage::disk('local')->files("models/$directoryName");
        if (empty($files)) {
            $this->info("The directory models/$directoryName is empty.");
            return;
        }

        $fileNames = array_map('basename', $files);
        $fileQuestion = new ChoiceQuestion(
            'Please select a file (use arrow keys to navigate):',
            $fileNames,
            0
        );
        $fileQuestion->setErrorMessage('File %s is invalid.');

        $fileName = $helper->ask($this->input, $this->output, $fileQuestion);

        $this->info("You have selected: $fileName");
        $this->info("Path to the file: models/$directoryName/$fileName");
        $this->uploadFileToS3("models/$directoryName/$fileName");
    }

    protected function uploadFileToS3(string $filePath)
    {
        try {
            $content = Storage::disk('local')->get($filePath);
            $s3Config = config('aws.s3');
            $s3Disk = Storage::disk('s3')->getDriver()->getAdapter();

            // Установка конфигурации S3
            $s3Disk->setKey($s3Config['key']);
            $s3Disk->setSecret($s3Config['secret']);
            $s3Disk->setRegion($s3Config['region']);
            $s3Disk->setBucket($s3Config['bucket']);

            // Загрузка файла на S3
            Storage::disk('s3')->put($filePath, $content);



            $this->output->write('Uploading...');
            for ($i = 0; $i < 5; $i++) {
                sleep(1);  // Эмуляция процесса загрузки
                $this->output->write('.');
            }
            $this->info(' Upload complete.');

        } catch (\Exception $e) {
            // Обработка исключений, например, запись в лог
            Log::error("Failed to upload file to S3: {$e->getMessage()}");
        }
    }
}
