jQuery(document).on('click', '#sidebarMenu span.chevron', function (e) {
    e.preventDefault();
    jQuery('#sidebarMenu li').removeClass('active');
    jQuery(this).parents('li').addClass('active');
});