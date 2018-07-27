<?php

function pagseguro_enrol_redirect_and_notify($plugin_instance,
                                     $userid,
                                     $user, 
                                     $course, 
                                     $coursecontext,
                                     $context){
    $plugin = enrol_get_plugin('pagseguro');

    if ($plugin_instance->enrolperiod) {
        $timestart = time();
        $timeend = $timestart + $plugin_instance->enrolperiod;
    } else {
        $timestart = 0;
        $timeend = 0;
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
    $mailadmins = $plugin->get_config('mailadmins');
    $shortname = format_string($course->shortname, true, array('context' => $context));

    if (!empty($mailstudents)) {
        $a = new stdClass();
        $a->coursename = format_string($course->fullname, true, array('context' => $coursecontext));
        $a->profileurl = new moodle_url('/user/view.php', array('id' => $user->id));

        $eventdata = new stdClass();
        $eventdata->modulename = 'moodle';
        $eventdata->component = 'enrol_pagseguro';
        $eventdata->name = 'pagseguro_enrolment';
        $eventdata->userfrom = $teacher;
        $eventdata->userto = $user;
        $eventdata->subject = get_string("enrolmentnew", 'enrol', $shortname);
        $eventdata->fullmessage = get_string('welcometocoursetext', '', $a);
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml = '';
        $eventdata->smallmessage = '';
        message_send($eventdata);
    }

    if (!empty($mailteachers)) {
        $a = new stdClass();
        $a->course = format_string($course->fullname, true, array('context' => $coursecontext));
        $a->user = fullname($user);

        $eventdata = new stdClass();
        $eventdata->modulename = 'moodle';
        $eventdata->component = 'enrol_pagseguro';
        $eventdata->name = 'pagseguro_enrolment';
        $eventdata->userfrom = $user;
        $eventdata->userto = $teacher;
        $eventdata->subject = get_string("enrolmentnew", 'enrol', $shortname);
        $eventdata->fullmessage = get_string('enrolmentnewuser', 'enrol', $a);
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml = '';
        $eventdata->smallmessage = '';
        message_send($eventdata);
    }

    if (!empty($mailadmins)) {
        $a = new stdClass();
        $a->course = format_string($course->fullname, true, array('context' => $coursecontext));
        $a->user = fullname($user);
        $admins = get_admins();
        foreach ($admins as $admin) {
            $eventdata = new stdClass();
            $eventdata->modulename = 'moodle';
            $eventdata->component = 'enrol_pagseguro';
            $eventdata->name = 'pagseguro_enrolment';
            $eventdata->userfrom = $user;
            $eventdata->userto = $admin;
            $eventdata->subject = get_string("enrolmentnew", 'enrol', $shortname);
            $eventdata->fullmessage = get_string('enrolmentnewuser', 'enrol', $a);
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml = '';
            $eventdata->smallmessage = '';

            message_send($eventdata);
        }
    }
    redirect(new moodle_url('/enrol/pagseguro/return.php', array('id' => $course->id)));

}