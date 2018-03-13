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
  public function __construct($missing_field = NULL, $code = 0, \Exception $previous = NULL) {
    $message = 'Apigee Edge API authentication key is malformed or not readable.';
    $message = $missing_field === NULL ? $message : "{$message} Missing field: {$missing_field}";
    parent::__construct($message, $code, $previous);
  }

}
