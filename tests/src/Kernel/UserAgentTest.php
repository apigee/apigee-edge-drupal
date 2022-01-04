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

use Drupal\KernelTests\KernelTestBase;
use Drupal\Core\Extension\InfoParser;
use Drupal\Core\Extension\InfoParserInterface;

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
  protected function setUp() {
    parent::setUp();

    // Install the user module.
    \Drupal::service('module_installer')->install(['user', 'apigee_edge']);
    \Drupal::service('module_installer')->install(['user', 'apigee_m10n']);

    // apigee_edge module info.
    $infoParser = new InfoParser();
    $this->edgeModuleInfo = $infoParser->parse(\Drupal::service('module_handler')->getModule('apigee_edge')->getPathname());
    if (!isset($this->edgeModuleInfo['version'])) {
      $this->edgeModuleInfo['version'] = '2.x-dev';
    }
  }

  /**
   * Test the user agent data with monetization module enabled.
   *
   * @throws \Exception
   */
  public function testUserAgent() {
    $user_agent_parts[] = $this->edgeModuleInfo['name'] . '/' . $this->edgeModuleInfo['version'];
    $user_agent_parts[] = 'Drupal/' . \Drupal::VERSION;

    \Drupal::moduleHandler()->invokeAll('apigee_edge_user_agent_string_alter', [&$user_agent_parts]);
    $userAgentPrefix = implode('; ', $user_agent_parts);

    $this->assertSame($userAgentPrefix, 'Apigee Monetization/2.x-dev; Apigee Edge/2.x-dev;' . ' Drupal/' . \Drupal::VERSION);
  }

  /**
   * Test the user agent data without monetization module.
   *
   * @throws \Exception
   */
  public function testUserAgentWithoutMonetization() {
    // Uninstalling the monetization module.
    \Drupal::service('module_installer')->uninstall(['apigee_m10n']);

    $user_agent_parts[] = $this->edgeModuleInfo['name'] . '/' . $this->edgeModuleInfo['version'];
    $user_agent_parts[] = 'Drupal/' . \Drupal::VERSION;

    \Drupal::moduleHandler()->invokeAll('apigee_edge_user_agent_string_alter', [&$user_agent_parts]);
    $userAgentPrefix = implode('; ', $user_agent_parts);

    $this->assertSame($userAgentPrefix, 'Apigee Edge/2.x-dev;' . ' Drupal/' . \Drupal::VERSION);
  }

}
