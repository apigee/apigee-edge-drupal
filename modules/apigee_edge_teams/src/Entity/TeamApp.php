<?php

/**
 * Copyright 2018 Google Inc.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 */

namespace Drupal\apigee_edge_teams\Entity;

use Apigee\Edge\Api\Management\Entity\CompanyApp;
use Apigee\Edge\Entity\EntityInterface as EdgeEntityInterface;
use Drupal\apigee_edge\Entity\App;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Defines the Team (company) app entity class.
 *
 * @\Drupal\apigee_edge\Annotation\EdgeEntityType(
 *   id = "team_app",
 *   label = @Translation("Team App"),
 *   label_collection = @Translation("Team Apps"),
 *   label_singular = @Translation("team app"),
 *   label_plural = @Translation("team apps"),
 *   label_count = @PluralTranslation(
 *     singular = "@count team app",
 *     plural = "@count team apps",
 *   ),
 *   config_with_labels = "apigee_edge_teams.team_app_settings",
 *   handlers = {
 *     "storage" = "Drupal\apigee_edge_teams\Entity\Storage\TeamAppStorage",
 *     "permission_provider" = "Drupal\apigee_edge_teams\Entity\TeamAppPermissionProvider",
 *     "access" = "Drupal\apigee_edge_teams\Entity\TeamAppAccessHandler",
 *     "form" = {
 *       "default" = "Drupal\apigee_edge_teams\Entity\Form\TeamAppCreateForm",
 *       "add" = "Drupal\apigee_edge_teams\Entity\Form\TeamAppCreateForm",
 *       "add_for_team" = "Drupal\apigee_edge_teams\Entity\Form\TeamAppCreateFormForTeam",
 *       "edit" = "Drupal\apigee_edge_teams\Entity\Form\TeamAppEditForm",
 *       "delete" = "Drupal\apigee_edge_teams\Entity\Form\TeamAppDeleteForm",
 *       "analytics" = "Drupal\apigee_edge_teams\Form\TeamAppAnalyticsForm",
 *       "add_api_key" = "Drupal\apigee_edge_teams\Form\TeamAppApiKeyAddForm",
 *       "delete_api_key" = "Drupal\apigee_edge_teams\Form\TeamAppApiKeyDeleteForm",
 *       "revoke_api_key" = "Drupal\apigee_edge_teams\Form\TeamAppApiKeyRevokeForm",
 *     },
 *     "list_builder" = "Drupal\apigee_edge_teams\Entity\ListBuilder\TeamAppListBuilder",
 *     "view_builder" = "Drupal\apigee_edge\Entity\AppViewBuilder",
 *     "route_provider" = {
 *        "html" = "Drupal\apigee_edge_teams\Entity\TeamAppRouteProvider",
 *     },
 *   },
 *   links = {
 *     "collection" = "/team-apps",
 *     "collection-by-team" = "/teams/{team}/apps",
 *     "canonical" = "/teams/{team}/apps/{app}",
 *     "add-form" = "/team-apps/add",
 *     "add-form-for-team" = "/teams/{team}/create-app",
 *     "edit-form" = "/teams/{team}/apps/{app}/edit",
 *     "delete-form" = "/teams/{team}/apps/{app}/delete",
 *     "analytics"  = "/teams/{team}/apps/{app}/analytics",
 *     "api-keys"  = "/teams/{team}/apps/{app}/api-keys",
 *     "add-api-key-form" = "/teams/{team}/apps/{app}/api-keys/add",
 *     "delete-api-key-form" = "/teams/{team}/apps/{app}/api-keys/{consumer_key}/delete",
 *     "revoke-api-key-form" = "/teams/{team}/apps/{app}/api-keys/{consumer_key}/revoke",
 *   },
 *   entity_keys = {
 *     "id" = "appId",
 *   },
 *   query_class = "Drupal\apigee_edge_teams\Entity\Query\TeamAppQuery",
 *   admin_permission = "administer team",
 *   field_ui_base_route = "apigee_edge_teams.settings.team_app",
 * )
 */
class TeamApp extends App implements TeamAppInterface {

  /**
   * The decorated company app entity from the SDK.
   *
   * @var \Apigee\Edge\Api\Management\Entity\CompanyApp
   */
  protected $decorated;

  /**
   * TeamApp constructor.
   *
   * @param array $values
   *   An array of values to set, keyed by property name.
   * @param null|string $entity_type
   *   Type of the entity. It is optional because constructor sets its default
   *   value.
   * @param \Apigee\Edge\Entity\EntityInterface|null $decorated
   *   The SDK entity that this Drupal entity decorates.
   */
  public function __construct(array $values, ?string $entity_type = NULL, ?EdgeEntityInterface $decorated = NULL) {
    /** @var \Apigee\Edge\Api\Management\Entity\CompanyAppInterface $decorated */
    $entity_type = $entity_type ?? 'team_app';
    parent::__construct($values, $entity_type, $decorated);
  }

  /**
   * We have to override this.
   *
   * This is how we could make it compatible with the SDK's
   * entity interface that has return type hint.
   */
  public function id(): ?string {
    return parent::id();
  }

  /**
   * {@inheritdoc}
   */
  public function getAppOwner(): ?string {
    return $this->decorated->getCompanyName();
  }

  /**
   * {@inheritdoc}
   */
  public function setAppOwner(string $owner): void {
    $this->decorated->setCompanyName($owner);
  }

  /**
   * {@inheritdoc}
   */
  public function getCompanyName(): ?string {
    return $this->decorated->getCompanyName();
  }

  /**
   * {@inheritdoc}
   */
  protected static function decoratedClass(): string {
    return CompanyApp::class;
  }

  /**
   * {@inheritdoc}
   */
  public static function idProperty(): string {
    return CompanyApp::idProperty();
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    /** @var \Drupal\Core\Field\BaseFieldDefinition[] $definitions */
    $definitions = parent::baseFieldDefinitions($entity_type);
    $team_app_singular_label = \Drupal::entityTypeManager()->getDefinition('team_app')->getSingularLabel();
    $team_app_singular_label = mb_convert_case($team_app_singular_label, MB_CASE_TITLE);

    $definitions['displayName']
      ->setLabel(t('@team_app name', ['@team_app' => $team_app_singular_label]));

    $definitions['status']
      ->setLabel(t('@team_app status', ['@team_app' => $team_app_singular_label]));

    $team_app_settings = \Drupal::config('apigee_edge_teams.team_app_settings');
    foreach ((array) $team_app_settings->get('required_base_fields') as $required) {
      $definitions[$required]->setRequired(TRUE);
    }

    // Hide readonly properties from Manage form display list.
    $definitions['companyName']->setDisplayConfigurable('form', FALSE);

    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  protected function urlRouteParameters($rel) {
    $params = parent::urlRouteParameters($rel);
    $link_templates = $this->linkTemplates();
    if (isset($link_templates[$rel])) {
      if (strpos($link_templates[$rel], '{team}') !== FALSE) {
        $params['team'] = $this->getCompanyName();
      }
      if (strpos($link_templates[$rel], '{app}') !== FALSE) {
        $params['app'] = $this->getName();
      }
      if (strpos($link_templates[$rel], '{team_app}') === FALSE) {
        unset($params['team_app']);
      }
    }

    return $params;
  }

}
