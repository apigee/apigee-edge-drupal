<?php

namespace Drupal\apigee_edge\Entity\Query;

use Drupal\apigee_edge\SDKConnector;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryBase;
use Drupal\Core\Entity\Query\QueryException;
use Drupal\Core\Entity\Query\QueryFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class QueryFactory implements QueryFactoryInterface, EventSubscriberInterface {

  /**
   * @var string[]
   */
  protected $namespaces;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $manager;

  public function __construct(EntityTypeManagerInterface $manager) {
    $this->manager = $manager;
    $this->namespaces = QueryBase::getNamespaces($this);
  }

  /**
   * {@inheritdoc}
   */
  public function get(EntityTypeInterface $entity_type, $conjunction) {
    /** @var \Drupal\apigee_edge\Entity\Storage\EdgeEntityStorageInterface $storage */
    $storage = $this->manager->getStorage($entity_type);
    return new Query($entity_type, $conjunction, $this->namespaces, $storage);
  }

  /**
   * {@inheritdoc}
   */
  public function getAggregate(EntityTypeInterface $entity_type, $conjunction) {
    throw new QueryException('Aggregation over edge entities is not supported');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [];
  }

}
