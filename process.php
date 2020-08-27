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
 * @package    enrol_pagseguro
 * @copyright  2020 Daniel Neis Araujo <danielneis@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Block requestes outside pagseguro: header("access-control-allow-origin: https://ws.pagseguro.uol.com.br");.
// @codingStandardsIgnoreLine
require('../../config.php');

require_once("lib.php");
require_once($CFG->libdir.'/enrollib.php');
require_once('../../lib/classes/user.php');

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

$submited = optional_param('submitbutton', '', PARAM_RAW);

$notificationcode = optional_param('notificationCode', '', PARAM_RAW);

$transactionid = optional_param('transaction_id', '', PARAM_RAW);
$instanceid = optional_param('instanceid', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);

if (get_config('enrol_pagseguro', 'usesandbox') == 1) {
    $pagsegurobaseurl = 'https://sandbox.pagseguro.uol.com.br';
    $pagsegurowsbaseurl = 'https://ws.sandbox.pagseguro.uol.com.br';
} else {
    $pagsegurobaseurl = 'https://pagseguro.uol.com.br';
    $pagsegurowsbaseurl = 'https://ws.pagseguro.uol.com.br';
}

$plugin = enrol_get_plugin('pagseguro');
$email = $plugin->get_config('pagsegurobusiness');
$token = $plugin->get_config('pagsegurotoken');

if ($submited) {

    $plugininstance = $DB->get_record("enrol", array("id" => $instanceid, "status" => 0));
    $courseid = $plugininstance->courseid;
    $course = $DB->get_record('course', array('id' => $courseid));

    pagseguro_handle_checkout($pagsegurowsbaseurl, $pagsegurobaseurl, $email, $token,
        $courseid, $plugin, $plugininstance, $course);

} else if ($transactionid) {

    $PAGE->set_context(\context_system::instance());
    pagseguro_handle_redirect_back($pagsegurowsbaseurl, $transactionid, $email, $token);

} else if (!empty($notificationcode)) {

    pagseguro_handle_old_notification_system($pagsegurowsbaseurl, $notificationcode, $email, $token);
}

