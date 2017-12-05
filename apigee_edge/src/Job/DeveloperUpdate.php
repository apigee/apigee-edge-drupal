<?php

namespace Drupal\apigee_edge\Job;

use Apigee\Edge\Api\Management\Entity\DeveloperInterface;

/**
 * A job to update a developer.
 */
class DeveloperUpdate extends EdgeJob {

  /**
   * A developer who is being updated.
   *
   * @var \Apigee\Edge\Api\Management\Entity\DeveloperInterface
   */
  protected $developer;

  /**
   * {@inheritdoc}
   */
  public function __construct(DeveloperInterface $developer) {
    parent::__construct();
    $this->developer = $developer;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeRequest() {
    $this->getConnector()->getDeveloperController()->update($this->developer);
  }

  /**
   * {@inheritdoc}
   */
  public function __toString() : string {
    return t('Updating developer (@mail) on edge', [
      '@mail' => $this->developer->getEmail(),
    ])->render();
  }

}
