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

namespace Drupal\Tests\apigee_edge\Kernel\Plugin\KeyType;

use Drupal\apigee_edge\OauthAuthentication;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\apigee_edge\Traits\TestKeyBuilderTrait;

/**
 * OAuth cache storage tests.
 *
 * @group apigee_edge
 * @group apigee_edge_kernel
 */
class ApigeeAuthKeyTypeTest extends KernelTestBase {

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
    $this->populateTestKeyValues();
  }

  /**
   * Test key validaiton.
   *
   * @throws \Drupal\Core\Form\EnforcedResponseException
   * @throws \Drupal\Core\Form\FormAjaxException
   */
  public function testValidateKeyValue() {

    $test_key = $this->test_key;

    $form_object = \Drupal::entityTypeManager()->getFormObject('key', 'edit');
    $form_object->setEntity($test_key);

    $form_state = new FormState();
    $form = \Drupal::formBuilder()->buildForm($form_object, $form_state);

    $key_value = $test_key->getKeyValue();
    $test_key->getKeyType()->validateKeyValue($form, $form_state, $key_value);
    static::assertEmpty($form_state->getErrors());
  }

  /**
   * Test that the test key returns an Oauth authentication method.
   */
  public function testGetAuthenticationMethod() {

    $test_key = $this->test_key;

    $auth = $test_key->getKeyType()->getAuthenticationMethod($test_key);

    static::assertInstanceOf(OauthAuthentication::class, $auth);
  }

}
