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
(function ($, Drupal, drupalSettings) {

  'use strict';

  Drupal.behaviors.apigeeEdgeDetails = {
    attach: function (context, settings) {
      Drupal.apigeeEdgeDetails.editActions(context, settings);
    }
  };

  Drupal.apigeeEdgeDetails = {
    editActions: function (context, settings) {
      var secrets = $('.secret', context);
      var appElWrapper = '.app-details-wrapper';
      var loader = drupalSettings.path.baseUrl + 'core/misc/throbber-active.gif';
      for (var i = 0; i < secrets.length; i++) {
        var secret = secrets[i];
        $(secret).addClass('secret-hidden').attr('data-value', $(secret).html()).html('<span>&#149;&#149;&#149;&#149;&#149;&#149;&#149;&#149;<br><a href="#" class="secret-show-hide">' + Drupal.t('Show') + '</a></span>').show();
      }

      $('.item-property', context).on('click', 'a.secret-show-hide', function (event) {
        var $wrapper = $(this).closest(appElWrapper);
        var index = $wrapper.find('a.secret-show-hide').index(this);
        var $el = $(this).parent().parent();
        secretToggle(event, $el, $wrapper, index);
      });

      function secretToggle(event, secret, wrapper, index) {
        event.preventDefault();
        if ($(secret).hasClass('secret-hidden')) {
          $(secret).html('<img src="' + loader + '" border="0" />');
          getSecretValueAjax(wrapper.data('app'), function(data) {
            $(secret).html(data[index] + '<br><span><a href="#" class="secret-show-hide">' + Drupal.t('Hide') + '</a></span>');
          });
        }
        else {
          $(secret).html('<span>&#149;&#149;&#149;&#149;&#149;&#149;&#149;&#149;<br><a href="#" class="secret-show-hide">' + Drupal.t('Show') + '</a></span>');
        }
        $(secret).toggleClass('secret-hidden');
      }
    }
  };

  /**
   * Get credentials based on the app name.
   */
  function getSecretValueAjax(app, callback) {
    $.get( drupalSettings.path.baseUrl + 'admin/config/apigee-edge/app/' + app + '/credentials', function( data ) {
      callback(data);
    });
  };

})(jQuery, Drupal, drupalSettings);
