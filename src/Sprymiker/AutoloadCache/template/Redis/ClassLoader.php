<?php

namespace Composer\Autoload;

use Redis;

class ClassLoader extends ClassLoaderOriginal
{
    protected const SEPARATOR = ':';
    protected const BULK_SIZE = 100;

    /**
     * @var bool
     */
    protected $enabled = false;

    /**
     * @var string
     */
    protected $redisHost = 'localhost';

    /**
     * @var int
     */
    protected $redisPort = 6379;

    /**
     * @var int
     */
    protected $redisDatabase = 0;

    /**
     * @var string
     */
    protected $keyPrefix = 'autoload' . self::SEPARATOR;

    /**
     * @var string[]
     */
    protected $cacheBuffer = [];

    public function __construct()
    {
        $this->enabled = getenv('COMPOSER_AUTOLOAD_CACHE_ENABLED');
        $this->redisHost = getenv('COMPOSER_AUTOLOAD_CACHE_REDIS_DATABASE') ?: $this->redisHost;
        $this->redisPort = getenv('COMPOSER_AUTOLOAD_CACHE_REDIS_DATABASE') ?: $this->redisPort;
        $this->redisDatabase = getenv('COMPOSER_AUTOLOAD_CACHE_REDIS_DATABASE') ?: $this->redisDatabase;

        if ($this->enabled) {
            $this->loadCache();

            // `__destruct` does not work for this class
            register_shutdown_function([$this, 'flushCache']);
        }
    }

    protected function findFileWithExtension($class, $ext)
    {
        $file = parent::findFileWithExtension($class, $ext);

        if ($this->enabled) {
            $this->putInCache($class, $file);
        }

        return $file;
    }

    /**
     * @param string $class
     * @param string|false $file
     */
    protected function putInCache(string $class, $file): void
    {
        $cacheKey = implode(static::SEPARATOR, [
            $this->keyPrefix,
            $file ? realpath($file) : '',
            str_replace('\\', '/', $class)
        ]);
        $this->cacheBuffer[$cacheKey] = $class;

        if (count($this->cacheBuffer) >= static::BULK_SIZE) {
            $this->flushCache();
        }
    }

    /**
     * @return void
     */
    protected function flushCache(): void
    {
        if (count($this->cacheBuffer) === 0) {
            return;
        }

        if ($redis = $this->connectToRedis()) {
            $redis->mset($this->cacheBuffer);
            $redis->close();
        }

        $this->cacheBuffer = [];
    }

    /**
     * @return void
     */
    protected function loadCache(): void
    {
        if ($redis = $this->connectToRedis()) {
            $keys = $redis->keys('*');
            if (!empty($keys)) {
                $values = $redis->mget($keys);
                $map = array_map(function ($fileAndClass) {
                    return explode(':', $fileAndClass)[1] ?: false;
                },
                    array_flip(array_combine($keys, $values))
                );
                $this->addClassMap($map);
            }
            $redis->close();
        }
    }

    /**
     * @return \Redis|null
     */
    protected function connectToRedis(): ?Redis
    {
        $redis = new Redis();
        if ($redis->pconnect($this->redisHost, $this->redisPort)) {
            $redis->select($this->redisDatabase);

            return $redis;
        }

        return null;
    }
}
