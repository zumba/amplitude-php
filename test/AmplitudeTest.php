<?php

namespace Zumba\Amplitude\Test;

use PHPUnit\Framework\MockObject\MockObject;
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
        /** @var Amplitude|MockObject $amplitude */
        $amplitude = $this->getMockBuilder(Amplitude::class)
            ->onlyMethods(['logEventObject'])
            ->getMock()
        ;

        $amplitude->expects($this->exactly(3))
            ->method('logEventObject')
        ;

        $amplitude->queueEvent('Event 1')
            ->queueEvent('Event 2', ['customProp' => 'value'])
            ->queueEvent('Event 3')
        ;

        $this->assertTrue($amplitude->hasQueuedEvents(), 'Initialization check, should have queued events');

        $amplitude->init('APIKEY', 'USER-ID')
            ->logQueuedEvents();

        $this->assertFalse($amplitude->hasQueuedEvents(), 'logQueuedEvents should reset the queue afterwards');
    }

    public function testLogQueuedEventsEmptyQueue()
    {
        /** @var Amplitude|MockObject $amplitude */
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

    public function testLogEventNoApiKey()
    {
        $amplitude = new Amplitude();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(Amplitude::EXCEPTION_MSG_NO_API_KEY);
        $amplitude->logEventObject($amplitude->newEvent('test'));
    }

    public function testLogEventEventInitializedEarly()
    {
        /** @var Amplitude|MockObject $amplitude */
        $amplitude = $this->getMockBuilder(Amplitude::class)
            ->onlyMethods(['sendEvent'])
            ->getMock()
        ;

        $amplitude->expects($this->once())
            ->method('sendEvent')
        ;

        $event            = $amplitude->newEvent('Event Type');
        $event->userId    = 'USER';

        $amplitude->init('APIKEY');
        $amplitude->logEventObject($event);
        // Note: this tests that no exceptions are thrown since it sets all of the requirements on the event prior to
        // calling logEvent
    }

    public function testLogEventNoUserNoDevice()
    {
        $amplitude = new Amplitude();

        $amplitude->init('APIKEY');
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(Amplitude::EXCEPTION_MSG_NO_USER_OR_DEVICE);
        $amplitude->logEvent('Event');
    }

    public function testQueueEventAlreadyInitRunImmediately()
    {
        /** @var Amplitude|MockObject $amplitude */
        $amplitude = $this->getMockBuilder(Amplitude::class)
            ->onlyMethods(['logEventObject'])
            ->getMock()
        ;
        $amplitude->expects($this->once())
            ->method('logEventObject')
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
        /** @var Amplitude|MockObject $amplitude */
        $amplitude = $this->getMockBuilder(Amplitude::class)
            ->onlyMethods(['logEventObject'])
            ->getMock();
        $amplitude->expects($this->never())
            ->method('logEventObject');

        $result           = $amplitude->queueEvent('Event');
        $this->assertTrue(
            $amplitude->hasQueuedEvents(),
            'Should have queued the event without throwing exception since event type set prior to being queued'
        );

        $this->assertSame($amplitude, $result, 'Should return itself');
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
        /** @var Amplitude|MockObject $amplitude */
        $amplitude = new Amplitude();
        // Should not end up attempting to send any events no matter how they are logged, either through queue or
        // directly
        $amplitude->setOptOut(true);

        $amplitude->queueEvent('Queued Event');

        $amplitude->init('API', 'USER')
            ->setOptOut(true)
            ->logQueuedEvents();

        $amplitude->logEvent('Another Event')
            ->queueEvent('Another Queued Event');

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
