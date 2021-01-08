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
 * Class containing data for index page
 *
 * @package    local_hackfest
 * @copyright  2015 Damyon Wiese
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace enrol_pagseguro\output;

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->dirroot/webservice/externallib.php");

use renderable;
use templatable;
use renderer_base;
use stdClass;

/**
 * Class containing data for index page
 *
 * @copyright  2015 Damyon Wiese
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class checkout_form implements renderable, templatable {


    public $formparams = array();

    public function __construct(array $fparams = array()) {
        $this->formparams = $fparams;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        global $USER, $COURSE, $PAGE;

        $data = array();
        $data["courseid"] = $PAGE->course->id;
        $data["email"] = $USER->email;
        $data["fullname"] = $USER->firstname." ".$USER->lastname;
        if ($USER->cpf) {
            $data["cpf"] = $USER->cpf;
        }
        if ($USER->phone) {
            $data["phone"] = $USER->phone;
        }
        $data["dt"] = userdate(time()) . ' ' . rand();
        if ($this->formparams['courseP']) {
            $data["price"] = $this->formparams['courseP'];
        }

        return json_encode($data);
    }
}
