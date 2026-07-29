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

namespace availability_shibboleth2fa\event;

use core\event\course_deleted;
use core\event\user_deleted;
use core\event\user_enrolment_deleted;
use dml_exception;

/**
 * Provides callbacks for events observed by the plugin.
 *
 * @link https://docs.moodle.org/dev/Events_API#Event_observers Events API documentation
 *
 * @package    availability_shibboleth2fa
 * @copyright  Daniel Fainberg <d.fainberg@tu-berlin.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class observer {
    /**
     * Callback to remove possible exceptions made for a user or course.
     *
     * @param course_deleted|user_deleted|user_enrolment_deleted $event Event instance in question referencing a course and/or user.
     * @throws dml_exception
     */
    public static function delete_associated_exceptions(course_deleted|user_deleted|user_enrolment_deleted $event): void {
        global $DB;
        $params = match ($event::class) {
            course_deleted::class         => ['courseid' => $event->courseid],
            user_deleted::class           => ['userid' => $event->relateduserid],
            user_enrolment_deleted::class => ['courseid' => $event->courseid, 'userid' => $event->relateduserid],
        };
        $DB->delete_records('availability_shibboleth2fa_e', $params);
    }
}
