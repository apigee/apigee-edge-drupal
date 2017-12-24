<?php

namespace Drupal\apigee_edge\Job;

use Apigee\Edge\Api\Management\Entity\Developer;
use Drupal\user\Entity\User;

/**
 * A job to create a Drupal user from an edge developer.
 */
class CreateUser extends EdgeJob {

  /**
   * @var string
   */
  protected $mail;

  /**
   * @var int
   */
  protected $current = 0;

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
    /** @var \Apigee\Edge\Api\Management\Entity\Developer $developer */
    $developer = $this->getConnector()->getDeveloperController()->load($this->mail);
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
    return t('Copying developer (@mail) to Drupal from edge.', [
      '@mail' => $this->mail,
    ])->render();
  }

}
