#!/usr/bin/php
<?php

require 'config.php';

require_once PATH_LIB . 'weixin_message_service.class.php';
require_once PATH_LIB . 'weixin_robot_service.class.php';
require_once PATH_LIB . 'test_service.class.php';

$robot = new weixin_robot_service();
$service = new test_service();

try {
    $robot->daemon($service);
} catch(exception $e) {
    echo $e->getMessage() . "\n";
}
