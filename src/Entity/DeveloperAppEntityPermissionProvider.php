<?php

namespace Drupal\apigee_edge\Entity;

use Drupal\Core\Entity\EntityTypeInterface;

class DeveloperAppEntityPermissionProvider extends EdgeEntityPermissionProviderBase {

  /**
   * {@inheritdoc}
   */
  public function buildPermissions(EntityTypeInterface $entity_type) {
    $perms = parent::buildPermissions($entity_type);
    // Currently we do not provide a general overview page for _all_ developer
    // apps in Drupal.
    unset($perms['access developer_app overview']);
    return $perms;
  }

}
