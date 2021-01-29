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

defined('MOODLE_INTERNAL') || die();

$string['title'] = '2FA';
$string['fulltitle'] = 'Two-factor authentication';
$string['pluginname'] = 'Restriction by two-factor authentication';
$string['description'] = 'Require students to authenticate using a second factor.';
$string['shibboleth2fa:addinstance'] = 'Add two-factor authentication conditions to activities';
$string['shibboleth2fa:manageexceptions'] = 'Add and remove per-user exceptions for two-factor authentication conditions';
$string['requires_2fa'] = 'You authenticate using a second factor';
$string['requires_no2fa'] = 'You have not authenticated using a second factor';
$string['login_required'] = 'You need to authenticate using a second factor to access this content. If you continue, you will be taken to the login prompt.';
$string['login_successful'] = 'You have successfully authenticated using a second factor. You can now proceed to the requested content.';
$string['user_exceptions'] = 'Per-user course-wide exceptions';
$string['manage_exceptions'] = 'manage exceptions';
$string['users_with_exception'] = 'Users with exception';
$string['users_without_exception'] = 'Users without exception';
$string['users_with_exception_matching'] = 'Users with exception matching';
$string['users_without_exception_matching'] = 'Users without exception matching';
$string['eventuser2faloggedin'] = 'User authenticated using second factor';