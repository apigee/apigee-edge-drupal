<?php

namespace Drupal\apigee_edge\Entity;

use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Permission provider for Developer App entities.
 */
class DeveloperAppEntityPermissionProvider extends EdgeEntityPermissionProviderBase {

  /**
   * {@inheritdoc}
   */
  public function buildPermissions(EntityTypeInterface $entity_type) {
    $perms = parent::buildPermissions($entity_type);
    // We use the combination of "view own/any developer_app" and
    // "administer developer_app" permissions instead of this or
    // an "access own developer_app overview" permission.
    unset($perms['access developer_app overview']);
    return $perms;
  }

}
