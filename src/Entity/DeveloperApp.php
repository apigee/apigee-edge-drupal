<?php

namespace Drupal\apigee_edge\Entity;

use Apigee\Edge\Api\Management\Entity\DeveloperApp as EdgeDeveloperApp;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Defines the Developer app entity class.
 *
 * @\Drupal\apigee_edge\Annotation\EdgeEntityType(
 *   id = "developer_app",
 *   label_singular = @Translation("Developer App"),
 *   label_plural = @Translation("Developer Apps"),
 *   label_count = @PluralTranslation(
 *     singular = "@count Developer App",
 *     plural = "@count Developer Apps",
 *   ),
 *   handlers = {
 *     "storage" = "\Drupal\apigee_edge\Entity\Storage\DeveloperAppStorage",
 *     "access" = "Drupal\entity\UncacheableEntityAccessControlHandler",
 *     "permission_provider" = "\Drupal\apigee_edge\Entity\DeveloperAppEntityPermissionProvider",
 *     "form" = {
 *       "add" = "\Drupal\apigee_edge\Entity\Form\DeveloperAppCreate",
 *     },
 *   },
 *   links = {
 *     "add-form" = "/developer_app/add",
 *   },
 *   entity_keys = {
 *     "id" = "appId",
 *     "bundle" = "developerId",
 *   },
 *   permission_granularity = "entity_type",
 *   admin_permission = "administer developer_app",
 * )
 */
class DeveloperApp extends EdgeDeveloperApp implements DeveloperAppInterface {

  use EdgeEntityBaseTrait {
    id as private traitId;
  }

  /** @var null|int */
  protected $drupalUserId ;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $values = []) {
    parent::__construct($values);
    $this->entityTypeId = 'developer_app';
  }

  /**
   * {@inheritdoc}
   */
  public function id(): ? string {
    return $this->getAppId();
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->drupalUserId === NULL ? NULL : User::load($this->drupalUserId);
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->drupalUserId = $account->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->drupalUserId;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->drupalUserId = $uid;
  }

}
