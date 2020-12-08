<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Listens for Instant Payment Notification from pagseguro
 *
 * This script waits for Payment notification from pagseguro,
 * then double checks that data by sending it back to pagseguro.
 * If pagseguro verifies this then it sets up the enrolment for that
 * user.
 *
 * @package    enrol
 * @subpackage pagseguro
 * @copyright 2010 Eugene Venter
 * @copyright  2015 Daniel Neis Araujo <danielneis@gmail.com>
 * @author     Eugene Venter - based on code by others
 * @author     Daniel Neis Araujo based on code by Eugene Venter and others
 * @author     Igor Agatti Lima based on code by Eugene Venter, Daniel Neis Araujo and others
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 

require('../../config.php');
require_once("lib.php");
require_once($CFG->libdir.'/enrollib.php');


header("access-control-allow-origin: https://sandbox.pagseguro.uol.com.br");

define('COMMERCE_PAGSEGURO_STATUS_AWAITING', 1);
define('COMMERCE_PAGSEGURO_STATUS_IN_ANALYSIS', 2);
define('COMMERCE_PAGSEGURO_STATUS_PAID', 3);
define('COMMERCE_PAGSEGURO_STATUS_AVAILABLE', 4);
define('COMMERCE_PAGSEGURO_STATUS_DISPUTED', 5);
define('COMMERCE_PAGSEGURO_STATUS_REFUNDED', 6);
define('COMMERCE_PAGSEGURO_STATUS_CANCELED', 7);
define('COMMERCE_PAGSEGURO_STATUS_DEBITED', 8); // Valor devolvido para o comprador.
define('COMMERCE_PAGSEGURO_STATUS_WITHHELD', 9); // Retenção temporária.
define('COMMERCE_PAYMENT_STATUS_SUCCESS', 'success');
define('COMMERCE_PAYMENT_STATUS_FAILURE', 'failure') ;
define('COMMERCE_PAYMENT_STATUS_PENDING', 'pending');

$plugin = enrol_get_plugin('pagseguro');
$email = $plugin->get_config('pagsegurobusiness');
$token = $plugin->get_config('pagsegurotoken');

if (get_config('enrol_pagseguro', 'usesandbox') == 1) {
	$baseUrl = 'https://ws.sandbox.pagseguro.uol.com.br';
}else{
	$baseUrl = 'https://ws.pagseguro.uol.com.br'
} 

$notificationCode = optional_param('notificationCode', '', PARAM_RAW);
$notificationType = optional_param('notificationType', '', PARAM_RAW);

$payment_method = optional_param('pay_method', '', PARAM_RAW);

if($payment_method == 'cc'){
	
	// Build array with all parameters from the form
	$params	= [];
	
	$courseid = optional_param('courseid', '0', PARAM_INT);
	$plugin_instance = $DB->get_record('enrol', array('courseid' => $courseid, 'enrol' => 'pagseguro'));
	$whole_phone = optional_param('senderphonenumber', '', PARAM_RAW);
	$inst_val = optional_param('inst_val', '', PARAM_RAW);
	
	$params['courseid'] = $courseid;
	$params['instanceid'] = $plugin_instance->id;
	
	// Continue building array of parameters from the form
	$params['name'] = optional_param('ccholdername', '', PARAM_RAW);
	$params['email'] = optional_param('senderemail', '', PARAM_RAW);
	$params['phone_area'] = substr($whole_phone, 1, 2);
	$params['phone_number'] = trim(preg_replace("(\D)","",substr($whole_phone, 5)));
	$params['doc_number'] = preg_replace("(\D)","",optional_param('sendercpfcnpj', '', PARAM_RAW));
	$params['doc_type'] = strlen($params['doc_number']) == 14 ? 'CNPJ' : 'CPF';
	$params['currency'] = 'BRL';
	$params['notification_url'] = new moodle_url('/enrol/pagseguro/tr_process.php');
	$params['item_desc'] = empty($course->fullname) ? 'Curso moodle' : mb_substr($course->fullname, 0, 100);
	$params['item_amount'] = number_format($plugin_instance->cost, 2);
	$params['item_amount'] = str_replace(',', '', $params['item_amount']);
	$params['item_qty'] = 1;
	$params['cc_token'] = optional_param('cc_token', '', PARAM_RAW);
	$params['cc_installment_quantity'] = optional_param('ccinstallments', '', PARAM_RAW);
	$params['cc_installment_value'] = number_format($inst_val,2);
	$params['address_street'] = optional_param('billingstreet', '', PARAM_RAW);
    $params['address_number'] = optional_param('billingnumber', '', PARAM_RAW);
	$params['address_complement'] = optional_param('billingcomplement', '', PARAM_RAW);
	$params['address_district'] = optional_param('billingdistrict', '', PARAM_RAW);
	$params['address_city'] = optional_param('billingcity', '', PARAM_RAW);
	$params['address_state'] = optional_param('billingstate', '', PARAM_RAW);
	$params['address_country'] = 'BRA'; //optional_param('billingcountry', '', PARAM_RAW);
	$params['address_postcode'] = optional_param('billingpostcode', '', PARAM_RAW);
	
	$params['payment_status'] = COMMERCE_PAYMENT_STATUS_PENDING;
	
	// Handle Credit Card Checkout
	pagseguro_transparent_ccCheckout($params, $email, $token, $baseUrl);
   
}

