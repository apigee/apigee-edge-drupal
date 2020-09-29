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

namespace Drupal\apigee_edge\Entity\ListBuilder;

use Drupal\apigee_edge\Entity\AppInterface;
use Drupal\apigee_edge\Exception\DeveloperDoesNotExistException;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Lists developer apps of a developer on the UI.
 */
class DeveloperAppListBuilderForDeveloper extends AppListBuilder implements ContainerInjectionInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Route match object.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Associative array that contains generated CSS entity ids for apps by name.
   *
   * @var array
   */
  private $appNameCssIdCache = [];

  /**
   * DeveloperAppListBuilderForDeveloper constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Render\RendererInterface $render
   *   The render.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack object.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   Currently logged-in user.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match object.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityTypeManagerInterface $entity_type_manager, RendererInterface $render, RequestStack $request_stack, TimeInterface $time, AccountInterface $current_user, RouteMatchInterface $route_match, ConfigFactoryInterface $config_factory = NULL) {
    if (!$config_factory) {
      $config_factory = \Drupal::service('config.factory');
    }

    parent::__construct($entity_type, $entity_type_manager, $render, $request_stack, $time, $config_factory);
    $this->currentUser = $current_user;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager'),
      $container->get('renderer'),
      $container->get('request_stack'),
      $container->get('datetime.time'),
      $container->get('current_user'),
      $container->get('current_route_match'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   *
   * Because we use the _controller directive in the route we had to implement
   * the ContainerInjectionInterface interface.
   */
  public static function create(ContainerInterface $container) {
    return static::createInstance($container, $container->get('entity_type.manager')->getDefinition('developer_app'));
  }

  /**
   * {@inheritdoc}
   */
  protected function buildEntityIdQuery(): QueryInterface {
    $query = parent::buildEntityIdQuery();
    $user = $this->routeMatch->getParameter('user');
    $developer_id = $user->get('apigee_edge_developer_id')->value;
    // If developer id can not be retrieved for a Drupal user it means that
    // either there is connection error or the site is out of sync with
    // Apigee Edge.
    if ($developer_id === NULL) {
      throw new DeveloperDoesNotExistException($user->getEmail());
    }
    $query->condition('developerId', $developer_id);
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    foreach ($operations as $operation => $parameters) {
      if ($entity->hasLinkTemplate("{$operation}-for-developer")) {
        $operations[$operation]['url'] = $entity->toUrl("{$operation}-for-developer");
      }
      if ($entity->hasLinkTemplate("{$operation}-form-for-developer")) {
        $operations[$operation]['url'] = $this->ensureDestination($entity->toUrl("{$operation}-form-for-developer"));
      }
    }

    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  protected function generateCssIdForApp(AppInterface $app): string {
    // If we are listing the apps of a developer then developer app name is also
    // unique. But this method must return the same CSS id for an app within
    // the same page request.
    if (!array_key_exists($app->getName(), $this->appNameCssIdCache)) {
      $this->appNameCssIdCache[$app->getName()] = Html::getUniqueId($app->getName());
    }
    return $this->appNameCssIdCache[$app->getName()];
  }

  /**
   * {@inheritdoc}
   */
  protected function renderAppName(AppInterface $app): array {
    if ($app->access('view')) {
      return $app->toLink(NULL, 'canonical-by-developer')->toRenderable();
    }
    return parent::renderAppName($app);
  }

  /**
   * Redirects users to their Apps page.
   *
   * This controller assumes that it is only invoked for authenticated users.
   * This is enforced for the 'apigee_edge.user.apps' route with the
   * '_user_is_logged_in' requirement.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Returns a redirect to the Apps of the currently logged in user.
   */
  public function appsPage(): RedirectResponse {
    $options['absolute'] = TRUE;
    $url = Url::fromRoute('entity.developer_app.collection_by_developer', ['user' => $this->currentUser->id()], $options);
    return new RedirectResponse($url->toString(), 302);
  }

  /**
   * Returns the title of the "developer app list by user" page.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title of the page.
   */
  public function pageTitle(): TranslatableMarkup {
    /** @var \Drupal\user\UserInterface $account */
    $account = $this->routeMatch->getParameter('user');
    if ($account && $account->id() != $this->currentUser->id()) {
      $args['@user'] = Markup::create($account->label());
      $title = $this->t('Apps of @user', $args);
    }
    else {
      $title = apigee_edge_app_listing_page_title();
    }

    return $title;
  }

  /**
   * {@inheritdoc}
   */
  protected function getCacheMaxAge() {
    return $this->configFactory
      ->get('apigee_edge.developer_app_settings')
      ->get('cache_expiration');
  }

}
