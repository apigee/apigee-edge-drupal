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
 * Script for the Secret element.
 */
(function ($, Drupal, drupalSettings) {

  'use strict';

  Drupal.behaviors.SecretElement = {
    attach: function (context, settings) {
      let $secret = $('.secret', context);
      if ($secret.length) {
        $secret.each(function (i, element) {
          let $this = $(this);
          let $el = $this.find('.secret__value');
          let hClass = 'secret--hidden';
          let appElWrapper = '.app-credential';
          let $wrapper = $this.closest(appElWrapper);
          let loader = '<img src="' + drupalSettings.path.baseUrl + 'core/misc/throbber-active.gif" border="0" />';

          // Hide the value.
          $this.addClass(hClass);

          // Toggle secret.
          $(this).find('.secret__toggle').on('click', function (event) {
            let index = $(this).closest(appElWrapper).find('.secret__toggle').index(this);
            let wrapperIndex = $wrapper.data('app-container-index');
            event.preventDefault();
            $this.toggleClass(hClass);
            if ($this.hasClass(hClass)) {
              $el.html('');
            }
            else {
              $el.html(loader);
              callEndpoint($wrapper.data('team'), $wrapper.data('app'), function(data) {
                $el.html(data[wrapperIndex][index]);
              });
            }
          });

          // Copy to clipboard.
          let $copy = $(this).find('.secret__copy');
          $copy.find('button').on('click', function (event) {
            let index = $(this).closest(appElWrapper).find('.secret__copy button').index(this);
            let wrapperIndex = $wrapper.closest('fieldset').parent().find('fieldset').index($(this).closest('fieldset'));
            callEndpoint($wrapper.data('team'), $wrapper.data('app'), function(data) {
              copyToClipboard(data[wrapperIndex][index]);
              $copy.find('.badge').fadeIn().delay(1000).fadeOut();
            });
          })
        });
      }
    }
  };

  /**
   * Cross browser helper to copy to clipboard.
   */
  function copyToClipboard(text) {
    if (window.clipboardData && window.clipboardData.setData) {
      // IE specific code path to prevent textarea being shown while dialog is visible.
      return clipboardData.setData("Text", text);

    } else if (document.queryCommandSupported && document.queryCommandSupported("copy")) {
      var textarea = document.createElement("textarea");
      textarea.textContent = text;
      // Prevent scrolling to bottom of page in MS Edge.
      textarea.style.position = "fixed";
      document.body.appendChild(textarea);
      textarea.select();
      try {
        // Security exception may be thrown by some browsers.
        return document.execCommand("copy");
      } catch (ex) {
        return false;
      } finally {
        document.body.removeChild(textarea);
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
