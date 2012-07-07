PHP Inspect 
===========

This project allows you to monitor each new object that is created.

When logging, each object's methods calls are captured (along with arguments), 
and an instance of every object is saved into Logger::$liveObjects. From there, 
you can inspect objects' state, and call their methods if you like.

Getting Started
===============

To begin, run this before the instantiation of the objects that you 
wish to capture:

::

    $logger = Logger::getInstance();
    $logger->startLogger();

After you've invoked the logger, every time the "new" keyword is used, 
the logger will insert code into each of your objects' methods that monitors
method calls into $this->__history for that object. 

Once you're done logging:

::

    $logger->pauseLogger()

Finally, to see the fruits of your logging: 

::

    print_r($logger->liveObjects);

IMPORTANT NOTE
--------------

The logger will not function properly unless Sebastian Bergmann's 
php-test-helpers module is enabled in php.ini. Get it from: 
https://github.com/sebastianbergmann/php-test-helpers/

Additionally, runkit is required. Unfortunately runkit is not actively 
developed by its original maintainers anymore. However, it has been forked 
on github and is available at https://github.com/mtorromeo/runkit

(For PHP 5.4 support, compile both extensions from the latest source instead
of a release).

To compile a php extension, simply cd into each project directory and run

::

    pecl build

After compilation, verify that the .so file resides in your system's php
module directory (typically /usr/lib/php/modules on linux). If not, copy the
.so file there. Add an entry to the [modules] path in your php.ini file.

