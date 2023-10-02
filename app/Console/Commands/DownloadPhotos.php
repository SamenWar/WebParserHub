<?php
// app/Console/Commands/DownloadPhotos.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PhotoDownloader;

class DownloadPhotos extends Command
{
    protected $signature = 'photos:download';
    protected $description = 'Download all photos from data.json';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(PhotoDownloader $photoDownloader)
    {
        $this->info('Downloading photos...');

        $jsonData = file_get_contents(storage_path('data.json'));
        $data = json_decode($jsonData, true);

        $photoDownloader->downloadAllPhotos($data);

        $this->info('Photos downloaded successfully.');
    }
}
