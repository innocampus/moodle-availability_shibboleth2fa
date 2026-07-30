@availability @availability_shibboleth2fa @javascript
Feature: Restrict a course section with second-factor authentication.

  Background:
    Given the following "courses" exist:
      | fullname | shortname | format | numsections |
      | Course 1 | C1        | topics | 2           |
    And the following "users" exist:
      | username |
      | teacher1 |
      | student1 |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | course | name | section |
      | page     | C1     | P1   | 1       |

  Scenario: A second-factor restriction added to a section is enforced and cleared by authenticating
    # Teacher adds the restriction to section 1 via the real editing UI.
    Given I am logged in as "teacher1"
    And I am on "Course 1" course homepage with editing mode "on"
    And I open section "1" edit menu
    And I choose "Edit settings" in the open action menu
    And I expand all fieldsets
    And I click on "Add restriction..." "button"
    And "2FA" "button" should exist in the "Add restriction..." "dialogue"
    And I click on "2FA" "button"
    And I click on "Save changes" "button"

    # Student is blocked: the section shows the authentication link and its activity is hidden.
    Given the Shibboleth second factor identifies me as "student1"
    And I am on the "Course 1" "course" page logged in as "student1"
    Then I should see "Not available unless: You authenticate using a second factor"
    And I should not see "Test page content"

    # Once 2FA is passed, the section becomes visible.
    When I follow "You authenticate using a second factor"
    And I press "Continue"
    Then I should see "You have successfully authenticated using a second factor"
    When I press "Continue"
    Then I should not see "Not available unless: You authenticate using a second factor"
    And I follow "P1"
    Then I should see "Test page content"
