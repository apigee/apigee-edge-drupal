<?php

namespace Drupal\apigee_edge_apidocs\Form;

use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Url;

/**
 * Class ApiDocUpdateSpecForm.
 */
class ApiDocUpdateSpecForm extends ContentEntityConfirmFormBase {

  use MessengerTrait;

  /**
   * {@inheritdoc}
   */
  protected $operation = 'update_spec';

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to update the OpenAPI specification
     file from URL on API Doc %name?', [
       '%name' => $this->entity->label(),
      ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.apidoc.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This will replace the current OpenAPI specification file.
     This action cannot be undone.');
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /* @var \Drupal\apigee_edge_apidocs\Entity\ApiDocInterface $entity */
    $entity = $this->getEntity();
    $status = $entity->updateOpenApiSpecFile(TRUE, TRUE);

    if ($status) {
      $this->messenger()->addStatus($this->t('API Doc %label: updated the OpenAPI
      specification file from URL.', [
        '%label' => $this->entity->label(),
      ]));
    }
    else {
      $this->messenger()->addError($this->t('API Doc %label: could not update
      the OpenAPI specification file from URL.', [
        '%label' => $this->entity->label(),
      ]));
    }
  }

}
