<?php

/**
 * Copyright 2024 Google Inc.
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301,
 * USA.
 */

namespace Drupal\apigee_edge\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\apigee_edge\Entity\Controller\OrganizationController;
use Drupal\apigee_edge\SDKConnectorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the LowercaseEmail constraint.
 */
class LowercaseEmailConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The internal entity cache.
   *
   * @var \Apigee\Edge\Api\Management\Entity\OrganizationInterface[]
   */
  private $cache = [];

  /**
   * The organization controller service.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\OrganizationController
   */
  private $orgController;

  /**
   * The sdk comnector service.
   *
   * @var \Drupal\apigee_edge\SDKConnectorInterface
   */
  private $sdkConnector;

  /**
   * Constructs a ValidReferenceConstraintValidator object.
   *
   * @param \Drupal\apigee_edge\SDKConnectorInterface $sdk_connector
   *   The SDK connector service.
   * @param \Drupal\apigee_edge\Entity\Controller\OrganizationController $org_controller
   *   The organization controller service.
   */
  public function __construct(SDKConnectorInterface $sdk_connector, OrganizationController $org_controller) {
    $this->sdkConnector = $sdk_connector;
    $this->orgController = $org_controller;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('apigee_edge.sdk_connector'),
      $container->get('apigee_edge.controller.organization')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    try {
      if (!isset($this->cache[$this->sdkConnector->getOrganization()])) {
        $this->cache[$this->sdkConnector->getOrganization()] = $this->orgController;
      }
      // Check if organization is ApigeeX.
      if ($this->cache[$this->sdkConnector->getOrganization()]->isOrganizationApigeeX()) {
        foreach ($value as $item) {
          if (preg_match('/[A-Z]/', $item->value)) {
            // The value contains uppercase character, the error, is applied.
            $this->context->addViolation($constraint->notLowercase, ['%value' => $item->value]);
          }
        }
      }
    }
    catch (\Exception $e) {
      // If not able to connect to Apigee Edge.
      \Drupal::logger('apigee_edge')->error($e->getMessage());
    }
  }

}
