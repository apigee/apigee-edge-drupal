<?php

namespace Drupal\apigee_edge_teams\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class TeamRoleForm.
 */
class TeamRoleForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $team_role = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $team_role->label(),
      '#description' => $this->t("Label for the Team Role."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $team_role->id(),
      '#machine_name' => [
        'exists' => '\Drupal\apigee_edge_teams\Entity\TeamRole::load',
      ],
      '#disabled' => !$team_role->isNew(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $team_role = $this->entity;
    $status = $team_role->save();

    $context = [
      '%label' => $team_role->label(),
      '@entity-type' => mb_strtolower($team_role->getEntityType()->getSingularLabel()),
    ];

    if ($status == SAVED_NEW) {
      $this->messenger()->addStatus($this->t('Created the %label @entity-type.', $context));
    }
    else {
      $this->messenger()->addStatus($this->t('Saved the %label @entity-type.', $context));
    }
    $form_state->setRedirectUrl($team_role->toUrl('collection'));
  }

}
