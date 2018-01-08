<?php

namespace Drupal\apigee_edge\Entity;

use Apigee\Edge\Api\Management\Entity\DeveloperApp as EdgeDeveloperApp;

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
 *     "access" = "\Drupal\apigee_edge\Entity\Access\DeveloperAppAccessControlHandler",
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
 *   admin_permission = "administer edge developer app",
 * )
 */
class DeveloperApp extends EdgeDeveloperApp implements DeveloperAppInterface {

  use EdgeEntityBaseTrait {
    id as private traitId;
  }

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

}
