<?php

namespace Pantono\Config\Parser;

use Symfony\Component\Yaml\Yaml;

class YamlFileParser extends AbstractConfigParser
{
    public function parse(string $contents): ?array
    {
        return Yaml::parse($contents, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE | Yaml::PARSE_CUSTOM_TAGS);
    }
}
