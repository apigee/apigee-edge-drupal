/*
 * Copyright 2018 Google Inc.
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
 * Javascript functions related to the Apigee Edge Drupal Module.
 */
(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.apigeeEdgeDetails = {
    attach: function (context, settings) {
      Drupal.apigeeEdgeDetails.editActions(context, settings);
    }
  };

  Drupal.apigeeEdgeDetails = {
    editActions: function (context, settings) {
      var secrets = $('.secret', context);
      for (var i = 0; i < secrets.length; i++) {
        var secret = secrets[i];
        $(secret).addClass('secret-hidden').attr('data-value', $(secret).html()).html('<span>&#149;&#149;&#149;&#149;&#149;&#149;&#149;&#149;<br><a href="#" class="secret-show-hide">' + Drupal.t('Show') + '</a></span>').show();
      }

      $('.item-property', context).on('click', 'a.secret-show-hide', function (event) {
        secretToggle(event, $(this).parent().parent());
      });

      function secretToggle(event, secret) {
        event.preventDefault();
        if ($(secret).hasClass('secret-hidden')) {
          $(secret).html(secret.attr('data-value') + '<br><span><a href="#" class="secret-show-hide">' + Drupal.t('Hide') + '</a></span>');
        }
        else {
          $(secret).html('<span>&#149;&#149;&#149;&#149;&#149;&#149;&#149;&#149;<br><a href="#" class="secret-show-hide">' + Drupal.t('Show') + '</a></span>');
        }
        $(secret).toggleClass('secret-hidden');
      }
    }
  };
})(jQuery, Drupal);
