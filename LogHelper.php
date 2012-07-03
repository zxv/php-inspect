<?php

class LogHelper {
    //private static $_excludedNames = false;

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
    //echo "\n\n\n\n";
    //echo $filename;
    $source = file($filename);
    $slice = array_slice($source, $startLine, $length);

    // Remove comments
    $slice = array_map(function($x) {return explode("//", $x)[0]; }, $slice);
    $body = implode("", $slice);

    // XXX: Not sure if necessary
    $body = str_replace(array("\r", "\n"), "", $body);
    //$body = rtrim($body, "}"); 

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

function prepareMethodsArray($srcClassName) {
    // Get an array of each method
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
        $methodHeader = "public function $method({$paramsDefault}) {";

        // Return the two components mentioned above, indexed by method name
        // XXX: Only send one of the params vars, processing on other end
        $objectArray[$method] = array("params" => $params, "paramsDefault" => $paramsDefault, 'methodHeader' => $methodHeader, 'src' => $source);
    }
    return $objectArray;
}

function runkitTransplantMethod($className, $methodName, $srcValues) {
    runkit_method_add($className, $methodName, $srcValues["paramsDefault"], $src);
}

function cloneConstructor($srcClassName, $destClassName, $constructorParams) {
    // XXX This function could be streamlined.
    //$constructorParams["src"] = getSource($srcClassName, "__construct"); 
    echo "DEBUG: Adding logger __construct({$constructorParams['paramsDefault']}) to $destClassName\n";
    runkitTransplantMethod($destClassName, "__construct", $constructorParams);
}

function transplantMethods($destClassName, $methods) {
    $finalsrc = "";

    foreach($methods as $method => $values) {
        // Concatenate function definition and single-line source
        $finalsrc .=  " ".$values['methodHeader'].$values['src']." ";
    }

    $methods = implode(", ", array_keys($methods));
    echo "DEBUG: Creating methods {$methods} on $destClassName\n";
    $evalStr = "class $destClassName { $finalsrc }";
    //echo $evalStr;
    eval($evalStr);
}

function setupLoggerConstructor($className, $values) {
    $logHelperSrc = getSource("LogHelper", "__construct"); 
    $logHelperSrc = str_replace("/*paramHint*/", $values['params'], $logHelperSrc);
    $logHelperSrc = rtrim($logHelperSrc, "}");


    runkit_method_add($className, "__construct", $values['paramsDefault'], $logHelperSrc);
}

function setLoggerMethods($srcClassName, $destClassName, $methods) {
    //XXX: Only method calls are logged currently. Add properties.

    #cloneConstructor("LoggerHelper", $srcClassName, $methods["__construct"]);

    foreach ($methods as $method => $data) {
        // Get rid of the original methods, since we've already transplanted them
        // This will allow us to invoke __call()
        echo "DEBUG: Removing $srcClassName::$method()\n";
        runkit_method_remove($srcClassName, $method);
    }
    // XXX: Abstract the evals to use the source file parsing funcs for convenience
    //runkit_method_add($srcClassName, "__construct", $methods["__construct"]["paramsDefault"], "echo \"DEBUG: Creating logger...\n\"; \$this->obj=new $destClassName({$methods["__construct"]["params"]});");
    ////runkit_method_add($srcClassName, "__construct", $methods["__construct"]["paramsDefault"], "echo \"DEBUG: Creating logger...\n\"; print_r(func_get_args()); ");
    setupLoggerConstructor($srcClassName, $methods['__construct']);

    // Clone the constructor from LogHelper to class with original name,
    // while preserving arguments
    //runkit_method_add($srcClassName, "__construct", "\$method, \$args", "echo \"Calling \$method on $destClassName\"; call_user_func_array(array(\$this->obj, \$method), \$args);");
    #runkit_method_add($srcClassName, "__call", "\$method, \$args", "echo \"Calling \$method on $destClassName\"; call_user_func_array(array(\$this->obj, \$method), \$args);");
}
 
function createLogHelper($className) {
    // TODO: Reimplement this entire stucture as an object instance
    // TODO: Bring startLogger and pauseLogger static calls to here
    if (strstr($className, '__ref')) {
        return;
    }
    print "DEBUG: Intercepting new $className\n";


    $destClassName = "__ref".$className;
    
    // Get a list of methods to be transplanted from original class
    $methods = prepareMethodsArray($className);

    // Create a '__ref' prefixed class that resembles the old one
    transplantMethods($destClassName, $methods);

    // Bind the logging __call() method to the class with the original name.
    // Each call will be dispatched to the '__ref' prefixed object.
    // Additionally, remove all the original methods so that __call() is invoked.
    setLoggerMethods($className, $destClassName, $methods);
}
