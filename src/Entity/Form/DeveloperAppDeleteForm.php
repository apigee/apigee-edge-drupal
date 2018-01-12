<?php

namespace Drupal\apigee_edge\Entity\Form;

use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * General form handler for the developer app delete forms.
 */
class DeveloperAppDeleteForm extends EntityDeleteForm {

  /**
   * DeveloperAppDeleteForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    // \Drupal\Core\Entity\EntityForm::buildEntity() would call set() on
    // $entity that only exists on config and content entities.
    // @see \Drupal\Core\Entity\EntityForm::copyFormValuesToEntity()
    return $this->entity;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  protected function getDeletionMessage() {
    $entity = $this->getEntity();
    return $this->t('The %name @label has been deleted.', [
      '@label' => $entity->getEntityType()->getLowercaseLabel(),
      '%name' => $entity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    $entity = $this->getEntity();
    return $this->t('Are you sure you want to delete the %name @label?', [
      '@label' => $entity->getEntityType()->getLowercaseLabel(),
      '%name' => $entity->label(),
    ]);
  }

  /**
   * Return page title.
   *
   * @return string
   */
  public function pageTitle() {
    return $this->t('Delete @name @label', [
      '@name' => $this->entity->getDisplayName(),
      '@label' => $this->entityTypeManager->getDefinition('developer_app')->getSingularLabel(),
    ]);
  }

}
