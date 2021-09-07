/*
 * Copyright 2021 Google Inc.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License version 2 as published by the
 * Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public
 * License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc., 51
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

/**
 * @file
 * Javascript functions related to the Analytics page of app entities.
 */
(function ($, Drupal, drupalSettings) {

  'use strict';

  /**
   * Apigee edge admin configuration.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   */
  Drupal.behaviors.apigeeEdgeAuthForm = {
    attach: function attach(context, drupalSettings) {
      $('#edit-key-input-settings-instance-type input:radio').click(function() {
        if ($(this).val() === 'private') {
          $('#edit-key-input-settings-auth-type option[value="basic"]').text(Drupal.t('HTTP basic'));
        } else if ($(this).val() === 'public') {
          $('#edit-key-input-settings-auth-type option[value="basic"]').text(Drupal.t('HTTP basic (deprecated)'));
        }
      });
    }
  }
})(jQuery, Drupal, drupalSettings);
