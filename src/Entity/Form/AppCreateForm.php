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

use Drupal\Core\Form\FormStateInterface;
use Apigee\Edge\Exception\ApiException;
use Drupal\apigee_edge\Entity\AppInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\Error;

/**
 * Base entity form for developer- and team (company) app create forms.
 */
abstract class AppCreateForm extends AppForm {

  use ApiProductSelectionFormTrait;

  /**
   * {@inheritdoc}
   */
  final public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $this->alterFormBeforeApiProductElement($form, $form_state);
    $form['api_products'] = $this->apiProductsFormElement($form, $form_state);
    $this->alterFormWithApiProductElement($form, $form_state);
    return $form;
  }

  /**
   * Allows to alter the form before API products gets added.
   *
   * @param array $form
   *   Form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  protected function alterFormBeforeApiProductElement(array &$form, FormStateInterface $form_state): void {}

  /**
   * Allows to alter the form after API products form element have been added.
   *
   * @param array $form
   *   Form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  protected function alterFormWithApiProductElement(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  protected function saveAppCredentials(AppInterface $app, FormStateInterface $form_state): ?bool {
    // On app creation we only support creation of one app credential at this
    // moment.
    $result = FALSE;
    $app_credential_controller = $this->appCredentialController($app->getAppOwner(), $app->getName());
    $logger = $this->logger('apigee_edge');

    /** @var \Apigee\Edge\Api\Management\Entity\AppCredential[] $credentials */
    $credentials = $app->getCredentials();
    /** @var \Apigee\Edge\Api\Management\Entity\AppCredential $credential */
    $credential = reset($credentials);
    $selected_products = array_values(array_filter((array) $form_state->getValue('api_products')));

    try {
      if ($this->appCredentialLifeTime() === 0) {
        $app_credential_controller->addProducts($credential->id(), $selected_products);
      }
      else {
        $app_credential_controller->delete($credential->id());
        // The value of -1 indicates no set expiry. But the value of 0 is not
        // acceptable by the server (InvalidValueForExpiresIn).
        $app_credential_controller->generate($selected_products, $app->getAttributes(), $app->getCallbackUrl(), [], $this->appCredentialLifeTime() * 86400000);
      }
      $result = TRUE;
    }
    catch (ApiException $exception) {
      $context = [
        '%app_name' => $app->label(),
        '%owner' => $app->getAppOwner(),
        'link' => $app->toLink()->toString(),
      ];
      $context += Error::decodeException($exception);
      $logger->error('Unable to set up app credentials on a created app. App name: %app_name. Owner: %owner. @message %function (line %line of %file). <pre>@backtrace_string</pre>', $context);
      try {
        // Apps without an associated API product should not exist in
        // Apigee Edge because they cause problems.
        $app->delete();
      }
      catch (EntityStorageException $exception) {
        $context = Error::decodeException($exception) + $context;
        $logger->critical('Unable automatically remove %app_name app owned by %owner after app credential set up has failed meanwhile app creation. @message %function (line %line of %file). <pre>@backtrace_string</pre>', $context);
        // save() is not going to redirect the user in this case, but.
        $form_state->setRedirectUrl($app->toUrl('collection'));
      }
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function saveButtonLabel() : TranslatableMarkup {
    return $this->t('Add @app', [
      '@app' => mb_strtolower($this->appEntityDefinition()->getSingularLabel()),
    ]);
  }

}
