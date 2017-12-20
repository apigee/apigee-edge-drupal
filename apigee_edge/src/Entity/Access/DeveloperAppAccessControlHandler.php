<?php

namespace Drupal\apigee_edge\Entity\Access;

use Drupal\apigee_edge\Entity\Developer;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandlerInterface;
use Drupal\Core\Entity\EntityHandlerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;

class DeveloperAppAccessControlHandler extends EntityHandlerBase implements EntityAccessControlHandlerInterface {

  protected $accessCache = [];

  /**
   * {@inheritdoc}
   */
  public function access(EntityInterface $entity, $operation, AccountInterface $account = NULL, $return_as_object = FALSE) {
    /** @var \Drupal\apigee_edge\Entity\DeveloperApp $entity */
    $account = $this->prepareUser($account);

    if ($account->hasPermission('administer edge developer app')) {
      $result = AccessResult::allowed()->cachePerPermissions();
      return $return_as_object ? $result : $result->isAllowed();
    }

    /** @var ?Developer $developer */
    $developer = Developer::load($account->getEmail());
    if ($developer && $developer->id() === $entity->getDeveloperId()) {
      $result = AccessResult::allowed()->cachePerPermissions();
      return $return_as_object ? $result : $result->isAllowed();
    }

    $result = AccessResult::forbidden()->cachePerPermissions();
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function createAccess($entity_bundle = NULL, AccountInterface $account = NULL, array $context = [], $return_as_object = FALSE) {
    $account = $this->prepareUser($account);

    if ($account->hasPermission('administer edge developer app')) {
      $result = AccessResult::allowed()->cachePerPermissions();
      return $return_as_object ? $result : $result->isAllowed();
    }

    if ($entity_bundle) {
      /** @var ?Developer $developer */
      $developer = Developer::load($account->getEmail());
      if ($developer && $developer->id() === $entity_bundle) {
        $result = AccessResult::allowed()->cachePerPermissions();
        return $return_as_object ? $result : $result->isAllowed();
      }
    }

    $result = AccessResult::forbidden()->cachePerPermissions();
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function resetCache() {
    $this->accessCache = [];
  }

  /**
   * {@inheritdoc}
   */
  public function fieldAccess($operation, FieldDefinitionInterface $field_definition, AccountInterface $account = NULL, FieldItemListInterface $items = NULL, $return_as_object = FALSE) {
    return AccessResult::allowed();
  }

  /**
   * Loads the current account object, if it does not exist yet.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account interface instance.
   *
   * @return \Drupal\Core\Session\AccountInterface
   *   Returns the current account object.
   */
  protected function prepareUser(AccountInterface $account = NULL) {
    if (!$account) {
      $account = \Drupal::currentUser();
    }
    return $account;
  }

}
