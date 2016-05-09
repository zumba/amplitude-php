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
    ->setUserProperties([
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

## Getting Started & Troubleshooting

When you are initially getting your application set up, if you do not see your event show up in Amplitude, you may need to do a little troubleshooting.  Normally your indication that "it worked" is when you see your event show up in your Amplitude app for the first time.

If you never see that first event show up, you can see what Amplitude's response is when the event is logged.  This may help find and fix the problem (such as an invalid API key, PHP environment errors, connection problems, etc.)

Amplitude uses `Psr\Logger` for logging the communication with the Amplitude HTTP API.  You can take advantage of this by setting a logger (using `$amlitude->setLogger()`) to help catch any problems.

### Stand-alone Troubleshooting Script

Below is a stand-alone script, meant to be copied into a PHP file at the root of your application's document root.  Just change the `APIKEY` and if needed, adjust the line that requires the `autoload.php` file for composer.  Then visit the script's URL from a browser to see any messages logged.

```php
<?php
// Stand-alone Amplitude troubleshooting script - just change APIKEY in next line
$apikey = 'APIKEY';

// Composer Autoloader - If new to composer, see https://getcomposer.org
require __DIR__ . '/vendor/autoload.php';

// Make sure if there is some error, we will see it
ini_set('display_errors', true);
error_reporting(E_ALL);

// Quick logger to display log messages - NOT for production use, this displays log message to the browser
class ChattyLogger extends \Psr\Log\AbstractLogger
{
    public function log($level, $message, array $context = [])
    {
        echo "<p><strong>".ucfirst($level).":</strong> $message<br>";
        if (!empty($context)) {
            echo '<strong>Context:</strong><br><span class="code">'.print_r($context,true).'</span>';
        }
        echo '</p>';
    }
}

$chatty = new ChattyLogger();
// Test logging an event
?>
<style>
.code {
    display: inline-block;
    border: 1px solid #a7a7a7;
    padding: 15px;
    margin: 0 5px;
    background-color: #eaeaea;
    white-space: pre;
}
p {
    padding-bottom: 5px;
    border-bottom: thin dashed gray;
}
</style>
<h1>Testing Amplitude Log Event Response</h1>
<h2>API Key: '<?= $apikey ?>'</h2>
<?php
$amplitude = new \Zumba\Amplitude\Amplitude();

// Add the chatty logger so we can see log messages
$amplitude->setLogger($chatty);

// Initialize Amplitude with the API key and a dummy test user ID
$amplitude->init($apikey, 'TEST-USER-ID');

$chatty->info('Calling $amplitude->logEvent(\'TEST EVENT\')...');

// Log a test event
$amplitude->logEvent('TEST EVENT');

$chatty->info('Done logging event');
```

### Troubleshooting Tips

* The Amplitude library will throw a `LogicException` for any problems caused by errors in the code, for instance if you try to log an event without setting the API key first, or try to log an event without specifying the event type.  Make sure your server's error logging is set up to display (or otherwise log) any exceptions that might be thrown so that you can see if there is a coding error causing a problem.
* Make sure PHP error logging is enabled (or display errors is enabled), so you can see any PHP errors that may point to the problem.
* Use the `setLogger(...)` method in amplitude to use your app's logging or your own custom logger like the standalone test script above.  As long as it implements the `Psr\Log\LoggerInterface`.
  * If no logs are generated:  It did not attempt to send an event after the point your app's logger was set, or the event was logged using a different instance that does not have a logger set.
  * If you see `Curl error:` logged: then something went wrong when it tried to send the request, the error message and context should help point to the problem.
  * If there are no curl errors, it will log a message starting with `Amplitude HTTP API response:`:
    * `success` with `httpCode = 200` : Amplitude got the request and the event should have been logged.  If you are not seeing it in Amplitude, check again after a few minutes, sometimes Amplitude can lag a little behind.
    * Anything Else: The event was not logged successfully, refer to the message and context to help troubleshoot the problem.

# Logging Anonymous Users

Since this is a PHP SDK, there are a lot of options for tracking Anonymous users.  Since this could be run in CLI mode or as a cron job, this SDK does not handle sessions for you.

Your application will need to figure out a way to track users across multiple page loads, chances are your app is already doing this with PHP sessions or something like it.

Once you have that unique identifier that allows an anonymous user to be tracked between page loads, set that as the `deviceId`.  Do this for both logged in users and anonymous users, that way once a user is logged in their past events will be linked to the user.

```php
// After your application has set up the session (for instance in your bootloader or similar), initialize Amplitude:
$amplitude = \Zumba\Amplitude\Amplitude::getInstance();
// Notice we are not setting second parameter here for user ID, we will do that below if it is available
$amplitude->init('APIKEY');

// Can use the PHP session ID, or alternatively, any unique string your application uses to track sessions
$sessionId = session_id();

// Keep track of whether we have a session or user ID
$canLogEvents = false;
if (!empty($sessionId)) {
    $amplitude->setDeviceId($sessionId);
    $canLogEvents = true;
}
// Presumes $applicationUserId set prior to this by your application
if (!empty($applicationUserId)) {
    $amplitude->setUserId($applicationUserId);
    $canLogEvents = true;
}
if (!empty($userData)) {
    // If you have other user properties, set them as well...  They will be set on the first event sent to Amplitude
    $amplitude->setUserProperties($userData);
}

if ($canLogEvents) {
    // Make sure to send any events that may have gotten queued early
    $amplitude->logQueuedEvents();
} else {
    // Do not have a user ID or device ID for this page load, so set `optOut`, to prevent amplitude from trying
    // to send events (since it won't work without user or device ID)
    $amplitude->setOptOut(true);
}

// -- Meanwhile, in another part of the code... --

// Just queue events as normal
\Zumba\Amplitude\Amplitude::getInstance()->queueEvent('EVENT');

```

# User Properties
There is one main way to set user properties, and this will send the user properties with the next Amplitude event sent to Amplitude:
```php
\Zumba\Amplitude\Amplitude::getInstance()
    ->setUserProperties(
        [
            'name' => 'Jane',
            'dob' => $dob,
            // ...
        ]
    );
```
You would typically call this right before calling `logQueuedEvents()` to make sure it gets sent with the first queued event (if there are any events).

Using this method, it only sends the user information with one event, since once a user property is set in Amplitude it persists for all events that match the user ID or event ID.

Also note that if there happens to be no events sent after `setUserProperties()` are sent, those properties will not get sent to Amplitude.

One option, is to use a login event that adds the user info when the user has logged in, and sends it in a login event.  That way you only send user properties for the page load that the user logs in.

Alternatively, just add the user properties with every page load when initializing the Amplitude object.  This is the option used in the examples.

## Adding User Properties on Event Object
Another option for setting the user properties, is setting them on the Event object itself.  You can do this by setting/changing `userProperties`, or by using the `setUserProperties()` method on the `Event` object.

You would typically use this in situations similar to the one in the next section, for times you may be sending events for different users in the same page load.

```php
$event = new \Zumba\Amplitude\Event();
// Method 1 - set user properties method:
$event->setUserProperties(
    [
        'name' => 'Rambo',
        // ...
    ]
);
// If you called setUserProperties() a second time, it would overwrite any properties with the same name but leave
// others intact

// Method 2 - just set the userProperties directly:
$event->userProperties = [
    'name' => 'Mary',
    // ...
];
// This works just like you would expect: it will reset what is already there.
// Note that prior to anything being set, $event->userProperties will be null, not an empty array
```
You can find more information about how the Event object works below in the [Events](#events) section.
### Use-Case: Events for Many Users
In situations where you will be sending many Amplitude events for different users, you can actually add the user properties on the event object itself as we covered in the previous section.  In fact, everything can be set on the event object itself except for the API Key.

For example:

```php
// Here, we are not using Singleton as we will only use this connection to send these batch user events, we don't
// want any user data from the Singleton instance to accidentally bleed into the first user's event
$amplitude = new \Zumba\Amplitude\Amlitude();
// Alternatively, if we wanted to re-use the same Amplitude object with the same key elsewhere in the code, could
// have used:
// $amplitude = \Zumba\Amplitude\Amplitude::getInstance('NAMED-INSTANCE');
// That will maintain the same Amplitude instance anywhere that requests that specific name.
$amplitude->init('APIKEY');
// $userEvents might be an array your application generates with user info and events that need to be sent
foreach ($userEvents as $myUserEvent) {
    $event = $amplitude->event();
    // Notice below we are setting user ID and user data on the event itself, not inside Amplitude where it would end
    // up persisting the user ID between logged events...

    // The below assumes an array set like so:
    /*
    $myUserEvent = [
        'id' => 'user-id',
        'user_details' => [], // key/value array of user info
        'event_type' => 'EVENT', // event to log
        'event_properties' => [], // key/value array of event properties to set
    ];
     */
    $event->userId = $myUserEvent['id'];
    $event->userProperties = $myUserEvent['user_details'];
    $event->eventType = $myUserEvent['event_type'];
    $event->set($myUserEvent['event_properties']);
    // Since we used $amplitude->event() to get event object, it will be the event to be sent when we call this
    $amplitude->logEvent();
    // Above we are using logEvent instead of queueEvent since the code is not "spread out", we can ensure that
    // amplitude is already initialized and all the requirements (eventType and either userId or deviceId) are set
    // on the event already
}
```
See the next section for more details about what you can do with the `Event` object.

# Events

This library is very flexible, in terms of giving you options for how to set up the event to be sent to Amplitude.  Use the method that best suites your own preferences and project needs.

## Just Send It!

The first option is the easiest, the one used in the main example.  Just call either `queueEvent` or `logEvent` with the event type and event properties if there are any.
```php
// Send just event with no event properties:
\Zumba\Amplitude\Amplitude::getInstance()
    ->queueEvent('EVENT-NAME');

// Send event and add a property:
\Zumba\Amplitude\Amplitude::getInstance()
    ->queueEvent('EVENT-NAME', ['property1' => 'value1']);
```

## Using Event Object

You have the option to use an event object to set up the event, if this is more convenient for your use case.  The example of how to do this is below:

```php
// Get the next event that will be queued or sent:
$event = \Zumba\Amplitude\Amplitude::getInstance()->event();

// Set up the event here, by setting properties...
$event->eventType = 'EVENT-NAME';

// Queue or send the event - since we got the event using the event method, it will be the one used on the next
// queue or send, no need to pass it back in.
\Zumba\Amplitude\Amplitude::getInstance()->queueEvent();
```

### Setting event properties
As far as setting the event properties on the event object, you have a few options once you have that `$event` object:

```php
// First, probably the most common, you can use the magic set methods to just set the property like this:
$event->propertyName = 'property value';

// Set using set(), handy for property names that are invalid as PHP variables:
$event->set('Property name with Space', 'property value');

// Set can be chained:
$event->set('prop1', 'val1')
    ->set('prop2', 'val2')
    ->set('prop3', 'val3');

// Pass in array of properties for the first parameter:
$event->set(
    [
        'prop1' => 'val1',
        'prop2' => 'val2',
    ]
);

```
### Unsetting event properties
If a property has already been set on an event, you can unset it.

```php
// For non-standard property names, use the unsetProperty method:
$event->unsetProperty('My Location');

// Magic unset also works
unset($event->productId);
```

### Sending or Queuing the Event

Once you have set all the event properties, you can then send or queue the event, just by calling `$amplitude->queueEvent()` or `$amplitude->logEvent()`.

Note: If you just created a new `Event` object, before calling `queueEvent()` or `logEvent()`, you must pass that event into amplitude like this:
```php
$event = new \Zumba\Amplitude\Event();

// Set event properties here

// Pass the event into amplitude and queue it
\Zumba\Amplitude\Amplitude::getInstance()
    ->event($event)
    ->queueEvent();
```

If however, you used the event method to get the event, no need to pass it back into Amplitude.
```php
$event = \Zumba\Amplitude\Amplitude::getInstance()->event();

// Set event properties here

// Send that event
\Zumba\Amplitude\Amplitude::getInstance()->queueEvent();
```
In other words, when dealing with the `Event` object directly, it must have passed through Amplitude's `event()` method one way or the other before attempting to call `queueEvent()` or `logEvent()`.

### Tip: No Need to Pass Around Event Object
If you need to set up an event across different parts of the code, you ***could*** pass that event around, but you don't ***have to***, as `Amplitude` keeps track of the next event object to be sent or queued.  So you could do something like this:

```php
$event = \Zumba\Amplitude\Amplitude::getInstance()->event();
$event->eventType = 'Complicated Event';

// -- Meanwhile, in another part of the code... --
// As long as the event has not yet been sent or queued up, you can get it and change it as needed:
$event = \Zumba\Amplitude\Amplitude::getInstance()->event();
$event->deviceId = 'DEVICE ID';

// Just remember, once finished setting up the event, call queueEvent() or logEvent() once.
\Zumba\Amplitude\Amplitude::getInstance()->queueEvent();
```

### Don't forget the eventType

When using the event object, remember that the `eventType` must be set one way or another before the event is queued or logged.

```php
// Either set it this way:
$event->eventType = 'EVENT';

// OR set it when logging/queuing the event:
\Zumba\Amplitude\Amplitude::getInstance()
    ->queueEvent('EVENT');
```

Note that setting it when calling `queueEvent()` or `logEvent()` will overwrite the `eventType` if it is already set in the event object, but any other properties set on the event will remain intact.

### Custom Event Factory

Say you wanted to make some sort of factory that is cranking out events to send, maybe even each with it's own user ID already set...  You could do something like this:
```php
$amplitude = \Zumba\Amplitude\Amplitude::getInstance()
    ->init('APIKEY');
foreach ($eventFactory->getEvents() as $event) {
    $amplitude->event($event)
        ->queueEvent();
}
```

## Using `event($array)` - Quickly set event properties
For times that you just want to quickly set some properties on the next event that will be queued or sent, but aren't ready to actually send or queue the event yet, you can pass in an array of properties into the `$amplitude->event()` method.

```php
// Convenience way to quickly add properties to an event, just pass in array of properties to the event method:
\Zumba\Zumba\Amplitude::getInstance()->event(
    [
        'eventProp' => 'Event Value',
        'productId' => 'acme-widget-45',
        'price' => 15.32,
    ]
);

// The above is equivalent to:
$event = \Zumba\Zumba\Amplitude::getInstance()->event();
$event->set(
    [
        'eventProp' => 'Event Value',
        'productId' => 'acme-widget-45',
        'price' => 15.32,
    ]
);
```

# queueEvent() vs. logEvent()

The difference?

* Both:
 * Require the `eventType` to be set and non-empty.
* `logEvent()`:
 * Requires API Key to be set, will throw exception if not set yet.
 * Requires either `userId` or `deviceId` to be set, will throw exception if not set either on the amplitude instance, or on the event itself.
 * Always sends the event at the time it is called, assuming requirements met.
* `queueEvent()`:
 * Does **not** require API key to be set ***first***.
 * Does **not** require `userId` or `deviceId` to be set ***first***.
 * If either of those are not set, or if there are still un-sent events in the queue, it will add the event to an internal queue.  This queue does not persist across page loads, if any remain in the queue they are lost if not send during that page load.
 * If those requirements **are** set, and there is nothing in the queue, it sends immediately.  So if you have already initialized Amplitude, set the API key and the `userId` or `deviceId`, when you call `queueEvent()` it will behave exactly the same as calling `logEvent()` would.
 * If you do use this, immediately after initializing Amplitude (setting the API key and either the `userId` or `deviceId` in amplitude), be sure to call `$amplitude->logQueuedEvents()` to send any events that are on the queue.  If nothing is on the queue, no worries, nothing happens.

Why would you ever use `logEvent()` instead of `queueEvent()`?  The use case for that is when sending events for multiple users, in such a way that you are initializing the data then sending right away.  Using `logEvent()` in that case you would catch right away if something is not initialized right away (it will throw a logic exception), instead of "quietly" starting up a queue that you may never deal with if not expecting there to be one.

**TL;DR:** If you know that you will always be initializing amplitude before calling an event, you can use `logEvent()` directly.  Otherwise use `queueEvent()` and just be sure to call `$amplitude->logQueuedEvents()` once you do have Amplitude initialized and user data set.

