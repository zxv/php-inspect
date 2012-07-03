<?php
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
    $body = implode("", array_slice($source, $startLine, $length));

    // XXX: Not sure if necessary
    $body = str_replace(array("\r", "\n"), "", $body);
    //$body = rtrim($body, "}"); 

    // The prize: source code of $method
    return $body;
}

function prepareParametersString($refMethod) {
    // Prepare reflection parameters
    $refParams = $refMethod->getParameters();
    $paramCount = count($refParams);
    $paramsOut = array();

    // Iterate over each parameter
    foreach ($refParams as $refParam) {
        $param = $refParam->name;
        $position = $refParam->getPosition() + 1;

        // Spit out default params, if there are any
        if ($refParam->isOptional()) {
            $paramsOut[] = "\${$param}={$refParam->getDefaultValue()}";
            continue;
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
        $params = prepareParametersString($refMethod); 
        $paramStr = "public function $method({$params}) {";

        // Return the two components mentioned above, indexed by method name
        $objectArray[$method] = array('paramStr' => $paramStr, 'src' => $source);
    }
    return $objectArray;
}

function transplantMethods($destClassName, $methods) {
    $finalsrc = "";

    foreach($methods as $values) {
        // Concatenate function definition and single-line source
        $finalsrc .=  " ".$values['paramStr'].$values['src']." ";
    }
    $methods = implode(", ", array_keys($methods));
    echo "DEBUG: Creating methods {$methods} on $destClassName\n";
    $evalStr = "class $destClassName { $finalsrc }";
    //echo $evalStr;
    eval($evalStr);
}

function setLoggerMethods($srcClassName, $destClassName, $methodNames) {
    //XXX: Only method calls are logged currently. Add properties.

    foreach ($methodNames as $method) {
        // Get rid of the original methods, since we've already transplanted them
        // This will allow us to invoke __call()
        echo "DEBUG: Removing $srcClassName::$method()\n";
        runkit_method_remove($srcClassName, $method);
    }
    // XXX: Replacing the constructor, are ya? What about the old one and its params?
    // XXX: Abstract the evals to use the source file parsing funcs for convenience
    runkit_method_add($srcClassName, "__construct", "", "echo \"DEBUG: Creating logger...\n\"; \$this->obj=new $destClassName;");
    runkit_method_add($srcClassName, "__call", "\$method, \$args", "echo \"Calling \$method on $destClassName\"; call_user_func_array(array(\$this->obj, \$method), \$args);");
}
 
function createLogHelper($className) {
    // TODO: Reimplement this entire stucture as an object instance
    // TODO: Bring startLogger and pauseLogger static calls to here
    if (strstr($className, '__ref')) {
        return;
    }

    $destClassName = "__ref".$className;
    
    // Get a list of methods to be transplanted from original class
    $methods = prepareMethodsArray($className);

    // Create a '__ref' prefixed class that resembles the old one
    transplantMethods($destClassName, $methods);

    // Bind the logging __call() method to the class with the original name.
    // Each call will be dispatched to the '__ref' prefixed object.
    // Additionally, remove all the original methods so that __call() is invoked.
    setLoggerMethods($className, $destClassName, array_keys($methods));
}
