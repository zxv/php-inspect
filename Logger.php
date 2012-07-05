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

    public function __error($errno, $errmsg, $filename, $linenum, $vars) {
        die("You've been bad!");
    }

    public function __errorFatal() {
        print_r(func_get_args());
        $err = error_get_last();
        print_r(debug_backtrace());
        print_r($err);
        debug_print_backtrace();
        die("You've been badder");        
    }

    public function intercept($className) {
        // This array will be used in the constructor of the Log Helper
        // object to instantiate the reference object __refClassName
        $this->_intercepted[] = $className;

        // Pause the logger so that our various uses of reflection
        // etc don't interfere
        self::pauseLogger();

        // Binds an error handler as a hack to trigger events on static
        // calls and static property set/get
        #self::setErrorHandler();

        // Create a reference object __refClassName which resembles
        // $className, and overload the magic methods of the object
        // possessing the original name.
        createLogHelper($className);

        #self::restoreErrorHandler();

        // Restart the logger, since our work here is done.
        self::startLogger();
        return $className;
    }

    public function pauseLogger() {
        unset_new_overload();
    }

    public function setErrorHandler($callback='__error') {
        set_error_handler(array($this, $callback));
        register_shutdown_function(array($this, '__errorFatal'));
    }

    public function restoreErrorHandler() {
        restore_error_handler();
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
