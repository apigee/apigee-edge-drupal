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

namespace Drupal\Tests\apigee_edge\Kernel\Form;

use Drupal\apigee_edge\Form\AuthenticationForm;
use Drupal\apigee_edge\Plugin\EdgeKeyTypeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\key\Entity\Key;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Test helpers of the authentication form.
 *
 * @group apigee_edge
 * @group apigee_edge_kernel
 */
class AuthenticationFormTest extends KernelTestBase {

  private const PRIVATE_FILE_DIR = 'private';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'apigee_edge',
    'key',
  ];

  /**
   * Token storage.
   *
   * @var \Apigee\Edge\HttpClient\Plugin\Authentication\OauthTokenStorageInterface
   */
  protected $tokenStorage;

  /**
   * Test token data.
   *
   * @var array
   */
  protected $tokenData;

  /**
   * Returns the URI of the token directory.
   *
   * @return string
   *   Token directory URI.
   */
  private function privateFileDirectoryUri(): string {
    return $this->vfsRoot->url() . '/' . static::PRIVATE_FILE_DIR;
  }

  /**
   * Test generating a new auth key.
   *
   * @throws \Drupal\Core\Form\EnforcedResponseException
   * @throws \Drupal\Core\Form\FormAjaxException
   */
  public function testGenerateNewAuthKey() {

    // Add file_private_path setting.
    mkdir($this->privateFileDirectoryUri(), 0775);
    $this->setSetting('file_private_path', $this->privateFileDirectoryUri());
    // Make sure the directory exists.
    $this->assertDirectoryExists($this->privateFileDirectoryUri());

    // Rebuild the form.
    $form_state = new FormState();
    $form = \Drupal::formBuilder()->buildForm(AuthenticationForm::class, $form_state);

    // The form should have created a new key and saved some empty values to it.
    $active_key = Key::load($this->config(AuthenticationForm::CONFIG_NAME)->get('active_key'));
    $this->assertSame($active_key->id(), $form_state->getFormObject()->getEntity()->id());

    $key_value = $active_key->getKeyValue();
    $decoded = Json::decode($key_value);

    // Get the key contents directly from the file (location test).
    $this->assertSame($key_value, file_get_contents("private://.apigee_edge/{$active_key->id()}.json"));

    $this->assertSame(EdgeKeyTypeInterface::EDGE_AUTH_TYPE_BASIC, $form['connection_settings']['auth_type']['#value']);
    $this->assertEmpty($form['connection_settings']['organization']['#value']);
    $this->assertEmpty($form['connection_settings']['username']['#value']);
    $this->assertEmpty($form['connection_settings']['password']['#value']);
    $this->assertSame(EdgeKeyTypeInterface::EDGE_AUTH_TYPE_BASIC, $decoded['auth_type']);
  }

  /**
   * Test private file path errors.
   *
   * @throws \Drupal\Core\Form\EnforcedResponseException
   * @throws \Drupal\Core\Form\FormAjaxException
   */
  public function testPrivateFileSystemPathErrors() {
    $form_state = new FormState();
    $form = \Drupal::formBuilder()->buildForm(AuthenticationForm::class, $form_state);
    $this->assertInstanceOf(AuthenticationForm::class, $form_state->getFormObject());

    // Private file system is not configured.
    $this->assertNotEmpty($form['connection_settings']['unconfigurable']['description']);
    $this->assertTrue($form['actions']['#disabled']);
    $this->assertEquals('The Drupal private file setting has not been configured.', $form['connection_settings']['unconfigurable']['label']['#value']);

    // Add file_private_path setting, but not the private file dir.
    $this->setSetting('file_private_path', $this->privateFileDirectoryUri());

    // Rebuild the form.
    $form_state = new FormState();
    $form = \Drupal::formBuilder()->buildForm(AuthenticationForm::class, $form_state);

    // The private file path dir does not exist.
    $this->assertNotEmpty($form['connection_settings']['unconfigurable']['description']);
    $this->assertTrue($form['actions']['#disabled']);

    $this->assertEquals(new TranslatableMarkup('The private file path %path does not exist.', ['%path' => $this->privateFileDirectoryUri()]), $form['connection_settings']['unconfigurable']['label']['#value']);

    // Make sure the directory exists but not writable.
    mkdir($this->privateFileDirectoryUri(), 0000);
    $this->assertDirectoryExists($this->privateFileDirectoryUri());

    // Rebuild the form.
    $form_state = new FormState();
    $form = \Drupal::formBuilder()->buildForm(AuthenticationForm::class, $form_state);

    // Make sure error is shown on page, and form is not available.
    $this->assertNotEmpty($form['connection_settings']['unconfigurable']['description']);
    $this->assertTrue($form['actions']['#disabled']);
    $this->assertEquals(new TranslatableMarkup('The private file path %path is not writable.', ['%path' => $this->privateFileDirectoryUri()]), $form['connection_settings']['unconfigurable']['label']['#value']);

    // Make private file path writable.
    $this->container->get('file_system')->chmod($this->privateFileDirectoryUri(), 755);

    // Rebuild the form.
    $form_state = new FormState();
    $form = \Drupal::formBuilder()->buildForm(AuthenticationForm::class, $form_state);

    // Make sure error is shown on page, and form is not available.
    $this->assertArrayNotHasKey('unconfigurable', $form['connection_settings']);
    $this->assertFalse($form['actions']['#disabled']);
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    parent::register($container);
    $container->register('stream_wrapper.private', 'Drupal\Core\StreamWrapper\PrivateStream')
      ->addTag('stream_wrapper', ['scheme' => 'private']);
  }

}
