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
 * Javascript functions related to the app listing.
 */
(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.apigeeEdgeAppListing = {
    attach: function (context, settings) {
      Drupal.apigeeEdgeAppListing.tableToggle(context, settings);
    }
  };

  Drupal.apigeeEdgeAppListing = {
    tableToggle: function (context, settings) {
      $('.toggle--warning').once('tableToggle').on('click', function (event) {
        event.preventDefault();
        var targetURL = $(this).attr('href');
        var targetID = '#' + targetURL.substr(targetURL.indexOf('#') + 1);
        var textOpen = $(this).data('textOpen');
        var textClosed = $(this).data('textClosed');
        var textTarget = $(this).find('.text');
        $(targetID).toggle();
        $(this).toggleClass('open').toggleClass('closed');
        if ($(this).hasClass('open')) {
          textTarget.html(textClosed);
        }
        else {
          textTarget.html(textOpen);
        }
      });

    }
  };
})(jQuery, Drupal);
