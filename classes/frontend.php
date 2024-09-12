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
 * Part of the required availability condition subsystem implementation.
 *
 * @see https://moodledev.io/docs/4.4/apis/plugintypes/availability#classesfrontendphp
 *
 * @package    availability_shibboleth2fa
 * @copyright  2021 Lars Bonczek, innoCampus, TU Berlin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_shibboleth2fa;

use cm_info;
use coding_exception;
use context_course;
use core_availability\frontend as abstract_frontend;
use section_info;
use stdClass;

/**
 * Class for front-end (editing form) functionality.
 *
 * @see https://moodledev.io/docs/4.4/apis/plugintypes/availability#classesfrontendphp
 *
 * @package    availability_shibboleth2fa
 * @copyright  2021 Lars Bonczek, innoCampus, TU Berlin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class frontend extends abstract_frontend {

    /**
     * Returns a list of language strings to pass to the javascript.
     *
     * @return string[]
     */
    protected function get_javascript_strings(): array {
        return ['fulltitle'];
    }

    /**
     * Check if the condition can be added.
     * Can only be added if the user has the appropriate capability.
     *
     * @param stdClass $course
     * @param cm_info|null $cm (optional)
     * @param section_info|null $section (optional)
     * @return bool
     * @throws coding_exception
     */
    protected function allow_add($course, cm_info|null $cm = null, section_info|null $section = null): bool {
        $context = $cm ? $cm->context : context_course::instance($course->id);
        return has_capability('availability/shibboleth2fa:addinstance', $context);
    }
}
