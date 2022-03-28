<?php

/**
 * Copyright 2020 Google Inc.
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
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Displays an error message on team pages if team is inactive.
 */
class TeamInactiveStatusSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The class resolver service.
   *
   * @var \Drupal\Core\Controller\ControllerResolverInterface
   */
  protected $classResolver;

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The available main content renderer services, keyed per format.
   *
   * @var array
   */
  protected $mainContentRenderers;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * TeamInactiveStatusSubscriber constructor.
   *
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $class_resolver
   *   The class resolver service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match service.
   * @param array $main_content_renderers
   *   The available main content renderer service IDs.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(ClassResolverInterface $class_resolver, RouteMatchInterface $route_match, array $main_content_renderers, AccountInterface $current_user) {
    $this->classResolver = $class_resolver;
    $this->routeMatch = $route_match;
    $this->mainContentRenderers = $main_content_renderers;
    $this->currentUser = $current_user;
  }

  /**
   * Display an error message on inactive team routes.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The event to process.
   */
  public function onRespond(ResponseEvent $event) {
    if ($this->currentUser->isAnonymous() || !in_array($this->routeMatch->getRouteName(), $this->getDisabledRoutes())) {
      return;
    }

    /** @var \Drupal\apigee_edge_teams\Entity\TeamInterface $team */
    $team = $this->routeMatch->getParameter('team');
    if (!$team || $team->getStatus() !== TeamInterface::STATUS_INACTIVE) {
      return;
    }

    $content = [
      'content' => [
        '#theme' => 'status_messages',
        '#message_list' => [
          'error' => [
            $this->t('The %team_name @team is inactive. This operation is not allowed.', [
              '%team_name' => $team->label(),
              '@team' => $team->getEntityType()->getSingularLabel(),
            ]),
          ],
        ],
      ],
    ];

    $renderer = $this->classResolver->getInstanceFromDefinition($this->mainContentRenderers['html']);
    /* @var \Symfony\Component\HttpFoundation\Response $response */
    $response = $renderer->renderResponse($content, $event->getRequest(), $this->routeMatch);
    $response->setStatusCode(Response::HTTP_FORBIDDEN);

    $event->setResponse($response);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = ['onRespond', 5];
    return $events;
  }

  /**
   * Returns an array of route names for which the warning message should be displayed.
   *
   * @return array
   *   An array of route names.
   */
  public static function getDisabledRoutes(): array {
    return [
      // Team.
      'entity.team.edit_form',
      'entity.team.delete_form',

      // Team app.
      'entity.team_app.add_form_for_team',
      'entity.team_app.edit_form',
      'entity.team_app.delete_form',
      'entity.team_app.analytics',

      // Team member.
      'entity.team.add_members',
      'entity.team.member.edit',
      'entity.team.member.remove',

      // Team invitation.
      'entity.team_invitation.resend_form',
      'entity.team_invitation.delete_form',
    ];
  }

}
