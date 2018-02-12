/**
 * @file
 * Javascript functions related to the Apigee Edge Drupal Module.
 */
(function ($, Drupal) {
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
