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
use Drupal\apigee_edge\Entity\ApiProduct;
use Drupal\apigee_edge\Entity\Developer;
use Drupal\apigee_edge\Entity\DeveloperAppPageTitleInterface;
use Drupal\apigee_edge\SDKConnectorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * General form handler for the developer app create forms.
 */
class DeveloperAppCreateForm extends FieldableEdgeEntityForm implements DeveloperAppPageTitleInterface {

  /**
   * The SDK Connector service.
   *
   * @var \Drupal\apigee_edge\SDKConnectorInterface
   */
  protected $sdkConnector;

  /**
   * Constructs DeveloperAppCreateForm.
   *
   * @param \Drupal\apigee_edge\SDKConnectorInterface $sdk_connector
   *   The SDK Connector service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   A config factory for retrieving required config objects.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(SDKConnectorInterface $sdk_connector, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager) {
    $this->sdkConnector = $sdk_connector;
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('apigee_edge.sdk_connector'),
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $config = $this->configFactory->get('apigee_edge.common_app_settings');
    $form['#attached']['library'][] = 'apigee_edge/apigee_edge.components';
    $form['#attributes']['class'][] = 'apigee-edge--form';

    /** @var \Drupal\apigee_edge\Entity\DeveloperApp $app */
    $app = $this->entity;

    $developers = [];
    // This is little bit hackish, but do not load all developer data on the
    // add/edit app form for developer forms and with that increase the speed
    // of these pages.
    if (!preg_match('/_for_developer$/', $this->getRouteMatch()->getRouteName())) {
      /** @var \Drupal\apigee_edge\Entity\Developer $developer */
      foreach (Developer::loadMultiple() as $developer) {
        $developers[$developer->uuid()] = "{$developer->getFirstName()} {$developer->getLastName()}";
      }
    }

    $form['developerId'] = [
      '#title' => $this->t('Owner'),
      '#type' => 'select',
      '#weight' => -100,
      '#default_value' => $app->getDeveloperId(),
      '#options' => $developers,
    ];

    $form['name'] = [
      '#type' => 'machine_name',
      '#machine_name' => [
        'source' => ['displayName', 'widget', 0, 'value'],
        'label' => $this->t('Internal name'),
        'exists' => [self::class, 'appExists'],
      ],
      '#title' => $this->t('Internal name'),
      '#disabled' => !$app->isNew(),
      '#default_value' => $app->getName(),
    ];

    $user_select = (bool) $config->get('user_select');
    $form['product'] = [
      '#type' => 'details',
      '#title' => $this->entityTypeManager->getDefinition('api_product')->getSingularLabel(),
      '#open' => TRUE,
      '#access' => $user_select,
      '#weight' => 100,
      '#attributes' => [
        'class' => ['form-required'],
      ],
    ];

    // We can use null, because in Entity::access() null falls back to the
    // currently logged in user.
    $currentUser = NULL;
    // Get the current user object from the route if available.
    // (It could happen that a user with bypass permission edits an other
    // user's app.)
    if ($this->routeMatch->getParameter('user') !== NULL) {
      $currentUser = $this->entityTypeManager->getStorage('user')->load($this->routeMatch->getParameter('user'));
    }
    /** @var \Drupal\apigee_edge\Entity\ApiProductInterface[] $availableProductsForUser */
    $availableProductsForUser = array_filter(ApiProduct::loadMultiple(), function (ApiProductInterface $product) use ($currentUser) {
      return $product->access('assign', $currentUser);
    });
    $product_list = [];
    foreach ($availableProductsForUser as $product) {
      $product_list[$product->id()] = $product->getDisplayName();
    }

    $multiple = $config->get('multiple_products');
    $default_products = $config->get('default_products') ?: [];

    $form['product']['api_products'] = [
      '#title' => $this->t('API Products'),
      '#title_display' => 'invisible',
      '#required' => TRUE,
      '#options' => $product_list,
      '#access' => $user_select,
      '#default_value' => $multiple ? $default_products : (string) reset($default_products),
    ];

    if ($config->get('display_as_select')) {
      $form['product']['api_products']['#type'] = 'select';
      $form['product']['api_products']['#multiple'] = $multiple;
      $form['product']['api_products']['#empty_value'] = '';
    }
    else {
      $form['product']['api_products']['#type'] = $multiple ? 'checkboxes' : 'radios';
      if (!$multiple) {
        $form['product']['api_products']['#options'] = ['' => $this->t('N/A')] + $form['product']['api_products']['#options'];
      }
    }

    return $form;
  }

  /**
   * Checks if the developer already has a developer app with the same name.
   *
   * @param string $name
   *   Developer app name.
   * @param array $element
   *   Form element.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   Form state.
   *
   * @return bool
   *   TRUE or FALSE.
   */
  public static function appExists(string $name, array $element, FormStateInterface $formState): bool {
    $query = \Drupal::entityQuery('developer_app')
      ->condition('developerId', $formState->getValue('developerId'))
      ->condition('name', $name);

    return $query->count()->execute();
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Add @developer_app', [
      '@developer_app' => $this->entityTypeManager->getDefinition('developer_app')->getLowercaseLabel(),
    ]);

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\apigee_edge\Entity\DeveloperApp $app */
    $app = $this->entity;
    $app->save();

    $dacc = new DeveloperAppCredentialController(
      $this->sdkConnector->getOrganization(),
      $app->getDeveloperId(),
      $app->getName(),
      $this->sdkConnector->getClient()
    );

    /** @var \Apigee\Edge\Api\Management\Entity\AppCredential[] $credentials */
    $credentials = $app->getCredentials();
    /** @var \Apigee\Edge\Api\Management\Entity\AppCredential $credential */
    $credential = reset($credentials);

    $credential_lifetime = $this->config('apigee_edge.developer_app_settings')->get('credential_lifetime');
    $products = array_values(array_filter((array) $form_state->getValue('api_products')));

    if ($credential_lifetime === 0) {
      $dacc->addProducts($credential->id(), $products);
    }
    else {
      $dacc->delete($credential->id());
      // The value of -1 indicates no set expiry. But the value of 0 is not
      // acceptable by the server (InvalidValueForExpiresIn).
      $dacc->generate($products, $app->getAttributes(), $app->getCallbackUrl(), [], $credential_lifetime * 86400000);
    }

    $form_state->setRedirectUrl($this->getRedirectUrl());
  }

  /**
   * Returns the URL where the user should be redirected after form submission.
   *
   * @return \Drupal\Core\Url
   *   The redirect URL.
   */
  protected function getRedirectUrl() {
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
   * {@inheritdoc}
   */
  public function getPageTitle(RouteMatchInterface $routeMatch): string {
    return $this->t('Add @developer_app', ['@developer_app' => $this->entityTypeManager->getDefinition('developer_app')->getLowercaseLabel()]);
  }

}
