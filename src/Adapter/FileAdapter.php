<?php

namespace Charithar\SimpleCache\Adapter;


class FileAdapter implements FileAdapterInterface
{

    public function isDir(string $path): bool
    {
        return is_dir($path);
    }

    public function fileExists(string $path): bool
    {
        return file_exists($path);
    }

    public function isWritable(string $path): bool
    {
        return is_writable($path);
    }

    public function createDir(string $path, int $permission = 0777): bool
    {
        return mkdir($path, $permission, true);
    }

    public function deleteDir(string $path): bool
    {
        array_map('unlink', glob(rtrim($path, '\\/') . "/*"));
        return true;
    }

    public function deleteFile(string $path): bool
    {
        return unlink($path);
    }

    public function readFile(string $path)
    {
        file_get_contents($path);
    }

    public function writeFile(string $path, $content): bool
    {
        return file_put_contents($path, $content) !== false;
    }
}