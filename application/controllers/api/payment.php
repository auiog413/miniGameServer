<?php

/* 
 * 支付相关接口，包括查询，支付通知回调
 */

class Payment extends MY_Controller {
        
        /**
         * 成功后返回给 AnySDK 的信息
         * @var type 
         */
        private $_returnSuccess = 'ok';
        
        /**
         * 失败后返回给 AnySDK 的信息
         * @var type 
         */
        private $_returnFailure = 'failed';
        
        public function __construct() {
                parent::__construct();
                
                $this->load->model('pay_notify_mdl');
        }
        
        /**
         * 接收支付结果通知，供AnySDK回调
         * 地址 api/payment/callback
         */
        public function callback () {
                // AnySDK 分配的 private_key
                $privateKey = settings(ANYSDK_PAY_KEY);
                
                $params = $_POST;
                foreach ($params as $key => $value) {
                        $params[$key] = $value;
                }
                
                if (!$this->_checkSign($params, $privateKey)) {
                        echo $this->_returnFailure . '_check_sign';
                        return;
                }
                
                // AnySDK 分配的 增强密钥
                $enhancedKey = settings(ANYSDK_ENHANCED_KEY);
                if (!$this->_checkEnhancedSign($params, $enhancedKey)) {
                        echo $this->_returnFailure . '_check_enhanced_sign';
                        return;
                }
                
                // todo: 在这里加入其他处理逻辑
                
                // 删除除下列字段的其他字段， 下列字段是 2014年7月31日，AnySDK 文档中所支持的字段
                // 以防游服部署了老的版本，然后AnySDK有新加字段之后导致游服处理出错
                $params_list = array(
                        'order_id',
                        'product_count',
                        'amount',
                        'pay_status',
                        'pay_time',
                        'user_id',
                        'order_type',
                        'game_user_id',
                        'game_id',
                        'server_id',
                        'product_name',
                        'product_id',
                        'private_data',
                        'channel_number',
                        'channel_order_id',
                        'sign',
                        'source'
                );
                
                foreach ($params as $key => $value) {
                        if (!in_array($key, $params_list)) {
                                unset($params[$key]);
                        }
                }
                
                // 是否通知成功过
                $feedback_order = false;
                // 写入数据库是否成功
                $order_exists = $this->pay_notify_mdl->getSuccessfulNotifyByOrderId($params['order_id']);
                if ($order_exists) {
                        $feedback_order = true;
                } else {
                        // 将记录写入数据库
                        $insert = $params;
                        // 失败订单直接把process_status设置成1
                        $insert['process_status'] = ($params['pay_status'] == 1) ? 0: 1;
                        $insert['time'] = time();
                        $newid = $this->pay_notify_mdl->add($insert);
                        if ($newid) {
                                $feedback_order = true;
                        }
                }
                
                // 先记日志后判断该返回的状态
                if ($feedback_order) {
                        echo $this->_returnSuccess;
                } else {
                        echo $this->_returnFailure . '_write_db';
                }
                
                $this->kp_counter('p');
        }
        
        /**
         * 查询订单状态，单机游戏调用
         * 接口地址：api/payment/check_order
         * 
         */
        public function check_order () {
                
                // 验证 app_key 和 app_secret
                $app_key = settings('app_key');
                $app_secret = settings('app_secret');
                
                $order_id = trim($this->input->get_post('order_id'));
                $time = trim($this->input->get_post('time'));
                $sign = trim($this->input->get_post('sign'));
                $ver  = trim($this->input->get_post('ver'));
                if (empty($ver)) {
                        $ver = 0;
                }

                $submit_app_key = trim($this->input->get_post('app_key'));

                if (empty($order_id)) {
                        echo json_encode(array('errno' => '101', 'errmsg' => 'order_id不能为空'));
                        return;
                }
                
                /**
                 * 若有填写app_key则需要验证签名
                 */
                if ($app_key) {
                        
                        if (empty($sign)) {
                                echo json_encode(array('errno' => '103', 'errmsg' => '缺少签名sign'));
                                return;
                        }
                        
                        if ($submit_app_key != $app_key) {
                                echo json_encode(array('errno' => '104', 'errmsg' => 'app_key无效'));
                                return;
                        }
                        
                        // 验证签名
                        $sign_local = md5($app_key.$order_id.$time);
                        
                        if ($sign_local != $sign) {
                                echo json_encode(array('errno' => '105', 'errmsg' => '签名sign无效'));
                                return;
                        }
                }
                
                $order = $this->pay_notify_mdl->getSuccessfulNotifyByOrderId($order_id);
                
                if (empty($order)) {
                        echo json_encode(array('errno' => '100', 'errmsg' => ' 订单不存在'));
                } else {
                        unset($order['id']);
                        unset($order['sign']);
                        unset($order['time']);
                        
                        // 生成订单信息签名
                        if ($ver >= 1) {
                                $order_sign = $this->order_sign($order, $app_secret);
                                echo json_encode(array('errno' => '0', 'errmsg' => '查询成功', 'sign' => strtolower($order_sign), 'data' => $order));
                        } else {
                                echo json_encode(array('errno' => '0', 'errmsg' => '查询成功', 'data' => $order));
                        }
                }
        }
        
