<?php
// app/Services/DataSenderService.php

namespace App\Services;

use GuzzleHttp\Client;

class DataSenderService {
    private $apiUrl;
    private $token;

    public function __construct() {
        $this->apiUrl = config('fapopedia_net.api.url');
        $this->token = config('fapopedia_net.api.token');
    }

    public function sendData($data) {
        $client = new Client();
        try {
            $response = $client->post($this->apiUrl . '/api/parser/v1/put-object', [
                'headers' => [
                    "Authorization" => "Bearer " . $this->token,
                    "Content-Type" => "application/json"
                ],
                'json' => $data
            ]);
            return [$response->getStatusCode(), (string)$response->getBody()];
        } catch (\Exception $e) {
            return [500, $e->getMessage()];
        }
    }

    public function processDataAndSend() {
        $jsonData = file_get_contents(storage_path('data.json'));
        $modelsData = json_decode($jsonData, true);

        foreach ($modelsData as $modelName => $modelData) {
            $aggregatedData = [
                "object" => [
                    "name" => $modelName,
                    "profile_url" => $modelData['profileUrl'],
                    "social" => $modelData['social'],
                    "photos" => $modelData['photos'],
                ]
            ];

            list($status_code, $api_response) = $this->sendData($aggregatedData);

            echo "Data for model: " . $modelName . "\n";
            echo "API Response: " . $api_response . " (Status Code: " . $status_code . ")\n";
        }
    }
}
