<?php

namespace Charithar\SimpleCache;

use Charithar\SimpleCache\Exception\InvalidArgumentException;

class CacheItem implements \JsonSerializable
{

    public const ITEM_VERSION = 'version';

    public const ITEM_VALUE = 'value';

    public const ITEM_CREATED_AT = 'created_at';

    public const ITEM_EXPIRES_AT = 'expires_at';

    public const VERSION = '1.0';

    /** @var mixed */
    protected $value;

    /** @var int|null */
    protected $expiresAt;

    /** @var int */
    protected $createdAt;


    public function __construct($value, $ttl = null)
    {
        $this->checkValue($value);
        $this->value = $value;
        $this->createdAt = time();
        $this->expiresAt = $this->normalizeTtl($ttl);
    }

    public function getValue($default = null)
    {
        if ($this->isExpired()) {
            return $default;
        }

        return $this->value;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt !== null && $this->expiresAt < time();
    }

    public function jsonSerialize()
    {
        return $this->toItem();
    }

    public static function fromItem(array $item): CacheItem
    {
        self::checkItem($item);

        $value = $item[self::ITEM_VALUE];
        $value = unserialize($value);

        $ttl = $item[self::ITEM_EXPIRES_AT];
        if (!is_null($ttl)) {
            $ttl = $ttl - time();
        }

        return new CacheItem($value, $ttl);
    }

    public function toItem(): array
    {
        return [
            self::ITEM_VALUE => serialize($this->value),
            self::ITEM_EXPIRES_AT => $this->expiresAt,
            self::ITEM_CREATED_AT => $this->createdAt,
            self::ITEM_VERSION => self::VERSION
        ];
    }

    public static function isValidItem(array $item): bool
    {
        if (array_diff_key(array_flip([self::ITEM_VALUE, self::ITEM_VERSION, self::ITEM_CREATED_AT, self::ITEM_EXPIRES_AT]), $item)) {
            return false;
        }

        if ($item[self::ITEM_VERSION] != self::VERSION) {
            return false;
        }

        $expiresAt = $item[self::ITEM_EXPIRES_AT];
        if ((!is_null($expiresAt)) && (!is_int($expiresAt))) {
            return false;
        }

        return true;
    }

    public static function checkItem(array $item)
    {
        if (!self::isValidItem($item)) {
            throw new InvalidArgumentException('Invalid item');
        }
    }

    protected function checkValue($value)
    {
        $type = gettype($value);

        $invalidTypes = [
            'resource',
            'resource (closed)',
            'unknown type'
        ];

        if (in_array($type, $invalidTypes)) {
            throw new InvalidArgumentException('Invalid value give');
        }
    }

    protected function normalizeTtl($ttl): ?int
    {
        if (is_null($ttl)) {
            return null;
        }

        if (is_int($ttl)) {
            return time() + $ttl;
        }

        if ($ttl instanceof \DateInterval) {
            return (new \DateTimeImmutable())->add($ttl)->getTimestamp();
        }

        throw new InvalidArgumentException('Invalid value given for TTL');
    }

}