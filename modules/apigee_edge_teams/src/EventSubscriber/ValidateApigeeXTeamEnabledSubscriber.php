<?php

/*
 * Copyright 2023 Google Inc.
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

namespace Drupal\apigee_edge_teams\EventSubscriber;

use Drupal\apigee_edge\Entity\Controller\OrganizationControllerInterface;
use Drupal\apigee_edge\SDKConnectorInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Validates that Apigee X Team is enabled on Team list page.
 */
class ValidateApigeeXTeamEnabledSubscriber implements EventSubscriberInterface {

  /**
   * The SDK connector service.
   *
   * @var \Drupal\apigee_edge\SDKConnectorInterface
   */
  private $connector;

  /**
   * The organization controller service.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\OrganizationControllerInterface
   */
  protected $orgController;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * ValidateApigeeXTeamEnabledSubscriber constructor.
   *
   * @param \Drupal\apigee_edge\SDKConnectorInterface $sdk_connector
   *   The SDK connector service.
   * @param \Drupal\apigee_edge\Entity\Controller\OrganizationControllerInterface $org_controller
   *   The organization controller service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(SDKConnectorInterface $sdk_connector, OrganizationControllerInterface $org_controller, MessengerInterface $messenger) {
    $this->connector = $sdk_connector;
    $this->orgController = $org_controller;
    $this->messenger = $messenger;
  }

  /**
   * If monetization enabled in Apigee X org alert the user.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The event.
   */
  public function validateApigeeXTeamEnabled(RequestEvent $event) {
    /** @var \Symfony\Component\Routing\Route $current_route */
    if (($current_route = $event->getRequest()->get('_route')) && ($current_route === 'entity.team.collection')) {
      $organization = $this->orgController->load($this->connector->getOrganization());
      if ($organization && $this->orgController->isOrganizationApigeeX()) {
        if ($organization->getAddonsConfig() || TRUE === $organization->getAddonsConfig()->getMonetizationConfig()->getEnabled()) {
          $this->messenger->addError('The Teams module functionality is not available for monetization enabled org on Apigee X / Hybrid and should be uninstalled');
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['validateApigeeXTeamEnabled'];
    return $events;
  }

}
