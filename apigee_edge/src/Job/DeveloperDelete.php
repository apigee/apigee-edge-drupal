<?php

namespace Drupal\apigee_edge\Job;

/**
 * A job to delete a developer.
 */
class DeveloperDelete extends EdgeJob {

  /**
   * The id of the developer.
   *
   * @var string
   */
  protected $developerId;

  /**
   * {@inheritdoc}
   */
  public function __construct(string $developer_id) {
    parent::__construct();
    $this->developerId = $developer_id;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeRequest() {
    $this->getConnector()->getDeveloperController()->delete($this->developerId);
  }

}
