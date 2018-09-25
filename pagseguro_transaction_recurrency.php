<?php
function handle_transaction_recurrency($courseid, $userid, $data){
    global $CFG, $USER, $DB;

    $plugin = enrol_get_plugin('pagseguro');

    $instanceid = $DB->get_field('enrol', 'id', array('courseid' => $courseid, 'enrol' => 'pagseguro'));
    $timeupdated = time();

    if (!$user = $DB->get_record("user", array("id" => $userid))) {
        pagseguro_message_error_to_admin("Not a valid user id", $data);
        return false;
    }

    if (!$course = $DB->get_record("course", array("id" => $courseid))) {
        pagseguro_message_error_to_admin("Not a valid course id", $data);
        return false;
    }

    if (!$context = context_course::instance($course->id)) {
        pagseguro_message_error_to_admin("Not a valid context id", $data);
        return false;
    }

    if (!$plugin_instance = $DB->get_record("enrol", array("id" => $instanceid, "status" => 0))) {
        pagseguro_message_error_to_admin("Not a valid instance id", $data);
        return false;
    }

    // 'INITIATED' => 0,
    // 'PENDING' => 1,
    if ($data->getStatus()->getValue() < 2) {
        pagseguro_message_error_to_admin("Status not completed or pending.", $data);
        redirect(new moodle_url('/enrol/pagseguro/return.php', array('id' => $courseid, 'waiting' => 1)));
    }

    $coursecontext = context_course::instance($course->id);

    pagseguro_enrol_redirect_and_notify($plugin_instance,
    $userid,
    $user, 
    $course, 
    $coursecontext,
    $context);

}