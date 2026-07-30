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
 * Definition of the {@see user_exception_selector_test} class.
 *
 * @package    availability_shibboleth2fa
 * @copyright  2026 Daniel Fainberg <d.fainberg@tu-berlin.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * {@noinspection PhpIllegalPsrClassPathInspection, PhpUnhandledExceptionInspection}
 */

namespace availability_shibboleth2fa;

use advanced_testcase;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionProperty;

/**
 * Unit tests for the {@see user_exception_selector} class.
 *
 * @package    availability_shibboleth2fa
 * @copyright  2026 Daniel Fainberg <d.fainberg@tu-berlin.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(user_exception_selector::class)]
final class user_exception_selector_test extends advanced_testcase {
    /**
     * Extracts the found user IDs from a {@see user_exception_selector::find_users} result.
     *
     * @param array $result Return value of {@see user_exception_selector::find_users}.
     * @return int[] The IDs of the users in the (single) option group, or an empty array if there were none.
     */
    private function found_ids(array $result): array {
        return $result ? array_keys(reset($result)) : [];
    }

    /**
     * Tests that {@see user_exception_selector::get_options} carries the course and mode needed to recreate the selector.
     *
     * {@noinspection PhpUndefinedMethodInspection}
     */
    public function test_get_options(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $selector = user_exception_selector::instance($course->id, skipauth: true);
        // Simple closure binding workaround to test the protected method.
        $closure = function (): array {
            return $this->get_options();
        };

        $options = $closure->call($selector);
        self::assertEquals($course->id, $options['courseid']);
        self::assertTrue($options['skipauth']);
    }

    /**
     * Tests that a selector in "add" mode finds enrolled users who currently have no exception.
     */
    public function test_find_users_without_exception(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $exempt = $this->getDataGenerator()->create_and_enrol($course);
        $regular = $this->getDataGenerator()->create_and_enrol($course);
        condition::set_exception($course->id, $exempt->id, skipauth: true);

        $found = $this->found_ids(user_exception_selector::instance($course->id, skipauth: false)->find_users(''));

        self::assertContainsEquals($regular->id, $found);
        self::assertNotContainsEquals($exempt->id, $found);
    }

    /**
     * Tests that a selector in "remove" mode finds enrolled users who currently have an exception.
     */
    public function test_find_users_with_exception(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $exempt = $this->getDataGenerator()->create_and_enrol($course);
        $regular = $this->getDataGenerator()->create_and_enrol($course);
        condition::set_exception($course->id, $exempt->id, skipauth: true);

        $found = $this->found_ids(user_exception_selector::instance($course->id, skipauth: true)->find_users(''));

        self::assertContainsEquals($exempt->id, $found);
        self::assertNotContainsEquals($regular->id, $found);
    }

    /**
     * Tests that {@see user_exception_selector::find_users} labels and filters results when given a search string.
     */
    public function test_find_users_with_search(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $wanted = $this->getDataGenerator()->create_and_enrol($course, userparams: ['lastname' => 'Beeblebrox']);
        $this->getDataGenerator()->create_and_enrol($course, userparams: ['lastname' => 'Dent']);

        $result = user_exception_selector::instance($course->id, skipauth: false)->find_users('Beeblebrox');

        $groupname = get_string('users_without_exception_matching', 'availability_shibboleth2fa', 'Beeblebrox');
        self::assertArrayHasKey($groupname, $result);
        self::assertEquals([$wanted->id], $this->found_ids($result));
    }

    /**
     * Tests that {@see user_exception_selector::find_users} returns an empty array when nothing matches the search.
     */
    public function test_find_users_returns_empty_when_no_matches(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_and_enrol($course);

        $result = user_exception_selector::instance($course->id, skipauth: false)->find_users('nonexistentsearchxyz');

        self::assertSame([], $result);
    }

    /**
     * Tests that {@see user_exception_selector::find_users} returns the "too many results" message once the page limit is exceeded.
     */
    public function test_find_users_returns_too_many_results_message(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_and_enrol($course);
        $this->getDataGenerator()->create_and_enrol($course);
        $selector = user_exception_selector::instance($course->id, skipauth: false);
        // Force the page limit below the number of matches so the count guard trips.
        (new ReflectionProperty($selector, 'maxusersperpage'))->setValue($selector, 1);

        $result = $selector->find_users('');

        self::assertArrayHasKey(get_string('pleaseusesearch'), $result);
    }

    /**
     * Tests that "add" mode grants an exception to the selected users.
     */
    public function test_set_exceptions_adds_for_selected_users(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course);
        $selector = user_exception_selector::instance($course->id, skipauth: false);
        $_POST['addselect'] = [$user->id];

        $modified = $selector->set_exceptions_for_selected_users();

        self::assertEquals([$user->id], array_keys($modified));
        self::assertTrue(condition::get_exception($course->id, $user->id));
    }

    /**
     * Tests that "remove" mode revokes the exception from the selected users.
     */
    public function test_set_exceptions_removes_for_selected_users(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course);
        condition::set_exception($course->id, $user->id, skipauth: true);
        $selector = user_exception_selector::instance($course->id, skipauth: true);
        $_POST['removeselect'] = [$user->id];

        $modified = $selector->set_exceptions_for_selected_users();

        self::assertEquals([$user->id], array_keys($modified));
        self::assertFalse(condition::get_exception($course->id, $user->id));
    }
}
