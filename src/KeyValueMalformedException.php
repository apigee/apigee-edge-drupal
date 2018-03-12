<?php

namespace Drupal\apigee_edge;

use Apigee\Edge\Exception\ApiException;

/**
 * Defines an exception for when a key value is malformed.
 */
class KeyValueMalformedException extends ApiException {

  /**
   * {@inheritdoc}
   */
  public function __construct($message = 'Apigee Edge API authentication key is malformed or not readable.', $code = 0, \Exception $previous = NULL) {
    parent::__construct($message, $code, $previous);
  }

}
