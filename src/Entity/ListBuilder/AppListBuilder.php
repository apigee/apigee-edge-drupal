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
use Drupal\apigee_edge\Entity\AppWarningsCheckerInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * General app list builder for developer and team apps.
 */
class AppListBuilder extends EdgeEntityListBuilder {

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The app warnings checker.
   *
   * @var \Drupal\apigee_edge\Entity\AppWarningsCheckerInterface
   */
  protected $appWarningsChecker;

  /**
   * AppListBuilder constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   *   The time service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack object.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface|null $config_factory
   *   The config factory.
   * @param \Drupal\apigee_edge\Entity\AppWarningsCheckerInterface $app_warnings_checker
   *   The app warnings checker service.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityTypeManagerInterface $entity_type_manager, RendererInterface $renderer, RequestStack $request_stack, TimeInterface $time, ConfigFactoryInterface $config_factory = NULL, AppWarningsCheckerInterface $app_warnings_checker = NULL) {
    if (!$config_factory) {
      $config_factory = \Drupal::service('config.factory');
    }
    if (!$app_warnings_checker) {
      @trigger_error('Calling ' . __METHOD__ . ' without the $app_warnings_checker is deprecated in apigee_edge:8-x-1.18 and is required before apigee_edge:8.x-2.0. See https://github.com/apigee/apigee-edge-drupal/pull/507', E_USER_DEPRECATED);
      $app_warnings_checker = \Drupal::service('apigee_edge.entity.app_warnings_checker');
    }

    parent::__construct($entity_type, $entity_type_manager, $config_factory);
    $this->renderer = $renderer;
    $this->entityTypeManager = $entity_type_manager;
    $this->requestStack = $request_stack;
    $this->time = $time;
    $this->appWarningsChecker = $app_warnings_checker;
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
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    if ($entity->access('analytics') && $entity->hasLinkTemplate('analytics')) {
      $operations['analytics'] = [
        'title' => $this->t('Analytics'),
        'weight' => 150,
        'url' => $entity->toUrl('analytics'),
      ];
    }

    return $operations;
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
      'sort' => 'asc',
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
  public function buildRow(EntityInterface $entity) {
    // We may return multiple rows for one entity which is not supported by
    // the parent class. (See render().)
    return [];
  }

  /**
   * Builds an info row for an app in the entity listing.
   *
   * The info row contains the app's name (link to the details page),
   * status and entity operations.
   *
   * @param \Drupal\apigee_edge\Entity\AppInterface $app
   *   The app entity.
   * @param array $rows
   *   The info row in the table for app.
   */
  protected function buildInfoRow(AppInterface $app, array &$rows) {
    $css_id = $this->getCssIdForInfoRow($app);
    $row = [
      'data' => [],
      'id' => $css_id,
      'class' => [
        'row--app',
        'row--info',
      ],
    ];

    $row['data']['name']['data'] = $this->renderAppName($app);
    $row['data']['status']['data'] = $this->renderAppStatus($app);

    $row['data'] += parent::buildRow($app);
    // Allow child classes to add items to the beginning of a row.
    if (array_key_exists($css_id, $rows)) {
      $rows[$css_id] = NestedArray::mergeDeep($rows[$css_id], $row);
    }
    else {
      $rows[$css_id] = $row;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();

    // Add user cache contexts.
    $build['#cache']['contexts'][] = 'user';
    $build['#cache']['contexts'][] = 'user.permissions';

    if ($this->usingDisplayType(static::VIEW_MODE_DISPLAY_TYPE)) {
      return $build;
    }

    $build['table']['#attributes']['class'][] = 'table--app-list';
    $build['table']['#rows'] = [];
    $build['table']['#empty'] = $this->t('Looks like you do not have any apps. Get started by adding one.');

    foreach ($this->load() as $entity) {
      $rows = [];
      $this->buildInfoRow($entity, $rows);
      $this->buildWarningRow($entity, $rows);
      $build['table']['#rows'] += $rows;
    }

    $build['#attached']['library'][] = 'apigee_edge/apigee_edge.app_listing';

    return $build;
  }

  /**
   * Builds a warning row for an app in the entity listing.
   *
   * The warning row contains the warning messages if there is any.
   *
   * @param \Drupal\apigee_edge\Entity\AppInterface $app
   *   The app entity.
   * @param array $rows
   *   The warning row in the table for app.
   */
  protected function buildWarningRow(AppInterface $app, array &$rows) {
    $info_row_css_id = $this->getCssIdForInfoRow($app);
    $warning_row_css_id = $this->getCssIdForWarningRow($app);
    $row = [
      'data' => [],
      'id' => $warning_row_css_id,
      'class' => [
        'row--app',
        'row--warning',
      ],
    ];

    $warnings = $this->checkAppCredentialWarnings($app);

    // Display warning sign next to the status if the app's status is
    // approved, but:
    // - any credentials of the app is in revoked or expired status
    // - any products of any credentials of the app is in revoked or
    //   pending status.
    if ($app->getStatus() === AppInterface::STATUS_APPROVED && ($warnings['revokedCred'] || $warnings['revokedOrPendingCredProduct'] || $warnings['expiredCred'])) {
      $build['status'] = $rows[$info_row_css_id]['data']['status']['data'];
      $build['warning'] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => '!',
        '#attributes' => ['class' => 'circle'],
      ];
      $link_options = [
        'attributes' => [
          'class' => [
            'toggle--warning',
            'closed',
          ],
          'data-text-open' => [
            $this->t('Show details'),
          ],
          'data-text-closed' => [
            $this->t('Hide details'),
          ],
        ],
        'fragment' => $warning_row_css_id,
      ];
      $url = Url::fromUserInput($this->requestStack->getCurrentRequest()->getRequestUri(), $link_options);
      $link = Link::fromTextAndUrl($this->t('<span class="ui-icon-triangle-1-e ui-icon"></span><span class="text">Show details</span>'), $url);
      $build['warning-toggle'] = $link->toRenderable();
      $rows[$info_row_css_id]['data']['status']['data'] = $this->renderer->renderPlain($build);
      $row['data']['info'] = [
        'colspan' => count($this->buildHeader()),
      ];

      $items = [];
      if ($warnings['revokedCred']) {
        $items[] = $warnings['revokedCred'];
      }
      elseif ($warnings['revokedOrPendingCredProduct']) {
        $items[] = $warnings['revokedOrPendingCredProduct'];
      }

      if ($warnings['expiredCred']) {
        $items[] = $warnings['expiredCred'];
      }

      $row['data']['info']['data'] = [
        '#theme' => 'item_list',
        '#items' => $items,
      ];
    }

    $rows[$warning_row_css_id] = $row;
  }

