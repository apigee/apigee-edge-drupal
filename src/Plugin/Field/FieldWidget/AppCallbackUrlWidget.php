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

namespace Drupal\apigee_edge\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\UriWidget;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'app_callback_url' widget.
 *
 * The field supposed to be used as a base field on company- and
 * developer app entities only.
 * Because it should be used as a base field we can not store validation
 * pattern's description in the widget's configuration otherwise it would not be
 * translateable. (Base field definitions and configurations gets cached.)
 *
 * @FieldWidget(
 *   id = "app_callback_url",
 *   label = @Translation("App Callback URL"),
 *   field_types = {
 *     "app_callback_url",
 *   }
 * )
 */
class AppCallbackUrlWidget extends UriWidget {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $settings = parent::defaultSettings();
    // Allows these to be overridden per entity programmatically
    // if it is necessary.
    $settings['placeholder'] = NULL;
    $settings['callback_url_pattern'] = NULL;
    // If you override it and the field is used as a base field then
    // this text won't be translated on the UI because its value is cached
    // to the base field definition.
    $settings['callback_url_pattern_description'] = NULL;
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $app_config = \Drupal::config('apigee_edge.common_app_settings');
    $element['value']['#pattern'] = $this->getSetting('callback_url_pattern') ?? $app_config->get('callback_url_pattern');
    $element['value']['#attributes']['title'] = $this->getSetting('callback_url_pattern_description') ?? $app_config->get('callback_url_pattern_description');
    $element['value']['#placeholder'] = $this->getSetting('placeholder') ?? $app_config->get('callback_url_placeholder');
    return $element;
  }

}
