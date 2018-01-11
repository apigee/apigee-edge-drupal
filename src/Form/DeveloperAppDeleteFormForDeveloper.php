<?php

namespace Drupal\apigee_edge\Form;

use Drupal\apigee_edge\Entity\Form\DeveloperAppDeleteForm;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
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
    $this->entity = $entityTypeManager->getStorage('developer_app')->create();
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $app = NULL) {
    // TODO Why apps is not up-casted here?
    $query = $this->entityTypeManager->getStorage('developer_app')->getQuery()
      ->condition('name', $app);
    $ids = $query->execute();
    $this->entity = $this->entityTypeManager->getStorage('developer_app')->load(reset($ids));
    return parent::buildForm($form, $form_state);
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

}
