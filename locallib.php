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
 * Pagseguro enrol plugin implementation.
 *
 * @package    enrol_pagseguro
 * @copyright  2015 Daniel Neis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");

class enrol_pagseguro_enrol_form extends moodleform {

    public function definition() {
        $mform = $this->_form;
        $mform = $this->_form;
        $instance = $this->_customdata;
        $plugin = enrol_get_plugin('pagseguro');

        $heading = $plugin->get_instance_name($instance);
        $mform->addElement('header', 'pagseguroheader', $heading);

        $instance->currency = get_string($instance->currency, 'currencies');
        $instance->cost = number_format((float)$instance->cost, 2, get_string('decsep', 'langconfig'), '');

        $mform->addElement('static', 'paymentrequired', '', get_string('paymentrequired', 'enrol_pagseguro', $instance));

        $pagseguroimgurl = "https://p.simg.uol.com.br/out/pagseguro/i/botoes/pagamentos/99x61-pagar-assina.gif";
        $mform->addElement('static', 'paymentrequired', '',
                           html_writer::empty_tag('img', array('alt' => get_string('pagseguroaccepted', 'enrol_pagseguro'),
                                                               'src' => $pagseguroimgurl)));

        $mform->addElement('hidden', 'courseid', $instance->courseid);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'instanceid', $instance->id);
        $mform->setType('instanceid', PARAM_INT);

        $mform->addElement('hidden', 'cost', $instance->cost);
        $mform->setType('cost', PARAM_INT);

        $mform->addElement('hidden', 'usersubmitted', 1);
        $mform->setType('usersubmitted', PARAM_INT);

        $this->add_action_buttons(false, get_string('sendpaymentbutton', 'enrol_pagseguro'), '1');
    }
}
