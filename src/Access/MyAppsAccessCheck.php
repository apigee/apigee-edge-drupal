<?php

namespace Drupal\apigee_edge\Access;

use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Custom access handler to My apps page.
 */
class MyAppsAccessCheck implements AccessInterface {

  /**
   * Grant access to My apps page if user has any of the required permissions.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account) {
    return AccessResultAllowed::allowedIfHasPermissions($account, [
      'view own developer_app',
      'view any developer_app',
      'administer developer_app',
    ], 'OR');
  }

}
