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

        $dataSender = app('DataSender');
        $dataSender->processDataAndSend();


        $jsonData = file_get_contents(storage_path('data.json'));
        $data = json_decode($jsonData, true);

        $photoDownloader->downloadAllPhotos($data);

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
                    foreach ($profileHtml['.shrt-blk img'] as $photo) {
                        $photos[] = pq($photo)->attr('src');
                    }



                    $pageCounter = 1;  // Инициализация счетчика страниц

                    while ($nextLink = $profileHtml['.nv-blk a:last']) {
                        $href = pq($nextLink)->attr('href');  // Получаем значение атрибута href

                        // Проверяем, содержит ли ссылка действительный URL и текст "Next"
                        if ($href && filter_var($href, FILTER_VALIDATE_URL) && strpos(pq($nextLink)->text(), 'Next') !== false) {
                            try {
                                $response = $client->get($href);
                                $nextHtmlContent = (string) $response->getBody();
                                $nextHtml = phpQuery::newDocumentHTML($nextHtmlContent);

                                echo "Processing page: $pageCounter\n";  // Вывод номера текущей страницы

                                $a = 1;
                                foreach ($nextHtml['.shrt-blk img'] as $photo) {

                                    // Извлекаем ссылку из текущего блока и получаем HTML следующей страницы
                                    $link = pq($photo)->parents('.shrt-blk')->find('a')->attr('href');

                                    $nextPageHtml = file_get_contents($link);

                                    // Ищем следующую ссылку на новой странице и получаем её HTML
                                    $nextDocument = phpQuery::newDocument($nextPageHtml);
                                    $nextLink = $nextDocument->find('YOUR_SELECTOR_FOR_NEXT_LINK')->attr('href');
                                    $finalPageHtml = file_get_contents($nextLink);

                                    // Ищем изображение на последней странице и сохраняем его src
                                    $finalDocument = phpQuery::newDocument($finalPageHtml);
                                    $finalImageSrc = $finalDocument->find('img')->attr('src');
                                    $photos[] = $finalImageSrc;

                                    echo "$a\n";
                                    $a++;
                                }


                                $pageCounter++;  // Увеличиваем счетчик страниц после обработки текущей страницы

                                // Обновляем $profileHtml для следующей итерации
                                $profileHtml = $nextHtml;
                            } catch (\Exception $e) {
                                // Логирование ошибок при отправке запроса или обработке ответа
                                echo "Error processing page $pageCounter: {$e->getMessage()}\n";
                                break;  // Выход из цикла при возникновении ошибки
                            }
                        } else {
                            break;  // Выход из цикла, если условия не выполняются
                        }
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
