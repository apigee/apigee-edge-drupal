<?php

namespace Drupal\apigee_edge\Entity\Storage;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller class for developers.
 */
class DeveloperStorage extends EdgeEntityStorageBase implements DeveloperStorageInterface {

  /**
   * {@inheritdoc}
   */
  protected function doLoadMultiple(array $ids = NULL) {
    // TODO: Implement doLoadMultiple() method.
  }

  /**
   * {@inheritdoc}
   */
  protected function has($id, EntityInterface $entity) {
    // TODO: Implement has() method.
  }

  /**
   * {@inheritdoc}
   */
  protected function doDelete($entities) {
    // TODO: Implement doDelete() method.
  }

  /**
   * {@inheritdoc}
   */
  protected function doSave($id, EntityInterface $entity) {
    // TODO: Implement doSave() method.
  }

  /**
   * {@inheritdoc}
   */
  protected function getQueryServiceName() {
    // TODO: Implement getQueryServiceName() method.
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    // TODO: Implement createInstance() method.
  }

  /**
   * {@inheritdoc}
   */
  public function loadRevision($revision_id) {
    // TODO: Implement loadRevision() method.
  }

  /**
   * {@inheritdoc}
   */
  public function deleteRevision($revision_id) {
    // TODO: Implement deleteRevision() method.
  }

}
