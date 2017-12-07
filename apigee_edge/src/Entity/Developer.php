<?php

namespace Drupal\apigee_edge\Entity;

use Apigee\Edge\Api\Management\Entity\Developer as EdgeDeveloper;
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
class Developer extends EdgeEntityBase {

  public const STATUS_ACTIVE = EdgeDeveloper::STATUS_ACTIVE;

  public const STATUS_INACTIVE = EdgeDeveloper::STATUS_INACTIVE;

  /**
   * The developer's username.
   *
   * @var string
   */
  protected $userName;

  /**
   * The developer's email.
   *
   * @var string
   */
  protected $email;

  /**
   * The developer's first name.
   *
   * @var string
   */
  protected $firstName;

  /**
   * The developer's last name.
   *
   * @var string
   */
  protected $lastName;

  /**
   * The developer's status.
   *
   * @var string
   */
  protected $status;

  /**
   * Creates a Drupal developer entity from a Drupal user.
   *
   * @param UserInterface $account
   *   The Drupal user.
   *
   * @return \Drupal\Core\Entity\EntityInterface|static
   *   The developer entity.
   */
  public static function createFromDrupalUser(UserInterface $account) {
    return static::create([
      'email' => $account->getEmail(),
      'userName' => $account->getAccountName(),
      'firstName' => $account->$account->get('first_name')->value,
      'lastName' => $account->$account->get('first_name')->value,
      'status' => $account->isActive() ? self::STATUS_ACTIVE : self::STATUS_INACTIVE,
    ]);
  }

  /**
   * {@inheritdoc}
   *
   * @param \Apigee\Edge\Api\Management\Entity\Developer $entity
   *   The Edge developer entity.
   */
  public static function createFromEdgeEntity($entity) {
    return static::create([
      'email' => $entity->getEmail(),
      'userName' => $entity->getUserName(),
      'firstName' => $entity->getFirstName(),
      'lastName' => $entity->getLastName(),
      'status' => $entity->getStatus(),
    ]);
  }

  /**
   * {@inheritdoc}
   *
   * @return \Apigee\Edge\Api\Management\Entity\Developer
   *   The Edge developer entity.
   */
  public function toEdgeEntity() {
    return new EdgeDeveloper([
      'email' => $this->email,
      'userName' => $this->userName,
      'firstName' => $this->firstName,
      'lastName' => $this->lastName,
      'status' => $this->status,
    ]);
  }

  /**
   * Gets the developer's username.
   *
   * @return string
   *   The developer's username.
   */
  public function getUserName(): string {
    return $this->userName;
  }

  /**
   * Sets the developer's username.
   *
   * @param string $userName
   *   The developer's username.
   */
  public function setUserName(string $userName) {
    $this->userName = $userName;
  }

  /**
   * Gets the developer's email.
   *
   * @return string
   *   The developer's email.
   */
  public function getEmail(): string {
    return $this->email;
  }

  /**
   * Sets the developer's email.
   *
   * @param string $email
   *   The developer's email.
   */
  public function setEmail(string $email) {
    $this->email = $email;
  }

  /**
   * Gets the developer's first name.
   *
   * @return string
   *   The developer's first name.
   */
  public function getFirstName(): string {
    return $this->firstName;
  }

  /**
   * Sets the developer's first name.
   *
   * @param string $firstName
   *   The developer's first name.
   */
  public function setFirstName(string $firstName) {
    $this->firstName = $firstName;
  }

  /**
   * Gets the developer's last name.
   *
   * @return string
   *   The developer's last name.
   */
  public function getLastName(): string {
    return $this->lastName;
  }

  /**
   * Sets the developer's last name.
   *
   * @param string $lastName
   *   The developer's last name.
   */
  public function setLastName(string $lastName) {
    $this->lastName = $lastName;
  }

  /**
   * Gets the developer's status.
   *
   * @return string
   *   The developer's status.
   */
  public function getStatus(): string {
    return $this->status;
  }

  /**
   * Sets the developer's status.
   *
   * @param string $status
   *   The developer's status.
   */
  public function setStatus(string $status) {
    $this->status = $status;
  }

}
