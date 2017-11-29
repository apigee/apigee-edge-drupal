<?php

namespace Drupal\apigee_edge\Job;

/**
 * A job that updates a developer's status.
 */
class DeveloperSetStatus extends EdgeJob {

  /**
   * The id of the developer.
   *
   * @var string
   */
  protected $developerId;

  /**
   * Status to set.
   *
   * @var string
   */
  protected $status;

  /**
   * {@inheritdoc}
   */
  public function __construct(string $developer_id, string $status) {
    parent::__construct();
    $this->developerId = $developer_id;
    $this->status = $status;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeRequest() {
    $this->getConnector()->getDeveloperController()->setStatus($this->developerId, $this->status);
  }
}
