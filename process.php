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
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

//header("access-control-allow-origin: https://ws.pagseguro.uol.com.br");
require('../../config.php');
require_once("lib.php");
require_once($CFG->libdir.'/eventslib.php');
require_once($CFG->libdir.'/enrollib.php');

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

$instanceid = optional_param('instanceid', 0, PARAM_INT);

$plugin_instance = $DB->get_record("enrol", array("id" => $instanceid, "status" => 0));
$courseid = $plugin_instance->courseid;
$course = $DB->get_record('course', array('id' => $courseid));

$submited = optional_param('submitbutton', '', PARAM_RAW);

$notificationCode = optional_param('notificationCode', '', PARAM_RAW);

$transactionid = optional_param('transaction_id', '', PARAM_RAW);

if (isset($CFG->pagsegurousesandbox)) {
    $pagseguroBaseURL = 'https://sandbox.pagseguro.uol.com.br';
    $pagseguroWSBaseURL = 'https://ws.sandbox.pagseguro.uol.com.br';
} else {
    $pagseguroBaseURL = 'https://pagseguro.uol.com.br';
    $pagseguroWSBaseURL = 'https://ws.pagseguro.uol.com.br';
}

$plugin = enrol_get_plugin('pagseguro');
$email = $plugin->get_config('pagsegurobusiness');
$token = $plugin->get_config('pagsegurotoken');

if ($submited) {

    pagseguro_handle_checkout($pagseguroWSBaseURL, $pagseguroBaseURL, $email, $token, $courseid, $plugin, $plugin_instance, $course);

} else if ($transactionid) {

    pagseguro_handle_redirect_back($pagseguroWSBaseURL, $transactionid, $email, $token, $courseid, $instanceid);

} else if (!empty($notificationCode)) {

    pagseguro_handle_old_notification_system($pagseguroWSBaseURL, $notificationCode, $email, $token, $courseid, $instanceid);
}

