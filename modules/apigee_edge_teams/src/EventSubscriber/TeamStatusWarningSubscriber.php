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

namespace Drupal\apigee_edge_teams\EventSubscriber;

use Drupal\apigee_edge_teams\Entity\TeamInterface;
use Drupal\apigee_edge_teams\TeamMembershipManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Displays a warning message on team app pages if app owner is inactive.
 */
class TeamStatusWarningSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $currentUser;

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  private $routeMatch;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  private $messenger;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * TeamStatusWarningSubscriber constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\apigee_edge_teams\TeamMembershipManagerInterface $team_membership_manager
   *   The team membership manager service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translations service.
   */
  public function __construct(AccountInterface $current_user, RouteMatchInterface $route_match, EntityTypeManagerInterface $entity_type_manager, TeamMembershipManagerInterface $team_membership_manager, MessengerInterface $messenger, TranslationInterface $string_translation) {
    $this->routeMatch = $route_match;
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
    $this->stringTranslation = $string_translation;
  }

  /**
   * Display's a warning message if team's status is inactive.
   *
   * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
   *   The event to process.
   */
  public function onRespond(FilterResponseEvent $event) {
    // Anonymous user's does not have access to these routes.
    if ($this->currentUser->isAuthenticated() && strpos($this->routeMatch->getRouteName(), 'entity.team_app.') === 0) {
      // Team is available in most of the team app routes as a route parameter.
      /** @var \Drupal\apigee_edge_teams\Entity\TeamInterface|NULL $team */
      $team = $this->routeMatch->getParameter('team');

      if ($team === NULL) {
        /** @var \Drupal\apigee_edge_teams\Entity\TeamAppInterface $app */
        $app = $this->routeMatch->getParameter('team_app') ?? $this->routeMatch->getParameter('app');
        if ($app) {
          $team = $this->entityTypeManager->getStorage('team')->load($app->getCompanyName());
        }
      }

      if ($team && $team->getStatus() === TeamInterface::STATUS_INACTIVE) {
        $this->messenger->addWarning($this->t('This @team has inactive status so @team members will not be able to use @team_app credentials until the @team gets activated. Please contact support for further assistance.', [
          '@team' => $this->entityTypeManager->getDefinition('team')->getLowercaseLabel(),
          '@team_app' => $this->entityTypeManager->getDefinition('team_app')->getLowercaseLabel(),
        ]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // We had to increase the weight to get the current route and not the
    // referer.
    $events[KernelEvents::RESPONSE][] = ['onRespond', 5];
    return $events;
  }

}
