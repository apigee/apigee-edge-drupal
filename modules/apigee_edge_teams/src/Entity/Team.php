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

use Apigee\Edge\Api\Management\Entity\Company;
use Apigee\Edge\Entity\EntityInterface;
use Apigee\Edge\Structure\AttributesProperty;
use Drupal\apigee_edge\Entity\AttributesAwareFieldableEdgeEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Defines the Team entity class.
 *
 * @\Drupal\apigee_edge\Annotation\EdgeEntityType(
 *   id = "team",
 *   label = @Translation("Team"),
 *   label_collection = @Translation("Teams"),
 *   label_singular = @Translation("team"),
 *   label_plural = @Translation("teams"),
 *   label_count = @PluralTranslation(
 *     singular = "@count team",
 *     plural = "@count teams",
 *   ),
 *   config_with_labels = "apigee_edge_teams.team_settings",
 *   handlers = {
 *     "storage" = "Drupal\apigee_edge_teams\Entity\Storage\TeamStorage",
 *     "permission_provider" = "Drupal\apigee_edge_teams\Entity\TeamPermissionProvider",
 *     "access" = "Drupal\apigee_edge_teams\Entity\TeamAccessHandler",
 *     "list_builder" = "Drupal\apigee_edge_teams\Entity\ListBuilder\TeamListBuilder",
 *     "form" = {
 *        "default" = "Drupal\apigee_edge_teams\Entity\Form\TeamForm",
 *        "add" = "Drupal\apigee_edge_teams\Entity\Form\TeamForm",
 *        "delete" = "Drupal\apigee_edge_teams\Entity\Form\TeamDeleteForm",
 *      },
 *     "route_provider" = {
 *       "default" = "Drupal\apigee_edge_teams\Entity\TeamRouteProvider",
 *     },
 *   },
 *   links = {
 *     "collection" = "/teams",
 *     "canonical" = "/teams/{team}",
 *     "add-form" = "/add-team",
 *     "edit-form" = "/teams/{team}/edit",
 *     "delete-form" = "/teams/{team}/delete",
 *     "members" = "/teams/{team}/members",
 *     "add-members" = "/teams/{team}/add-members",
 *   },
 *   entity_keys = {
 *     "id" = "name",
 *     "label" = "displayName",
 *   },
 *   admin_permission = "administer team",
 *   field_ui_base_route = "apigee_edge_teams.settings.team",
 * )
 */
class Team extends AttributesAwareFieldableEdgeEntityBase implements TeamInterface {

  /**
   * The decorated company entity from the SDK.
   *
   * @var \Apigee\Edge\Api\Management\Entity\Company
   */
  protected $decorated;

  /**
   * Team constructor.
   *
   * @param array $values
   *   An array of values to set, keyed by property name.
   * @param null|string $entity_type
   *   Type of the entity. It is optional because constructor sets its default
   *   value.
   * @param \Apigee\Edge\Entity\EntityInterface|null $decorated
   *   The SDK entity that this Drupal entity decorates.
   */
  public function __construct(array $values, ?string $entity_type, ?EntityInterface $decorated = NULL) {
    $entity_type = $entity_type ?? 'team';
    // Callers expect that the status is always either 'active' or 'inactive',
    // never null.
    if (!isset($values['status'])) {
      $values['status'] = static::STATUS_ACTIVE;
    }
    parent::__construct($values, $entity_type, $decorated);
  }

