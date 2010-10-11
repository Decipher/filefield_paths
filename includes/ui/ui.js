// $Id$

Drupal.behaviors.fileFieldPaths_UI = function(context) {
  $.each(Drupal.settings.fileFieldPaths_UI.fields, function(id) {
    field = Drupal.settings.fileFieldPaths_UI.fields[id];
    $('.form-text.filefield_paths-' + field).parents('.form-item:first')
      .addClass('filefield_paths-js_ui-wrapper clear-block')
      .append('<a href="javascript:void(0);" class="filefield_paths-button-config" />')
      .find('a.filefield_paths-button-config')
        .bind('click', function() {
          classes = $(this).siblings('.form-text.filefield_paths').attr('class');
          field_class = classes.match(/filefield_paths-\w*/)[0];
          $('.filefield_paths_fieldset.' + field_class).slideToggle();
        });
    $('.filefield_paths_fieldset.filefield_paths-' + field).css('display', 'none');
  });
}
