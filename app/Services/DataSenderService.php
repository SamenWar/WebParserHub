<?php
namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Exception;

class DataSenderService
{
    private $apiUrl;
    private $apiToken;

    public function __construct()
    {
        $this->apiUrl = config('fapopedia_net.api.url');
        $this->apiToken = config('fapopedia_net.api.token');
    }
    public function sendData($data)
    {

        $client = new Client();
        try {
            $response = $client->post($this->apiUrl . '/api/parser/v1/put-object', [
                'headers' => [
                    "Parser-Api-Token" =>  $this->apiToken,

                ],
                'json' => $data  // Непосредственная отправка данных в формате JSON
            ]);

            return [$response->getStatusCode(), (string)$response->getBody()];
        } catch (RequestException $e) {
            return [$e->getCode(), $e->getMessage()];
        }

    }

}
