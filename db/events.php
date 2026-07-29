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
 * Event observer definitions.
 *
 * @link https://docs.moodle.org/dev/Events_API#Event_observers Events API documentation
 *
 * @package    availability_shibboleth2fa
 * @copyright  2021 Lars Bonczek, innoCampus, TU Berlin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use availability_shibboleth2fa\event\observer;
use core\event\course_deleted;
use core\event\user_deleted;
use core\event\user_enrolment_deleted;

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => course_deleted::class,
        'callback'  => [observer::class, 'delete_associated_exceptions'],
    ],
    [
        'eventname' => user_deleted::class,
        'callback'  => [observer::class, 'delete_associated_exceptions'],
    ],
    [
        'eventname' => user_enrolment_deleted::class,
        'callback'  => [observer::class, 'delete_associated_exceptions'],
    ],
];
