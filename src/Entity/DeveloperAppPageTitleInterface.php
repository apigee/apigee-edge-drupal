<?php

namespace Drupal\apigee_edge\Entity;

use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Generates page title for developer app related pages.
 */
interface DeveloperAppPageTitleInterface {

  /**
   * Returns the translated title of the page.
   *
   * Because parameter name of the developer app object varies based on the
   * page context we only inject the route matcher and retrieves parameters
   * from that directly.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   *
   * @return string
   *   The page title.
   */
  public function getPageTitle(RouteMatchInterface $routeMatch): string;

}
