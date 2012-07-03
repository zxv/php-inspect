PHP Inspect 
===========

This project is comprised of two components:

- A LogHelper, whose magic methods are inject into the original object.
  before this takes place, the original object is stored in __refClassName,
  where ClassName is the original name of the class being logged.

- A Logger singleton which contains each LogHelper, allowing you to
  inspect their state and call their methods if you like.

To begin, run this before the instantiation of the objects that you 
wish to capture:

::

    $logger = Logger::getInstance();
    $logger->startLogger();

If you want to stop logging temporarily, just execute Logger's 
pauseLogger() method.

Once you are finished capturing events, try print_r($logger->liveObjects);

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

