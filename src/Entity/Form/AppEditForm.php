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

use Apigee\Edge\Api\Management\Entity\AppCredentialInterface;
use Apigee\Edge\Exception\ApiException;
use Drupal\apigee_edge\Element\StatusPropertyElement;
use Drupal\apigee_edge\Entity\ApiProductInterface;
use Drupal\apigee_edge\Entity\AppInterface;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Utility\Error;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base entity form for developer- and team (company) app edit forms.
 */
abstract class AppEditForm extends AppForm {

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $render;

  /**
   * AppEditForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Render\RendererInterface $render
   *   The renderer service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RendererInterface $render) {
    parent::__construct($entity_type_manager);
    $this->render = $render;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $form['#cache']['contexts'][] = 'user.permissions';

    /** @var \Drupal\apigee_edge\Entity\AppInterface $app */
    $app = $this->entity;

    $app_settings = $this->config('apigee_edge.common_app_settings');
    $is_multiple_selection = $app_settings->get('multiple_products');
    $api_product_def = $this->entityTypeManager->getDefinition('api_product');

    // Do not allow to change the (machine) name of the app.
    $form['name'] = [
      '#type' => 'value',
      '#value' => $app->getName(),
    ];

    // Do not allow to change the owner of the app.
    $form['owner']['#access'] = FALSE;

    // If app's display name is empty then fallback to app name as default
    // value just like Apigee Edge Management UI does.
    if ($form['displayName']['widget'][0]['value']['#default_value'] === NULL) {
      $form['displayName']['widget'][0]['value']['#default_value'] = $app->getName();
    }

    // If app's callback URL field is visible on the form then set its value
    // to the callback url property's value always, because it could happen that
    // its value is empty if the saved value is not a valid URL.
    // (Apigee Edge Management API does not validate the value of the
    // callback URL, but Drupal does.)
    if (isset($form['callbackUrl'])) {
      $form['callbackUrl']['widget'][0]['value']['#default_value'] = $app->getCallbackUrl();
    }

