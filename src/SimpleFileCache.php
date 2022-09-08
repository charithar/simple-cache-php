<?php

namespace Charithar\SimpleCache;


use Charithar\SimpleCache\Adapter\FileAdapter;
use Charithar\SimpleCache\Adapter\FileAdapterInterface;
use Charithar\SimpleCache\Exception\CacheException;
use Charithar\SimpleCache\Exception\InvalidArgumentException;
use Psr\SimpleCache\CacheInterface;

class SimpleFileCache implements CacheInterface
{

    /** @var string */
    protected $storagePath;

    /** @var string */
    protected $name;

    /** @var FileAdapterInterface */
    protected $fileAdapter;


    public function __construct(string $name = 'default', string $storagePath = '/tmp', FileAdapterInterface $fileAdapter = null)
    {
        $this->fileAdapter = $fileAdapter ?? new FileAdapter();
        $this->setName($name);
        $this->setStoragePath($storagePath);
    }

    /**
     * @return string
     */
    public function getStoragePath(): string
    {
        return $this->storagePath;
    }

    /**
     * @param string $storagePath
     */
    public function setStoragePath(string $storagePath): void
    {
        if (!$this->fileAdapter->isDir($storagePath)) {
            throw new InvalidArgumentException('Storage path is not a valid directory');
        }
        else if (!$this->fileAdapter->fileExists($storagePath)) {
            throw new InvalidArgumentException('Storage path does not exist');
        }
        else if (!$this->fileAdapter->isWritable($storagePath)) {
            throw new InvalidArgumentException('Storage path is not writable');
        }

        $this->storagePath = rtrim($storagePath, '\\/');
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->validateIdentifier($name);
        $this->name = $name;
    }

    public function get($key, $default = null)
    {
        return $this->getItem($key, $default);
    }

    public function set($key, $value, $ttl = null)
    {
        return $this->setItem($key, $value, $ttl);
    }

    public function delete($key)
    {
        return $this->deleteItem($key);
    }

    public function clear()
    {
        $this->fileAdapter->deleteDir($this->getNamespacePath());
    }

    public function getMultiple($keys, $default = null)
    {
        $returnData = [];

        foreach ($keys as $key) {
            $returnData[$key] = $this->getItem($key, $default);
        }

        return $returnData;
    }

    public function setMultiple($values, $ttl = null)
    {
        $success = true;
        foreach ($values as $key => $value) {
            if (! $this->set($key, $value, $ttl)) {
                $success = false;
            }
        }

        return $success;
    }

    public function deleteMultiple($keys)
    {
        $success = true;
        foreach ($keys as $key) {
            if (! $this->delete($key)) {
                $success = false;
            }
        }

        return $success;
    }

    public function has($key)
    {
        return $this->hasItem($key);
    }

    protected function validateIdentifier(string $identifier)
    {
        if (! preg_match('/^[a-zA-Z0-9_\-.]{1,64}$/', $identifier)) {
            throw new InvalidArgumentException('Identifier is not valid. Only a-zA-Z0-9_-. characters allowed. Should not exceed 64 characters');
        }
    }

    protected function getItem(string $key, $default = null)
    {
        if ($this->hasItem($key)) {
            $value = $this->fileAdapter->readFile($this->getItemPath($key));
            if ($value === false) {
                throw new CacheException("Failed read item from cache");
            }

            try {
                $item = CacheItem::fromItem(json_decode($value, true, 512, JSON_THROW_ON_ERROR));
                if (!$item->isExpired()) {
                    return $item->getValue($default);
                }
                else {
                    $this->deleteItem($key);
                }
            }
            catch (\Throwable $e) {
                $this->deleteItem($key);
                throw new CacheException("Error reading cache item");
            }
        }

        return $default;
    }

    protected function hasItem(string $key): bool
    {
        $itemPath = $this->getItemPath($key);
        return $this->fileAdapter->fileExists($itemPath);
    }

    protected function setItem($key, $value, $ttl = null)
    {
        $item = new CacheItem($value, $ttl);
        return $this->fileAdapter->writeFile($this->getItemPath($key), json_encode($item)) !== false;
    }

    protected function deleteItem($key): bool
    {
        if ($this->hasItem($key)) {
            $itemPath = $this->getItemPath($key);
            return $this->fileAdapter->deleteFile($itemPath);
        }

        return true;
    }

    protected function getNamespacePath(): string
    {
        return $this->getStoragePath() . DIRECTORY_SEPARATOR . $this->getName();
    }

    protected function getItemPath(string $key): string
    {
        $path = $this->getNamespacePath();
        if (!$this->fileAdapter->fileExists($path)) {
            $this->fileAdapter->createDir($path);
        }

        return $path . DIRECTORY_SEPARATOR . hash('sha256', $key);
    }

}