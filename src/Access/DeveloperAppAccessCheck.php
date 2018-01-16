<?php

namespace Drupal\apigee_edge\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;

/**
 * Provides a generic access checker for developer app entities.
 */
class DeveloperAppAccessCheck implements AccessInterface {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * DeveloperAppAccessCheck constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Checks access to the developer app entity operation on the given route.
   *
   * @code
   * pattern: '/developer-apps/{developer_app}/edit'
   * requirements:
   *   _developer_app_access: 'edit'
   * @endcode
   * or
   * @code
   * pattern: '/user/{user}/{app}'
   * requirements:
   *   _developer_app_access: 'view'
   * @endcode
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The parametrized route
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, RouteMatchInterface $route_match, AccountInterface $account) {
    $operation = $route->getRequirement('_developer_app_access');
    // If $entity_type parameter is a valid entity, call its own access check.
    $parameters = $route_match->getParameters();
    /** @var \Drupal\apigee_edge\Entity\DeveloperAppInterface|null $entity */
    $entity = $parameters->get('developer_app');
    if ($entity === NULL && $parameters->has('app')) {
      $entity = $parameters->get('app');
      return $entity->access($operation, $account, TRUE);
    }
    // No opinion, so other access checks should decide if access should be
    // allowed or not.
    return AccessResult::neutral();
  }

}
