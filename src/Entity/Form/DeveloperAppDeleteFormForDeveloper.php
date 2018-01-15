<?php

namespace Drupal\apigee_edge\Entity\Form;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Dedicated form handler that allows a developer to delete its own app.
 */
class DeveloperAppDeleteFormForDeveloper extends DeveloperAppDeleteForm {

  /**
   * DeveloperAppDeleteFormForDeveloper constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function __construct(\Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager, ModuleHandlerInterface $moduleHandler) {
    parent::__construct($entityTypeManager);
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.manager'), $container->get('module_handler'));
  }

  /**
   * {@inheritdoc}
   */
  protected function getRedirectUrl() {
    $entity = $this->getEntity();
    return $entity->toUrl('collection-by-developer');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return $this->getRedirectUrl();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $form_state->setRedirectUrl($this->getRedirectUrl());
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
    return $this->pageTitle([
      '@name' => $routeMatch->getParameter('app')->getDisplayName(),
      '@devAppLabel' => $this->entityTypeManager->getDefinition('developer_app')->getSingularLabel(),
    ]);
  }

}
