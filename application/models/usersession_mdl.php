<?php

/**
 * 通知游服信息
 */
class Usersession_mdl extends MY_Model {
        
        const TABLE = 'usersession';
        
        public function __construct() {
                parent::__construct();
                
                $this->_clear_expired();
        }
        
        public function add ($insert) {
                if (empty($insert)) {
                        return null;
                }
                
                $this->db->insert($this->_table_prefix . self::TABLE, $insert);
                
                return $this->db->insert_id();
        }
        
        public function get_by_uid_and_channel_num ($uid, $channel_num) {
                $sql = 'SELECT * FROM ' . $this->_table_prefix . self::TABLE 
                        . ' WHERE `uid` = "' . $uid . '" AND `channel_num` = "' . $channel_num . '"  ';
                
                $query =  $this->db->query($sql);
                $result = $query->result_array();
                
                if (empty($result)) {
                        return array();
                } else {
                        return array_shift($result);
                }
        }
        
        public function del_by_id ($id) {
                $this->db->where('id', $id);
                $this->db->delete($this->_table_prefix . self::TABLE);
        }
        
        private function _clear_expired () {
                $time = time() - 900;
                $this->db->where('created_time <', $time);
                $this->db->delete($this->_table_prefix . self::TABLE);
        }
}