<?php

namespace Zumba\Amplitude\Test;

use PHPUnit\Framework\TestCase;
use Zumba\Amplitude\Amplitude;
use Zumba\Amplitude\Event;

/**
 * @group amplitude
 */
class AmplitudeTest extends TestCase
{
    public function testGetInstance()
    {
        $default = Amplitude::getInstance();
        $this->assertSame($default, Amplitude::getInstance());

        $named = Amplitude::getInstance('named');
        $this->assertNotSame($named, $default);
        $this->assertSame($named, Amplitude::getInstance('named'));
    }

    public function testConstructSetsApiKey()
    {
        $amplitude = new Amplitude('api-key');
        $this->assertEquals('api-key', $amplitude->getApiKey());
    }

    public function testInit()
    {
        $amplitude = new Amplitude();
        $this->assertNull($amplitude->getApiKey(), 'Initial value should be null');
        $amplitude->init('API-KEY', 'USER-ID');
        $this->assertEquals('API-KEY', $amplitude->getApiKey(), 'Init should set api key');
        $this->assertEquals('USER-ID', $amplitude->getUserId(), 'Init should set user ID');
    }

    public function testLogQueuedEvents()
    {
        $amplitude = $this->getMockBuilder(Amplitude::class)
            ->onlyMethods(['logEvent'])
            ->getMock()
        ;

        $amplitude->expects($this->exactly(3))
            ->method('logEvent')
        ;

        $amplitude->queueEvent('Event 1')
            ->queueEvent('Event 2', ['customProp' => 'value'])
            ->queueEvent('Event 3')
        ;

        $this->assertTrue($amplitude->hasQueuedEvents(), 'Initialization check, should have queued events');

        $amplitude->init('APIKEY', 'USER-ID')
            ->logQueuedEvents()
        ;

        $this->assertFalse($amplitude->hasQueuedEvents(), 'logQueuedEvents should reset the queue afterwards');
    }

    public function testLogQueuedEventsEmptyQueue()
    {
        $amplitude = $this->getMockBuilder(Amplitude::class)
            ->onlyMethods(['logEvent'])
            ->getMock()
        ;

        $amplitude->expects($this->never())
            ->method('logEvent')
        ;

        $this->assertFalse($amplitude->hasQueuedEvents(), 'Initialization check, should not have queued events');

        $result = $amplitude->init('APIKEY', 'USER-ID')
            ->logQueuedEvents()
        ;

        $this->assertSame($amplitude, $result, 'Should return itself');
    }

    public function testEvent()
    {
        $event     = new Event();
        $amplitude = new Amplitude();
        $newEvent  = $amplitude->event();
        $this->assertNotSame($newEvent, $event, 'Initialization check');
        $amplitude->event($event);
        $this->assertSame($event, $amplitude->event(), 'Event passed in should persist until it is used or reset');
        $this->assertNotSame($newEvent, $event, 'Should not keep using old event if pass in a new one');

        $addPropertyEvent = $amplitude->event(['new property' => 'value']);
        $this->assertSame($addPropertyEvent, $event, 'Should keep using same event, have not set the event yet');
        $this->assertEquals(
            'value',
            $addPropertyEvent->get('new property'),
            'Should allow passing in event properties to set them on the event'
        );
    }

    public function testLogEvent()
    {
        $props           = ['event property' => 'value'];
        $userId          = 'USERID';
        $deviceId        = 'DEVICEID';
        $eventType       = 'Event Type';
        $secondEventType = 'Second Event';

        $amplitude = $this->getMockBuilder(Amplitude::class)
            ->onlyMethods(['sendEvent'])
            ->getMock()
        ;
        $event     = $amplitude->event();
        $result    = $amplitude->init('APIKEY', $userId)
            ->setDeviceId($deviceId)
            ->logEvent($eventType, $props)
        ;

        $eventData = $event->toArray();

        $this->assertEquals($eventType, $eventData['event_type'], 'logEvent should set the event_type on the event');
        $this->assertEquals(
            $props,
            $eventData['event_properties'],
            'logEvent should set event_properties on the event'
        );
        $this->assertEquals($userId, $eventData['user_id'], 'logEvent should set the user_id on the event');
        $this->assertEquals($deviceId, $eventData['device_id'], 'logEvent should set device_id on the event');

        $this->assertSame($amplitude, $result, 'Should return itself');

        $event2 = $amplitude->event();
        $this->assertNotSame($event2, $event, 'Should have created a new event once the original was logged');
        $amplitude->logEvent($secondEventType);
        $eventData = $event2->toArray();

        $this->assertEquals(
            $secondEventType,
            $eventData['event_type'],
            'logEvent should set the event_type on the event'
        );
        $this->assertArrayNotHasKey(
            'event_properties',
            $eventData,
            'logEvent should not have set event_properties with nothing passed in'
        );
        $this->assertArrayNotHasKey(
            'user_properties',
            $eventData,
            'logEvent should not persist user properties after they are sent in first event'
        );
        $this->assertEquals($userId, $eventData['user_id'], 'logEvent should persist the user_id for every event sent');
        $this->assertEquals(
            $deviceId,
            $eventData['device_id'],
            'logEvent should persist the device_id for every event sent'
        );
    }

