<?php

namespace Drupal\apigee_edge\Controller;

use Drupal\apigee_edge\Entity\DeveloperAppInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Lists developer apps of a developer on the UI.
 *
 * @package Drupal\apigee_edge\Controller
 */
class DeveloperAppListingController extends ControllerBase implements ContainerInjectionInterface {

  /** @var \Drupal\apigee_edge\Entity\Storage\DeveloperAppStorageInterface */
  protected $developerAppStorage;

  /** @var string */
  protected $defaultSortDirection = 'displayName';

  /** @var string */
  protected $defaultSortField = 'ASC';

  /**
   * DeveloperAppListingController constructor.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $developerAppStorage
   */
  public function __construct(EntityStorageInterface $developerAppStorage) {
    $this->developerAppStorage = $developerAppStorage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $developerAppStorage = $container->get('entity.manager')
      ->getStorage('developer_app');
    return new static($developerAppStorage);
  }

  /**
   * Returns developer apps of user in the given order.
   *
   * @param \Drupal\user\UserInterface $user
   *   Drupal user entity.
   * @param null|string $sortField
   *   Sorting field.
   * @param null $sortDirection
   *   Sorting order.
   *
   * @return array|\Drupal\Core\Entity\EntityInterface[]
   */
  protected function getEntities(UserInterface $user, $sortField = NULL, $sortDirection = NULL) {
    if ($sortField === NULL) {
      $sortField = $this->defaultSortField;
    }
    if ($sortDirection === NULL) {
      $sortDirection = $this->defaultSortDirection;
    }
    $storedDeveloperId = $user->get('apigee_edge_developer_id')->target_id;
    if ($storedDeveloperId === NULL) {
      return [];
    }
    $query = $this->developerAppStorage->getQuery()
      ->condition('developerId', $storedDeveloperId)
      ->sort($sortField, $sortDirection);
    return $this->developerAppStorage->loadMultiple($query->execute());
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
        '@app' => ucfirst(\Drupal::entityTypeManager()
          ->getDefinition('developer_app')
          ->get('label_singular')),
      ]),
      'field' => 'displayName',
      'sort' => 'asc',
    ];
    $headers['app_status'] = [
      'data' => $this->t('Status'),
      'field' => 'status',
    ];
    return $headers;
  }

  /**
   * Builds a row for an entity in the entity listing.
   *
   * @param \Drupal\apigee_edge\Entity\DeveloperAppInterface $app
   *   The entity for this row of the list.
   *
   * @return array
   *   A render array structure of fields for this entity.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function buildRow(DeveloperAppInterface $app) {
    $row = [];
    $row['app_name'] = $app->toLink(NULL, 'developer-app-details');
    $row['app_status'] = $app->getStatus();
    return $row;
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
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function render(UserInterface $user, Request $request) {
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
    foreach ($this->getEntities($user, $request->get('order'), $request->get('sort')) as $entity) {
      if ($row = $this->buildRow($entity)) {
        $build['table']['#rows'][$entity->id()] = $row;
      }
    }

    return $build;
  }

}
