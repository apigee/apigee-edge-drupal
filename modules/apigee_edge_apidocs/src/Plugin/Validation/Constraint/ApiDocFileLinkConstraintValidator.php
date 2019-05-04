<?php

/**
 * Copyright 2019 Google Inc.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 */

namespace Drupal\apigee_edge_apidocs\Plugin\Validation\Constraint;

use Drupal\Core\Url;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Class ApiDocFileLinkConstraintValidator
 */
class ApiDocFileLinkConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint) {
    /** @var \Drupal\Core\Field\FieldItemListInterface $items */
    if (!isset($items)) {
      return;
    }

    foreach ($items as $item) {
      if ($item->isEmpty()) {
        continue;
      }

      $uri = $item->getValue()['uri'];

      // Try to resolve the given URI to a URL. It may fail if it's schemeless.
      try {
        $url = Url::fromUri($uri, ['absolute' => TRUE])->toString();
      }
      catch (\InvalidArgumentException $e) {
        $this->context->addViolation("The following error occurred while getting the link URL: @error", ['@error' => $e->getMessage()]);
        return;
      }

      try {
        $options = [
          'exceptions' => TRUE,
          'allow_redirects' => [
            'strict' => TRUE,
          ],
        ];

        // Perform only a HEAD method to save bandwidth.
        /* @var $response ResponseInterface */
        $response = \Drupal::httpClient()->head($url, $options);
      }
      catch (RequestException $request_exception) {
        $this->context->addViolation($constraint->notValid, [
          '%value' => $url,
        ]);
      }
    }
  }
}
