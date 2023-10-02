<?php
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Storage;

class MediaRobot
{
    protected $client;

    public function __construct()
    {
        $this->client = new Client();
    }

    public function run()
    {
        $modelDirs = Storage::directories('app/models');

        foreach ($modelDirs as $modelDir) {
            $files = Storage::files($modelDir);

            $fileIds = [];
            foreach ($files as $file) {
                $filePath = storage_path("app/{$file}");
                $fileId = $this->uploadFileToS3($filePath, 'parserName');
                if ($fileId !== null) {
                    $fileIds[] = $fileId;
                }
            }

            $objectData = $this->collectObjectData($fileIds);
            $this->sendObjectData($objectData);

            // Логирование
            $this->logParsingStatus([
                'site_name' => 'example.com',
                'date_start' => now()->format('Y-m-d H:i:s'),
                'date_end' => now()->addMinutes(10)->format('Y-m-d H:i:s'),

            ]);
        }
    }

    protected function uploadFileToS3($filePath, $source)
    {
        $response = $this->client->post('{uri}/storage/s3/image/store', [
            'multipart' => [
                [
                    'name' => 'source',
                    'contents' => $source,
                ],
                [
                    'name' => 'file',
                    'contents' => fopen($filePath, 'r'),
                ],
            ],
        ]);

        $responseData = json_decode($response->getBody(), true);
        return $responseData['data']['id'] ?? null;
    }

    protected function collectObjectData($fileIds)
    {
        // Инициализация массива для хранения данных объекта
        $objectData = [];

        // Предположим, что у нас есть метод для получения информации о файле по его ID
        foreach ($fileIds as $fileId) {
            $fileInfo = $this->getFileInfo($fileId);
            if ($fileInfo) {
                $objectData['files'][] = $fileInfo;
            }
        }


        return $objectData;
    }

    protected function sendObjectData($objectData)
    {
        $this->client->post('{{uri}}/api/parser/v1/put-object', [
            'headers' => [
                'Parser-Api-Token' => '{value}',
            ],
            'json' => $objectData,
        ]);
    }

    protected function logParsingStatus($logData)
    {
        $this->client->post('{{uri}}/api/parser/v1/put-log', [
            'json' => $logData,
        ]);
    }
    protected function getFileInfo($fileId)
    {
        $filePath = storage_path("app/models/{$fileId}");
        if (file_exists($filePath)) {
            return [
                'id' => $fileId,
                'name' => basename($filePath),
                'size' => filesize($filePath),
            ];
        }
        return null;
    }


}