    public function testLogEventUserPropertiesMerged()
    {
        $props     = ['event property' => 'value'];
        $props2    = ['second prop' => 'second val'];
        $userId    = 'USERID';
        $eventType = 'Event Type';

        $amplitude = $this->getMockBuilder(Amplitude::class)
            ->onlyMethods(['sendEvent'])
            ->getMock()
        ;
        $event                 = $amplitude->event();
        $event->userProperties = $props;
        $result                = $amplitude->init('APIKEY', $userId)
            ->setUserProperties($props2)
            ->logEvent($eventType)
        ;

        $eventData = $event->toArray();

        $this->assertEquals(
            array_merge($props, $props2),
            $eventData['user_properties'],
            'logEvent should merge any pending user properties with any properties set on the event already'
        );
    }

    public function testLogEventNoApiKey()
    {
        $amplitude = $this->getMockBuilder(Amplitude::class)
            ->onlyMethods(['sendEvent'])
            ->getMock()
        ;

        $amplitude->expects($this->never())
            ->method('sendEvent')
        ;

        $this->expectException(\LogicException::class, Amplitude::EXCEPTION_MSG_NO_API_KEY);
        $amplitude->logEvent();
    }

    public function testLogEventNoEventType()
    {
        $amplitude = $this->getMockBuilder(Amplitude::class)
            ->onlyMethods(['sendEvent'])
            ->getMock()
        ;

        $amplitude->expects($this->never())
            ->method('sendEvent')
        ;

        $amplitude->init('APIKEY', 'USER');
        $this->expectException(\LogicException::class, Amplitude::EXCEPTION_MSG_NO_EVENT_TYPE);
        $amplitude->logEvent();
    }

    public function testLogEventEventInitializedEarly()
    {
        $amplitude = $this->getMockBuilder(Amplitude::class)
            ->onlyMethods(['sendEvent'])
            ->getMock()
        ;

        $amplitude->expects($this->once())
            ->method('sendEvent')
        ;

        $event            = $amplitude->event();
        $event->eventType = 'Event Type';
        $event->userId    = 'USER';

        $amplitude->init('APIKEY');
        $amplitude->logEvent();
        // Note: this tests that no exceptions are thrown since it sets all of the requirements on the event prior to
        // calling logEvent
    }

    public function testLogEventNoUserNoDevice()
    {
        $amplitude = $this->getMockBuilder(Amplitude::class)
            ->onlyMethods(['sendEvent'])
            ->getMock()
        ;

        $amplitude->expects($this->never())
            ->method('sendEvent')
        ;

        $amplitude->init('APIKEY');
        $this->expectException(\LogicException::class, Amplitude::EXCEPTION_MSG_NO_USER_OR_DEVICE);
        $amplitude->logEvent('Event');
    }

    public function testQueueEvent()
    {
        $amplitude = $this->getMockBuilder(Amplitude::class)
            ->onlyMethods(['logEvent'])
            ->getMock()
        ;

        $amplitude->expects($this->never())
            ->method('logEvent')
        ;

        $event = $amplitude->event();
        $amplitude->setUserId('USER');
        $this->assertFalse($amplitude->hasQueuedEvents(), 'Initialization check, should not have queue starting out');
        $result = $amplitude->queueEvent('Event', ['prop' => 'val']);

        $this->assertSame($amplitude, $result, 'Should return itself');

        // Make sure event data set properly
        $eventData = $event->toArray();
        $this->assertEquals('Event', $eventData['event_type'], 'should save the event type on the event');
        $this->assertEquals(['prop' => 'val'], $eventData['event_properties'], 'should save event properties on event');
        $this->assertArrayNotHasKey(
            'user_id',
            $eventData,
            'should not set user ID at time event is queued, even if user ID is set at time of being queued'
        );
        $this->assertTrue($amplitude->hasQueuedEvents(), 'Should have queued the event');
        $this->assertNotSame($event, $amplitude->event(), 'Should be creating a new event once one has been queued');
    }

