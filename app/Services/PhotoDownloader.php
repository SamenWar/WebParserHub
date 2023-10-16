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
        $proxyConfig = [
            'host' => config('fapopedia_net.proxy.host'),
            'port' => config('fapopedia_net.proxy.port'),
            'user' => config('fapopedia_net.proxy.user'),  // Опционально
            'pass' => config('fapopedia_net.proxy.password'),  // Опционально
        ];

        // Проверка наличия необходимых параметров прокси
        if (!isset($proxyConfig['host'], $proxyConfig['port'])) {
            throw new \Exception('Incomplete proxy configuration.');
        }

        $proxyUrl = isset($proxyConfig['user'], $proxyConfig['pass']) && $proxyConfig['user'] && $proxyConfig['pass']
            ? sprintf('http://%s:%s@%s:%d', $proxyConfig['user'], $proxyConfig['pass'], $proxyConfig['host'], $proxyConfig['port'])
            : sprintf('http://%s:%d', $proxyConfig['host'], $proxyConfig['port']);


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
        $totalModels = count($data);  // Получаем общее количество моделей
        $processedModels = 0;  // Инициализируем счетчик обработанных моделей

        foreach ($data as $profile) {
            $modelName =$profile['name'];
            $photos = $profile['photos'] ?? [];
            $this->downloadPhotos($modelName, $photos);

            // Увеличиваем счетчик обработанных моделей
            $processedModels++;

            // Вычисляем и выводим прогресс
            $progress = ($processedModels / $totalModels) * 100;
            echo "Progress: " . round($progress) . "%\n";
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
            Log::channel('doclog')->info($message);
        } catch (\Exception $e) {
            // Логирование ошибок при отправке лога на сервер
            Log::error("Failed to log to API: {$e->getMessage()}");
            Log::channel('doclog')->error("Failed to log to API: {$e->getMessage()}");

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
