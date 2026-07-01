<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;

class SupabaseStorage
{
    private static function base(): string
    {
        return rtrim(config('services.supabase.url'), '/');
    }

    private static function key(): string
    {
        return config('services.supabase.key');
    }

    private static function bucket(): string
    {
        return config('services.supabase.bucket', 'kpspam-files');
    }

    public static function upload(string $path, string $fileData, string $ext = 'jpg'): string
    {
        $mimeMap = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];
        $mime = $mimeMap[strtolower($ext)] ?? 'image/jpeg';

        $endpoint = self::base() . '/storage/v1/object/' . self::bucket() . '/' . ltrim($path, '/');

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . self::key(),
            'Content-Type'  => $mime,
            'x-upsert'      => 'true',
        ])->withBody($fileData, $mime)->post($endpoint);

        if (!$response->successful()) {
            throw new Exception('Gagal upload foto: ' . $response->body());
        }

        return self::base() . '/storage/v1/object/public/' . self::bucket() . '/' . ltrim($path, '/');
    }

    public static function buildUrl(?string $pathOrUrl): ?string
    {
        if (!$pathOrUrl) return null;
        if (str_starts_with($pathOrUrl, 'http')) return $pathOrUrl;
        return asset('storage/' . $pathOrUrl);
    }
}
