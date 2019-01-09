<?php

namespace Drupal\apigee_edge\Form;

/**
 * Provides a form for configuring base field settings on developer apps.
 */
class DeveloperAppBaseFieldConfigForm extends BaseFieldConfigFromBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'apigee_edge_developer_app_base_field_config_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function entityType(): string {
    return 'developer_app';
  }

  /**
   * {@inheritdoc}
   */
  protected function getLockedBaseFields(): array {
    return $this->config('apigee_edge.developer_app_settings')->get('locked_base_fields');
  }

  /**
   * {@inheritdoc}
   */
  protected function saveRequiredBaseFields(array $required_base_fields): void {
    $this->configFactory()
      ->getEditable('apigee_edge.developer_app_settings')
      ->set('required_base_fields', $required_base_fields)
      ->save();
  }

}