        /**
         * 验签
         * @param array $data 接收到的所有请求参数数组，通过$_POST可以获得。注意data数据如果服务器没有自动解析，请做一次urldecode(参考rfc1738标准)处理
         * @param array $privateKey AnySDK分配的游戏privateKey
         * @return bool
         */
        private function _checkSign($data, $privateKey) {
                if (empty($data) || !isset($data['sign']) || empty($privateKey)) {
                        return false;
                }
                $sign = $data['sign'];
                $_sign = $this->_getSign($data, $privateKey);
                if ($_sign != $sign) {
                        return false;
                }
                return true;
        }

        /**
         * 计算签名
         * @param array $data
         * @param string $privateKey
         * @return string
         */
        private function _getSign($data, $privateKey) {
                //sign 不参与签名
                unset($data['sign']);
                //数组按key升序排序
                ksort($data);
                //将数组中的值不加任何分隔符合并成字符串
                $string = implode('', $data);
                //做一次md5并转换成小写，末尾追加游戏的privateKey，最后再次做md5并转换成小写
                return strtolower(md5(strtolower(md5($string)) . $privateKey));
        }
        
        /**
         * 对返回给客户端的订单数据做签名
         * @param type $order
         * @param type $app_secret
         */
        private function order_sign($order, $app_secret) {
                ksort($order);
                
                $sign_str = '';
                foreach ($order as $value) {
                        $sign_str .= $value;
                }
                
                $sign = md5($sign_str . $app_secret);
                
                return $sign;
        }
        
        /**
         * 验证AnySDK支付通知结果的增强签名
         * 
         * @param type $data
         * @param type $enhancedKey
         * @return boolean
         */
        private function _checkEnhancedSign ($data, $enhancedKey) {
                if (empty($data) || !isset($data['enhanced_sign']) || empty($enhancedKey)) {
                        return false;
                }
                $enhancedSign = $data['enhanced_sign'];
                //sign及enhanced_sign 不参与签名
                unset($data['enhanced_sign']);
                $_enhancedSign = $this->_getSign($data, $enhancedKey);
                if ($_enhancedSign != $enhancedSign) {
                        return false;
                }
                return true;
        }
        
        /**
         * 异步通知游戏服务端
         */
        public function notify_game () {
                $start = time();
                $this->_log_message('start cron; tl='.$start);
                while(true){
                        $notify_field = array(
                                'order_id',
                                'product_count',
                                'amount',
                                'pay_status',
                                'pay_time',
                                'user_id',
                                'order_type',
                                'game_user_id',
                                'game_id',
                                'server_id',
                                'product_name',
                                'product_id',
                                'private_data',
                                'channel_number'
                        );

                        $orders = $this->pay_notify_mdl->get_order_can_process();

                        $orders_count = count($orders);
                        
                        $this->_log_message('has ' . $orders_count . ' orders to process; tl='.$start);
                        
                        foreach ($orders as $order) {

                                $this->_log_message('processing ' . $order['order_id'] . '; tl='.$start);

                                $post = $order;
                                foreach ($post as $key => $value) {
                                        if (!in_array($key, $notify_field)) {
                                                unset($post[$key]);
                                        }
                                }
                                $post['oper_id'] = '1';
                                $post['sign'] = $this->_notify_game_sign($post);
                                $ret = $this->_http_post($post);
                                if (substr($ret, 0, 2) === 'ok') {
                                // 游戏返回ok类响应（包括ok和ok.xxx类型的响应）或者通知次数大于10次的
                                // if (substr($ret, 0, 2) === 'ok' || $order['notify_times'] > 10) {
                                        $status = 1;
                                } else {
                                        $status = 0;
                                }

                                $this->_log_message('finish process ' . $order['order_id'] . ($order['notify_times'] + 1) . ' times by received ' . $ret . '; tl='.$start);

                                $this->pay_notify_mdl->update_process_status_by_id($order['id'], $status, $order['notify_times']+1, $ret);
                        }
                        
                        if (time() - $start < 285) {
                                $this->_log_message('sleep 3 seconds; tl='.$start);
                                sleep(3);
                        } else {
                                $this->_log_message('stop cron; tl='.$start);
                                break;
                        }
                }
        }
        
        /**
         * 记录计划任务（异步通知游戏服务端）日志
         * 
         * @param type $msg
         * @param type $newline
         */
        private function _log_message ($msg, $newline = false) {
                $file = 'E:\\cron_log\\' . date('Ymd') . '.log';
                $data = date('Y-m-d H:i:s ') . end(explode('.', microtime(true))) . '  ' . $msg . "\n";
                if ($newline) {
                        $data .= "\n";
                }
                file_put_contents($file, $data, FILE_APPEND);
        }
        
        /**
         * 发送http post请求
         * 
         * @param type $postfields
         * @return type
         */
        private function _http_post ($postfields) {
                $url = 'http://10.208.216.24:20009';
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($postfields) ? json_encode($postfields): $postfields);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_USERAGENT, 'happyattack');
                $output = curl_exec($ch);
                $this->_log_message(var_export(curl_error($ch), true), true);
                curl_close($ch);

                return $output;
        }
        
        /**
         * 生成与游戏服务端通信的消息签名
         * 
         * @param type $notify
         * @return type
         */
        private function _notify_game_sign ($notify) {
                // 读取安装miniGameServer的时候生成的app_secret参数
                $secret = settings('app_secret');
                
                ksort($notify);
                
                $sign_str = implode($notify);
                
                $sign = md5($sign_str . $secret);
                
                return $sign;
        }
}
