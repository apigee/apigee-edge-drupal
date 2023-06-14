<?php

/**
 * Copyright 2020 Google Inc.
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

namespace Drupal\apigee_edge_teams\Controller;

use Drupal\apigee_edge\Controller\DeveloperAppKeysController;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller for the team app credentials.
 */
class TeamAppKeysController extends DeveloperAppKeysController {

  /**
   * Returns app credentials.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The app credentials.
   */
  public function teamAppKeys($team, $app): JsonResponse {
    $payload = [];
    if ($team) {
      $app_storage = $this->entityTypeManager->getStorage('team_app');
      // Lists all the team apps ids.
      // Team app is accessible to all the team members.
      $app_ids = $app_storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('companyName', $team->id())
        ->condition('name', $app->getName())
        ->execute();
      if (!empty($app_ids)) {
        $app_id = reset($app_ids);
        $payload = $this->getAppKeys($app_storage->load($app_id));
      }
    }
    return new JsonResponse($payload, 200, ['Cache-Control' => 'must-understand, no-store']);
  }

}
