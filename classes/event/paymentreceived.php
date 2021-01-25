<?php 

//namespace enrol_pagseguro\event;

//class pagseguro_paymentreceived extends \core\event\base {

//    public static function get_name(){
//        return "pagseguro_paymentreceived";
//    }
//    
//    public function get_description(){
//        return "pagseguro_paymentreceived";
//    }
//    
//    public function get_legacy_logdata(){
//        return null;
//    }
//    
//    protected function validate_data(){
//        parent::validate_data();
//        /*if (!isset($this->relateduserid)) {
//            throw new \coding_exception('The \'relateduserid\' must be set.');
//        }
//        if (!isset($this->other['subject'])) {
//            throw new \coding_exception('The \'subject\' value must be set in other.');
//        }
//        if (!isset($this->other['message'])) {
//            throw new \coding_exception('The \'message\' value must be set in other.');
//        }
//        if (!isset($this->other['errorinfo'])) {
//            throw new \coding_exception('The \'errorinfo\' value must be set in other.');
//        }*/
//    }
//    
//    protected function init() {
//        //$this->context = \context_system::instance();
//        $this->data['crud'] = 'c';
//        $this->data['edulevel'] = self::LEVEL_OTHER;
//    }
//}
