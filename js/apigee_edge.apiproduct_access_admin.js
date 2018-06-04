/*
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

/**
 * @file
 * Access by Visibility table behaviors.
 *
 * Modified version of Core's user.permissions.js.
 */
(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.apigee_edge_apiproduct_access_admin = {
    attach: function attach(context) {
      var self = this;
      $('table#visibility').once('visibility').each(function () {
        var $table = $(this);
        var $ancestor = void 0;
        var method = void 0;
        if ($table.prev().length) {
          $ancestor = $table.prev();
          method = 'after';
        } else {
          $ancestor = $table.parent();
          method = 'append';
        }
        $table.detach();

        var $dummy = $('<input type="checkbox" class="dummy-checkbox js-dummy-checkbox" disabled="disabled" checked="checked" />').attr('title', Drupal.t('This checkbox is disabled because this role inherited the settings of the authenticated user role.')).hide();

        // Do not override default title attribute value.
        $table.find('input[type="checkbox"]').not('.js-rid-anonymous, .js-rid-authenticated, [title]').addClass('real-checkbox js-real-checkbox').after($dummy);

        $table.find('input[type=checkbox].js-rid-authenticated').on('click.permissions', self.toggle).each(self.toggle);

        $ancestor[method]($table);
      });
    },
    toggle: function toggle() {
      var authCheckbox = this;
      var $row = $(this).closest('tr');

      $row.find('.js-real-checkbox').each(function () {
        this.style.display = authCheckbox.checked ? 'none' : '';
      });
      $row.find('.js-dummy-checkbox').each(function () {
        this.style.display = authCheckbox.checked ? '' : 'none';
      });
    }
  };
})(jQuery, Drupal);
