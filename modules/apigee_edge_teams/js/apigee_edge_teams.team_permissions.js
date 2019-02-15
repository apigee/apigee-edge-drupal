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
 * Team permissions table behaviors.
 *
 * Modified version of Drupal core's user.permissions.js.
 */
(function ($, Drupal) {
  Drupal.behaviors.apigee_edge_teams_team_permissions = {
    attach: function attach(context) {
      var self = this;
      $('table#permissions').once('permissions').each(function () {
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

        var $dummy = $('<input type="checkbox" class="dummy-checkbox js-dummy-checkbox" disabled="disabled" checked="checked" />').attr('title', Drupal.t('This permission is inherited from the member role.')).hide();

        $table.find('input[type="checkbox"]').not('.js-rid-member').addClass('real-checkbox js-real-checkbox').after($dummy);

        $table.find('input[type=checkbox].js-rid-member').on('click.permissions', self.toggle).each(self.toggle);

        $ancestor[method]($table);
      });
    },
    toggle: function toggle() {
      var memberCheckbox = this;
      var $row = $(this).closest('tr');

      $row.find('.js-real-checkbox').each(function () {
        this.style.display = memberCheckbox.checked ? 'none' : '';
      });
      $row.find('.js-dummy-checkbox').each(function () {
        this.style.display = memberCheckbox.checked ? '' : 'none';
      });
    }
  };
})(jQuery, Drupal);
