<?php

namespace Drupal\apigee_edge\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a controller for the configurable error page.
 */
class ErrorPageController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $configFactory) {
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * Builds the renderable array for page.html.twig using the module's config.
   */
  public function render() {
    $build['content'] = [
      '#type' => 'processed_text',
      '#format' => $this->configFactory->get('apigee_edge.error_page')->get('error_page_content.format'),
      '#text' => $this->configFactory->get('apigee_edge.error_page')->get('error_page_content.value'),
    ];
    return $build;
  }

  /**
   * Returns the error page title from the module's config.
   */
  public function getPageTitle() {
    return $this->configFactory->get('apigee_edge.error_page')->get('error_page_title');
  }

}
