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
use Drupal\Component\Datetime\DateTimePlus;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
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
  public function fetchSpec(ApiDocInterface $apidoc, bool $show_messages = TRUE): bool {
    $needs_save = FALSE;
    $spec_value = $apidoc->get('spec')->isEmpty() ? [] : $apidoc->get('spec')->getValue()[0];

    // If "spec_file_source" uses URL, grab file from "file_link" and save it
    // into the "spec" file field. The file_link field should already have
    // validated that a valid file exists at that URL.
    if ($apidoc->get('spec_file_source')->value == ApiDocInterface::SPEC_AS_URL) {

      // If the file_link field is empty, return without changes.
      if ($apidoc->get('file_link')->isEmpty()) {
        return FALSE;
      }

      $file_uri = $apidoc->get('file_link')->getValue()[0]['uri'];
      $file_uri = Url::fromUri($file_uri, ['absolute' => TRUE])->toString();
      $request = new Request('GET', $file_uri);
      $options = [
        'exceptions' => TRUE,
        'allow_redirects' => [
          'strict' => TRUE,
        ],
      ];

      // Generate conditional GET header.
      if (!$apidoc->get('fetched_timestamp')->isEmpty()) {
        $request = $request->withAddedHeader('If-Modified-Since', gmdate(DateTimePlus::RFC7231, $apidoc->get('fetched_timestamp')->value));
      }

      try {
        $response = $this->httpClient->send($request, $options);

        // In case of a 304 Not Modified there are no changes, but update
        // last fetched timestamp.
        if ($response->getStatusCode() == 304) {
          $apidoc->set('fetched_timestamp', time());
          return TRUE;
        }
      }
      catch (RequestException $e) {
        $this->log($apidoc, static::TYPE_ERROR, 'API Doc %label: Could not retrieve OpenAPI specification file located at %url.', [
          '%url' => $file_uri,
          '%label' => $apidoc->label(),
        ], $show_messages);
        return FALSE;
      }

      $data = (string) $response->getBody();
      if (empty($data)) {
        $this->log($apidoc, static::TYPE_ERROR, 'API Doc %label: OpenAPI specification file located at %url is empty.', [
          '%url' => $file_uri,
          '%label' => $apidoc->label(),
        ], $show_messages);
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
        $destination = "$uri_scheme://$target_dir/";

        try {
          $this->checkRequirements($destination);
          $file = file_save_data($data, $destination . $filename, FILE_EXISTS_RENAME);

          if (empty($file)) {
            throw new \Exception('Could not save API Doc specification file.');
          }
        }
        catch (\Exception $e) {
          $this->log($apidoc, static::TYPE_ERROR, 'Error while saving API Doc spec file from URL on API Doc ID: %id. Error: %error', [
            '%id' => $apidoc->id(),
            '%error' => $e->getMessage(),
          ], $show_messages);
          return FALSE;
        }

        $spec_value = ['target_id' => $file->id()] + $spec_value;
        $apidoc->set('spec', $spec_value);
        $apidoc->set('spec_md5', $data_md5);
        $apidoc->set('fetched_timestamp', time());

        $needs_save = TRUE;
      }
    }

    elseif ($apidoc->get('spec_file_source')->value == ApiDocInterface::SPEC_AS_FILE) {
      $this->log($apidoc, static::TYPE_STATUS, 'API Doc %label is using a file upload as source. Nothing to update.', [
        '%label' => $apidoc->label(),
      ], $show_messages);
      $needs_save = FALSE;
    }

    return $needs_save;
  }

  /**
   * Log a message, and optionally display it on the UI.
   *
   * @param \Drupal\apigee_edge_apidocs\Entity\ApiDocInterface $apidoc
   *   The API Doc entity.
   * @param string $type
   *   Type of message.
   * @param string $message
   *   The message.
   * @param array $params
   *   Optional parameters array.
   * @param bool $show_messages
   *   TRUE if message should be displayed to the UI as well.
   */
  private function log(ApiDocInterface $apidoc, string $type, string $message, array $params = [], bool $show_messages = TRUE) {
    switch ($type) {
      case static::TYPE_ERROR:
        $this->logger->error($message, $params);
        break;

      case static::TYPE_STATUS:

      default:
        $this->logger->info($message, $params);
    }
    if ($show_messages) {
      $this->messenger->addMessage($this->t($message, $params), $type);
    }
  }

  /**
   * Checks requirements for saving of a file spec.
   *
   * If a requirement is not fulfilled it throws an exception.
   *
   * @param string $destination
   *   The specification file destination directory, including scheme.
   *
   * @throws \Exception
   */
  private function checkRequirements(string $destination): void {
    // If using private filesystem, check that it's been configured.
    if (strpos($destination, 'private://') === 0 && !$this->isPrivateFileSystemConfigured()) {
      throw new \Exception('Private filesystem has not been configured.');
    }

    if (!file_prepare_directory($destination, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS)) {
      throw new \Exception('Could not prepare API Doc specification file destination directory.');
    }
  }

  /**
   * Checks whether the private filesystem is configured.
   *
   * @return bool
   *   True if configured, FALSE otherwise.
   */
  private function isPrivateFileSystemConfigured(): bool {
    return (bool) $this->fileSystem->realpath('private://');
  }

}
