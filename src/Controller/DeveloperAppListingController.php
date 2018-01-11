<?php

namespace Drupal\apigee_edge\Controller;

use Apigee\Edge\Api\Management\Entity\App;
use Apigee\Edge\Api\Management\Entity\AppCredential;
use Apigee\Edge\Structure\CredentialProduct;
use Drupal\apigee_edge\Entity\DeveloperAppInterface;
use Drupal\apigee_edge\Utility\AppStatusDisplayTrait;
use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Lists developer apps of a developer on the UI.
 *
 * @package Drupal\apigee_edge\Controller
 */
class DeveloperAppListingController extends ControllerBase implements ContainerInjectionInterface {

  use AppStatusDisplayTrait;

  /** @var \Drupal\Core\Render\RendererInterface */
  protected $renderer;

  /** @var string */
  protected $defaultSortDirection = 'displayName';

  /** @var string */
  protected $defaultSortField = 'ASC';

  /**
   * DeveloperAppListingController constructor.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   */
  public function __construct(RendererInterface $renderer) {
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('renderer'));
  }

  /**
   * Returns definition of the Developer app entity.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface|null
   */
  protected function getDeveloperAppEntityDefinition() {
    return $this->entityTypeManager()->getDefinition('developer_app');
  }

  /**
   * Returns definition of the API product entity.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface|null
   */
  protected function getApiProductEntityDefinition() {
    return $this->entityTypeManager()->getDefinition('api_product');
  }

  /**
   * Returns the Developer app storage.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function getDeveloperAppStorage() {
    return $this->entityTypeManager()->getStorage('developer_app');
  }

  /**
   * Returns the API product storage.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function getApiProductStorage() {
    return $this->entityTypeManager()->getStorage('api_product');
  }

  /**
   * Returns developer apps of user in the given order.
   *
   * @param \Drupal\user\UserInterface $user
   *   Drupal user entity.
   * @param array $headers
   *   Table headers for sorting.
   *
   * @return array|\Drupal\Core\Entity\EntityInterface[]
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function getEntities(UserInterface $user, array $headers = []) {
    $storedDeveloperId = $user->get('apigee_edge_developer_id')->target_id;
    if ($storedDeveloperId === NULL) {
      return [];
    }
    $query = $this->getDeveloperAppStorage()->getQuery()
      ->condition('developerId', $storedDeveloperId);
    $query->tableSort($headers);
    return $this->getDeveloperAppStorage()->loadMultiple($query->execute());
  }

  /**
   * Builds the header row for the entity listing.
   *
   * @return array
   *   A render array structure of header strings.
   */
  protected function buildHeader() {
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
    return $headers;
  }

  /**
   * Builds info and warning rows for a developer app in the entity listing.
   *
   * @param \Drupal\apigee_edge\Entity\DeveloperAppInterface $app
   *   The entity for this row of the list.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Current HTTP request from render().
   *
   * @return array
   *   A render array structure.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Exception
   */
  protected function buildEntityRows(DeveloperAppInterface $app, Request $request) {
    $appNameAsCssId = Html::getUniqueId($app->getName());
    $infoRowId = "{$appNameAsCssId}-info";
    $warningRowId = "{$appNameAsCssId}-warning";
    $rows = [
      $infoRowId => [
        'data' => [],
        'id' => $infoRowId,
      ],
      $warningRowId => [
        'data' => [],
        'id' => $warningRowId,
      ],
    ];
    $infoRow = &$rows[$infoRowId]['data'];
    $warningRow = &$rows[$warningRowId]['data'];
    $infoRow['app_name'] = $app->toLink(NULL, 'developer-app-details');
    $infoRow['app_status']['data'] = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => $this->getAppStatus($app),
    ];

    $hasRevokedCred = FALSE;
    $hasRevokedCredProduct = FALSE;
    $hasPendingCredProduct = FALSE;
    $problematicApiProductName = NULL;
    foreach ($app->getCredentials() as $credential) {
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
     * Only display warning next to the status if:
     *  - app has multiple credentials and one of them is revoked (if it has
     *    only one revoked credentials we display "revoked" as app status)
     *  - if any credentials of the app has a product with revoked or pending
     *    status.
     */
    if (($this->getAppStatus($app) !== App::STATUS_REVOKED && $hasRevokedCred) || $hasPendingCredProduct || $hasRevokedCredProduct) {
      $build['status'] = $infoRow['app_status']['data'];
      $build['warning'] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => '!',
        '#attributes' => ['class' => 'circle'],
      ];
      $url = Url::fromUserInput($request->getRequestUri(), ['fragment' => $warningRowId]);
      $link = Link::fromTextAndUrl('^', $url);
      $build['warning-toggle'] = $link->toRenderable();
      $infoRow['app_status']['data'] = $this->renderer->render($build);
      $warningRow['info'] = [
        'colspan' => 2,
      ];

      if ($hasRevokedCred) {
        $warningRow['info']['data'] = $this->t(
          'One of the credentials associated with this @app is in revoked status.',
          [
            '@app' => strtolower($this->getDeveloperAppEntityDefinition()->getSingularLabel()),
          ]
        );
      }
      elseif ($hasRevokedCredProduct || $hasPendingCredProduct) {
        $args = [
          '@app' => strtolower($this->getDeveloperAppEntityDefinition()->getSingularLabel()
          ),
          '@apiproduct' => strtolower($this->getApiProductEntityDefinition()->getSingularLabel()),
          '@status' => $hasPendingCredProduct ? $this->t('pending') : $this->t('revoked'),
        ];
        if (count($app->getCredentials()) === 1) {
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
   * Builds the list of developer's developer apps a renderable array.
   *
   * @param \Drupal\user\UserInterface $user
   *   Drupal user entity.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   *
   * @return array
   *   Render array.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Exception
   */
  public function render(UserInterface $user, Request $request) {
    $build['add_app'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => 'pull-right',
      ],
      'link' => Link::createFromRoute($this->t('Add @label', [
        '@label' => $this->getDeveloperAppEntityDefinition()->getSingularLabel(),
      ]), 'entity.developer.createapp', ['user' => $user->id()])->toRenderable(),
    ];
    /** @var \Apigee\Edge\Api\Management\Entity\DeveloperApp[] $entities */
    $build['table'] = [
      '#type' => 'table',
      '#header' => $this->buildHeader(),
      '#title' => '',
      '#rows' => [],
      '#empty' => $this->t('Looks like you do not have any apps. Get started by adding one.'),
      '#stricky' => TRUE,
      '#cache' => [
        // TODO.
      ],
    ];
    foreach ($this->getEntities($user, $this->buildHeader()) as $entity) {
      $build['table']['#rows'] += $this->buildEntityRows($entity, $request);
    }

    return $build;
  }

}
