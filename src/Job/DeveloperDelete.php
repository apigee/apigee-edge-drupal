<?php

namespace Drupal\apigee_edge\Job;

use Drupal\apigee_edge\Entity\Developer;

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
    Developer::load($this->developerId)->delete();
  }

  /**
   * {@inheritdoc}
   */
  public function __toString(): string {
    return t('Deleting developer (@mail) from edge', [
      '@mail' => $this->developerId,
    ])->render();
  }

}
