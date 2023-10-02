<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use simplehtmldom\HtmlWeb;

class ScrapeOneModel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scrape:three-models';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scrape information of the first three models from the website';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */

    public function handle()
    {
        $url = 'https://fapopedia.net/list/';
        $data = $this->getModelsAndServices($url);

        print_r($data);
    }

    private function getModelsAndServices($url)
    {
        $client = new HtmlWeb();
        $html = $client->load($url);

        $letterLinks = [];
        foreach ($html->find('.nv-blk-a-z a') as $link) {
            $letterLinks[] = $link->href;  // Получаем ссылки на все страницы с моделями по алфавиту
        }

        $data = [];
        foreach ($letterLinks as $letterLink) {  // Проходим по каждой странице алфавита
            $html = $client->load($letterLink);
            $elements = $html->find('.shrt-pc');  // Селектор карточек моделей

            foreach ($elements as $element) {
                $profileUrl = $element->parent()->href;
                $modelName = $element->next_sibling()->plaintext;  // Извлечение имени модели




                $data[$modelName] = [  // Используйте имя модели как ключ
                    'profileUrl' => $profileUrl,
                ];
            }
        }

        return $data;
    }
}
