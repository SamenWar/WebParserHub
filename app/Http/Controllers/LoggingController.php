<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LoggingController extends Controller
{
    public function logScraping(Request $request)
    {
        // Получение данных из запроса
        $data = $request->all();

        // Логирование данных (опционально)
        Log::info('Scraping log: ', $data);

        // Получение существующих данных из файла JSON
        $filePath = storage_path('logs/scraping.json');
        $existingData = [];
        if (file_exists($filePath)) {
            $existingDataJson = file_get_contents($filePath);
            $existingData = json_decode($existingDataJson, true);
        }

        // Добавление новых данных в существующий массив
        $existingData['scraping'][] = $data;

        // Кодирование данных в формат JSON и запись в файл
        $jsonData = json_encode($existingData, JSON_PRETTY_PRINT);
        file_put_contents($filePath, $jsonData);

        return response()->json(['message' => 'Scraping logged successfully']);
    }

    public function logDownloading(Request $request)
    {
        Log::info('Downloading log: ', $request->all());
        return response()->json(['message' => 'Downloading logged successfully']);
    }

    public function logUploading(Request $request)
    {
        Log::info('Uploading log: ', $request->all());
        return response()->json(['message' => 'Uploading logged successfully']);
    }
}
