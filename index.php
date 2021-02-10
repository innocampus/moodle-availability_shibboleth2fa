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

$courseid = required_param('id', PARAM_INT);
$cmid = optional_param('cmid', null, PARAM_INT);
$sectionid = optional_param('sectionid', null, PARAM_INT);

if ($cmid) {
    list($course, $cm) = get_course_and_cm_from_cmid($cmid);
} else {
    $cm = null;
    $course = get_course($courseid);
}

$url = new moodle_url('/availability/condition/shibboleth2fa/index.php', array('id' => $course->id));
if ($cmid) $url->param('cmid', $cmid);
if ($sectionid) $url->param('sectionid', $sectionid);
$PAGE->set_url($url);

require_login($course, false);

$PAGE->set_title(get_string('fulltitle', 'availability_shibboleth2fa'));
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();

$format = course_get_format($course);
$courseurl = $format->get_view_url(null);

$continueurl = null;
$continuetext = null;
if (\availability_shibboleth2fa\condition::is_course_available($course->id, $USER->id)) {

    // Continue to unlocked content.
    if ($cm) {
        $continueurl = new moodle_url("/mod/$cm->modname/view.php", array("id" => $cm->id));
    } else {
        $continueurl = $format->get_view_url($sectionid);
    }

    $continuetext = get_string('login_successful', 'availability_shibboleth2fa');

} else {

    // Continue to 2FA auth page.
    $continueurl = new \moodle_url('/availability/condition/shibboleth2fa/auth.php', array('id' => $course->id));
    if ($cmid) $continueurl->param('cmid', $cmid);
    if ($sectionid) $continueurl->param('sectionid', $sectionid);

    $continuetext = get_string('login_required', 'availability_shibboleth2fa');
}

// Create button ourselves because we do not want to post.
$btn = new single_button($continueurl, get_string('continue'), 'get', true);
echo $OUTPUT->confirm($continuetext, $btn, $courseurl);

echo $OUTPUT->footer();