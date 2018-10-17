<?php

namespace Drupal\apigee_edge\Exception;

use Throwable;

/**
 * Defines an exception for when a key value is malformed.
 */
class AuthenticationKeyValueMalformedException extends AuthenticationKeyException implements ApigeeEdgeExceptionInterface {

  /**
   * The key value field that is malformed.
   *
   * @var string
   */
  protected $problematicField;

  /**
   * KeyValueMalformedException constructor.
   *
   * We do not expose the name of problematic field by default in the message.
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
  public function __construct($problematic_field, $message = 'Apigee Edge API authentication key is malformed or not readable.', $code = 0, Throwable $previous = NULL) {
    $this->problematicField = $problematic_field;
    $message = strtr($message, ['@field' => $problematic_field]);
    parent::__construct($message, $code, $previous);
  }

  /**
   * Returns the name of the problematic field of a key.
   *
   * @return string
   *   Name of the field.
   */
  public function getProblematicField(): string {
    return $this->problematicField;
  }

}
