<?php

namespace Drupal\apigee_edge\Entity\Storage;

use Apigee\Edge\Api\Management\Controller\DeveloperController;
use Apigee\Edge\Api\Management\Controller\DeveloperControllerInterface;
use Apigee\Edge\Controller\EntityCrudOperationsControllerInterface;
use Drupal\apigee_edge\SDKConnectorInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller class for developers.
 */
class DeveloperStorage extends EdgeEntityStorageBase implements DeveloperStorageInterface {

  /** @var \Drupal\Core\Entity\EntityTypeManagerInterface */
  protected $entityTypeManager;

  /**
   * DeveloperAppStorage constructor.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   * @param \Psr\Log\LoggerInterface $logger
   */
  public function __construct(ContainerInterface $container, EntityTypeInterface $entity_type, LoggerInterface $logger) {
    $this->entityTypeManager = $container->get('entity_type.manager');
    parent::__construct($container, $entity_type, $logger);
  }

  /**
   * {@inheritdoc}
   */
  public function getController(SDKConnectorInterface $connector): EntityCrudOperationsControllerInterface {
    return new DeveloperController($connector->getOrganization(), $connector->getClient());
  }

  /**
   * {@inheritdoc}
   */
  protected function doSave($id, EntityInterface $entity) {
    /** @var \Drupal\apigee_edge\Entity\Developer $entity */
    $developer_status = $entity->getStatus();
    $result = parent::doSave($id, $entity);

    // In case of entity update, the original email must be
    // replaced by the new email before a new API call.
    if ($result === SAVED_UPDATED) {
      $entity->setOriginalEmail($entity->getEmail());
    }

    $this->withController(function (DeveloperControllerInterface $controller) use ($entity, $developer_status) {
      $controller->setStatus($entity->id(), $developer_status);
    });

    return $result;
  }

  /**
   * {@inheritdoc}
   *
   * Adds Drupal user information to loaded entities.
   */
  protected function postLoad(array &$entities) {
    parent::postLoad($entities);
    $email_developerid_map = [];
    /** @var \Drupal\apigee_edge\Entity\Developer $entity */
    foreach ($entities as $entity) {
      $email_developerid_map[$entity->getEmail()] = $entity->getDeveloperId();
    }

    $email_developerid_map = array_unique($email_developerid_map);
    $users = $this->entityTypeManager->getStorage('user')
      ->loadByProperties(['apigee_edge_developer_id' => $email_developerid_map]);
    $uid_mail_map = array_map(function (UserInterface $user) {
      return $user->getEmail();
    }, $users);
    $mail_uid_map = array_flip($uid_mail_map);

    foreach ($entities as $entity) {
      // If developer id is not in this map it means the developer does
      // not exist in Drupal yet (developer syncing between Edge and Drupal is
      // required) or the developer id has not been stored in
      // related Drupal user yet.
      // This can be fixed with running developer sync too,
      // because it could happen that the user had been
      // created in Drupal before Edge connected was configured.
      // Although, this could be a result of a previous error
      // but there should be a log about that.
      if (isset($mail_uid_map[$entity->getEmail()])) {
        $entity->setOwnerId($mail_uid_map[$entity->getEmail()]);
      }
    }
  }

}
