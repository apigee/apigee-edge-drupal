# Apigee Edge Actions

The Apigee Edge Actions module provides rules integration for Apigee Edge. It makes it easy to automate tasks and react on events such as:

  * Sending an email when an App is created.
  * Notify a developer when added to a Team.
  * Notify admin when an API product is added to an App.

## Events

The following events are supported out of the box:

### App
`\Drupal\apigee_edge\Entity\DeveloperApp`

| Event | Name  |
|---|---|
| After saving a new App  | `apigee_edge_actions_entity_insert:developer_app`  |
| After deleting an App   | `apigee_edge_actions_entity_delete:developer_app`  |
| After updating an App   | `apigee_edge_actions_entity_insert:developer_app`  |
| After adding an API Product   | `apigee_edge_actions_entity_add_product:developer_app`  |
| After removing an API Product   | `apigee_edge_actions_entity_remove_product:developer_app`  |

### Team App
`\Drupal\apigee_edge_teams\Entity\TeamApp`

| Event | Name  |
|---|---|
| After saving a new Team App  | `apigee_edge_actions_entity_insert:team_app`  |
| After deleting an Team App   | `apigee_edge_actions_entity_delete:team_app`  |
| After updating an Team App   | `apigee_edge_actions_entity_insert:team_app`  |
| After adding an API Product   | `apigee_edge_actions_entity_add_product:team_app`  |
| After removing an API Product   | `apigee_edge_actions_entity_remove_product:team_app`  |

### Team
`\Drupal\apigee_edge_teams\Entity\Team`

| Event | Name  |
|---|---|
| After saving a new Team  | `apigee_edge_actions_entity_insert:team`  |
| After deleting an Team   | `apigee_edge_actions_entity_delete:team`  |
| After updating an Team   | `apigee_edge_actions_entity_insert:team`  |
| After adding a team member | `apigee_edge_actions_entity_add_member:team`  |
| After removing a team member | `apigee_edge_actions_entity_remove_member:team`  |

## Examples

The `apigee_edge_actions_examples` module ships with some example rules you can use to test:

1. Log a message when team is deleted.
2. Notify developer when added to a team
3. Notify developer when adding a new app
4. Notify site admins when app is created
