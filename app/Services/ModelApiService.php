<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Exception;

class ModelApiService
{

    private $dataSenderService;

    public function __construct(DataSenderService $dataSenderService)
    {

        $this->dataSenderService = $dataSenderService;
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

        // Счетчики
        $successCount = 0;
        $errorCount = 0;

        foreach ($dataArray as $data) {
            // Отправка данных на API
            list($status_code, $api_response) = $this->dataSenderService->sendData([
                "object" => [
                    "object_type" => 1,
                    'code' => 2,
                    'site_name' => 'fapopedia.net',
                    'url_page' => $data['profileUrl'],
                    'name' => $data['name'],
                    'instagram_url' => $data['social']['instagram'] ?? '',
                    'twitter_url' => $data['social']['twitter'] ?? '',
                    'onlyfans_url' => $data['social']['onlyfans'] ?? '',
                    'tiktok_url' => $data['social']['tiktok'] ?? '',
                    'website_url' => $data['social']['website'] ?? '',
                    ]
            ]);

            // Проверка статуса ответа
            if ($status_code == 200 || $status_code == 201) {
                $successCount++;
            } else {

                // Запись ошибки в файл вместо выброса исключения
                $errorData = [
                    'model_name' => $data['name'],
                    'error_code' => $status_code,
                    'api_response' => $api_response
                ];

                // Чтение существующих ошибок из файла
                $existingErrors = [];
                if (file_exists('errors.json')) {
                    $existingErrors = json_decode(file_get_contents('errors.json'), true) ?? [];
                }

                // Добавление новой ошибки к массиву ошибок
                $existingErrors[] = $errorData;

                // Запись обновленного массива ошибок обратно в файл
                file_put_contents('errors.json', json_encode($existingErrors, JSON_PRETTY_PRINT));
                echo "An error occurred: ";
                echo "Model Name: " . $errorData['model_name'] . "<br>";
                echo "Error Code: " . $errorData['error_code'] . "<br>";
                echo "API Response: " . $errorData['api_response'] . "<br>";
                $errorCount++;
                break;
            }
            echo "Data for model: " . $data['name'] . "\n";
            echo "API Response: " . $api_response . " (Status Code: " . $status_code . ")\n";

        }

        // Вывод результатов
        echo "Total profiles sent: " . $successCount . "\n";
        echo "Total errors occurred: " . $errorCount . "\n";

        // Возвращаем количество успешно отправленных профилей и количество ошибок
        return ['successCount' => $successCount, 'errorCount' => $errorCount];
    }


}
