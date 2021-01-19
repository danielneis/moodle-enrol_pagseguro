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
 * External Web Service that connects with pagseguro checkout service.
 *
 * @package    enrol_pagseguro
 * @copyright  Igor Agatti Lima <igor@igoragatti.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . "/externallib.php");

/**
 * External Web Service that connects with pagseguro checkout service.
 *
 * @package    enrol_pagseguro
 * @copyright  Igor Agatti Lima <igor@igoragatti.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_pagseguro_external extends external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_session_parameters() {
        return new external_function_parameters(
            array( 'courseP' => new external_value(PARAM_FLOAT, 'Price of course that is being bought') )
        );
    }

    /**
     * Connects to pagseguro webservice and retrieves session token.
     *
     * @param string $courseprice
     * @return string $sessionToken
     */
    public static function get_session($courseprice) {
        global $USER;
        
        $psemail = get_config('enrol_pagseguro', 'pagsegurobusiness');
        $pstoken = get_config('enrol_pagseguro', 'pagsegurotoken');
        if (get_config('enrol_pagseguro', 'usesandbox') == 1) {
            $baseurl = 'https://ws.sandbox.pagseguro.uol.com.br/v2/sessions?email=';
        } else {
            $baseurl = 'https://ws.pagseguro.uol.com.br/v2/sessions?email=';
        } 

        $params = self::validate_parameters(self::get_session_parameters(),
            array(
                'courseP' => $courseprice
            )
        );

        $url = $baseurl . urlencode($psemail) . '&token=' . $pstoken;
        
        //var_dump($url);

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, Array("Content-Type: application/xml; charset=UTF-8"));
        curl_setopt($curl, CURLOPT_POSTFIELDS, array()); // To be sure it's a POST request.
        $xml = curl_exec($curl);

        $resultxml = simplexml_load_string($xml);
        $rtn = array();

        $rtn['stoken'] = $resultxml->id->__toString();
        $rtn['courseP'] = $courseprice;

        return $rtn;
    }

    /**
     * Returns description of method result value
     * @return external_description
     *
     */
    public static function get_session_returns() {
        return new external_single_structure(
            array(
                'stoken' => new external_value(PARAM_TEXT, 'PagSeguro Session Token'),
                'courseP' => new external_value(PARAM_TEXT, 'Price of course that is being bought')
            )
        );
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_forms_parameters() {
        return new external_function_parameters(
            array(
                'sessionId' => new external_value(PARAM_TEXT, 'Session ID from Pagseguro'),
                'courseId' => new external_value(PARAM_TEXT, 'Course ID that is being bought'),
                'courseP' => new external_value(PARAM_TEXT, 'Price of course that is being bought')
            )
        );
    }


    /**
     * Renders the form inside pagseguro modal.
     *
     * @param string $sessionid
     * @param string $courseid
     * @param string $courseprice
     * @return string $sessionToken
     */
    public static function get_forms($sessionid, $courseid, $courseprice) {
        global $PAGE;

        $params = self::validate_parameters(self::get_forms_parameters(),
            array(
                'sessionId' => $sessionid,
                'courseId' => $courseid,
                'courseP' => $courseprice
            )
        );

        $PAGE->set_context(context_system::instance());
        $renderer = $PAGE->get_renderer('enrol_pagseguro');
        $page = new \enrol_pagseguro\output\checkout_form($params);

        return $page->export_for_template($renderer);
    }

    /**
     * Returns description of method result value
     * @return external_description
     *
     */
    public static function get_forms_returns() {
        $result = new external_value(PARAM_RAW, 'the current time');
        return $result;
    }

}
