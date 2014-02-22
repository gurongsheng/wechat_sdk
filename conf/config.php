<?php

//路径配置

define('PATH_ROOT', '/path/to/your/webhome/');//替换成自己的web目录

define('PATH_LIB', PATH_ROOT . 'lib/');
define('PATH_DATA', PATH_ROOT . 'data/');
define('PATH_LOGS', PATH_ROOT . 'logs/');
define('PATH_LOGS_COMMON', PATH_LOGS . 'common.log');
define('PATH_RUN', PATH_ROOT . 'run/');


//redis服务器设置
define('REDIS_SERVER', '127.0.0.1');
define('REDIS_PORT', 6379);
define('REDIS_AUTH', 'your_redis_password');//替换成自己的redis密码

define('REDIS_KEY_PREFIX', 'wx_');
define('REDIS_KEY_MSG', REDIS_KEY_PREFIX . 'msg_');

//待处理队列
define('REDIS_KEY_QUEUE', REDIS_KEY_PREFIX . 'queue');
//处理中的队列
define('REDIS_KEY_PQUEUE', REDIS_KEY_PREFIX . 'pqueue');

//机器人pid文件
define('ROBOT_PID_FILE', PATH_RUN . 'robot.pid');

define('WEIXIN_TOKEN', 'your wechat appid');//替换成自己的微信公众号token
define('WEIXIN_APPID', 'your wechat appid');//替换成自己的微信公众号appid
define('WEIXIN_SECRET', 'your wechat secret');//替换成自己的微信公众号app secret

