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

use Drupal\apigee_edge\Exception\KeyProviderRequirementsException;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Utility\Error;
use Drupal\key\KeyInterface;
use Drupal\key\Plugin\KeyPluginFormInterface;
use Drupal\key\Plugin\KeyProviderSettableValueInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Stores Apigee Edge authentication credentials in a private file.
 *
 * @KeyProvider(
 *   id = "apigee_edge_private_file",
 *   label = @Translation("Apigee Edge: Private File"),
 *   description = @Translation("Stores Apigee Edge authentication credentials in a private file.</p>"),
 *   storage_method = "apigee_edge",
 *   key_value = {
 *     "accepted" = TRUE,
 *     "required" = FALSE
 *   }
 * )
 */
class PrivateFileKeyProvider extends KeyProviderRequirementsBase implements KeyPluginFormInterface, KeyProviderSettableValueInterface {

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  private $fileSystem;

  /**
   * PrivateFileKeyProvider constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerInterface $logger, FileSystemInterface $file_system) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger);
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.channel.apigee_edge'),
      $container->get('file_system')
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
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->setConfiguration($form_state->getValues());
  }

  /**
   * {@inheritdoc}
   */
  public function realGetKeyValue(KeyInterface $key) {
    // If the key is new then the file does not exist in the filesystem yet
    // so do not try to read it.
    if ($key->isNew()) {
      return NULL;
    }

    return file_get_contents($this->getFileUri($key)) ?: NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setKeyValue(KeyInterface $key, $key_value) {
    // Throwing an exception would be better than returning FALSE but the key
    // module's design does not allow this.
    // Related issue: https://www.drupal.org/project/key/issues/3038212
    try {
      $this->checkRequirements($key);
    }
    catch (KeyProviderRequirementsException $exception) {
      $context = [
        '@message' => (string) $exception,
      ];
      $context += Error::decodeException($exception);
      $this->getLogger()
        ->error('Could not save Apigee Edge authentication key value in the private file storage: @message %function (line %line of %file). <pre>@backtrace_string</pre>', $context);
      return FALSE;
    }

    $file_uri = $this->getFileUri($key);
    $file_path = $this->getFileSystem()
      ->dirname($file_uri);

    // Make sure the folder exists.
    $this->getFileSystem()
      ->prepareDirectory($file_path, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    try {
      // Save the token data.
      return $this->getFileSystem()
        ->saveData($key_value, $file_uri, FileSystemInterface::EXISTS_REPLACE);
    }
    catch (FileException $e) {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteKeyValue(KeyInterface $key) {
    try {
      $this->getFileSystem()
        ->delete($this->getFileUri($key));
    }
    catch (FileException $e) {
      // Do nothing.
    }
  }

  /**
   * {@inheritdoc}
   */
  public function checkRequirements(KeyInterface $key): void {
    // Validate private file path is set.
    $file_private_path = $this->getFileSystem()->realpath('private://');
    if (!(bool) $file_private_path) {
      throw new KeyProviderRequirementsException('Private filesystem has not been configured yet.', $this->t("Private filesystem has not been configured yet. <a href=':file_docs_uri' target='_blank'>Learn more</a>", [
        ':file_docs_uri' => 'https://www.drupal.org/docs/8/modules/apigee-edge/configure-the-connection-to-apigee-edge#configure-private-file',
      ]));
    }
    // Validate private file path is writable.
    if (!is_writable($file_private_path)) {
      throw new KeyProviderRequirementsException('The private file path is not writable.', $this->t('The private file path is not writable.'));
    }
    // Validate private file path is a directory.
    if (!is_dir($file_private_path)) {
      throw new KeyProviderRequirementsException('The private file path does not exist.', $this->t('The private file path does not exist.'));
    }
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
  protected function getFileUri(KeyInterface $key): string {
    return "private://.apigee_edge/{$key->id()}.json";
  }

  /**
   * Gets the file system service.
   *
   * @return \Drupal\Core\File\FileSystemInterface
   *   The file system service.
   */
  protected function getFileSystem(): FileSystemInterface {
    // This fallback is needed when the plugin instance is serialized and the
    // property is null.
    return $this->fileSystem ?? \Drupal::service('file_system');
  }

}
