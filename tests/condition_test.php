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
 * Definition of the {@see condition_test} class.
 *
 * @package    availability_shibboleth2fa
 * @copyright  2026 Daniel Fainberg <d.fainberg@tu-berlin.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * {@noinspection PhpIllegalPsrClassPathInspection, PhpUnhandledExceptionInspection}
 */

namespace availability_shibboleth2fa;

use advanced_testcase;
use availability_shibboleth2fa\event\user_2fa_loggedin;
use core_availability\info_module;
use core_availability\info_section;
use core_availability\mock_info;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionClass;

/**
 * Unit tests for the {@see condition} class.
 *
 * @package    availability_shibboleth2fa
 * @copyright  2026 Daniel Fainberg <d.fainberg@tu-berlin.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(condition::class)]
final class condition_test extends advanced_testcase {
    /**
     * Ensures that relevant files are loaded.
     */
    #[\Override]
    public static function setupBeforeClass(): void {
        global $CFG;
        parent::setupBeforeClass();
        require_once("$CFG->dirroot/availability/tests/fixtures/mock_info.php");
    }

    /**
     * Resets the condition class's static exception cache so that it cannot leak between tests.
     */
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $reflection = new ReflectionClass(condition::class);
        $reflection->getProperty('exceptioncacheuser')->setValue(null, null);
        $reflection->getProperty('exceptioncache')->setValue(null, []);
    }

    /**
     * Tests that {@see condition::save} returns the expected structure.
     */
    public function test_save(): void {
        $condition = new condition();
        self::assertEquals((object) ['type' => 'shibboleth2fa'], $condition->save());
    }

    /**
     * Tests that {@see condition::get_debug_string} is empty (i.e. the condition takes no parameters).
     *
     * {@noinspection PhpUndefinedMethodInspection}
     */
    public function test_get_debug_string(): void {
        $condition = new condition();
        // Simple closure binding workaround to test the protected method.
        $closure = function (): string {
            return $this->get_debug_string();
        };
        self::assertSame('', $closure->call($condition));
    }

    /**
     * Tests that {@see condition::is_available} delegates to {@see condition::is_course_available} and honours `$not`.
     */
    public function test_is_available(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $info = new mock_info($course, $user->id);
        $condition = new condition();

        // Without an exception or authentication, the condition is not met.
        self::assertFalse($condition->is_available(not: false, info: $info, grabthelot: false, userid: $user->id));
        self::assertTrue($condition->is_available(not: true, info: $info, grabthelot: false, userid: $user->id));

        // With an exception, the condition is met (and `$not` inverts it).
        condition::set_exception($course->id, $user->id, skipauth: true);
        self::assertTrue($condition->is_available(not: false, info: $info, grabthelot: false, userid: $user->id));
        self::assertFalse($condition->is_available(not: true, info: $info, grabthelot: false, userid: $user->id));
    }

    /**
     * Tests that {@see condition::is_course_available} treats the authenticated session as available.
     */
    public function test_is_course_available_uses_authenticated_session(): void {
        global $USER;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Not authenticated and no exception: unavailable.
        self::assertFalse(condition::is_course_available($course->id, $user->id));

        // Authenticated session flag makes it available for the current user only.
        $USER->shibbolethcondauth = true;
        self::assertTrue(condition::is_course_available($course->id, $user->id));

        // The session flag must not leak to other users (they fall back to the exception check).
        $other = $this->getDataGenerator()->create_user();
        self::assertFalse(condition::is_course_available($course->id, $other->id));
    }

    /**
     * Tests inserting, updating, and reading exceptions via {@see condition::set_exception} and {@see condition::get_exception}.
     */
    public function test_set_and_get_exception(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        self::assertFalse(condition::get_exception($course->id, $user->id));

        // Insert.
        condition::set_exception($course->id, $user->id, skipauth: true);
        self::assertTrue(condition::get_exception($course->id, $user->id));

        // Update the same record (no duplicate row) toggling the flag off.
        condition::set_exception($course->id, $user->id, skipauth: false);
        self::assertFalse(condition::get_exception($course->id, $user->id));
    }

    /**
     * Tests that {@see condition::preload_exceptions} caches reads and that {@see condition::set_exception} invalidates them.
     */
    public function test_exception_cache_is_invalidated_on_write(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $condition = new condition();

        condition::set_exception($course->id, $user->id, skipauth: true);

        // Trigger the preload path to warm the cache for this user.
        $condition->is_available(
            not: false,
            info: new mock_info($course, $user->id),
            grabthelot: true,
            userid: $user->id,
        );

        // Mutate the actual DB row. A cached read must still return the stale value.
        // Proves that reads are served from the cache rather than the DB.
        $DB->set_field(
            table: 'availability_shibboleth2fa_e',
            newfield: 'skipauth',
            newvalue: 0,
            conditions: ['courseid' => $course->id, 'userid' => $user->id],
        );
        self::assertTrue(condition::get_exception($course->id, $user->id));

        // A write for the cached user must invalidate the cache, so the next read reflects the DB.
        condition::set_exception($course->id, $user->id, skipauth: false);
        self::assertFalse(condition::get_exception($course->id, $user->id));
    }

    /**
     * Tests that {@see condition::set_authenticated} sets the session flag and triggers the event exactly once.
     */
    public function test_set_authenticated(): void {
        global $USER;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $sink = $this->redirectEvents();
        condition::set_authenticated();
        self::assertTrue($USER->shibbolethcondauth);

        // A second call must not trigger another event, as the user is already authenticated.
        condition::set_authenticated();

        $events = array_filter($sink->get_events(), fn ($event): bool => $event instanceof user_2fa_loggedin);
        self::assertCount(1, $events);
        $event = reset($events);
        self::assertEquals($user->id, $event->userid);
    }

    /**
     * Tests the `$not` branch of {@see condition::get_description}.
     */
    public function test_get_description_not(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $condition = new condition();

        $description = $condition->get_description(full: false, not: true, info: new mock_info($course, $user->id));
        self::assertEquals(get_string('requires_no2fa', 'availability_shibboleth2fa'), $description);
    }

    /**
     * Tests that {@see condition::get_description} links to the authentication page when the condition is not yet met.
     */
    public function test_get_description_links_when_unavailable(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $condition = new condition();

        $description = $condition->get_description(full: false, not: false, info: new mock_info($course, $user->id));
        self::assertStringContainsString(get_string('requires_2fa', 'availability_shibboleth2fa'), $description);
        self::assertStringContainsString('/availability/condition/shibboleth2fa/index.php', $description);
    }

    /**
     * Tests that {@see condition::get_description} passes the course module ID for an {@see info_module}.
     */
    public function test_get_description_includes_cmid_for_module(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $cm = get_fast_modinfo($course)->get_cm($page->cmid);
        $condition = new condition();

        $description = $condition->get_description(full: false, not: false, info: new info_module($cm));
        self::assertStringContainsString('cmid=' . $cm->id, $description);
    }

    /**
     * Tests that {@see condition::get_description} passes the section number for an {@see info_section}.
     */
    public function test_get_description_includes_sectionid_for_section(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $section = get_fast_modinfo($course)->get_section_info(0);
        $condition = new condition();

        $description = $condition->get_description(full: false, not: false, info: new info_section($section));
        self::assertStringContainsString('sectionid=', $description);
    }

    /**
     * Tests that {@see condition::get_description} shows the manage-exceptions link to users with the capability.
     */
    public function test_get_description_shows_manage_link_with_capability(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->setAdminUser();
        $condition = new condition();

        $description = $condition->get_description(full: true, not: false, info: new mock_info($course, get_admin()->id));
        self::assertStringContainsString('/availability/condition/shibboleth2fa/manage.php', $description);
        self::assertStringContainsString(get_string('manage_exceptions', 'availability_shibboleth2fa'), $description);
    }
}
