<?php

namespace Drupal\apigee_edge\Controller;

use Drupal\apigee_edge\SDKConnectorInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists developer apps of a developer on the UI.
 *
 * @package Drupal\apigee_edge\Controller
 */
class DeveloperAppListingController extends ControllerBase implements ContainerInjectionInterface {

  /** @var \Drupal\apigee_edge\Entity\Storage\DeveloperAppStorageInterface */
  protected $developerAppStorage;

  /** @var \Drupal\apigee_edge\SDKConnectorInterface */
  protected $sdkConnector;

  /**
   * DeveloperAppListingController constructor.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $developerAppStorage
   * @param \Drupal\apigee_edge\SDKConnectorInterface $sdkConnector
   */
  public function __construct(EntityStorageInterface $developerAppStorage, SDKConnectorInterface $sdkConnector) {
    $this->developerAppStorage = $developerAppStorage;
    $this->sdkConnector = $sdkConnector;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $developerAppStorage = $container->get('entity.manager')->getStorage('developer_app');
    $sdkConnector = $container->get('apigee_edge.sdk_connector');
    return new static($developerAppStorage, $sdkConnector);
  }

  /**
   * Returns rendered list of the developer's developer apps.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *
   * @return array
   */
  public function content(AccountInterface $user) {
    /** @var \Apigee\Edge\Api\Management\Entity\DeveloperApp[] $entities */
    $entities = $this->developerAppStorage->loadByDeveloper($user->getEmail());
    $items = array_map(function ($entity) {
      /** @var \Drupal\apigee_edge\Entity\DeveloperApp $entity */
      return $entity->getDisplayName();
    }, $entities);

    $build = [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#title' => '',
      '#items' => $items,
      '#attributes' => ['class' => 'app-list'],
      '#wrapper_attributes' => ['class' => 'app-list-container'],
    ];

    return $build;
  }

}
