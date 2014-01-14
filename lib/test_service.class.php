<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker: */

/**
 * 微信机器人测试服务 
 *
 * @author  gurongsheng@gmail.com 
 */
 
 /*
 // 可以实现的方法列表

    'on_text_message',
    'on_image_message',
    'on_voice_message',
    'on_video_message',
    'on_location_message',
    'on_link_message',
    'on_subscribe',
    'on_unsubscribe',
    'on_scan_subscribe',
    'on_scan',
    'on_location',
    'on_context_menu',
*/

require_once 'weixin_message_service.class.php';
require_once 'weixin_robot_service.class.php';

// {{{ class test_service

/**
 * 测试服务
 *
 * @author  gurongsheng@gmail.com 
 */
class weather_service
{
    // {{{ members start

    /**
     * @see weixin_message_service
     */
    private $__app = null;

    // }}} members end 
    // {{{ functions 

    // {{{ public function __construct()

    public function __construct()
    {
        $this->__app = new weixin_message_service(WEIXIN_APPID, WEIXIN_SECRET);
    }

    // }}}

    // {{{ public function on_text_message()

    /**
     * 处理文本消息
     *
     * @param {string} $from_user_name
     * @param {string} $content
     * @param {string} $msg_id
     * @param {string} $create_time
     * @param {string} $to_user_name
     */
    public function on_text_message($from_user_name, $content, $msg_id, $create_time, $to_user_name)
    {
        $this->__app->send_custom_text_message($from_user_name, $content);
    }

    // }}}
    // {{{ public function on_location_message()

    /**
     * 处理地理位置消息
     *
     * @param {string} $from_user_name
     * @param {string} $latitude
     * @param {string} $longitude
     * @param {string} $scale
     * @param {string} $label
     * @param {string} $msg_id
     * @param {string} $create_time
     * @param {string} $to_user_name
     */
    public function on_location_message($from_user_name, $latitude, $longitude, $scale, $label, $msg_id, $create_time, $to_user_name)
    {
        $this->__app->send_custom_text_message($from_user_name, sprintf('%s,%s'), $latitude, $longitude);
    }

    // }}}
    // {{{ public function on_voice_message()

    /**
     * 处理文本消息
     *
     * @param {string} $from_user_name
     * @param {string} $media_id
     * @param {string} $format
     * @param {string} $recognition
     * @param {string} $msg_id
     * @param {string} $create_time
     * @param {string} $to_user_name
     */
    public function on_voice_message($from_user_name, $media_id, $format, $recognition, $msg_id, $create_time, $to_user_name)
    {
        if ($recognition !== '') {
            $this->__app->send_custom_text_message($from_user_name, $recognition);
        }
    }

    // }}}
    // {{{ public function on_location()

    /**
     * 获取地理位置事件
     *
     * @param {string} $from_user_name
     * @param {string} $latitude
     * @param {string} $longitude
     * @param {string} $precision
     * @param {string} $msg_id
     * @param {string} $create_time
     * @param {string} $to_user_name
     */
    public function on_location($from_user_name, $latitude, $longitude, $precision, $msg_id, $create_time, $to_user_name)
    {
        $this->__app->send_custom_text_message($from_user_name, sprintf('%s,%s'), $latitude, $longitude);
    }

    // }}}
    // {{{ public function on_subscribe()

    /**
     * 获取地理位置事件
     *
     * @param {string} $from_user_name
     * @param {string} $msg_id
     * @param {string} $create_time
     * @param {string} $to_user_name
     */
    public function on_subscribe($from_user_name, $msg_id, $create_time, $to_user_name)
    {
        $this->_send_help($from_user_name);
    }

    // }}}
    // {{{ private function _send_help()

    private function _send_help($from_user_name)
    {
        $this->__app->send_custom_text_message($from_user_name, '请发送语音消息，地理位置，或文字消息。');
    }

    // }}}

    // }}} functions end
}

// }}}

