<?php

namespace App\Console\Commands;
require_once 'vendor/autoload.php';

use App\Services\PhotoDownloader;
use Illuminate\Console\Command;
use GuzzleHttp\Client;
use phpQuery;
use simplehtmldom\HtmlWeb;

class ScrapeModelsServices extends Command
{
    protected $signature = 'scrape:models-services';
    protected $description = 'Scrape models and services from the website';


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
        $url = 'https://fapopedia.net/list/';
        $dateStart = now()->toDateTimeString();
        $dat = $this->getModelsAndServices($url);
        $dateEnd = now()->toDateTimeString();
        print_r($dat);
        $this->info('Downloading photos...');
        // Использование сервиса в роуте, контроллере, job, и т.д.



        $jsonData = file_get_contents(storage_path('data.json'));

        $this->info('Photos downloaded successfully.');
        $this->logParsingState('completed',$dateStart, $dateEnd);  // Логирование завершения парсинга

    }


    private function getModelsAndServices($url)
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

        // Создание экземпляра клиента Guzzle с конфигурацией прокси
        $client = new Client([
            'proxy' => [
                'http'  => $proxyUrl,
                'https' => $proxyUrl,
            ],
        ]);
        $response = $client->get($url);
        $htmlContent = (string) $response->getBody();

        $doc = phpQuery::newDocumentHTML($htmlContent);

        $letterLinks = [];
        foreach ($doc['.nv-blk-a-z a'] as $link) {
            $letterLinks[] = pq($link)->attr('href');
        }

        $data = [];
        $i = 1;

        foreach ($letterLinks as $letterLink) {
            $response = $client->get($letterLink);
            $htmlContent = (string) $response->getBody();
            $html = phpQuery::newDocumentHTML($htmlContent);

            $elements = $html['.shrt-pc'];
            foreach ($elements as $element) {
                $profileUrl = pq($element)->parent()->attr('href');
                $modelName = pq($element)->next()->text();

                $response = $client->get($profileUrl);
                $profileHtmlContent = (string) $response->getBody();
                $profileHtml = phpQuery::newDocumentHTML($profileHtmlContent);

                if ($profileHtml) {
                    $socialLinks = [];
                    foreach ($profileHtml['.md-blk-lnk a'] as $socialLink) {
                        $socialLinks[pq($socialLink)->text()] = pq($socialLink)->attr('href');
                    }

                    $photos = [];
                    foreach ($profileHtml['.shrt-blk a'] as $photo) {
                        $photoUrl = pq($photo)->attr('href');

                        // Получение HTML содержимого по ссылке
                        $photoHtmlContent = file_get_contents($photoUrl);

                        if ($photoHtmlContent === false) {
                            echo "Ошибка при получении содержимого по URL: $photoUrl";
                            continue;
                        }

                        // Парсинг HTML содержимого
                        $photoDoc = phpQuery::newDocument($photoHtmlContent);

                        // Ищем ссылку внутри контейнера с классом 'lrg-pc-blk'
                        $innerLink = $photoDoc['.lrg-pc a']->attr('href');

                        if ($innerLink) {
                            $photos[] = $innerLink;
                        }



                    }
                    if ($i >= 60) {
                        break;
                    }
                    $uniqueId = uniqid();

                    // Ensure the ID is unique by checking it against existing IDs
                    while (array_key_exists($uniqueId, $data)) {
                        $uniqueId = uniqid();
                    }

                    $data[$uniqueId] = [
                        "name" => $modelName,
                        'profileUrl' => $profileUrl,
                        'social' => $socialLinks,
                        'photos' => $photos
                    ];

                    echo "one model $modelName ready $i\n";
                    $i++;
                }

            }
        }

        file_put_contents(storage_path('data.json'), json_encode($data, JSON_PRETTY_PRINT));

        return $data;
    }
    private function logParsingState($state, $dateStart, $dateEnd)
    {
        $client = new Client();
        $uri = $this->apiUrl . '/api/parser/v1/put-log';  // Обновленный URL

          // Пример времени начала

        try {
            $response = $client->post($uri, [
                'headers' => [
                    'Parser-Api-Token' => $this->apiToken, // Использование токена API
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
