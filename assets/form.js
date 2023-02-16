jQuery('.chosen').chosen();

jQuery('div.form-group input[type="file"]').on('change', function (e) {
    jQuery(this).closest('div.custom-file').find('label').html(jQuery(this).val().replace(/^.*[\\\/]/, ''));
});