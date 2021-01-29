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
global $PAGE, $OUTPUT, $USER;

$cmid = required_param('id', PARAM_INT);
/** @var cm_info $cm */
list($course, $cm) = get_course_and_cm_from_cmid($cmid);

$url = new moodle_url('/availability/condition/shibboleth2fa/index.php', array('id' => $cm->id));
$PAGE->set_url($url);

require_login($course, false);

$format = course_get_format($course);
$courseurl = $format->get_view_url(null);

$PAGE->set_title($course->shortname);
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();

if (\availability_shibboleth2fa\condition::is_course_available($course->id, $USER->id)) {
    $modurl = new moodle_url("/mod/$cm->modname/view.php", array("id" => $cm->id));
    echo $OUTPUT->confirm(get_string('login_successful', 'availability_shibboleth2fa'), $modurl, $courseurl);
} else {
    $authurl = new \moodle_url('/availability/condition/shibboleth2fa/auth.php', array('id' => $cm->id));
    echo $OUTPUT->confirm(get_string('login_required', 'availability_shibboleth2fa'), $authurl, $courseurl);
}

echo $OUTPUT->footer();