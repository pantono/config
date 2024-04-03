<?php

namespace Pantono\Config\Parser;

use Pantono\Config\Exception\EnvironmentDoesNotExist;

class IniFileParser extends AbstractConfigParser
{
    public function parse(string $contents): array
    {
        $data = parse_ini_string($contents, true, INI_SCANNER_TYPED);
        if ($data === false) {
            return [];
        }
        $envs = [];
        $keys = array_keys($data);
        foreach ($keys as $section) {
            $parent = null;
            if (str_contains($section, ':')) {
                [$env, $parent] = explode(':', $section, 2);
                $env = trim($env);
                $parent = trim($parent);
            } else {
                $env = $section;
            }
            $envs[$env] = ['parent' => $parent, 'section' => $section, 'config' => $data[$section]];
        }
        if (!isset($envs[$this->env])) {
            throw new EnvironmentDoesNotExist('Cannot parse ini file with no section for current application environment (' . $this->env . ')');
        }
        $envData = $envs[$this->env];
        $useEnvs[] = $this->env;
        while ($envData['parent']) {
            $useEnvs[] = $envData['parent'];
            $envData = $envs[$envData['parent']];
        }
        $useEnvs = array_reverse($useEnvs);
        $config = [];
        foreach ($useEnvs as $env) {
            $config = array_merge($config, $envs[$env]['config']);
        }
        return $this->expandConfigArray($config);
    }

    private function expandConfigArray(array $config): array
    {
        $result = [];

        foreach ($config as $key => $value) {
            $parts = explode('.', $key);
            $tempArray = [];
            for ($i = count($parts) - 1; $i >= 0; $i--) {
                if ($i == count($parts) - 1) {
                    $tempArray = [$parts[$i] => $value];
                } else {
                    $tempArray = [$parts[$i] => $tempArray];
                }
            }
            $result = array_merge_recursive($result, $tempArray);
        }

        return $result;
    }
}
