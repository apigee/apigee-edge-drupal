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

namespace Drupal\apigee_edge\Entity\ListBuilder;

use Apigee\Edge\Api\Management\Entity\App;
use Apigee\Edge\Api\Management\Entity\AppCredential;
use Apigee\Edge\Structure\CredentialProduct;
use Drupal\apigee_edge\Entity\DeveloperAppInterface;
use Drupal\apigee_edge\Entity\DeveloperAppPageTitleInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * General entity listing builder for developer apps.
 *
 * ContainerInjectionInterface had to be implemented to make
 * \Drupal\Core\Controller\TitleResolver happy otherwise it would have
 * called the constructor with 0 parameter when it generates the page title
 * by calling getPageTitle().
 */
class DeveloperAppListBuilder extends EntityListBuilder implements DeveloperAppPageTitleInterface, ContainerInjectionInterface {

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The default sort direction.
   *
   * @var string
   */
  protected $defaultSortDirection = 'displayName';

  /**
   * The default sort field.
   *
   * @var string
   */
  protected $defaultSortField = 'ASC';

  /**
   * DeveloperAppListBuilder constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage object.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack object.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, EntityTypeManagerInterface $entity_type_manager, RendererInterface $renderer, RequestStack $request_stack) {
    parent::__construct($entity_type, $storage);
    $this->renderer = $renderer;
    $this->entityTypeManager = $entity_type_manager;
    $this->requestStack = $request_stack;
    // Disable pager for now.
    $this->limit = 0;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('entity_type.manager'),
      $container->get('renderer'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $entityType = $container->get('entity_type.manager')->getDefinition('developer_app');
    return static::createInstance($container, $entityType);
  }

  /**
   * Returns definition of the Developer app entity.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface|null
   *   The Developer app entity definition.
   */
  protected function getDeveloperAppEntityDefinition() {
    return $this->entityTypeManager->getDefinition('developer_app');
  }

  /**
   * Returns definition of the API product entity.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface|null
   *   The API product entity definition.
   */
  protected function getApiProductEntityDefinition() {
    return $this->entityTypeManager->getDefinition('api_product');
  }

