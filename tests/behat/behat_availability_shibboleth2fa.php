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

use availability_shibboleth2fa\condition;
use Behat\Step\Given;

require_once(__DIR__ . '/../../../../../lib/behat/behat_base.php');

/**
 * Behat step definitions.
 *
 * @package    availability_shibboleth2fa
 * @copyright  2026 Daniel Fainberg <d.fainberg@tu-berlin.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * {@noinspection PhpIllegalPsrClassPathInspection, PhpUnused}
 */
class behat_availability_shibboleth2fa extends behat_base {
    /**
     * Sets the `behat_shibuser` config value to the specified username.
     *
     * This is used to simulate the successful Shibboleth authentication of a user.
     *
     * @param string $username Username to consider successfully authenticated.
     *
     * {@noinspection PhpUnused}
     */
    #[Given('the Shibboleth second factor identifies me as :username')]
    public function the_shibboleth_second_factor_identifies_me_as(string $username): void {
        set_config('behat_shibuser', $username, 'availability_shibboleth2fa');
    }

    /**
     * Creates a per-course exception that lets the given user skip second-factor authentication.
     *
     * @param string $username Username of the user the exception is for.
     * @param string $shortname Short name of the course the exception applies to.
     * @throws dml_exception
     *
     * {@noinspection PhpUnused}
     */
    #[Given('a Shibboleth second-factor exception exists for user :username in course :shortname')]
    public function a_shibboleth_exception_exists(string $username, string $shortname): void {
        global $DB;
        $userid = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $courseid = $DB->get_field('course', 'id', ['shortname' => $shortname], MUST_EXIST);
        condition::set_exception($courseid, $userid, skipauth: true);
    }
}
