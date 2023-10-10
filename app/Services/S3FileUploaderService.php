<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Storage;
use Exception;

class S3FileUploaderService
{
    private $apiUrl;
    private $token;

    public function __construct()
    {
        $this->apiUrl = config('fapopedia_net.api.url');
        $this->token = config('fapopedia_net.api.token');
    }

    public function uploadFileToS3($Path, $name)
    {
        $filePath = storage_path('app/' . $Path);

        // Проверяем, является ли путь файлом
        if (!is_file($filePath)) {
            throw new Exception("File does not exist: $filePath");
        }

        // Инициализируем клиент Guzzle
        $client = new Client();

        try {
            // Выполняем запрос и получаем ответ от сервера
            $response = $client->post($this->apiUrl.'/api/parser/v1/put-object', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->token
                ],
                'multipart' => [
                    [
                        'name'     => 'file',
                        'contents' => fopen($filePath, 'r')
                    ],
                    [
                        'name'     => 'source',
                        'contents' => 'WebParserHub'
                    ],
                    [
                        'name'     => 'original_name',
                        'contents' => $name
                    ]
                ]
            ]);

            // Пытаемся декодировать ответ как JSON
            $body = (string) $response->getBody();
            $decodedResponse = json_decode($body, true);

            // Проверяем, был ли ответ декодирован в массив
            if (is_array($decodedResponse)) {
                return $decodedResponse;
            } else {
                // Если ответ не был декодирован в массив, возвращаем оригинальный ответ в виде массива
                return ['response' => $body];
            }
        } catch (RequestException $e) {
            // Обрабатываем возможные ошибки запроса
            echo "HTTP request URL: " . $this->apiUrl . "\n";
            echo "HTTP response status: " . $e->getResponse()->getStatusCode() . "\n";
            echo "HTTP response body: " . $e->getResponse()->getBody() . "\n";
            throw new Exception('File upload failed: ' . $e->getMessage());
        }
    }

}
