<?php

namespace Composer\Autoload;

use Redis;

include __DIR__ . DIRECTORY_SEPARATOR . 'ClassLoaderOriginal.php';

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
    protected $keyPrefix = 'autoload';

    /**
     * @var string[]
     */
    protected $cachedClassMap = [];

    /**
     * @var string[]
     */
    protected $cacheBuffer = [];

    public function __construct()
    {
        $this->enabled = (bool)getenv('COMPOSER_AUTOLOAD_CACHE_ENABLED');
        $this->redisHost = getenv('COMPOSER_AUTOLOAD_CACHE_REDIS_HOST') ?: $this->redisHost;
        $this->redisPort = getenv('COMPOSER_AUTOLOAD_CACHE_REDIS_PORT') ?: $this->redisPort;
        $this->redisDatabase = getenv('COMPOSER_AUTOLOAD_CACHE_REDIS_DATABASE') ?: $this->redisDatabase;

        if ($this->enabled) {
            $this->loadCache();
        }
    }

    public function __destruct()
    {
        $this->flushCache();
    }

    /**
     * @inheritDoc
     */
    protected function findFileWithExtension($class, $ext)
    {
        if ($this->enabled && isset($this->cachedClassMap[$class])) {
            return $this->cachedClassMap[$class];
        }

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
                $this->cachedClassMap = $this->mapCacheDataToClassMap($keys, $values);
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

        if (!$redis->pconnect($this->redisHost, $this->redisPort)) {
            return null;
        }

        $redis->select($this->redisDatabase);

        return $redis;
    }
}
