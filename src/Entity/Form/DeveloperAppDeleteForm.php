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

namespace Drupal\apigee_edge\Entity\Form;

use Drupal\apigee_edge\Entity\DeveloperAppPageTitleInterface;
use Drupal\apigee_edge\Entity\DeveloperStatusCheckTrait;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * General form handler for the developer app delete forms.
 */
class DeveloperAppDeleteForm extends EntityDeleteForm implements DeveloperAppPageTitleInterface {

  use DeveloperStatusCheckTrait;

  /**
   * DeveloperAppDeleteForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
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
  public function buildForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\apigee_edge\Entity\DeveloperAppInterface $developer_app */
    $developer_app = $this->entity;
    $this->checkDeveloperStatus($developer_app->getOwnerId());
    $form = parent::buildForm($form, $form_state);

    // TODO Move this verification to a reusable trait.
    // Use entity id as a default value (developer: email, api product: name)
    // and override this for developer apps where developer app name should be
    // provided instead of developer app id here.
    $form['id_verification'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Type "%developer_app" to proceed', [
        '%developer_app' => $developer_app->getName(),
      ]),
      '#default_value' => '',
      '#required' => TRUE,
      '#element_validate' => [
        '::validateVerification',
      ],
    ];

    return $form;
  }

  /**
   * Element validate callback for the id verification field.
   *
   * @param array $element
   *   Element to validate.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   * @param array $complete_form
   *   The complete form.
   */
  public function validateVerification(array &$element, FormStateInterface $form_state, array &$complete_form) {
    /** @var \Drupal\apigee_edge\Entity\DeveloperAppInterface $developer_app */
    $developer_app = $this->entity;
    if ($element['#value'] !== $developer_app->getName()) {
      $form_state->setError($element, $this->t('App name does not match app you are attempting to delete.'));
    }
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
    return $this->t('The %name @developer_app has been deleted.', [
      '@developer_app' => $entity->getEntityType()->getLowercaseLabel(),
      '%name' => $entity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    $entity = $this->getEntity();
    return $this->t('Are you sure you want to delete the %name @developer_app?', [
      '@developer_app' => $entity->getEntityType()->getLowercaseLabel(),
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
   *   The translatable page title.
   *
   * @see \Drupal\Core\StringTranslation\StringTranslationTrait::t()
   */
  protected function pageTitle(array $args = []) {
    return $this->t('Delete @name @developer_app', $args);
  }

  /**
   * {@inheritdoc}
   */
  public function getPageTitle(RouteMatchInterface $routeMatch): string {
    return $this->pageTitle([
      '@name' => $routeMatch->getParameter('developer_app')->getDisplayName(),
      '@developer_app' => $this->entityTypeManager->getDefinition('developer_app')->getSingularLabel(),
    ]);
  }

}
