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
 * Definition of the {@see user_2fa_loggedin_test} class.
 *
 * @package    availability_shibboleth2fa
 * @copyright  2026 Daniel Fainberg <d.fainberg@tu-berlin.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * {@noinspection PhpIllegalPsrClassPathInspection, PhpUnhandledExceptionInspection}
 */

namespace availability_shibboleth2fa\event;

use advanced_testcase;
use core\context\system as context_system;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for the {@see user_2fa_loggedin} event class.
 *
 * @package    availability_shibboleth2fa
 * @copyright  2026 Daniel Fainberg <d.fainberg@tu-berlin.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(user_2fa_loggedin::class)]
final class user_2fa_loggedin_test extends advanced_testcase {
    /**
     * Tests that {@see user_2fa_loggedin::create_and_trigger} dispatches a correctly populated event.
     */
    public function test_create_and_trigger(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $sink = $this->redirectEvents();
        $event = user_2fa_loggedin::create_and_trigger($user->id);
        $events = $sink->get_events();

        self::assertCount(1, $events);
        self::assertInstanceOf(user_2fa_loggedin::class, $events[0]);
        self::assertEquals($user->id, $event->userid);
        self::assertEquals($user->id, $event->objectid);
        self::assertEquals(context_system::instance(), $event->get_context());
    }

    /**
     * Tests that {@see user_2fa_loggedin::create_and_trigger} falls back to the global user when no ID is given.
     */
    public function test_create_and_trigger_defaults_to_current_user(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $sink = $this->redirectEvents();
        user_2fa_loggedin::create_and_trigger();
        $events = $sink->get_events();

        self::assertCount(1, $events);
        self::assertEquals($user->id, $events[0]->userid);
    }

    /**
     * Tests that {@see user_2fa_loggedin::get_objectid_mapping} declares the object ID as not mapped.
     */
    public function test_get_objectid_mapping(): void {
        self::assertSame(user_2fa_loggedin::NOT_MAPPED, user_2fa_loggedin::get_objectid_mapping());
    }

    /**
     * Tests that {@see user_2fa_loggedin::get_name} returns the localised event name.
     */
    public function test_get_name(): void {
        self::assertEquals(
            get_string('eventuser2faloggedin', 'availability_shibboleth2fa'),
            user_2fa_loggedin::get_name(),
        );
    }

    /**
     * Tests that {@see user_2fa_loggedin::get_description} mentions the acting user.
     */
    public function test_get_description(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $sink = $this->redirectEvents();
        $event = user_2fa_loggedin::create_and_trigger($user->id);
        $sink->close();

        self::assertStringContainsString("id '$user->id'", $event->get_description());
    }
}
