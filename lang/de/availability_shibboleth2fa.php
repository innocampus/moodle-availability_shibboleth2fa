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
 * @package    availability_shibboleth2fa
 * @copyright  2021 Lars Bonczek, innoCampus, TU Berlin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['title'] = '2FA';
$string['fulltitle'] = 'Zwei-Faktor-Authentifizierung';
$string['pluginname'] = 'Voraussetzung: Zwei-Faktor-Authentifizierung';
$string['description'] = 'Erfordere eine Zwei-Faktor-Authentifizierung.';
$string['shibboleth2fa:addinstance'] = 'Zwei-Faktor-Authentifizierung als Voraussetzung für Aktivitäten hinzufügen';
$string['shibboleth2fa:manageexceptions'] = 'Nutzerspezifische Ausnahmen für die Zwei-Faktor-Authentifizierung verwalten';
$string['requires_2fa'] = 'Sie authentifizieren sich mit einem zweiten Faktor';
$string['requires_no2fa'] = 'Sie haben sich nicht mit einem zweiten Faktor authentifiziert';
$string['login_required'] = 'Sie müssen sich mit einem zweiten Faktor authentifizieren, um auf diesen Inhalt zugreifen zu können. Wenn Sie fortfahren, werden Sie zur Anmeldeseite weitergeleitet.';
$string['login_successful'] = 'Sie haben sich erfolgreich mit einem zweiten Faktor authentifiziert. Sie können nun auf den geschützten Inhalt zugreifen.';
$string['user_exceptions'] = 'Nutzerspezifische kursweite Ausnahmen';
$string['manage_exceptions'] = 'Ausnahmen verwalten';
$string['users_with_exception'] = 'Nutzer/innen mit Ausnahme';
$string['users_without_exception'] = 'Nutzer/innen ohne Ausnahme';
$string['users_with_exception_matching'] = 'Passende Nutzer/innen mit Ausnahme';
$string['users_without_exception_matching'] = 'Passende Nutzer/innen ohne Ausnahme';
$string['eventuser2faloggedin'] = 'Nutzer/in hat sich mit einem zweiten Faktor authentifiziert';
$string['username_override_description'] = 'Überschreibt den Namen der Shibboleth Webserver-Umgebungsvariable, die mit dem Moodle-Benutzername verglichen werden soll. Verwendet standardmäßig den in auth_shibboleth festgelegten Wert, falls nicht gesetzt.';
$string['login_failed'] = 'Der Authentifizierungs-Vorgang ist fehlgeschlagen. Bitte kontaktieren Sie Ihren Server-Administrator oder versuchen Sie es später erneut.';
$string['login_failed_wrong_user'] = 'Sie haben sich mit dem falschen Benutzername authentifiziert. Bitte melden Sie sich ab und versuchen Sie es erneut.';