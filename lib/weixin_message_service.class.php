<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker: */

/**
 * 微信消息服务 
 *
 * @author  gurongsheng@gmail.com 
 */

// {{{ class weixin_message_service

/**
 * 微信开放平台接口封装,不依赖appid的接口都可以静态调用
 *
 * @author  gurongsheng@gmail.com 
 */
class weixin_message_service
{
    // {{{ consts start

    /**
     * 文本消息
     */
    const MSG_TYPE_TEXT = 1;
    
    /**
     * 图片消息
     */
    const MSG_TYPE_IMAGE = 2;
    
    /**
     * 语音消息
     */
    const MSG_TYPE_VOICE = 3;
    
    /**
     * 视频消息
     */
    const MSG_TYPE_VIDEO = 4;
    
    /**
     * 地理位置消息
     */
    const MSG_TYPE_LOCATION = 5;
    
    /**
     * 链接消息
     */
    const MSG_TYPE_LINK = 6;
    
    /**
     * 音乐消息
     */
    const MSG_TYPE_MUSIC = 7;
    
    /**
     * 图文消息
     */
    const MSG_TYPE_NEWS = 8;
    
    /**
     * 事件消息
     */
    const MSG_TYPE_EVENT = 11;
    
    /**
     * 订阅事件
     */
    const EVENT_TYPE_SUB = 101;
    
    /**
     * 取消订阅事件
     */
    const EVENT_TYPE_UNSUB = 102;
    
    /**
     * 扫描带参数二维码订阅事件
     */
    const EVENT_TYPE_SCAN_SUB = 103;
    
    /**
     * 扫描带参数二维码已经订阅事件
     */
    const EVENT_TYPE_SCAN = 104;
    
    /**
     * 上报地理位置事件
     */
    const EVENT_TYPE_LOCATION = 105;
    
    /**
     * 自定义菜单事件
     */
    const EVENT_TYPE_CLICK = 106;
    
    /**
     * 图片媒体类型 
     */
    const MEDIA_TYPE_IMAGE = 201;
    
    /**
     * 语音媒体类型 
     */
    const MEDIA_TYPE_VOICE = 202;
    
    /**
     * 视频媒体类型 
     */
    const MEDIA_TYPE_VIDEO = 203;
    
    /**
     * 缩略图媒体类型 
     */
    const MEDIA_TYPE_THUMB = 204;

    // }}} consts end
    // {{{ members start

    /**
     * 消息类型映射
     */
    private static $__msg_type_map = array(
        'text'     => self::MSG_TYPE_TEXT,
        'image'    => self::MSG_TYPE_IMAGE,
        'voice'    => self::MSG_TYPE_VOICE,
        'video'    => self::MSG_TYPE_VIDEO,
        'location' => self::MSG_TYPE_LOCATION,
        'link'     => self::MSG_TYPE_LINK,
        'music'    => self::MSG_TYPE_MUSIC,
        'news'     => self::MSG_TYPE_NEWS,
        'event'    => self::MSG_TYPE_EVENT,
    );

    /**
     * 事件类型映射
     */
    private static $__event_type_map = array(
        'x_scan_subscribe'    => self::EVENT_TYPE_SCAN_SUB,
        'subscribe'    => self::EVENT_TYPE_SUB,
        'unsubscribe'  => self::EVENT_TYPE_UNSUB,
        'scan'         => self::EVENT_TYPE_SCAN,
        'location'     => self::EVENT_TYPE_LOCATION,
        'click'        => self::EVENT_TYPE_CLICK,
    );

    /**
     * 媒体类型映射
     */
    private static $__media_type_map = array(
        'image'        => self::MEDIA_TYPE_IMAGE,
        'voice'        => self::MEDIA_TYPE_VOICE,
        'video'        => self::MEDIA_TYPE_VIDEO,
        'thumb'        => self::MEDIA_TYPE_THUMB,
    );

    /**
     * 微信开放平台appid
     */
    private $__appid = null;
    
    /**
     * 微信开放平台secret
     */
    private $__secret = null;

