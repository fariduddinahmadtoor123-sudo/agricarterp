<?php

namespace App\Services\Backup\Storage;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleDriveBackupStorage
{
    public function isConfigured(): bool
    {
        return (bool) config('backup.google_drive.enabled')
            && filled(config('backup.google_drive.service_account_json'))
            && filled(config('backup.google_drive.folder_id'));
    }

    public function uploadFile(string $absolutePath, string $fileName): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Google Drive backup storage is not configured.');
        }

        $token = $this->accessToken();
        $folderId = (string) config('backup.google_drive.folder_id');
        $mime = 'application/zip';
        $size = filesize($absolutePath) ?: 0;

        $metadata = json_encode([
            'name' => $fileName,
            'parents' => [$folderId],
        ], JSON_THROW_ON_ERROR);

        $boundary = 'agricart_backup_' . bin2hex(random_bytes(8));
        $body = "--{$boundary}\r\n"
            . "Content-Type: application/json; charset=UTF-8\r\n\r\n"
            . $metadata . "\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: {$mime}\r\n\r\n"
            . file_get_contents($absolutePath)
            . "\r\n--{$boundary}--";

        $response = Http::withToken($token)
            ->withHeaders(['Content-Type' => 'multipart/related; boundary=' . $boundary])
            ->withBody($body, 'multipart/related; boundary=' . $boundary)
            ->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id,size');

        if (! $response->successful()) {
            throw new RuntimeException('Google Drive upload failed: ' . $response->body());
        }

        $fileId = (string) ($response->json('id') ?? '');

        if ($fileId === '') {
            throw new RuntimeException('Google Drive upload did not return a file id.');
        }

        if ($size > 0 && (int) $response->json('size') === 0) {
            throw new RuntimeException('Google Drive upload appears incomplete.');
        }

        return $fileId;
    }

    protected function accessToken(): string
    {
        $jsonPath = (string) config('backup.google_drive.service_account_json');

        if (! is_file($jsonPath)) {
            throw new RuntimeException('Google Drive service account JSON file was not found.');
        }

        $credentials = json_decode(file_get_contents($jsonPath), true, 512, JSON_THROW_ON_ERROR);
        $clientEmail = (string) ($credentials['client_email'] ?? '');
        $privateKey = (string) ($credentials['private_key'] ?? '');

        if ($clientEmail === '' || $privateKey === '') {
            throw new RuntimeException('Google Drive service account JSON is invalid.');
        }

        $now = time();
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $claim = $this->base64UrlEncode(json_encode([
            'iss' => $clientEmail,
            'scope' => 'https://www.googleapis.com/auth/drive.file',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_THROW_ON_ERROR));

        $unsigned = $header . '.' . $claim;
        openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        $jwt = $unsigned . '.' . $this->base64UrlEncode($signature);

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Google Drive authentication failed: ' . $response->body());
        }

        $token = (string) ($response->json('access_token') ?? '');

        if ($token === '') {
            throw new RuntimeException('Google Drive authentication did not return an access token.');
        }

        return $token;
    }

    protected function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
