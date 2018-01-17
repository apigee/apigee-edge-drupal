<?php

namespace Drupal\apigee_edge\Entity\Form;

use Drupal\apigee_edge\Entity\ApiProduct;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;

/**
 * General form handler for the developer app details forms.
 */
class DeveloperAppEditForm extends DeveloperAppCreateForm {

  /**
   * The developer app entity.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperAppInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->get('apigee_edge.appsettings');
    $form = parent::form($form, $form_state);

    $form['details']['name']['#access'] = FALSE;
    $form['details']['developerId']['#access'] = FALSE;
    $form['product']['#access'] = !isset($form['product']) ?: FALSE;
    $form['#tree'] = TRUE;

    if ($config->get('associate_apps') && $config->get('user_select')) {
      for ($credential_index = 0; $credential_index < count($this->entity->getCredentials()); $credential_index++) {
        $credential = $this->entity->getCredentials()[$credential_index];
        $credential_title = '<span>' . ucfirst($credential->getStatus()) . '</span> - Credential - ' . $credential->getConsumerKey();

        $form['credential'][$credential_index] = [
          '#type' => 'fieldset',
          '#title' => $credential_title,
          '#collapsible' => FALSE,
        ];

        /** @var \Drupal\apigee_edge\Entity\ApiProduct[] $products */
        $products = ApiProduct::loadMultiple();
        $product_list = [];
        foreach ($products as $product) {
          $product_list[$product->id()] = $product->getDisplayName();
        }

        $multiple = $config->get('multiple_products');
        $current_products = [];
        foreach ($credential->getApiProducts() as $product) {
          $current_products[] = $product->getApiproduct();
        }

        $form['credential'][$credential_index]['api_products'] = [
          '#title' => $this->entityTypeManager->getDefinition('api_product')->getPluralLabel(),
          '#required' => $config->get('require'),
          '#options' => $product_list,
          '#default_value' => $multiple ? $current_products : reset($current_products),
        ];

        if ($config->get('display_as_select')) {
          $form['credential'][$credential_index]['api_products']['#type'] = 'select';
          $form['credential'][$credential_index]['api_products']['#multiple'] = $multiple;
        }
        else {
          $form['credential'][$credential_index]['api_products']['#type'] = $multiple ? 'checkboxes' : 'radios';
        }
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = t('Save');

    $actions['delete']['#access'] = $this->entity->access('delete');
    $actions['delete']['#url'] = $this->getFormId() === 'developer_app_developer_app_edit_for_developer_form'
      ? $this->entity->toUrl('delete-form-for-developer')
      : $this->entity->toUrl('delete-form');

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $this->entity->save();
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityFromRouteMatch(RouteMatchInterface $route_match, $entity_type_id) {
    if ($route_match->getRawParameter('app') !== NULL) {
      $entity = $route_match->getParameter('app');
    }
    else {
      $entity = parent::getEntityFromRouteMatch($route_match, $entity_type_id);
    }
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getPageTitle(RouteMatchInterface $routeMatch): string {
    return $this->t('Edit @devAppLabel', ['@devAppLabel' => $this->entityTypeManager->getDefinition('developer_app')->getLowercaseLabel()]);
  }

}
