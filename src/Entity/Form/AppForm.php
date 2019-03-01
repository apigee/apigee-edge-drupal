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

use Drupal\apigee_edge\Entity\AppInterface;
use Drupal\apigee_edge\Entity\Controller\AppCredentialControllerInterface;
use Drupal\apigee_edge\Entity\Storage\EdgeEntityStorageBase;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\Core\Utility\Error;

/**
 * Base entity form for developer- and team (company) app create/edit forms.
 */
abstract class AppForm extends FieldableEdgeEntityForm {

  /**
   * Constructs AppCreationForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    // Ensure entity type manager is always initialized.
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $form['#tree'] = TRUE;

    /** @var \Drupal\apigee_edge\Entity\AppInterface $app */
    $app = $this->entity;

    // By default we render this as a simple text field, sub-classes can change
    // this.
    $form['owner'] = [
      '#title' => $this->t('Owner'),
      '#description' => $this->t("A developer's id (uuid), email address or a team's (company's) name."),
      '#type' => 'textfield',
      '#weight' => -100,
      '#default_value' => $app->getAppOwner(),
      '#required' => TRUE,
    ];

    $form['name'] = [
      '#type' => 'machine_name',
      '#machine_name' => [
        'source' => ['displayName', 'widget', 0, 'value'],
        'label' => $this->t('Internal name'),
        'exists' => [$this, 'appExists'],
      ],
      '#title' => $this->t('Internal name'),
      // It should/can not be changed if app is not new.
      '#disabled' => !$app->isNew(),
      '#default_value' => $app->getName(),
    ];