function pagseguro_handle_transaction($transaction_data, $instanceid, $cid) {
    global $CFG,$USER,$DB,$course;

    $data = new stdClass();

    $transaction = array();

    $plugin = enrol_get_plugin('pagseguro');

    $userid   = (int) isset($USER->id) && !empty($USER->id) ? $USER->id : null;
    $courseid = (int) isset($course->id) && !empty($course->id) ? $course->id : $cid;

    $transaction_xml = unserialize($transaction_data);
    $transaction = json_decode(json_encode(simplexml_load_string($transaction_xml)));

    if ($transaction) {
        foreach ($transaction as $trans_key => $trans_value) {
            $trans_key = strtolower($trans_key);
            if(!is_object($trans_value)) {
                $data->$trans_key = $trans_value;
            } else {
                foreach($trans_value as $key => $value) {
                    $key = strtolower($key);
                    if(is_object($value)) {
                        foreach($value as $k => $v) {
                            $k = strtolower($k);
                            $k = $trans_key.'_'.$key.'_'.$k;
                            $data->$k = $v;
                        }
                    } else {
                        $key = $trans_key.'_'.$key;
                        $data->$key = $value;
                    }
                }
            }
        }
    } else {
        return false;
    }

    $data->xmlstring        = trim(htmlentities($transaction_xml));
    $data->business         = $plugin->get_config('pagsegurobusiness');
    $data->receiver_email   = $plugin->get_config('pagsegurobusiness');
    $data->userid           = $userid;
    $data->courseid         = $courseid;
    $data->instanceid       = $instanceid;
    $data->timeupdated      = time();

    if(!isset($data->reference) && empty($data->reference)) {
        $data->reference = $plugin->get_config('pagsegurobusiness');
    }

    if (!$user = $DB->get_record("user", array("id" => $data->userid))) {
        pagseguro_message_error_to_admin("Not a valid user id", $data);
        return false;
    }

    if (!$course = $DB->get_record("course", array("id" => $data->courseid))) {
        pagseguro_message_error_to_admin("Not a valid course id", $data);
        return false;
    }

    if (!$context = context_course::instance($course->id)) {
        pagseguro_message_error_to_admin("Not a valid context id", $data);
        return false;
    }

    if (!$plugin_instance = $DB->get_record("enrol", array("id" => $data->instanceid, "status" => 0))) {
        pagseguro_message_error_to_admin("Not a valid instance id", $data);
        return false;
    }

    switch ($data->status) {
        case COMMERCE_PAGSEGURO_STATUS_AWAITING:
        case COMMERCE_PAGSEGURO_STATUS_IN_ANALYSIS:
            $data->payment_status = COMMERCE_PAYMENT_STATUS_PENDING;
            break;

        case COMMERCE_PAGSEGURO_STATUS_PAID:
        case COMMERCE_PAGSEGURO_STATUS_AVAILABLE:
            $data->payment_status = COMMERCE_PAYMENT_STATUS_SUCCESS;
            break;

        case COMMERCE_PAGSEGURO_STATUS_DISPUTED:
        case COMMERCE_PAGSEGURO_STATUS_REFUNDED:
        case COMMERCE_PAGSEGURO_STATUS_CANCELED:
        case COMMERCE_PAGSEGURO_STATUS_DEBITED:
        case COMMERCE_PAGSEGURO_STATUS_WITHHELD:
            $data->payment_status = COMMERCE_PAYMENT_STATUS_FAILURE;
            break;
    }

    if (!in_array($data->status, array(COMMERCE_PAGSEGURO_STATUS_IN_ANALYSIS, COMMERCE_PAGSEGURO_STATUS_PAID, COMMERCE_PAGSEGURO_STATUS_AVAILABLE))) {
        #$plugin->unenrol_user($plugin_instance, $data->userid);
        pagseguro_message_error_to_admin("Status not completed or pending.", $data);
        redirect(new moodle_url('/enrol/pagseguro/return.php', array('id' => $courseid, 'waiting' => 1)));
    }


    $coursecontext = context_course::instance($course->id);

    // Check that amount paid is the correct amount
    if ( (float) $plugin_instance->cost <= 0 ) {
        $cost = (float) $plugin->get_config('cost');
    } else {
        $cost = (float) $plugin_instance->cost;
    }

    if ($data->grossamount < $cost) {
        $cost = format_float($cost, 2);
        pagseguro_message_error_to_admin("Amount paid is not enough ($data->payment_gross < $cost))", $data);
        return false;
    }

    if ($existing = $DB->get_record("enrol_pagseguro", array("code" => $data->code))) {
        $data->id = $existing->id;
        $DB->update_record("enrol_pagseguro", $data);
    } else {
        $DB->insert_record("enrol_pagseguro", $data);
    }

    if ($plugin_instance->enrolperiod) {
        $timestart = time();
        $timeend   = $timestart + $plugin_instance->enrolperiod;
    } else {
        $timestart = 0;
        $timeend   = 0;
    }

    // Enrol user
    $plugin->enrol_user($plugin_instance, $userid, $plugin_instance->roleid, $timestart, $timeend);

    // Pass $view=true to filter hidden caps if the user cannot see them
    if ($users = get_users_by_capability($context, 'moodle/course:update', 'u.*', 'u.id ASC',
                '', '', '', '', false, true)) {
        $users = sort_by_roleassignment_authority($users, $context);
        $teacher = array_shift($users);
    } else {
        $teacher = get_admin();
    }

    $mailstudents = $plugin->get_config('mailstudents');
    $mailteachers = $plugin->get_config('mailteachers');
    $mailadmins   = $plugin->get_config('mailadmins');
    $shortname = format_string($course->shortname, true, array('context' => $context));

    if (!empty($mailstudents)) {
        $a = new stdClass();
        $a->coursename = format_string($course->fullname, true, array('context' => $coursecontext));
        $a->profileurl = new moodle_url('/user/view.php', array('id' => $user->id));

        $eventdata = new stdClass();
        $eventdata->modulename        = 'moodle';
        $eventdata->component         = 'enrol_pagseguro';
        $eventdata->name              = 'pagseguro_enrolment';
        $eventdata->userfrom          = $teacher;
        $eventdata->userto            = $user;
        $eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
        $eventdata->fullmessage       = get_string('welcometocoursetext', '', $a);
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml   = '';
        $eventdata->smallmessage      = '';
        message_send($eventdata);
    }

    if (!empty($mailteachers)) {
        $a = new stdClass();
        $a->course = format_string($course->fullname, true, array('context' => $coursecontext));
        $a->user = fullname($user);

        $eventdata = new stdClass();
        $eventdata->modulename        = 'moodle';
        $eventdata->component         = 'enrol_pagseguro';
        $eventdata->name              = 'pagseguro_enrolment';
        $eventdata->userfrom          = $user;
        $eventdata->userto            = $teacher;
        $eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
        $eventdata->fullmessage       = get_string('enrolmentnewuser', 'enrol', $a);
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml   = '';
        $eventdata->smallmessage      = '';
        message_send($eventdata);
    }

    if (!empty($mailadmins)) {
        $a = new stdClass();
        $a->course = format_string($course->fullname, true, array('context' => $coursecontext));
        $a->user = fullname($user);
        $admins = get_admins();
        foreach ($admins as $admin) {
            $eventdata = new stdClass();
            $eventdata->modulename        = 'moodle';
            $eventdata->component         = 'enrol_pagseguro';
            $eventdata->name              = 'pagseguro_enrolment';
            $eventdata->userfrom          = $user;
            $eventdata->userto            = $admin;
            $eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
            $eventdata->fullmessage       = get_string('enrolmentnewuser', 'enrol', $a);
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml   = '';
            $eventdata->smallmessage      = '';

            message_send($eventdata);
        }
    }

    redirect(new moodle_url('/enrol/pagseguro/return.php', array('id' => $courseid)));
}

