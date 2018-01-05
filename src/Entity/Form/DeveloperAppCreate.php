<?php

namespace Drupal\apigee_edge\Entity\Form;

use Apigee\Edge\Api\Management\Controller\DeveloperAppCredentialController;
use Drupal\apigee_edge\Entity\ApiProduct;
use Drupal\apigee_edge\Entity\Developer;
use Drupal\apigee_edge\Entity\DeveloperApp;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

class DeveloperAppCreate extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $config = \Drupal::config('apigee_edge.appsettings');

    /** @var \Drupal\apigee_edge\Entity\DeveloperApp $app */
    $app = $this->entity;

    $form['details'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Details'),
      '#collapsible' => FALSE,
    ];

    $form['details']['displayName'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Application Name'),
      '#required' => TRUE,
      '#default_value' => $app->getDisplayName(),
    ];

    $form['details']['name'] = [
      '#type' => 'machine_name',
      '#machine_name' => [
        'source' => ['details', 'displayName'],
        'label' => $this->t('Internal name'),
        'exists' => [self::class, 'appExists'],
      ],
      '#title' => $this->t('Internal name'),
      '#disabled' => !$app->isNew(),
      '#default_value' => $app->getName(),
    ];

    if (($developerId = $this->getRouteMatch()->getParameter('developer'))) {
      $form['details']['developerId'] = [
        '#type' => 'value',
        '#value' => $developerId,
      ];
    }
    else {
      $developers = [];
      /** @var Developer $developer */
      foreach (Developer::loadMultiple() as $developer) {
        $developers[$developer->uuid()] = $developer->getUserName();
      }

      $form['details']['developerId'] = [
        '#title' => $this->t('Owner'),
        '#type' => 'select',
        '#default_value' => $app->getDeveloperId(),
        '#options' => $developers,
      ];
    }

    $form['details']['callbackUrl'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Callback URL'),
      '#default_value' => $app->getCallbackUrl(),
      '#access' => (bool) $config->get('callback_url_visible'),
      '#required' => (bool) $config->get('callback_url_required'),
    ];

    $form['details']['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $app->getDescription(),
      '#access' => (bool) $config->get('description_visible'),
      '#required' => (bool) $config->get('description_required'),
    ];

    if ($config->get('associate_apps')) {
      $required = $config->get('require');
      $form['product'] = [
        '#type' => 'fieldset',
        '#title' => \Drupal::entityTypeManager()
          ->getDefinition('api_product')
          ->get('label_singular'),
        '#collapsible' => FALSE,
        '#access' => $config->get('user_select'),
        '#attributes' => [
          'class' => $required ? ['form-required'] : [],
        ],
      ];

      /** @var ApiProduct[] $products */
      $products = ApiProduct::loadMultiple();
      $product_list = [];
      foreach ($products as $product) {
        $product_list[$product->id()] = $product->getDisplayName();
      }

      $multiple = $config->get('multiple_products');
      $default_products = $config->get('default_products') ?: [];

      $form['product']['api_products'] = [
        '#title' => $this->t('API Products'),
        '#title_display' => 'invisible',
        '#required' => $required,
        '#options' => $product_list,
        '#default_value' => $multiple ? $default_products : reset($default_products),
      ];

      if ($config->get('display_as_select')) {
        $form['product']['api_products']['#type'] = 'select';
        $form['product']['api_products']['#multiple'] = $multiple;
      }
      else {
        $form['product']['api_products']['#type'] = $multiple ? 'checkboxes' : 'radios';
      }
    }

    return parent::form($form, $form_state);
  }

  /**
   * Checks if an app machine name already exists.
   *
   * @param string $name
   *
   * @return bool
   */
  public static function appExists(string $name) : bool {
    $query = \Drupal::entityQuery('developer_app');
    $query->condition('name', $name);

    return $query->count()->execute();
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = apigee_edge_create_app_title();

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    /** @var \Drupal\apigee_edge\Entity\DeveloperApp $entity */
    $entity->setName($form_state->getValue('name'));
    $entity->setDisplayName($form_state->getValue('displayName'));
    $entity->setDeveloperId($form_state->getValue('developerId'));
    $entity->setCallbackUrl($form_state->getValue('callbackUrl'));
    $entity->setDescription($form_state->getValue('description'));
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\apigee_edge\Entity\DeveloperApp $app */
    $app = $this->entity;
    $app->save();
    $config = \Drupal::config('apigee_edge.appsettings');

    if ($config->get('associate_apps')) {
      /** @var \Drupal\apigee_edge\SDKConnectorInterface $connector */
      $connector = \Drupal::service('apigee_edge.sdk_connector');
      $dacc = new DeveloperAppCredentialController(
        $connector->getOrganization(),
        $app->getDeveloperId(),
        $app->getName(),
        $connector->getClient()
      );

      /** @var \Apigee\Edge\Api\Management\Entity\AppCredential[] $credentials */
      $credentials = $app->getCredentials();
      /** @var \Apigee\Edge\Api\Management\Entity\AppCredential $credential */
      $credential = reset($credentials);

      $products = array_values(array_filter((array) $form_state->getValue('api_products')));
      if ($products) {
        $dacc->addProducts($credential->id(), $products);
      }
    }
  }

}
