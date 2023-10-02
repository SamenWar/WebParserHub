<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use simplehtmldom\HtmlWeb;

class GetLinks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:links';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get all links from the specified URL';


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
        $client = new HtmlWeb();
        $html = $client->load($url);


        $letterLinks = [];
        foreach ($html->find('.nv-blk-a-z a') as $link) {
            $letterLinks[] = $link->href;  // Получаем ссылки на все страницы с моделями по алфавиту
        }

        // Выводим ссылки в консоль
        foreach ($letterLinks as $link) {
            $this->info($link);
        }

        return 0;
    }

}
