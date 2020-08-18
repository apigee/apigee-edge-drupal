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
      var appElWrapper = '.app-credential';
      var showHideEl = 'a.secret-show-hide';
      var pClass = 'processing';
      var loader = '<img src="' + drupalSettings.path.baseUrl + 'core/misc/throbber-active.gif" border="0" />';
      for (var i = 0; i < secrets.length; i++) {
        var secret = secrets[i];
        $(secret)
          .addClass('secret-hidden')
          .attr('data-value', $(secret).html())
          .html('<span>&#149;&#149;&#149;&#149;&#149;&#149;&#149;&#149;<br><a href="#" class="secret-show-hide">' + Drupal.t('Show') + '</a></span>')
          .show();
      }

      $('.item-property', context).on('click', showHideEl, function (event) {
        event.preventDefault();
        var $wrapper = $(this).closest(appElWrapper);
        if (!$(this).hasClass(pClass)) {
          $(showHideEl).addClass(pClass);
          secretToggle(
            $(this).parent().parent(),
            $wrapper.data('team'),
            $wrapper.data('app'),
            $wrapper.data('app-container-index'),
            $(this).closest('.secret').data('app-index')
          );
        }
      });

      function secretToggle(el, teamAppName, appName, wrapperIndex, keyIndex) {
        if ($(el).hasClass('secret-hidden')) {
          $(el).html(loader);
          callEndpoint(teamAppName, appName, function(data) {
            $(el).html(data[wrapperIndex][keyIndex] + '<br><span><a href="#" class="secret-show-hide">' + Drupal.t('Hide') + '</a></span>');
            $(showHideEl).removeClass(pClass);
          });
        }
        else {
          $(el).html('<span>&#149;&#149;&#149;&#149;&#149;&#149;&#149;&#149;<br><a href="#" class="secret-show-hide">' + Drupal.t('Show') + '</a></span>');
        }
        $(el).toggleClass('secret-hidden');
      }
    }
  };

  /**
   * Get credentials based on the app name.
   */
  function callEndpoint(teamApp,  app, callback) {
    var endpoint = drupalSettings.path.baseUrl + 'user/' + drupalSettings.currentUser + '/apps/' + app + '/api-keys';
    if (teamApp !== undefined && teamApp !== 0 && teamApp !== '') {
      endpoint = drupalSettings.path.baseUrl + 'teams/' + teamApp + '/apps/' + app + '/api-keys';
    }
    $.get(endpoint, function(data) {
      callback(data);
    });
  };

})(jQuery, Drupal, drupalSettings);
