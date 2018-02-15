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

namespace Drupal\apigee_edge\Controller;

use Apigee\Edge\Entity\EntityInterface;
use Drupal\apigee_edge\Entity\ApiProduct;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Trait for developer app view controllers.
 */
trait DeveloperAppViewControllerTrait {

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityManagerInterface $entity_manager, RendererInterface $renderer, ConfigFactoryInterface $configFactory, DateFormatterInterface $date_formatter) {
    $this->entityManager = $entity_manager;
    $this->renderer = $renderer;
    $this->configFactory = $configFactory;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager'),
      $container->get('renderer'),
      $container->get('config.factory'),
      $container->get('date.formatter')
    );
  }

  /**
   * Creates the view render array for the developer app credentials.
   *
   * @param array $build
   *   The render array.
   *
   * @return array
   *   The render array.
   */
  protected function getCredentialsRenderArray(array $build): array {
    $config = $this->configFactory->get('apigee_edge.appsettings');
    /** @var \Drupal\apigee_edge\Entity\DeveloperAppInterface $developer_app */
    $developer_app = $build['#developer_app'];
    $build = [
      '#cache' => [
        'contexts' => $developer_app->getCacheContexts(),
        'tags' => $developer_app->getCacheTags(),
      ],
    ];

    if ($config->get('associate_apps')) {
      $credential_elements = [
        'consumerKey' => [
          'label' => ('Consumer Key'),
          'value_type' => 'plain',
        ],
        'consumerSecret' => [
          'label' => ('Consumer Secret'),
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
      foreach ($developer_app->getCredentials() as $credential) {
        $build['credential'][$credential->getConsumerKey()] = [
          '#type' => 'fieldset',
          '#title' => t('Credential'),
          '#collapsible' => FALSE,
          '#attributes' => [
            'class' => [
              'items--inline',
              'apigee-edge-developer-app-view',
            ],
          ],
        ];

        $build['credential'][$credential->getConsumerKey()]['primary_wrapper'] = $this->getContainerRenderArray($credential, $credential_elements);
        $build['credential'][$credential->getConsumerKey()]['primary_wrapper']['#type'] = 'container';
        $build['credential'][$credential->getConsumerKey()]['primary_wrapper']['#attributes']['class'] = ['wrapper--primary'];

        $build['credential'][$credential->getConsumerKey()]['secondary_wrapper']['#type'] = 'container';
        $build['credential'][$credential->getConsumerKey()]['secondary_wrapper']['#attributes']['class'] = ['wrapper--secondary'];
        $build['credential'][$credential->getConsumerKey()]['secondary_wrapper']['title'] = [
          '#type' => 'label',
          '#title_display' => 'before',
          '#title' => $this->entityManager->getDefinition('api_product')->getPluralLabel(),
        ];

        foreach ($credential->getApiProducts() as $product) {
          /** @var \Drupal\apigee_edge\Entity\ApiProduct $api_product_entity */
          $api_product_entity = ApiProduct::load($product->getApiproduct());

          $build['credential'][$credential->getConsumerKey()]['secondary_wrapper']['api_product_list_wrapper'][$product->getApiproduct()] = [
            '#type' => 'container',
            '#attributes' => [
              'class' => [
                'api-product-list-row',
                'clearfix',
              ],
            ],
          ];
          $build['credential'][$credential->getConsumerKey()]['secondary_wrapper']['api_product_list_wrapper'][$product->getApiproduct()]['name'] = [
            '#prefix' => '<span class="api-product-name">',
            '#markup' => Xss::filter($api_product_entity->getDisplayName()),
            '#suffix' => '</span>',
          ];

          $status = '';
          if ($product->getStatus() === 'approved') {
            $status = 'enabled';
          }
          elseif ($product->getStatus() === 'revoked') {
            $status = 'disabled';
          }
          elseif ($product->getStatus() === 'pending') {
            $status = 'pending';
          }

          $build['credential'][$credential->getConsumerKey()]['secondary_wrapper']['api_product_list_wrapper'][$product->getApiproduct()]['status'] = [
            '#type' => 'status_property',
            '#value' => $status,
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
   * @param array $elements
   *   The elements of the container.
   *
   * @return array
   *   The render array.
   */
  protected function getContainerRenderArray(EntityInterface $entity, array $elements): array {
    $build = [];
    $ro = new \ReflectionObject($entity);
    $hidden_value_types = [
      'consumerKey',
      'consumerSecret',
    ];
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
          '#title_display' => 'before',
        ];

        if ($settings['value_type'] === 'plain') {
          $secret_attribute = '<span>';
          if (in_array($element, $hidden_value_types)) {
            $secret_attribute = '<span class="secret" data-secret-type="' . $element . '">';
          };
          $build[$element]['wrapper']['value'] = [
            '#prefix' => $secret_attribute,
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

}
