<?php

namespace Drupal\apigee_edge;

use Apigee\Edge\Exception\ApiException;

/**
 * Defines an exception for when a key fails to be loaded.
 */
class KeyNotFoundException extends ApiException {

  /**
   * {@inheritdoc}
   */
  public function __construct($message = 'Apigee Edge API authentication key not found.', $code = 0, \Exception $previous = NULL) {
    parent::__construct($message, $code, $previous);
  }

}
