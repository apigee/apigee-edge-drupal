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

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\apigee_edge\Entity\Controller\OrganizationControllerInterface;
use Drupal\apigee_edge\SDKConnectorInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Validates that Apigee X Team is enabled on every Team page request.
 */
final class ValidateApigeeXTeamEnabledSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  private AccountInterface $currentUser;

  /**
   * The SDK connector service.
   *
   * @var \Drupal\apigee_edge\SDKConnectorInterface
   */
  private SDKConnectorInterface $connector;

  /**
   * The organization controller service.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\OrganizationControllerInterface
   */
  protected OrganizationControllerInterface $orgController;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected MessengerInterface $messenger;

  /**
   * ValidateApigeeXTeamEnabledSubscriber constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\apigee_edge\SDKConnectorInterface $sdk_connector
   *   The SDK connector service.
   * @param \Drupal\apigee_edge\Entity\Controller\OrganizationControllerInterface $org_controller
   *   The organization controller service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(AccountInterface $current_user, SDKConnectorInterface $sdk_connector, OrganizationControllerInterface $org_controller, MessengerInterface $messenger) {
    $this->currentUser = $current_user;
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
  public function validateApigeexTeamEnabled(RequestEvent $event): void {
    // Check only for html request and admin users.
    if ($this->currentUser->hasPermission('administer modules') && $event->getRequest()->getRequestFormat() === 'html') {
      /** @var \Symfony\Component\Routing\Route $current_route */
      if (($current_route = $event->getRequest()->get('_route')) && (strpos($current_route, 'entity.team') !== FALSE || strpos($current_route, 'settings.team') !== FALSE)) {
        $organization = $this->orgController->load($this->connector->getOrganization());
        if ($organization && $this->orgController->isOrganizationApigeeX()) {
          if ($organization->getAddonsConfig() && $organization->getAddonsConfig()->getMonetizationConfig() && TRUE === $organization->getAddonsConfig()->getMonetizationConfig()->getEnabled()) {
            $this->messenger->addError($this->t('The Teams module functionality is not available for monetization enabled org on Apigee X / Hybrid and should be uninstalled.'));
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::REQUEST][] = ['validateApigeexTeamEnabled'];
    return $events;
  }

}
