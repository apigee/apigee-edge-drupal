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

namespace Drupal\apigee_edge\Controller;

use Drupal\apigee_edge\Entity\DeveloperAppInterface;
use Drupal\apigee_edge\Entity\DeveloperAppPageTitleInterface;
use Drupal\apigee_edge\Entity\DeveloperStatusCheckTrait;
use Drupal\Core\Entity\Controller\EntityViewController;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays the view page of a developer app on the UI.
 */
class DeveloperAppViewController extends EntityViewController implements DeveloperAppPageTitleInterface {

  use DeveloperStatusCheckTrait;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Creates an EntityViewController object.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(EntityManagerInterface $entity_manager, RendererInterface $renderer, MessengerInterface $messenger) {
    parent::__construct($entity_manager, $renderer);
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager'),
      $container->get('renderer'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function view(EntityInterface $developer_app, $view_mode = 'full') {
    /** @var \Drupal\apigee_edge\Entity\DeveloperAppInterface $developer_app */
    $this->checkDeveloperStatus($developer_app->getOwnerId());

    /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $developer_app_view_display */
    $developer_app_view_display = $this->entityManager->getStorage('entity_view_display')->load('developer_app.developer_app.default');
    // If the Callback URL field is enabled then check its value.
    if (!array_key_exists('callbackUrl', $developer_app_view_display->get('hidden'))) {
      $this->checkCallbackUrl($developer_app);
    }

    $build = parent::view($developer_app, $view_mode);
    return $build;
  }

  /**
   * Checks whether the developer app's Callback URL value is valid.
   *
   * @param \Drupal\apigee_edge\Entity\DeveloperAppInterface $developer_app
   *   The developer app entity.
   *
   * @see \Drupal\apigee_edge\Entity\DeveloperApp::set()
   */
  protected function checkCallbackUrl(DeveloperAppInterface $developer_app) {
    // If the property value and the field value are different then the
    // callback URL is not a valid URL.
    if ($developer_app->getCallbackUrl() !== $developer_app->get('callbackUrl')->value) {
      try {
        Url::fromUri($developer_app->getCallbackUrl());
      }
      catch (\Exception $exception) {
        $this->messenger->addWarning(t('The Callback URL value should be fixed. @message', [
          '@message' => $exception->getMessage(),
        ]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getPageTitle(RouteMatchInterface $routeMatch): string {
    return t('@name @developer_app', [
      '@name' => Markup::create($routeMatch->getParameter('developer_app')->label()),
      '@developer_app' => $this->entityManager->getDefinition('developer_app')->getSingularLabel(),
    ]);
  }

}
