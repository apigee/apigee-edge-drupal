<?php

namespace Drupal\apigee_edge\Controller;

use \Apigee\Edge\Entity\EntityInterface;
use Drupal\apigee_edge\Entity\DeveloperAppInterface;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Trait for developer app details controllers.
 */
trait DeveloperAppDetailsControllerTrait {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $configFactory, DateFormatterInterface $date_formatter) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $configFactory;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * Gets the details render array for a given developer app.
   *
   * @param \Drupal\apigee_edge\Entity\DeveloperAppInterface $developer_app
   *   The developer app entity.
   *
   * @return array
   *   The render array.
   */
  protected function getRenderArray(DeveloperAppInterface $developer_app): array {
    $config = $this->configFactory->get('apigee_edge.appsettings');
    $build = [];
    
    $build['details'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Details'),
      '#collapsible' => FALSE,
      '#attributes' => [
        'class' => [
          'items--inline',
          'apigee-edge-developer-app-details',
        ],
      ],
    ];

    $build['#attached']['library'][] = 'apigee_edge/apigee_edge.components';
    $build['#attached']['library'][] = 'apigee_edge/apigee_edge.details';

    $details_primary_elements = [
      'displayName' => [
        'label' => $this->t('@devAppLabel name', ['@devAppLabel' => $this->entityTypeManager->getDefinition('developer_app')->getSingularLabel()]),
        'value_type' => 'plain',
      ],
      'callbackUrl' => [
        'label' => t('Callback URL'),
        'value_type' => 'plain',
      ],
      'description' => [
        'label' => t('Description'),
        'value_type' => 'plain',
      ],
    ];

    $details_secondary_elements = [
      'status' => [
        'label' => $this->t('@devAppLabel status', ['@devAppLabel' => $this->entityTypeManager->getDefinition('developer_app')->getSingularLabel()]),
        'value_type' => 'status',
      ],
      'createdAt' => [
        'label' => t('Created'),
        'value_type' => 'date',
      ],
      'lastModifiedAt' => [
        'label' => t('Last updated'),
        'value_type' => 'date',
      ],
    ];

    $build['details']['primary_wrapper'] = $this->getContainerRenderArray($developer_app, $details_primary_elements);
    $build['details']['primary_wrapper']['#type'] = 'container';
    $build['details']['primary_wrapper']['#attributes']['class'] = ['wrapper--primary'];
    $build['details']['secondary_wrapper'][] = $this->getContainerRenderArray($developer_app, $details_secondary_elements);
    $build['details']['secondary_wrapper']['#type'] = 'container';
    $build['details']['secondary_wrapper']['#attributes']['class'] = ['wrapper--secondary'];

    if ($config->get('associate_apps')) {
      $credential_elements = [
        'consumerKey' => [
          'label' => t('Consumer Key'),
          'value_type' => 'plain',
        ],
        'consumerSecret' => [
          'label' => t('Consumer Secret'),
          'value_type' => 'plain',
        ],
        'issuedAt' => [
          'label' => t('Issued'),
          'value_type' => 'date',
        ],
        'expiresAt' => [
          'label' => t('Expires'),
          'value_type' => 'date',
        ],
        'status' => [
          'label' => t('Key Status'),
          'value_type' => 'status',
        ],
      ];
      for ($credential_index = 0; $credential_index < count($developer_app->getCredentials()); $credential_index++) {
        $credential = $developer_app->getCredentials()[$credential_index];
        $build['credential'][$credential_index] = [
          '#type' => 'fieldset',
          '#title' => $this->t('Credential'),
          '#collapsible' => FALSE,
          '#attributes' => [
            'class' => [
              'items--inline',
              'apigee-edge-developer-app-details',
            ],
          ],
        ];

        $build['credential'][$credential_index]['primary_wrapper'] = $this->getContainerRenderArray($credential, $credential_elements);
        $build['credential'][$credential_index]['primary_wrapper']['#type'] = 'container';
        $build['credential'][$credential_index]['primary_wrapper']['#attributes']['class'] = ['wrapper--primary'];

        $build['credential'][$credential_index]['secondary_wrapper']['#type'] = 'container';
        $build['credential'][$credential_index]['secondary_wrapper']['#attributes']['class'] = ['wrapper--secondary'];
        $build['credential'][$credential_index]['secondary_wrapper']['title'] = [
          '#type' => 'label',
          '#title' => $this->entityTypeManager->getDefinition('api_product')->getPluralLabel(),
        ];

        for ($product_index = 0; $product_index < count($credential->getApiProducts()); $product_index++) {
          $build['credential'][$credential_index]['secondary_wrapper']['api_product_list_wrapper'][$product_index] = [
            '#type' => 'container',
            '#attributes' => [
              'class' => [
                'api-product-list-row',
              ],
            ],
          ];
          $build['credential'][$credential_index]['secondary_wrapper']['api_product_list_wrapper'][$product_index]['name'] = [
            '#prefix' => '<span class="api-product-name">',
            '#markup' => Xss::filter($credential->getApiProducts()[$product_index]->getApiproduct()),
            '#suffix' => '</span>',
          ];
          $build['credential'][$credential_index]['secondary_wrapper']['api_product_list_wrapper'][$product_index]['status'] = [
            '#type' => 'status_property',
            '#value' => Xss::filter($credential->getApiProducts()[$product_index]->getStatus()),
          ];
        }
      }
    }

    return $build;
  }

  /**
   * Returns the render array of a container for a given entity.
   *
   * @param \Apigee\Edge\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return array
   *   The render array.
   */
  protected function getContainerRenderArray(EntityInterface $entity, array $elements): array {
    $build = [];
    $ro = new \ReflectionObject($entity);
    foreach ($elements as $element => $settings) {
      $getter = 'get' . ucfirst($element);
      if (!$ro->hasMethod($getter)) {
        $getter = 'is' . ucfirst($element);
      }
      if ($ro->hasMethod($getter)) {
        $build[$element]['wrapper'] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => [
              'item-property',
            ],
          ],
        ];
        $build[$element]['wrapper']['label'] = [
          '#type' => 'label',
          '#title' => $settings['label'],
        ];

        if ($settings['value_type'] === 'plain') {
          $build[$element]['wrapper']['value'] = [
            '#prefix' => '<span>',
            '#markup' => Xss::filter(call_user_func([$entity, $getter])),
            '#suffix' => '</span>',
          ];
        }
        elseif ($settings['value_type'] === 'date') {
          $value = call_user_func([$entity, $getter]) !== '-1' ? $this->dateFormatter->format(call_user_func([$entity, $getter]) / 1000, 'custom', 'D, m/d/Y - H:i', drupal_get_user_timezone()) : 'Never';
          $build[$element]['wrapper']['value'] = [
            '#markup' => Xss::filter($value),
          ];
        }
        elseif ($settings['value_type'] === 'status') {
          $build[$element]['wrapper']['value'] = [
            '#type' => 'status_property',
            '#value' => Xss::filter(call_user_func([$entity, $getter])),
          ];
        }
      }
    }
    return $build;
  }

  /**
   * Builds a translatable page title by using values from args as replacements.
   *
   * @param array $args
   *   An associative array of replacements.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The page title.
   *
   * @see \Drupal\Core\StringTranslation\StringTranslationTrait::t()
   */
  protected function pageTitle(array $args = []): TranslatableMarkup {
    return $this->t('@name @devAppLabel', $args);
  }

}
