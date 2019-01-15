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

namespace Drupal\Tests\apigee_edge\Kernel\Plugin\KeyInput;

use Drupal\apigee_edge\Plugin\EdgeKeyTypeInterface;
use Drupal\apigee_edge\Plugin\KeyInput\ApigeeAuthKeyInput;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormState;
use Drupal\Core\Render\Element;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\apigee_edge\Traits\TestKeyBuilderTrait;

/**
 * OAuth cache storage tests.
 *
 * @group apigee_edge
 * @group apigee_edge_kernel
 */
class ApigeeAuthKeyInputTest extends KernelTestBase {

  use TestKeyBuilderTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'apigee_edge',
    'key',
  ];

  /**
   * A test key.
   *
   * @var \Drupal\key\KeyInterface
   */
  protected $test_key;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function setUp() {
    parent::setUp();

    // Create a test key.
    $this->generateTestKey();
  }

  /**
   * Test plugin instantiation.
   */
  public function testGetTokenStorage() {
    static::assertInstanceOf(ApigeeAuthKeyInput::class, $this->test_key->getKeyInput());
  }

  /**
   * Test plugin instantiation.
   */
  public function testInputform() {
    $plugin_form_state = new FormState();
    $form = $this->test_key->getKeyInput()->buildConfigurationForm([], $plugin_form_state);

    static::assertSame([
      'auth_type',
      'organization',
      'username',
      'password',
      'endpoint',
      'authorization_server',
      'client_id',
      'client_secret',
      'key_value',
    ], Element::children($form));
  }

  /**
   * Test input processing.
   */
  public function testInputProcessing() {
    // Create a new formstate object.
    $plugin_form_state = new FormState();

    // Generate random input settings for basic auth.
    $input_settings = [
      'auth_type' => EdgeKeyTypeInterface::EDGE_AUTH_TYPE_OAUTH,
      'organization' => strtolower($this->randomMachineName()),
      'username' => strtolower($this->randomMachineName()),
      'password' => $this->randomString(16),
      'endpoint' => '',
      'authorization_server' => '',
      'client_id' => strtolower($this->randomMachineName()),
      'client_secret' => strtolower($this->randomMachineName()),
    ];
    // Set the input values.
    $plugin_form_state->setValues($input_settings);

    // Process the input values.
    $processed_values = $this->test_key->getKeyInput()->processSubmittedKeyValue($plugin_form_state);

    // This is what the key_value should look like.
    $expected = Json::encode(array_filter($input_settings));

    static::assertSame($expected, $processed_values['processed_submitted']);
  }

}
