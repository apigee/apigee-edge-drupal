<?php

namespace Drupal\apigee_edge\Form;

use Apigee\Edge\Api\Management\Entity\AppCredentialInterface;
use Drupal\apigee_edge\Entity\DeveloperAppInterface;
use Drupal\apigee_edge\Utility\AppStatusDisplayTrait;
use Drupal\apigee_edge\Utility\CredentialStatusDisplayTrait;
use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for displaying and editing the developer apps.
 */
class DeveloperAppDetailsForm extends FormBase {

  use AppStatusDisplayTrait;
  use CredentialStatusDisplayTrait;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

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
   * Constructs a new DeveloperAppDetailsForm.
   */
  public function __construct(DateFormatterInterface $date_formatter, RendererInterface $renderer, ConfigFactoryInterface $configFactory) {
    $this->dateFormatter = $date_formatter;
    $this->renderer = $renderer;
    $this->configFactory = $configFactory;

    $this->appLabelSingular = \Drupal::entityTypeManager()->getDefinition('developer_app')->get('label_singular');
    $this->apiProductLabelPlural = \Drupal::entityTypeManager()->getDefinition('api_product')->get('label_plural');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('date.formatter'),
      $container->get('renderer'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'apigee_edge_developer_app_details';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, UserInterface $user = NULL, DeveloperAppInterface $app = NULL) {
    $this->user = $user;
    $this->developerApp = $app;

    $form[] = $this->renderDetailsContainer();

    for ($index = 0; $index < count($this->developerApp->getCredentials()); $index++) {
      $form[] = $this->renderCredentialContainer($this->developerApp->getCredentials()[$index], $index);
    }

    $form[] = $this->renderDeleteContainer();

    return $form;
  }

  /**
   * Renders the details container.
   *
   * @return array
   *   The render array of the details container.
   */
  private function renderDetailsContainer() {
    $config = $this->configFactory->get('apigee_edge.appsettings');

    $created = $this->dateFormatter->format($this->developerApp->getCreatedAt() / 1000, 'custom', self::DATE_FORMAT, drupal_get_user_timezone());
    $last_updated = $this->dateFormatter->format($this->developerApp->getLastModifiedAt() / 1000, 'custom', self::DATE_FORMAT, drupal_get_user_timezone());

    $display_name = $this->developerApp->getDisplayName();
    $callback_url = $this->developerApp->getCallbackUrl();
    $description = $this->developerApp->getDescription();
    $status = $this->getAppStatus($this->developerApp);

    $form['details_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => t('Details'),
    ];

    $form['details_fieldset']['details_primary_wrapper'] = [
      '#type' => 'container',
    ];

    $form['details_fieldset']['details_primary_wrapper']['display_name_value'] = [
      '#type' => 'textfield',
      '#title' => t('Application Name'),
      '#required' => TRUE,
      '#default_value' => Html::escape($display_name),
    ];

    $form['details_fieldset']['details_primary_wrapper']['callback_url_value'] = [
      '#type' => 'textfield',
      '#title' => t('Callback URL'),
      '#default_value' => Html::escape($callback_url),
      '#access' => (bool) $config->get('callback_url_visible'),
      '#required' => (bool) $config->get('callback_url_required'),
    ];

    $form['details_fieldset']['details_primary_wrapper']['description_value'] = [
      '#type' => 'textfield',
      '#title' => t('Description'),
      '#default_value' => Html::escape($description),
      '#access' => (bool) $config->get('description_visible'),
      '#required' => (bool) $config->get('description_required'),
    ];

    $form['details_fieldset']['details_secondary_wrapper'] = [
      '#type' => 'container',
    ];

    $form['details_fieldset']['details_secondary_wrapper']['status_label'] = [
      '#type' => 'label',
      '#title' => t('App status'),
    ];
    $form['details_fieldset']['details_secondary_wrapper']['status_value'] = [
      '#type' => 'status_property',
      '#value' => Html::escape($status),
    ];

    $form['details_fieldset']['details_secondary_wrapper']['created_label'] = [
      '#type' => 'label',
      '#title' => t('Created'),
    ];

    $form['details_fieldset']['details_secondary_wrapper']['created_value'] = [
      '#markup' => Html::escape($created),
    ];

    $form['details_fieldset']['details_secondary_wrapper']['last_updated_label'] = [
      '#type' => 'label',
      '#title' => t('Last updated'),
    ];
    $form['details_fieldset']['details_secondary_wrapper']['last_updated_value'] = [
      '#markup' => Html::escape($last_updated),
    ];

    $form['details_fieldset']['details_action_button_wrapper'] = [
      '#type' => 'container',
    ];

    $form['details_fieldset']['details_action_button_wrapper']['details_edit_button'] = [
      '#type' => 'button',
      '#value' => $this->t('Edit'),
    ];

    $form['details_fieldset']['details_action_button_wrapper']['details_cancel_button'] = [
      '#type' => 'button',
      '#value' => $this->t('Cancel'),
    ];

    $form['details_fieldset']['details_action_button_wrapper']['details_save_button'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;

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
    $status = $this->getCredentialStatus($this->developerApp, $credential);

    $form['credential_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => 'Credential',
    ];

    $form['credential_fieldset']['credential_primary_wrapper'] = [
      '#type' => 'container',
    ];

    $form['credential_fieldset']['credential_primary_wrapper']['consumer_key_label'] = [
      '#type' => 'label',
      '#title' => t('Consumer Key'),
    ];
    $form['credential_fieldset']['credential_primary_wrapper']['consumer_key_value'] = [
      '#markup' => Html::escape($consumer_key),
    ];
    $form['credential_fieldset']['credential_primary_wrapper']['consumer_secret_label'] = [
      '#type' => 'label',
      '#title' => t('Consumer Secret'),
    ];
    $form['credential_fieldset']['credential_primary_wrapper']['consumer_secret_value'] = [
      '#markup' => Html::escape($consumer_secret),
    ];
    $form['credential_fieldset']['credential_primary_wrapper']['issued_label'] = [
      '#type' => 'label',
      '#title' => t('Issued'),
    ];
    $form['credential_fieldset']['credential_primary_wrapper']['issued_value'] = [
      '#markup' => Html::escape($issued),
    ];
    $form['credential_fieldset']['credential_primary_wrapper']['expires_label'] = [
      '#type' => 'label',
      '#title' => t('Expires'),
    ];
    $form['credential_fieldset']['credential_primary_wrapper']['expires_value'] = [
      '#markup' => Html::escape($expires),
    ];
    $form['credential_fieldset']['credential_primary_wrapper']['status_label'] = [
      '#type' => 'label',
      '#title' => t('Key Status'),
    ];
    $form['credential_fieldset']['credential_primary_wrapper']['status_value'] = [
      '#type' => 'status_property',
      '#value' => Html::escape($status),
    ];

    $form['credential_fieldset']['credential_secondary_wrapper'] = [
      '#type' => 'container',
    ];

    $form['credential_fieldset']['credential_secondary_wrapper']['api_product_label_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'api-product-label-wrapper',
        ],
      ],
    ];
    $form['credential_fieldset']['credential_secondary_wrapper']['api_product_label_wrapper']['api_product_label'] = [
      '#type' => 'label',
      '#title' => $this->apiProductLabelPlural,
    ];

    $form['credential_fieldset']['credential_secondary_wrapper']['api_product_list_wrapper'] = [
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

      $form['credential_fieldset']['credential_secondary_wrapper']['api_product_list_wrapper'][$i] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'api-product-list-row',
          ],
        ],
      ];
      $form['credential_fieldset']['credential_secondary_wrapper']['api_product_list_wrapper'][$i]['api_product_name_wrapper'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'api-product-name',
          ],
        ],
      ];
      $form['credential_fieldset']['credential_secondary_wrapper']['api_product_list_wrapper'][$i]['api_product_name_wrapper']['api_product_name_value'] = [
        '#markup' => Html::escape($api_product_name),
      ];
      $form['credential_fieldset']['credential_secondary_wrapper']['api_product_list_wrapper'][$i]['api_product_status_value'] = [
        '#type' => 'status_property',
        '#value' => Html::escape($api_product_status),
      ];
    }

    $form['credential_fieldset']['credential_action_button_wrapper'] = [
      '#type' => 'container',
    ];

    $form['credential_fieldset']['credential_action_button_wrapper']['credential_edit_button'] = [
      '#type' => 'button',
      '#value' => $this->t('Edit'),
    ];

    $form['credential_fieldset']['credential_action_button_wrapper']['credential_cancel_button'] = [
      '#type' => 'button',
      '#value' => $this->t('Cancel'),
    ];

    $form['credential_fieldset']['credential_action_button_wrapper']['credential_save_button'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
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

    $form['delete_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => t('Delete'),
      '#attributes' => [
        'class' => [
          'delete-container',
        ],
      ],
    ];

    $form['delete_fieldset']['delete_text_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'delete-text-wrapper',
        ],
      ],
    ];
    $form['delete_fieldset']['delete_text_wrapper']['delete_text'] = [
      '#markup' => Html::escape($delete_text),
    ];

    $form['delete_fieldset']['delete_button_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'delete-button-wrapper',
        ],
      ],
    ];
    $form['delete_fieldset']['delete_button_wrapper']['delete_button'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete'),
      '#submit' => [
        '::developerAppDeleteHandler',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // TODO : save developer app.
  }

  /**
   * Custom developer app delete button handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function developerAppDeleteHandler(array &$form, FormStateInterface $form_state) {
    // TODO : delete developer app.
  }

}
