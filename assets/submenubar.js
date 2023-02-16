
var submenubar_h2 = null;
var submenubar = null;

/*
jQuery(document).on('scroll', function (e) {

    if (submenubar === false) return false;

    if (submenubar == null) {
        var submenubar = jQuery('.submenubar');
        if (submenubar.length == 0) {
            submenubar = false;
            return false;
        }

        submenubar_h2 = jQuery('main').find('h2');
    }

});
*/

jQuery(document).on('click', '.submenubar li a', function (e) {
    e.preventDefault();
    if (jQuery(jQuery(this).attr('href')).length == 0) return false;
    jQuery([document.documentElement, document.body]).animate({
        scrollTop: jQuery(jQuery(this).attr('href')).offset().top - 160
    });
});