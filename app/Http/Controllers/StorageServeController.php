<?php

namespace App\Http\Controllers;

use App\Models\FileStorage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StorageServeController extends Controller
{
    public function __invoke(Request $request, string $path)
    {
        $file = FileStorage::where('path', $path)->first();

        if (! $file) {
            abort(404, 'File not found.');
        }

        $disposition = $request->query('download') ? 'attachment' : 'inline';
        $filename = basename($path);

        return new StreamedResponse(
            function () use ($file) {
                echo $file->contents;
            },
            200,
            [
                'Content-Type' => $file->mime_type ?? 'application/octet-stream',
                'Content-Length' => $file->size,
                'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
                'Cache-Control' => 'private, max-age=300',
            ]
        );
    }
}
