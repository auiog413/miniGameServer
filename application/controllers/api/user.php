<?php

/* 
 * 用户登录验证相关接口
 */

class User extends MY_Controller {
        
        /**
         * anysdk统一登录地址
         * @var string
         */
        private $_loginCheckUrl = 'http://oauth.anysdk.com/api/User/LoginOauth/';

        public function __construct() {
                parent::__construct();
                
                $login_check_url = settings('anysdk_login_url');
                
                if ($login_check_url) {
                        $this->_loginCheckUrl = $login_check_url;
                }
        }
        
        /**
         * 登录验证转发接口
         */
        public function login () {
                $params = $_REQUEST;
                
                //检测必要参数
                if (!$this->parametersIsset($params)) {
                        echo 'parameter not complete';
                        exit;
                }
                
                //发送http请求
                $this->load->library('http_request');
                //这里建议使用post方式提交请求，避免客户端提交的参数再次被urlencode导致部分渠道token带有特殊符号验证失败
                $result = $this->http_request->post($this->_loginCheckUrl, $params);
                
                //@todo在这里处理游戏逻辑，在服务器注册用户信息等
                $result_arr = json_decode($result, true);
                if ($result_arr['status'] === 'ok') {
                        $this->load->model('usersession_mdl');
                        $this->usersession_mdl->add(array(
                                'uid' => $result_arr['common']['uid'],
                                'channel_num' => $result_arr['common']['channel'],
                                'anysdk_return' => $result,
                                'created_time' =>  time(),
                                'updated_time' => time()
                        ));
                }
                
                //返回示例： {"status":"ok","data":{--渠道服务器返回的信息--},"common":{"channel":"渠道标识","uid":"用户标识"},"ext":""}
                echo $result;
                
                $this->kp_counter('u');
        }
        
        public function check_user_login () {
                
                // 验证 app_key 和 app_secret
                $app_key = settings('app_key');
                $app_secret = settings('app_secret');
                
                $detail = trim($this->input->get_post('detail'));
                $ret_detail = ($detail === 'true') ? true: false;
                
                $submit_app_key = trim($this->input->get_post('app_key'));
                $uid = trim($this->input->get_post('uid'));
                $channel = trim($this->input->get_post('channel_num'));
                $sign = trim($this->input->get_post('sign'));
                
                if (empty($uid)) {
                        if ($ret_detail) {
                                echo json_encode(array('errno' => '101', 'errmsg' => '缺少uid'));
                        } else {
                                echo 'no-101';
                        }
                        return;
                }
                
                if (empty($channel)) {
                        if ($ret_detail) {
                                echo json_encode(array('errno' => '102', 'errmsg' => '缺少channel_num'));
                        } else {
                                echo 'no-102';
                        }
                        return;
                }
                
                if (empty($sign)) {
                        if ($ret_detail) {
                                echo json_encode(array('errno' => '103', 'errmsg' => '缺少签名sign'));
                        } else {
                                echo 'no-103';
                        }
                        return;
                }

                if ($submit_app_key != $app_key) {
                        if ($ret_detail) {
                                echo json_encode(array('errno' => '104', 'errmsg' => 'app_key无效'));
                        } else {
                                echo 'no-104';
                        }
                        return;
                }

                // 验证签名
                $sign_local = md5($app_key . $uid . $channel . $app_secret);

                if ($sign_local != $sign) {
                        if ($ret_detail) {
                                echo json_encode(array('errno' => '105', 'errmsg' => '签名sign无效'));
                        } else {
                                echo 'no-105';
                        }
                        return;
                }
                
                $this->load->model('usersession_mdl');
                $usersession = $this->usersession_mdl->get_by_uid_and_channel_num($uid, $channel);
                if (empty($usersession)) {
                        if ($ret_detail) {
                                echo json_encode(array('errno' => '106', 'errmsg' => '用户未登录'));
                        } else {
                                echo 'no-106';
                        }
                        return;
                }
                
                $this->usersession_mdl->del_by_id($usersession['id']);
                
                if ($ret_detail) {
                        echo json_encode(array('errno' => '0', 'errmsg' => '查询成功', 'data' => json_decode($usersession['anysdk_return'], true)));
                } else {
                        echo 'ok';
                }
        }
        
        /**
         * 检查 channel, uapi_key, uapi_secret 是否存在
         * 
         * @param type $params
         * @return boolean
         */
        private function parametersIsset($params) {
                if (!(isset($params['channel']) && isset($params['uapi_key']) && isset($params['uapi_secret']))) {
                        return false;
                }
                return TRUE;
        }
}