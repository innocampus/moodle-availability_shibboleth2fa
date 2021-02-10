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

$course = get_course($courseid);

$url = new moodle_url('/availability/condition/shibboleth2fa/auth.php', array('id' => $courseid));
if ($cmid) $url->param('cmid', $cmid);
if ($sectionid) $url->param('sectionid', $sectionid);
$PAGE->set_url($url);

require_login($course, false);

// Fetch shibboleth username.
$userattribute = get_config('availability_shibboleth2fa', 'user_attribute_override');
$username = null;
if ($userattribute) {
    // Fetch server environment variable directly.
    if (!empty($_SERVER[$userattribute])) {
        $username = $_SERVER[$userattribute];
    }
} else {
    // Use auth_shibboleth to fetch shibboleth username.
    $plugin = get_auth_plugin('shibboleth');
    if ($plugin) {
        // Do not pass a username here to prevent that value being returned (auth_shibboleth doesn't use it, anyway).
        $userinfo = $plugin->get_userinfo(null);
        if ($userinfo && isset($userinfo['username'])) {
            $username = $userinfo['username'];
        }
    }
}

// Verify username.
$errormsg = null;
if ($username) {
    if (strtolower($username) == strtolower($USER->username)) {
        // User authenticated successfully.
        \availability_shibboleth2fa\condition::set_authenticated();
    } else {
        // Wrong user authenticated.
        $errormsg = get_string('login_failed_wrong_user', 'availability_shibboleth2fa');
    }
} else {
    // No shibboleth login.
    $errormsg = get_string('login_failed', 'availability_shibboleth2fa');
}

$redirecturl = new \moodle_url('/availability/condition/shibboleth2fa/index.php', array('id' => $courseid));
if ($cmid) $redirecturl->param('cmid', $cmid);
if ($sectionid) $redirecturl->param('sectionid', $sectionid);

if ($errormsg) {
    // Display error before redirecting.
    $PAGE->set_title(get_string('fulltitle', 'availability_shibboleth2fa'));
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();
    echo $OUTPUT->notification($errormsg, 'error');
    echo $OUTPUT->continue_button($redirecturl);
    echo $OUTPUT->footer();
} else {
    redirect($redirecturl);
}