if($payment_method == 'boleto'){
  
  $courseid = optional_param('courseid', '0', PARAM_INT);
  $plugin_instance = $DB->get_record('enrol', array('courseid' => $courseid, 'enrol' => 'pagseguro'));

  $whole_phone = optional_param('senderphonenumber', '', PARAM_RAW);
  
  $params	= [];
  $params['courseid'] = $courseid;
  $params['instanceid'] = $plugin_instance->id;
  
  $params['name'] = optional_param('sendername', '', PARAM_RAW);
  $params['email'] = optional_param('senderemail', '', PARAM_RAW);
  $params['phone_area'] = substr($whole_phone, 1, 2);
  $params['phone_number'] = trim(preg_replace("(\D)","",substr($whole_phone, 5)));
  $params['doc_number'] = preg_replace("(\D)","",optional_param('sendercpfcnpj', '', PARAM_RAW));
  $params['doc_type'] = strlen($params['doc_number']) == 14 ? 'CNPJ' : 'CPF';
  $params['currency'] = 'BRL';
  $params['notification_url'] = new moodle_url('/enrol/pagseguro/tr_process.php');
  $params['item_desc'] = empty($course->fullname) ? 'Curso moodle' : mb_substr($course->fullname, 0, 100);
  $params['item_amount'] = number_format($plugin_instance->cost, 2);
  $params['item_amount'] = str_replace(',', '', $params['item_amount']);
  $params['item_qty'] = 1;
  $params['sender_hash'] = optional_param('sender_hash', '', PARAM_RAW);
  $params['plugin_instance'] = $plugin_instance;

  pagseguro_transparent_boletoCheckout($params, $email, $token,$baseUrl);
}

if(!empty($notificationCode) && $notificationType == 'transaction'){	
	pagseguro_transparent_notificationRequest($notificationCode, $email, $token, $baseUrl);
}

/**
 * Controller function of the credit card checkout
 *
 * parameters:
 * $params: array of information about the order, gathered from the form
 * $email: string with Pagseguro seller email
 * $token: string with Pagseguro seller token
 * 
 */
