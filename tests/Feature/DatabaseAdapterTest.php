<?php

use App\Models\FileStorage;
use App\Storage\DatabaseAdapter;
use League\Flysystem\Config;

beforeEach(function () {
    $this->adapter = new DatabaseAdapter();
});

it('writes and reads a file', function () {
    $this->adapter->write('test/hello.txt', 'Hello World', new Config());

    expect($this->adapter->read('test/hello.txt'))->toBe('Hello World');
    expect($this->adapter->fileExists('test/hello.txt'))->toBeTrue();
});

it('detects mime type for PNG', function () {
    // Minimal 1x1 PNG
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
    $this->adapter->write('test/image.png', $png, new Config());

    $attrs = $this->adapter->mimeType('test/image.png');
    expect($attrs->mimeType())->toBe('image/png');
});

it('returns correct file size', function () {
    $content = str_repeat('x', 1024);
    $this->adapter->write('test/size.txt', $content, new Config());

    $attrs = $this->adapter->fileSize('test/size.txt');
    expect($attrs->fileSize())->toBe(1024);
});

it('deletes a file', function () {
    $this->adapter->write('test/delete-me.txt', 'bye', new Config());
    expect($this->adapter->fileExists('test/delete-me.txt'))->toBeTrue();

    $this->adapter->delete('test/delete-me.txt');
    expect($this->adapter->fileExists('test/delete-me.txt'))->toBeFalse();
});

it('copies a file', function () {
    $this->adapter->write('test/original.txt', 'content', new Config());
    $this->adapter->copy('test/original.txt', 'test/copy.txt', new Config());

    expect($this->adapter->read('test/copy.txt'))->toBe('content');
    expect($this->adapter->fileExists('test/original.txt'))->toBeTrue();
});

it('moves a file', function () {
    $this->adapter->write('test/before.txt', 'content', new Config());
    $this->adapter->move('test/before.txt', 'test/after.txt', new Config());

    expect($this->adapter->fileExists('test/before.txt'))->toBeFalse();
    expect($this->adapter->read('test/after.txt'))->toBe('content');
});

it('overwrites existing file on write', function () {
    $this->adapter->write('test/overwrite.txt', 'v1', new Config());
    $this->adapter->write('test/overwrite.txt', 'v2', new Config());

    expect($this->adapter->read('test/overwrite.txt'))->toBe('v2');
    expect(FileStorage::where('path', 'test/overwrite.txt')->count())->toBe(1);
});

it('deletes directory and all contents', function () {
    $this->adapter->write('dir/a.txt', 'a', new Config());
    $this->adapter->write('dir/b.txt', 'b', new Config());
    $this->adapter->write('other/c.txt', 'c', new Config());

    $this->adapter->deleteDirectory('dir');

    expect($this->adapter->fileExists('dir/a.txt'))->toBeFalse();
    expect($this->adapter->fileExists('dir/b.txt'))->toBeFalse();
    expect($this->adapter->fileExists('other/c.txt'))->toBeTrue();
});

it('lists contents of a directory', function () {
    $this->adapter->write('list/file1.txt', 'a', new Config());
    $this->adapter->write('list/file2.txt', 'b', new Config());
    $this->adapter->write('list/sub/file3.txt', 'c', new Config());

    $shallow = iterator_to_array($this->adapter->listContents('list', false));
    expect(count($shallow))->toBe(2);

    $deep = iterator_to_array($this->adapter->listContents('list', true));
    expect(count($deep))->toBe(3);
});

it('generates a temporary signed URL', function () {
    $this->adapter->write('test/signed.pdf', '%PDF-test', new Config());

    $url = $this->adapter->getTemporaryUrl('test/signed.pdf', now()->addMinutes(10));

    expect($url)->toContain('/storage/serve/');
    expect($url)->toContain('signature=');
});
