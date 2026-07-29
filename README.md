# `availability_shibboleth2fa`

Availability plugin for [Moodle][moodle home], which lets users restrict activities and sections with [Shibboleth][shibboleth home] two-factor authentication (2FA).

## Requirements

The minimum supported Moodle version is [**5.2**][moodle docs release 5.2] (build 2026042000).

## Installation

You install `availability_shibboleth2fa` just like any other Moodle plugin.
The code belongs in the `public/availability/condition/shibboleth2fa` directory.

For example, using `git` from the root directory of your Moodle installation:

```shell
git clone \
    https://github.com/innocampus/moodle-availability_shibboleth2fa.git \
    public/availability/condition/shibboleth2fa
```

For other options and general plugin installation instructions, see the [official Moodle documentation][moodle docs plugin install].

Shibboleth needs to be configured to protect `public/availability/condition/shibboleth2fa/auth.php` in order for this plugin to work.

## Shibboleth and Apache Example Configuration

#### shibboleth2.xml

```xml
<SPConfig ...>
    <RequestMapper type="Native">
        <RequestMap>
            <Host name="...">
                <Path name="stepup" applicationId="2fa"
                    authnContextClassRef="urn:oasis:names:tc:SAML:2.0:ac:classes:TimeSyncToken" />
            </Host>
        </RequestMap>
    </RequestMapper>

    <ApplicationDefaults ...>
    ...
        <ApplicationOverride id="2fa" entityID=".../stepup/shibboleth">
            <Sessions handlerURL="/stepup/Shibboleth.sso" lifetime="28800" timeout="14400" checkAddress="false"
                relayState="ss:mem" handlerSSL="true" cookieProps="; path=/; secure; HttpOnly" />
        </ApplicationOverride>
```

[More information here.](https://wiki.cac.washington.edu/display/infra/Configure+a+Service+Provider+for+Step-up+Two-Factor+Authentication)
You need to specify the `entityID` if you want to have a Single Logout.

#### Apache

```apacheconf
<Location /availability/condition/shibboleth2fa/auth.php>
    AuthType shibboleth
    ShibRequestSetting requireSession 1
    ShibRequestSetting acsIndex 2
    ShibRequestSetting applicationId 2fa
    ShibRequestSetting authnContextClassRef urn:oasis:names:tc:SAML:2.0:ac:classes:TimeSyncToken
    ShibRequestSetting authnContextComparison minimum
    Require authnContextClassRef urn:oasis:names:tc:SAML:2.0:ac:classes:TimeSyncToken
</Location>
```

(This assumes the document root points to the `public/` directory of your Moodle installation.)

## Usage

#### Access restriction

After installation, teachers can select "**2FA**" from the list of access restrictions when configuring an activity or section.
Students will then be required to authenticate with shibboleth to access the resource.
After successfully authenticating once, students can access _any resource_ protected by 2FA on your Moodle site _until they log out_.

#### Exceptions

Teachers can add course-wide exceptions for individual users by clicking "**manage exceptions**" on a protected resource.
Users with an exception will never be required to authenticate using 2FA for any protected resource _in that course_.

[moodle docs plugin install]: https://docs.moodle.org/en/Installing_plugins#Installing_a_plugin
[moodle docs release 5.2]: https://moodledev.io/general/releases/5.2
[moodle home]: https://moodle.com
[shibboleth home]: https://www.shibboleth.net
