<?php

//路径配置

define('PATH_ROOT', '/path/to/your/home/');//替换成自己的web目录

define('PATH_LIB', PATH_ROOT . 'lib/');
define('PATH_DATA', PATH_ROOT . 'data/');
define('PATH_LOGS', PATH_ROOT . 'logs/');
define('PATH_LOGS_COMMON', PATH_LOGS . 'common.log');
define('PATH_RUN', PATH_ROOT . 'run/');



//数据库服务器设置

define('DB_DSN', 'mysql:host=127.0.0.1;dbname=weixin');
define('DB_USER', 'root');//替换成自己的数据库用户名
define('DB_PASSWORD', '');//替换成自己的数据库密码

define('DB_TABLE_PREFIX', 'wx_');
define('SESSION_PREFIX', 'wx_');

define('QRCODE_TIMEOUT', '300');

/*
define('DB_KEY_MSG', DB_KEY_PREFIX . 'msg_');

//待处理队列
define('DB_KEY_QUEUE', DB_KEY_PREFIX . 'queue');
//处理中的队列
define('DB_KEY_PQUEUE', DB_KEY_PREFIX . 'pqueue');
*/



//机器人pid文件
define('ROBOT_PID_FILE', PATH_RUN . 'robot.pid');

define('WEIXIN_TOKEN', 'zaq1xsw2');//替换成自己的微信公众号token
define('WEIXIN_APPID', 'wxe54a2e8b1c9d43b9');//替换成自己的微信公众号appid
define('WEIXIN_SECRET', '4691c40bc5f332cc10abe1fc578096ac');//替换成自己的微信公众号app secret

