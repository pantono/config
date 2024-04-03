<?php

namespace Pantono\Config\Helper;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Pantono\Config\Event\RegisterConfigPathEvent;
use Pantono\Utilities\ApplicationHelper;

class RegisterConfigPath implements EventSubscriberInterface
{
    private static array $paths = [];

    public static function getSubscribedEvents(): array
    {
        return [
            RegisterConfigPathEvent::class => [
                ['processPaths', 1000]
            ]
        ];
    }

    public static function registerPath(string $path): void
    {
        self::$paths[] = $path;
    }

    public function processPaths(RegisterConfigPathEvent $event): void
    {
        foreach (self::$paths as $path) {
            $event->addPath($path);
        }
    }

    public static function registerModule(string $module): void
    {
        $path = sprintf('%s/vendor/pantono/%s/conf', ApplicationHelper::getApplicationRoot(), $module);
        if (!file_exists($path)) {
            throw new \RuntimeException('Unable to locate service ' . $module . ' has composer install been run? (Path: ' . $path . ')');
        }
        self::$paths[] = $path;
    }
}
