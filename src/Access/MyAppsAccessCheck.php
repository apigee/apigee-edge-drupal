<?php

namespace Drupal\apigee_edge\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Custom access handler to My apps page.
 */
class MyAppsAccessCheck implements AccessInterface {

  /**
   * Grant access to My apps page if user has any of the required permissions.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The parametrized route.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(RouteMatchInterface $route_match, AccountInterface $account) {
    /** @var \Drupal\user\UserInterface $user */
    $user = $route_match->getParameter('user');
    if ($user === NULL) {
      return AccessResult::forbidden('User is missing from route.');
    }
    return AccessResultAllowed::allowedIf(
      ($account->id() === $user->id() && $account->hasPermission('view own developer_app')) ||
      ($account->hasPermission('administer developer_app'))
    );
  }

}