  /**
   * Renders the name of an app for the entity list.
   *
   * @param \Drupal\apigee_edge\Entity\AppInterface $app
   *   App entity.
   *
   * @return array
   *   Render array with the app name.
   */
  protected function renderAppName(AppInterface $app): array {
    if ($app->access('view')) {
      return $app->toLink()->toRenderable();
    }
    return ['#markup' => $app->label()];
  }

  /**
   * Renders the status of an app for the entity list.
   *
   * @param \Drupal\apigee_edge\Entity\AppInterface $app
   *   App entity.
   *
   * @return array
   *   Render array with the app status.
   */
  protected function renderAppStatus(AppInterface $app): array {
    $field = $this->entityTypeManager->getViewBuilder($this->entityTypeId)->viewField($app->get('status'), 'default');
    $field['#label_display'] = 'hidden';
    return $field;
  }

  /**
   * Checks credentials of an app and returns warnings about them.
   *
   * @param \Drupal\apigee_edge\Entity\AppInterface $app
   *   The app entity to be checked.
   *
   * @return array
   *   An associative array that contains information about the revoked
   *   credentials and revoked or pending API products in a credential.
   */
  protected function checkAppCredentialWarnings(AppInterface $app): array {
    return $this->appWarningsChecker->getWarnings($app);
  }

  /**
   * Generates a unique CSS id for an app.
   *
   * @param \Drupal\apigee_edge\Entity\AppInterface $app
   *   App entity.
   *
   * @return string
   *   A unique CSS id for the app.
   */
  protected function generateCssIdForApp(AppInterface $app): string {
    // App id (UUID) is unique by default.
    return Html::getId($app->getAppId());
  }

  /**
   * Returns the CSS ID of the app info row.
   *
   * @param \Drupal\apigee_edge\Entity\AppInterface $app
   *   The app entity.
   *
   * @return string
   *   The CSS ID of the info row.
   */
  final protected function getCssIdForInfoRow(AppInterface $app): string {
    return $this->generateCssIdForApp($app) . '-info';
  }

  /**
   * Returns the CSS ID of the app warning row.
   *
   * @param \Drupal\apigee_edge\Entity\AppInterface $app
   *   The app entity.
   *
   * @return string
   *   The CSS ID of the warning row.
   */
  final protected function getCssIdForWarningRow(AppInterface $app): string {
    return $this->generateCssIdForApp($app) . '-warning';
  }

}
