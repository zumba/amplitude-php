<?php

namespace Zumba\Amplitude\Test;

use PHPUnit\Framework\TestCase;
use \Zumba\Amplitude\Inflector;

/**
 * @group amplitude
 */
class InflectorTest extends TestCase
{
    public function testCamelCase()
    {
        $this->assertEquals('ok', Inflector::camelCase('ok'));
        $this->assertEquals('camelCased', Inflector::camelCase('camelCased'));
        $this->assertEquals('doubleCamelCased', Inflector::camelCase('doubleCamelCased'));
        $this->assertEquals('underScored', Inflector::camelCase('under_scored'));
        $this->assertEquals('doubleUnderScored', Inflector::camelCase('double_under_scored'));
    }

    public function testUnderscore()
    {
        $this->assertEquals('ok', Inflector::underscore('ok'));
        $this->assertEquals('camel_cased', Inflector::underscore('camelCased'));
        $this->assertEquals('double_camel_cased', Inflector::underscore('doubleCamelCased'));
        $this->assertEquals('under_scored', Inflector::underscore('under_scored'));
        $this->assertEquals('double_under_scored', Inflector::underscore('double_under_scored'));
    }
}