    $form['#attached']['library'][] = 'apigee_edge/apigee_edge.components';
    $form['#attributes']['class'][] = 'apigee-edge--form';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->saveButtonLabel();
    return $actions;
  }

  /**
   * Returns the list of API product that the user can see on the form.
   *
   * @return \Drupal\apigee_edge\Entity\ApiProductInterface[]
   *   Array of API product entities.
   */
  abstract protected function apiProductList(array $form, FormStateInterface $form_state): array;

  /**
   * Returns the label of the Save button on the form.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The translatable label.
   */
  protected function saveButtonLabel() : TranslatableMarkup {
    return $this->t('Save');
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\apigee_edge\Entity\AppInterface $entity */
    $entity = parent::buildEntity($form, $form_state);
    // Set the owner of the app. Without this an app can not be saved.
    // @see \Drupal\apigee_edge\Entity\Controller\DeveloperAppEdgeEntityControllerProxy::create()
    $entity->setAppOwner($form_state->getValue('owner'));
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\apigee_edge\Entity\AppInterface $app */
    $app = $this->entity;
    $was_new = $app->isNew();
    $context = [
      '@app' => $this->appEntityDefinition()->getSingularLabel(),
      '@operation' => $was_new ? $this->t('created') : $this->t('updated'),
    ];
    // First save the app entity on Apigee Edge.
    $result = $this->saveApp();
    if ($result > 0) {
      // Then apply credential changes on the app.
      $credential_save_result = $this->saveAppCredentials($app, $form_state);
      // If credential save result is either successful (TRUE) or NULL
      // (no operation performed because there were no change in API product
      // association) then we consider the app save as successful.
      if ($credential_save_result ?? TRUE) {
        $this->messenger()->addStatus($this->t('@app has been successfully @operation.', $context));
        // Also, if app credential(s) could be successfully saved as well then
        // display an extra confirmation message about this.
        if ($credential_save_result === TRUE) {
          $this->messenger()->addStatus($this->t("Credential's product list has been successfully updated."));
        }
        // Only redirect the user from the add/edit form if all operations
        // could be successfully performed.
        $form_state->setRedirectUrl($this->getRedirectUrl());
      }
      else {
        // Display different error messages on app create/edit.
        if ($was_new) {
          $this->messenger()->addError($this->t('Unable to set up credentials on the created app. Please try again.'));
        }
        else {
          $this->messenger()->addError($this->t('Unable to update credentials on the app. Please try again.'));
        }
      }
    }
    else {
      $this->messenger()->addError($this->t('@app could not be @operation. Please try again.', $context));
    }

    return $result;
  }

  /**
   * Saves the app entity on Apigee Edge.
   *
   * It should log failures but it should not display messages to users.
   * This is handled in save().
   *
   * @return int
   *   SAVED_NEW, SAVED_UPDATED or SAVED_UNKNOWN.
   */
  protected function saveApp(): int {
    /** @var \Drupal\apigee_edge\Entity\AppInterface $app */
    $app = $this->entity;
    $was_new = $app->isNew();
    try {
      $result = $app->save();
    }
    catch (EntityStorageException $exception) {
      $context = [
        '%app_name' => $app->label(),
        '%owner' => $app->getAppOwner(),
        '@app' => $this->appEntityDefinition()->getLowercaseLabel(),
        '@owner' => $this->appOwnerEntityDefinition()->getLowercaseLabel(),
        '@operation' => $was_new ? $this->t('create') : $this->t('update'),
      ];
      $context += Error::decodeException($exception);
      $this->logger('apigee_edge')->critical('Could not @operation %app_name @app of %owner @owner. @message %function (line %line of %file). <pre>@backtrace_string</pre>', $context);
      $result = EdgeEntityStorageBase::SAVED_UNKNOWN;
    }

    return $result;
  }

  /**
   * Save app credentials on Apigee Edge.
   *
   * It should log failures but it should not display messages to users.
   * This is handled in save().
   *
   * @param \Drupal\apigee_edge\Entity\AppInterface $app
   *   The app entity which credentials gets updated.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object with the credential related changes.
   *
   * @return bool|null
   *   TRUE on success, FALSE or failure, NULL if no action performed (because
   *   credentials did not change).
   */
  abstract protected function saveAppCredentials(AppInterface $app, FormStateInterface $form_state): ?bool;

  /**
   * Returns the URL where the user should be redirected after form submission.
   *
   * @return \Drupal\Core\Url
   *   The redirect URL.
   */
  protected function getRedirectUrl(): Url {
    $entity = $this->getEntity();
    if ($entity->hasLinkTemplate('collection')) {
      // If available, return the collection URL.
      return $entity->toUrl('collection');
    }
    else {
      // Otherwise fall back to the front page.
      return Url::fromRoute('<front>');
    }
  }

  /**
   * Returns the app specific app credential controller.
   *
   * @param string $owner
   *   The developer id (UUID), email address or team (company) name.
   * @param string $app_name
   *   The name of an app.
   *
   * @return \Drupal\apigee_edge\Entity\Controller\AppCredentialControllerInterface
   *   The app credential controller.
   */
  abstract protected function appCredentialController(string $owner, string $app_name) : AppCredentialControllerInterface;

  /**
   * Checks if the owner already has an app with the same name.
   *
   * @param string $name
   *   The app name.
   * @param array $element
   *   Form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @return bool
   *   TRUE if the owner already has an app with the provided name or in case
   *   if an API communication error, FALSE otherwise.
   */
  abstract public static function appExists(string $name, array $element, FormStateInterface $form_state): bool;

  /**
   * Returns the default lifetime of a created app credential.
   *
   * @return int
   *   App credential lifetime in seconds, 0 for never expire.
   */
  abstract protected function appCredentialLifeTime(): int;

  /**
   * Returns the developer/team (company) app entity definition.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface
   *   The app entity definition.
   */
  abstract protected function appEntityDefinition(): EntityTypeInterface;

  /**
   * Returns the app owner (developer or team (company)) entity definition.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface
   *   The app owner entity definition.
   */
  abstract protected function appOwnerEntityDefinition(): EntityTypeInterface;

  /**
   * {@inheritdoc}
   */
  public function getEntityFromRouteMatch(RouteMatchInterface $route_match, $entity_type_id) {
    // Always expect that the parameter in the route is not the entity type
    // (ex: {developer_app}) in case of apps rather just {app}.
    if ($route_match->getRawParameter('app') !== NULL) {
      $entity = $route_match->getParameter('app');
    }
    else {
      $entity = parent::getEntityFromRouteMatch($route_match, $entity_type_id);
    }
    return $entity;
  }

}
