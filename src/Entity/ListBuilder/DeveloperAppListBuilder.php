<?php

namespace Drupal\apigee_edge\Entity\ListBuilder;

use Apigee\Edge\Api\Management\Entity\App;
use Apigee\Edge\Api\Management\Entity\AppCredential;
use Apigee\Edge\Structure\CredentialProduct;
use Drupal\apigee_edge\Entity\DeveloperAppInterface;
use Drupal\apigee_edge\Entity\DeveloperAppPageTitleInterface;
use Drupal\Component\Utility\Html;
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

/**
 * General entity listing builder for developer apps.
 *
 * ContainerInjectionInterface had to be implemented to make
 * \Drupal\Core\Controller\TitleResolver happy otherwise it would have
 * called the constructor with 0 parameter when it generates the page title
 * by calling getPageTitle().
 */
class DeveloperAppListBuilder extends EntityListBuilder implements DeveloperAppPageTitleInterface, ContainerInjectionInterface {

  /** @var \Drupal\Core\Render\RendererInterface */
  protected $renderer;

  /** @var string */
  protected $defaultSortDirection = 'displayName';

  /** @var string */
  protected $defaultSortField = 'ASC';

  /** @var \Drupal\Core\Entity\EntityTypeManagerInterface */
  protected $entityTypeManager;

  /**
   * DeveloperAppListBuilder constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   * @param \Drupal\Core\Render\RendererInterface $render
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, EntityTypeManagerInterface $entityTypeManager, RendererInterface $render) {
    parent::__construct($entity_type, $storage);
    $this->renderer = $render;
    $this->entityTypeManager = $entityTypeManager;
    // Disable pager for now.
    $this->limit = 0;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity.manager')->getStorage($entity_type->id()),
      $container->get('entity.manager'),
      $container->get('renderer')
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
   */
  protected function getDeveloperAppEntityDefinition() {
    return $this->entityTypeManager->getDefinition('developer_app');
  }

  /**
   * Returns definition of the API product entity.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface|null
   */
  protected function getApiProductEntityDefinition() {
    return $this->entityTypeManager->getDefinition('api_product');
  }

