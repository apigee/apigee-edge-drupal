<?php

namespace Drupal\apigee_edge\Job;

use Apigee\Edge\Api\Management\Entity\Developer;
use Apigee\Edge\Api\Management\Entity\DeveloperInterface;
use Apigee\Edge\Exception\ClientErrorException;
use Drupal\apigee_edge\Job;
use Drupal\user\UserInterface;

/**
 * A job to create a developer.
 */
class DeveloperCreate extends EdgeJob {

  public const ERR_DEVELOPER_ALREADY_EXISTS = 'developer.service.DeveloperAlreadyExists';

  /**
   * The developer to create.
   *
   * @var \Apigee\Edge\Api\Management\Entity\DeveloperInterface
   */
  protected $developer;

  /**
   * Whether to fail if a developer already exists.
   *
   * @var bool
   */
  protected $failWhenExists;

  /**
   * {@inheritdoc}
   */
  public function __construct(DeveloperInterface $developer, $fail_when_exists = FALSE) {
    parent::__construct();
    $this->developer = $developer;
    $this->failWhenExists = $fail_when_exists;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeRequest() {
    try {
      $this->getConnector()->getDeveloperController()->create($this->developer);
    }
    catch (ClientErrorException $ex) {
      if ($this->failWhenExists || $ex->getEdgeErrorCode() !== static::ERR_DEVELOPER_ALREADY_EXISTS) {
        throw $ex;
      }
      else {
        $this->recordMessage('Developer already exists.');
      }
    }
  }

  /**
   * Creates a job to create a remote developer for a local user.
   *
   * @param \Drupal\user\UserInterface $account
   *   Local Drupal account.
   *
   * @return \Drupal\apigee_edge\Job|null
   *   The created job or null if properties are missing on the local account.
   */
  public static function createForUser(UserInterface $account) : ? Job {
    $developer_data = [
      'userName' => $account->getAccountName(),
      'email' => $account->getEmail(),
      'firstName' => $account->get('first_name')->value,
      'lastName' => $account->get('last_name')->value,
    ];

    if (!$developer_data['firstName'] || !$developer_data['lastName']) {
      return NULL;
    }

    $jobs = new JobList(TRUE);
    $developer = new Developer($developer_data);
    $jobs->addJob(new static($developer));
    if (!$account->isActive()) {
      $jobs->addJob(new DeveloperSetStatus($developer->getEmail(), Developer::STATUS_INACTIVE));
    }

    return $jobs;
  }

  /**
   * {@inheritdoc}
   */
  public function __toString() : string {
    return t('Creating developer for @mail on edge', [
      '@mail' => $this->developer->getEmail(),
    ])->render();
  }

}
