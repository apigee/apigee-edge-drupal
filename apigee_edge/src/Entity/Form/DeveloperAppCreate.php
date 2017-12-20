<?php

namespace Drupal\apigee_edge\Entity\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

class DeveloperAppCreate extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $config = \Drupal::config('apigee_edge.createapp');

    /** @var \Drupal\apigee_edge\Entity\DeveloperApp $app */
    $app = $this->entity;

    $form['details'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Details'),
      '#collapsible' => FALSE,
    ];

    $form['details']['displayName'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Application Name'),
      '#required' => TRUE,
      '#default_value' => $app->getDisplayName(),
    ];

    $form['details']['name'] = [
      '#type' => 'machine_name',
      '#machine_name' => [
        'source' => ['details', 'displayName'],
        'label' => $this->t('Internal name'),
      ],
      '#title' => $this->t('Internal name'),
      '#disabled' => !$app->isNew(),
      '#default_value' => $app->getName(),
    ];

    $form['details']['callbackUrl'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Callback URL'),
      '#default_value' => $app->getCallbackUrl(),
      '#access' => (bool) $config->get('callback_url_visible'),
      '#required' => (bool) $config->get('callback_url_required'),
    ];

    $form['details']['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $app->getDescription(),
      '#access' => (bool) $config->get('description_visible'),
      '#required' => (bool) $config->get('description_required'),
    ];

    return parent::form($form, $form_state);
  }

}
