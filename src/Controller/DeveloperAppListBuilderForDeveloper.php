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

use Drupal\apigee_edge\Exception\DeveloperDoesNotExistException;
use Drupal\apigee_edge\Entity\DeveloperAppInterface;
use Drupal\apigee_edge\Entity\DeveloperStatusCheckTrait;
use Drupal\apigee_edge\Entity\ListBuilder\DeveloperAppListBuilder;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Lists developer apps of a developer on the UI.
 */
class DeveloperAppListBuilderForDeveloper extends DeveloperAppListBuilder {

  use DeveloperStatusCheckTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * DeveloperAppListBuilderForDeveloper constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Render\RendererInterface $render
   *   The render.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   Currently logged-in user.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack object.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, EntityTypeManagerInterface $entity_type_manager, RendererInterface $render, AccountInterface $current_user, RequestStack $request_stack) {
    parent::__construct($entity_type, $storage, $entity_type_manager, $render, $request_stack);
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $entity_type = $container->get('entity_type.manager')->getDefinition('developer_app');
    return new static(
      $entity_type,
      $container->get('entity.manager')->getStorage($entity_type->id()),
      $container->get('entity.manager'),
      $container->get('renderer'),
      $container->get('current_user'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds(array $headers = [], UserInterface $user = NULL) {
    $developerId = $user->get('apigee_edge_developer_id')->value;
    // If developer id can not be retrieved for a Drupal user it means that
    // either there is connection error or the site is out of sync with
    // Apigee Edge.
    if ($developerId === NULL) {
      throw new DeveloperDoesNotExistException($user->getEmail());
    }

    $query = $this->storage->getQuery()
      ->condition('developerId', $developerId);
    $query->tableSort($headers);
    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function load(array $headers = []) {
    return [];
  }

  /**
   * Load developer apps of a Drupal user.
   *
   * @param \Drupal\user\UserInterface $user
   *   User object.
   * @param array $headers
   *   Table headers.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   Developer apps or an empty array.
   */
  protected function loadByUser(UserInterface $user, array $headers = []): array {
    $entity_ids = $this->getEntityIds($headers, $user);
    return $this->storage->loadMultiple($entity_ids);
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
  protected function getAppDetailsLink(DeveloperAppInterface $app) {
    return $app->toLink(NULL, 'canonical-by-developer');
  }

  /**
   * {@inheritdoc}
   */
  protected function getUniqueCssIdForApp(DeveloperAppInterface $app): string {
    // If we are listing the apps of a developer than developer app name is also
    // unique.
    return Html::getUniqueId($app->getName());
  }

  /**
   * {@inheritdoc}
   */
  protected function renderAddAppLink(UserInterface $user = NULL) {
    $link = parent::renderAddAppLink();
    if ($user) {
      $link['#url'] = new Url('entity.developer_app.add_form_for_developer', ['user' => $user->id()], $link['#url']->getOptions());
    }
    return $link;
  }

  /**
   * {@inheritdoc}
   */
  public function render(UserInterface $user = NULL) {
    $this->checkDeveloperStatus($user->id());
    $build = parent::render();

    $build['table']['#empty'] = $this->t('Looks like you do not have any apps. Get started by adding one.');
    // If current user has access to the Add app form (validated by the parent
    // class).
    if (!empty($build['add_app'])) {
      $build['add_app']['link'] = $this->renderAddAppLink($user);
    }

    foreach ($this->loadByUser($user, $this->buildHeader()) as $entity) {
      if ($row = $this->buildRow($entity)) {
        $build['table']['#rows'] += $this->buildRow($entity);
      }
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getPageTitle(RouteMatchInterface $route_match): string {
    $account = $route_match->getParameter('user');
    if ($account->id() == $this->currentUser->id()) {
      return apigee_edge_get_my_developer_apps_title();
    }
    else {
      return apigee_edge_get_my_developer_apps_title($account);
    }
  }

  /**
   * Redirects users to their My apps page.
   *
   * This controller assumes that it is only invoked for authenticated users.
   * This is enforced for the 'apigee_edge.user.my_apps' route with the
   * '_user_is_logged_in' requirement.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Returns a redirect to the My apps of the currently logged in user.
   */
  public function myAppsPage(): RedirectResponse {
    $options['absolute'] = TRUE;
    $url = Url::fromRoute('entity.developer_app.collection_by_developer', ['user' => \Drupal::currentUser()->id()], $options);
    return new RedirectResponse($url->toString(), 302);
  }

}
