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
  public function __construct($key_id, $code = 0, \Exception $previous = NULL) {
    $message = isset($key_id) ? 'Apigee Edge API authentication key "' . $key_id . '" not found.' : 'Apigee Edge API authentication key is not set.';
    parent::__construct($message, $code, $previous);
  }

}
