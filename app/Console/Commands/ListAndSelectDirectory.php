<?php

namespace App\Console\Commands;

use App\Services\S3FileUploaderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Question\ChoiceQuestion;

class ListAndSelectDirectory extends Command
{
    protected $signature = 'list:select-directory';
    protected $description = 'List all directories and allow user to select one to view its files';

    protected $s3Uploader;

    public function __construct(S3FileUploaderService $s3Uploader)
    {
        parent::__construct();
        $this->s3Uploader = $s3Uploader;
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

// Задаем вопрос о загрузке всех файлов
        if ($this->confirm('Do you wish to upload all files in the directory? [yes|no]')) {
            foreach ($files as $file) {
                $fileName = basename($file);
                $this->info("Uploading: $fileName");

                $uploadResult = $this->s3Uploader->uploadFileToS3($file, $fileName);
                $this->info("Server response: " . (is_array($uploadResult) ? json_encode($uploadResult) : $uploadResult));

                if (isset($uploadResult['response'])) {
                    $fileId = $uploadResult['response'];
                    $this->updateJsonFile($file, $fileId);
                }
            }
        } else {
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

            if ($this->confirm('Do you wish to start the upload? [yes|no]')) {
                $uploadResult = $this->s3Uploader->uploadFileToS3("models/$directoryName/$fileName", $fileName);

                $this->info("Server response: " . (is_array($uploadResult) ? json_encode($uploadResult) : $uploadResult));

                if (isset($uploadResult['response'])) {
                    $fileId = $uploadResult['response'];
                    $this->updateJsonFile("models/$directoryName/$fileName", $fileId);
                }
            } else {
                $this->info('Upload cancelled.');
            }
        }

    }

    function updateJsonFile($filePath, $fileId, $jsonFilePath = 'file_ids.json') {
        if (file_exists($jsonFilePath)) {
            $jsonData = json_decode(file_get_contents($jsonFilePath), true);
        } else {
            $jsonData = [];
        }

        $jsonData[] = [
            'local_path' => $filePath,
            'remote_id'  => $fileId
        ];

        // Кодируем массив обратно в JSON и сохраняем его в файле
        file_put_contents($jsonFilePath, json_encode($jsonData, JSON_PRETTY_PRINT));
    }




}
