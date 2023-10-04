<?php

namespace App\Console\Commands;

use Exception;
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
//        dd([
//            'AWS_ACCESS_KEY_ID' => env('AWS_ACCESS_KEY_ID'),
//            'AWS_SECRET_ACCESS_KEY' => env('AWS_SECRET_ACCESS_KEY'),
//            'AWS_DEFAULT_REGION' => env('AWS_DEFAULT_REGION'),
//            'AWS_BUCKET' => env('AWS_BUCKET'),
//            'AWS_URL' => env('AWS_URL'),
//        ]);

        if ($this->confirm('Do you wish to start the upload? [yes|no]')) {
            $this->uploadFileToS3("models/$directoryName/$fileName");
        } else {
            $this->info('Upload cancelled.');
        }

    }

    function uploadFileToS3($filePath)
    {
        try {
            // Построение полного пути к файлу
            $fullPath = storage_path('app/' . $filePath);

            // Проверка существования файла
            if (!file_exists($fullPath)) {
                throw new Exception("File does not exist: {$fullPath}");
            }

            // Извлечение имени файла из пути
            $fileName = basename($fullPath);

            // Чтение содержимого файла
            $fileContent = file_get_contents($fullPath);

            // Загрузка файла на S3
            Storage::disk('s3')->put($fileName, $fileContent, 'public');

            echo "File {$fileName} uploaded successfully to S3.";
        } catch (Exception $e) {
            echo "An error occurred: " . $e->getMessage();
        }
    }


}
