<?php

namespace Drupal\apigee_edge\Job;

use Apigee\Edge\Api\Management\Controller\DeveloperController;
use Apigee\Edge\Api\Management\Entity\Developer;

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
    $controller = new DeveloperController($this->getConnector()->getOrganization(), $this->getConnector()->getClient());
    $controller->setStatus($this->developerId, $this->status);
  }

  /**
   * {@inheritdoc}
   */
  public function __toString(): string {
    $args = ['@mail' => $this->developerId];
    return ($this->status == Developer::STATUS_ACTIVE ?
      t('Enabling developer (@mail) on edge', $args) :
      t('Disabling developer (@mail) on edge', $args))
      ->render();
  }

}
