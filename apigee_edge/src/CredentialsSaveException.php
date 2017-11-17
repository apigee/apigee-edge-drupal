<?php

namespace Drupal\apigee_edge;

/**
 * Defines the credentials save exception class.
 */
class CredentialsSaveException extends \Exception {

  /**
   * {@inheritdoc}
   */
  public function __construct($message = NULL) {
    parent::__construct($message);
  }

}