function pagseguro_transparent_ccCheckout($params, $email, $token, $baseUrl){
    
  // Insert into database the order (so there aren't any lost customers)
  $refid = pagseguro_transparent_insertOrder($params, $email, $token);
  $params['reference'] = $refid;
  
  $req_xml = pagseguro_transparent_ccXml($params);
  
  $url = $baseUrl."/v2/transactions?email={$email}&token={$token}";

  $data = pagseguro_transparent_sendPaymentDetails($req_xml, $url);
  
  if ($data == 'Unauthorized') {
  	$params['payment_status'] = COMMERCE_PAYMENT_STATUS_FAILURE;
  	pagseguro_transparent_updateOrder($params, $email, $token);
    redirect(new moodle_url('/enrol/pagseguro/return.php', array('id' => $params['courseid'], 'error' => 'unauthorized')));
  }
  
  if (count($data->error) > 0) {
    $params['payment_status'] = COMMERCE_PAYMENT_STATUS_FAILURE;
  	pagseguro_transparent_updateOrder($params, $email, $token);
    redirect(new moodle_url('/enrol/pagseguro/return.php', array('id' => $params['courseid'], 'error' => 'generic')));
  }
  
  $transaction_response = simplexml_load_string($data);
  
  pagseguro_transparent_handleTransactionResponse($transaction_response);
  
  redirect(new moodle_url('/enrol/pagseguro/return.php', array('id' => $params['courseid'] )));
 
}

/**
 * Controller function of the boleto checkout
 *
 * parameters:
 * $params: array of information about the order, gathered from the form
 * $email: string with Pagseguro seller email
 * $token: string with Pagseguro seller token
 * 
 */
function pagseguro_transparent_boletoCheckout($params, $email, $token){ 
    
  // Insert into database the order (so there aren't any lost customers)
  $refid = pagseguro_transparent_insertOrder($params, $email, $token);
  $params['reference'] = $refid;
  
  $req_xml = pagseguro_transparent_boletoXml($params);

  $url = $baseUrl."/v2/transactions?email={$email}&token={$token}";

  $data = pagseguro_transparent_sendPaymentDetails($req_xml, $url);

  if ($data == 'Unauthorized') {
    $params['payment_status'] = COMMERCE_PAYMENT_STATUS_FAILURE;
  	pagseguro_transparent_updateOrder($params, $email, $token);
    redirect(new moodle_url('/enrol/pagseguro/return.php', array('id' => $params['courseid'], 'error' => 'unauthorized')));
  }

  if (count($data->error) > 0) {
    $params['payment_status'] = COMMERCE_PAYMENT_STATUS_FAILURE;
  	pagseguro_transparent_updateOrder($params, $email, $token);
    redirect(new moodle_url('/enrol/pagseguro/return.php', array('id' => $params['courseid'], 'error' => 'generic')));
  }

  $transaction_response = simplexml_load_string($data);
  
  pagseguro_transparent_handleTransactionResponse($transaction_response);
  
  redirect(new moodle_url('/enrol/pagseguro/return.php', array('id' => $params['courseid'] )));
  
}

/**
 * Controller function of the notification receiver
 *
 * parameters:
 * $notificationCode: string with the notification code sent by Pagseguro
 * $email: string with Pagseguro seller email
 * $token: string with Pagseguro seller token
 * 
 */
function pagseguro_transparent_notificationRequest($notificationCode, $email, $token){
  
  $url = $baseUrl."/v3/transactions/notifications/{$notificationCode}?email={$email}&token={$token}";
  
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_HEADER, 0);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/x-www-form-urlencoded; charset=ISO-8859-1"));
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  
  $data = curl_exec($ch);
  curl_close($ch);
  
  $transaction = simplexml_load_string($data);
  
  $rec = pagseguro_transparent_handleTransactionResponse($transaction, $data);
  
  pagseguro_transparent_handleEnrolment($rec);
	
}

/**
 * pagseguro_transparent_sendPaymentDetails
 * Sends payment details with an XML string to a URL using the curl request system.
 *
 * parameters:
 * $xml: string with the XML file to be sent to URL
 * $url: string with the URL
 * 
 * return:
 * response from the curl request
 */
function pagseguro_transparent_sendPaymentDetails($xml, $url){

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_HEADER, 0);
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/xml; charset=ISO-8859-1"));
  curl_setopt($ch, CURLOPT_POST, 1);
 
  curl_setopt($ch, CURLOPT_POSTFIELDS, $xml );
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  
  $data = curl_exec($ch);
  curl_close($ch);
  
  return $data;
  
}

