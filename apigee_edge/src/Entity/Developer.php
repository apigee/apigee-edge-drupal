<?php

namespace Drupal\apigee_edge\Entity;

use Apigee\Edge\Api\Management\Entity\Developer as EdgeDeveloper;

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
   * {@inheritdoc}
   *
   * @param \Drupal\apigee_edge\Entity\Developer $entity
   */
  public static function createFromEdgeEntity($entity) {
    /** @var \Apigee\Edge\Api\Management\Entity\Developer $entity */
    return static::create([
      'email' => $entity->getEmail(),
      'userName' => $entity->getUserName(),
      'firstName' => $entity->getFirstName(),
      'lastName' => $entity->getLastName(),
    ]);
  }

  /**
   * {@inheritdoc}
   *
   * @return \Apigee\Edge\Api\Management\Entity\Developer
   */
  public function toEdgeEntity() {
    return new EdgeDeveloper([
      'email' => $this->email,
      'userName' => $this->userName,
      'firstName' => $this->firstName,
      'lastName' => $this->lastName,
    ]);
  }

}
