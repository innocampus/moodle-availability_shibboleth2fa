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
 * 4
 * {@noinspection PhpUnhandledExceptionInspection}
 */

use availability_shibboleth2fa\condition;
use availability_shibboleth2fa\exception_current_user_selector;
use availability_shibboleth2fa\exception_potential_user_selector;

require(__DIR__ . '/../../../config.php');

global $OUTPUT, $PAGE;

$courseid = required_param('id', PARAM_INT);
$course = get_course($courseid);

$url = new moodle_url('/availability/condition/shibboleth2fa/manage.php', ['id' => $courseid]);
$PAGE->set_url($url);

require_login($course, false);

$context = context_course::instance($course->id);

require_capability('availability/shibboleth2fa:manageexceptions', $context);

$PAGE->set_title(get_string('fulltitle', 'availability_shibboleth2fa'));
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();

// Create the user selector objects.
$options = ['courseid' => $course->id, 'accesscontext' => $context];

$potentialuserselector = new exception_potential_user_selector('addselect', $options);
$currentuserselector = new exception_current_user_selector('removeselect', $options);

// Process add and removes.
if (optional_param('add', false, PARAM_BOOL) && confirm_sesskey()) {
    $userstoassign = $potentialuserselector->get_selected_users();
    if (!empty($userstoassign)) {
        foreach ($userstoassign as $adduser) {
            condition::set_exception(
                courseid: $course->id,
                userid: $adduser->id,
                skipauth: true,
            );
        }
        $potentialuserselector->invalidate_selected_users();
        $currentuserselector->invalidate_selected_users();
    }
}

// Process incoming role unassignments.
if (optional_param('remove', false, PARAM_BOOL) && confirm_sesskey()) {
    $userstounassign = $currentuserselector->get_selected_users();
    if (!empty($userstounassign)) {
        foreach ($userstounassign as $removeuser) {
            condition::set_exception(
                courseid: $course->id,
                userid: $removeuser->id,
                skipauth: false,
            );
        }
        $potentialuserselector->invalidate_selected_users();
        $currentuserselector->invalidate_selected_users();
    }
}

echo $OUTPUT->heading(get_string('user_exceptions', 'availability_shibboleth2fa'));

?>
<form id="assignform" method="post" action="<?php echo $PAGE->url ?>">
    <div>
        <input type="hidden" name="sesskey" value="<?php echo sesskey() ?>" />
        <table class="roleassigntable generaltable generalbox boxaligncenter">
            <tr>
                <td id="existingcell">
                    <p>
                        <label for="removeselect">
                            <?php print_string('users_with_exception', 'availability_shibboleth2fa'); ?>
                        </label>
                    </p>
                    <?php $currentuserselector->display() ?>
                </td>
                <td id="buttonscell">
                    <div id="addcontrols">
                        <input name="add"
                               id="add"
                               type="submit"
                               value="<?php echo $OUTPUT->larrow().'&nbsp;'.get_string('add'); ?>"
                               title="<?php print_string('add'); ?>"
                        />
                        <br />
                    </div>
                    <div id="removecontrols">
                        <input name="remove"
                               id="remove"
                               type="submit"
                               value="<?php echo get_string('remove').'&nbsp;'.$OUTPUT->rarrow(); ?>"
                               title="<?php print_string('remove'); ?>"
                        />
                    </div>
                </td>
                <td id="potentialcell">
                    <p>
                        <label for="addselect">
                            <?php print_string('users_without_exception', 'availability_shibboleth2fa'); ?>
                        </label>
                    </p>
                    <?php $potentialuserselector->display() ?>
                </td>
            </tr>
        </table>
    </div>
</form>
<?php

echo $OUTPUT->footer();
