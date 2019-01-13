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

namespace Drupal\Tests\apigee_edge_teams\Kernel;

use Apigee\Edge\Api\Management\Entity\Company;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\apigee_edge\Traits\EntityControllerCacheUtilsTrait;

/**
 * Apigee Edge Teams entity controller cache tests.
 *
 * @group apigee_edge
 * @group apigee_edge_kernel
 * @group apigee_edge_teams
 * @group apigee_edge_teams_kernel
 */
class EntityControllerCacheTest extends KernelTestBase {

  use EntityControllerCacheUtilsTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'apigee_edge',
    'apigee_edge_teams',
    'key',
  ];

  /**
   * Tests team entity controller cache.
   */
  public function testTeamEntityControllerCache() {
    $team_cache = $this->container->get('apigee_edge_teams.controller.cache.team');
    /** @var \Drupal\apigee_edge\Entity\Controller\Cache\EntityIdCacheInterface $team_id_cache */
    $team_id_cache = $this->container->get('apigee_edge_teams.controller.cache.team_ids');

    // Generate team entities with random data.
    $teams = [];
    for ($i = 0; $i < 3; $i++) {
      $id = $this->getRandomUniqueId();
      $teams[$id] = new Company([
        'name' => $id,
      ]);
    }

    $this->saveAllEntitiesAndValidate($teams, $team_cache, $team_id_cache);

    /** @var \Apigee\Edge\Api\Management\Entity\CompanyInterface $team */
    foreach ($teams as $team) {
      // Load team by name.
      $this->assertSame($team, $team_cache->getEntity($team->getName()));
      $this->assertContains($team->getName(), $team_id_cache->getIds());

      // Pass an invalid entity id to ensure it does not cause any trouble
      // anymore.
      // @see \Drupal\apigee_edge\Entity\Controller\Cache\EntityCache::removeEntities()
      // @see https://www.drupal.org/project/drupal/issues/3017753
      $team_cache->removeEntities([$team->getName(), $this->getRandomGenerator()->string()]);
      $this->assertNull($team_cache->getEntity($team->getName()));
      $this->assertNotContains($team->getName(), $team_id_cache->getIds());
    }

    $this->assertEmptyCaches($team_cache, $team_id_cache);
  }

}
