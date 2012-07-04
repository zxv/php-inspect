<?php

define('DEBUG', false);

class LogHelper {

    public function __construct() {
        // Create a list of properties and methods which should not be proxied.
        // This is checked using $this->_isExcluded() in the magic methods.
        $self = get_class();
        $this->_excludedNames = get_class_methods($self);
        array_push(get_class_vars($self), $this->_excludedNames);

        if (!property_exists($this, "_initializedLogger")) {
            // Initialize an associative array to keep track of method calls
            $this->__history = array();

            // Get the logger singleton
            $logger = Logger::getInstance();

            // Pause the logger to avoid an infinite loop
            //$logger->pauseLogger();

            // Get the most recent class name from those intercepted,
            // instantiate a new instance of it.
            //
            // I don't like how this is currently handled. I wish
            // that I could pass the className to here straight from 
            // the callback
            $className = end($logger->_intercepted);

            // Instantiate the reference object. paramHint can be replaced if 
            // this method is appended via runkit
            $newClass = '__ref'.$className;
            $this->__obj = new $newClass(/*paramHint*/);
            if (!isset($this->__obj)) {
                throw new Exception("Error: Cannot create {$className} object");
            }

            // Append the current instance to live objects list in logger instance
            $logger->liveObjects[] = $this;

            // Finally, re-bind the logger's callback to the 'new' statement
            $this->_initializedLogger = true;
            return true;
        }

        echo "Log Helper object already initialized";
        die();
    }

    public function setEvent($type, $data) {
        // Log an event to the logger's internal array.
        // $type is a string, and $data is an associative array.
        
        $entry = array($type => $data);
        array_push($this->__history, $entry);
    }

    private function _isExcluded($methodOrProperty) {
        // Determine whether a method or property is to be proxied.
        //
        // Conditions to avoid exclusion:
        // 1. Is not a LogHelper variable or method
        // 2. LoggerProxy's constructor has been executed
        // 
        if (property_exists($this, "_initializedLogger")) {
            if (!in_array($methodOrProperty, $this->_excludedNames)) {
                return false;
            }
        }

        return true;
    }

    public function __call($method, $arguments) {
        if (!$this->_isExcluded($method)) {
            $this->setEvent("call", array("method" => $method, "args" => $arguments));

            // Call the proxied object's method with the specified arguments
            return call_user_func_array(array($this->__obj, $method), $arguments);
        }
    }

    static public function __callsStatic($method, $arguments) {
        echo "Static method called.";
        die();
/*
      // from http://stackoverflow.com/questions/1279382/magic-get-getter-for-static-properties-in-php
      $mainClass = get_class($this->__obj);

      if (preg_match('/^([gs]et)([A-Z])(.*)$/', $method, $match)) {
        $reflector = new \ReflectionClass($mainClass);
        $property = strtolower($match[2]). $match[3];
        if ($reflector->hasProperty($property)) {
          $property = $reflector->getProperty($property);
          switch($match[1]) {
            case 'get': return $property->getValue();
            case 'set': return $property->setValue($args[0]);
          }     
        } else throw new InvalidArgumentException("Property {$property} doesn't exist");
      }
*/

    }

    public function __get($property) {
        if (!$this->_isExcluded($property)) {
            $this->setEvent("get", array("property" => $property));

            // Return the proxied object's property
            if (!isset($this->__obj->$property)) {
                return array();
            } else {
                return $this->__obj->$property;
            }
        }

        // Not sure whether this is required
        //return $this->$property;
    }

    public function __set($property, $value) {
        if (!$this->_isExcluded($property)) {
            $this->setEvent("set", array("property" => $property, "value" => $value));

            // Set the proxied object's property
            $this->__obj->$property = $value;

            // We've set the object's property where it needs to stored.
            // Now we prevent the setting of default property of the LogHelper
            return true;

        }

        $this->$property = $value;
    }

    public function __unset($property) {
        if (!$this->_isExcluded($property)) {
            $this->setEvent("unset", array("property" => $property));

            // Unset the proxied object's property
            unset($this->__obj->$property);
            return;
        }

        // Not sure whether this is required
        // unset($this->$property);
    }

}

function debugMsg($msg) {
    if (DEBUG == true) {
        echo "$msg\n";
    }
}

function getSource($src, $method) {
    // based on http://stackoverflow.com/questions/7026690/reconstruct-get-code-of-php-function

    // Get class name if passed in as object
    if (is_object($src)) {
        $src = get_class($src);
    }

    // Prepare reflection objects
    $refClass = new ReflectionClass($src);
    $refMethod = $refClass->getMethod($method);

    // Source-grabbing range
    $filename = $refMethod->getFileName();
    $startLine = $refMethod->getStartLine();
    $endLine = $refMethod->getEndLine();
    $length = $endLine - $startLine;

    // Slice and dice the source file
    $source = file($filename);
    $slice = array_slice($source, $startLine, $length);

    // Remove comments
    $slice = array_map(function($x) {return explode("//", $x)[0]; }, $slice);

    // Strip the trailing curly braces from the captured source.
    $firstLine = ltrim($slice[0]);
    $firstLine = ltrim($firstLine, "{");
    $slice[0] = $firstLine;

    $lastKey = key(array_slice($slice, -1, 1, TRUE)); // OH GOD PHP WHY
    $lastLine = rtrim($slice[$lastKey]);
    $lastLine = rtrim($lastLine, "}");
    $slice[$lastKey] = $lastLine;

    $body = implode("", $slice);


    // XXX: Not sure if necessary
    //$body = str_replace(array("\r", "\n"), "", $body);

    // The prize: source code of $method
    return $body;
}

