<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Exception;

class S3FileUploaderService
{
    public function uploadFileToS3($filePath)
    {
        try {
            // Построение полного пути к файлу
            $fullPath = storage_path('app/' . $filePath);

            // Проверка существования файла
            if (!file_exists($fullPath)) {
                throw new Exception("File does not exist: {$fullPath}");
            }

            // Извлечение имени файла из пути
            $fileName = basename($fullPath);

            // Чтение содержимого файла
            $fileContent = file_get_contents($fullPath);

            // Загрузка файла на S3
            Storage::disk('s3')->put($fileName, $fileContent, 'public');

            // Получение URL загруженного файла
            $fileUrl = Storage::disk('s3')->url($fileName);

            return [
                'success' => true,
                'message' => "File {$fileName} uploaded successfully to S3.",
                'fileUrl' => $fileUrl
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => "An error occurred: " . $e->getMessage(),
                'fileUrl' => null
            ];
        }
    }
}
