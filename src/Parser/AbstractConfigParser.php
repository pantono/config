<?php

namespace Pantono\Config\Parser;

abstract class AbstractConfigParser
{
    protected string $env;

    public function __construct(string $env)
    {
        $this->env = $env;
    }

    abstract public function parse(string $contents): ?array;
}