  /**
   * Returns the API product storage.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function getApiProductStorage() {
    return $this->entityTypeManager->getStorage('api_product');
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultOperations(EntityInterface $entity) {
    // TODO Use parent::getDefaultOperations() when permissions are working properly.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getOperations(EntityInterface $entity) {
    // TODO Use parent::getOperations() when permissions are working properly.
    return [];
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
   * Returns the canonical link of an app.
   *
   * @param \Drupal\apigee_edge\Entity\DeveloperAppInterface $app
   *
   * @return \Drupal\Core\Link
   */
  protected function getAppDetailsLink(DeveloperAppInterface $app) {
    // FIXME When apps has a dedicated details page.
    return new Link($app->getDisplayName(), new Url('<front>'));
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $headers = [];
    $headers['app_name'] = [
      'data' => $this->t('@app name', [
        '@app' => ucfirst($this->getDeveloperAppEntityDefinition()->get('label_singular')),
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
   * Returns a unique CSS id for an app.
   *
   * @param \Drupal\apigee_edge\Entity\DeveloperAppInterface $app
   *
   * @return string
   */
  protected function getUniqueCssIdForApp(DeveloperAppInterface $app): string {
    // App's default UUID is unique ennough.
    return $app->id();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\apigee_edge\Entity\DeveloperAppInterface $entity */
    $request = \Drupal::request();
    $appNameAsCssId = $this->getUniqueCssIdForApp($entity);
    $infoRowId = "{$appNameAsCssId}-info";
    $warningRowId = "{$appNameAsCssId}-warning";
    $rows = [
      $infoRowId => [
        'data' => [],
        'id' => $infoRowId,
        'class' => [
          'row--app',
          'row--info',
        ],
      ],
      $warningRowId => [
        'data' => [],
        'id' => $warningRowId,
        'class' => [
          'row--app',
          'row--warning',
        ],
      ],
    ];
    $infoRow = &$rows[$infoRowId]['data'];
    $warningRow = &$rows[$warningRowId]['data'];
    $infoRow['app_name'] = $this->getAppDetailsLink($entity);
    $infoRow['app_status']['data'] = [
      '#prefix' => '<span class="' . Html::escape($entity->getStatus()) . ' wrapper--status">',
      '#suffix' => '</span>',
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => $entity->getStatus(),
      '#attributes' => [
        'class' => [
          'label--status',
        ],
      ],
    ];
    $infoRow += parent::buildRow($entity);

    $hasRevokedCred = FALSE;
    $hasRevokedCredProduct = FALSE;
    $hasPendingCredProduct = FALSE;
    $problematicApiProductName = NULL;
    foreach ($entity->getCredentials() as $credential) {
      if ($credential->getStatus() === AppCredential::STATUS_REVOKED) {
        $hasRevokedCred = TRUE;
        break;
      }
      foreach ($credential->getApiProducts() as $credProduct) {
        if ($credProduct->getStatus() == CredentialProduct::STATUS_REVOKED) {
          $problematicApiProductName = $credProduct->getApiproduct();
          $hasRevokedCredProduct = TRUE;
          break;
        }
        elseif ($credProduct->getStatus() == CredentialProduct::STATUS_PENDING) {
          $problematicApiProductName = $credProduct->getApiproduct();
          $hasPendingCredProduct = TRUE;
          break;
        }
      }
    }

    /*
     * Display warning sign next to the status if app's status is approved, but:
     *  - any credentials of the app is in revoked status
     *  - any products of any credentials of the app is in revoked or pending
     *    status.
     */
    if ($entity->getStatus() === App::STATUS_APPROVED && ($hasRevokedCred || $hasPendingCredProduct || $hasRevokedCredProduct)) {
      $build['status'] = $infoRow['app_status']['data'];
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
        ],
        'fragment' => $warningRowId,
      ];
      $url = Url::fromUserInput($request->getRequestUri(), $link_options);
      $link = Link::fromTextAndUrl('^', $url);
      $build['warning-toggle'] = $link->toRenderable();
      $infoRow['app_status']['data'] = $this->renderer->render($build);
      $warningRow['info'] = [
        'colspan' => 3,
      ];

      if ($hasRevokedCred) {
        $args = [
          '@app' => $this->getDeveloperAppEntityDefinition()->getLowercaseLabel(),
        ];
        if (count($entity->getCredentials()) > 1) {
          $warningRow['info']['data'] = $this->t(
            'One of the credentials associated with this @app is in revoked status.',
            $args
          );
        }
        else {
          $warningRow['info']['data'] = $this->t(
            'The credential associated with this @app is in revoked status.',
            $args
          );
        }
      }
      elseif ($hasRevokedCredProduct || $hasPendingCredProduct) {
        $args = [
          '@app' => $this->getDeveloperAppEntityDefinition()->getLowercaseLabel(),
          '@apiproduct' => $this->getApiProductEntityDefinition()->getLowercaseLabel(),
          '@status' => $hasPendingCredProduct ? $this->t('pending') : $this->t('revoked'),
        ];
        if (count($entity->getCredentials()) === 1) {
          /** @var \Drupal\apigee_edge\Entity\ApiProductInterface $apiProduct */
          $apiProduct = $this->getApiProductStorage()->load($problematicApiProductName);
          $args['%name'] = $apiProduct->getDisplayName();
          $warningRow['info']['data'] = $this->t("%name @apiproduct associated with this @app is in @status status.", $args);
        }
        else {
          $warningRow['info']['data'] = $this->t("At least one @apiproduct associated with one of the credentials of this @app is in @status status.", $args);
        }
      }
    }

    return $rows;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build['#attached']['library'][] = 'apigee_edge/apigee_edge.listing';

    $build['add_app'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => '',
      ],
      'link' => Link::createFromRoute($this->t('Add @devAppLabel', [
        '@devAppLabel' => $this->getDeveloperAppEntityDefinition()->getLowercaseLabel(),
      ]), 'entity.developer_app.add_form', [], ['attributes' => ['class' => 'btn btn-primary btn--add-app']])->toRenderable(),
    ];
    $build['table'] = [
      '#type' => 'table',
      '#header' => $this->buildHeader(),
      '#title' => $this->getTitle(),
      '#rows' => [],
      '#empty' => $this->t('There is no @label yet.', ['@label' => $this->entityType->getLabel()]),
      '#cache' => [
        // TODO
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
  public function getPageTitle(RouteMatchInterface $routeMatch): string {
    return $this->t('@devAppLabel', ['@devAppLabel' => $this->getDeveloperAppEntityDefinition()->getPluralLabel()]);
  }

}
