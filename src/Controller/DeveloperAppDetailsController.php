<?php

namespace Drupal\apigee_edge\Controller;

use Apigee\Edge\Api\Management\Entity\AppCredentialInterface;
use Drupal\apigee_edge\Entity\DeveloperAppInterface;
use Drupal\apigee_edge\SDKConnectorInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays the details of a developer app on the UI.
 *
 * @package Drupal\apigee_edge\Controller
 */
class DeveloperAppDetailsController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The developer app storage instance.
   *
   * @var \Drupal\apigee_edge\Entity\Storage\DeveloperAppStorageInterface
   */
  protected $developerAppStorage;

  /**
   * The SDK connector instance.
   *
   * @var \Drupal\apigee_edge\SDKConnectorInterface
   */
  protected $sdkConnector;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * A config object for the UI references to Apps.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The date format.
   */
  protected const DATE_FORMAT = 'D, m/d/Y - H:i';

  /**
   * The user account.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * The Developer App instance.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperAppInterface
   */
  protected $developerApp;

  /**
   * The singular label of the Developer App entity.
   *
   * @var string
   */
  protected $appLabelSingular;

  /**
   * The plural label of the API Product entity.
   *
   * @var string
   */
  protected $apiProductLabelPlural;

  /**
   * Constructs a new DeveloperAppDetailsController.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $developerAppStorage
   *   The developer app storage.
   * @param \Drupal\apigee_edge\SDKConnectorInterface $sdkConnector
   *   The SDK Connector service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   */
  public function __construct(EntityStorageInterface $developerAppStorage, SDKConnectorInterface $sdkConnector, DateFormatterInterface $date_formatter, ConfigFactoryInterface $config_factory) {
    $this->developerAppStorage = $developerAppStorage;
    $this->sdkConnector = $sdkConnector;
    $this->dateFormatter = $date_formatter;
    $this->config = $config_factory->get('apigee_edge.entity_labels');

    $this->appLabelSingular = \Drupal::entityTypeManager()->getDefinition('developer_app')->get('label_singular');
    $this->apiProductLabelPlural = \Drupal::entityTypeManager()->getDefinition('api_product')->get('label_plural');

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager')->getStorage('developer_app'),
      $container->get('apigee_edge.sdk_connector'),
      $container->get('date.formatter'),
      $container->get('config.factory')
    );
  }

  /**
   * Returns rendered properties of the requested developer app.
   *
   * @param \Drupal\user\UserInterface $user
   *   The app developer's user account.
   * @param \Drupal\apigee_edge\Entity\DeveloperAppInterface $app
   *   The requested app object.
   *
   * @return array
   *   The render array contains the app details.
   */
  public function render(UserInterface $user, DeveloperAppInterface $app) {
    $this->user = $user;
    $this->developerApp = $app;

    $build[] = $this->renderDetailsContainer();

    for ($index = 0; $index < count($this->developerApp->getCredentials()); $index++) {
      $build[] = $this->renderCredentialContainer($this->developerApp->getCredentials()[$index], $index);
    }

    $build[] = $this->renderDeleteContainer();

    return $build;
  }

  /**
   * Renders the details container.
   *
   * @return array
   *   The render array of the details container.
   */
  private function renderDetailsContainer() {
    $created = $this->dateFormatter->format($this->developerApp->getCreatedAt() / 1000, 'custom', self::DATE_FORMAT, drupal_get_user_timezone());
    $last_updated = $this->dateFormatter->format($this->developerApp->getLastModifiedAt() / 1000, 'custom', self::DATE_FORMAT, drupal_get_user_timezone());

    $display_name = $this->developerApp->getDisplayName();
    $callback_url = $this->developerApp->getCallbackUrl();
    $description = $this->developerApp->getDescription();
    $status = $this->developerApp->getApp;

    $build['details_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => t('Details'),
    ];

    $build['details_fieldset']['details_primary_wrapper'] = [
      '#type' => 'container',
    ];

    $build['details_fieldset']['details_primary_wrapper']['display_name_wrapper'] = [
      '#type' => 'container',
    ];
    $build['details_fieldset']['details_primary_wrapper']['display_name_wrapper']['display_name_label'] = [
      '#type' => 'label',
      '#title' => t('Application Name'),
    ];
    $build['details_fieldset']['details_primary_wrapper']['display_name_wrapper']['display_name_value_wrapper'] = [
      '#type' => 'container',
    ];
    $build['details_fieldset']['details_primary_wrapper']['display_name_wrapper']['display_name_value_wrapper']['display_name_value'] = [
      '#markup' => Html::escape($display_name),
    ];

    $build['details_fieldset']['details_primary_wrapper']['callback_url_wrapper'] = [
      '#type' => 'container',
    ];
    $build['details_fieldset']['details_primary_wrapper']['callback_url_wrapper']['callback_url_label'] = [
      '#type' => 'label',
      '#title' => t('Callback URL'),
    ];
    $build['details_fieldset']['details_primary_wrapper']['callback_url_wrapper']['callback_url_value_wrapper'] = [
      '#type' => 'container',
    ];
    $build['details_fieldset']['details_primary_wrapper']['callback_url_wrapper']['callback_url_value_wrapper']['callback_url_value'] = [
      '#markup' => Html::escape($callback_url),
    ];

    $build['details_fieldset']['details_primary_wrapper']['description_wrapper'] = [
      '#type' => 'container',
    ];
    $build['details_fieldset']['details_primary_wrapper']['description_wrapper']['description_label'] = [
      '#type' => 'label',
      '#title' => t('Description'),
    ];
    $build['details_fieldset']['details_primary_wrapper']['description_wrapper']['description_value_wrapper'] = [
      '#type' => 'container',
    ];
    $build['details_fieldset']['details_primary_wrapper']['description_wrapper']['description_value_wrapper']['description_value'] = [
      '#markup' => Html::escape($description),
    ];

    $build['details_fieldset']['details_secondary_wrapper'] = [
      '#type' => 'container',
    ];

    $build['details_fieldset']['details_secondary_wrapper']['status_wrapper'] = [
      '#type' => 'container',
    ];
    $build['details_fieldset']['details_secondary_wrapper']['status_wrapper']['status_label'] = [
      '#type' => 'label',
      '#title' => t('App status'),
    ];
    $build['details_fieldset']['details_secondary_wrapper']['status_wrapper']['status_value_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'app-status',
          'app-status-' . Html::escape($status),
        ],
      ],
    ];
    $build['details_fieldset']['details_secondary_wrapper']['status_wrapper']['status_value_wrapper']['status_value'] = [
      '#markup' => Html::escape(ucfirst($status)),
    ];

    $build['details_fieldset']['details_secondary_wrapper']['created_wrapper'] = [
      '#type' => 'container',
    ];
    $build['details_fieldset']['details_secondary_wrapper']['created_wrapper']['created_label'] = [
      '#type' => 'label',
      '#title' => t('Created'),
    ];
    $build['details_fieldset']['details_secondary_wrapper']['created_wrapper']['created_value_wrapper'] = [
      '#type' => 'container',
    ];
    $build['details_fieldset']['details_secondary_wrapper']['created_wrapper']['created_value_wrapper']['created_value'] = [
      '#markup' => Html::escape($created),
    ];

    $build['details_fieldset']['details_secondary_wrapper']['last_updated_wrapper'] = [
      '#type' => 'container',
    ];
    $build['details_fieldset']['details_secondary_wrapper']['last_updated_wrapper']['last_updated_label'] = [
      '#type' => 'label',
      '#title' => t('Last updated'),
    ];
    $build['details_fieldset']['details_secondary_wrapper']['last_updated_wrapper']['last_updated_value_wrapper'] = [
      '#type' => 'container',
    ];
    $build['details_fieldset']['details_secondary_wrapper']['last_updated_wrapper']['last_updated_value_wrapper']['last_updated_value'] = [
      '#markup' => Html::escape($last_updated),
    ];

    return $build;

  }

  /**
   * Renders the credential container.
   *
   * @param \Apigee\Edge\Api\Management\Entity\AppCredentialInterface $credential
   *   The credential object.
   * @param int $index
   *   Index of the current credential.
   *
   * @return array
   *   Render array of the credential container.
   */
  private function renderCredentialContainer(AppCredentialInterface $credential, $index) {
    $consumer_key = $credential->getConsumerKey();
    $consumer_secret = $credential->getConsumerSecret();
    $issued = $this->dateFormatter->format($credential->getIssuedAt() / 1000, 'custom', self::DATE_FORMAT, drupal_get_user_timezone());
    $expires = $credential->getExpiresAt() === '-1' ? t('Never') : $this->dateFormatter->format($credential->getExpiresAt() / 1000, 'custom', self::DATE_FORMAT, drupal_get_user_timezone());
    $status = $credential->getStatus();

    $build['credential_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => 'Credential',
    ];

    $build['credential_fieldset']['credential_primary_wrapper'] = [
      '#type' => 'container',
    ];

    $build['credential_fieldset']['credential_primary_wrapper']['consumer_key_wrapper'] = [
      '#type' => 'container',
    ];
    $build['credential_fieldset']['credential_primary_wrapper']['consumer_key_wrapper']['consumer_key_label'] = [
      '#type' => 'label',
      '#title' => t('Consumer Key'),
    ];
    $build['credential_fieldset']['credential_primary_wrapper']['consumer_key_wrapper']['consumer_key_value_wrapper'] = [
      '#type' => 'container',
    ];
    $build['credential_fieldset']['credential_primary_wrapper']['consumer_key_wrapper']['consumer_key_value_wrapper']['consumer_key_value'] = [
      '#markup' => Html::escape($consumer_key),
    ];

    $build['credential_fieldset']['credential_primary_wrapper']['consumer_secret_wrapper'] = [
      '#type' => 'container',
    ];
    $build['credential_fieldset']['credential_primary_wrapper']['consumer_secret_wrapper']['consumer_secret_label'] = [
      '#type' => 'label',
      '#title' => t('Consumer Secret'),
    ];
    $build['credential_fieldset']['credential_primary_wrapper']['consumer_secret_wrapper']['consumer_secret_value_wrapper'] = [
      '#type' => 'container',
    ];
    $build['credential_fieldset']['credential_primary_wrapper']['consumer_secret_wrapper']['consumer_secret_value_wrapper']['consumer_secret_value'] = [
      '#markup' => Html::escape($consumer_secret),
    ];

    $build['credential_fieldset']['credential_primary_wrapper']['issued_wrapper'] = [
      '#type' => 'container',
    ];
    $build['credential_fieldset']['credential_primary_wrapper']['issued_wrapper']['issued_label'] = [
      '#type' => 'label',
      '#title' => t('Issued'),
    ];
    $build['credential_fieldset']['credential_primary_wrapper']['issued_wrapper']['issued_value_wrapper'] = [
      '#type' => 'container',
    ];
    $build['credential_fieldset']['credential_primary_wrapper']['issued_wrapper']['issued_value_wrapper']['issued_value'] = [
      '#markup' => Html::escape($issued),
    ];

    $build['credential_fieldset']['credential_primary_wrapper']['expires_wrapper'] = [
      '#type' => 'container',
    ];
    $build['credential_fieldset']['credential_primary_wrapper']['expires_wrapper']['expires_label'] = [
      '#type' => 'label',
      '#title' => t('Expires'),
    ];
    $build['credential_fieldset']['credential_primary_wrapper']['expires_wrapper']['expires_value_wrapper'] = [
      '#type' => 'container',
    ];
    $build['credential_fieldset']['credential_primary_wrapper']['expires_wrapper']['expires_value_wrapper']['expires_value'] = [
      '#markup' => Html::escape($expires),
    ];

    $build['credential_fieldset']['credential_primary_wrapper']['status_wrapper'] = [
      '#type' => 'container',
    ];
    $build['credential_fieldset']['credential_primary_wrapper']['status_wrapper']['status_label'] = [
      '#type' => 'label',
      '#title' => t('Key Status'),
    ];
    $build['credential_fieldset']['credential_primary_wrapper']['status_wrapper']['status_value_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'credential-status',
          'credential-status-' . Html::escape($status),
        ],
      ],
    ];
    $build['credential_fieldset']['credential_primary_wrapper']['status_wrapper']['status_value_wrapper']['status_value'] = [
      '#markup' => Html::escape(ucfirst($status)),
    ];

    $build['credential_fieldset']['credential_secondary_wrapper'] = [
      '#type' => 'container',
    ];

    $build['credential_fieldset']['credential_secondary_wrapper']['api_product_label_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'api-product-label-wrapper',
        ],
      ],
    ];
    $build['credential_fieldset']['credential_secondary_wrapper']['api_product_label_wrapper']['api_product_label'] = [
      '#type' => 'label',
      '#title' => $this->apiProductLabelPlural,
    ];

    $build['credential_fieldset']['credential_secondary_wrapper']['api_product_list_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'api-product-list-wrapper',
        ],
        'id' => [
          'api-product-list-wrapper-' . $index,
        ],
      ],
    ];

    for ($i = 0; $i < count($credential->getApiProducts()); $i++) {
      $api_product_name = $credential->getApiProducts()[$i]->getApiproduct();
      $api_product_status = $credential->getApiProducts()[$i]->getStatus();

      $build['credential_fieldset']['credential_secondary_wrapper']['api_product_list_wrapper'][$i] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'api-product-list-row',
          ],
        ],
      ];
      $build['credential_fieldset']['credential_secondary_wrapper']['api_product_list_wrapper'][$i]['api_product_name_wrapper'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'api-product-name',
          ],
        ],
      ];
      $build['credential_fieldset']['credential_secondary_wrapper']['api_product_list_wrapper'][$i]['api_product_name_wrapper']['api_product_name_value'] = [
        '#markup' => Html::escape($api_product_name),
      ];
      $build['credential_fieldset']['credential_secondary_wrapper']['api_product_list_wrapper'][$i]['api_product_status_wrapper'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'api-product-status',
            'api-product-status-' . Html::escape($api_product_status),
          ],
        ],
      ];
      $build['credential_fieldset']['credential_secondary_wrapper']['api_product_list_wrapper'][$i]['api_product_status_wrapper']['api_product_status_value'] = [
        '#markup' => Html::escape(ucfirst($api_product_status)),
      ];
    }

    $build['credential_fieldset']['edit_button_wrapper'] = [
      '#type' => 'container',
    ];
    $build['credential_fieldset']['edit_button_wrapper']['edit_button'] = [
      '#type' => 'button',
      '#value' => $this->t('Edit'),
      '#ajax' => [
        'callback' => '::editCredential',
        'wrapper' => 'api-product-list-wrapper-' . $index,
        'event' => 'click',
      ],
    ];

    return $build;
  }

  /**
   * Renders the delete app container.
   *
   * @return array
   *   The render array of the delete app container.
   */
  private function renderDeleteContainer() {
    $delete_text =
      "Deleting the '"
      . $this->developerApp->getDisplayName()
      . "' "
      . $this->appLabelSingular
      . " will also delete all of its data. The action cannot be undone.";

    $build['delete_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => 'Delete',
      '#attributes' => [
        'class' => [
          'delete-container',
        ],
      ],
    ];

    $build['delete_fieldset']['delete_text_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'delete-text-wrapper',
        ],
      ],
    ];
    $build['delete_fieldset']['delete_text_wrapper']['delete_text'] = [
      '#markup' => Html::escape($delete_text),
    ];

    $build['delete_fieldset']['delete_button_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'delete-button-wrapper',
        ],
      ],
    ];
    $build['delete_fieldset']['delete_button_wrapper']['delete_button'] = [
      '#type' => 'button',
      '#value' => $this->t('Delete'),
    ];

    return $build;
  }

}
