<?php
/* Please see README for dependency info */

require("LogHelper.php");

class Logger {
    private static $uniqueInstance;

    protected function __construct() {
        if (!extension_loaded('test_helpers')) {
            echo "This logger will not work without the test_helpers extension. ";
            echo "Download the and compile the latest version from ";
            echo "https://github.com/sebastianbergmann/php-test-helpers\n";

            exit(1);
        }

        if (!extension_loaded('runkit')) {
            echo "This logger will not work without the runkit extension. ";
            echo "Download the and compile the latest version from ";
            echo "https://github.com/mtorromeo/runkit";

            exit(1);
        }
    } 

    private final function __clone() {}

    public static function getInstance() {
        if (!self::$uniqueInstance) {
            self::$uniqueInstance = new Logger;
        }

        return self::$uniqueInstance;
    }

    public function intercept($className) {
        // This array will be used in the constructor of the Log Helper
        // object to instantiate the reference object __refClassName
        $this->_intercepted[] = $className;

        // Pause the logger so that our various uses of reflection
        // etc don't interfere
        self::pauseLogger();

        // Create a reference object __refClassName which resembles
        // $className, and overload the magic methods of the object
        // possessing the original name.
        createLogHelper($className);

        // Restart the logger, since our work here is done.
        self::startLogger();
        return $className;
    }

    public function pauseLogger() {
        unset_new_overload();
    }

    public function startLogger($callback='intercept') {
        if (!isset($this->_intercepted)) {
            $this->_intercepted = array();
        }

        if (!isset($this->liveObjects)) {
            $this->liveObjects = array();
        }

        $instance = self::getInstance();
        set_new_overload(array($instance, $callback));
    }
}
