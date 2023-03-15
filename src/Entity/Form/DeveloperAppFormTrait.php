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
use Drupal\apigee_edge\Entity\ApiProductInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
  public static function appExists(string $name, array $element, FormStateInterface $form_state): bool {
    // Do not validate if app name is not set.
    if ($name === '') {
      return FALSE;
    }

    // Return TRUE if developer account has not been found for this Drupal user.
    // TODO Make sure that DeveloperAppCreateEditFormForDeveloper can be
    // used only if the Drupal user in the route has a developer account
    // in Apigee Edge.
    if ($form_state->getValue('owner') === NULL) {
      return TRUE;
    }

    // We use the developer app controller factory here instead of entity
    // query to reduce the number API calls. (Entity query may load all
    // developers to return whether the given developer has an app with
    // the provided name already.)
    /** @var \Drupal\apigee_edge\Entity\Controller\DeveloperAppControllerFactoryInterface $factory */
    $factory = \Drupal::service('apigee_edge.controller.developer_app_controller_factory');
    $app = TRUE;
    try {
      $app = $factory->developerAppController($form_state->getValue('owner'))->load($name);
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
          '%owner' => $form_state->getValue('owner'),
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
    return $this->getEntityTypeManager()->getDefinition('developer_app');
  }

  /**
   * {@inheritdoc}
   */
  protected function appOwnerEntityDefinition(): EntityTypeInterface {
    return $this->getEntityTypeManager()->getDefinition('developer');
  }

  /**
   * {@inheritdoc}
   */
  protected function appCredentialLifeTime(): int {
    $config_name = 'apigee_edge.developer_app_settings';
    $config = method_exists($this, 'config') ? $this->config($config_name) : \Drupal::config($config_name);
    return $config->get('credential_lifetime');
  }

  /**
   * Allows to access to the injected entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  private function getEntityTypeManager(): EntityTypeManagerInterface {
    if (property_exists($this, 'entityTypeManager') && $this->entityTypeManager instanceof EntityTypeManagerInterface) {
      return $this->entityTypeManager;
    }

    return \Drupal::entityTypeManager();
  }

  /**
   * {@inheritdoc}
   */
  protected function apiProductList(array $form, FormStateInterface $form_state): array {
    $email = $form_state->getValue('owner') ?? $form['owner']['#value'] ?? $form['owner']['#default_value'];
    /** @var \Drupal\user\UserInterface|null $account */
    $account = user_load_by_mail($email);

    return array_filter(\Drupal::entityTypeManager()->getStorage('api_product')->loadMultiple(), function (ApiProductInterface $product) use ($account) {
      return $product->access('assign', $account);
    });
  }

}
