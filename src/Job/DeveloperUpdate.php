<?php

namespace Drupal\apigee_edge\Job;

use Drupal\apigee_edge\Entity\DeveloperInterface;

/**
 * A job to update a developer.
 */
class DeveloperUpdate extends EdgeJob {

  /**
   * A developer who is being updated.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperInterface
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
    $this->developer->save();
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
