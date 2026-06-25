<?php

namespace App\Http\Controllers;

use App\Models\Backup;
use App\Services\Backup\Storage\LocalBackupStorage;
use App\Support\Settings\BackupAuthorization;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupDownloadController extends Controller
{
    public function __invoke(Request $request, Backup $backup): StreamedResponse
    {
        abort_unless(BackupAuthorization::canDownload(), 403);
        abort_unless($backup->isCompleted() && filled($backup->local_path), 404);

        $storage = app(LocalBackupStorage::class);
        $stream = $storage->downloadStream((string) $backup->local_path);

        return response()->streamDownload(function () use ($stream): void {
            fpassthru($stream);
        }, $backup->file_name ?? 'backup.zip', [
            'Content-Type' => 'application/zip',
        ]);
    }
}
