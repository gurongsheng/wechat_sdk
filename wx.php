<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker: */

/**
 * 微信服务 
 *
 * @author  gurongsheng@gmail.com 
 */
require_once 'conf/config.php';

require_once PATH_LIB . 'weixin_message_service.class.php';
require_once PATH_LIB . 'weixin_robot_service.class.php';
require_once PATH_LIB . 'test_service.class.php';

$robot = new weixin_robot_service();

function on_message($msg)
{
    $msg_id = $msg['msg_id'];
    weixin_robot_service::pub_message($msg_id, $msg);
    echo weixin_message_service::convert_message(array(
                'ToUserName' => $msg['from_user_name'],
                'FromUserName' => $msg['to_user_name'],
                'CreateTime' => time(),
                'MsgType' => 'text',
                'Content' => '',
                ));
    return false;
}

$robot->on('message', 'on_message');
$robot->run();
