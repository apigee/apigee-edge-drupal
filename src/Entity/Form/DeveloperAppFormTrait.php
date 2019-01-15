<?php

/**
 * Copyright 2018 Google Inc.
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

namespace Drupal\apigee_edge\Entity\Form;

use Apigee\Edge\Exception\ApiException;
use Apigee\Edge\Exception\ClientErrorException;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Utility\Error;

/**
 * Helper trait that contains developer app (create/edit) form specific tweaks.
 *
 * @see \Drupal\apigee_edge\Entity\Form\DeveloperAppCreateForm
 * @see \Drupal\apigee_edge\Entity\Form\DeveloperAppCreateFormForDeveloper
 * @see \Drupal\apigee_edge\Entity\Form\DeveloperAppEditForm
 * @see \Drupal\apigee_edge\Entity\Form\DeveloperAppEditFormForDeveloper
 */
trait DeveloperAppFormTrait {

  /**
   * {@inheritdoc}
   */
  public static function appExists(string $name, array $element, FormStateInterface $formState): bool {
    // Do not validate if app name is not set.
    if ($name === '') {
      return FALSE;
    }

    // We use the developer app controller factory here instead of entity
    // query to reduce the number API calls. (Entity query may load all
    // developers to return whether the given developer has an app with
    // the provided name already.)
    /** @var \Drupal\apigee_edge\Entity\Controller\DeveloperAppControllerFactoryInterface $factory */
    $factory = \Drupal::service('apigee_edge.controller.developer_app_controller_factory');
    $app = TRUE;
    try {
      $app = $factory->developerAppController($formState->getValue('owner'))->load($name);
    }
    catch (ApiException $exception) {
      if ($exception instanceof ClientErrorException && $exception->getEdgeErrorCode() === 'developer.service.AppDoesNotExist') {
        $app = FALSE;
      }
      else {
        // Fail safe, return TRUE in case of an API communication error or an
        // unexpected response.
        $context = [
          '%app_name' => $name,
          '%owner' => $formState->getValue('owner'),
        ];
        $context += Error::decodeException($exception);
        \Drupal::logger('apigee_edge')->error("Unable to properly validate an app name's uniqueness. App name: %app_name. Owner: %owner. @message %function (line %line of %file). <pre>@backtrace_string</pre>", $context);
      }
    }

    return (bool) $app;
  }

  /**
   * {@inheritdoc}
   */
  protected function appEntityDefinition(): EntityTypeInterface {
    return \Drupal::entityTypeManager()->getDefinition('developer_app');
  }

  /**
   * {@inheritdoc}
   */
  protected function appOwnerEntityDefinition(): EntityTypeInterface {
    return \Drupal::entityTypeManager()->getDefinition('developer');
  }

  /**
   * {@inheritdoc}
   */
  protected function appCredentialLifeTime(): int {
    return \Drupal::config('apigee_edge.developer_app_settings')->get('credential_lifetime');
  }

}