  /**
   * {@inheritdoc}
   */
  protected static function decoratedClass(): string {
    return Company::class;
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
  public static function idProperty(): string {
    return Company::idProperty();
  }

  /**
   * {@inheritdoc}
   */
  protected function drupalEntityId(): ?string {
    return $this->decorated->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getAttributes(): AttributesProperty {
    return $this->decorated->getAttributes();
  }

  /**
   * {@inheritdoc}
   */
  public function setAttributes(AttributesProperty $attributes): void {
    $this->decorated->setAttributes($attributes);
  }

  /**
   * {@inheritdoc}
   */
  public function getAttributeValue(string $attribute): ?string {
    return $this->decorated->getAttributeValue($attribute);
  }

  /**
   * {@inheritdoc}
   */
  public function setAttribute(string $name, string $value): void {
    $this->decorated->setAttribute($name, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function hasAttribute(string $name): bool {
    return $this->decorated->hasAttribute($name);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAttribute(string $name): void {
    $this->decorated->deleteAttribute($name);
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedAt(): ?\DateTimeImmutable {
    return $this->decorated->getCreatedAt();
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedBy(): ?string {
    return $this->decorated->getCreatedBy();
  }

  /**
   * {@inheritdoc}
   */
  public function getLastModifiedAt(): ?\DateTimeImmutable {
    return $this->decorated->getLastModifiedAt();
  }

  /**
   * {@inheritdoc}
   */
  public function getLastModifiedBy(): ?string {
    return $this->decorated->getLastModifiedBy();
  }

  /**
   * {@inheritdoc}
   */
  public function getApps(): array {
    return $this->decorated->getApps();
  }

  /**
   * {@inheritdoc}
   */
  public function hasApp(string $app_name): bool {
    return $this->decorated->hasApp($app_name);
  }

  /**
   * {@inheritdoc}
   */
  public function setOrganization(string $organization): void {
    $this->decorated->setOrganization($organization);
  }

  /**
   * {@inheritdoc}
   */
  public function getOrganization(): ?string {
    return $this->decorated->getOrganization();
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplayName(): ?string {
    return $this->decorated->getDisplayName();
  }

  /**
   * {@inheritdoc}
   */
  public function setDisplayName(string $display_name): void {
    $this->decorated->setDisplayName($display_name);
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): ?string {
    return $this->decorated->getName();
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus(): ?string {
    return $this->decorated->getStatus();
  }

  /**
   * {@inheritdoc}
   */
  public function setStatus(string $status): void {
    $this->decorated->setStatus($status);
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    /** @var \Drupal\Core\Field\BaseFieldDefinition[] $definitions */
    $definitions = parent::baseFieldDefinitions($entity_type);

    $team_singular_label = \Drupal::entityTypeManager()
      ->getDefinition('team')
      ->getSingularLabel();
    $team_singular_label = mb_convert_case($team_singular_label, MB_CASE_TITLE);

    $definitions['displayName']
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'weight' => 0,
      ])
      ->setLabel(t("@team name", ['@team' => $team_singular_label]))
      ->setRequired(TRUE);

    $definitions['status']
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'status_property',
        'weight' => 2,
      ])
      ->setLabel(t('@team status', ['@team' => $team_singular_label]))
      ->setDisplayConfigurable('form', FALSE);

    $definitions['createdAt']
      ->setDisplayOptions('view', [
        'type' => 'timestamp_ago',
        'label' => 'inline',
        'weight' => 3,
      ])
      ->setLabel(t('Created'))
      ->setDisplayConfigurable('form', FALSE);

    $definitions['lastModifiedAt']
      ->setDisplayOptions('view', [
        'type' => 'timestamp_ago',
        'label' => 'inline',
        'weight' => 5,
      ])
      ->setLabel(t('Last updated'))
      ->setDisplayConfigurable('form', FALSE);

    $definitions['name']
      ->setLabel(t('@team id', ['@team' => $team_singular_label]))
      ->setDisplayConfigurable('form', FALSE)
      ->setRequired(TRUE);

    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  protected static function propertyToBaseFieldTypeMap(): array {
    return parent::propertyToBaseFieldTypeMap() + [
      'status' => 'string',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected static function propertyToBaseFieldBlackList(): array {
    return array_merge(parent::propertyToBaseFieldBlackList(), [
      // Apps only contains app names (not display names), we do not want to
      // expose them by default.
      'apps',
      // There is no need to expose the organization that the team (company)
      // belongs.
      'organization',
    ]);
  }

}
