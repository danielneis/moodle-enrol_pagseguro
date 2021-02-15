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
 * Event class to execute after payment is received.
 *
 *
 * @package    enrol_pagseguro
 * @copyright  2020 Daniel Neis Araujo <danielneis@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_pagseguro\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Event class to execute after payment is received.
 * @author  Igor Agatti
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class payment_receive extends \core\event\base {

    public static function get_name() {
        return "pagseguro_payment_receive";
    }

    public function get_description() {
        return "pagseguro_payment_receive";
    }

    public function get_legacy_logdata() {
        return null;
    }

    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }
}
