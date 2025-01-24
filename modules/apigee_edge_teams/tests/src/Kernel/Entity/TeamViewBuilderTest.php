<?php

/**
 * Copyright 2020 Google Inc.
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

namespace Drupal\Tests\apigee_edge_teams\Kernel\Entity;

use Drupal\Tests\apigee_edge\Kernel\ApigeeEdgeKernelTestBase;
use Drupal\Tests\apigee_edge\Kernel\ApigeeEdgeKernelTestTrait;
use Drupal\Tests\apigee_mock_api_client\Traits\ApigeeMockApiClientHelperTrait;

/**
 * Tests the Team view builder.
 *
 * @group apigee_edge
 * @group apigee_edge_kernel
 * @group apigee_edge_teams
 * @group apigee_edge_teams_kernel
 */
class TeamViewBuilderTest extends ApigeeEdgeKernelTestBase {

  use ApigeeMockApiClientHelperTrait, ApigeeEdgeKernelTestTrait;

  /**
   * Indicates this test class is mock API client ready.
   *
   * @var bool
   */
  protected static $mock_api_client_ready = TRUE;

  /**
   * The entity type to test.
   */
  const ENTITY_TYPE = 'team';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'apigee_edge',
    'apigee_edge_teams',
    'apigee_mock_api_client',
    'key',
    'user',
    'options',
  ];

  /**
   * The team entity.
   *
   * @var \Drupal\apigee_edge_teams\Entity\TeamInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['apigee_edge']);
    $this->installConfig(['apigee_edge_teams']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('team_member_role');
    $this->installSchema('system', ['sequences']);
    $this->installSchema('user', ['users_data']);

    $this->apigeeTestHelperSetup();

    $this->addOrganizationMatchedResponse();

    $this->entity = $this->createTeam();
  }

  /**
   * Tests the cache max-age for the view builder.
   */
  public function testViewCacheExpiration() {
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = $this->container->get('entity_type.manager');
    $build = $entity_type_manager->getViewBuilder(static::ENTITY_TYPE)->view($this->entity);

    static::assertEquals(900, $build['#cache']['max-age']);

    // Update the cache setting.
    $this->config('apigee_edge_teams.team_settings')
      ->set('cache_expiration', 0)
      ->save();

    $build = $entity_type_manager->getViewBuilder(static::ENTITY_TYPE)->view($this->entity);
    static::assertEquals(0, $build['#cache']['max-age']);
  }

}
