@webUI @insulated @disablePreviews
Feature: obtaining an access token
  As a user
  I want to be able to receive and use oauth tokens for authentication
  So that I do not need to entrust various apps with my ownCloud password

  Background:
    Given these users have been created with large skeleton files:
      | username | password | displayname  | email             |
      | Alice    | 1234     | Alice Hansen | alice@example.org |


  Scenario: receive an authorization code
    When the user sends an oauth2 authorization request using the webUI
    And the user logs in with username "Alice" and password "1234" using the webUI after a redirect from the oauth2AuthRequest page
    And the user authorizes the oauth app using the webUI
    Then the client app should receive an authorization code


  Scenario: receive an access token and use it to access a file
    Given these users have been created with large skeleton files:
      | username | password | displayname  | email             |
      | Brian    | 1234     | Brian Murphy | brian@example.org |
    When the user sends an oauth2 authorization request using the webUI
    And the user logs in with username "Alice" and password "1234" using the webUI after a redirect from the oauth2AuthRequest page
    And the user authorizes the oauth app using the webUI
    And the client app requests an access token
    Then the client app should be able to download the file "lorem.txt" of "Alice" using the access token for authentication
    But the client app should not be able to download the file "lorem.txt" of "Brian" using the access token for authentication


  Scenario: receive a new access token by using the refresh token
    Given these users have been created with large skeleton files:
      | username | password | displayname  | email             |
      | Brian    | 1234     | Brian Murphy | brian@example.org |
    And user "Alice" has correctly established an oauth session
    When the client app refreshes the access token
    Then the client app should be able to download the file "lorem.txt" of "Alice" using the access token for authentication
    But the client app should not be able to download the file "lorem.txt" of "Brian" using the access token for authentication


  Scenario: use OCS with oauth
    Given user "Alice" has correctly established an oauth session
    When the user requests "/ocs/v1.php/apps/files_sharing/api/v1/remote_shares" with "GET" using oauth
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"


  Scenario: use OCS with oauth
    Given user "Alice" has correctly established an oauth session
    When the user requests "/ocs/v2.php/apps/files_sharing/api/v1/remote_shares" with "GET" using oauth
    Then the OCS status code should be "200"
    And the HTTP status code should be "200"


  Scenario: receive an access token when user is already logged in and use it to access a file
    Given the user has browsed to the login page
    And the user has logged in with username "Alice" and password "1234" using the webUI
    When the user sends an oauth2 authorization request using the webUI
    And the user authorizes the oauth app using the webUI
    And the client app requests an access token
    Then the client app should be able to download the file "lorem.txt" of "Alice" using the access token for authentication


  Scenario: try to receive an authorization code with an unregistered client
    When the user sends an oauth2 authorization request with an unregistered clientId using the webUI
    And the user logs in with username "Alice" and password "1234" using the webUI after a redirect from the oauth2AuthRequest page
    Then an invalid oauth request message should be shown


  Scenario: receive an access token for a user that is different to the currently logged in user
    Given these users have been created with large skeleton files:
      | username | password | displayname  | email             |
      | Brian    | 1234     | Brian Murphy | brian@example.org |
    And the user has browsed to the login page
    And the user has logged in with username "Alice" and password "1234" using the webUI
    When user "Brian" sends an oauth2 authorization request using the webUI
    And the user switches the user to continue the oauth process using the webUI
    And the user logs in with username "Brian" and password "1234" using the webUI after a redirect from the oauth2AuthRequest page
    And the user authorizes the oauth app using the webUI
    And the client app requests an access token
    Then the client app should be able to download the file "lorem.txt" of "Brian" using the access token for authentication


  Scenario: receive an access token after invalid password entry
    When the user sends an oauth2 authorization request using the webUI
    And the user logs in with username "Alice" and invalid password "wrong" using the webUI
    And the user logs in with username "Alice" and password "1234" using the webUI after a redirect from the oauth2AuthRequest page
    And the user authorizes the oauth app using the webUI
    And the client app requests an access token
    Then the client app should be able to download the file "lorem.txt" of "Alice" using the access token for authentication
