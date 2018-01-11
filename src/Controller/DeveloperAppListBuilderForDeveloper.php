<?php

namespace Drupal\apigee_edge\Controller;

use Drupal\apigee_edge\Entity\DeveloperAppInterface;
use Drupal\apigee_edge\Entity\ListBuilder\DeveloperAppListBuilder;
use Drupal\Component\Utility\Html;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Url;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Lists developer apps of a developer on the UI.
 *
 * @package Drupal\apigee_edge\Controller
 */
class DeveloperAppListBuilderForDeveloper extends DeveloperAppListBuilder implements ContainerInjectionInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $entity_type = $container->get('entity_type.manager')->getDefinition('developer_app');
    return new static(
      $entity_type,
      $container->get('entity.manager')->getStorage($entity_type->id()),
      $container->get('entity.manager'),
      $container->get('renderer')
    );
  }

  /**
   * @param array $headers
   * @param \Drupal\user\UserInterface|NULL $user
   *
   * @return array|int
   */
  protected function getEntityIds(array $headers = [], UserInterface $user = NULL) {
    $storedDeveloperId = $user->get('apigee_edge_developer_id')->target_id;
    if ($storedDeveloperId === NULL) {
      return [];
    }
    $query = $this->storage->getQuery()
      ->condition('developerId', $storedDeveloperId);
    $query->tableSort($headers);
    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function load(array $headers = []) {
    return [];
  }

  /**
   * Load developer apps of a Drupal user.
   *
   * @param \Drupal\user\UserInterface $user
   *   User object.
   * @param array $headers
   *   Table headers.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   Developer apps or an empty array.
   */
  protected function loadByUser(UserInterface $user, array $headers = []) {
    $entity_ids = $this->getEntityIds($headers, $user);
    return $this->storage->loadMultiple($entity_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $headers = parent::buildHeader();
    unset($headers['operations']);
    return $headers;
  }

  /**
   * {@inheritdoc}
   */
  protected function getAppDetailsLink(DeveloperAppInterface $app) {
    return $app->toLink(NULL, 'canonical-by-developer');
  }

  /**
   * {@inheritdoc}
   */
  protected function getUniqueCssIdForApp(DeveloperAppInterface $app): string {
    // If we are listing the apps of a developer than app name is also
    // unique.
    return Html::getUniqueId($app->getName());
  }

  /**
   * {@inheritdoc}
   */
  public function render(UserInterface $user = NULL) {
    $build = parent::render();

    $build['table']['#empty'] = $this->t('Looks like you do not have any apps. Get started by adding one.');
    $build['add_app']['link']['#url'] = new Url('entity.developer_app.add_form_for_developer', ['user' => $user->id()], $build['add_app']['link']['#url']->getOptions());

    $tableRows = [];
    foreach ($this->loadByUser($user, $this->buildHeader()) as $entity) {
      /** @var \Drupal\apigee_edge\Entity\DeveloperAppInterface $entity */
      if ($row = $this->buildRow($entity)) {
        $rows = $this->buildRow($entity);
        reset($rows);
        $infoRow = key($rows);
        unset($rows[$infoRow]['data']['operations']);
        end($rows);
        $warningRow = key($rows);
        if (!empty($rows[$warningRow]['data'])) {
          $rows[$warningRow]['data']['info']['colspan'] = 2;
        }
        $tableRows += $rows;
      }
    }

    $build['table']['#rows'] = $tableRows;

    return $build;
  }

  /**
   * Redirects users to their My apps page.
   *
   * This controller assumes that it is only invoked for authenticated users.
   * This is enforced for the 'apigee_edge.user.my_apps' route with the
   * '_user_is_logged_in' requirement.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Returns a redirect to the My apps of the currently logged in user.
   */
  public function myAppsPage() {
    $options['absolute'] = TRUE;
    $url = Url::fromRoute('entity.developer_app.collection_by_developer', ['user' => \Drupal::currentUser()->id()], $options);
    return new RedirectResponse($url->toString(), 302);
  }

}
