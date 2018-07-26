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

header("access-control-allow-origin: https://ws.pagseguro.uol.com.br");
require '../../config.php';
require_once "lib.php";
require_once "./vendor/autoload.php";
require_once $CFG->libdir . '/eventslib.php';
require_once $CFG->libdir . '/enrollib.php';
require_once "./pagseguro_enrol.php";
require_once "./pagseguro_transaction_recurrency.php";


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
define('COMMERCE_PAYMENT_STATUS_FAILURE', 'failure');
define('COMMERCE_PAYMENT_STATUS_PENDING', 'pending');

$instanceid = optional_param('instanceid', 0, PARAM_INT);

$submited = optional_param('submitbutton', '', PARAM_RAW);

$notificationCode = optional_param('notificationCode', '', PARAM_RAW);

$transactionid = optional_param('transaction_id', '', PARAM_RAW);

$recurrencyCode = optional_param('code', '', PARAM_RAW);

if (isset($CFG->pagsegurousesandbox)) {
    $pagseguroBaseURL = 'https://sandbox.pagseguro.uol.com.br';
    $pagseguroWSBaseURL = 'https://ws.sandbox.pagseguro.uol.com.br';
    $env='sandbox';
} else {
    $env='production';
    $pagseguroBaseURL = 'https://pagseguro.uol.com.br';
    $pagseguroWSBaseURL = 'https://ws.pagseguro.uol.com.br';
}
PagSeguroLibrary::init();
PagSeguroConfig::setEnvironment($env);

$plugin = enrol_get_plugin('pagseguro');
$email = $plugin->get_config('pagsegurobusiness');
$token = $plugin->get_config('pagsegurotoken');

if ($submited) {

    $plugin_instance = $DB->get_record("enrol", array("id" => $instanceid, "status" => 0));
    $courseid = $plugin_instance->courseid;
    $course = $DB->get_record('course', array('id' => $courseid));

    pagseguro_handle_checkout($pagseguroWSBaseURL, $pagseguroBaseURL, $email, $token, $courseid, $plugin, $plugin_instance, $course);

} else if ($transactionid) {

    pagseguro_handle_redirect_back($pagseguroWSBaseURL, $transactionid, $email, $token);

} else if ($recurrencyCode){
    pagseguro_handle_redirect_back_recurrency($pagseguroWSBaseURL, $recurrencyCode, $email, $token);

}else if (!empty($notificationCode)) {

    pagseguro_handle_old_notification_system($pagseguroWSBaseURL, $notificationCode, $email, $token);
}

