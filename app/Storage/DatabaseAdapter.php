<?php

namespace App\Storage;

use App\Models\FileStorage;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;

class DatabaseAdapter implements FilesystemAdapter
{
    public function fileExists(string $path): bool
    {
        return FileStorage::where('path', $path)->exists();
    }

    public function directoryExists(string $path): bool
    {
        return FileStorage::where('path', 'like', rtrim($path, '/') . '/%')->exists();
    }

    public function write(string $path, string $contents, Config $config): void
    {
        FileStorage::updateOrCreate(
            ['path' => $path],
            [
                'contents' => $contents,
                'mime_type' => $this->detectMimeType($path, $contents),
                'size' => strlen($contents),
                'visibility' => $config->get('visibility', 'private'),
                'metadata' => $config->get('metadata'),
            ]
        );
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->write($path, stream_get_contents($contents), $config);
    }

    public function read(string $path): string
    {
        $file = FileStorage::where('path', $path)->first();

        if (! $file) {
            throw UnableToReadFile::fromLocation($path, 'File not found in database.');
        }

        return $file->contents;
    }

    public function readStream(string $path)
    {
        $contents = $this->read($path);
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $contents);
        rewind($stream);

        return $stream;
    }

    public function delete(string $path): void
    {
        FileStorage::where('path', $path)->delete();
    }

    public function deleteDirectory(string $path): void
    {
        FileStorage::where('path', 'like', rtrim($path, '/') . '/%')->delete();
    }

    public function createDirectory(string $path, Config $config): void
    {
        // Directories are virtual in database storage — no-op.
    }

    public function setVisibility(string $path, string $visibility): void
    {
        FileStorage::where('path', $path)->update(['visibility' => $visibility]);
    }

    public function visibility(string $path): FileAttributes
    {
        $file = $this->getFileRecord($path);

        return new FileAttributes($path, null, $file->visibility);
    }

    public function mimeType(string $path): FileAttributes
    {
        $file = $this->getFileRecord($path);

        return new FileAttributes($path, null, null, null, $file->mime_type);
    }

    public function lastModified(string $path): FileAttributes
    {
        $file = $this->getFileRecord($path);

        return new FileAttributes($path, null, null, $file->updated_at?->getTimestamp());
    }

    public function fileSize(string $path): FileAttributes
    {
        $file = $this->getFileRecord($path);

        return new FileAttributes($path, $file->size);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        $prefix = rtrim($path, '/');
        $prefix = $prefix === '' ? '' : $prefix . '/';

        $query = FileStorage::where('path', 'like', $prefix . '%')
            ->select(['path', 'mime_type', 'size', 'visibility', 'updated_at']);

        foreach ($query->cursor() as $file) {
            if (! $deep) {
                $relativePath = substr($file->path, strlen($prefix));
                if (str_contains($relativePath, '/')) {
                    continue;
                }
            }

            yield new FileAttributes(
                $file->path,
                $file->size,
                $file->visibility,
                $file->updated_at?->getTimestamp(),
                $file->mime_type,
            );
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $updated = FileStorage::where('path', $source)->update(['path' => $destination]);

        if (! $updated) {
            throw UnableToMoveFile::fromLocationTo($source, $destination);
        }
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $file = FileStorage::where('path', $source)->first();

        if (! $file) {
            throw UnableToCopyFile::fromLocationTo($source, $destination);
        }

        FileStorage::create([
            'path' => $destination,
            'contents' => $file->contents,
            'mime_type' => $file->mime_type,
            'size' => $file->size,
            'visibility' => $config->get('visibility', $file->visibility),
            'metadata' => $file->metadata,
        ]);
    }

    /**
     * Generate a temporary signed URL for serving a file.
     *
     * Laravel's FilesystemAdapter checks method_exists($this->adapter, 'getTemporaryUrl')
     * and delegates to it, making Storage::disk('database')->temporaryUrl() work transparently.
     */
    public function getTemporaryUrl(string $path, \DateTimeInterface $expiration, array $options = []): string
    {
        return \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'storage.serve',
            $expiration,
            ['path' => $path]
        );
    }

    private function getFileRecord(string $path): FileStorage
    {
        $file = FileStorage::where('path', $path)
            ->select(['path', 'mime_type', 'size', 'visibility', 'updated_at'])
            ->first();

        if (! $file) {
            throw UnableToRetrieveMetadata::create($path, 'file_not_found');
        }

        return $file;
    }

    private function detectMimeType(string $path, string $contents): ?string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->buffer($contents);

        if ($detected && $detected !== 'application/octet-stream') {
            return $detected;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'csv' => 'text/csv',
            default => 'application/octet-stream',
        };
    }
}
