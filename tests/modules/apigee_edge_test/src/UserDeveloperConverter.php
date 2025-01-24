<?php

/**
 * Copyright 2018 Google Inc.
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

namespace Drupal\apigee_edge_test;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\apigee_edge\FieldAttributeConverterInterface;
use Drupal\apigee_edge\Plugin\FieldStorageFormatManagerInterface;
use Drupal\apigee_edge\Structure\UserToDeveloperConversionResult;
use Drupal\apigee_edge\UserDeveloperConverter as DecoratedUserDeveloperConverter;
use Drupal\apigee_edge\UserDeveloperConverterInterface;
use Drupal\user\UserInterface;

/**
 * Service decorator for user-developer converter.
 */
final class UserDeveloperConverter extends DecoratedUserDeveloperConverter {

  public const DRUPAL_ROLE_ATTRIBUTE_NAME = 'DP_USER_ROLES';

  /**
   * The decorated user-developer converter service.
   *
   * @var \Drupal\apigee_edge\UserDeveloperConverterInterface
   */
  private $innerService;

  /**
   * The service ID.
   *
   * @var string
   */
  public $_serviceId;

  /**
   * UserToDeveloper constructor.
   *
   * @param \Drupal\apigee_edge\UserDeveloperConverterInterface $inner_service
   *   The decorated user-developer converter service.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   Config factory service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\apigee_edge\Plugin\FieldStorageFormatManagerInterface $field_storage_manager
   *   Field storage manager service.
   * @param \Drupal\apigee_edge\FieldAttributeConverterInterface $field_attribute_converter
   *   Field name to attribute name converter service.
   */
  public function __construct(UserDeveloperConverterInterface $inner_service, ConfigFactory $config_factory, EntityTypeManagerInterface $entity_type_manager, FieldStorageFormatManagerInterface $field_storage_manager, FieldAttributeConverterInterface $field_attribute_converter) {
    $this->configFactory = $config_factory;
    $this->fieldStorageFormatManager = $field_storage_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->fieldAttributeConverter = $field_attribute_converter;
    $this->innerService = $inner_service;
    parent::__construct($config_factory, $entity_type_manager, $field_storage_manager, $field_attribute_converter);
  }

  /**
   * Push but do not sync Drupal user roles to Apigee Edge.
   *
   * {@inheritdoc}
   */
  public function convertUser(UserInterface $user): UserToDeveloperConversionResult {
    $original_result = parent::convertUser($user);
    $original_result->getDeveloper()->setAttribute(static::DRUPAL_ROLE_ATTRIBUTE_NAME, implode(',', $user->getRoles()));
    return new UserToDeveloperConversionResult($original_result->getDeveloper(), $original_result->getSuccessfullyAppliedChanges() + 1, $original_result->getProblems());
  }

}
