<?php

class LoggerProxy {
    public function __construct() {
        // Get the logger singleton
        $logger = Logger::getInstance();

        // Pause the logger to avoid an infinite loop
        $logger->pauseLogger();

        // Get the most recent class name from those intercepted,
        // instantiate a new instance of it.
        // 
        // I don't like how this is currently handled. I wish
        // that I could pass the className to here straight from 
        // the callback
        $className = end($logger->_intercepted);
        $this->obj = new $className;

        // Append the current instance to live objects list
        $logger->liveObjects[] = $this;

        // Finally, re-bind the logger's callback to the 'new' statement
        $logger->startLogger();
    }

    public function __call($name, $arguments) {
        echo "Called.";
        die();
        call_user_func_array($this->obj->$name, $arguments);
    }
}

class Logger {
    private static $uniqueInstance;

    protected function __construct() {} 
    private final function __clone() {}

    public static function getInstance() {
        if ( !self::$uniqueInstance ) {
            self::$uniqueInstance = new Logger;
        }

        return self::$uniqueInstance;
    }

    public function intercept($className) {
        $this->_intercepted[] = $className;
        return "LoggerProxy";
        return $className;
    }

    public function pauseLogger() {
        unset_new_overload();
    }

    public function startLogger($callback='intercept') {
        if ( !$this->_intercepted ) {
            $this->_intercepted = array();
        }

        if ( !$this->liveObjects ) {
            $this->liveObjects = array();
        }

        $instance = self::getInstance();
        set_new_overload(array($instance, $callback));
    }
}
