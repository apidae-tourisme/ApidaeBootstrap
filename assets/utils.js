
jQuery(document).on('click', '.ellipsis', function () {
    jQuery(this).toggleClass('show');
});

jQuery(document).ready(function () {
    if (jQuery('a[data-redirect]').length > 0) {
        var a = jQuery('a[data-redirect]');
        window.setTimeout(function () {
            console.log(a.attr('href'), parseInt(a.data('redirect')) * 1000);
            window.location.href = a.attr('href');
        }, parseInt(a.data('redirect')) * 1000);
    }
});
/*
jQuery(document).ready(function () {
    jQuery('[title]').tooltip();
});
*/