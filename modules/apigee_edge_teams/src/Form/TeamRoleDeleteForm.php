<?php

namespace Drupal\apigee_edge_teams\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Builds the form to delete Team Role entities.
 */
class TeamRoleDeleteForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete %name?', ['%name' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.team_role.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->entity->delete();

    $this->messenger()->addStatus(
      $this->t('%label @entity-type successfully deleted.',
        [
          '%label' => $this->entity->label(),
          '@entity-type' => mb_strtolower($this->entity->getEntityType()->getSingularLabel()),
        ])
    );

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
