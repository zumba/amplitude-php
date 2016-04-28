<?php

namespace Zumba\Amplitude\Test;

use \Zumba\Amplitude\Event;

/**
 * @group amplitude
 */
class EventTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider setDataProvider
     */
    public function testSet($name, $value, $expected, $msg)
    {
        $event = new Event();
        $result = $event->set($name, $value);
        $this->assertInstanceOf('\Zumba\Amplitude\Event', $result, 'get should return instance of itself');
        $this->assertEquals(json_encode($expected), json_encode($event), $msg);
    }

    public function setDataProvider()
    {
        return [
            [
                'user_id',
                'underscore',
                ['user_id' => 'underscore'],
                'Set built-in property directly',
            ],
            [
                'userId',
                'camel',
                ['user_id' => 'camel'],
                'Set built-in property using camelcase, but setting the value using underscore',
            ],
            [
                'productId',
                'camel',
                ['productId' => 'camel'],
                'Set camelcase built-in property using camelcase',
            ],
            [
                'product_id',
                'under',
                ['productId' => 'under'],
                'Set camelcase built-in property using underscore, still sets camelcase version',
            ],
            [
                'revenue_type',
                'under',
                ['revenueType' => 'under'],
                'Set camelcase built-in property using underscore, still sets camelcase version',
            ],
            [
                'customProp',
                'custom',
                ['event_properties' => ['customProp' => 'custom']],
                'Set not-built-in property in event_properties without changing name',
            ],
            [
                'Custom With Space',
                'Some value',
                ['event_properties' => ['Custom With Space' => 'Some value']],
                'Set not-built-in property in event_properties without changing name',
            ],
        ];
    }

    public function testSetArray()
    {
        $event = new Event();

        $event->set(
            [
                'deviceId' => 'device',
                'user_id' => 'user',
                'product_id' => 'product',
                'some property' => 'some value',
            ]
        );
        $expected = [
            'device_id' => 'device',
            'user_id' => 'user',
            'productId' => 'product',
            'event_properties' => [
                'some property' => 'some value',
            ],
        ];
        $this->assertEquals(
            json_encode($expected),
            json_encode($event),
            'Set should accept array of values to set, normalizing built in property names'
        );
    }

    /**
     * @dataProvider getDataProvider
     */
    public function testGet($eventData, $getName, $expected, $msg)
    {
        $event = new Event($eventData);
        $this->assertEquals($expected, $event->get($getName), $msg);
    }

    public function getDataProvider()
    {
        return [
            [
                ['user_id' => 'underscore'],
                'user_id',
                'underscore',
                'Get built-in property directly',
            ],
            [
                ['user_id' => 'camel'],
                'userId',
                'camel',
                'Get built-in property using camelcase',
            ],
            [
                ['productId' => 'camel'],
                'productId',
                'camel',
                'Get camelcase built-in property using camelcase',
            ],
            [
                ['productId' => 'under'],
                'product_id',
                'under',
                'Get camelcase built-in property using underscore, still gets camelcase version',
            ],
            [
                ['revenueType' => 'under'],
                'revenue_type',
                'under',
                'Get camelcase built-in property using underscore, still gets camelcase version',
            ],
            [
                ['event_properties' => ['customProp' => 'custom']],
                'customProp',
                'custom',
                'Get not-built-in property in event_properties without changing name',
            ],
            [
                ['event_properties' => ['custom_prop' => 'custom']],
                'customProp',
                null,
                'Should not get custom property with camelcase name (if not initially set with camelcase),
                    that should only work on built-in properties',
            ],
            [
                ['event_properties' => ['Custom With Space' => 'Some value']],
                'Custom With Space',
                'Some value',
                'Get not-built-in property in event_properties without changing name',
            ],
        ];
    }
}
