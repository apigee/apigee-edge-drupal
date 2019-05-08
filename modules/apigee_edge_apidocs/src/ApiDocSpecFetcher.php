<?php

/**
 * Copyright 2019 Google Inc.
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

namespace Drupal\apigee_edge_apidocs;

use Drupal\apigee_edge_apidocs\Entity\ApiDocInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Class ApiDocSpecFetcher.
 */
class ApiDocSpecFetcher implements ApiDocSpecFetcherInterface {

  use StringTranslationTrait;

  /**
   * Drupal\Core\File\FileSystemInterface definition.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * GuzzleHttp\ClientInterface definition.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Messenger\MessengerInterface definition.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private $logger;

  /**
   * Constructs a new ApiDocSpecFetcher object.
   *
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file_system service.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The http_client service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   */
  public function __construct(FileSystemInterface $file_system, ClientInterface $http_client, EntityTypeManagerInterface $entityTypeManager, MessengerInterface $messenger, LoggerInterface $logger) {
    $this->fileSystem = $file_system;
    $this->httpClient = $http_client;
    $this->entityTypeManager = $entityTypeManager;
    $this->messenger = $messenger;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public function fetchSpec(ApiDocInterface $apidoc, bool $save = TRUE, bool $new_revision = TRUE, bool $show_messages = TRUE) : bool {
    $needs_save = FALSE;
    $spec_value = $apidoc->get('spec')->isEmpty() ? [] : $apidoc->get('spec')->getValue()[0];

    // If "spec_file_source" uses URL, grab file from "file_link" and save it
    // into the "spec" file field. The file_link field should already have
    // validated that a valid file exists at that URL.
    if ($apidoc->get('spec_file_source')->value == ApiDocInterface::SPEC_AS_URL) {

      // If the file_link field is empty, return without error.
      if ($apidoc->get('file_link')->isEmpty()) {
        return TRUE;
      }

      $file_uri = $apidoc->get('file_link')->getValue()[0]['uri'];
      $data = file_get_contents($file_uri);
      if (empty($data)) {
        $message = 'Could not retrieve OpenAPI specification file located at %url';
        $params = [
          '%url' => $file_uri,
        ];
        $this->logger->error($message, $params);
        if ($show_messages) {
          $this->messenger->addMessage($this->t($message, $params), 'error');
        }
        return FALSE;
      }

      // Only save file if it hasn't been fetched previously.
      $data_md5 = md5($data);
      $prev_md5 = $apidoc->get('spec_md5')->isEmpty() ? NULL : $apidoc->get('spec_md5')->value;
      if ($prev_md5 != $data_md5) {
        $filename = $this->fileSystem->basename($file_uri);
        $specs_definition = $apidoc->getFieldDefinition('spec')->getItemDefinition();
        $target_dir = $specs_definition->getSetting('file_directory');
        $uri_scheme = $specs_definition->getSetting('uri_scheme');
        $file = file_save_data($data, "$uri_scheme://$target_dir/$filename", FILE_EXISTS_RENAME);
        $spec_value = [
            'target_id' => $file->id(),
          ] + $spec_value;
        $apidoc->set('spec', $spec_value);
        $apidoc->set('spec_md5', $data_md5);

        $needs_save = TRUE;
      }
    }

    elseif (!empty($spec_value['target_id'])) {
      /* @var \Drupal\file\Entity\File $file */
      $file = $this->entityTypeManager
        ->getStorage('file')
        ->load($spec_value['target_id']);

      if ($file) {
        $prev_md5 = $apidoc->get('spec_md5')->isEmpty() ? NULL : $apidoc->get('spec_md5')->value;
        $file_md5 = md5_file($file->getFileUri());
        if ($prev_md5 != $file_md5) {
          $apidoc->set('spec_md5', $file_md5);
          $needs_save = TRUE;
        }
      }
    }

    // Only save if changes were made.
    if ($save && $needs_save) {
      if ($new_revision && $apidoc->getEntityType()->isRevisionable()) {
        $apidoc->setNewRevision();
      }

      try {
        $apidoc->save();
      }
      catch (EntityStorageException $e) {
        $message = 'Error while saving API Doc while fetching OpenAPI specification file located at %url';
        $params = [
          '%url' => $file_uri,
        ];
        $this->logger->error($message, $params);
        if ($show_messages) {
          $this->messenger->addMessage($this->t($message, $params), 'error');
        }
        return FALSE;
      }
    }

    return TRUE;
  }

}
