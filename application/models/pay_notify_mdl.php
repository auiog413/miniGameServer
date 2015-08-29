<?php

/* 
 * 通知游服信息
 */
class Pay_Notify_Mdl extends MY_Model {
        
        const TABLE = 'pay_notify';
        
        public function __construct() {
                parent::__construct();
        }
        
        /**
         * 通过order_id 渠道被通知成功的订单信息
         * 
         * 被通知成功不是指订单状态为成功， 而是指订单有明确的成功（1）或者失败（2）状态
         * 
         * @param type $order_id
         */
        public function getSuccessfulNotifyByOrderId($order_id) {
                if (empty($order_id)) {
                        return null;
                }
                
                $sql = 'SELECT * FROM ' . $this->_table_prefix . self::TABLE 
                        . ' WHERE (`pay_status` = 1 OR `pay_status` = 2) AND `order_id` = ? ';
                
                $query =  $this->db->query($sql, array($order_id));
                $result = $query->result_array();
                
                if (empty($result)) {
                        return null;
                } else {
                        return $result[0];
                }
        }
        
        /**
         * 新增支付结果通知记录
         * 
         * @param Array $insert
         * @return int
         */
        public function add (Array $insert) {
                if (empty($insert)) {
                        return null;
                }
                
                $this->db->insert($this->_table_prefix . self::TABLE, $insert);
                
                return $this->db->insert_id();
        }
        
        public function get_order_can_process () {
                
                $sql = 'SELECT * FROM ' . $this->_table_prefix . self::TABLE 
                        . ' WHERE `pay_status` = 1 AND `process_status` = 0 AND notify_times < 10';
                
                $query =  $this->db->query($sql);
                $result = $query->result_array();
                
                if (empty($result)) {
                        return array();
                } else {
                        return $result;
                }
        }
        
        /**
         * 修改订单处理状态
         * @param type $id
         */
        public function update_process_status_by_id ($id, $status = 0, $notify_times, $game_return) {
                $this->db->where('id', $id);
                $this->db->update($this->_table_prefix . self::TABLE, array(
                        'process_status' => $status,
                        'notify_times' => $notify_times,
                        'game_return' => $game_return
                ));
        }
}