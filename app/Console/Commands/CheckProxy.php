<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class CheckProxy extends Command
{
    protected $signature = 'check:proxy';
    protected $description = 'Check if a proxy server is working';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
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



        $this->checkProxy($proxyUrl);
    }

    private function checkProxy($proxyUrl)
    {

        $client = new Client();

        try {
            $response = $client->get('http://www.google.com', [
                'proxy' => [
                    'http'  => $proxyUrl,
                    'https' => $proxyUrl,
                ],
                'timeout' => 13,
            ]);

            if ($response->getStatusCode() == 200) {
                $this->info("Proxy is working");
            } else {
                $this->error("Received unexpected status code {$response->getStatusCode()}: Proxy might not be working properly");
            }
        } catch (RequestException $e) {
            $this->error("An error occurred: {$e->getMessage()}");
        }
    }

}