function prepareParametersString($refMethod, $printDefaultValues=true) {
    // Prepare reflection parameters
    $refParams = $refMethod->getParameters();
    $paramCount = count($refParams);
    $paramsOut = array();

    // Iterate over each parameter
    foreach ($refParams as $refParam) {
        $param = $refParam->name;

        if ($printDefaultValues == true) {
            $position = $refParam->getPosition() + 1;

        
            // Spit out default params, if there are any
            if ($refParam->isOptional()) {
                $paramValue = $refParam->getDefaultValue();
                $paramType = gettype($paramValue);
                
                // If param is a string, wrap it in quotes
                if ($paramType == "string") {
                    $paramValue = "'{$paramValue}'";
                }

                // If param is null, escape it as such
                if (is_null($paramValue)) {
                    $paramValue = 'null';
                }

                $paramsOut[] = "\${$param}={$paramValue}";
                continue;
            }
        }

        if ($refParam->getClass()) {
            // Do nothing. Because SCREW type-hinting.

            // Also, don't you think it's a little weird that reflection
            // doesn't throw an exception when I call this on non-object
            // parameters? I mean seriously, it did with getDefaultValue()!
            // Oh well.
        }

        $paramsOut[] = "\${$param}";
    }

    // Join the parameters by commmas
    $finalParams = implode(", ", $paramsOut);
    return $finalParams;
}

function sourceArray($srcClassName) {
    // Process a classname, iterating over each of its methods.
    // Returns an array which contains method source and args.

    $ref = new ReflectionClass($srcClassName);
    $refMethods = $ref->getMethods();

    $objectArray = array();
    foreach ($refMethods as $refMethod) {
        $method = $refMethod->name;

        // Get a string of a method's source, in a single line.
        // XXX: Y u no cache file
        $source = getSource($srcClassName, $method);

        // Get a comma-seperated string of parameters, wrap them in
        // a method definition. Note that all your methods
        // just became public.
        $params = prepareParametersString($refMethod, false); 
        $paramsDefault = prepareParametersString($refMethod); 
        if ($method == "__callStatic") {
            // unconfirmed as of yet
            $methodHeader = "public static function $method({$paramsDefault})";
        } else {
            $methodHeader = "public function $method({$paramsDefault})";
        }
        
        // unconfirmed
        $isStatic = $refMethod->isStatic();

        // Return the two components mentioned above, indexed by method name
        // XXX: Only send one of the params vars, processing on other end
        $objectArray[$method] = array("params" => $params, "paramsDefault" => $paramsDefault, 'methodHeader' => $methodHeader, 'src' => $source, 'isStatic' => $isStatic);
    }
    return $objectArray;
}

function transplantMethods($destClassName, $methods) {
    $finalsrc = "";
    $closeBrace = "";

    foreach($methods as $method => $values) {
        // Concatenate function definition and single-line source
        $finalsrc .=  " ".$values['methodHeader']." { ".$values['src']." } ";

        if ($closeBrace == "") {
            $closeBrace = "}";
        }
    }

    $methods = implode(", ", array_keys($methods));
    debugMsg("Creating methods {$methods} on $destClassName");
    $evalStr = "class $destClassName { $finalsrc }";
    //print $evalStr;
    eval($evalStr);
}

function injectLogHelper($className, $values) {
    $srcLogHelper = sourceArray("LogHelper"); 

    $params = "";
    $newparams = "";
    if (array_key_exists("__construct", $values)) {
        $cValues = $values["__construct"];
        $params = $cValues['params'];
        $newparams = $cValues['paramsDefault'];
    }

    foreach ($srcLogHelper as $method => $mValues) {
        if ($method == "__construct") {
            // set calling parameters of reference object
            $newsrc = str_replace("/*paramHint*/", $params, $mValues['src']);

        } else {
            $newsrc = $mValues['src'];
            $newparams = $mValues['params'];
        }

        runkit_method_add($className, $method, $newparams, $newsrc);
    }
}

function setLoggerMethods($srcClassName, $destClassName, $methods) {
    foreach ($methods as $method => $data) {
        // Get rid of the original methods, since we've already transplanted them
        // This will allow us to invoke __call()
        debugMsg("Removing $srcClassName::$method()");
        if ($data['isStatic'] != true) {
            runkit_method_remove($srcClassName, $method);
        }
    }

    // Clone the constructor from LogHelper to class with original name,
    // while preserving arguments
    injectLogHelper($srcClassName, $methods);

}
 
function createLogHelper($className) {
    // TODO: Reimplement this entire stucture as an object instance
    // TODO: Bring startLogger and pauseLogger static calls to here
    if (strstr($className, '__ref')) {
        return;
    }
    debugMsg("Intercepting new $className");


    $destClassName = "__ref".$className;
    
    // Get a list of methods to be transplanted from original class
    $srcOriginal = sourceArray($className);

    // Create a '__ref' prefixed class that resembles the old one
    transplantMethods($destClassName, $srcOriginal);

    // Bind the logging __call() method to the class with the original name.
    // Each call will be dispatched to the '__ref' prefixed object.
    // Additionally, remove all the original methods so that __call() is invoked.
    setLoggerMethods($className, $destClassName, $srcOriginal);
}
