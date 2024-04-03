<?php

namespace Pantono\Config;

use Pantono\Contracts\Config\FileInterface;

class File implements FileInterface
{
    private int $position = 0;
    private array $data;
    private array $keys;

    public function __construct(array $data)
    {
        $this->data = $data;
        $this->keys = array_keys($data);
    }

    public function getAllData(): array
    {
        return $this->data;
    }

    public function getValue(string $key, mixed $default = null): mixed
    {
        $data = $this->data;
        if (str_contains($key, '.')) {
            $parts = explode('.', $key);
            $var = $data;
            foreach ($parts as $part) {
                $var = $var[$part] ?? null;
                if ($var === null) {
                    return $default;
                }
            }
            return $var;
        }

        return $data[$key] ?? $default;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \RuntimeException('Cannot override config');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \RuntimeException('Cannot override config');
    }

    public function current(): mixed
    {
        return $this->data[$this->keys[$this->position]] ?? null;
    }

    public function key(): int
    {
        return $this->position;
    }

    public function next(): void
    {
        ++$this->position;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function valid(): bool
    {
        return isset($this->keys[$this->position]);
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
