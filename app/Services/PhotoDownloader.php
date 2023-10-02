<?php
// app/Services/PhotoDownloader.php


namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PhotoDownloader
{
    protected $client;
    private $apiUrl;
    private $apiToken;

    public function __construct()
    {
        // Получение конфигурации прокси из файла конфигурации
        $proxyConfig = config('fapopedia_net.proxy');

        // Проверка наличия необходимых параметров прокси
        if (!isset($proxyConfig['host'], $proxyConfig['port'], $proxyConfig['user'], $proxyConfig['pass'])) {
            throw new \Exception('Incomplete proxy configuration.');
        }

        // Формирование строки прокси для Guzzle
        $proxyUrl = sprintf(
            'http://%s:%s@%s:%d',
            $proxyConfig['user'],
            $proxyConfig['pass'],
            $proxyConfig['host'],
            $proxyConfig['port']
        );
        $this->apiUrl = config('fapopedia_net.api.url');
        $this->apiToken = config('fapopedia_net.api.token');

        $this->client = new Client([
            'proxy' => [
                'http'  => $proxyUrl,
                'https' => $proxyUrl,
            ],
        ]);
    }


    public function downloadAllPhotos(array $data)
    {
        foreach ($data as $modelName => $profile) {
            $photos = $profile['photos'] ?? [];
            $this->downloadPhotos($modelName, $photos);
        }
    }

    protected function downloadPhotos(string $modelName, array $photos)
    {
        foreach ($photos as $url) {
            $this->downloadPhoto($modelName, $url);
        }
    }
    protected function logToApi(string $message)
    {
        try {
            $this->client->post("{$this->apiUrl}/log", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiToken}",
                ],
                'json' => [
                    'message' => $message,
                ],
            ]);
        } catch (\Exception $e) {
            // Логирование ошибок при отправке лога на сервер
            Log::error("Failed to log to API: {$e->getMessage()}");
        }
    }

    protected function downloadPhoto(string $modelName, string $url)
    {
        try {
            $response = $this->client->get($url);
            $content = $response->getBody()->getContents();

            $filename = basename($url);
            $path = "models/{$modelName}/{$filename}";

            Storage::disk('local')->put($path, $content);
            $this->logToApi("Successfully downloaded photo from {$url}");
        } catch (\Exception $e) {
            // Логирование ошибок при загрузке фото
            $this->logToApi("Failed to download photo from {$url}: {$e->getMessage()}");
            Log::error("Failed to download photo from {$url}: {$e->getMessage()}");
        }
    }
}
