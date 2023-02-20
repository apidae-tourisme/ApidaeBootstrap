
jQuery(document).on('click', 'ul.nav-tabs a', function (e) {
    e.preventDefault();
    jQuery(this).closest('ul').find('a').removeClass('active');
    jQuery(this).addClass('active');

    var tc = jQuery(this).closest('ul').next('div.tab-content');
    tc.find('div.tab-pane').removeClass('active');
    tc.find('div.tab-pane#' + jQuery(this).attr('aria-controls')).addClass('active');

});

jQuery(document).ready(function () {

    var tabs = jQuery('ul.nav-tabs');

    if (tabs.length > 0) {

        jQuery(tabs).each(function () {
            // Au chargement de la page, si aucun onglet n'est actif on active le premier.
            if (jQuery(this).find('a.active').length == 0) {
                jQuery(this).find('li>a').first().trigger('click');
            }
        });

    }

});