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

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\user\UserInterface;

/**
 * Defines the team member role entity.
 *
 * This entity stores a team member's roles within a team. It does not store
 * the "member" role because it is an implied role and whether a developer is a
 * member of a team or not is stored in Apigee Edge.
 *
 * @ContentEntityType(
 *   id = "team_member_role",
 *   label = @Translation("Team member role"),
 *   base_table = "team_member_role",
 *   data_table = "team_member_role_data",
 *   handlers = {
 *     "storage" = "Drupal\apigee_edge_teams\Entity\Storage\TeamMemberRoleStorage",
 *   },
 *   entity_keys = {
 *     "id" = "uuid",
 *   },
 * )
 *
 * @internal Right now this is a content entity, because there is no better
 * way in Drupal core to create a fieldable entity. Hopefully this improves
 * in time. See our \Drupal\apigee_edge\Entity\FieldableEdgeEntityBase for
 * Apigee Edge entities.
 */
final class TeamMemberRole extends ContentEntityBase implements TeamMemberRoleInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = [];
    // We would like to use uuid as primary id to these entities and we do
    // not need anything else than the parent class could provide, so we
    // did not call it here.
    $fields[$entity_type->getKey('id')] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
    // We have to store the user id of the user because the email address.
      ->setReadOnly(TRUE);
    // Of a developer can change outside of Drupal and it seems the CPS
    // migration can change its developer UUID as well. This is the reason
    // why we can not use a developer entity reference here.
    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User'))
      ->setDescription(t('The Drupal user of the developer.'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setRequired(TRUE)
      ->setDisplayConfigurable('view', FALSE)
      ->setDisplayConfigurable('form', FALSE);

    $fields['team'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Team'))
      ->setDescription(t('A team that the developer belongs.'))
      ->setSetting('target_type', 'team')
      ->setSetting('handler', 'default')
      ->setRequired(TRUE)
      ->setDisplayConfigurable('view', FALSE)
      ->setDisplayConfigurable('form', FALSE);

    $fields['roles'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Roles'))
      ->setDescription(t('The team roles of the developer within the team, except member, because that is an implied role.'))
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setSetting('target_type', 'team_role')
      ->setSetting('handler', 'default')
      ->setRequired(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t("The time that team member's roles were last edited."))
      ->setTranslatable(TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getDeveloper(): ?UserInterface {
    return $this->getOwner();
  }

  /**
   * {@inheritdoc}
   */
  public function getTeam(): ?TeamInterface {
    return $this->get('team')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getTeamRoles(): array {
    $roles = [];

    /** @var \Drupal\apigee_edge_teams\Entity\TeamMemberRoleInterface $reference */
    foreach ($this->get('roles') as $reference) {
      // The team role has been deleted.
      if ($reference->entity === NULL) {
        continue;
      }
      $roles[$reference->entity->id()] = $reference->entity;
    }
    return $roles;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('uid')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    return $this->set('uid', $account->id());
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('uid')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    return $this->set('uid', $uid);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    if ($this->getTeam()) {
      $this->cacheTags = array_merge($this->cacheTags, $this->getTeam()->getCacheTags());
    }
    if ($this->getDeveloper()) {
      $this->cacheTags = array_merge($this->cacheTags, $this->getDeveloper()->getCacheTags());
    }
    return parent::getCacheTags();
  }

}
