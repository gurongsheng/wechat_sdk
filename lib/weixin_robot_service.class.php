<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker: */

/**
 * 微信天气服务 
 *
 * @author  gurongsheng@gmail.com 
 */

require_once 'weixin_message_service.class.php';

// {{{ class weixin_robot_service

/**
 * 微信机器人服务
 *
 * @author  gurongsheng@gmail.com 
 */
class weixin_robot_service
{
    // {{{ members start

    /**
     * 事件处理队列
     */
    private $__listers = array();

    private static $__db = null;
    private static $__log_file = PATH_LOGS_COMMON;
    private $__sleep_wait_time = 1000000; //单位微秒，默认1秒


    // }}} members end
    // {{{ functions start

    // {{{ public function get_query()

    /**
     * 获取get参数
     *
     * @param {string} query_name 参数名
     * @param {string} default_value 默认值 
     *
     * @return {mixed}
     */
    public function get_query($query_name, $default_value=null)
    {
        return isset($_GET[$query_name]) ? $_GET[$query_name] : $default_value;
    }

    // }}}
    // {{{ public function run()

    /**
     * 机器人http服务运行
     */
    public function run()
    {
        $signature = $this->get_query('signature');
        $timestamp = $this->get_query('timestamp');
        $nonce = $this->get_query('nonce');

        if (!weixin_message_service::check_message_valid($signature, $timestamp, $nonce, WEIXIN_TOKEN)) {
            return;
        }

        $echostr =  $this->get_query('echostr');

        if ($echostr !== null) {
            echo $echostr;
            return;
        }

        $xml = file_get_contents('php://input');
        error_log(date('Y-m-d H:i:s') . "\n" . $xml . "\n", 3, PATH_LOGS . 'wx.log');

        try {
            $msg = weixin_message_service::parse_message($xml);
            $this->_process_message($msg);
            $this->fire('message', array($msg));
        } catch (exception $e) {
            error_log(date('Y-m-d H:i:s') . "\n" . var_export($e, true), 3, PATH_LOGS . 'wx_ex.log');
        }
        
    }
    
    // }}}
    // {{{ public function daemon()

    /**
     * 机器人后台消息处理
     *
     * @param {object} $service 机器人服务对象
     */
    public function daemon($service)
    {

        self::$__log_file = PATH_LOGS . 'daemon.log';

        $pid_file  = ROBOT_PID_FILE;
        
        if (file_exists($pid_file)) {
            $run_pid = (int)file_get_contents($pid_file); 
            if ($run_pid > 0) {
                throw new exception('pid file exists, program may be running.');
            }
        }

        $pid = pcntl_fork();
        if ($pid == -1) {
             throw new exception('fork robot process failure.');  
            exit;
        } else if ($pid) {
            //写入进程号，父进程退出
            file_put_contents($pid_file, $pid);
            echo sprintf('fork robot process success, pid %s.' . "\n", $pid);  
            exit;
        } else {
            //register_shutdown_function(array($this, '_daemon_shutdown'));

            declare(ticks = 1);

            pcntl_signal(SIGTERM, array($this, 'sig_handler'));

            //主程序运行
            try {
                $callback = array(
                        'text_message',
                        'image_message',
                        'voice_message',
                        'video_message',
                        'location_message',
                        'link_message',
                        'subscribe',
                        'unsubscribe',
                        'scan_subscribe',
                        'scan',
                        'location',
                        'context_menu',
                );
                foreach ($callback as $c) {
                    $method = 'on_' . $c;
                    if (method_exists($service, $method)) {
                        $this->on($c, array($service, $method));
                    }
                }
                $this->on('message', array($this, '_process_message'));
                while($message = self::get_halt_message()) {
                    try {
                        self::log('get halt message,msg_id %s, message %s', $message['msg_id'], json_encode($message['message']));
                        $this->fire('message', array($message['message']));
                        self::clean_message($message['msg_id']);
                    } catch (exception $e) {
                        self::clean_message($message['msg_id']);
                        self::log($e->getMessage());
                    }
                }

                while(1) {
                    while($message = self::sub_message()) {
                        try {
                            self::log('sub message,msg_id %s, message %s', $message['msg_id'], json_encode($message['message']));
                            $this->fire('message', array($message['message']));
                            self::clean_message($message['msg_id']);
                        } catch (exception $e) {
                            self::clean_message($message['msg_id']);
                            self::log($e->getMessage());
                        }
                    }
                    
                    //self::log("no message to be process, sleep %f ms.", $this->__sleep_wait_time / 1000);
                    usleep($this->__sleep_wait_time);
                }

            } catch (exception $e) {
                self::log($e->getMessage());
            }
        }

    }

    // }}}
    // {{{ public function _daemon_shutdown()

    public function _daemon_shutdown()
    {
        $pid_file  = ROBOT_PID_FILE;
        @unlink($pid_file);
        self::log('robot process exit.');
    }

    // }}}
    // {{{ public function sig_handler()

