<?php

namespace Drupal\apigee_edge\Job;

use Drupal\apigee_edge\Entity\Developer;
use Drupal\user\Entity\User;

/**
 * A job to create a Drupal user from an Apigee Edge developer.
 */
class CreateUser extends EdgeJob {

  /**
   * The developer's email.
   *
   * @var string
   */
  protected $mail;

  /**
   * {@inheritdoc}
   */
  public function __construct(string $mail) {
    parent::__construct();
    $this->mail = $mail;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeRequest() {
    /** @var \Drupal\apigee_edge\Entity\Developer $developer */
    $developer = Developer::load($this->mail);
    User::create([
      'name' => $developer->getUserName(),
      'mail' => $developer->getEmail(),
      'first_name' => $developer->getFirstName(),
      'last_name' => $developer->getLastName(),
      'status' => $developer->getStatus() === Developer::STATUS_ACTIVE,
      'pass' => user_password(),
    ])->save();
  }

  /**
   * {@inheritdoc}
   */
  public function __toString(): string {
    return t('Copying developer (@mail) to Drupal from Edge.', [
      '@mail' => $this->mail,
    ])->render();
  }

}
