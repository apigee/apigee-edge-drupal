<?php

namespace Drupal\apigee_edge\Entity\Form;

use Apigee\Edge\Api\Management\Controller\DeveloperAppCredentialController;
use Drupal\apigee_edge\Entity\ApiProduct;
use Drupal\apigee_edge\Entity\Developer;
use Drupal\apigee_edge\SDKConnectorInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * General form handler for the developer app create forms.
 */
class DeveloperAppCreateForm extends EntityForm {

  /** @var \Drupal\apigee_edge\SDKConnectorInterface */
  protected $sdkConnector;

  /**
   * DeveloperAppCreate constructor.
   *
   * @param \Drupal\apigee_edge\SDKConnectorInterface $sdkConnector
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   */
  public function __construct(SDKConnectorInterface $sdkConnector, ConfigFactory $configFactory, EntityTypeManagerInterface $entityTypeManager) {
    $this->sdkConnector = $sdkConnector;
    $this->configFactory = $configFactory;
    $this->entityTypeManager = $entityTypeManager;
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
    $config = $this->configFactory->get('apigee_edge.appsettings');

    $form['#attached']['library'][] = 'apigee_edge/apigee_edge.components';

    /** @var \Drupal\apigee_edge\Entity\DeveloperApp $app */
    $app = $this->entity;

    $form['#attributes']['class'][] = 'apigee-edge--form';

    $form['details'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Details'),
      '#collapsible' => FALSE,
      '#attributes' => [
        'class' => [
          'items--inline',
        ],
      ],
    ];

    $form['details']['displayName'] = [
      '#type' => 'textfield',
      '#title' => $this->t('@appLabel Name', ['@appLabel' => $this->entityTypeManager->getDefinition('developer_app')->getSingularLabel()]),
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

    $developers = [];
    /** @var \Drupal\apigee_edge\Entity\Developer $developer */
    foreach (Developer::loadMultiple() as $developer) {
      $developers[$developer->uuid()] = "{$developer->getFirstName()} {$developer->getLastName()}";
    }

    $form['details']['developerId'] = [
      '#title' => $this->t('Owner'),
      '#type' => 'select',
      '#default_value' => $app->getDeveloperId(),
      '#options' => $developers,
    ];

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
      '#resizable' => 'none',
    ];

    if ($config->get('associate_apps')) {
      $required = $config->get('require');
      $form['product'] = [
        '#type' => 'fieldset',
        '#title' => $this->entityTypeManager->getDefinition('api_product')->getSingularLabel(),
        '#collapsible' => FALSE,
        '#access' => $config->get('user_select'),
        '#attributes' => [
          'class' => $required ? ['form-required'] : [],
        ],
      ];

      /** @var \Drupal\apigee_edge\Entity\ApiProduct[] $products */
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
    $config = $this->configFactory->get('apigee_edge.appsettings');

    if ($config->get('associate_apps')) {
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

      $products = array_values(array_filter((array) $form_state->getValue('api_products')));
      if ($products) {
        $dacc->addProducts($credential->id(), $products);
      }
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
      return $entity->urlInfo('collection');
    }
    else {
      // Otherwise fall back to the front page.
      return Url::fromRoute('<front>');
    }
  }

}
