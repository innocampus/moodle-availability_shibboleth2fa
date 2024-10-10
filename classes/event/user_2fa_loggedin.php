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
 * Definition of a custom event for when a user authenticates with this plugin.
 *
 * @see https://docs.moodle.org/dev/Events_API
 *
 * @package    availability_shibboleth2fa
 * @copyright  2021 Lars Bonczek, innoCampus, TU Berlin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_shibboleth2fa\event;

use coding_exception;
use context_system;
use core\event\base as base_event;
use dml_exception;


/**
 * Event class for when a user authenticates with this plugin.
 *
 * @see https://docs.moodle.org/dev/Events_API
 *
 * @package    availability_shibboleth2fa
 * @copyright  2021 Lars Bonczek, innoCampus, TU Berlin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_2fa_loggedin extends base_event {

    /**
     * Sets basic properties for the event.
     *
     * @throws dml_exception
     */
    protected function init(): void {
        $this->context = context_system::instance();
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'user';
    }

    /**
     * Returns a localised name for the event.
     *
     * @return string
     * @throws coding_exception
     */
    public static function get_name(): string {
        return get_string('eventuser2faloggedin', 'availability_shibboleth2fa');
    }

    /**
     * Returns non-localised event description with id's for admin use only.
     *
     * @return string
     */
    public function get_description(): string {
        return "The user with id '$this->userid' authenticated using a second factor.";
    }

    /**
     * {@inheritDoc}
     */
    public static function get_objectid_mapping(): int {
        return base_event::NOT_MAPPED;
    }

    /**
     * Creates an instance of this event for the specified user and dispatches it right away.
     *
     * @param int|null $userid ID of the user to associate with this event;
     *                         if `null` (default) it will be associated with the global `$USER`.
     * @return static The newly created event. (Its {@see trigger} method will already have been called.)
     * @throws coding_exception
     */
    public static function create_and_trigger(int|null $userid = null): static {
        global $USER;
        if (is_null($userid)) {
            $userid = $USER->id;
        }
        // Because the return type of the parent `create` method is not correctly annotated, we do this here.
        /** @var static $event */
        $event = static::create(['userid' => $userid, 'objectid' => $userid]);
        $event->trigger();
        return $event;
    }
}
