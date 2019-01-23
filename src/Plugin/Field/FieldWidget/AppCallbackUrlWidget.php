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
 * Because it should be used as a base field we can not store human readable
 * strings in the widget's configuration otherwise they could not be
 * translated. (Base field definitions and configurations gets cached.)
 *
 * @see https://www.drupal.org/node/2546212
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
    // If you override these and the field is used as a base field then
    // you can not translatable them on the UI because these values are
    // being cached to the base field definition.
    // @see https://www.drupal.org/node/2546212
    $settings['callback_url_description'] = NULL;
    $settings['callback_url_pattern_error_message'] = NULL;
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
    // Fallback to the default configuration if field widget does
    // not have an override.
    $app_settings = \Drupal::config("apigee_edge.common_app_settings");
    $element['value']['#pattern'] = $this->getSetting('callback_url_pattern') ?? $app_settings->get('callback_url_pattern');
    $element['value']['#attributes']['title'] = $this->getSetting('callback_url_pattern_error_message') ?? $app_settings->get('callback_url_pattern_error_message');
    $element['value']['#placeholder'] = $this->getSetting('placeholder') ?? $app_settings->get('callback_url_placeholder');
    $element['value']['#description'] = $this->getSetting('callback_url_description') ?? $app_settings->get('callback_url_description');
    return $element;
  }

}
