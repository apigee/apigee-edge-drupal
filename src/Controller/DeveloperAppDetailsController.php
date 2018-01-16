<?php

namespace Drupal\apigee_edge\Controller;

use Drupal\apigee_edge\Entity\DeveloperAppInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays the details of a developer app on the UI.
 *
 * @package Drupal\apigee_edge\Controller
 */
class DeveloperAppDetailsController extends ControllerBase {

  use DeveloperAppDetailsControllerTrait;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, FormBuilderInterface $form_builder) {
    $this->entityTypeManager = $entity_type_manager;
    $this->formBuilder = $form_builder;
  }

  /**
   * Renders the details form of a developer app.
   */
  public function render(DeveloperAppInterface $developer_app) {
    $build = [];
    $build['form'] = \Drupal::service('entity.form_builder')->getForm($developer_app, 'details');

    return $build;
  }

}