function pagseguro_handle_transaction($transactionxml, $redirect = true) {
    global $CFG, $USER, $DB;

    $data = new stdClass();

    $plugin = enrol_get_plugin('pagseguro');

    $transaction = json_decode(json_encode(simplexml_load_string($transactionxml)));

    if ($transaction) {
        foreach ($transaction as $transkey => $transvalue) {
            $transkey = strtolower($transkey);
            if (!is_object($transvalue)) {
                $data->$transkey = $transvalue;
            } else {
                foreach ($transvalue as $key => $value) {
                    $key = strtolower($key);
                    if (is_object($value)) {
                        foreach ($value as $k => $v) {
                            $k = strtolower($k);
                            $k = $transkey.'_'.$key.'_'.$k;
                            $data->$k = $v;
                        }
                    } else {
                        $key = $transkey.'_'.$key;
                        $data->$key = $value;
                    }
                }
            }
        }
    } else {
        return false;
    }

    list($instanceid, $userid) = explode('-', $transaction->items->item->id);

    $data->xmlstring        = trim(htmlentities($transactionxml));
    $data->business         = $plugin->get_config('pagsegurobusiness');
    $data->receiver_email   = $plugin->get_config('pagsegurobusiness');
    $data->userid           = $userid;
    $data->instanceid       = $instanceid;
    $data->courseid         = $DB->get_field('enrol', 'courseid', ['id' => $instanceid]);
    $data->timeupdated      = time();

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

    if (!$plugininstance = $DB->get_record("enrol", array("id" => $data->instanceid, "status" => 0))) {
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

    $coursecontext = context_course::instance($course->id);

    // Check that amount paid is the correct amount.
    if ( (float) $plugininstance->cost <= 0 ) {
        $cost = (float) $plugin->get_config('cost');
    } else {
        $cost = (float) $plugininstance->cost;
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

    if ($plugininstance->enrolperiod) {
        $timestart = time();
        $timeend   = $timestart + $plugininstance->enrolperiod;
    } else {
        $timestart = 0;
        $timeend   = 0;
    }

    if (get_config('enrol_pagseguro', 'automaticenrolboleto')) {
        $enrolstatuses = [COMMERCE_PAGSEGURO_STATUS_AWAITING,
                          COMMERCE_PAGSEGURO_STATUS_IN_ANALYSIS,
                          COMMERCE_PAGSEGURO_STATUS_PAID,
                          COMMERCE_PAGSEGURO_STATUS_AVAILABLE];
    } else {
        $enrolstatuses = [COMMERCE_PAGSEGURO_STATUS_PAID, COMMERCE_PAGSEGURO_STATUS_AVAILABLE];
    }

    $unenrolstatuses = [
        COMMERCE_PAGSEGURO_STATUS_DISPUTED,
        COMMERCE_PAGSEGURO_STATUS_REFUNDED,
        COMMERCE_PAGSEGURO_STATUS_CANCELED
    ];

    if (in_array($data->status, $enrolstatuses)) {

        $plugin->enrol_user($plugininstance, $userid, $plugininstance->roleid, $timestart, $timeend);

    } else if (in_array($data->status, $unenrolstatuses)) {

        $plugin->unenrol_user($plugininstance, $userid);
        return;

    } else {
        if ($redirect) {
            redirect(new moodle_url('/enrol/pagseguro/return.php', array('id' => $data->courseid, 'waiting' => 1)));
        } else {
            return;
        }
    }

    // Pass $view=true to filter hidden caps if the user cannot see them.
    $teachers = get_users_by_capability($context, 'moodle/course:update',
        'u.id,u.email,u.username,'. get_all_user_name_fields(true, 'u'), 'u.id ASC', '', '', '', '', false, true);
    if ($teachers) {
        $teachers = sort_by_roleassignment_authority($teachers, $context);
    }

    $mailstudents = $plugin->get_config('mailstudents');
    $mailteachers = $plugin->get_config('mailteachers');
    $mailadmins   = $plugin->get_config('mailadmins');
    $shortname = format_string($course->shortname, true, array('context' => $context));

    if (!empty($mailstudents)) {
        $a = new stdClass();
        $a->coursename = format_string($course->fullname, true, array('context' => $coursecontext));
        $a->profileurl = new moodle_url('/user/view.php', array('id' => $user->id));

        if ($plugin->get_config('mailfromsupport') == 1) {
            $userfrom = core_user::get_support_user();
        } else {
            $userfrom = array_shift($teachers);
        }

        $eventdata = new \core\message\message();
        $eventdata->component         = 'enrol_pagseguro';
        $eventdata->name              = 'pagseguro_enrolment';
        $eventdata->userfrom          = $userfrom;
        $eventdata->userto            = $user;
        $eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
        $eventdata->fullmessage       = get_string('welcometocoursetext', '', $a);
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml   = '';
        $eventdata->smallmessage      = '';
        message_send($eventdata);
    }

    if (!empty($mailteachers) && isset($teachers)) {
        $a = new stdClass();
        $a->course = format_string($course->fullname, true, array('context' => $coursecontext));
        $a->user = fullname($user);

        foreach ($teacher as $teachers) {
            $eventdata = new \core\message\message();
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
    }

    if (!empty($mailadmins)) {
        $a = new stdClass();
        $a->course = format_string($course->fullname, true, array('context' => $coursecontext));
        $a->user = fullname($user);
        $admins = get_admins();
        foreach ($admins as $admin) {
            $eventdata = new \core\message\message();
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

    redirect(new moodle_url('/enrol/pagseguro/return.php', array('id' => $data->courseid)));
}

function pagseguro_message_error_to_admin($subject, $data) {

    $admin = get_admin();
    $userfrom = \core_user::get_noreply_user();
    $site = get_site();

    $message = "$site->fullname:  Transaction failed.\n\n$subject\n\n";

    $message .= serialize($data);

    $eventdata = new \core\message\message();
    $eventdata->courseid          = SITEID;
    $eventdata->component         = 'enrol_pagseguro';
    $eventdata->name              = 'pagseguro_enrolment';
    $eventdata->userfrom          = $userfrom;
    $eventdata->userto            = $admin;
    $eventdata->notification      = 1;
    $eventdata->subject           = "pagseguro ERROR: ".$subject;
    $eventdata->fullmessage       = $message;
    $eventdata->fullmessageformat = FORMAT_PLAIN;
    $eventdata->fullmessagehtml   = '';
    $eventdata->smallmessage      = $subject;
    message_send($eventdata);
}

function pagseguro_handle_checkout($pagsegurowsbaseurl, $pagsegurobaseurl, $email, $token,
    $courseid, $plugin, $plugininstance, $course) {

    global $CFG, $USER;

    $checkouturl = $pagsegurowsbaseurl . '/v2/checkout/';

    $reference    = json_encode(['instanceid' => $plugininstance->id, 'userid' => $USER->id]);

    $itemid      = "{$plugininstance->id}-{$USER->id}";
    $itemdesc    = empty($course->fullname) ? 'Curso moodle' : mb_substr($course->fullname, 0, 100);
    $itemqty     = (int)1;
    $itemcost    = empty($plugininstance->cost) ? 0.00 : number_format($plugininstance->cost, 2);
    $itemcost    = str_replace(',', '', $itemcost);
    $itemamount  = $itemcost;

    $encoding     = 'UTF-8';
    $currency     = $plugin->get_config('currency');

    $redirecturl = new moodle_url('/enrol/pagseguro/process.php', ['instanceid' => $plugininstance->id, 'userid' => $USER->id]);

    $url = $checkouturl .'?email=' . urlencode($email) . "&token=" . $token;

    $xml = "<?xml version=\"1.0\" encoding=\"{$encoding}\" standalone=\"yes\"?>
        <checkout>
            <currency>$currency</currency>
            <redirectURL>$redirecturl</redirectURL>
            <items>
                <item>
                    <id>$itemid</id>
                    <description>$itemdesc</description>
                    <amount>$itemamount</amount>
                    <quantity>$itemqty</quantity>
                </item>
                <reference>$reference</reference>
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
        redirect(new moodle_url('/enrol/pagseguro/return.php', array('id' => $courseid, 'error' => 'generic')));
    }

    header('Location: '. $pagsegurobaseurl . '/v2/checkout/payment.html?code='.$xml->code);
}

function pagseguro_handle_redirect_back($pagsegurobaseurl, $transactionid, $email, $token) {

    $url = "{$pagsegurobaseurl}/v2/transactions/{$transactionid}?email={$email}&token={$token}";

    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $transaction = curl_exec($curl);
    curl_close($curl);

    if ($transaction == 'Unauthorized') {
        redirect(new moodle_url('/enrol/pagseguro/return.php', array('error' => 'unauthorized')));
    } else {
        pagseguro_handle_transaction($transaction);
    }
}

function pagseguro_handle_old_notification_system($pagsegurobaseurl, $notificationcode, $email, $token) {

    $transactionsv2url = $pagsegurobaseurl .'/v2/transactions/notifications/';

    $transaction = null;

    $url = $transactionsv2url . $notificationcode . "?email=".$email."&token=".$token;

    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $transaction = curl_exec($curl);
    curl_close($curl);

    if ($transaction == 'Unauthorized') {
        redirect(new moodle_url('/enrol/pagseguro/return.php', array('id' => $courseid, 'error' => 'unauthorized')));
    }

    $transaction = json_decode(json_encode(simplexml_load_string($transaction)));

    $url = "{$pagsegurobaseurl}/v2/transactions/{$transaction->code}?email={$email}&token={$token}";

    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $transaction = curl_exec($curl);
    curl_close($curl);

    if ($transaction == 'Unauthorized') {
        redirect(new moodle_url('/enrol/pagseguro/return.php', array('id' => $courseid, 'error' => 'unauthorized')));
    } else {
        pagseguro_handle_transaction($transaction, false);
    }
}