/**
 * pagseguro_transparent_insertOrder
 * Inserts preliminary order information into enrol_pagseguro table.
 *
 * parameters:
 * $params: array of information about the order, gathered from the form
 * $email: string with Pagseguro seller email
 * $token: string with Pagseguro seller token
 *
 * return:
 * ID of the record inserted
 */
function pagseguro_transparent_insertOrder($params, $email, $token){
  global $USER, $DB;

  $rec = new stdClass();
  $rec->pagseguro_token = $token;
  $rec->pagseguro_email = $email;
  $rec->courseid = $params['courseid'];
  $rec->userid = $USER->id;
  $rec->instanceid = $params['instanceid'];
  $rec->date = date("Y-m-d");
  $rec->payment_status = COMMERCE_PAYMENT_STATUS_PENDING;
  
  return $DB->insert_record("enrol_pagseguro", $rec);
  
}

/**
 * pagseguro_transparent_insertOrder
 * Inserts preliminary order information into enrol_pagseguro table.
 *
 * parameters:
 * $params: array of information about the order, gathered from the form
 * $email: string with Pagseguro seller email
 * $token: string with Pagseguro seller token
 *
 * return:
 * response from the curl request
 */
function pagseguro_transparent_updateOrder($params, $email, $token){
  global $USER, $DB;

  $rec = new stdClass();
  $rec->id = $params['reference'];
  $rec->pagseguro_token = $token;
  $rec->pagseguro_email = $email;
  $rec->courseid = $params['courseid'];
  $rec->userid = $USER->id;
  $rec->instanceid = $params['instanceid'];
  $rec->date = date("Y-m-d");
  $rec->payment_status = $params['payment_status'];
  
  $DB->update_record("enrol_pagseguro", $rec);
  
}

function pagseguro_transparent_handleTransactionResponse($data) {
	
	global $DB;
	
	$rec = new stdClass();
	$rec->id = $data->reference->__toString();
	$rec->code = $data->code->__toString();
	$rec->type = $data->type->__toString();
	$rec->status = intval($data->status->__toString());
	$rec->paymentmethod_type = $data->paymentMethod->type->__toString();
	$rec->paymentmethod_code = $data->paymentMethod->code->__toString();
	$rec->grossamount = number_format($data->grossAmount->__toString(),2);
	$rec->discountedamount = $data->discountAmount->__toString();
	
	switch($rec->status){
		case COMMERCE_PAGSEGURO_STATUS_AWAITING:
        case COMMERCE_PAGSEGURO_STATUS_IN_ANALYSIS:
            $rec->payment_status = COMMERCE_PAYMENT_STATUS_PENDING;
            break;

        case COMMERCE_PAGSEGURO_STATUS_PAID:
        case COMMERCE_PAGSEGURO_STATUS_AVAILABLE:
            $rec->payment_status = COMMERCE_PAYMENT_STATUS_SUCCESS;
            break;

        case COMMERCE_PAGSEGURO_STATUS_DISPUTED:
        case COMMERCE_PAGSEGURO_STATUS_REFUNDED:
        case COMMERCE_PAGSEGURO_STATUS_CANCELED:
        case COMMERCE_PAGSEGURO_STATUS_DEBITED:
        case COMMERCE_PAGSEGURO_STATUS_WITHHELD:
            $rec->payment_status = COMMERCE_PAYMENT_STATUS_FAILURE;
            break;
	
	}
	
	$DB->update_record("enrol_pagseguro", $rec);
	
	return $DB->get_record("enrol_pagseguro",['id'=>$rec->id]);

}

function pagseguro_transparent_handleEnrolment($rec){
	global $DB;
	
	$plugin = enrol_get_plugin('pagseguro');
	$plugin_instance = $DB->get_record('enrol', array('courseid' => $rec->courseid, 'enrol' => 'pagseguro'));
	
	if ($plugin_instance->enrolperiod) {
        $timestart = time();
        $timeend   = $timestart + $plugin_instance->enrolperiod;
    } else {
        $timestart = 0;
        $timeend   = 0;
    }
    
	switch($rec->payment_status){
		case COMMERCE_PAYMENT_STATUS_SUCCESS:
			$plugin->enrol_user($plugin_instance, $rec->userid, $plugin_instance->roleid, $timestart, $timeend);
			break;
		case COMMERCE_PAYMENT_STATUS_FAILURE:
			$plugin->unenrol_user($plugin_instance, $rec->userid);
			break;	
	}
	
}

