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
use Pantono\Utilities\CacheHelper;
use Pantono\Contracts\Application\Cache\ApplicationCacheInterface;

class Config implements ConfigInterface
{
    private array $paths = [];
    private EventDispatcher $eventDispatcher;
    private ApplicationCacheInterface $cache;

    public function __construct(EventDispatcher $eventDispatcher, ApplicationCacheInterface $cache)
    {
        $this->cache = $cache;
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

    /**
     * @return string[]
     */
    public function getAllowedConfigTypes(): array
    {
        return ['config', 'endpoints', 'services', 'validators', 'security_gates', 'event_listeners', 'cli_commands', 'queue_tasks'];
    }

    public function compileConfig(string $type): void
    {
        if (!in_array($type, $this->getAllowedConfigTypes())) {
            throw new \RuntimeException('Invalid config type ' . $type);
        }
        $path = ApplicationHelper::getApplicationRoot() . '/cache/compiled_config' . $type . '.php';
        $data = $this->getConfigData($type);
        file_put_contents($path, '<?php return ' . var_export($data, true) . ';');
    }

    public function getConfigForType(string $type): FileInterface
    {
        if (!in_array($type, $this->getAllowedConfigTypes())) {
            throw new \RuntimeException('Invalid config type ' . $type);
        }
        $compiledPath = $path = ApplicationHelper::getApplicationRoot() . '/cache/compiled_config' . $type . '.php';
        if (file_exists($compiledPath)) {
            return new File(include $compiledPath);
        }
        $data = $this->getConfigData($type);
        return new File($data);
    }

    /**
     * @return array<mixed>
     */
    private function getConfigData(string $type): array
    {
        if (!in_array($type, $this->getAllowedConfigTypes())) {
            throw new \RuntimeException('Invalid config type ' . $type);
        }
        $extensions = ['yml', 'ini', 'php'];
        $data = [];
        foreach ($this->getPaths() as $path) {
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
        return $data;
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
        if (file_exists(ApplicationHelper::getApplicationRoot() . '/.env')) {
            $modifiedTime .= filemtime(ApplicationHelper::getApplicationRoot() . '/.env');
        }
        if ($ext === 'yml') {
            $data = $this->cache->getCallback(CacheHelper::cleanCacheKey($path . $env . $modifiedTime), function () use ($path) {
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
            return ApplicationHelper::interpolateEnv($data);
        }
        if ($ext === 'ini') {
            return $this->cache->getCallback(CacheHelper::cleanCacheKey($path . $env . $modifiedTime), function () use ($path) {
                $fileData = file_get_contents($path);
                if ($fileData === false) {
                    throw new \RuntimeException('Unable to get contents of ' . $path);
                }
                $parser = new IniFileParser(ApplicationHelper::getEnv());
                return ApplicationHelper::interpolateEnv($parser->parse($fileData));
            });
        }
        if ($ext === 'php') {
            return ApplicationHelper::interpolateEnv(include $path);
        }

        throw new \RuntimeException('Unable to parse config file ' . $path);
    }

    public function getPaths(): array
    {
        $paths = $this->paths;
        $paths[] = ApplicationHelper::getApplicationRoot() . '/conf';
        return $paths;
    }
}
