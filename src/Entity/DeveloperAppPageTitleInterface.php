<?php

/**
 * Copyright 2018 Google Inc.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License version 2 as published by the
 * Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public
 * License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc., 51
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

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
   * To avoid double-escaping the developer app's name use a MarkupInterface
   * object (e.g. \Drupal\Core\Render\Markup) as placeholder replacement value
   * so the replaced value in the returned string will not be sanitized.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   *
   * @return string
   *   The page title.
   *
   * @see https://github.com/drupal/core/blob/10b7c918ab56f9b6b14ed52ed0afd2ab66f4b927/lib/Drupal/Component/Render/FormattableMarkup.php#L140
   */
  public function getPageTitle(RouteMatchInterface $route_match): string;

}
