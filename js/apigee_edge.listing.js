/**
 * @file
 * Javascript functions related to the Apigee Edge Drupal Module.
 */
(function ($, Drupal) {
  Drupal.behaviors.apigeeEdgeListing = {
    attach: function (context, settings) {
      Drupal.apigeeEdgeListing.tableToggle(context, settings);
    }
  };

  Drupal.apigeeEdgeListing = {
    tableToggle: function (context, settings) {
      $('.toggle--warning').on('click', function (event) {
        event.preventDefault();
        var targetURL = $(this).attr('href');
        var targetID = '#' + targetURL.substr(targetURL.indexOf('#') + 1);
        $(targetID).toggle();
        $(this).toggleClass('open').toggleClass('closed');
      });

    }
  };
})(jQuery, Drupal);