    public function testQueueEventAlreadyInitRunImmediately()
    {
        $amplitude = $this->getMockBuilder(Amplitude::class)
            ->onlyMethods(['logEvent'])
            ->getMock()
        ;
        $amplitude->expects($this->once())
            ->method('logEvent')
        ;

        $this->assertFalse($amplitude->hasQueuedEvents(), 'Initialization check, should not have queue starting out');
        $amplitude->init('APIKEY', 'USER')
            ->queueEvent('Event')
        ;
        $this->assertFalse(
            $amplitude->hasQueuedEvents(),
            'Should have sent event right away since amplitude was already initialized'
        );
    }

    public function testQueueEventInitEarly()
    {
        $amplitude = $this->getMockBuilder(Amplitude::class)
            ->onlyMethods(['logEvent'])
            ->getMock()
        ;
        $amplitude->expects($this->never())
            ->method('logEvent')
        ;

        $event            = $amplitude->event();
        $event->eventType = 'Event';
        $result           = $amplitude->queueEvent();
        $this->assertTrue(
            $amplitude->hasQueuedEvents(),
            'Should have queued the event without throwing exception since event type set prior to being queued'
        );

        $this->assertSame($amplitude, $result, 'Should return itself');
    }

    public function testQueueEventNoEventType()
    {
        $amplitude = $this->getMockBuilder(Amplitude::class)
            ->onlyMethods(['logEvent'])
            ->getMock()
        ;
        $amplitude->expects($this->never())
            ->method('logEvent')
        ;

        $this->expectException(\LogicException::class, Amplitude::EXCEPTION_MSG_NO_EVENT_TYPE);
        $amplitude->queueEvent();
    }

    public function testResetUser()
    {
        $amplitude = new Amplitude();
        $amplitude->setUserId('User')
            ->setDeviceId('device')
            ->setUserProperties(['user props'])
        ;
        $this->assertNotEmpty($amplitude->getUserId(), 'Initialization check');
        $this->assertNotEmpty($amplitude->getDeviceId(), 'Initialization check');
        $this->assertNotEmpty($amplitude->getUserProperties(), 'Initialization check');

        $amplitude->resetUser();

        $this->assertEmpty($amplitude->getUserId(), 'Should have cleared user ID');
        $this->assertEmpty($amplitude->getDeviceId(), 'Should have cleared device ID');
        $this->assertEmpty($amplitude->getUserProperties(), 'Should have cleared user properties');
    }

    public function testOptOut()
    {
        $amplitude = $this->getMockBuilder(Amplitude::class)
            ->onlyMethods(['sendEvent'])
            ->getMock()
        ;

        $amplitude->expects($this->never())
            ->method('sendEvent')
        ;
        // Should not end up attempting to send any events no matter how they are logged, either through queue or
        // directly
        $amplitude->setOptOut(true);

        $amplitude->queueEvent('Queued Event');

        $amplitude->init('API', 'USER')
            ->setOptOut(true)
            ->logQueuedEvents()
        ;

        $amplitude->logEvent('Another Event')
            ->queueEvent('Another Queued Event')
        ;
        $this->assertTrue($amplitude->getOptOut());
    }

    public function testSetUserProperties()
    {
        $userProps = ['dob'    => 'tomorrow',
                      'gender' => 'f',
        ];
        $amplitude = new Amplitude();
        $amplitude->setUserProperties($userProps);
        $this->assertSame($userProps, $amplitude->getUserProperties());
        $userProps2 = ['dob'  => 'yesterday',
                       'name' => 'Baby',
        ];
        $expected   = [
            'dob'    => 'yesterday',
            'gender' => 'f',
            'name'   => 'Baby',
        ];
        $amplitude->setUserProperties($userProps2);
        $this->assertSame(
            $expected,
            $amplitude->getUserProperties(),
            'Second call to setUserProperties should set properties, without removing existing'
        );
    }
}
