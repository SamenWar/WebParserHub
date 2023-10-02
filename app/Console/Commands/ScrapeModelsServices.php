<?php

namespace App\Console\Commands;
require_once 'vendor/autoload.php';
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

    public function handle()
    {
        $url = 'https://fapopedia.net/list/';
        //$this->logParsingState('started');  // Логирование начала парсинга
        $data = $this->getModelsAndServices($url);
        // $this->logParsingState('completed');  // Логирование завершения парсинга
        print_r($data);
    }


    private function getModelsAndServices($url)
    {
        $client = new Client();
        $response = $client->get($url);
        $htmlContent = (string) $response->getBody();

        $doc = phpQuery::newDocumentHTML($htmlContent);

        $letterLinks = [];
        foreach ($doc['.nv-blk-a-z a'] as $link) {
            $letterLinks[] = pq($link)->attr('href');
        }

        $data = [];
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
                    foreach ($profileHtml['.shrt-blk img'] as $photo) {
                        $photos[] = pq($photo)->attr('src');
                    }

                    while ($nextLink = $profileHtml['.nv-blk a:last']) {
                        if (strpos(pq($nextLink)->text(), 'Next') !== false) {
                            $response = $client->get(pq($nextLink)->attr('href'));
                            $nextHtmlContent = (string) $response->getBody();
                            $nextHtml = phpQuery::newDocumentHTML($nextHtmlContent);

                            foreach ($nextHtml['.shrt-blk img'] as $photo) {
                                $photos[] = pq($photo)->attr('src');
                            }
                        } else {
                            break;
                        }
                    }

                    $data[$modelName] = [
                        'profileUrl' => $profileUrl,
                        'social' => $socialLinks,
                        'photos' => $photos
                    ];
                }
            }
        }

        file_put_contents(storage_path('data.json'), json_encode($data, JSON_PRETTY_PRINT));

        return $data;
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
                    'Parser-Api-Token' => $this->apiToken,  // Обновленный заголовок
                ],
                'json' => [
                    'errors' => $state == 'completed' ? 'No errors' : '',  // Пример поля ошибок
                    'site_name' => 'YourSiteName',  // Пример имени сайта
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
