<?php

namespace Zumba\Amplitude\Test;

use PHPUnit\Framework\TestCase;
use \Zumba\Amplitude\Event;

/**
 * @group amplitude
 */
class EventTest extends TestCase
{
    /**
     * @dataProvider setDataProvider
     */
    public function testSet($name, $value, $expected, $msg)
    {
        $event = new Event('test');
        $result = $event->set($name, $value);
        $this->assertInstanceOf(Event::class, $result, 'get should return instance of itself');
        $this->assertEquals(json_encode($expected), json_encode($event), $msg);
    }

    public function setDataProvider()
    {
        return [
            [
                'user_id',
                'underscore',
                ['event_type' => 'test', 'user_id' => 'underscore'],
                'Set built-in property directly',
            ],
            [
                'userId',
                'camel',
                ['event_type' => 'test', 'user_id' => 'camel'],
                'Set built-in property using camelcase, but setting the value using underscore',
            ],
            [
                'productId',
                'camel',
                ['event_type' => 'test', 'productId' => 'camel'],
                'Set camelcase built-in property using camelcase',
            ],
            [
                'product_id',
                'under',
                ['event_type' => 'test', 'productId' => 'under'],
                'Set camelcase built-in property using underscore, still sets camelcase version',
            ],
            [
                'revenue_type',
                'under',
                ['event_type' => 'test', 'revenueType' => 'under'],
                'Set camelcase built-in property using underscore, still sets camelcase version',
            ],
            [
                'customProp',
                'custom',
                ['event_type' => 'test', 'event_properties' => ['customProp' => 'custom']],
                'Set not-built-in property in event_properties without changing name',
            ],
            [
                'Custom With Space',
                'Some value',
                ['event_type' => 'test', 'event_properties' => ['Custom With Space' => 'Some value']],
                'Set not-built-in property in event_properties without changing name',
            ],
        ];
    }

    public function testSetArray()
    {
        $event = new Event('test');

        $event->set(
            [
                'deviceId' => 'device',
                'user_id' => 'user',
                'product_id' => 'product',
                'some property' => 'some value',
            ]
        );
        $expected = [
            'event_type' => 'test',
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

    public function testSetCasting()
    {
        $event = new Event('test');
        $event->set('quantity', '10.5');
        $event->set('price', '1234.350');

        $eventData = $event->toArray();
        $this->assertSame(10, $eventData['quantity']);
        $this->assertSame(1234.35, $eventData['price']);
    }

    public function testUnsetProperty()
    {
        $event = new Event('test');
        $event->set('custom prop', 'value');
        $event->userId = 'user';
        $event->quantity = 50;
        $event->userProperties = ['prop' => 'value'];
        $this->assertEquals(
            [
                'event_type' => 'test',
                'event_properties' => ['custom prop' => 'value'],
                'user_id' => 'user',
                'quantity' => 50,
                'user_properties' => ['prop' => 'value']
            ],
            $event->toArray(),
            'Initialization Check'
        );
        // Should just not care if not set...
        $event->unsetProperty('invalid');
        unset($event->invalid);

        // Should be able to successfully unset custom property
        $this->assertNotEmpty($event->get('custom prop'), 'Initialization check');
        $event->unsetProperty('custom prop');
        $this->assertEmpty($event->get('custom prop'), 'Should be able to unset custom properties');

        // also should work with magic methods
        $this->assertNotEmpty($event->userId, 'Initialization check');
        unset($event->userId);
        $this->assertEmpty($event->userId, 'Should unset built-in properties with magic unset');
    }

    public function testSetUserProperties()
    {
        $userProps = ['dob' => 'tomorrow', 'gender' => 'f'];
        $event = new Event('test');
        $event->setUserProperties($userProps);
        $this->assertSame(
            ['event_type' => 'test', 'user_properties' => $userProps],
            $event->toArray(),
            'Should set user properties in user_properties'
        );
        $userProps2 = ['dob' => 'yesterday', 'name' => 'Baby'];
        $expected = [
            'dob' => 'yesterday',
            'gender' => 'f',
            'name' => 'Baby',
        ];
        $event->setUserProperties($userProps2);
        $this->assertSame(
            ['event_type' => 'test', 'user_properties' => $expected],
            $event->toArray(),
            'Second call to setUserProperties should update properties, not remove existing'
        );
    }
}
