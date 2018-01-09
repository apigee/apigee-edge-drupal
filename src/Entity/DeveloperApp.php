<?php

namespace Drupal\apigee_edge\Entity;

use Apigee\Edge\Api\Management\Entity\DeveloperApp as EdgeDeveloperApp;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Defines the Developer app entity class.
 *
 * @\Drupal\apigee_edge\Annotation\EdgeEntityType(
 *   id = "developer_app",
 *   label_singular = @Translation("Developer App"),
 *   label_plural = @Translation("Developer Apps"),
 *   label_count = @PluralTranslation(
 *     singular = "@count Developer App",
 *     plural = "@count Developer Apps",
 *   ),
 *   handlers = {
 *     "storage" = "\Drupal\apigee_edge\Entity\Storage\DeveloperAppStorage",
 *     "access" = "Drupal\entity\UncacheableEntityAccessControlHandler",
 *     "permission_provider" = "\Drupal\apigee_edge\Entity\DeveloperAppEntityPermissionProvider",
 *     "form" = {
 *       "add" = "\Drupal\apigee_edge\Entity\Form\DeveloperAppCreate",
 *     },
 *   },
 *   links = {
 *     "add-form" = "/developer_app/add",
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
    toUrl as private traitToUrl;
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
  }

  /**
   * {@inheritdoc}
   */
  public function toUrl($rel = 'canonical', array $options = []) {
    // Pass required router parameters.
    if ($rel == 'developer-app-details') {
      $route_name = "entity.developer_app.developer_app_details";
      $route_parameters = [
        'user' => $this->drupalUserId,
        'app_name' => $this->getName(),
      ];
      $options = [
        'entity_type' => $this->entityTypeId,
        'entity' => $this,
        // Display links by default based on the current language.
        'language' => $this->language(),
      ];
      return new Url($route_name, $route_parameters, $options);
    }
    else {
      return $this->traitToUrl($rel, $options);
    }
  }

}
