<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MediaRobot;  // Импортируйте класс MediaRobot, если он в отдельном файле

class MediaRobotCommand extends Command
{
    protected $signature = 'media:robot';
    protected $description = 'Run the media robot';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $mediaRobot = new MediaRobot();
        $mediaRobot->run();
    }
}