  /**
   * Returns the API product storage.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   The API product storage.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function getApiProductStorage() {
    return $this->entityTypeManager->getStorage('api_product');
  }

  /**
   * {@inheritdoc}
   */
  public function load(array $headers = []) {
    $entity_ids = $this->getEntityIds($headers);
    return $this->storage->loadMultiple($entity_ids);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds(array $headers = []) {
    $query = $this->storage->getQuery()->tableSort($headers);
    return $query->execute();
  }

  /**
   * Returns a rendered link to Add developer app form.
   *
   * @return array
   *   Render array.
   */
  protected function renderAddAppLink() {
    return Link::createFromRoute($this->t('Add @developer_app', [
      '@developer_app' => $this->getDeveloperAppEntityDefinition()->getLowercaseLabel(),
    ]), 'entity.developer_app.add_form', [], ['attributes' => ['class' => 'btn btn-primary btn--add-app']])->toRenderable();
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    if ($entity->access('analytics')) {
      $operations['analytics'] = [
        'title' => $this->t('Analytics'),
        'weight' => 150,
        'url' => $entity->toUrl('analytics'),
      ];
    }

    return $operations;
  }

  /**
   * Returns the value of developer app name's field in the entity list.
   *
   * Returns the link if user can view a developer app otherwise the label of
   * the developer app.
   *
   * @param \Drupal\apigee_edge\Entity\DeveloperAppInterface $developer_app
   *   Developer app entity.
   *
   * @return \Drupal\Core\Link|string
   *   Link to the view page of a developer app or the label of the developer
   *   app if the current user has no permission to view a developer app.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function getAppDetailsLink(DeveloperAppInterface $developer_app) {
    if ($developer_app->access('view')) {
      return $developer_app->toLink();
    }
    return $developer_app->label();
  }

  /**
   * Returns a unique CSS id for a developer app.
   *
   * @param \Drupal\apigee_edge\Entity\DeveloperAppInterface $developer_app
   *   The developer app entity.
   *
   * @return string
   *   The unique developer app id.
   */
  protected function getUniqueCssIdForApp(DeveloperAppInterface $developer_app): string {
    // Developer app's default UUID is unique enough.
    return $developer_app->id();
  }

  /**
   * Returns a unique CSS id for an info row of a developer app.
   *
   * @param \Drupal\apigee_edge\Entity\DeveloperAppInterface $developer_app
   *   The developer app entity.
   *
   * @return string
   *   The unique info row id of a developer app.
   */
  protected function getUniqueCssIdForAppInfoRow(DeveloperAppInterface $developer_app): string {
    return "{$this->getUniqueCssIdForApp($developer_app)}-info";
  }

  /**
   * Returns a unique CSS id for a warning row of a developer app.
   *
   * @param \Drupal\apigee_edge\Entity\DeveloperAppInterface $developer_app
   *   The developer app entity.
   *
   * @return string
   *   The unique warning row id of a developer app.
   */
  protected function getUniqueCssIdForAppWarningRow(DeveloperAppInterface $developer_app): string {
    return "{$this->getUniqueCssIdForApp($developer_app)}-warning";
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $headers = [];
    $headers['app_name'] = [
      'data' => $this->t('@developer_app name', [
        '@developer_app' => ucfirst($this->getDeveloperAppEntityDefinition()->getSingularLabel()),
      ]),
      'specifier' => 'displayName',
      'field' => 'displayName',
      'sort' => 'asc',
    ];
    $headers['app_status'] = [
      'data' => $this->t('Status'),
      'specifier' => 'status',
      'field' => 'status',
    ];
    return $headers + parent::buildHeader();
  }

  /**
   * Builds an info row for a developer app in the entity listing.
   *
   * The info row contains the developer app's name (link to the details page),
   * status and entity operations.
   *
   * @param \Drupal\apigee_edge\Entity\DeveloperAppInterface $developer_app
   *   The developer app entity for this row of the list.
   * @param array $rows
   *   A reference to developer app entity rows.
   */
  protected function buildInfoRow(DeveloperAppInterface $developer_app, array &$rows) {
    $row = [
      'data' => [],
      'id' => $this->getUniqueCssIdForAppInfoRow($developer_app),
      'class' => [
        'row--app',
        'row--info',
      ],
    ];

    $row['data']['app_name'] = $this->getAppDetailsLink($developer_app);
    $row['data']['app_status']['data'] = [
      '#type' => 'status_property',
      '#value' => $developer_app->getStatus(),
    ];

    $row['data'] += parent::buildRow($developer_app);
    $rows[$this->getUniqueCssIdForAppInfoRow($developer_app)] = $row;
  }

  /**
   * Checks credentials of a developer app and returns warnings about them.
   *
   * @param \Drupal\apigee_edge\Entity\DeveloperAppInterface $developer_app
   *   Developer app entity to be checked.
   *
   * @return array
   *   An array containing the information about the revoked credentials and
   *   revoked or pending products in a credential.
   */
  protected function getDeveloperAppCredentialWarnings(DeveloperAppInterface $developer_app): array {
    $warnings = [];
    $warnings['revokedCred'] = FALSE;
    $warnings['revokedOrPendingCredProduct'] = FALSE;

    foreach ($developer_app->getCredentials() as $credential) {
      if ($credential->getStatus() === AppCredential::STATUS_REVOKED) {
        $args = [
          '@developer_app' => $this->getDeveloperAppEntityDefinition()->getLowercaseLabel(),
        ];
        if (count($developer_app->getCredentials()) > 1) {
          $warnings['revokedCred'] = $this->t('One of the credentials associated with this @developer_app is in revoked status.', $args);
        }
        else {
          $warnings['revokedCred'] = $this->t('The credential associated with this @developer_app is in revoked status.', $args);
        }
        break;
      }
      foreach ($credential->getApiProducts() as $credProduct) {
        if ($credProduct->getStatus() == CredentialProduct::STATUS_REVOKED || $credProduct->getStatus() == CredentialProduct::STATUS_PENDING) {
          $args = [
            '@developer_app' => $this->getDeveloperAppEntityDefinition()->getLowercaseLabel(),
            '@apiproduct' => $this->getApiProductEntityDefinition()->getLowercaseLabel(),
            '@status' => $credProduct->getStatus() == CredentialProduct::STATUS_REVOKED ? $this->t('revoked') : $this->t('pending'),
          ];
          if (count($developer_app->getCredentials()) === 1) {
            /** @var \Drupal\apigee_edge\Entity\ApiProductInterface $apiProduct */
            $apiProduct = $this->getApiProductStorage()->load($credProduct->getApiproduct());
            $args['%name'] = $apiProduct->label();
            $warnings['revokedOrPendingCredProduct'] = $this->t('%name @apiproduct associated with this @developer_app is in @status status.', $args);
          }
          else {
            $warnings['revokedOrPendingCredProduct'] = $this->t('At least one @apiproduct associated with one of the credentials of this @developer_app is in @status status.', $args);
          }
          break;
        }
      }
    }

    return $warnings;
  }

  /**
   * Builds a warning row for a developer app in the entity listing.
   *
   * The warning row contains the warning messages if there is any.
   *
   * @param \Drupal\apigee_edge\Entity\DeveloperAppInterface $developer_app
   *   The developer app entity for this row of the list.
   * @param array $rows
   *   A reference to developer app entity rows.
   */
  protected function buildWarningRow(DeveloperAppInterface $developer_app, array &$rows) {
    $row = [
      'data' => [],
      'id' => $this->getUniqueCssIdForAppWarningRow($developer_app),
      'class' => [
        'row--app',
        'row--warning',
      ],
    ];

    $warnings = $this->getDeveloperAppCredentialWarnings($developer_app);

    // Display warning sign next to the status if developer app's status is
    // approved, but:
    // - any credentials of the developer app is in revoked status
    // - any products of any credentials of the developer app is in revoked or
    //   pending status.
    if ($developer_app->getStatus() === App::STATUS_APPROVED && ($warnings['revokedCred'] || $warnings['revokedOrPendingCredProduct'])) {
      $build['status'] = $rows[$this->getUniqueCssIdForAppInfoRow($developer_app)]['data']['app_status']['data'];
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
        'fragment' => $this->getUniqueCssIdForAppWarningRow($developer_app),
      ];
      $url = Url::fromUserInput($this->requestStack->getCurrentRequest()->getRequestUri(), $link_options);
      $link = Link::fromTextAndUrl($this->t('<span class="ui-icon-triangle-1-e ui-icon"></span><span class="text">Show details</span>'), $url);
      $build['warning-toggle'] = $link->toRenderable();
      $rows[$this->getUniqueCssIdForAppInfoRow($developer_app)]['data']['app_status']['data'] = $this->renderer->render($build);
      $row['data']['info'] = [
        'colspan' => 3,
      ];

      if ($warnings['revokedCred']) {
        $row['data']['info']['data'] = $warnings['revokedCred'];
      }
      elseif ($warnings['revokedOrPendingCredProduct']) {
        $row['data']['info']['data'] = $warnings['revokedOrPendingCredProduct'];
      }
    }

    $rows[$this->getUniqueCssIdForAppWarningRow($developer_app)] = $row;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\apigee_edge\Entity\DeveloperAppInterface $entity */
    $rows = [];
    // Generate info row of the current developer app.
    $this->buildInfoRow($entity, $rows);
    // Generate warning row of the current developer app.
    $this->buildWarningRow($entity, $rows);
    return $rows;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build['#attached']['library'][] = 'apigee_edge/apigee_edge.listing';

    // Create "Add developer app" link.
    if ($this->entityTypeManager->getAccessControlHandler('developer_app')->createAccess()) {
      $build['add_app'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => '',
        ],
        'link' => $this->renderAddAppLink(),
      ];
    }

    // Developer app entity list.
    $build['table'] = [
      '#id' => 'app-list',
      '#type' => 'table',
      '#header' => $this->buildHeader(),
      '#title' => $this->getTitle(),
      '#rows' => [],
      '#empty' => $this->t('There is no @label yet.', ['@label' => $this->entityType->getLabel()]),
      '#cache' => [
        // TODO.
        'contexts' => $this->entityType->getListCacheContexts(),
        'tags' => $this->entityType->getListCacheTags(),
      ],
    ];
    $build['table']['#attributes']['class'][] = 'table--app-list';
    foreach ($this->load($this->buildHeader()) as $entity) {
      if ($row = $this->buildRow($entity)) {
        $build['table']['#rows'] += $this->buildRow($entity);
      }
    }

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $build['pager'] = [
        '#type' => 'pager',
      ];
    }
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getPageTitle(RouteMatchInterface $route_match): string {
    return $this->t('@developer_app', [
      '@developer_app' => $this->getDeveloperAppEntityDefinition()->getPluralLabel(),
    ]);
  }

}
