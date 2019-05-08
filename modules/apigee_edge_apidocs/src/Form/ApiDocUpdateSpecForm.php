<?php

namespace Drupal\apigee_edge_apidocs\Form;

use Drupal\apigee_edge_apidocs\ApiDocSpecFetcherInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ApiDocUpdateSpecForm.
 */
class ApiDocUpdateSpecForm extends ContentEntityConfirmFormBase {

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The ApiDoc spec fetcher service.
   *
   * @var \Drupal\apigee_edge_apidocs\ApiDocSpecFetcherInterface
   */
  protected $specFetcher;

  /**
   * {@inheritdoc}
   */
  protected $operation = 'reimport_spec';

  /**
   * Constructs a ApiDocUpdateSpecForm object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\apigee_edge_apidocs\ApiDocSpecFetcherInterface $spec_fetcher
   *   The ApiDoc spec fetcher service.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL, TimeInterface $time = NULL, MessengerInterface $messenger, ApiDocSpecFetcherInterface $spec_fetcher) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->messenger = $messenger;
    $this->specFetcher = $spec_fetcher;
  }
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('messenger'),
      $container->get('apigee_edge_apidocs.spec_fetcher')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to update the OpenAPI specification file from URL on API Doc %name?', [
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
    $status = $this->specFetcher->fetchSpec($entity, FALSE, FALSE);

    if ($status) {
      $this->messenger->addStatus($this->t('API Doc %label: imported the OpenAPI
      specification file from URL.', [
        '%label' => $this->entity->label(),
      ]));
    }
    else {
      $this->messenger()->addError($this->t('API Doc %label: could not import
      the OpenAPI specification file from URL.', [
        '%label' => $this->entity->label(),
      ]));
    }
  }

}
