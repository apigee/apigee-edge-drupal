<?php

namespace Drupal\apigee_edge\Form;

use Drupal\apigee_edge\Entity\DeveloperAppInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Displays the analytics page of a developer app for a given user on the UI.
 */
class DeveloperAppAnalyticsFormForDeveloper extends DeveloperAppAnalyticsForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, DeveloperAppInterface $app = NULL) {
    return parent::buildForm($form, $form_state, $app);
  }

  /**
   * {@inheritdoc}
   */
  public function getPageTitle(RouteMatchInterface $routeMatch): string {
    return $this->t('Analytics of @name', [
      '@name' => $routeMatch->getParameter('app')->getDisplayName(),
    ]);
  }

}
