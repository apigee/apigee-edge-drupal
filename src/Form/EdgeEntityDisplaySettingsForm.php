<?php

/**
 * Copyright 2020 Google Inc.
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

use Drupal\apigee_edge\Entity\ListBuilder\EdgeEntityListBuilder;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\BaseFormIdInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form for Apigee entities display settings.
 */
class EdgeEntityDisplaySettingsForm extends ConfigFormBase implements BaseFormIdInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * The entity type ID.
   *
   * @var string
   */
  protected $entityTypeId;

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * AppDisplaySettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, EntityDisplayRepositoryInterface $entity_display_repository, ModuleHandlerInterface $module_handler, RouteMatchInterface $route_match) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
    $this->entityDisplayRepository = $entity_display_repository;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('entity_display.repository'),
      $container->get('module_handler'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseFormId() {
    return 'apigee_edge_display_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    $entity_type_id = $this->routeMatch->getParameter('entity_type_id');
    return "apigee_edge_display_settings_form.{$entity_type_id}";
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'apigee_edge.display_settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity_type_id = NULL) {
    $this->entityTypeId = $entity_type_id;

    /** @var \Drupal\Core\Entity\EntityTypeInterface $entity_type */
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    $config = $this->configFactory()->get("apigee_edge.display_settings.{$entity_type_id}");

    $form['display_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Configure the display for @label listing page.', [
        '@label' => $entity_type->getPluralLabel(),
      ]),
      '#collapsible' => FALSE,
    ];

    $form['display_settings']['display_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Type'),
      '#default_value' => $config->get('display_type'),
      '#required' => TRUE,
      '#description' => $this->t('Select the <em>Default</em> type to display a table of entities with links to entity operations. Select <em>Display mode</em> to configure a custom display.'),
      '#options' => [
        EdgeEntityListBuilder::DEFAULT_DISPLAY_TYPE => $this->t('Default'),
        EdgeEntityListBuilder::VIEW_MODE_DISPLAY_TYPE => $this->t('Display mode'),
      ],
    ];

    $form['display_settings']['display_mode_container'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="display_type"]' => ['value' => 'view_mode'],
        ],
      ],
    ];

    $display_modes = [
      'default' => $this->t('Default')
    ];
    foreach ($this->entityDisplayRepository->getViewModes($entity_type->id()) as $name => $view_mode) {
      $display_modes[$name] = $view_mode['label'];
    }

    $form['display_settings']['display_mode_container']['view_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Display mode'),
      '#default_value' => $config->get('view_mode'),
      '#description' => $this->t('Select the display mode.', [
        ':uri' => '#',
      ]),
      '#options' => $display_modes,
    ];

    if ($this->moduleHandler->moduleExists('field_ui')) {
      $form['display_settings']['display_mode_container']['display_mode_help'] = [
        '#theme' => 'item_list',
        '#items' => [
          [
            '#markup' => $this->t('<a href=":uri">Click here</a> to configure the display.', [
              ':uri' => Url::fromRoute("entity.entity_view_display.{$entity_type->id()}.default")->toString(),
            ])
          ],
          [
            '#markup' => $this->t('<a href=":uri">Click here</a> to add a new display mode.', [
              ':uri' => Url::fromRoute('entity.entity_view_mode.collection')->toString(),
            ])
          ]
        ],
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory()->getEditable("apigee_edge.display_settings.{$this->entityTypeId}")
      ->set('display_type', $form_state->getValue('display_type'))
      ->set('view_mode', $form_state->getValue('view_mode'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
