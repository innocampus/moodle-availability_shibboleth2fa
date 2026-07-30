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
 * Definition of the {@see provider_test} class.
 *
 * @package    availability_shibboleth2fa
 * @copyright  2026 Daniel Fainberg <d.fainberg@tu-berlin.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * {@noinspection PhpIllegalPsrClassPathInspection, PhpUnhandledExceptionInspection}
 */

namespace availability_shibboleth2fa\privacy;

use advanced_testcase;
use availability_shibboleth2fa\condition;
use core\context\course as context_course;
use core\context\system as context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\request\content_writer;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for the privacy {@see provider} class.
 *
 * @package    availability_shibboleth2fa
 * @copyright  2026 Daniel Fainberg <d.fainberg@tu-berlin.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(provider::class)]
final class provider_test extends advanced_testcase {
    /** @var string The frankenstyle component name. */
    private const string COMPONENT = 'availability_shibboleth2fa';

    /**
     * Tests that {@see provider::get_metadata} describes the exception table.
     */
    public function test_get_metadata(): void {
        $collection = provider::get_metadata(new collection(self::COMPONENT));
        $items = $collection->get_collection();
        self::assertCount(1, $items);
        self::assertEquals('availability_shibboleth2fa_e', $items[0]->get_name());
    }

    /**
     * Tests that {@see provider::get_contexts_for_userid} returns the course contexts where the user has an exception.
     */
    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        condition::set_exception($course->id, $user->id, skipauth: true);

        $contextids = provider::get_contexts_for_userid($user->id)->get_contextids();
        self::assertEqualsCanonicalizing([context_course::instance($course->id)->id], $contextids);
        self::assertNotContains(context_course::instance($other->id)->id, $contextids);
    }

    /**
     * Tests that {@see provider::get_users_in_context} returns the users who have an exception in the given course.
     */
    public function test_get_users_in_context(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        condition::set_exception($course->id, $user->id, skipauth: true);

        $userlist = new userlist(context_course::instance($course->id), self::COMPONENT);
        provider::get_users_in_context($userlist);

        self::assertEqualsCanonicalizing([$user->id], $userlist->get_userids());
        self::assertNotContains($other->id, $userlist->get_userids());
    }

    /**
     * Tests that {@see provider::export_user_data} writes the user's exception into the export.
     */
    public function test_export_user_data(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        condition::set_exception($course->id, $user->id, skipauth: true);
        $context = context_course::instance($course->id);

        provider::export_user_data(new approved_contextlist($user, self::COMPONENT, [$context->id]));

        /** @var content_writer $writer */
        $writer = writer::with_context($context);
        self::assertTrue($writer->has_any_data());
        $data = $writer->get_data([get_string('pluginname', self::COMPONENT)]);
        self::assertEquals($course->id, $data->courseid);
        self::assertEquals($user->id, $data->userid);
    }

    /**
     * Tests that {@see provider::export_user_data} exports nothing when the approved context list is empty.
     */
    public function test_export_user_data_with_empty_contextlist(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        condition::set_exception($course->id, $user->id, skipauth: true);
        $context = context_course::instance($course->id);
        writer::reset();

        provider::export_user_data(new approved_contextlist($user, self::COMPONENT, []));

        /** @var content_writer $writer */
        $writer = writer::with_context($context);
        self::assertFalse($writer->has_any_data());
    }

    /**
     * Tests that {@see provider::get_users_in_context} ignores contexts that are not course contexts.
     */
    public function test_get_users_in_context_ignores_non_course_context(): void {
        $this->resetAfterTest();
        $userlist = new userlist(context_system::instance(), self::COMPONENT);
        provider::get_users_in_context($userlist);
        self::assertCount(0, $userlist->get_userids());
    }

    /**
     * Tests that {@see provider::delete_data_for_users} ignores contexts that are not course contexts.
     */
    public function test_delete_data_for_users_ignores_non_course_context(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        condition::set_exception($course->id, $user->id, skipauth: true);

        provider::delete_data_for_users(new approved_userlist(context_system::instance(), self::COMPONENT, [$user->id]));

        self::assertTrue($DB->record_exists('availability_shibboleth2fa_e', ['courseid' => $course->id, 'userid' => $user->id]));
    }

    /**
     * Tests that {@see provider::delete_data_for_all_users_in_context} removes every exception in the course.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        condition::set_exception($course->id, $user1->id, skipauth: true);
        condition::set_exception($course->id, $user2->id, skipauth: true);

        provider::delete_data_for_all_users_in_context(context_course::instance($course->id));

        self::assertFalse($DB->record_exists('availability_shibboleth2fa_e', ['courseid' => $course->id]));
    }

    /**
     * Tests that {@see provider::delete_data_for_user} removes only that user's exceptions in the approved contexts.
     */
    public function test_delete_data_for_user(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        condition::set_exception($course->id, $user->id, skipauth: true);
        condition::set_exception($course->id, $other->id, skipauth: true);
        $context = context_course::instance($course->id);

        provider::delete_data_for_user(new approved_contextlist($user, self::COMPONENT, [$context->id]));

        self::assertFalse($DB->record_exists('availability_shibboleth2fa_e', ['courseid' => $course->id, 'userid' => $user->id]));
        self::assertTrue($DB->record_exists('availability_shibboleth2fa_e', ['courseid' => $course->id, 'userid' => $other->id]));
    }

    /**
     * Tests that {@see provider::delete_data_for_users} removes only the approved users' exceptions.
     */
    public function test_delete_data_for_users(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        condition::set_exception($course->id, $user->id, skipauth: true);
        condition::set_exception($course->id, $other->id, skipauth: true);
        $context = context_course::instance($course->id);

        provider::delete_data_for_users(new approved_userlist($context, self::COMPONENT, [$user->id]));

        self::assertFalse($DB->record_exists('availability_shibboleth2fa_e', ['courseid' => $course->id, 'userid' => $user->id]));
        self::assertTrue($DB->record_exists('availability_shibboleth2fa_e', ['courseid' => $course->id, 'userid' => $other->id]));
    }
}
