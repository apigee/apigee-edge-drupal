<?php

namespace Drupal\apigee_edge\Exception;

use Apigee\Edge\Exception\ApiException;
use Throwable;

/**
 * Defines an exception for when a key value is malformed.
 */
class KeyValueMalformedException extends AuthenticationKeyException {

  /**
   * The key value field that is malformed.
   *
   * @var string
   */
  protected $problematicField;

  /**
   * KeyValueMalformedException constructor.
   *
   * @param string $problematic_field
   *   Name of the field that caused the issue.
   * @param string $message
   *   Exception message.
   * @param int $code
   *   Error code.
   * @param \Throwable|null $previous
   *   Previous exception.
   */
  public function __construct($problematic_field, $message = '', $code = 0, Throwable $previous = NULL) {
    $this->problematicField = $problematic_field;
    // We do not expose the name of problematic field by default in the message.
    $message = $message ?: t('Apigee Edge API authentication key is malformed or not readable.');
    parent::__construct($message, $code, $previous);
  }

  /**
   * @return string
   */
  public function getProblematicField(): string {
    return $this->problematicField;
  }

}