    // If "Let user select the product(s)" is enabled.
    if ($app_settings->get('user_select')) {
      $available_products_by_user = $this->apiProductList($form, $form_state);

      $form['credential'] = [
        '#type' => 'container',
        '#weight' => 100,
      ];

      foreach ($app->getCredentials() as $credential) {
        $credential_status_element = [
          '#type' => 'status_property',
          '#value' => Xss::filter($credential->getStatus()),
          '#indicator_status' => $credential->getStatus() === AppCredentialInterface::STATUS_APPROVED ? StatusPropertyElement::INDICATOR_STATUS_OK : StatusPropertyElement::INDICATOR_STATUS_ERROR,
        ];
        $rendered_credential_status = $this->render->render($credential_status_element);

        $form['credential'][$credential->getConsumerKey()] = [
          '#type' => 'fieldset',
          '#title' => $rendered_credential_status . $this->t('Credential'),
          '#collapsible' => FALSE,
        ];

        // List of API product (ids/names) that the credential currently
        // contains.
        $credential_currently_assigned_product_ids = [];
        foreach ($credential->getApiProducts() as $product) {
          $credential_currently_assigned_product_ids[] = $product->getApiproduct();
        }
        // $available_products_by_user ensures that only those API products
        // are visible in this list that the user can access.
        // But we have to add this app credential's currently assigned API
        // products to the list as well.
        $credential_product_options = array_map(function (ApiProductInterface $product) {
          return $product->label();
        }, $available_products_by_user + $this->entityTypeManager->getStorage('api_product')->loadMultiple($credential_currently_assigned_product_ids));

        $form['credential'][$credential->getConsumerKey()]['api_products'] = [
          '#title' => $api_product_def->getPluralLabel(),
          '#required' => TRUE,
          '#options' => $credential_product_options,
          '#disabled' => !$this->canEditApiProducts(),
        ];

        if ($is_multiple_selection) {
          $form['credential'][$credential->getConsumerKey()]['api_products']['#default_value'] = $credential_currently_assigned_product_ids;
        }
        else {
          if (count($credential_currently_assigned_product_ids) > 1) {
            $this->messenger()->addWarning($this->t('@apps now require selection of a single @api_product; multiple @api_product selection is no longer supported. Confirm your @api_product selection below.', [
              '@apps' => $this->appEntityDefinition()->getPluralLabel(),
              '@api_product' => $api_product_def->getSingularLabel(),
            ]));
          }
          $form['credential'][$credential->getConsumerKey()]['api_products']['#default_value'] = reset($credential_currently_assigned_product_ids) ?: NULL;
        }

        if ($app_settings->get('display_as_select')) {
          $form['credential'][$credential->getConsumerKey()]['api_products']['#type'] = 'select';
          $form['credential'][$credential->getConsumerKey()]['api_products']['#multiple'] = $is_multiple_selection;
          $form['credential'][$credential->getConsumerKey()]['api_products']['#empty_value'] = '';
        }
        else {
          if ($is_multiple_selection) {
            $form['credential'][$credential->getConsumerKey()]['api_products']['#type'] = 'checkboxes';
            $form['credential'][$credential->getConsumerKey()]['api_products']['#options'] = $credential_product_options;
          }
          else {
            $form['credential'][$credential->getConsumerKey()]['api_products']['#type'] = 'radios';
            $form['credential'][$credential->getConsumerKey()]['api_products']['#options'] = $credential_product_options;
          }
        }
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function saveAppCredentials(AppInterface $app, FormStateInterface $form_state): ?bool {
    // We do not support creation of multiple app credentials on the add app
    // form at this moment, but it could happen that a user edits an app
    // that has multiple credentials (probably created outside of Drupal).
    // This is the reason why we have to collect and summarize the result
    // of the app credential changes.
    $results = [];

    $config = $this->config('apigee_edge.common_app_settings');

    // If a user can change associated API products on a credential.
    if ($config->get('user_select')) {
      $app_credential_controller = $this->appCredentialController($app->getAppOwner(), $app->getName());

      // $app->getCredentials() always returns the already saved
      // credentials on Apigee Edge.
      // @see \Drupal\apigee_edge\Entity\DeveloperApp::getCredentials()
      foreach ($form_state->getValue('credential', []) as $credential_key => $credential_changes) {
        foreach ($app->getCredentials() as $credential) {
          if ($credential_key === $credential->getConsumerKey()) {
            $original_api_product_ids = [];
            // Cast it to array to be able handle the same way the single- and
            // multi-select configuration.
            $new_api_product_ids = array_filter((array) $credential_changes['api_products']);
            foreach ($credential->getApiProducts() as $original_api_product) {
              $original_api_product_ids[] = $original_api_product->getApiproduct();
            }

            try {
              $product_list_changed = FALSE;
              // Remove API products from the credential.
              if (array_diff($original_api_product_ids, $new_api_product_ids)) {
                foreach (array_diff($original_api_product_ids, $new_api_product_ids) as $api_product_to_remove) {
                  $app_credential_controller->deleteApiProduct($credential_key, $api_product_to_remove);
                }
                $product_list_changed = TRUE;
              }
              // Add new API products to the credential.
              if (array_diff($new_api_product_ids, $original_api_product_ids)) {
                $app_credential_controller->addProducts($credential_key, array_values(array_diff($new_api_product_ids, $original_api_product_ids)));
                $product_list_changed = TRUE;
              }

              // Do not add anything to the results if there were no change.
              if ($product_list_changed) {
                $results[] = TRUE;
              }
              break;

            }
            catch (ApiException $exception) {
              $results[] = FALSE;
              $context = [
                '%app_name' => $app->label(),
                '%owner' => $app->getAppOwner(),
                'link' => $app->toLink()->toString(),
              ];
              $context += Error::decodeException($exception);
              $this->logger('apigee_edge')->error('Unable to update app credentials on app. App name: %app_name. Owner: %owner. @message %function (line %line of %file). <pre>@backtrace_string</pre>', $context);
            }
          }
        }
      }
    }

    return empty($results) || !in_array(FALSE, $results);
  }

  /**
   * Access check for editing API products.
   *
   * @return bool
   *   TRUE if current user can edit API products. FALSE otherwise.
   */
  protected function canEditApiProducts(): bool {
    return $this->currentUser()->hasPermission('bypass api product access control')
      || $this->currentUser()->hasPermission("edit_api_products {$this->entity->getEntityTypeId()}");
  }

}
