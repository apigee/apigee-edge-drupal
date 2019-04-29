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

namespace Drupal\apigee_edge\Form;

use Drupal\apigee_edge\Exception\KeyProviderRequirementsException;
use Drupal\apigee_edge\Plugin\KeyProviderRequirementsInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\key\Form\KeyEditForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for saving the Apigee Edge API authentication key.
 */
class AuthenticationForm extends KeyEditForm {

  /**
   * The config name that stores the authentication key entity id.
   *
   * @var string
   */
  public const CONFIG_NAME = 'apigee_edge.auth';

  /**
   * The default key entity id created by this form.
   *
   * @var string
   */
  public const DEFAULT_KEY_ENTITY_ID = 'apigee_edge_connection_default';

  /**
   * AuthenticationForm constructor.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $key_storage
   *   The key storage.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(ConfigEntityStorageInterface $key_storage, ConfigFactoryInterface $config_factory, ModuleHandlerInterface $module_handler) {
    parent::__construct($key_storage);
    $this->configFactory = $config_factory;
    // Module handler must be set but Key does not set it.
    $this->moduleHandler = $module_handler;

    // If we use `$this->config()`, config overrides won't be considered.
    $config = $config_factory->get(static::CONFIG_NAME);

    // Loads to the key entity that belongs to the active key or creates a
    // new one _without_ saving it.
    if (!($active_key_id = $config->get('active_key')) || !($active_key = $key_storage->load($active_key_id))) {
      /** @var \Drupal\key\KeyInterface $active_key */
      $active_key = $key_storage->create([
        'id' => static::DEFAULT_KEY_ENTITY_ID,
        'label' => $this->t('Apigee Edge connection'),
        'description' => $this->t('Contains the credentials for connecting to Apigee Edge.'),
        'key_type' => 'apigee_auth',
        'key_input' => 'apigee_auth_input',
        'key_provider' => 'apigee_edge_private_file',
      ]);
    }

    // Sets the entity object for the form. This is the best place where we
    // can do that if we do not want to override n+1 methods inherited from the
    // EntityForm.
    $this->entity = $active_key;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('key'),
      $container->get('config.factory'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'apigee_edge_authentication_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Do the same trick as Key does in its add form.
    // (Key should provide a "default" form that should be to handle
    // both key add and edit operations just like Node does.
    // Drupal\key\Form\KeyAddForm::buildForm()
    // Only when the form is first built.
    if ($this->entity->isNew() && !$form_state->isRebuilding()) {
      // Set the key value data to NULL, since this is a new key.
      $form_state->set('key_value', [
        'original' => NULL,
        'processed_original' => NULL,
        'obscured' => NULL,
        'current' => '',
      ]);
    }

    // Hide the confirmation step added by Key.
    if (!$this->editConfirmed) {
      $this->confirmEdit($form, $form_state);
      $form_state->setRebuild(FALSE);
    }

    $form = parent::buildForm($form, $form_state);

    // Do not override title which is coming from the route.
    unset($form['#title']);
    // Hide fields that users should not be able to modify in this form.
    $form['label']['#access'] = FALSE;
    $form['id']['#access'] = FALSE;
    $form['description']['#access'] = FALSE;
    $form['settings']['type_section']['#access'] = FALSE;
    $form['settings']['input_section']['#title'] = $this->t('Apigee Edge connection settings');
    $form['settings']['input_section']['#weight'] = 0;
    $form['settings']['provider_section']['#title'] = $this->t('Advanced settings');
    // Provider selection should be closed by default unless the form rebuild
    // trigger by the provider selector or there is an error with the
    // key provider.
    /** @var \Drupal\apigee_edge\Plugin\KeyProviderRequirementsInterface $key_provider */
    $key_provider = $this->entity->getKeyProvider();
    $key_provider_requirements_error = FALSE;

    // Warn user about key provider pre-requirement issues before form
    // submission.
    if ($key_provider instanceof KeyProviderRequirementsInterface) {
      try {
        $key_provider->checkRequirements($this->entity);
      }
      catch (KeyProviderRequirementsException $exception) {
        $key_provider_requirements_error = TRUE;
        $form['settings']['provider_section']['key_provider']['#attributes']['class'][] = 'error';
      }
    }
    $form['settings']['provider_section']['#open'] = $key_provider_requirements_error || ($form_state->getTriggeringElement() && $form_state->getTriggeringElement()['#name'] === 'key_provider');
    $form['settings']['provider_section']['#weight'] = $form['settings']['input_section']['#weight'] + 1;

    // Override the title of the submit button.
    $form['actions']['submit']['#value'] = $this->t('Save configuration');

    // Hide the Delete button.
    $form['actions']['delete']['#access'] = FALSE;

    $form['#attached']['library'][] = 'apigee_edge/apigee_edge.admin';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    // @see https://www.drupal.org/project/key/issues/3048562
    $status = parent::save($form, $form_state);

    // Save the authentication key entity id to module's configuration.
    $this->configFactory->getEditable(static::CONFIG_NAME)->set('active_key', $this->entity->id())->save();
    // Override the redirect destination.
    $form_state->setRedirect('apigee_edge.settings');

    return $status;
  }

}
