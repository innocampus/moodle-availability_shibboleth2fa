@availability @availability_shibboleth2fa
Feature: Restrict activity access with second-factor authentication.

  Background:
    Given the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "users" exist:
      | username |
      | teacher1 |
      | student1 |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    # Set the condition directly here on course_modules.availability to avoid the slow/flaky YUI add-restriction UI.
    # The `showc` setting keeps the activity visible (greyed) so the student sees the authentication link.
    And the following "activities" exist:
      | activity | course | name | availability                                             |
      | page     | C1     | P1   | {"op":"&","c":[{"type":"shibboleth2fa"}],"showc":[true]} |

  Scenario: Student sees the second-factor restriction on the course page
    When I am on the "C1" "Course" page logged in as "student1"
    Then I should see "Not available unless: You authenticate using a second factor"

  Scenario: Student passes second-factor authentication and reaches the content
    Given the Shibboleth second factor identifies me as "student1"
    And I am on the "C1" "Course" page logged in as "student1"
    When I follow "You authenticate using a second factor"
    Then I should see "You need to authenticate using a second factor to access this content"
    When I press "Continue"
    Then I should see "You have successfully authenticated using a second factor"
    When I press "Continue"
    Then I should see "Test page content"

  Scenario: A different Shibboleth identity is rejected
    Given the Shibboleth second factor identifies me as "student2"
    And I am on the "C1" "Course" page logged in as "student1"
    And I follow "You authenticate using a second factor"
    When I press "Continue"
    Then I should see "You authenticated using the wrong username"

  Scenario: A missing Shibboleth login is rejected
    Given the Shibboleth second factor identifies me as ""
    And I am on the "C1" "Course" page logged in as "student1"
    And I follow "You authenticate using a second factor"
    When I press "Continue"
    Then I should see "Something went wrong with the authentication process"

  Scenario: Visiting the activity URL directly is blocked before authenticating
    When I am on the "P1" "page activity" page logged in as "student1"
    Then I should not see "Test page content"
    And I should see "You authenticate using a second factor"

  Scenario: A per-course exception unlocks the content without authenticating
    Given a Shibboleth second-factor exception exists for user "student1" in course "C1"
    When I am on the "C1" "Course" page logged in as "student1"
    Then I should not see "Not available unless: You authenticate using a second factor"
    And I follow "P1"
    Then I should see "Test page content"
