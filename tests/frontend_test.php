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
 * Definition of the {@see frontend_test} class.
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
use ReflectionClass;
use ReflectionException;

/**
 * Unit tests for the {@see frontend} class.
 *
 * @package    availability_shibboleth2fa
 * @copyright  2026 Daniel Fainberg <d.fainberg@tu-berlin.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(frontend::class)]
final class frontend_test extends advanced_testcase {
    /**
     * Tests that {@see frontend::get_javascript_strings} declares the strings required by the form JavaScript.
     *
     * @throws ReflectionException
     * {@noinspection PhpUndefinedMethodInspection}
     */
    public function test_get_javascript_strings(): void {
        $frontend = (new ReflectionClass(frontend::class))->newInstanceWithoutConstructor();
        // Simple closure binding workaround to test the protected method.
        $closure = function (): array {
            return $this->get_javascript_strings();
        };
        self::assertSame(['fulltitle'], $closure->call($frontend));
    }

    /**
     * Tests that {@see frontend::allow_add} is gated behind the `addinstance` capability.
     *
     * @throws ReflectionException
     * {@noinspection PhpUndefinedMethodInspection}
     */
    public function test_allow_add_requires_capability(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $frontend = (new ReflectionClass(frontend::class))->newInstanceWithoutConstructor();
        // Simple closure binding workaround to test the protected method.
        $closure = function (object $course): bool {
            return $this->allow_add($course);
        };

        // The admin holds the capability.
        $this->setAdminUser();
        self::assertTrue($closure->call($frontend, $course));

        // A plain student in the course does not.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);
        self::assertFalse($closure->call($frontend, $course));
    }
}
