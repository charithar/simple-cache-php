<?php

interface FileAdapterInterface
{

    public function isDir(string $path): bool;

    public function fileExists(string $path): bool;

    public function isWritable(string $path): bool;

    public function createDir(string $path, int $permission = 0777): bool;

    public function deleteDir(string $path): bool;

    public function deleteFile(string $path): bool;

    public function readFile(string $path);

    public function writeFile(string $path, $content): bool;

}