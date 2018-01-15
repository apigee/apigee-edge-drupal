<?php

namespace Drupal\apigee_edge\Entity\Form;

use Drupal\apigee_edge\Entity\DeveloperAppPageTitleInterface;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * General form handler for the developer app delete forms.
 */
class DeveloperAppDeleteForm extends EntityDeleteForm implements DeveloperAppPageTitleInterface {

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
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.manager'));
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
  protected function getDeletionMessage() {
    $entity = $this->getEntity();
    return $this->t('The %name @devAppLabel has been deleted.', [
      '@devAppLabel' => $entity->getEntityType()->getLowercaseLabel(),
      '%name' => $entity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    $entity = $this->getEntity();
    return $this->t('Are you sure you want to delete the %name @devAppLabel?', [
      '@devAppLabel' => $entity->getEntityType()->getLowercaseLabel(),
      '%name' => $entity->label(),
    ]);
  }

  /**
   * Builds a translatable page title by using values from args as replacements.
   *
   * @param array $args
   *   An associative array of replacements.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *
   * @see \Drupal\Core\StringTranslation\StringTranslationTrait::t()
   */
  protected function pageTitle(array $args = []) {
    return $this->t('Delete @name @devAppLabel', $args);
  }

  /**
   * {@inheritdoc}
   */
  public function getPageTitle(RouteMatchInterface $routeMatch): string {
    return $this->pageTitle([
      '@name' => $routeMatch->getParameter('developer_app')->getDisplayName(),
      '@devAppLabel' => $this->entityTypeManager->getDefinition('developer_app')->getSingularLabel(),
    ]);
  }

}
