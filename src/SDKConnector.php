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

namespace Drupal\apigee_edge;

use Apigee\Edge\Api\Management\Controller\OrganizationController;
use Apigee\Edge\Controller\EntityCrudOperationsControllerInterface;
use Apigee\Edge\Exception\ApiException;
use Apigee\Edge\Exception\UnknownEndpointException;
use Apigee\Edge\HttpClient\Client;
use Apigee\Edge\HttpClient\ClientInterface;
use Apigee\Edge\HttpClient\Utility\Builder;
use Drupal\apigee_edge\Entity\Storage\EdgeEntityStorageInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\InfoParserInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\key\Exception\KeyValueNotRetrievedException;
use Drupal\key\KeyInterface;
use Drupal\key\KeyRepositoryInterface;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use Http\Adapter\Guzzle6\Client as GuzzleClientAdapter;

/**
 * Provides an Apigee Edge SDK connector.
 */
class SDKConnector implements SDKConnectorInterface {

  /**
   * The client object.
   *
   * @var null|\Http\Client\HttpClient
   */
  private static $client = NULL;

  /**
   * The currently used key entity.
   *
   * @var null|\Drupal\key\KeyInterface
   */
  private static $key = NULL;

  /**
   * Custom user agent prefix.
   *
   * @var null|string
   */
  private static $userAgentPrefix = NULL;

  /**
   * The key repository.
   *
   * @var \Drupal\key\KeyRepositoryInterface
   */
  protected $keyRepository;

  /**
   * The active key ID.
   *
   * @var string
   */
  protected $keyId;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Guzzle client.
   *
   * @var \Http\Client\HttpClient
   */
  protected $httpClient;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The info parser.
   *
   * @var \Drupal\Core\Extension\InfoParserInterface
   */
  protected $infoParser;

  /**
   * Constructs a new SDKConnector.
   *
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   Http client.
   * @param \Drupal\key\KeyRepositoryInterface $key_repository
   *   The key repository.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Module handler service.
   * @param \Drupal\Core\Extension\InfoParserInterface $infoParser
   *   Info file parser service.
   */
  public function __construct(GuzzleClientInterface $httpClient, KeyRepositoryInterface $key_repository, EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory, ModuleHandlerInterface $moduleHandler, InfoParserInterface $infoParser) {
    $this->httpClient = new GuzzleClientAdapter($httpClient);
    $this->entityTypeManager = $entity_type_manager;
    $this->keyRepository = $key_repository;
    $this->keyId = $config_factory->get('apigee_edge.authentication')->get('active_key');
    $this->moduleHandler = $moduleHandler;
    $this->infoParser = $infoParser;
  }

  /**
   * {@inheritdoc}
   */
  public function getOrganization(): string {
    /** @var \Drupal\apigee_edge\Plugin\EdgeKeyTypeInterface $key_type */
    $key_type = $this->getKey()->getKeyType();
    return $key_type->get($this->getKey(), 'organization');
  }

  /**
   * {@inheritdoc}
   */
  public function getClient() : ? ClientInterface {
    if ($this->getKey() === NULL) {
      self::$client = NULL;
      return NULL;
    }
    if (NULL === self::$client) {
      $builder = new Builder($this->httpClient);
      /** @var \Drupal\apigee_edge\Plugin\EdgeKeyTypeInterface $key_type */
      $key_type = $this->getKey()->getKeyType();
      self::$client = new Client($key_type->getAuthenticationMethod($this->getKey()), $builder, $key_type->get($this->getKey(), 'endpoint'), $this->userAgentPrefix());
    }
    return self::$client;
  }

  /**
   * Returns the key object used by the API client.
   *
   * @return null|\Drupal\key\KeyInterface
   *   The key entity.
   */
  private function getKey() : ? KeyInterface {
    if (NULL === self::$key) {
      if ($this->keyId === NULL) {
        throw new ApiException('Apigee Edge authentication key is not set.');
      }
      $key = $this->keyRepository->getKey($this->keyId);
      if ($key === NULL) {
        throw new ApiException('Apigee Edge authentication key is not set.');
      }
      self::$key = $key;
    }

    return self::$key;
  }

  /**
   * Changes key used by the API client.
   *
   * @param \Drupal\key\KeyInterface $key
   *   The key entity.
   */
  private function setKey(KeyInterface $key) {
    self::$key = $key;
    $this->keyId = $key === NULL ?: $key->id();
    // Ensure that client will be rebuilt with the new key.
    self::$client = NULL;
  }

  /**
   * Generates a custom user agent prefix.
   */
  protected function userAgentPrefix() : string {
    if (NULL === self::$userAgentPrefix) {
      $moduleInfo = $this->infoParser->parse($this->moduleHandler->getModule('apigee_edge')->getPathname());
      if (!isset($moduleInfo['version'])) {
        $moduleInfo['version'] = '8.x-1.0-dev';
      }
      self::$userAgentPrefix = $moduleInfo['name'] . ' ' . $moduleInfo['version'];
    }

    return self::$userAgentPrefix;
  }

  /**
   * {@inheritdoc}
   */
  public function getControllerByEntity(string $entity_type) :  EntityCrudOperationsControllerInterface {
    $controller = $this->entityTypeManager->getStorage($entity_type);
    if ($controller instanceof EdgeEntityStorageInterface) {
      return $controller->getController($this);
    }

    throw new UnknownEndpointException($entity_type);
  }

  /**
   * {@inheritdoc}
   */
  public function testConnection(KeyInterface $key = NULL) {
    if (NULL !== $key) {
      try {
        $originalKey = self::getKey();
      }
      catch (ApiException $e) {
        // Skip key not set exception if there is no currently used key.
      }
      self::setKey($key);
    }
    try {
      $oc = new OrganizationController($this->getClient());
      /** @var \Drupal\apigee_edge\Plugin\EdgeKeyTypeInterface $key_type */
      $key_type = $this->getKey()->getKeyType();

      if (($organization = $key_type->get($this->getKey(), 'organization')) === NULL) {
        throw new KeyValueNotRetrievedException('Could not read the key storage. Key ID: ' . $this->getKey()->id());
      }
      $oc->load($organization);
    }
    catch (\Exception $e) {
      throw $e;
    }
    finally {
      if (isset($originalKey)) {
        self::setKey($originalKey);
      }
    }
  }

}