function pagseguro_message_error_to_admin($subject, $data) {

    $admin = get_admin();
    $site = get_site();

    $message = "$site->fullname:  Transaction failed.\n\n$subject\n\n";

    $message .= serialize($data);

    $eventdata = new stdClass();
    $eventdata->modulename        = 'moodle';
    $eventdata->component         = 'enrol_pagseguro';
    $eventdata->name              = 'pagseguro_enrolment';
    $eventdata->userfrom          = $admin;
    $eventdata->userto            = $admin;
    $eventdata->subject           = "pagseguro ERROR: ".$subject;
    $eventdata->fullmessage       = $message;
    $eventdata->fullmessageformat = FORMAT_PLAIN;
    $eventdata->fullmessagehtml   = '';
    $eventdata->smallmessage      = '';
    message_send($eventdata);
}

function pagseguro_handle_checkout($pagseguroWSBaseURL, $pagseguroBaseURL, $email, $token, $courseid, $plugin, $plugin_instance, $course) {
    global $CFG, $USER;

    $checkoutURL = $pagseguroWSBaseURL . '/v2/checkout/';

    $item_id      = $courseid;
    $item_desc    = empty($course->fullname) ? 'Curso moodle' : mb_substr($course->fullname, 0, 100);
    $item_qty     = (int)1;
    $item_cost    = empty($plugin_instance->cost) ? 0.00 : number_format($plugin_instance->cost, 2);
    $item_cost    = str_replace(',', '', $item_cost);
    $item_amount  = $item_cost;

    $encoding     = 'UTF-8';
    $currency     = $plugin->get_config('currency');

    $redirect_url = $CFG->wwwroot.'/enrol/pagseguro/process.php?instanceid='.$plugin_instance->id.'&amp;userid='.$USER->id;

    $url = $checkoutURL .'?email=' . urlencode($email) . "&token=" . $token;

    $xml = "<?xml version=\"1.0\" encoding=\"{$encoding}\" standalone=\"yes\"?>
        <checkout>
            <currency>$currency</currency>
            <redirectURL>$redirect_url</redirectURL>
            <items>
                <item>
                    <id>$item_id</id>
                    <description>$item_desc</description>
                    <amount>$item_amount</amount>
                    <quantity>$item_qty</quantity>
                </item>
            </items>
        </checkout>";

    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, Array("Content-Type: application/xml; charset=UTF-8"));
    curl_setopt($curl, CURLOPT_POSTFIELDS, trim($xml));
    $xml = curl_exec($curl);

    curl_close($curl);

    if ($xml == 'Unauthorized') {
        redirect(new moodle_url('/enrol/pagseguro/return.php', array('id' => $courseid, 'error' => 'unauthorized')));
    }

    $xml = simplexml_load_string($xml);

    if (count($xml->error) > 0) {
        #print_error(var_export($xml->error, true));
        redirect(new moodle_url('/enrol/pagseguro/return.php', array('id' => $courseid, 'error' => 'generic')));
    }

    header('Location: '. $pagseguroBaseURL . '/v2/checkout/payment.html?code='.$xml->code);
}

function pagseguro_handle_redirect_back($pagseguroBaseURL, $transactionid, $email, $token, $courseid, $instanceid) {

    $url = "{$pagseguroBaseURL}/v2/transactions/{$transactionid}?email={$email}&token={$token}";

    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $transaction = curl_exec($curl);
    curl_close($curl);

    if ($transaction == 'Unauthorized'){
        redirect(new moodle_url('/enrol/pagseguro/return.php', array('id' => $courseid, 'error' => 'unauthorized')));
    } else {
        $transaction_data  = serialize(trim($transaction));
        pagseguro_handle_transaction($transaction_data, $instanceid, $courseid);
    }
}

function pagseguro_handle_old_notification_system($pagseguroBaseURL, $notificationCode, $email, $token, $courseid, $instanceid) {

    $transactionsv2URL = $pagseguroBaseURL .'/v2/transactions/notifications/';

    $transaction = null;

    $url = $transactionsv2URL . $notificationCode . "?email=".$email."&token=".$token;

    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $transaction = curl_exec($curl);
    curl_close($curl);

    if ($transaction == 'Unauthorized'){
        redirect(new moodle_url('/enrol/pagseguro/return.php', array('id' => $courseid, 'error' => 'unauthorized')));
    } else {
        $transaction_data  = serialize(trim($transaction));
        pagseguro_handle_transaction($transaction_data, $instanceid, $courseid);
    }
}
