<?php

/**
 * Copyright 2018 Google Inc.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 */

namespace Drupal\apigee_edge_teams\Entity\ListBuilder;

use Drupal\apigee_edge\Entity\AppInterface;
use Drupal\apigee_edge\Entity\ListBuilder\AppListBuilder;

/**
 * Lists _all_ team apps.
 */
class TeamAppListBuilder extends AppListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $headers = [];

    $headers['team'] = [
      'data' => $this->t('@team', [
        '@team' => ucfirst($this->entityTypeManager->getDefinition('team')->getSingularLabel()),
      ]),
    ];

    return $headers + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  protected function buildInfoRow(AppInterface $app, array &$rows) {
    /** @var \Drupal\apigee_edge_teams\Entity\TeamAppInterface $app */
    /** @var \Drupal\apigee_edge_teams\Entity\TeamInterface[] $teams */
    $teams = $this->entityTypeManager->getStorage('team')->loadMultiple();
    $css_id = $this->getCssIdForInfoRow($app);
    $rows[$css_id]['data']['team']['data'] = $teams[$app->getCompanyName()]->access('view') ? $teams[$app->getCompanyName()]->toLink()->toRenderable() : $teams[$app->getCompanyName()]->label();
    parent::buildInfoRow($app, $rows);
  }

}