    /**
     * 微信开放平台中的access token
     */
    private static $__access_token = null;

    /**
     * 微信开放平台中的二维码ticket
     */
    private static $__qrcode_ticket = null;
    

    // }}} members end
    // {{{ functions start
    
    // {{{ public function __construct()

    /**
     * 构造函数
     *
     * @param {string} $appid 微信开发平台appid
     * @param {string} $secret 微信开发平台secret
     *
     * @return void
     */
    public function __construct($appid=null, $secret=null)
    {
        $this->set_app_account($appid, $secret);
    }

    // }}}
    // {{{ public function set_app_account()

    /**
     * 设置app账户信息
     *
     * @param {string} $appid 微信开发平台appid
     * @param {string} $secret 微信开发平台secret
     *
     * @return void
     */
    public function set_app_account($appid=null, $secret=null)
    {
        $this->__appid = $appid;
        $this->__secret = $secret;
    }

    // }}}
    // {{{ public static function check_message_valid()
    /**
     * 检查消息是否是微信发出 
     *
     * @param {string} $signature  消息签名
     * @param {integer} $timestamp 时间戳
     * @param {integer} $nonce     随机数
     *
     * @return {boolean}
     */
    public static function check_message_valid($signature, $timestamp, $nonce, $__weixin_token)
    {
        $params = array($__weixin_token, $timestamp, $nonce);
        sort($params);
        $sign = sha1(implode($params));
        if ($sign !== $signature) {
            return false; 
        } else {
            return true; 
        }
    }

    // }}}
    // {{{ public static function parse_message()