function pagseguro_transparent_boletoXml($params){
  return "<? xml version=\"1.0\" encoding=\"ISO-8859-1\" standalone=\"yes\" ?>
    <payment>
      <mode>default</mode>
      <method>boleto</method>
	  <sender>
        <name>".$params['name']."</name>
        <email>".$params['email']."</email>
        <phone>
          <areaCode>".$params['phone_area']."</areaCode>
          <number>".$params['phone_number']."</number>
    	</phone>
    	<documents>
    	  <document>
    	    <type>".$params['doc_type']."</type>
    	    <value>".$params['doc_number']."</value>
		  </document>
		</documents>
		<hash>".$params['sender_hash']."</hash>
      </sender>
      <currency>".$params['currency']."</currency>
      <notificationURL>".$params['notification_url']."</notificationURL>
      <items>
        <item>
          <id>".$params['courseid']."</id>
          <description>".$params['item_desc']."</description>
          <amount>".$params['item_amount']."</amount>
          <quantity>".$params['item_qty']."</quantity>
        </item>
      </items>
      <extraAmount>0.00</extraAmount>
      <reference>".$params['reference']."</reference>
      <shipping>
        <addressRequired>false</addressRequired>
      </shipping>
	</payment>";
}

function pagseguro_transparent_ccXml($params){
	return "<? xml version=\"1.0\" encoding=\"ISO-8859-1\" standalone=\"yes\" ?>
    <payment>
      <mode>default</mode>
      <method>creditCard</method>
	  <sender>
        <name>".$params['name']."</name>
        <email>".$params['email']."</email>
        <phone>
          <areaCode>".$params['phone_area']."</areaCode>
          <number>".$params['phone_number']."</number>
    	</phone>
    	<documents>
    	  <document>
    	    <type>".$params['doc_type']."</type>
    	    <value>".$params['doc_number']."</value>
		  </document>
		</documents>
      </sender>
      <currency>".$params['currency']."</currency>
      <notificationURL>".$params['notification_url']."</notificationURL>
      <items>
        <item>
          <id>".$params['courseid']."</id>
          <description>".$params['item_desc']."</description>
          <amount>".$params['item_amount']."</amount>
          <quantity>".$params['item_qty']."</quantity>
        </item>
      </items>
      <extraAmount>0.00</extraAmount>
      <reference>".$params['reference']."</reference>
      <shipping>
        <addressRequired>false</addressRequired>
      </shipping>
      <creditCard>
		<token>".$params['cc_token']."</token>
		<installment>
		  <quantity>".$params['cc_installment_quantity']."</quantity>
		  <value>".$params['cc_installment_value']."</value>
		</installment>
		<holder>
		  <name>".$params['name']."</name>
		  <documents>
		    <document>
		      <type>".$params['doc_type']."</type>
		      <value>".$params['doc_number']."</value>
		    </document>
		  </documents>
		  <birthDate>".$params['birthday']."</birthDate>
		  <phone>
		    <areaCode>".$params['phone_area']."</areaCode>
		    <number>".$params['phone_number']."</number>
		  </phone>
		</holder>
		<billingAddress>
		  <street>".$params['address_street']."</street>
		  <number>".$params['address_number']."</number>
		  <complement>".$params['address_complement']."</complement>
		  <district>".$params['address_district']."</district>
		  <city>".$params['address_city']."</city>
		  <state>".$params['address_state']."</state>
		  <country>".$params['address_country']."</country>
		  <postalCode>".$params['address_postcode']."</postalCode>
		</billingAddress>
      </creditCard>
	</payment>";
}


