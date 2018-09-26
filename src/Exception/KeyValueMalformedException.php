<?php

namespace Drupal\apigee_edge\Exception;

use Apigee\Edge\Exception\ApiException;
use Throwable;

/**
 * Defines an exception for when a key value is malformed.
 */
class KeyValueMalformedException extends ApiException {

  /**
   * The key value field that cannot be read.
   *
   * @var string
   */
  protected $missingField;

  /**
   * KeyValueMalformedException constructor.
   *
   * @param string $missing_field
   *   The missing key value field.
   * @param string $message
   *   Exception message.
   * @param int $code
   *   Error code.
   * @param \Throwable|null $previous
   *   Previous exception.
   */
  public function __construct($missing_field = NULL, $message = '', $code = 0, Throwable $previous = NULL) {
    $this->missingField = $missing_field;
    $message = $message ?: t('Apigee Edge API authentication key is malformed or not readable.');
    parent::__construct($message, $code, $previous);
  }

}