    /**
     * 解析消息
     *
     * @param {string} $message xml消息体
     *
     * @return {array}
     */
    public static function parse_message($message)
    {
        $doc = DOMDocument::loadXML($message);

        $node_list = $doc->documentElement->childNodes;

        $msg = array();

        foreach ($node_list as $elm) {
            $k = strtolower($elm->nodeName);
            $msg[$k] = $k === 'event' ? strtolower($elm->textContent) : $elm->textContent;
        }
        
        if (empty($msg['msgtype']) 
                || !isset(self::$__msg_type_map[$msg['msgtype']])) {
            throw new exception('未知消息类型。');
        }

        $ret = array('msg_type' => self::$__msg_type_map[$msg['msgtype']]);
        $msg_keys_maybe = array();

        switch (self::$__msg_type_map[$msg['msgtype']]) {
            case self::MSG_TYPE_TEXT:
                $msg_keys = array(
                    'tousername' => 'to_user_name',        
                    'fromusername' => 'from_user_name',        
                    'createtime' => 'create_time',        
                    'content' => 'content',        
                    'msgid' => 'msg_id',        
                );

                break;
            case self::MSG_TYPE_IMAGE:
                $msg_keys = array(
                    'tousername' => 'to_user_name',        
                    'fromusername' => 'from_user_name',        
                    'createtime' => 'create_time',        
                    'picurl' => 'pic_url',        
                    'mediaid' => 'media_id',        
                    'msgid' => 'msg_id',        
                );

                break;
            case self::MSG_TYPE_VOICE:
                $msg_keys = array(
                    'tousername' => 'to_user_name',        
                    'fromusername' => 'from_user_name',        
                    'createtime' => 'create_time',        
                    'mediaid' => 'media_id',        
                    'format' => 'format',        
                    'msgid' => 'msg_id',        
                );

                $msg_keys_maybe = array(
                    'recognition' => 'recognition',        
                );

                break;
            case self::MSG_TYPE_VIDEO:
                $msg_keys = array(
                    'tousername' => 'to_user_name',        
                    'fromusername' => 'from_user_name',        
                    'createtime' => 'create_time',        
                    'mediaid' => 'media_id',        
                    'thumbmediaid' => 'thumb_media_id',        
                    'msgid' => 'msg_id',        
                );

                break;
            case self::MSG_TYPE_LOCATION:
                $msg_keys = array(
                    'tousername' => 'to_user_name',        
                    'fromusername' => 'from_user_name',        
                    'createtime' => 'create_time',        
                    'location_x' => 'location_x',        
                    'location_y' => 'location_y',        
                    'scale' => 'scale',        
                    'label' => 'label',        
                    'msgid' => 'msg_id',        
                );

                break;
            case self::MSG_TYPE_LINK:
                $msg_keys = array(
                    'tousername' => 'to_user_name',        
                    'fromusername' => 'from_user_name',        
                    'createtime' => 'create_time',        
                    'title' => 'title',        
                    'description' => 'description',        
                    'url' => 'url',        
                    'msgid' => 'msg_id',        
                );

                break;
            case self::MSG_TYPE_EVENT:
                $msg_keys = array(
                    'tousername' => 'to_user_name',        
                    'fromusername' => 'from_user_name',        
                    'createtime' => 'create_time',        
                    'msgid' => 'msg_id',        
                );

                if (empty($msg['event'])) {
                    throw new exception('未知的事件类型。');
                }

                $msg['msgid'] = $msg['fromusername'] . $msg['createtime'];

                //逻辑上增加的类型
                if (!empty($msg['eventkey']) && self::$__event_type_map[$msg['event']] === self::EVENT_TYPE_SUB) {
                    $msg['event'] = 'x_scan_subscribe'; 
                }
                
                if (!isset(self::$__event_type_map[$msg['event']])) {
                    throw new exception('未支持的事件类型。');
                }

                $ret['event'] = self::$__event_type_map[$msg['event']]; 

                switch(self::$__event_type_map[$msg['event']]) {
                    case self::EVENT_TYPE_SUB://此处有意省略break
                    case self::EVENT_TYPE_UNSUB:
                        break;
                    case self::EVENT_TYPE_SCAN_SUB:
                    case self::EVENT_TYPE_SCAN:
                        $msg_keys['eventkey'] = 'event_key';
                        $msg_keys['ticket'] = 'ticket';
                        break;
                    case self::EVENT_TYPE_LOCATION:
                        $msg_keys['latitude'] = 'latitude';
                        $msg_keys['longitude'] = 'longitude';
                        $msg_keys['precision'] = 'precision';
                        break;
                    case self::EVENT_TYPE_CLICK:
                        $msg_keys['eventkey'] = 'event_key';
                        break;
                    default:
                        throw new exception('未支持的事件类型。');
                }
                break;
            default:
                throw new exception('未支持的消息类型。');
        }
        
        foreach ($msg_keys as $k => $alias) {
            if (!isset($msg[$k])) {
                throw new exception('消息内容不完整。');
            }

            $ret[$alias] = $msg[$k];
        }
        
        foreach ($msg_keys_maybe as $k => $alias) {
            if (!isset($msg[$k])) {
                continue;
            }
            $ret[$alias] = $msg[$k];
        }


        return $ret;
    }

    // }}}
    // {{{ public static function convert_message()

    /**
     * 加工xml消息
     *
     * @param {array} $msg 消息数组
     *
     * @return {string}
     */
    public static function convert_message(array $msg) 
    {
        $doc = DOMDocument::loadXML('<xml></xml>'); 
        $doc->formatOutput = true;

        $node = $doc->documentElement;
        foreach ($msg as $k => $v) {
            $elm = $doc->createElement($k);
            if (!is_numeric($v)) {
                $child = $doc->createCDATASection($v);
            } else {
                $child = $doc->createTextNode($v); 
            }

            $elm->appendChild($child);
            
            $node->appendChild($elm);
        }

        return $doc->saveXML($node);
    }

    // }}}
    // {{{ private function __parse_json_result()

    /**
     * @解析服务器返回的json结果
     *
     * @param {string} $res 服务器返回结果字符串
     *
     * @return {array}
     */
    private function __parse_json_result($res)
    {
        $res = json_decode($res, true);

        if (!empty($res['errcode'])) {
            throw new exception($res['errmsg'], $res['errcode']);
        }

        return $res;
    }

    // }}}
    // {{{ public function get_access_token()
    
