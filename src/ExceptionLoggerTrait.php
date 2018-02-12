<?php
/**
 * Copyright 2018 Google Inc.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License version 2 as published by the 
 * Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT 
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY 
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public 
 * License for more details.
 *
 * You should have received a copy of the GNU General Public License along 
 * with this program; if not, write to the Free Software Foundation, Inc., 51 
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

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
