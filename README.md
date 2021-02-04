# moodle-availability_shibboleth2fa

Moodle availability plugin which lets users restrict activities and sections with shibboleth two-factor authentication (2FA).

## Requirements

This plugin requires Moodle 3.9+

## Installation

Install the plugin by copying the code to
availability/condition/shibboleth2fa/.

Example:

    git clone https://github.com/innocampus/moodle-availability_shibboleth2fa.git availability/condition/shibboleth2fa

Shibboleth needs to be configured to protect availability/condition/shibboleth2fa/auth.php in order for this plugin to work.

## Usage

After installation, teachers can select "2FA" from the list of access restrictions when configuring an activity or section.
Students will then be required to authenticate with shibboleth in order to access the resource.
After successfully authenticating once, students can access *any resource* protected by 2FA on your moodle site *until they log out*.

Teachers can add course-wide exceptions for individual users by clicking "manage exceptions" on a protected resource.
Users with exception will never be required to authenticate using 2FA for any protected resource in that course.