function pagseguro_handle_transaction($transaction_data)
{
    global $CFG, $USER, $DB;

    $data = new stdClass();

    $plugin = enrol_get_plugin('pagseguro');

    $transaction_xml = unserialize($transaction_data);
    $transaction = json_decode(json_encode(simplexml_load_string($transaction_xml)));

    $courseid = $transaction->items->item->id;
    $userid = $USER->id;

    if ($transaction) {
        foreach ($transaction as $trans_key => $trans_value) {
            $trans_key = strtolower($trans_key);
            if (!is_object($trans_value)) {
                $data->$trans_key = $trans_value;
            } else {
                foreach ($trans_value as $key => $value) {
                    $key = strtolower($key);
                    if (is_object($value)) {
                        foreach ($value as $k => $v) {
                            $k = strtolower($k);
                            $k = $trans_key . '_' . $key . '_' . $k;
                            $data->$k = $v;
                        }
                    } else {
                        $key = $trans_key . '_' . $key;
                        $data->$key = $value;
                    }
                }
            }
        }
    } else {
        return false;
    }

    $data->xmlstring = trim(htmlentities($transaction_xml));
    $data->business = $plugin->get_config('pagsegurobusiness');
    $data->receiver_email = $plugin->get_config('pagsegurobusiness');
    $data->userid = $userid;
    $data->courseid = $courseid;
    $data->instanceid = $DB->get_field('enrol', 'id', array('courseid' => $courseid, 'enrol' => 'pagseguro'));
    $data->timeupdated = time();

    if (!isset($data->reference) && empty($data->reference)) {
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

    if (!in_array($data->status,
        array(COMMERCE_PAGSEGURO_STATUS_AWAITING,
            COMMERCE_PAGSEGURO_STATUS_IN_ANALYSIS,
            COMMERCE_PAGSEGURO_STATUS_PAID,
            COMMERCE_PAGSEGURO_STATUS_AVAILABLE))) {
        pagseguro_message_error_to_admin("Status not completed or pending.", $data);
        redirect(new moodle_url('/enrol/pagseguro/return.php', array('id' => $courseid, 'waiting' => 1)));
    }

    $coursecontext = context_course::instance($course->id);

    // Check that amount paid is the correct amount
    if ((float) $plugin_instance->cost <= 0) {
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

    pagseguro_enrol_redirect_and_notify($plugin_instance,
                                     $userid,
                                     $user, 
                                     $course, 
                                     $coursecontext,
                                     $context);
}

function pagseguro_message_error_to_admin($subject, $data)
{

    $admin = get_admin();
    $site = get_site();

    $message = "$site->fullname:  Transaction failed.\n\n$subject\n\n";

    $message .= serialize($data);

    $eventdata = new stdClass();
    $eventdata->modulename = 'moodle';
    $eventdata->component = 'enrol_pagseguro';
    $eventdata->name = 'pagseguro_enrolment';
    $eventdata->userfrom = $admin;
    $eventdata->userto = $admin;
    $eventdata->subject = "pagseguro ERROR: " . $subject;
    $eventdata->fullmessage = $message;
    $eventdata->fullmessageformat = FORMAT_PLAIN;
    $eventdata->fullmessagehtml = '';
    $eventdata->smallmessage = '';
    message_send($eventdata);
}

function pagseguro_handle_checkout_no_recurrency($redirect_url, $item, $pagseguroWSBaseURL, $pagseguroBaseURL, $email, $token, $courseid, $plugin, $plugin_instance, $course)
{
    $url = $checkoutURL . '?email=' . urlencode($email) . "&token=" . $token;
    $encoding = 'UTF-8';

    $xml = "<?xml version=\"1.0\" encoding=\"{$encoding}\" standalone=\"yes\"?>
        <checkout>
            <currency>$item->currency</currency>
            <redirectURL>$redirect_url</redirectURL>
            <items>
                <item>
                    <id>$item->item_id</id>
                    <description>$item->item_desc</description>
                    <amount>$item->item_amount</amount>
                    <quantity>$item->item_qty</quantity>
                </item>
            </items>
        </checkout>";

    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type: application/xml; charset=UTF-8"));
    curl_setopt($curl, CURLOPT_POSTFIELDS, trim($xml));
    $xml = curl_exec($curl);

    curl_close($curl);

    if ($xml == 'Unauthorized') {
        redirect(new moodle_url('/enrol/pagseguro/return.php', array('id' => $courseid, 'error' => 'unauthorized')));
    }

    $xml = simplexml_load_string($xml);

    if (count($xml->error) > 0) {
        redirect(new moodle_url('/enrol/pagseguro/return.php', array('id' => $courseid, 'error' => 'generic')));
    }

    header('Location: ' . $pagseguroBaseURL . '/v2/checkout/payment.html?code=' . $xml->code);
}
function pagseguro_handle_checkout_recurrency($redirect_url, $item, $pagseguroWSBaseURL, $pagseguroBaseURL, $email, $token, $courseid, $plugin, $plugin_instance, $course)
{
    global $CFG, $USER;

    $preApprovalRequest = new PagSeguroPreApprovalRequest();
    $preApprovalRequest->setCurrency($item->currency);
    $preApprovalRequest->setReference($item->item_id);
    /***
     * Pre Approval information
     */
    $preApprovalRequest->setPreApprovalCharge('auto');
    $preApprovalRequest->setPreApprovalName($item->item_desc);
    $preApprovalRequest->setPreApprovalAmountPerPayment($item->item_amount);
    $preApprovalRequest->setPreApprovalPeriod($plugin_instance->customchar1);
    $preApprovalRequest->setRedirectURL('http://942f99c7.ngrok.io' .
     '/enrol/pagseguro/process.php?instanceid=' . $plugin_instance->id .
                                   '&userid=' . $USER->id .
                                   '&courseid=' . $item->item_id);
    $preApprovalRequest->setReviewURL('');
    try {
        $credentials = new PagSeguroAccountCredentials($email,
            $token);
        $url = $preApprovalRequest->register($credentials);
        header('Location: ' . $url["checkoutUrl"]);
    } catch (PagSeguroServiceException $e) {
        redirect(new moodle_url('/enrol/pagseguro/return.php', array('id' => $courseid, 'error' => 'generic')));
    }
}

function pagseguro_handle_checkout($pagseguroWSBaseURL, $pagseguroBaseURL, $email, $token, $courseid, $plugin, $plugin_instance, $course)
{
    global $CFG, $USER;

    $checkoutURL = $pagseguroWSBaseURL . '/v2/checkout/';
    $createSubscriptionURL = $pagseguroWSBaseURL . '/pre-approvals/request';

    $item = new stdClass();

    $item->item_id = $courseid;
    $item->item_desc = empty($course->fullname) ? 'Curso moodle' : mb_substr($course->fullname, 0, 100);
    $item->item_qty = (int) 1;
    $item->item_cost = empty($plugin_instance->cost) ? 0.00 : number_format($plugin_instance->cost, 2);
    $item->item_cost = str_replace(',', '', $item->item_cost);
    $item->item_amount = $item->item_cost;

    $item->currency = $plugin->get_config('currency');

    $redirect_url = $CFG->wwwroot . '/enrol/pagseguro/process.php?instanceid=' . $plugin_instance->id . '&amp;userid=' . $USER->id;

    if (empty($plugin_instance->customchar1) || $plugin_instance->customchar1 == 'none') {
        pagseguro_handle_checkout_no_recurrency($redirect_url, $item, $pagseguroWSBaseURL, $pagseguroBaseURL, $email, $token, $courseid, $plugin, $plugin_instance, $course);
    } else {
        pagseguro_handle_checkout_recurrency($redirect_url, $item, $pagseguroWSBaseURL, $pagseguroBaseURL, $email, $token, $courseid, $plugin, $plugin_instance, $course);
    }
}

function pagseguro_handle_redirect_back_recurrency($pagseguroBaseURL, $code, $email, $token){
    global $USER;
    try {
        $credentials = new PagSeguroAccountCredentials($email,
            $token);
        $result = PagSeguroPreApprovalSearchService::searchByCode($credentials, $code);
        $result->getStatus()->getTypeFromValue();
        //cancelado
        if ($result->getStatus()->getValue() > 2) {
            redirect(new moodle_url('/enrol/pagseguro/return.php', array('error' => 'unauthorized')));
        } else {
            handle_transaction_recurrency($courseid, $USER->id, $result);
        }
    } catch (PagSeguroServiceException $e) {
        redirect(new moodle_url('/enrol/pagseguro/return.php', array('error' => 'unauthorized')));
    }
}
function pagseguro_handle_redirect_back($pagseguroBaseURL, $transactionid, $email, $token)
{

    $url = "{$pagseguroBaseURL}/v2/transactions/{$transactionid}?email={$email}&token={$token}";

    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $transaction = curl_exec($curl);
    curl_close($curl);

    if ($transaction == 'Unauthorized') {
        redirect(new moodle_url('/enrol/pagseguro/return.php', array('error' => 'unauthorized')));
    } else {
        $transaction_data = serialize(trim($transaction));
        pagseguro_handle_transaction($transaction_data);
    }
}

function pagseguro_handle_old_notification_system($pagseguroBaseURL, $notificationCode, $email, $token)
{

    $transactionsv2URL = $pagseguroBaseURL . '/v2/transactions/notifications/';

    $transaction = null;

    $url = $transactionsv2URL . $notificationCode . "?email=" . $email . "&token=" . $token;

    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $transaction = curl_exec($curl);
    curl_close($curl);

    if ($transaction == 'Unauthorized') {
        redirect(new moodle_url('/enrol/pagseguro/return.php', array('id' => $courseid, 'error' => 'unauthorized')));
    } else {
        $transaction_data = serialize(trim($transaction));
        pagseguro_handle_transaction($transaction_data);
    }
}
