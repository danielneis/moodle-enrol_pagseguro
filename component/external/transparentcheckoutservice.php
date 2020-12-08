<?php

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
 * External Web Service Template
 *
 * @package    localwstemplate
 * @copyright  2011 Moodle Pty Ltd (http://moodle.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->libdir . "/externallib.php");

class enrol_pagseguro_external extends external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_session_parameters() {
        return new external_function_parameters();
    }
    
    
    /**
    * Expose to AJAX
    * @return boolean
    */
    public static function get_session_is_allowed_from_ajax() {
    	return true;
    }

    /**
     * Returns welcome message
     * @return string welcome message
     */
    public static function get_session() {
        global $USER;

        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(self::hello_world_parameters(),array());

        //Context validation
        //OPTIONAL but in most web service it should present
        $context = get_context_instance(CONTEXT_USER, $USER->id);
        self::validate_context($context);

        //Capability checking
        //OPTIONAL but in most web service it should present
        if (!has_capability('moodle/user:viewdetails', $context)) {
            throw new moodle_exception('cannotviewprofile');
        }
        
        if (get_config('enrol_pagseguro', 'usesandbox') == 1) {
        	$url = 'https://ws.sandbox.pagseguro.uol.com.br/v2/sessions';
        }else{
        	$url = 'https://ws.pagseguro.uol.com.br/v2/sessions'
        }

        $ps_email = get_config('enrol_pagseguro', 'pagsegurobusiness');
        $ps_token = get_config('enrol_pagseguro', 'pagsegurotoken')
        
		$data = array('email' => $ps_email, 'token' => $ps_token);

		// use key 'http' even if you send the request to https://...
		$options = array(
			'http' => array(
				'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
				'method'  => 'POST',
				'content' => http_build_query($data)
			)
		);
		$context  = stream_context_create($options);
		$result = file_get_contents($url, false, $context);
		if ($result === FALSE) { /* Handle error */ }

		$result_xml = simplexml_load_string($result);
		$rtn = $result_xml->id;

        return $rtn;
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function get_session_returns() {
        return new external_value(PARAM_TEXT, 'PagSeguro Session Token');
    }

}
