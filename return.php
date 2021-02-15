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
 * PagSeguro return script.
 *
 * @package    enrol_pagseguro
 * @copyright  2020 Daniel Neis Araujo <danielneis@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require("../../config.php");
require_once("$CFG->dirroot/enrol/pagseguro/lib.php");

$id = optional_param('id', 0, PARAM_INT);
$error = optional_param('error', '', PARAM_ALPHANUM);

if ($error == 'Unauthorized') {
    print_error('unauthorized request on pagseguro');
}

if (!$course = $DB->get_record("course", array("id" => $id))) {
    redirect($CFG->wwwroot);
}

$context = context_course::instance($course->id);

require_login();

if (isset($SESSION->wantsurl)) {
    $destination = $SESSION->wantsurl;
    unset($SESSION->wantsurl);
} else {
    $destination = "{$CFG->wwwroot}/course/view.php?id={$course->id}";
}

$fullname = format_string($course->fullname, true, array('context' => $context));

if (is_enrolled($context, null, '', true)) { // TODO: use real pagseguro check.
    redirect($destination, get_string('paymentthanks', '', $fullname));

} else if ($error) {

    $PAGE->set_context($context);
    $PAGE->set_url($destination);
    echo $OUTPUT->header();
    notice(get_string('error:'.$error, 'enrol_pagseguro'), $destination);
    echo $OUTPUT->footer();

} else {
    $PAGE->set_context($context);
    $PAGE->set_url($destination);
    echo $OUTPUT->header();
    $a = new stdClass();
    $a->teacher = get_string('defaultcourseteacher');
    $a->fullname = $fullname;
    notice(get_string('paymentsorry', '', $a), $destination);
    echo $OUTPUT->footer();
}
