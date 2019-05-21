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

namespace Drupal\apigee_edge\EventSubscriber;

use Drupal\apigee_edge\Entity\DeveloperInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Displays a warning message on developer app pages if app owner is inactive.
 */
final class DeveloperStatusWarningSubscriber implements EventSubscriberInterface {

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
   * DeveloperStatusWarningSubscriber constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translations service.
   */
  public function __construct(AccountInterface $current_user, RouteMatchInterface $route_match, EntityTypeManagerInterface $entity_type_manager, MessengerInterface $messenger, TranslationInterface $string_translation) {
    $this->routeMatch = $route_match;
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
    $this->stringTranslation = $string_translation;
  }

  /**
   * Display's a warning message if developer's status is inactive.
   *
   * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
   *   The event to process.
   */
  public function onRespond(FilterResponseEvent $event) {
    // Anonymous user's does not have access to these routes.
    if ($this->currentUser->isAuthenticated() && strpos($this->routeMatch->getRouteName(), 'entity.developer_app.') === 0) {
      $developer_storage = $this->entityTypeManager->getStorage('developer');
      /** @var \Drupal\apigee_edge\Entity\DeveloperInterface|NULL $developer */
      $developer = NULL;
      /** @var \Drupal\Core\Session\AccountInterface|NULL $account */
      $account = NULL;
      /** @var \Drupal\apigee_edge\Entity\DeveloperAppInterface $app */
      $app = $this->routeMatch->getParameter('developer_app') ?? $this->routeMatch->getParameter('app');
      if ($app) {
        /** @var \Drupal\apigee_edge\Entity\DeveloperInterface $developer */
        $developer = $developer_storage->load($app->getDeveloperId());
        $account = $developer->getOwner();
      }
      // Taking special care of the "Apps" page.
      elseif ($this->routeMatch->getRouteName() === 'entity.developer_app.collection_by_developer') {
        /** @var \Drupal\Core\Session\AccountInterface $account */
        $account = $this->routeMatch->getParameter('user');
        $developer = $developer_storage->load($account->getEmail());
      }

      // If we could figure out the developer from the route and its status
      // is inactive.
      if ($developer && $developer->getStatus() === DeveloperInterface::STATUS_INACTIVE) {
        if ($this->currentUser->getEmail() === $developer->getEmail()) {
          $message = $this->t('Your developer account has inactive status so you will not be able to use your credentials until your account gets activated. Please contact support for further assistance.');
        }
        // Displays different warning if the current user is not the
        // owner of the app.
        else {
          // It could happen that the app's owner (developer) does not have
          // a Drupal user yet. (The two system is out of sync.)
          if ($account) {
            $message = $this->t('The developer account of <a href=":url">@username</a> has inactive status so this user has invalid credentials until the account gets activated.', [
              ':url' => Url::fromRoute('entity.user.edit_form', ['user' => $account->id()])
                ->toString(),
              '@username' => $account->getDisplayName(),
            ]);
          }
          else {
            $message = $this->t("The @developer developer has inactive status so it has invalid credentials until its account gets activated.", [
              '@developer' => $developer->label(),
            ]);
          }
        }
        $this->messenger->addWarning($message);
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
