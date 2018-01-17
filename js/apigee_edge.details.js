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
      $('.apigee-edge--form .button--edit', context).on('click', function (event) {
        event.preventDefault();
        var clickedID = $(this).attr('id');
        var currentContainer = clickedID.split('-')[1];
        var currentCancelID = '#edit-' + currentContainer + '-cancel-button';
        var currentSaveID = '#edit-' + currentContainer + '-save-button';

        $('#' + clickedID).toggleClass('hidden');
        $(currentCancelID).toggleClass('hidden');
        $(currentSaveID).toggleClass('hidden');

        var disabledElements = [
          ['form-item-display-name-value', 'edit-display-name-value'],
          ['edit-display-name-value', 'edit-display-name-value'],
          ['edit-display-name-value', 'edit-display-name-value']
        ];
      });
    }
  };
})(jQuery, Drupal);
