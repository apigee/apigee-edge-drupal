<?php

namespace Drupal\apigee_edge\Entity;

use Apigee\Edge\Api\Management\Entity\DeveloperApp as EdgeDeveloperApp;
use Drupal\Core\Annotation\Translation;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Defines the Developer app entity class.
 *
 * @\Drupal\apigee_edge\Annotation\EdgeEntityType(
 *   id = "developer_app",
 *   label = @Translation("Developer App"),
 *   label_singular = @Translation("Developer App"),
 *   label_plural = @Translation("Developer Apps"),
 *   label_count = @PluralTranslation(
 *     singular = "@count Developer App",
 *     plural = "@count Developer Apps",
 *   ),
 *   handlers = {
 *     "storage" = "Drupal\apigee_edge\Entity\Storage\DeveloperAppStorage",
 *     "access" = "Drupal\entity\UncacheableEntityAccessControlHandler",
 *     "permission_provider" = "Drupal\apigee_edge\Entity\DeveloperAppEntityPermissionProvider",
 *     "form" = {
 *       "default" = "Drupal\apigee_edge\Entity\Form\DeveloperAppCreateForm",
 *       "add" = "Drupal\apigee_edge\Entity\Form\DeveloperAppCreateForm",
 *       "add_for_developer" = "Drupal\apigee_edge\Entity\Form\DeveloperAppCreateFormForDeveloper",
 *       "delete" = "Drupal\apigee_edge\Entity\Form\DeveloperAppDeleteForm",
 *       "delete_for_developer" = "Drupal\apigee_edge\Entity\Form\DeveloperAppDeleteFormForDeveloper",
 *     },
 *     "list_builder" = "Drupal\apigee_edge\Entity\ListBuilder\DeveloperAppListBuilder",
 *   },
 *   links = {
 *     "collection" = "/developer-apps",
 *     "add-form" = "/developer-apps/add",
 *     "delete-form" = "/developer-apps/{developer_app}/delete",
 *     "collection-by-developer" = "/user/{user}/apps",
 *     "add-form-for-developer" = "/user/{user}/apps/add",
 *     "delete-form-for-developer" = "/user/{user}/apps/{app}/delete",
 *     "canonical-by-developer" = "/user/{user}/apps/{app}/details",
 *   },
 *   entity_keys = {
 *     "id" = "appId",
 *     "bundle" = "developerId",
 *   },
 *   permission_granularity = "entity_type",
 *   admin_permission = "administer developer_app",
 * )
 */
class DeveloperApp extends EdgeDeveloperApp implements DeveloperAppInterface {

  use EdgeEntityBaseTrait {
    id as private traitId;
    urlRouteParameters as private traitUrlRouteParameters;
  }

  /** @var null|int */
  protected $drupalUserId;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $values = []) {
    parent::__construct($values);
    $this->entityTypeId = 'developer_app';
  }

  /**
   * {@inheritdoc}
   */
  public function id(): ? string {
    return $this->getAppId();
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->drupalUserId === NULL ? NULL : User::load($this->drupalUserId);
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->drupalUserId = $account->id();
    // TODO What should we do if id is missing from the user?
    $this->developerId = $account->get('apigee_edge_developer_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->drupalUserId;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->drupalUserId = $uid;
    $user = User::load($uid);
    // TODO Should we throw an exception if the user can not be loaded?
    if ($user) {
      $this->developerId = $user->get('apigee_edge_developer_id')->target_id;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function urlRouteParameters($rel) {
    $params = $this->traitUrlRouteParameters($rel);
    if ($rel == 'add-form-for-developer') {
      $params['user'] = $this->drupalUserId;
    }
    elseif ($rel == 'collection-by-developer') {
      $params['user'] = $this->drupalUserId;
      unset($params['developer_app']);
    }
    elseif (in_array($rel, ['canonical-by-developer', 'delete-form-for-developer'])) {
      $params['user'] = $this->drupalUserId;
      $params['app'] = $this->getName();
      unset($params['developer_app']);
    }

    return $params;
  }

}
