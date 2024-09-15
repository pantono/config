<?php

namespace Pantono\Config;

use Pantono\Utilities\ApplicationHelper;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Pantono\Config\Event\RegisterConfigPathEvent;
use Pantono\Config\Parser\YamlFileParser;
use Pantono\Config\Parser\IniFileParser;
use Pantono\Contracts\Config\ConfigInterface;
use Pantono\Contracts\Config\FileInterface;
use Symfony\Component\Yaml\Tag\TaggedValue;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Pantono\Utilities\CacheHelper;

class Config implements ConfigInterface
{
    private array $paths = [];
    private EventDispatcher $eventDispatcher;
    private AbstractAdapter $cache;

    public function __construct(EventDispatcher $eventDispatcher, AbstractAdapter $cache)
    {
        $this->cache = $cache;
        $this->paths[] = ApplicationHelper::getApplicationRoot();
        $this->paths[] = __DIR__ . '/../../conf';
        $this->eventDispatcher = $eventDispatcher;
        $event = new RegisterConfigPathEvent();
        $this->eventDispatcher->dispatch($event);
        foreach ($event->getPaths() as $path) {
            $this->paths[] = $path;
        }
    }

    public function registerPath(string $path): void
    {
        if (!file_exists($path)) {
            throw new \RuntimeException('Config path ' . $path . ' does not exist');
        }
        $this->paths[] = $path;
    }

    public function getConfigForType(string $type): FileInterface
    {
        $allowedTypes = ['config', 'endpoints', 'services', 'validators', 'security_gates', 'event_listeners', 'cli_commands', 'queue_tasks'];
        if (!in_array($type, $allowedTypes)) {
            throw new \RuntimeException('Invalid config type ' . $type);
        }
        $extensions = ['yml', 'ini', 'php'];
        $data = [];
        foreach ($this->paths as $path) {
            $filePath = sprintf('%s/%s', $path, $type);
            foreach ($extensions as $extension) {
                $envFullPath = sprintf('%s.%s.%s', $filePath, ApplicationHelper::getEnv(), $extension);
                if (file_exists($envFullPath)) {
                    $data = array_merge($data, $this->parseContents($envFullPath) ?? []);
                } else {
                    $fullPath = sprintf('%s.%s', $filePath, $extension);
                    if (file_exists($fullPath)) {
                        $data = array_merge($data, $this->parseContents($fullPath) ?? []);
                    }
                }
            }
        }
        return new File($data);
    }

    public function getApplicationConfig(): FileInterface
    {
        return $this->getConfigForType('config');
    }

    private function parseContents(string $path): ?array
    {
        $env = ApplicationHelper::getEnv();
        if (!file_exists($path)) {
            throw new \RuntimeException('Path ' . $path . ' does not exist');
        }
        $fileInfo = pathinfo($path);
        $ext = $fileInfo['extension'] ?? '';
        $modifiedTime = filemtime($path);
        if ($ext === 'yml') {
            $data = $this->cache->get(CacheHelper::cleanCacheKey($path . $env . $modifiedTime), function () use ($path) {
                $fileData = file_get_contents($path);
                if ($fileData === false) {
                    throw new \RuntimeException('Unable to get contents of ' . $path);
                }
                $parser = new YamlFileParser(ApplicationHelper::getEnv());
                return $parser->parse($fileData);
            });
            if (is_array($data)) {
                foreach ($data as $key => $value) {
                    if ($value instanceof TaggedValue) {
                        unset($data[$key]);
                        if ($value->getTag() === 'include') {
                            $info = pathinfo($path);
                            if (!isset($info['dirname'])) {
                                throw new \RuntimeException('Cannot get directory name from ' . $path);
                            }
                            $included = $this->parseContents($info['dirname'] . '/' . $value->getValue());
                            if (is_array($included)) {
                                $data = array_merge($data, $included);
                            }
                        } else {
                            throw new \RuntimeException('Tag ' . $value->getTag() . ' is not implemented');
                        }
                    }
                }
            }
            return $data;
        }
        if ($ext === 'ini') {
            return $this->cache->get(CacheHelper::cleanCacheKey($path . $env . $modifiedTime), function () use ($path) {
                $fileData = file_get_contents($path);
                if ($fileData === false) {
                    throw new \RuntimeException('Unable to get contents of ' . $path);
                }
                $parser = new IniFileParser(ApplicationHelper::getEnv());
                return $parser->parse($fileData);
            });
        }
        if ($ext === 'php') {
            return include $path;
        }

        throw new \RuntimeException('Unable to parse config file ' . $path);
    }
}
