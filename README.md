# Zumba amplitude-php

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Build Status](https://img.shields.io/travis/zumba/amplitude-php/master.svg?style=flat-square)](https://travis-ci.org/zumba/amplitude-php)
[![Code Coverage](https://img.shields.io/coveralls/zumba/amplitude-php/master.svg)](https://coveralls.io/github/zumba/amplitude-php)
[![Scrutinizer](https://scrutinizer-ci.com/g/zumba/amplitude-php/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/zumba/amplitude-php/)

This is a moderately thin PHP API for [Amplitude](https://amplitude.com/), powerful enough to do what you need without getting in the way.  Designed to work well in 2 main scenarios:

* **Multiple Events using Same User & Amplitude App** - When you are tracking possibly multiple events, all for the same user, all for the same Amplitude app.  This library provides a Singleton instance that allows initializing the API key and user info once for the page load, that gets used for any events logged during that page load.
* **Multiple Events across Multiple Users and possibly multiple Amplitude Apps** - For times you may need to log multiple events for a lot of different users, possibly using different amplitude apps.

# Example

```php
// After User is Initialized in your application, set the user info in Amplitude that you want to track (minimally
// the user identifier or device identifier, and of course your Amplitude App API key)
$amplitude = \Zumba\Amplitude\Amplitude::getInstance();
$amplitude->init('APIKEY', 'johnny@example.com')
    ->addUserProperties([
        'dob' => '1980-11-04',
        'name' => 'Johnny 5'
    ])
    // Only call this once API Key and user ID or device ID is set
    ->logQueuedEvents();

// -- Meanwhile, in another part of the code... --

// Anywhere else in your application that needs to log an event
// This will even work if called before the above code initializes Amplitude!  If that is case, it will queue it
// and send the event when logQueuedEvents() is called.  If Amplitude is already initialized, this will send the event
// to Amplitude right away (only uses a queue for early-logged events)
\Zumba\Amplitude\Amplitude::getInstance()
    ->queueEvent('EVENT TYPE');

// Can pass in an array for the second parameter to set event properties
\Zumba\Amplitude\Amplitude::getInstance()
    ->queueEvent('SECOND EVENT', ['quantity' => 1, 'price' => 15.32, 'Custom Property' => 'Widgets']);

// This is a simple example to get you started, see the rest of the readme for more examples
```

# Logging Anonymous Users

Since this is a PHP SDK, and PHP can be run from multiple environments on the back end, we chose not to include anything fancy to handle logging anonymous users.  Instead you can do this yourself by figuring out something unique to use that persists for the same anonymous user, such as a session ID or similar, and set that as the `deviceId`.

Your application will need to figure out a way to track users across multiple page loads, chances are your app is already doing this with PHP sessions or something like it.

Once you have that unique identifier that allows an anonymous user to be tracked between page loads, set that as the device ID.  Do this for both logged in users and anonymous users, that way once a user is logged in their past events will be linked to the user.

## Anonymous User Example

```php
// When initializing Amplitude, do something like this:
$amplitude = \Zumba\Amplitude\Amplitude::getInstance();
// Notice we are not setting second parameter here for user ID, we will do that below if it is available
$amplitude->init('APIKEY');

// Do not have to use session ID, can use any string your app uses for sessions, this is just an example after all...
$sessionId = session_id();
// Keep track of whether we have a session or user ID.. If we don't, we should not try to log events as they will fail
$canLogEvents = false;
if (!empty($sessionId)) {
    $amplitude->setDeviceId($sessionId);
    $canLogEvents = true;
}
// Presumes $applicationUserId set prior to this by your application
if (!empty($applicationUserId)) {
    // Application has the userId available! Set that in amplitude
    $amplitude->setUserId($applicationUserId);
    $canLogEvents = true;
}
if (!empty($userData)) {
    // If you have other user properties, set them as well...
    $amplitude->setUserProperties($userData);
}

if ($canLogEvents) {
    // Make sure to send any events that may have gotten queued
    $amplitude->logQueuedEvents();
} else {
    // set opt out, prevent amplitude from trying to send events since it won't work anyways
    $amplitude->setOptOut(true);
}

// -- Meanwhile, in another part of the code... --

// Just queue events as normal
\Zumba\Amplitude\Amplitude::getInstance()->queueEvent('EVENT');

```

# Using Event Object

You have the option to use an event object to set up the event, if this is more convenient for your use case.  The example of how to do this is below:

```php
$event = new \Zumba\Amplitude\Event();

// Can set values using object properties if you prefer:
$event->productId = 'acme-12345';
$event->eventType = 'EVENT TYPE';

// Can also use setters / getters, handy for names that are invalid as properties:
$event->set('My Location', 'Behind You');

// Also handy if you like chaining methods
$event->set('First Property', 'value')
    ->set('Second', 'value')
    ->set('Third', 'value');

// Can also pass in array of things to set like so
$event->set(
    [
        'quantity' => 5,
        'price' => 2.34
    ]
);

// Can also unset values, handy if an event is being generated by modularized parts of the code, where one part may
// want to un-set something another part added.
$event->unsetProperty('My Location');
// Magic unset also works
unset($event->productId);

// If you do need to generate an event in different parts of the code, you don't need to pass it around (if you don't
// want to), amplitude will keep track of the "next event to be sent" itself:
\Zumba\Amplitude\Amplitude::getInstance()->event($event);

// -- Meanwhile, in another part of the code... --
// As long as the event has not yet been sent or queued up, you can get it and change it as needed:
$event = \Zumba\Amplitude\Amplitude::getInstance()->event();
$event->deviceId = 'DEVICE ID';

// -- NOW to wrap up - once everything is done setting up the event object --
// Note: the event must have either been retrieved from Amplitude->event() or set using that method, prior to calling
// this, or Amplitude just won't know about the event and it will throw an exception.
// Note that the event type is required, either pass it as the first parameter of queueEvent(), or set eventType on the
// event before calling this.
\Zumba\Amplitude\Amplitude::getInstance()->queueEvent();
```

## Custom Event Factory

Say you wanted to make some sort of factory that is cranking out events to send...  You could do something like this:
```php
foreach ($eventFactory->getEvents() as $event) {
    \Zumba\Amplitude\Amplitude::getInstance()
        ->event($event)
        ->queueEvent();
}
```

# queueEvent() vs. logEvent()

The difference?

* Both:
 * Require the eventType to be set and non-empty.
* `logEvent()`:
 * Requires API Key to be set, will throw exception if not set yet.
 * Requires either userId or deviceId to be set, will throw exception if not set either on the amplitude instance, or on the event itself.
 * Always sends the event at the time it is called, assuming requirements met.
* `queueEvent()`:
 * Does NOT require API key to be set first.
 * Does NOT require userId or deviceId to be set first.
 * If either of those are not set, OR if there are still un-sent events in the queue, it will add the event to an internal queue.  This queue does not persist across page loads, if any remain in the queue they are lost if not send during that page load.
 * If those requirements ARE set, and there is nothing in the queue, it sends immediately.  So if you have already initialized Amplitude, set the API key and the userId or deviceId, when you call `queueEvent()` it will behave exactly the same as calling `logEvent()` would.
 * If you do use this, immediately after initializing Amplitude (setting the API key and either the `userId` or `deviceId` in amplitude), be sure to call `$amplitude->logQueuedEvents()` to send any events that are on the queue.  If nothing is on the queue, no worries, nothing happens.

Why would you ever use `logEvent()` instead of `queueEvent()`?  The use case for that is when sending events for multiple users, in such a way that you are initializing the data then sending right away.  Using `logEvent()` in that case you would catch right away if something is not initialized right away (it will throw a logic exception), instead of "quietly" starting up a queue that you may never deal with if not expecting there to be one.

**TL;DR:** If you know that you will always be initializing amplitude before calling an event, you can use `logEvent()` directly.  Otherwise use `queueEvent()` and just be sure to call `$amplitude->logQueuedEvents()` once you do have Amplitude initialized and user data set.

