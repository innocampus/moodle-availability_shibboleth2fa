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
 * Definition of the {@see observer_test} class.
 *
 * @package    availability_shibboleth2fa
 * @copyright  2026 Daniel Fainberg <d.fainberg@tu-berlin.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * {@noinspection PhpIllegalPsrClassPathInspection, PhpUnhandledExceptionInspection}
 */

namespace availability_shibboleth2fa\event;

use advanced_testcase;
use availability_shibboleth2fa\condition;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for the {@see observer} class (and the wiring in db/events.php).
 *
 * @package    availability_shibboleth2fa
 * @copyright  2026 Daniel Fainberg <d.fainberg@tu-berlin.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(observer::class)]
final class observer_test extends advanced_testcase {
    /**
     * Tests that deleting a course removes any exceptions belonging to it.
     */
    public function test_course_deletion_removes_exceptions(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        condition::set_exception($course->id, $user->id, skipauth: true);

        delete_course($course, showfeedback: false);

        self::assertFalse($DB->record_exists('availability_shibboleth2fa_e', ['courseid' => $course->id]));
    }

    /**
     * Tests that deleting a user removes any exceptions belonging to them.
     */
    public function test_user_deletion_removes_exceptions(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        condition::set_exception($course->id, $user->id, skipauth: true);

        delete_user($user);

        self::assertFalse($DB->record_exists('availability_shibboleth2fa_e', ['userid' => $user->id]));
    }

    /**
     * Tests that unenrolling a user removes their exception in the corresponding course.
     */
    public function test_unenrolment_removes_exceptions(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        condition::set_exception($course->id, $user->id, skipauth: true);

        $plugin = enrol_get_plugin('manual');
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], strictness: MUST_EXIST);
        $plugin->unenrol_user($instance, $user->id);

        self::assertFalse($DB->record_exists('availability_shibboleth2fa_e', ['courseid' => $course->id, 'userid' => $user->id]));
    }
}
