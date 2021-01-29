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
 * @package    availability_shibboleth2fa
 * @copyright  2021 Lars Bonczek, innoCampus, TU Berlin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
global $PAGE, $OUTPUT;

$cmid = required_param('id', PARAM_INT);
/** @var cm_info $cm */
list($course, $cm) = get_course_and_cm_from_cmid($cmid);

$url = new moodle_url('/availability/condition/shibboleth2fa/auth.php', array('id' => $cm->id));
$PAGE->set_url($url);

require_login($course, false);

\availability_shibboleth2fa\condition::set_authenticated();

$url = new \moodle_url('/availability/condition/shibboleth2fa/index.php', array('id' => $cm->id));
redirect($url);