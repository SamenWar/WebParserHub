<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Exception;

class ModelApiService
{
    private $apiUrl;
    private $apiToken;

    public function __construct()
    {
        $this->apiUrl = config('fapopedia_net.api.url');
        $this->apiToken = config('fapopedia_net.api.token');
    }

    public function sendModelDataToApi($dataPath)
    {
        $filePath = storage_path($dataPath);

        if (!is_file($filePath)) {
            throw new Exception("File does not exist: $filePath");
        }

        // Загрузка данных из файла JSON
        $jsonData = file_get_contents($filePath);
        $dataArray = json_decode($jsonData, true);

        // Инициализация клиента Guzzle
        $client = new Client();

        // Счетчик успешных ответов
        $successCount = 0;

        foreach ($dataArray as $data) {
            try {
                // Отправка данных на API
                $response = $client->post($this->apiUrl.'/api/parser/v1/put-object', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->apiToken,
                        'Content-Type' => 'application/json'
                    ],
                    'body' => json_encode($data) // Отправка одного профиля
                ]);

                // Проверка статуса ответа
                if ($response->getStatusCode() == 200) { // Успешный ответ
                    $successCount++;
                } else {
                    throw new Exception('Negative response from server');
                }
            } catch (RequestException $e) {
                echo "HTTP request URL: " . $this->apiUrl . "\n";
                echo "HTTP response status: " . $e->getResponse()->getStatusCode() . "\n";
                echo "HTTP response body: " . $e->getResponse()->getBody() . "\n";
                throw new Exception('Data sending failed: ' . $e->getMessage());
            }
        }

        // Возвращаем количество успешно отправленных профилей
        return ['successCount' => $successCount];
    }
}
