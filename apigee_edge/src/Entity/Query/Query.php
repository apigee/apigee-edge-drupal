<?php

namespace Drupal\apigee_edge\Entity\Query;

use Drupal\apigee_edge\Entity\Storage\EdgeEntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Query\QueryBase;
use Drupal\Core\Entity\Query\QueryInterface;

class Query extends QueryBase implements QueryInterface {

  /**
   * @var \Drupal\apigee_edge\Entity\Storage\EdgeEntityStorageInterface
   */
  protected $storage;

  public function __construct(EntityTypeInterface $entity_type, string $conjunction, array $namespaces, EdgeEntityStorageInterface $storage) {
    parent::__construct($entity_type, $conjunction, $namespaces);
    $this->storage = $storage;
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
  }

}
