<?php

namespace Pantono\Config\Tests;

use PHPUnit\Framework\TestCase;
use Pantono\Config\File;
use Pantono\Config\Parser\IniFileParser;
use Pantono\Config\Parser\YamlFileParser;
use Symfony\Component\Yaml\Tag\TaggedValue;

class ConfigFileTest extends TestCase
{
    public function testIniFile(): void
    {
        $path = __DIR__ . '/Data/test-config-ini.ini';
        $parser = new IniFileParser('production');
        $data = $parser->parse(file_get_contents($path));
        $file = new File($data);
        $this->assertEquals([
            'env' => 'production',
            'some' => ['var' => 'test2', 'other' => 'test3', 'really' => ['long' => ['dot' => ['notation' => 'end']]]]
        ], $file->getAllData());
    }

    public function testInitDotNotation(): void
    {
        $path = __DIR__ . '/Data/test-config-ini.ini';
        $parser = new IniFileParser('production');
        $data = $parser->parse(file_get_contents($path));
        $file = new File($data);
        $this->assertEquals('end', $file->getValue('some.really.long.dot.notation'));
    }

    public function testIniFileExtends(): void
    {
        $path = __DIR__ . '/Data/test-config-ini.ini';
        $parser = new IniFileParser('local');
        $data = $parser->parse(file_get_contents($path));
        $file = new File($data);
        $this->assertEquals([
            'env' => 'local',
            'some' => ['var' => 'test3', 'other' => 'test3', 'really' => ['long' => ['dot' => ['notation' => 'end']]]]
        ], $file->getAllData());
    }

    public function testIniFileDeepArray(): void
    {
        $path = __DIR__ . '/Data/test-config-ini-deep.ini';
        $parser = new IniFileParser('production');
        $data = $parser->parse(file_get_contents($path));
        $file = new File($data);
        $this->assertEquals([
            'config' => ['var' => [
                'one' => [
                    'two' => [
                        'three' => [
                            'four' => "1"
                        ]
                    ]
                ]
            ]]
        ], $file->getAllData());
    }

    public function testIniFileMultiParent(): void
    {
        $path = __DIR__ . '/Data/test-ini-multi-parent.ini';
        $parser = new IniFileParser('three');
        $data = $parser->parse(file_get_contents($path));
        $file = new File($data);
        $this->assertEquals([
            'test' => [
                'test' => [
                    'test' => 3
                ],
                'one' => 1
            ]
        ], $file->getAllData());
    }

    public function testYamlParse(): void
    {
        $path = __DIR__ . '/Data/test-simple.yml';
        $parser = new YamlFileParser('production');
        $file = new File($parser->parse(file_get_contents($path)));
        $this->assertEquals([
            'test' => 'file'
        ], $file->getAllData());
    }

    public function testYamlInclude(): void
    {
        $value = new TaggedValue('include', 'test-simple.yml');
        $path = __DIR__ . '/Data/test-linked.yml';
        $parser = new YamlFileParser('production');
        $file = new File($parser->parse(file_get_contents($path)));
        $this->assertEquals([
            'test' => 'file',
            'other' => $value
        ], $file->getAllData());
    }
}
