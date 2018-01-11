<?php

namespace Drupal\apigee_edge\Form;

use Drupal\apigee_edge\Entity\Form\DeveloperAppCreateForm;
use Drupal\apigee_edge\SDKConnectorInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Dedicated form handler that allows a developer to create an developer app.
 */
class DeveloperAppCreateFormForDeveloper extends DeveloperAppCreateForm {

  /** @var \Drupal\apigee_edge\Entity\DeveloperAppInterface */
  protected $entity;

  /**
   * DeveloperCreateDeveloperAppForm constructor.
   *
   * @param \Drupal\apigee_edge\SDKConnectorInterface $sdkConnector
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   * @param \Drupal\Core\Entity\EntityManagerInterface $entityManager
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function __construct(SDKConnectorInterface $sdkConnector, ConfigFactory $configFactory, EntityManagerInterface $entityManager, EntityTypeManagerInterface $entityTypeManager, ModuleHandlerInterface $moduleHandler) {
    parent::__construct($sdkConnector, $configFactory);
    $this->sdkConnector = $sdkConnector;
    $this->configFactory = $configFactory;
    $this->entityManager = $entityManager;
    $this->entityTypeManager = $entityTypeManager;
    $this->moduleHandler = $moduleHandler;
    $this->entity = $this->entityTypeManager->getStorage('developer_app')->create([]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, UserInterface $user = NULL) {
    $form = parent::buildForm($form, $form_state);
    $form['details']['developerId'] = [
      '#type' => 'value',
      '#value' => $this->entity->getDeveloperId(),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('apigee_edge.sdk_connector'), $container->get('config.factory'), $container->get('entity.manager'), $container->get('entity_type.manager'), $container->get('module_handler'));
  }

  /**
   * {@inheritdoc}
   */
  protected function init(FormStateInterface $form_state) {
    parent::init($form_state);
    $this->entity->setOwner($form_state->getBuildInfo()['args'][0]);
  }

  /**
   * {@inheritdoc}
   */
  protected function getRedirectUrl() {
    $entity = $this->getEntity();
    return $entity->toUrl('collection-by-developer');
  }

}
