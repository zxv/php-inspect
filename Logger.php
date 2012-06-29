<?php

class Logger {
    public function __construct() {
        $this->_intercepted = array();
    }

    public function intercept($className) {
        $this->_intercepted[] = $className;
        return $className;
    }

    public function startLogger($callback='intercept') {
        set_new_overload(array($this, $callback));
    }
}
