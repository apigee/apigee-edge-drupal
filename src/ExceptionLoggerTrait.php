<?php

namespace Drupal\apigee_edge;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Utility\Error;
use Psr\Log\LoggerAwareTrait;

trait ExceptionLoggerTrait {
  use LoggerAwareTrait;

  protected function logException(\Exception $ex, ?string $message = NULL, array $variables = [], int $severity = RfcLogLevel::ERROR, ?string $link = NULL) {
    if (empty($message)) {
      $message = '%type: @message in %function (line %line of %file).';
    }

    if ($link) {
      $variables['link'] = $link;
    }

    $variables += Error::decodeException($ex);

    $this->logger->log($severity, $message, $variables);
  }

}
