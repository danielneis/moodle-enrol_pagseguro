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
 * Web service local plugin template external functions and service definitions.
 *
 * @package    enrol_pagseguro
 * @copyright  2011 Jerome Mouneyrac
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// We defined the web service functions to install.
$functions = array(

	'enrol_pagseguro_get_session' => array(
		'classname'   => 'enrol_pagseguro_external',
		'methodname'  => 'get_session',
		'classpath'   => 'enrol/pagseguro/externallib.php',
		'description' => 'Gets the session token from PagSeguro.',
		'ajax'		  => true, 
		'type'        => 'read',
	),
	
	'enrol_pagseguro_get_forms' => array(
		'classname'   => 'enrol_pagseguro_external',
		'methodname'  => 'get_forms',
		'classpath'   => 'enrol/pagseguro/externallib.php',
		'description' => 'Renders the form for PagSeguro Transparent Checkout.',
		'ajax'		  => true, 
		'type'        => 'read',
	),
);

