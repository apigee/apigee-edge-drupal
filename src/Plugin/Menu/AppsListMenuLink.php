<?php

namespace Drupal\apigee_edge\Plugin\Menu;

use Drupal\Core\Menu\MenuLinkDefault;

/**
 * Provides a default app linting page menu link.
 */
class AppsListMenuLink extends MenuLinkDefault {

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    // Use the same title as the page.
    return apigee_edge_app_listing_page_title();
  }

}