    public function sig_handler($signo)
    {
        switch ($signo) {
        case SIGKILL:
            $this->_daemon_shutdown();
            exit;
            break;
        case SIGTERM:
            $this->_daemon_shutdown();
            exit;
            break;
        case SIGHUP:
            break;
        case SIGUSR1:
            break;
        default:
        }
    }

    // }}}
    // {{{ private function _process_message()

    /**
     * 处理daemon消息
     *
     * @param {array} $msg 消息内容
     */
    private function _process_message($msg)
    {
        if (!isset($msg['msg_type'])) {
            throw new exception('未支持的消息类型。');
        }
        if ($msg['msg_type'] === weixin_message_service::MSG_TYPE_EVENT) {
            //处理事件类型
            if (!isset($msg['event'])) {
                throw new exception('未支持的事件类型。');
            }

            switch($msg['event']) {
            case weixin_message_service::EVENT_TYPE_SUB:
                //订阅事件
                $this->fire('subscribe', array(
                            $msg['from_user_name'],
                            $msg['msg_id'],
                            $msg['create_time'],
                            $msg['to_user_name'],
                            ));
                break;
            case weixin_message_service::EVENT_TYPE_UNSUB:
                //取消订阅事件
                $this->fire('unsubscribe', array(
                            $msg['from_user_name'],
                            $msg['msg_id'],
                            $msg['create_time'],
                            $msg['to_user_name'],
                            ));
                break;
            case weixin_message_service::EVENT_TYPE_SCAN_SUB:
                //扫描二维码订阅事件
                $this->fire('scan_subscribe', array(
                            $msg['from_user_name'],
                            $msg['event_key'],
                            $msg['ticket'],
                            $msg['msg_id'],
                            $msg['create_time'],
                            $msg['to_user_name'],
                            ));
                break;
            case weixin_message_service::EVENT_TYPE_SCAN:
                //扫描二维码事件
                $this->fire('scan', array(
                            $msg['from_user_name'],
                            $msg['event_key'],
                            $msg['ticket'],
                            $msg['msg_id'],
                            $msg['create_time'],
                            $msg['to_user_name'],
                            ));
                break;
            case weixin_message_service::EVENT_TYPE_LOCATION:
                //报告位置事件
                $this->fire('location', array(
                            $msg['from_user_name'],
                            $msg['latitude'],
                            $msg['longitude'],
                            $msg['precision'],
                            $msg['msg_id'],
                            $msg['create_time'],
                            $msg['to_user_name'],
                            ));
                break;
            case weixin_message_service::EVENT_TYPE_CLICK:
                //点击菜单事件
                $this->fire('context_menu', array(
                            $msg['from_user_name'],
                            $msg['event_key'],
                            $msg['msg_id'],
                            $msg['create_time'],
                            $msg['to_user_name'],
                            ));
                break;

            }

        } else {
            //处理消息类型
            switch($msg['msg_type']) {
            case weixin_message_service::MSG_TYPE_TEXT:
                $this->fire('text_message', array(
                            $msg['from_user_name'],
                            $msg['content'],
                            $msg['msg_id'],
                            $msg['create_time'],
                            $msg['to_user_name'],
                            ));

                break;
            case weixin_message_service::MSG_TYPE_IMAGE:
                $this->fire('image_message', array(
                            $msg['from_user_name'],
                            $msg['pic_url'],
                            $msg['media_id'],
                            $msg['msg_id'],
                            $msg['create_time'],
                            $msg['to_user_name'],
                            ));

                break;
            case weixin_message_service::MSG_TYPE_VOICE:
                if (!isset($msg['recognition'])) {
                    $msg['recognition'] = '';
                }
                $this->fire('voice_message', array(
                            $msg['from_user_name'],
                            $msg['media_id'],
                            $msg['format'],
                            $msg['recognition'],
                            $msg['msg_id'],
                            $msg['create_time'],
                            $msg['to_user_name'],
                            ));

                break;
            case weixin_message_service::MSG_TYPE_VIDEO:
                $this->fire('video_message', array(
                            $msg['from_user_name'],
                            $msg['media_id'],
                            $msg['thumb_media_id'],
                            $msg['msg_id'],
                            $msg['create_time'],
                            $msg['to_user_name'],
                            ));

                break;
            case weixin_message_service::MSG_TYPE_LOCATION:
                $this->fire('location_message', array(
                            $msg['from_user_name'],
                            $msg['location_x'],
                            $msg['location_y'],
                            $msg['scale'],
                            $msg['label'],
                            $msg['msg_id'],
                            $msg['create_time'],
                            $msg['to_user_name'],
                            ));

                break;
            case weixin_message_service::MSG_TYPE_LINK:
                $this->fire('link_message', array(
                            $msg['from_user_name'],
                            $msg['url'],
                            $msg['title'],
                            $msg['description'],
                            $msg['msg_id'],
                            $msg['create_time'],
                            $msg['to_user_name'],
                            ));

                break;

            }

        }
    }

    // }}}
    // {{{ public function on()

