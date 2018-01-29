<?php

namespace Drupal\apigee_edge\Controller;

use Drupal\apigee_edge\Entity\DeveloperAppInterface;
use Drupal\apigee_edge\Entity\DeveloperAppPageTitleInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Displays the details of a developer app on the UI.
 *
 * @package Drupal\apigee_edge\Controller
 */
class DeveloperAppViewController extends ControllerBase implements DeveloperAppPageTitleInterface {

  use DeveloperAppViewControllerTrait;

  /**
   * Renders the details of a developer app for a given user.
   *
   * @param \Drupal\apigee_edge\Entity\DeveloperAppInterface $developer_app
   *   The developer app entity.
   *
   * @return array
   *   The render array.
   */
  public function render(DeveloperAppInterface $developer_app): array {
    return $this->getRenderArray($developer_app);
  }

  /**
   * {@inheritdoc}
   */
  public function getPageTitle(RouteMatchInterface $routeMatch): string {
    return $this->pageTitle([
      '@name' => $routeMatch->getParameter('developer_app')->getDisplayName(),
      '@devAppLabel' => $this->entityTypeManager->getDefinition('developer_app')->getSingularLabel(),
    ]);
  }

}
