<?php
/*
 * This project is comprised of two components:
 * - A logger proxy class that acts as a proxy to every newly created object.
 * - A container logger class which holds bound logger proxies, allowing you to
 *   inspect their state and call their methods if you like.
 *
 * To begin, run this before the instantiation of the objects that you 
 * wish to capture:
 * $logger = Logger::getInstance();
 * $logger->startLogger();
 *
 * If you want to stop logging temporarily, just execute Logger's 
 * pauseLogger() method.
 *
 * Once you are finished, just print_r($logger->liveObjects);
 *
 * IMPORTANT:
 * The logger will not function properly unless Sebastian Bergmann's 
 * php-test-helpers module is enabled in php.ini.
 *
 * Get it from https://github.com/sebastianbergmann/php-test-helpers/
 * (For PHP 5.4 support, compile from the latest source instead of a release).
 *
 */

class LoggerProxy {
    private static $_excludedNames = false;

    public function __construct() {
        // Create a list of names which should not be proxied.
        // This is checked using $this->_isExcluded() in the magic methods.
        $self = get_class();
        self::$_excludedNames = get_class_methods($self);
        array_push(get_class_vars($self), self::$_excludedNames);

        if (!property_exists($this, "_initializedLogger")) {
            // Initialize an associative array to keep track of method calls
            $this->_history = array();

            // Get the logger singleton
            $logger = Logger::getInstance();

            // Pause the logger to avoid an infinite loop
            $logger->pauseLogger();

            // Get the most recent class name from those intercepted,
            // instantiate a new instance of it.
            
            // I don't like how this is currently handled. I wish
            // that I could pass the className to here straight from 
            // the callback
            $className = end($logger->_intercepted);
            /// print_r(debug_backtrace());
            $this->obj = new $className;
            if (!isset($this->obj)) {
                throw new Exception("Error: Cannot create {$className} object");
            }

            // Append the current instance to live objects list in logger instance
            $logger->liveObjects[] = $this;

            // Finally, re-bind the logger's callback to the 'new' statement
            $logger->startLogger();

            $this->_initializedLogger = true;
            return true;
        }

        echo "Logger Proxy object already initialized";
        //print_r(debug_backtrace());
        die();
    }

    public function setEvent($type, $data) {
        // Log an event to the logger's internal array.
        // $type is a string, and $data is an associative array.
        
        $entry = array($type => $data);
        array_push($this->_history, $entry);
    }

    private function _isExcluded($methodOrProperty) {
        // Determine whether a method or property is to be proxied.
        //
        // Conditions to avoid exclusion:
        // 1. Is not a class variable or method
        // 2. LoggerProxy's constructor has been executed
        // 
        if (property_exists($this, "_initializedLogger")) {
            if (!in_array($methodOrProperty, self::$_excludedNames)) {
                return false;
            }
        }

        return true;
    }

    public function __call($method, $arguments) {
        if (!$this->_isExcluded($method)) {
            $this->setEvent("call", array("method" => $method, "args" => $arguments));

            // Call the proxied object's method with the specified arguments
            return call_user_func_array(array($this->obj, $method), $arguments);
        }
    }

    public function __get($property) {
        if (!$this->_isExcluded($property)) {
            $entry = array("get" => array("property" => $property));
            array_push($this->_history, $entry);

            // Return the proxied object's property
            if (!isset($this->obj->$property)) {
                return array();
            } else {
                return $this->obj->$property;
            }
        }

        // Not sure whether this is required
        //return $this->$property;
    }

    public function __set($property, $value) {
        if (!$this->_isExcluded($property)) {
            $this->setEvent("set", array("property" => $property, "value" => $value));
            //array_push($this->_history[] = array("set" => array("property" => $property, "value" => $value)));

            // Set the proxied object's property
            $this->obj->$property = $value;

            // We've created the object's property where it needs to stored.
            // Prevent the setting of default property of the LoggerProxy object
            return true;

        }

        $this->$property = $value;
    }

    public function __unset($property) {
        if (!$this->_isExcluded($property)) {
            $this->_history[] = array("unset" => array("property" => $property));

            // Unset the proxied object's property
            unset($this->obj->$property);
            return;
        }

        // Not sure whether this is required
        // unset($this->$property);
    }

}

class Logger {
    private static $uniqueInstance;

    protected function __construct() {} 
    private final function __clone() {}

    public static function getInstance() {
        if (!self::$uniqueInstance) {
            self::$uniqueInstance = new Logger;
        }

        return self::$uniqueInstance;
    }

    public function intercept($className) {
        $this->_intercepted[] = $className;
        return "LoggerProxy";
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
