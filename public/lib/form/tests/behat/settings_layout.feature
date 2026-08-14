@core @core_form
Feature: Settings forms are shown beside a navigation rail
  In order to move around a long settings form
  As a teacher
  I need the form's sections listed beside it, and every section visible without expanding

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "activities" exist:
      | activity | course | section | name        |
      | assign   | C1     | 1       | Test assign |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |

  @javascript
  Scenario: The rail lists the sections of an activity settings form
    When I am on the "Test assign" "assign activity editing" page logged in as teacher1
    Then ".form-settings-nav" "css_element" should exist
    And I should see "Submission types" in the ".form-settings-nav" "css_element"
    And I should see "Common module settings" in the ".form-settings-nav" "css_element"

  @javascript
  Scenario: A rail link moves to its section
    When I am on the "Test assign" "assign activity editing" page logged in as teacher1
    And I click on "Common module settings" "link" in the ".form-settings-nav" "css_element"
    # The link targets the section's own fieldset, so the section stays on the page and
    # the rail marks it as the one now in view.
    Then "#id_modstandardelshdr" "css_element" should be visible
    And ".form-settings-nav a[aria-current='location']" "css_element" should exist

  @javascript
  Scenario: Every section of an activity settings form is visible without expanding
    When I am on the "Test assign" "assign activity editing" page logged in as teacher1
    # Sections no longer collapse, so the expand control is gone and the later sections
    # are already on the page.
    Then "Expand all" "link" should not exist
    And I should see "Common module settings"
    And I should see "Restrict access"

  @javascript
  Scenario: Editing an activity does not repeat the activity name as a heading
    When I am on the "Test assign" "assign activity editing" page logged in as teacher1
    Then I should see "Test assign" in the "page-header" "region"
    And I should not see "Edit settings"

  @javascript
  Scenario: Adding an activity still says what is being created
    Given I am on the "Course 1" "Course" page logged in as teacher1
    When I add a "assign" activity to course "Course 1" section "1"
    Then I should see "New Assignment"

  @javascript
  Scenario: A form that does not opt in is unaffected
    # user/editadvanced.php does not use the settings layout, so it keeps its collapsible
    # sections and shows no rail.
    When I am on the "teacher1" "user > editing" page logged in as admin
    Then ".form-settings-nav" "css_element" should not exist
    And "Expand all" "link" should exist
