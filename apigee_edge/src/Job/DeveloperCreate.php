<?php

namespace Drupal\apigee_edge\Job;

use Apigee\Edge\Api\Management\Entity\DeveloperInterface;
use Apigee\Edge\Exception\ClientErrorException;

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

}
