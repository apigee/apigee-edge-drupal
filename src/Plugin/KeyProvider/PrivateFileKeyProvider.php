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

namespace Drupal\apigee_edge\Plugin\KeyProvider;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\key\Exception\KeyValueNotSetException;
use Drupal\key\Plugin\KeyProviderBase;
use Drupal\key\Plugin\KeyPluginFormInterface;
use Drupal\key\Plugin\KeyProviderSettableValueInterface;
use Drupal\key\KeyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Stores Apigee Edge authentication credentials in a private file.
 *
 * @KeyProvider(
 *   id = "apigee_edge_private_file",
 *   label = @Translation("Apigee Edge: Private File"),
 *   description = @Translation("Stores Apigee Edge authentication credentials in a private file.<p><strong>Warning! </strong>Private file storage is suitable only for testing environments. In production environments, use the <em>Apigee Edge: Environment Variables</em> key provider.</p>"),
 *   storage_method = "apigee_edge",
 *   key_value = {
 *     "accepted" = TRUE,
 *     "required" = FALSE
 *   }
 * )
 */
class PrivateFileKeyProvider extends KeyProviderBase implements KeyPluginFormInterface, KeyProviderSettableValueInterface {

  /**
   * Site settings.
   *
   * @var \Drupal\Core\Site\Settings
   */
  protected $settings;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Settings $settings) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->settings = $settings;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('settings')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    if (empty($this->settings->get('file_private_path'))) {
      $form_state->setError($form, $this->t('The private file system is not configured properly. Visit the <a href=":url">File system</a> settings page to specify the private file system path.', [
        ':url' => Url::fromRoute('system.file_system_settings', ['destination' => 'admin/config/system/keys/add'])->toString(),
      ]));
      return;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->setConfiguration($form_state->getValues());
  }

  /**
   * {@inheritdoc}
   */
  public function getKeyValue(KeyInterface $key) {
    if (empty($this->settings->get('file_private_path')) || !is_file($this->getFileUri($key)) || !is_readable($this->getFileUri($key))) {
      return NULL;
    }

    return file_get_contents($this->getFileUri($key)) ?: NULL;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\key\Exception\KeyValueNotSetException
   *   Thrown when the key cannot be saved.
   */
  public function setKeyValue(KeyInterface $key, $key_value) {
    if (!\file_unmanaged_save_data($key_value, $this->getFileUri($key), FILE_EXISTS_REPLACE)) {
      throw new KeyValueNotSetException();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteKeyValue(KeyInterface $key) {
    return file_unmanaged_delete($this->getFileUri($key));
  }

  /**
   * Gets the URI of the file that contains the key value.
   *
   * @param \Drupal\key\KeyInterface $key
   *   The key entity.
   *
   * @return string
   *   The file URI.
   */
  protected function getFileUri(KeyInterface $key) {
    return "private://{$key->id()}_apigee_edge.json";
  }

}
