<?php

namespace Drupal\apigee_edge\Entity;

use Apigee\Edge\Api\Management\Entity\Developer as EdgeDeveloper;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Defines the Developer entity class.
 *
 * @\Drupal\apigee_edge\Annotation\EdgeEntityType(
 *   id = "developer",
 *   label = @Translation("Developer"),
 *   handlers = {
 *     "storage" = "\Drupal\apigee_edge\Entity\Storage\DeveloperStorage",
 *   }
 * )
 */
class Developer extends EdgeDeveloper implements DeveloperInterface {

  use EdgeEntityBaseTrait {
    id as private traitId;
  }

  /**
   * @var null|int
   */
  protected $drupalUserId;

  /**
   * The original email address of the developer.
   *
   * @var null|string
   */
  protected $originalEmail;

  /**
   * Constructs a Developer object.
   *
   * @param array $values
   *   An array of values to set, keyed by property name. If the entity type
   *   has bundles, the bundle key has to be specified.
   */
  public function __construct(array $values = []) {
    // Callers expect that the status is always either 'active' or 'inactive',
    // never null.
    if (!isset($values['status'])) {
      $values['status'] = static::STATUS_ACTIVE;
    }
    parent::__construct($values);
    $this->entityTypeId = 'developer';
    $this->originalEmail = isset($this->originalEmail) ? $this->originalEmail : $this->email;
  }

  /**
   * Creates developer entity from Drupal user.
   *
   * @param \Drupal\user\UserInterface $user
   *   The Drupal user account.
   *
   * @return Developer
   *   The developer entity.
   */
  public static function createFromDrupalUser(UserInterface $user): Developer {
    $developer_data = [
      'email' => $user->getEmail(),
      'originalEmail' => isset($user->original) ? $user->original->getEmail() : $user->getEmail(),
      'userName' => $user->getAccountName(),
      'firstName' => (string) $user->get('first_name')->value,
      'lastName' => (string) $user->get('last_name')->value,
      'status' => $user->isActive() ? static::STATUS_ACTIVE : static::STATUS_INACTIVE,
    ];

    $developer = !isset($user->original) ? static::create($developer_data) : new static($developer_data);
    $developer->setOwnerId($user->id());

    return $developer;
  }

  /**
   * {@inheritdoc}
   */
  public function uuid() {
    return parent::id();
  }

  /**
   * {@inheritdoc}
   */
  public function id(): ? string {
    return $this->originalEmail;
  }

  /**
   * {@inheritdoc}
   */
  public function setEmail(string $email): void {
    parent::setEmail($email);
    if ($this->originalEmail === NULL) {
      $this->originalEmail = $this->email;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setOriginalEmail(string $originalEmail) {
    $this->originalEmail = $originalEmail;
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

}
