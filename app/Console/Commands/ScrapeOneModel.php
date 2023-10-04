<?php

namespace App\Console\Commands;

use GuzzleHttp\Client;
use Illuminate\Console\Command;
use phpQuery;

/**
 * @property $apiUrl
 * @property $apiToken
 */
class ScrapeOneModel extends Command
{
    protected $signature = 'scrape:three-models';
    protected $description = 'Scrape information of the first three models from the website';

    public function __construct()
    {

        parent::__construct();
    }

    public function handle()
    {
        $url = 'https://fapopedia.net/list/';
        $this->logParsingState('started');  // Логирование начала парсинга
        $data = $this->getModelsAndServices($url);
        $this->logParsingState('completed');  // Логирование завершения парсинга
        print_r($data);
    }

    private function getModelsAndServices($url)
    {
        $proxyConfig = [
            'host' => config('fapopedia_net.proxy.host'),
            'port' => config('fapopedia_net.proxy.port'),
            'user' => config('fapopedia_net.proxy.user'),
            'pass' => config('fapopedia_net.proxy.password'),
        ];

        if (!isset($proxyConfig['host'], $proxyConfig['port'])) {
            throw new \Exception('Incomplete proxy configuration.');
        }

        $proxyUrl = isset($proxyConfig['user'], $proxyConfig['pass']) && $proxyConfig['user'] && $proxyConfig['pass']
            ? sprintf('http://%s:%s@%s:%d', $proxyConfig['user'], $proxyConfig['pass'], $proxyConfig['host'], $proxyConfig['port'])
            : sprintf('http://%s:%d', $proxyConfig['host'], $proxyConfig['port']);

        $client = new Client([
            'proxy' => [
                'http'  => $proxyUrl,
                'https' => $proxyUrl,
            ],
        ]);

        try {
            $response = $client->get($url);
            $htmlContent = (string) $response->getBody();
            $html = phpQuery::newDocumentHTML($htmlContent);
        } catch (\Exception $e) {
            // Обработка ошибок запроса или парсинга
            // Возможно, вы захотите залогировать ошибку и продолжить выполнение скрипта
            return [];
        }

        $letterLinks = [];
        foreach ($html->find('.nv-blk-a-z a') as $link) {
            $href = pq($link)->attr('href');
            if ($href) {
                $letterLinks[] = $href;
            }
        }

        $data = [];
        $i = 1;
        foreach ($letterLinks as $letterLink) {
            try {
                $response = $client->get($letterLink);
                $pageContent = (string) $response->getBody();
                $pageHtml = phpQuery::newDocumentHTML($pageContent);
            } catch (\Exception $e) {
                // Обработка ошибок запроса или парсинга для каждой страницы
                // Возможно, вы захотите залогировать ошибку и продолжить выполнение скрипта
                continue;
            }

            foreach ($pageHtml->find('.shrt-pc') as $element) {
                $profileUrl = pq($element)->parent()->attr('href');
                $modelName = pq($element)->next()->text();

                $data[$modelName] = [
                    'profileUrl' => $profileUrl,
                ];

                echo "one model ready $i\n";
                $i++;
            }
        }

        var_dump($data);
        var_dump(count($data));
    }

    private function logParsingState($state)
    {
        $client = new Client();
        $uri = $this->apiUrl . '/api/parser/v1/put-log';  // Обновленный URL

        $dateStart = now()->toDateTimeString();  // Пример времени начала
        $dateEnd = now()->addHour()->toDateTimeString();  // Пример времени окончания, добавив 1 час

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
            if ($response->getStatusCode() != 201) {  // Обновленный код статуса
                throw new \Exception('Failed to log parsing state: ' . $response->getBody());
            }
        } catch (\Exception $e) {
            // Обработка ошибок
            echo 'Error: ' . $e->getMessage() . PHP_EOL;
        }
    }

}
