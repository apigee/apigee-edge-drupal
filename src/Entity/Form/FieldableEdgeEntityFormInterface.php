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

namespace Drupal\apigee_edge\Entity\Form;

use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Interface FieldableEdgeEntityFormInterface.
 *
 * Based on ContentEntityFormInterface-
 *
 * @see \Drupal\Core\Entity\ContentEntityFormInterface
 */
interface FieldableEdgeEntityFormInterface extends EdgeEntityFormInterface {

  /**
   * Gets the form display.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Entity\Display\EntityFormDisplayInterface
   *   The current form display.
   */
  public function getFormDisplay(FormStateInterface $form_state);

  /**
   * Sets the form display.
   *
   * Sets the form display which will be used for populating form element
   * defaults.
   *
   * @param \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display
   *   The form display that the current form operates with.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return $this
   */
  public function setFormDisplay(EntityFormDisplayInterface $form_display, FormStateInterface $form_state);

}
