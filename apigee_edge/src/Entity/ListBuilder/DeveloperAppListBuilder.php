<?php

namespace Drupal\apigee_edge\Entity\ListBuilder;

use Drupal\apigee_edge\Entity\Developer;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Defines a class to build a listing of developer app entities.
 */
class DeveloperAppListBuilder extends EntityListBuilder {

  /**
   * The path of the current request.
   *
   * @var string
   */
  protected $currentPath;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, string $current_path) {
    parent::__construct($entity_type, $storage);
    $this->currentPath = $current_path;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity.manager')->getStorage($entity_type->id()),
      $container->get('path.current')->getPath()
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds() {
    $user = User::load(explode('/', $this->currentPath)[2]);
    if (!isset($user)) {
      throw new NotFoundHttpException();
    }

    $developer = Developer::load($user->getEmail());
    if (!isset($developer)) {
      throw new NotFoundHttpException();
    }

    $query = $this->getStorage()->getQuery()
      ->condition('developerId', $developer->getDeveloperId());

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $query->pager($this->limit);
    }
    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [
      'app_name' => [
        'data' => $this->t('App name'),
        'field' => 'name',
        'specifier' => 'name',
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
      ],
      'status' => [
        'data' => $this->t('Status'),
        'field' => 'status',
        'specifier' => 'status',
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
      ],
    ];
    return $header;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\apigee_edge\Entity\DeveloperApp $entity */
    $row['app_name']['data'] = [
      '#type' => 'link',
      '#title' => $entity->getDisplayName(),
      '#url' => $entity->toUrl(),
    ];
    $row['status']['data'] = $entity->getStatus();

    return $row;
  }

}
