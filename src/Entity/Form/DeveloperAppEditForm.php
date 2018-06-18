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

namespace Drupal\apigee_edge\Entity\Form;

use Drupal\apigee_edge\Entity\ApiProductInterface;
use Drupal\apigee_edge\Entity\Controller\DeveloperAppCredentialController;
use Drupal\apigee_edge\Entity\DeveloperStatusCheckTrait;
use Drupal\apigee_edge\SDKConnectorInterface;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * General form handler for the developer app edit forms.
 */
class DeveloperAppEditForm extends DeveloperAppCreateForm {

  use DeveloperStatusCheckTrait;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The developer app entity.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperAppInterface
   */
  protected $entity;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs DeveloperAppEditForm.
   *
   * @param \Drupal\apigee_edge\SDKConnectorInterface $sdk_connector
   *   The SDK Connector service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   A config factory for retrieving required config objects.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(SDKConnectorInterface $sdk_connector, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, RendererInterface $renderer, MessengerInterface $messenger) {
    parent::__construct($sdk_connector, $config_factory, $entity_type_manager);
    $this->renderer = $renderer;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('apigee_edge.sdk_connector'),
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('renderer'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $this->checkDeveloperStatus($this->entity->getOwnerId());
    $config = $this->configFactory->get('apigee_edge.common_app_settings');
    $multiple = $config->get('multiple_products');

    // Do not allow to change the (machine) name of the app.
    $form['name'] = [
      '#type' => 'value',
      '#value' => $this->entity->getName(),
    ];
    $form['#tree'] = TRUE;
    $form['developerId']['#access'] = FALSE;
    $form['product']['#access'] = !isset($form['product']) ?: FALSE;

    if ($config->get('user_select')) {
      $form['credential'] = [
        '#type' => 'container',
        '#weight' => 100,
      ];

      foreach ($this->entity->getCredentials() as $credential) {
        $credential_status_element = [
          '#type' => 'status_property',
          '#value' => Xss::filter($credential->getStatus()),
        ];
        $rendered_credential_status = $this->renderer->render($credential_status_element);

        $form['credential'][$credential->getConsumerKey()] = [
          '#type' => 'fieldset',
          '#title' => $rendered_credential_status . $this->t('Credential'),
          '#collapsible' => FALSE,
        ];

        $current_product_ids = [];
        foreach ($credential->getApiProducts() as $product) {
          $current_product_ids[] = $product->getApiproduct();
        }
        // Parent form has already ensured that only those API products
        // are visible in this list in which the (current) user has access.
        $product_list = $form['product']['api_products']['#options'];
        // But we have to add this app's currently assigned API products to the
        // list as well.
        $product_list += array_map(function (ApiProductInterface $product) {
          return $product->getDisplayName();
        }, $this->entityTypeManager->getStorage('api_product')->loadMultiple($current_product_ids));

        $form['credential'][$credential->getConsumerKey()]['api_products'] = [
          '#title' => $this->entityTypeManager->getDefinition('api_product')->getPluralLabel(),
          '#required' => TRUE,
          '#options' => $product_list,
        ];

        if ($multiple) {
          $form['credential'][$credential->getConsumerKey()]['api_products']['#default_value'] = $current_product_ids;
        }
        else {
          if (count($current_product_ids) > 1) {
            $dev_app_def = $this->entityTypeManager->getDefinition('developer_app');
            $api_product_def = $this->entityTypeManager->getDefinition('api_product');
            $this->messenger->addWarning($this->t('@developer_apps status now require selection of a single @api_product; multiple @api_product selection is no longer supported. Confirm your @api_product selection below.', [
              '@developer_apps' => $dev_app_def->getPluralLabel(),
              '@api_product' => $api_product_def->getSingularLabel(),
            ]));
          }
          $form['credential'][$credential->getConsumerKey()]['api_products']['#default_value'] = reset($current_product_ids) ?: NULL;
        }

        if ($config->get('display_as_select')) {
          $form['credential'][$credential->getConsumerKey()]['api_products']['#type'] = 'select';
          $form['credential'][$credential->getConsumerKey()]['api_products']['#multiple'] = $multiple;
          $form['credential'][$credential->getConsumerKey()]['api_products']['#empty_value'] = '';
        }
        else {
          if ($multiple) {
            $form['credential'][$credential->getConsumerKey()]['api_products']['#type'] = 'checkboxes';
            $form['credential'][$credential->getConsumerKey()]['api_products']['#options'] = $product_list;
          }
          else {
            $form['credential'][$credential->getConsumerKey()]['api_products']['#type'] = 'radios';
            $form['credential'][$credential->getConsumerKey()]['api_products']['#options'] = $product_list;
          }
        }
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Save');
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->get('apigee_edge.common_app_settings');
    $redirect_user = FALSE;

    if ($config->get('user_select')) {
      $dacc = new DeveloperAppCredentialController(
        $this->sdkConnector->getOrganization(),
        $this->entity->getDeveloperId(),
        $this->entity->getName(),
        $this->sdkConnector->getClient()
      );

      // $this->entity->getCredentials() always returns the already stored
      // credentials on Apigee Edge.
      // @see \Drupal\apigee_edge\Entity\DeveloperApp::getCredentials()
      foreach ($form_state->getValue('credential', []) as $new_credential => $new_credentail_data) {
        foreach ($this->entity->getCredentials() as $original_credential) {
          if ($new_credential === $original_credential->getConsumerKey()) {
            try {
              $original_api_product_names = [];
              // Cast it to array to be able handle the same way the single- and
              // multi-select configuration.
              $new_api_product_names = array_filter((array) $new_credentail_data['api_products']);
              foreach ($original_credential->getApiProducts() as $original_api_product) {
                $original_api_product_names[] = $original_api_product->getApiproduct();
              }

              $product_list_changed = FALSE;
              if (array_diff($original_api_product_names, $new_api_product_names)) {
                foreach (array_diff($original_api_product_names, $new_api_product_names) as $api_product_to_remove) {
                  $dacc->deleteApiProduct($new_credential, $api_product_to_remove);
                }
                $product_list_changed = TRUE;
                $redirect_user = TRUE;
              }
              if (array_diff($new_api_product_names, $original_api_product_names)) {
                $dacc->addProducts($new_credential, array_values(array_diff($new_api_product_names, $original_api_product_names)));
                $product_list_changed = TRUE;
                $redirect_user = TRUE;
              }

              if ($product_list_changed) {
                $this->messenger->addStatus($this->t("Credential's product list has been successfully updated."));
              }
              break;

            }
            catch (\Exception $exception) {
              $this->messenger->addError(t("Could not update credential's product list.",
                ['@consumer_key' => $new_credential]));
              watchdog_exception('apigee_edge', $exception);
              $redirect_user = FALSE;
            }
          }
        }
      }
    }

    try {
      $this->entity->save();
      $this->messenger->addStatus($this->t('@developer_app details have been successfully updated.',
        ['@developer_app' => $this->entityTypeManager->getDefinition('developer_app')->getSingularLabel()]));
      $redirect_user = TRUE;
    }
    catch (\Exception $exception) {
      $this->messenger->addError($this->t('Could not update @developer_app details.',
        ['@developer_app' => $this->entityTypeManager->getDefinition('developer_app')->getLowercaseLabel()]));
      watchdog_exception('apigee_edge', $exception);
    }

    if ($redirect_user) {
      $form_state->setRedirectUrl($this->getRedirectUrl());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityFromRouteMatch(RouteMatchInterface $route_match, $entity_type_id) {
    if ($route_match->getRawParameter('app') !== NULL) {
      $entity = $route_match->getParameter('app');
    }
    else {
      $entity = parent::getEntityFromRouteMatch($route_match, $entity_type_id);
    }
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getPageTitle(RouteMatchInterface $routeMatch): string {
    return $this->t('Edit @developer_app', ['@developer_app' => $this->entityTypeManager->getDefinition('developer_app')->getLowercaseLabel()]);
  }

}