    /**
     * 绑定事件处理
     *
     * @param {string} $ev 事件类型
     * @param {mixed} $callback 事件处理函数
     */
    public function on($ev, $callback)
    {
        if (!isset($this->__listers[$ev])) {
            $this->__listers[$ev] = array();
        }

        $this->__listers[$ev][] = $callback;
    }

    // }}}
    // {{{ public function fire()

    /**
     * 触发事件处理
     *
     * @param {string} $ev 事件类型
     * @param {array}  $args 传递给callback处理函数的参数 可选
     */
    public function fire($ev, $args=null)
    {
        if (!isset($this->__listers[$ev])) {
            return;
        }

        $callback = $this->__listers[$ev];
        foreach ($callback as $c) {
            if (false === call_user_func_array($c, $args)) {
                break;
            }
        }
    }

    // }}}
    // {{{ public static function connect()

    public static function connect()
    {
        if (null === self::$__db) {
            $redis = new redis();
            if (!$redis->connect(REDIS_SERVER, REDIS_PORT, 30) || !$redis->auth(REDIS_AUTH)) {
                throw new exception('connect redis server failure.');
            }

            self::$__db = $redis;
        }
    }

    // }}}
    // {{{ public static function pub_message()

    /**
     * 添加微信消息到消息队列
     *
     * @param {string} $msg_id 消息排重id
     * @param {array} $message 消息结构
     */
    public static function pub_message($msg_id, array $message)
    {
        self::connect();
        //写入key，在写入队列id
        $message = json_encode($message);

        //写入消息,检查消息是否存在
        if (!self::$__db->setnx(REDIS_KEY_MSG . $msg_id, $message)) {
            throw new exception('message exists.');
        }

        //消息id加入到消息队列
        if (!self::$__db->lpush(REDIS_KEY_QUEUE, (string)$msg_id)) {
            self::$__db->del(REDIS_KEY_MSG . $msg_id);
            throw new exception('pub message failure.');
        }
    }

    // }}}
    // {{{ public static function sub_message()

    /**
     * 消费微信消息
     *
     * @return {array}
     */
    public static function sub_message()
    {
        self::connect();

        //消息id加入到消息队列
        $msg_id = self::$__db->rpoplpush(REDIS_KEY_QUEUE, REDIS_KEY_PQUEUE);
        if (!$msg_id) {
            return false;
        }

        $message = self::$__db->get(REDIS_KEY_MSG . $msg_id);
        if (!$message) {
            //消息不存在，删掉队里中的$msg_id;
            self::$__db->lrem(REDIS_KEY_PQUEUE, $msg_id);
            return false;
        }

        return array('msg_id' => $msg_id, 'message' => json_decode($message, true));
    }

    // }}}
    // {{{ public static function get_halt_message()

    /**
     * 消费之前为消费完的微信消息
     *
     * @return {array}
     */
    public static function get_halt_message()
    {
        self::connect();

        //消息id加入到消息队列
        $msg_id = self::$__db->lindex(REDIS_KEY_PQUEUE, -1);
        if (!$msg_id) {
            return false;
        }

        $message = self::$__db->get(REDIS_KEY_MSG . $msg_id);
        if (!$message) {
            //消息不存在，删掉队里中的$msg_id;
            self::$__db->lrem(REDIS_KEY_PQUEUE, $msg_id);
            return false;
        }

        return array('msg_id' => $msg_id, 'message' => json_decode($message, true));
    }

    // }}}
    // {{{ public static function clean_message()

    /**
     * 清理消费完的微信消息
     *
     * @param {string} $msg_id 消息id
     *
     * @return {boolean}
     */
    public static function clean_message($msg_id)
    {
        self::connect();

        self::$__db->multi();
        self::$__db->del(REDIS_KEY_MSG . $msg_id);
        self::$__db->lrem(REDIS_KEY_PQUEUE, $msg_id);
        $res = self::$__db->exec();

        return $res[0] && $res[1];
    }

    // }}}
    // {{{ public static function log()
    
    /** 
     * 写日志,格式跟sprintf类似
     *
     * @param {string} $log 日志内容
     * @param {mixed} $arg1 参数1 可选
     * ...
     */
    public static function log()
    {
        $args = func_get_args();
        $argc = func_num_args();
        if ($argc > 1) {
            $msg = call_user_func_array('sprintf', $args);
        } else {
            $msg = $args[0];
        }

        $msg = date('Y-m-d H:i:s') . ":". $msg . "\n";
        error_log($msg, 3, self::$__log_file);
    }

    // }}}
    // {{{ public static function get_store()

    /**
     * 从数据存储系统里获取内容
     *
     * @param {string} $key
     *
     * @return {string}
     */
    public static function get_store($key)
    {
        self::connect();
        return  self::$__db->get($key);
    }

    // }}}
    // {{{ public static function set_store()

    /**
     * 从数据存储系统里获取内容
     *
     * @param {string} $key
     * @param {string} $val
     *
     * @return {string}
     */
    public static function set_store($key, $val)
    {
        self::connect();
        return  self::$__db->set($key, $val);
    }

    // }}}

    // }}} functions end

}

// }}}

