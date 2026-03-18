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

namespace Drupal\apigee_edge_teams\Entity\ListBuilder;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\apigee_edge\Element\StatusPropertyElement;
use Drupal\apigee_edge\Entity\ListBuilder\EdgeEntityListBuilder;
use Drupal\apigee_edge_teams\Entity\TeamInterface;
use Drupal\apigee_edge_teams\TeamMembershipManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\apigee_edge\Form\EdgeEntitySearchForm;

/**
 * General entity listing builder for teams.
 */
class TeamListBuilder extends EdgeEntityListBuilder {

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The request stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The team membership manager.
   *
   * @var \Drupal\apigee_edge_teams\TeamMembershipManagerInterface
   */
  protected $teamMembershipManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityTypeManagerInterface $entity_type_manager,
    FormBuilderInterface $form_builder,
    RequestStack $request_stack,
    ?ConfigFactoryInterface $config_factory = NULL,
    ?AccountInterface $current_user = NULL,
    ?TeamMembershipManagerInterface $team_membership_manager = NULL,
  ) {
    parent::__construct($entity_type, $entity_type_manager, $config_factory);

    $this->formBuilder = $form_builder;
    $this->requestStack = $request_stack;
    $this->currentUser = $current_user ?: \Drupal::currentUser();
    $this->teamMembershipManager = $team_membership_manager ?: \Drupal::service('apigee_edge_teams.team_membership_manager');

    // Override the parent construct limit here.
    $this->limit = 1000;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager'),
      $container->get('form_builder'),
      $container->get('request_stack'),
      $container->get('config.factory'),
      $container->get('current_user'),
      $container->get('apigee_edge_teams.team_membership_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $headers = [];

    $headers['name'] = [
      'data' => $this->t('@entity_type name', [
        '@entity_type' => ucfirst($this->entityType->getSingularLabel()),
      ]),
      'specifier' => 'displayName',
      'field' => 'displayName',
      'sort' => 'desc',
    ];
    $headers['status'] = [
      'data' => $this->t('Status'),
      'specifier' => 'status',
      'field' => 'status',
    ];

    return $headers + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);

    $team_app_list_url = Url::fromRoute('entity.team_app.collection_by_team', ['team' => $entity->id()]);
    if ($team_app_list_url->access()) {
      $team_app_entity_def = $this->entityTypeManager->getDefinition('team_app');
      $operations['apps'] = [
        'title' => $team_app_entity_def->getCollectionLabel(),
        'url' => $team_app_list_url,
        'weight' => -10,
      ];
    }

    if ($entity->hasLinkTemplate('members')) {
      $members_url = $entity->toUrl('members');
      if ($members_url->access()) {
        $operations['members'] = [
          'title' => $this->t('Members'),
          'url' => $members_url,
        ];
      }
    }

    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function load() {
    // Compared with a usual entity collection page this listing page is also
    // available to _all_ logged in users so it must be ensured that users
    // can see only those teams in this list that they have view access.
    // @see \Drupal\apigee_edge_teams\Entity\TeamAccessHandler
    return array_filter(parent::load(), function (TeamInterface $entity) {
      return $entity->access('view');
    });
  }

  /**
   * {@inheritdoc}
   */
  protected function buildEntityIdQuery(): QueryInterface {

    $query = parent::buildEntityIdQuery();

    $search_query = $this->requestStack->getCurrentRequest()->query->get($this->entityTypeId, '');
    // If there is a search term, add the condition to the query.
    if (!empty($search_query)) {
      $query->condition('displayName', $search_query, 'CONTAINS');
    }

    // It filters the entities for non admin users BEFORE
    // the pager is initialized.
    if ($this->currentUser->isAuthenticated() && !$this->currentUser->hasPermission('administer team') && !$this->currentUser->hasPermission('view any team')) {
      $teams = $this->teamMembershipManager->getTeams($this->currentUser->getEmail());

      // FIX: Disable the pager completely if the user have a single team
      // or no teams. `Query::getFromStorage()` have `IN` conditions
      // containing only a single value, which breaks the pager count and
      // triggers global pagination for single team or no teams on teams page.
      // Only return entities the user is explicitly a member of.
      if (count($teams) <= 1) {
        $this->limit = 0;
      }

      if (!empty($teams)) {
        $query->condition('name', $teams, 'IN');
      }
    }
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\apigee_edge_teams\Entity\TeamInterface $entity */
    $row['name']['data'] = $entity->toLink()->toRenderable();
    $row['status']['data'] = [
      '#type' => 'status_property',
      '#value' => $entity->getStatus(),
      '#indicator_status' => $entity->getStatus() === TeamInterface::STATUS_ACTIVE ? StatusPropertyElement::INDICATOR_STATUS_OK : StatusPropertyElement::INDICATOR_STATUS_ERROR,
    ];
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    $account = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());

    if (isset($build['#type']) && $build['#type'] === 'table') {
      $build['table'] = $build;
    }

    $build['filter_form'] = $this->formBuilder->getForm(EdgeEntitySearchForm::class, $this->entityTypeId);
    $build['filter_form']['#weight'] = -1;

    $build['#cache']['keys'][] = 'team_list_per_user';

    // Team lists vary for each user and their permissions.
    // Note: Even though cache contexts will be optimized to only include the
    // 'user' cache context, the element should be invalidated correctly when
    // permissions change because the 'user.permissions' cache context defined
    // cache tags for permission changes, which should have bubbled up for the
    // element when it was optimized away.
    // @see \Drupal\KernelTests\Core\Cache\CacheContextOptimizationTest
    $build['#cache']['contexts'][] = 'user';
    $build['#cache']['contexts'][] = 'user.permissions';

    // Important: Cache per page.
    $build['#cache']['contexts'][] = 'url.query_args:page';

    $build['#cache']['tags'] = Cache::mergeTags($build['#cache']['tags'], $account->getCacheTags());

    // Use cache expiration defined in configuration.
    $build['#cache']['max-age'] = $this->configFactory
      ->get('apigee_edge_teams.team_settings')
      ->get('cache_expiration');

    return $build;
  }

}
