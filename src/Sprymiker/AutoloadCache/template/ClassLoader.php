<?php

namespace Composer\Autoload;

include __DIR__ . DIRECTORY_SEPARATOR . 'ClassLoaderOriginal.php';

class ClassLoader extends ClassLoaderOriginal
{
    /**
     * @var bool
     */
    protected $cacheEnabled = false;

    /**
     * @var string
     */
    protected $cacheUrl = 'http://localhost:8999/';

    /**
     * @var int
     */
    protected $bulkSize = 300;

    /**
     * @var string[]
     */
    protected $cachedClassMap = [];

    /**
     * @var string[]
     */
    protected $cacheWriteBuffer = [];

    /**
     * @var string
     */
    protected $baseDir = '';

    /**
     * @var int
     */
    protected $baseDirLength = 0;

    public function __construct()
    {
        $this->cacheEnabled = (bool)getenv('COMPOSER_AUTOLOAD_CACHE_ENABLED');
        $this->cacheUrl = getenv('COMPOSER_AUTOLOAD_CACHE_URL') ?: $this->cacheUrl;
        $this->bulkSize = getenv('COMPOSER_AUTOLOAD_CACHE_BULK') ?: $this->bulkSize;

        if ($this->cacheEnabled) {
            $this->baseDir = $this->defineBaseDir();
            $this->baseDirLength = strlen($this->baseDir);
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
        if ($this->cacheEnabled && isset($this->cachedClassMap[$class])) {
            return $this->cachedClassMap[$class];
        }

        $file = parent::findFileWithExtension($class, $ext);

        if ($this->cacheEnabled) {
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
        $this->cacheWriteBuffer[$class] = is_string($file) ? $this->getRelativePath($file) : false;

        if (count($this->cacheWriteBuffer) >= $this->bulkSize) {
            $this->flushCache();
        }
    }

    /**
     * @return void
     */
    protected function flushCache(): void
    {
        if (count($this->cacheWriteBuffer) === 0) {
            return;
        }

        $opts = [
            'http' => [
                'ignore_errors' => true,
                'timeout' => 0.1,
                'method' => 'PUT',
                'header' => 'Content-Type: application/json',
                'content' => json_encode($this->cacheWriteBuffer)
            ]
        ];

        $context = stream_context_create($opts);

        try {
            $response = @file_get_contents($this->cacheUrl, false, $context);
        } catch (\Exception $exception) {
            // do nothing
        }

        $this->cacheWriteBuffer = [];
    }

    /**
     * @return void
     */
    protected function loadCache(): void
    {
        $opts = [
            'http' => [
                'ignore_errors' => true,
                'timeout' => 0.1,
                'method' => 'GET',
            ]
        ];

        $context = stream_context_create($opts);

        try {
            $response = @file_get_contents($this->cacheUrl, false, $context);
        } catch (\Exception $exception) {
            return;
        }

        if ($response === false) {
            return;
        }

        $classMap = json_decode($response, true);
        if (is_array($classMap)) {
            $baseDir = $this->baseDir;
            $this->cachedClassMap = array_map(function ($path) use ($baseDir) {
                return is_string($path) && $path ? ($baseDir . $path) : false;
            }, $classMap);
        }
    }

    /**
     * @return string
     */
    protected function defineBaseDir(): string
    {
        include __DIR__ . DIRECTORY_SEPARATOR . 'autoload_files.php';

        return realpath(isset($baseDir) ? $baseDir : dirname(__DIR__, 2)) . DIRECTORY_SEPARATOR;
    }

    /**
     * @param string $file
     *
     * @return string
     */
    protected function getRelativePath(string $file): string
    {
        return substr(realpath($file), $this->baseDirLength);
    }
}
