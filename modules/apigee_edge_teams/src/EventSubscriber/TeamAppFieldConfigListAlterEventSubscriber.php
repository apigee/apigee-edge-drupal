<?php

/**
 * Copyright 2018 Google Inc.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 */

namespace Drupal\apigee_edge_teams\EventSubscriber;

use Drupal\apigee_edge\Event\EdgeEntityFieldConfigListAlterEvent;
use Drupal\apigee_edge_teams\Form\TeamAppBaseFieldConfigForm;
use Drupal\Core\Form\FormBuilderInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds base field configuration form to team app entity's field config UI.
 */
final class TeamAppFieldConfigListAlterEventSubscriber implements EventSubscriberInterface {

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  private $formBuilder;

  /**
   * TeamAppFieldConfigListAlterEventSubscriber constructor.
   *
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder service.
   */
  public function __construct(FormBuilderInterface $form_builder) {
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      EdgeEntityFieldConfigListAlterEvent::EVENT_NAME => 'alterPage',
    ];
  }

  /**
   * Alters the field config UI page.
   *
   * @param \Drupal\apigee_edge\Event\EdgeEntityFieldConfigListAlterEvent $event
   *   The field config list alter event.
   */
  public function alterPage(EdgeEntityFieldConfigListAlterEvent $event) {
    if ($event->getEntityType() === 'team_app') {
      $page = &$event->getPage();
      $page['base_field_config'] = $this->formBuilder->getForm(TeamAppBaseFieldConfigForm::class);
    }
  }

}
