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

namespace Drupal\apigee_edge\Entity\Controller;

use Apigee\Edge\Api\Management\Controller\AppByOwnerControllerInterface as EdgeAppByOwnerControllerInterface;
use Apigee\Edge\Api\Management\Controller\DeveloperAppController as EdgeDeveloperAppController;
use Drupal\apigee_edge\Entity\Controller\Cache\DeveloperAppCacheInterface;
use Drupal\apigee_edge\SDKConnectorInterface;
use Egulias\EmailValidator\EmailValidatorInterface;

/**
 * Definition of the developer app controller service.
 *
 * This integrates the Management API's developer app controller from the
 * SDK's with Drupal. It uses a shared (not internal) app cache to reduce the
 * number of API calls that we send to Apigee Edge.
 *
 * TODO Leverage cache in those methods that works with app ids not app object.
 */
final class DeveloperAppController extends AppByOwnerController implements DeveloperAppControllerInterface {

  /**
   * The email validator service.
   *
   * @var \Egulias\EmailValidator\EmailValidatorInterface
   */
  private $emailValidator;

  /**
   * DeveloperAppController constructor.
   *
   * @param string $owner
   *   Developer's email address or id (uuid).
   * @param \Drupal\apigee_edge\SDKConnectorInterface $connector
   *   The SDK connector service.
   * @param \Drupal\apigee_edge\Entity\Controller\OrganizationControllerInterface $org_controller
   *   The organization controller service.
   * @param \Drupal\apigee_edge\Entity\Controller\Cache\DeveloperAppCacheInterface $app_cache
   *   The app cache.
   * @param \Egulias\EmailValidator\EmailValidatorInterface $email_validator
   *   The email validator service.
   */
  public function __construct(string $owner, SDKConnectorInterface $connector, OrganizationControllerInterface $org_controller, DeveloperAppCacheInterface $app_cache, EmailValidatorInterface $email_validator) {
    parent::__construct($owner, $connector, $org_controller, $app_cache);
    $this->emailValidator = $email_validator;
  }

  /**
   * {@inheritdoc}
   */
  protected function decorated(): EdgeAppByOwnerControllerInterface {
    return new EdgeDeveloperAppController($this->connector->getOrganization(), $this->owner, $this->connector->getClient(), NULL, $this->organizationController);
  }

  /**
   * {@inheritdoc}
   */
  public function getEntities(): array {
    /** @var \Apigee\Edge\Api\Management\Entity\DeveloperAppInterface[] $entities */
    $entities = parent::getEntities();
    // If owner contains the email address of the developer then also add a
    // mark by app list by its developer id (uuid).
    if (!empty($entities) && $this->emailValidator->isValid($this->owner)) {
      $entity = reset($entities);
      $this->appCache->allAppsLoadedForOwner($entity->getDeveloperId());
    }
    return $entities;
  }

}
