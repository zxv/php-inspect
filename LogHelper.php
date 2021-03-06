<?php

define('DEBUG', true);

class LogHelper {

    public function __construct() {

        if (!property_exists($this, "__history")) {
            $logger = Logger::getInstance();
            
            // Initialize an associative array to keep track of method calls
            $this->__history = array();

            // Append the current instance to live objects list in logger instance
            $logger->liveObjects[] = $this;
            //$logger->liveObjects[] = $this;

            // Finally, re-bind the logger's callback to the 'new' statement
            //return true;
        }

        //echo "Log Helper object already initialized";
        //die();
    }

    public function setEvent($type, $data) {
        // Log an event to the logger's internal array.
        // $type is a string, and $data is an associative array.
        //
        // Enhancement idea: names of args
        $method = '/*method*/';
        $data = array("method" => $method, "args" => func_get_args());
        array_push($this->__history, array("call" => $data));
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

function getSource($src, $method, $filename=null) {
    // based on http://stackoverflow.com/questions/7026690/reconstruct-get-code-of-php-function

    // Get class name if passed in as object
    if (is_object($src)) {
        $src = get_class($src);
    }

    // Prepare reflection objects
    $refClass = new ReflectionClass($src);
    $refMethod = $refClass->getMethod($method);

    if ($filename == null) {
        $filename = $refMethod->getFileName();
    }

    // Source-grabbing range
    $startLine = $refMethod->getStartLine();
    $endLine = $refMethod->getEndLine();
    $length = $endLine - $startLine;

    // Slice and dice the source file
    $source = file($filename);
    $slice = array_slice($source, $startLine, $length);

    // Remove comments
    // Avoid clashing with protocols (e.g. php://)
    $slice = array_map(function($x) {$match = preg_split( "/(?<!:)\/\//", $x); return $match[0]; }, $slice);

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
    /*
    // debug helper
    if ($refMethod->name == "") {
        $err = true;
    } else {
        $err = false;
    }*/
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
                
                // What follows is a number of special cases for
                // default argument types

                // If param is a string, wrap it in quotes
                if ($paramType == "string") {
                    $paramValue = "'{$paramValue}'";
                }

                // If param is null, escape it as such
                if (is_null($paramValue)) {
                    $paramValue = 'null';
                }

                if ($paramValue === false) {
                    $paramValue = 'false';
                }

                if ($paramValue === true) {
                    $paramValue = 'true';
                }

                if (gettype($paramValue) == "array") {
                    $paramValue = var_export($paramValue, true);
                    $hint = "array ";
                } else {
                    $hint = '';
                }

                // debug helper
                //if ($err == true) {
                //    print $paramValue;
                //}

                $paramsOut[] = "{$hint}\${$param}={$paramValue}";
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
    // Possible replacement: get_class_methods()?

    $objectArray = array();
    foreach ($refMethods as $refMethod) {
        $method = $refMethod->name;

        // Get a string of a method's source, in a single line.
        // XXX: Y u no cache file
        $filename = $refMethod->getFileName();
        if (!empty($filename)) {
            $source = getSource($srcClassName, $method, $filename);
        } else {
            // We presume that if no filename is found, the method is
            // built-in and we are unconcerned with it
            debugMsg("Skipping builtin method $method");
            continue;
        }
        
        // Check to determine whether the method being inspected is static
        $isStatic = $refMethod->isStatic();

        // Get a comma-seperated string of parameters, wrap them in
        // a method definition. Note that all your methods
        // just became public.
        $params = prepareParametersString($refMethod, false); 
        $paramsDefault = prepareParametersString($refMethod); 
        if ($isStatic) {
            // unconfirmed as of yet
            $methodHeader = "public static function $method({$paramsDefault})";
        } else {
            $methodHeader = "public function $method({$paramsDefault})";
        }

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
    // Get rid of the original methods, since we've already transplanted them
    // This will allow us to invoke __call().
    foreach ($methods as $method => $data) {
        debugMsg("Removing $srcClassName::$method()");
        if ($data['isStatic'] != true) {
            runkit_method_remove($srcClassName, $method);
        }
    }

    // Clone the constructor from LogHelper to class with original name,
    // while preserving arguments
    injectLogHelper($srcClassName, $methods);

}

function injectMethodLogger($className, $srcOriginal) {
    $setEvent = getSource("LogHelper", "setEvent");

    foreach ($srcOriginal as $method => $values) {

        // If the method being intercepted is the constructor, pass in the proper source
        if ($method == "__construct") {
            $toInject = getSource("LogHelper", "__construct");
            $constructed = true;
            // $toInject['src'];
        } elseif ($method == "__get") {
            // XXX: Not logged. Should it be?
            $toInject = 'if( $name == "__history" ) { return $name; }';
        } else {
            $toInject = str_replace('/*method*/', $method, $setEvent);
        }

        $oldSrc = $values['src'];
        $newcode = $toInject." ".$oldSrc;

        $isStatic = $values['isStatic'];

        // For now, only non-static calls are logged
        if (!$isStatic) {
            debugMsg("Injecting logger code into $className::{$method}");
            runkit_method_redefine($className, $method, $values['paramsDefault'], $newcode);
        }
    }

    // The original object had no constructor
    if (!isset($constructed)) {
        $toInject = getSource("LogHelper", "__construct");
        runkit_method_add($className, "__construct", "", $toInject);
    }
}
 
function createLogHelper($className) {
    // TODO: Reimplement this entire stucture as an object instance
    // TODO: Bring startLogger and pauseLogger static calls to here
    if (strstr($className, '__ref')) {
        return;
    }

    // XXX: Expensive call (hell, hasn't stopped me yet)
    if (DEBUG == true) {
        $dbg = debug_backtrace();
        $lineNumber = $dbg[1]['line'];
        $fileName = $dbg[1]['file'];
        debugMsg("Intercepting new $className on line $lineNumber of $fileName");
    }

    $destClassName = "__ref".$className;
    
    // Get a list of methods to be transplanted from original class
    $srcOriginal = sourceArray($className);


    // 
    injectMethodLogger($className, $srcOriginal);

    // Create a '__ref' prefixed class that resembles the old one
    // Possible replacement? class_alias($className, $destClassName);
    //transplantMethods($destClassName, $srcOriginal);

    // Bind the logging __call() method to the class with the original name.
    // Each call will be dispatched to the '__ref' prefixed object.
    // Additionally, remove all the original methods so that __call() is invoked.
    //setLoggerMethods($className, $destClassName, $srcOriginal);
    debugMsg("Creating new $className object\n");
}
