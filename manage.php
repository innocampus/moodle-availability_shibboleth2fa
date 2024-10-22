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
 * Page for managing user exceptions from the 2FA requirement in a course.
 *
 * @package      availability_shibboleth2fa
 * @copyright    2021 Lars Bonczek, innoCampus, TU Berlin
 * @license      http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * {@noinspection PhpUnhandledExceptionInspection}
 */

use availability_shibboleth2fa\user_exception_selector;

require(__DIR__ . '/../../../config.php');

global $OUTPUT, $PAGE;

$courseid = required_param('id', PARAM_INT);
$course = get_course($courseid);

$url = new moodle_url('/availability/condition/shibboleth2fa/manage.php', ['id' => $courseid]);
$PAGE->set_url($url);

require_login($course, false);

require_capability('availability/shibboleth2fa:manageexceptions', context_course::instance($courseid));

$PAGE->set_title(get_string('fulltitle', 'availability_shibboleth2fa'));
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();

$potentialuserselector = user_exception_selector::instance($courseid, skipauth: false);
$currentuserselector = user_exception_selector::instance($courseid, skipauth: true);

// Add/remove user exceptions.
// Checking which of the two submit buttons was pressed (`add` or `remove`) ensures only one of the two corresponding actions will
// be performed following the form submission. Even if users in both selectors had been selected, when the `add` button was pressed,
// those selected in the `$currentuserselector` will not have their exceptions removed, until the `remove` button is subsequently
// pressed as well, and vice versa.
if (optional_param('add', false, PARAM_BOOL) && confirm_sesskey()) {
    $potentialuserselector->set_exceptions_for_selected_users();
}
if (optional_param('remove', false, PARAM_BOOL) && confirm_sesskey()) {
    $currentuserselector->set_exceptions_for_selected_users();
}

echo $OUTPUT->heading(get_string('user_exceptions', 'availability_shibboleth2fa'));
$templatecontext = [
    'actionurl'             => $PAGE->url,
    'sesskey'               => sesskey(),
    'currentuserselector'   => $currentuserselector->display(return: true),
    'potentialuserselector' => $potentialuserselector->display(return: true),
    'larrow'                => $OUTPUT->larrow(),
    'rarrow'                => $OUTPUT->rarrow(),
];
echo $OUTPUT->render_from_template('availability_shibboleth2fa/manage_form', $templatecontext);
echo $OUTPUT->footer();
