<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker: */

/**
 * 微信服务 
 *
 * @author  gurongsheng@gmail.com 
 */

require_once PATH_LIB . 'weixin_message_service.class.php';

/**
 * 用微信扫描二维码做认证服务 流程：生成带参数二维码，显示到页面，用户扫描二维码，认证用户
 */
class weixin_scan_auth_service
{
    // {{{ members start

    /**
     * 微信消息服务对象
     */
    protected $__weixin_service = null;

    /**
     * 数据库对象
     */
    protected static $__db = null;

    // }}} members end
    // {{{ functions start

    // {{{ public function __construct()

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->__weixin_service = new weixin_message_service(WEIXIN_APPID, WEIXIN_SECRET);
    }

    // }}}
    // {{{ public function connect()

    /**
     * 连接数据库
     */
    public function connect()
    {
        if (null === self::$__db) {
            $conn = new pdo(DB_DSN, DB_USER, DB_PASSWORD, array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\''));
            self::$__db = $conn;
        }
    }

    // }}}
    // {{{ public function table_name()

    /**
     * 获取带前缀的表名
     *
     * @param {string} $name 表名
     *
     * @return {string}
     */
    public function table_name($name)
    {
        return DB_TABLE_PREFIX . $name; 
    }

    // }}}
    // {{{ public function session()

    /**
     * 存取session
     *
     * @param {string} $key session键
     * @param {string} $val session值 
     *
     * @return {string}
     */
    public function session($key, $val=null)
    {
        $_key = SESSION_PREFIX . $key; 

        if (null === $val) {
            //获取
            return isset($_SESSION[$_key]) ? $_SESSION[$_key] : null;
        } else {
            $_SESSION[$_key] = $val;
        }
    }

    // }}}
    // {{{ public function unset_session()

    /**
     * 删除session
     *
     * @param {string} $key session键
     *
     * @return {void}
     */
    public function unset_session($key)
    {
        $_key = SESSION_PREFIX . $key; 

        if (isset($_SESSION[$_key])) {
            unset($_SESSION[$_key]);
        }
    }

    // }}}
    // {{{ public function get_qrcode_url()

    /**
     * 获取用来认证的二维码,写入二维码信息到session，防止重复生成；写入scene_id,session_id，module信息到待扫描队列
     *
     * @param {string} $module 模块名，比如login用来做登录认证 settings 用来做修改账户信息时认证 
     * @param {number} $timeout 有效期 单位秒 默认300秒 
     * @param {boolean} $force 强制生成新的二维码 默认false
     * 
     * @return {string} 二维码url
     */
    public function get_qrcode_url($module, $timeout=300, $force=false)
    {
        $this->connect(); 
        
        //检查是否已经有没过期的qrcode,有则用没过期的，没有则生成全新的
        //session里面存了qrcode，数据库里有对应没过期的
            
        $session_key_qrcode = 'scene_id' . $module . 'qrcode';
        $session_key_ticket = 'scene_id' . $module . 'ticket';
        $client_id = session_id();
                
        //清理过期
        self::$__db->exec('delete from ' . $this->table_name('auth_queue') . ' where UNIX_TIMESTAMP() - create_time > ttl');

        $use_old = false;
        
        if (false === $force) {
            $session_qrcode = $this->session($session_key_qrcode);
            $session_ticket = $this->session($session_key_ticket);
            if ($session_qrcode && $session_ticket) {
                //检查数据库中有没有对应值,先删除过期值
                $smt = self::$__db->prepare('select * from ' . $this->table_name('auth_queue') . ' where client_id=? AND module=? AND scene_id=?');
                $smt->execute(array($client_id, $module, $session_qrcode));
                
                $res = $smt->fetch(PDO::FETCH_ASSOC);
                if (!empty($res)) {
                    $use_old = true;
                }
            }
        }

        if ($use_old) {
            return weixin_message_service::get_qrcode_url($session_ticket);
        } else {
            //清理旧的
            $smt = self::$__db->prepare('delete from ' . $this->table_name('auth_queue') . ' where client_id=? AND module=?');
            $smt->execute(array($client_id, $module));

            self::$__db->beginTransaction();

            try {
                $smt = self::$__db->prepare('insert into ' . $this->table_name('auth_queue') . '(client_id, module, create_time, ttl) values(?, ?, unix_timestamp(), ?)');
                $smt->execute(array($client_id, $module, $timeout));

                $queue_id = self::$__db->lastInsertId(); 
                $scene_id = $queue_id % 100000 + 1;

                self::$__db->exec('update ' . $this->table_name('auth_queue') . ' set scene_id=' . $scene_id . ' where queue_id=' . $queue_id);

                //产生新的二维码 
                $ticket = $this->__weixin_service->create_qrcode_ticket($scene_id, true, 300);

                $this->session($session_key_qrcode, $scene_id);
                $this->session($session_key_ticket, $ticket);

                self::$__db->commit();
                return weixin_message_service::get_qrcode_url($ticket);
            } catch (exception $e) {
                self::$__db->rollback();
                throw $e;
            }
        }
    }

    // }}}
    // {{{ public function somebody_scanned()
    
    /**
     * 微信用户扫描后，执行该方法更新队列信息
     *
     * @param {string} $open_id  用户id
     * @param {string} $scene_id 场景id
     * 
     */
    public function somebody_scanned($open_id, $scene_id)
    {
        $this->connect(); 
                
        $num = self::$__db->exec('update ' . $this->table_name('auth_queue') . ' set open_id="' . self::$__db->quote($open_id) . '" where open_id <> \'\' and unix_timestamp()-create_time <=ttl and scene_id=' . self::$__db->quote($scene_id));
        if (!$num) {
            throw new exception('二维码已过期。');
        }
    }

    // }}}
    // {{{ public function check_somebody_scanned()
    
    /**
     * 检查用户是否扫描过
     *
     * @param {string} $module 模块 
     * 
     */
    public function check_somebody_scanned($module)
    {
        $this->connect(); 
        
        $session_key_qrcode = 'scene_id' . $module . 'qrcode';
        $session_key_ticket = 'scene_id' . $module . 'ticket';
        $client_id = session_id();
            
        $session_qrcode = $this->session($session_key_qrcode);
        $session_ticket = $this->session($session_key_ticket);
                
        if (!$session_ticket || !$session_qrcode) {
            throw new exception('没有可以扫描的二维码。');
        }

        $smt = self::$__db->prepare('select * from ' . $this->table_name('auth_queue') . ' where UNIX_TIMESTAMP() - create_time <= ttl and client_id=? and module=? and scene_id=?');
        $smt->execute(array($client_id, $module, $session_qrcode));
        $res = $smt->fetch(PDO::FETCH_ASSOC);
        if (empty($res)) {
            throw new exception('二维码已经过期。');
        }

        $open_id = $res['open_id'];
        if (!$open_id) {
            return false;
        } else {
            self::$__db->exec('delete frome ' . $this->table_name('auth_queue') . ' where queue_id=' . $res['queue_id']);
            $this->unset_session($session_key_ticket);
            $this->unset_session($session_key_qrcode);
            return $open_id;
        }

    }

    // }}}
       
    // }}} functions end
}