    /**
     * 获取access token
     *
     * @param {boolean} is_force 是否强制获取 默认false
     *
     * @return {string}
     */
    public function get_access_token($is_force=false)
    {
        if (self::$__access_token === null || $is_force) {
            $url = sprintf('https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=%s&secret=%s', $this->__appid, $this->__secret);

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HEADER, 0);

            $res = curl_exec($ch);

            if(curl_errno($ch)) {
                throw new exception(curl_error($ch), curl_errno($ch));
            }

            $res = $this->__parse_json_result($res); 

            if (!empty($res['access_token'])) {
                self::$__access_token = $res['access_token'];
            } else {
                throw new exception('获取access_token失败。');
            }
        }
    }

    // }}}
    // {{{ public function upload_media()

    /**
     * 上传多媒体文件
     *
     * @param {string} $type  媒体类型
     * @param {string} $media 媒体文件路径
     */
    public function upload_media($type, $media)
    {
        $map = array_flip(self::$__media_type_map);
        if (!isset($map[$type])) {
            throw new exception('未知媒体类型。');
        }
        
        if (!file_exists($media)) {
            throw new exception('媒体文件不存在:' . $media);
        }

        $this->get_access_token();

        $url = sprintf('http://file.api.weixin.qq.com/cgi-bin/media/upload?access_token=%s&type=%s', self::$__access_token, $map[$type]);

        $ch = curl_init();

        $data = array('file' => '@' . $media);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        $res = curl_exec($ch);

        if(curl_errno($ch)) {
            throw new exception(curl_error($ch), curl_errno($ch));
        }
            
        try {
            $res = $this->__parse_json_result($res); 
        } catch (exception $e) {
            if ($e->getCode() == '42001') {
                $this->get_access_token(true);
                return $this->upload_media($type, $media);
            } 
            throw $e;
        }

        //转换type为本地类型
        if (!empty($res['type'])) {
            if (isset(self::$__media_type_map[$res['type']])) {
                $res['type'] = self::$__media_type_map[$res['type']];
            }
        }

        return $res;
    }

    // }}}
    // {{{ public function download_media()

    /**
     * 下载媒体文件
     *
     * @param {string} $media_id 媒体id
     *
     * @return {string}
     */
    public function download_media($media_id)
    {
        $this->get_access_token();

        $url = sprintf('http://file.api.weixin.qq.com/cgi-bin/media/get?access_token=%s&media_id=%s', self::$__access_token, $media_id); 
            
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);

        $res = curl_exec($ch);

        if(curl_errno($ch)) {
            throw new exception(curl_error($ch), curl_errno($ch));
        }

        $status  = curl_getinfo($ch);

        $header = substr($res, 0, $status['header_size']);

        $body = substr($res, $status['header_size']);

        $find_download = false;
        $tmp = strtok($header, "\r\n");
        while ($tmp !== false) {
            $tmp = strtok("\r\n");
            if ('' === trim($tmp)) {
                continue;
            }

            $pos = strpos($tmp, ':');
            if (false !== $pos) {
                $hk =  substr($tmp, 0, $pos);
                $hv = substr($tmp, $pos+1);

                if (strtolower($hk) === 'content-disposition' 
                        && false !== strpos($hv, 'attachment')) {
                    $find_download = true;
                    break;
                }
            }
        }
            
        if ($find_download) {
            return $body;
        } else {
            try {
                $res = $this->__parse_json_result($body); 
            } catch (exception $e) {
                if ($e->getCode() == '42001') {
                    $this->get_access_token(true);
                    return $this->download_media($media_id);
                } 
                throw $e;
            }
            throw new exception('下载媒体文件失败。');
        }

    }

    // }}}
    // {{{ public function send_custom_message()
    /**
     * 发送客户消息给用户
     *
     * @param {string}  $user     用户的open id
     * @param {integer} $msg_type 消息类型
     * @param {array}   $package  消息体结构
     */
    public function send_custom_message($user, $msg_type, $package)
    {
        $map = array_flip(self::$__msg_type_map);
        
        if (!isset($map[$msg_type])) {
            throw new exception('消息类型不支持。');
        }

        $this->get_access_token();

        $url = sprintf('https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=%s', self::$__access_token);

        $msg = array(
            'touser' => $user,
            'msgtype' => $map[$msg_type],
            $map[$msg_type] => $package,
        );
        
        $ch = curl_init();
        //$msg = json_encode($msg);
        $msg = preg_replace('/\\\u([0-9a-f]{4})/ie', "iconv('UCS-2BE', 'UTF-8', pack('H4', '\\1'))", json_encode($msg));

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        //curl_setopt($ch, CURLOPT_VERBOSE, 1);
        //curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json; charset=UTF-8',));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $msg);
        $res = curl_exec($ch);

        if(curl_errno($ch)) {
            throw new exception(curl_error($ch), curl_errno($ch));
        }

        try {
            $res = $this->__parse_json_result($res); 
        } catch (exception $e) {
            if ($e->getCode() == '42001') {
                $this->get_access_token(true);
                return $this->send_custom_message($user, $msg_type, $package);
            } 
            throw $e;
        }

        if (!isset($res['errcode']) || $res['errcode'] != 0) {
            throw new exception('发送客服消息失败。');
        }

        return true;
    }

    // }}}
    // {{{ public function send_custom_text_message()
    /**
     * 发送客户文本消息给用户
     *
     * @param {string}  $user  用户的open id
     * @param {string}  $text  消息内容
     */
    public function send_custom_text_message($user, $text)
    {
        $package = array (
            'content' => $text,         
                
        );
        return $this->send_custom_message($user, self::MSG_TYPE_TEXT, $package);
    }

    // }}}
    // {{{ public function send_custom_image_message()
    /**
     * 发送客户图片消息给用户
     *
     * @param {string}  $user  用户的open id
     * @param {string}  $media 图片内容路径
     */
    public function send_custom_image_message($user, $media)
    {
        $res = $this->upload_media(self::MEDIA_TYPE_IMAGE, $media);
        $media_id = $res['media_id'];

        $package = array (
            'media_id' => $media_id,         
        );
        return $this->send_custom_message($user, self::MSG_TYPE_IMAGE, $package);
    }

    // }}}
    // {{{ public function send_custom_voice_message()
    /**
     * 发送客户语音消息给用户
     *
     * @param {string}  $user  用户的open id
     * @param {string}  $media 语音内容路径
     */
    public function send_custom_voice_message($user, $media)
    {
        $res = $this->upload_media(self::MEDIA_TYPE_VOICE, $media);
        $media_id = $res['media_id'];

        $package = array (
            'media_id' => $media_id,         
        );
        return $this->send_custom_message($user, self::MSG_TYPE_VOICE, $package);
    }

    // }}}
    // {{{ public function send_custom_video_message()
    /**
     * 发送客户视频消息给用户
     *
     * @param {string}  $user  用户的open id
     * @param {string}  $media 视频内容路径
     * @param {string}  $title 视频标题 默认空 
     * @param {string}  $description 视频描述 默认空 
     */
    public function send_custom_video_message($user, $media, $title=null, $description=null)
    {
        $res = $this->upload_media(self::MEDIA_TYPE_VIDEO, $media);
        $media_id = $res['media_id'];

        $package = array (
            'media_id' => $media_id,         
        );
        if (isset($title)) {
            $package['title'] = $title;
        }
        if (isset($description)) {
            $package['description'] = $description;
        }
        return $this->send_custom_message($user, self::MSG_TYPE_VIDEO, $package);
    }

    // }}}
    // {{{ public function send_custom_music_message()
    /**
     * 发送客户音乐消息给用户
     *
     * @param {string}  $user  用户的open id
     * @param {string}  $musicurl 音乐路径 
     * @param {string}  $hqmusicurl 高品质音乐链接，wifi环境优先使用该链接播放音乐 
     * @param {string}  $media 音乐缩略图路径
     * @param {string}  $title 视频标题 默认空 
     * @param {string}  $description 视频描述 默认空 
     */
    public function send_custom_music_message($user, $musicurl, $hqmusicurl, $media, $title=null, $description=null)
    {
        $res = $this->upload_media(self::MEDIA_TYPE_THUMB, $media);
        $media_id = $res['media_id'];

        $package = array (
            'musicurl' => $musicurl,
            'hqmusicurl' => $hqmusicurl,
            'thumb_media_id' => $media_id, 
        );
        if (isset($title)) {
            $package['title'] = $title;
        }
        if (isset($description)) {
            $package['description'] = $description;
        }
        return $this->send_custom_message($user, self::MSG_TYPE_MUSIC, $package);
    }

    // }}}
    // {{{ public function send_custom_news_message()
    /**
     * 发送客户图文消息给用户
     *
     * @param {string}  $user  用户的open id
     * @param {array}  $articles 图文内容数组 [{title, description, url, picurl}],最长10条
     */
    public function send_custom_news_message($user, $articles)
    {
        $package = array (
            'articles' => $articles, 
        );
        return $this->send_custom_message($user, self::MSG_TYPE_NEWS, $package);
    }

    // }}}
    // {{{ public function create_qrcode_ticket()
    /**
     * 创建二维码ticket
     *
     * @param {int}   $scene_id        场景值ID，临时二维码时为32位非0整型，永久二维码时最大值为100000（目前参数只支持1--100000）
     * @param {bool}  $action_type     默认生成二维码类型为临时，true为永久 
     * @param {int}   $expire_seconds  设置临时二维码的有效时间，最大1800秒
     */
    public function create_qrcode_ticket($scene_id, $action_type = false, $expire_seconds = 1800)
    {
        //接收$action_type参数判断生成二维码的类型是永久或者临时
        $action_name = $action_type ? 'QR_LIMIT_SCENE' : 'QR_SCENE';

        //判断接收的秒数是否为整形且大于1800秒，返回最大为1800的整形数字
        $expire_seconds = $expire_seconds >= 1800 ? 1800 : $expire_seconds;

        $this->get_access_token();

        $url = 'https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=' . self::$__access_token;

        //根据二维码类型生成不同的参数
        if ($action_type) {
            if (($scene_id < 1) || ($scene > 100000)) {
                throw new exception('永久二维码场景ID不合法');
            }
            
            //{"action_name": "QR_LIMIT_SCENE", "action_info": {"scene": {"scene_id": 123} } }
            $msg = array(
                'action_name' => $action_name,
                'action_info' => array('scene' => array('scene_id' => $scene_id))
            );
        } else {

            //{"expire_seconds": 1800, "action_name": "QR_SCENE", "action_info": {"scene": {"scene_id": 123} } }
            $msg = array(
                'expire_seconds' => $expire_seconds,
                'action_name'    => $action_name,
                'action_info'    => array('scene' => array('scene_id' => $scene_id))
            );
        }
        
        $ch = curl_init();
        $msg = json_encode($msg);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json; charset=UTF-8'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $msg);
        $res = curl_exec($ch);
        
        if(curl_errno($ch)) {
            throw new exception(curl_error($ch), curl_errno($ch));
        }

        try {
            $res = $this->__parse_json_result($res); 
        } catch (exception $e) {
            if ($e->getCode() == '42001') {
                $this->get_access_token(true);
                return $this->create_qrcode_ticket($scene_id, $action_type = false, $expire_seconds = 1800);
            } 
            throw $e;
        }

        if (isset($res['errcode']) && ($res['errcode'] != 0)) {
            throw new exception('创建二维码ticket失败。');
        }
        
        self::$__qrcode_ticket = $res;

        return true;
    }

    // }}}
    // {{{ public static function get_qrcode_url()
    /**
     * 通过ticket换取二维码
     *
     */
    public static function get_qrcode_url()
    {

        $url = 'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket==' . rawurlencode( self::$__qrcode_ticket['ticket'] );
            
        return $url;

    }

    // }}}
   // }}}
}

// }}}
