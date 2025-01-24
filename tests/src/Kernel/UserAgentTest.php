<?php

/*
 * Copyright 2022 Google Inc.
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

namespace Drupal\Tests\apigee_edge\Kernel;

use Drupal\Core\Extension\InfoParser;
use Drupal\KernelTests\KernelTestBase;

/**
 * Apigee Useragent tests.
 *
 * @group apigee_edge
 * @group apigee_edge_kernel
 */
class UserAgentTest extends KernelTestBase {

  /**
   * Apigee Edge module info.
   *
   * @var string
   */
  protected $edgeModuleInfo;

  /**
   * The info parser.
   *
   * @var \Drupal\Core\Extension\InfoParserInterface
   */
  protected $infoParser;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'file',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Install the apigee edge module.
    \Drupal::service('module_installer')->install(['apigee_edge']);
  }

  /**
   * Test the user agent data without monetization module.
   *
   * @throws \Exception
   */
  public function testUserAgentWithoutMonetization() {
    // apigee_edge module info.
    $infoParser = new InfoParser($this->root);
    $this->edgeModuleInfo = $infoParser->parse(\Drupal::service('module_handler')->getModule('apigee_edge')->getPathname());
    if (!isset($this->edgeModuleInfo['version'])) {
      $this->edgeModuleInfo['version'] = '3.x-dev';
    }

    $user_agent_parts[] = $this->edgeModuleInfo['name'] . '/' . $this->edgeModuleInfo['version'];
    $user_agent_parts[] = 'Drupal/' . \Drupal::VERSION;

    \Drupal::moduleHandler()->invokeAll('apigee_edge_user_agent_string_alter', [&$user_agent_parts]);
    $userAgentPrefix = implode('; ', $user_agent_parts);

    $this->assertSame($userAgentPrefix, 'Apigee/3.x-dev;' . ' Drupal/' . \Drupal::VERSION);
  }

}
