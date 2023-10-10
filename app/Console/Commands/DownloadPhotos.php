<?php
// app/Console/Commands/DownloadPhotos.php

namespace App\Console\Commands;

use GuzzleHttp\Client;
use Illuminate\Console\Command;
use App\Services\PhotoDownloader;

class DownloadPhotos extends Command
{
    protected $signature = 'photos:download';
    protected $description = 'Download all photos from data.json';

    private $apiUrl;
    private $apiToken;

    public function __construct()
    {
        parent::__construct();
        $this->apiUrl = config('fapopedia_net.api.url');
        $this->apiToken = config('fapopedia_net.api.token');
    }

    public function handle(PhotoDownloader $photoDownloader)
    {
        $this->info('Downloading photos...');

        $jsonData = file_get_contents(storage_path('data.json'));
        $data = json_decode($jsonData, true);

        $photoDownloader->downloadAllPhotos($data);

        $this->info('Photos downloaded successfully.');

    }
    private function logParsingState($state)
    {
        $client = new Client();
        $uri = $this->apiUrl . '/api/downloader1/v1/put-log';

        $dateStart = now()->toDateTimeString();
        $dateEnd = now()->addHour()->toDateTimeString();

        try {
            $response = $client->post($uri, [
                'headers' => [
                    'Parser-Api-Token' => $this->apiToken,
                ],
                'json' => [
                    'errors' => $state == 'completed' ? 'No errors' : '',
                    'site_name' => 'https://fapopedia.net',
                    'date_start' => $dateStart,
                    'date_end' => $dateEnd,
                ],
            ]);

            // Проверка успешности запроса
            if ($response->getStatusCode() != 201) {
                throw new \Exception('Failed to log parsing state: ' . $response->getBody());
            }

            // Обработка ответа от сервера
            $serverResponse = json_decode($response->getBody(), true);
            echo "Server Response: ";
            print_r($serverResponse);

        } catch (\Exception $e) {
            // Обработка ошибок
            echo 'Error: ' . $e->getMessage() . PHP_EOL;
        }


    }

}
