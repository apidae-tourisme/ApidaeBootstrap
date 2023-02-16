
/**
 * Lance la surveillance d'une tâche id
 * @param {L} id 
 */
var tachesPool = [];
var tachesFails = [];
var frequence = 5000;

var dateFormat = require("dateformat");

var tachesDateOpt = {
    'day': '2-digit',
    'month': '2-digit',
    'year': 'numeric',
    'hour': '2-digit',
    'minutes': '2-digit'
}

function tacheWatch(id) {
    var status = jQuery('[data-tacheid="' + id + '"] .status:not(.badge)');

    var ajax = jQuery.get({
        url: "/taches/status/" + id
    });

    ajax.done(function (data) {
        status.html(data);
        if (status.find('.badge.status').text() == 'RUNNING')
            setTimeout(function () { tacheWatch(id) }, frequence);
        else
            tacheRefresh(id);
    });

    ajax.fail(function (data) {
        console.log('fail', id, data);
        if (typeof tachesFails[id] == 'undefined') tachesFails[id] = 0;
        tachesFails[id]++;
        if (tachesFails[id] < 10)
            setTimeout(function () { tacheWatch(id) }, frequence);
    });

    tachesPool[id] = ajax;

}

function tacheRefresh(id) {

    var ajax = jQuery.get({
        'url': '/taches/status/' + id,
        'data': { '_format': 'json' },
        'dataType': 'json'
    });

    ajax.done(function (data) {

        var tache = jQuery('[data-tacheid="' + data.id + '"]');
        if (tache.length == 0) return;

        if (typeof data.fichier != 'undefined')
            tache.find('.fichier').html('<small><a href="/taches/download/' + data.id + '">' + data.fichier + '</a></small>');
        else tache.find('.fichier').html('');

        if (typeof data.result != 'undefined') {
            // On affiche le result dans la case...
            tache.find('.result').html('<pre>' + JSON.stringify(data.result) + '</pre>');
            // Mais on va le MAJ en ajax histoire d'avoir l'affichage classique généré par le template
            jQuery.get({
                'url': '/taches/result/' + id
            }, function (data) {
                tache.find('.result').html(data);
            });
        }
        else tache.find('.result').html('');


        if (typeof data.startdate != 'undefined') {
            let d = new Date(data.startdate.date);
            tache.find('.startdate').html(dateFormat(d, 'dd/mm/yyyy H:MM:ss'));
        }
        else tache.find('.startdate').html('');

        if (typeof data.enddate != 'undefined') {
            let d = new Date(data.enddate.date);
            tache.find('.enddate').html(dateFormat(d, 'dd/mm/yyyy H:MM:ss'));
        }
        else tache.find('.enddate').html('');

        if (data.dateend != null) {

        }
    });

    ajax.fail(function (data) {
        console.log('tacheRefresh fail', id, data);
    });

}


function tacheDelete(id) {
    var ajax = jQuery.ajax({
        'url': '/taches/delete/' + id,
        'data': { 'force': 1 }
    });
    ajax.done(function (data) {
        if (data == true) {
            jQuery('[data-tacheid="' + id + '"]').remove();
        }
    });
    ajax.fail(function (data) {
        console.log('tacheDelete fail', data, data.responseText);
    });
}

function tacheStart(id) {
    var ajax = jQuery.ajax({
        'url': '/taches/start/' + id,
        'data': { 'force': 1 }
    });
    ajax.fail(function (data) {
        console.log('tacheStart fail', data, data.responseText);
    });
    ajax.done(function (data) {

        jQuery('[data-tacheid="' + id + '"] .result').html('');

        if (data.startdate.date != null) {
            let d = new Date(data.startdate.date);
            jQuery('[data-tacheid="' + id + '"] .startdate').html(dateFormat(d, 'dd/mm/yyyy H:MM:ss'));
        }

        jQuery('[data-tacheid="' + id + '"] .enddate').html('');
        if (typeof data === 'object' && typeof data.id === 'number') {
            setTimeout(function () { tacheWatch(id) }, frequence);
        }
    });
}

function tacheStop(id) {
    var ajax = jQuery.ajax({
        'url': '/taches/stop/' + id,
        'data': { 'force': 1 }
    });
    ajax.fail(function (data) {
        console.log('tacheStop fail', data, data.responseText);
    });
    ajax.done(function (data) {
        /**
         * @todo : on fait quoi quand on clique sur stopper une tâche et que ça a a marché ?
         */
    });
}

function tacheId(obj) {
    if (typeof obj.data('tacheid') != 'undefined') return obj.data('tacheid')
    if (obj.closest('[data-tacheid]').length > 0) return obj.closest('[data-tacheid]').data('tacheid');
    return false;
}






/**
 * BINDS
 */

jQuery(document).ready(function () {

    /**
     * Au chargement de la page, on cherche tout élément qui possederait un data-tacheid
     * Pour chacun on vérifie le statut, s'il est running on lance un watch
     */
    jQuery("[data-tacheid] .status").each(function () {
        if (jQuery(this).text() == 'RUNNING')
            tacheWatch(tacheId(jQuery(this)));
    });
});

jQuery(document).on('click', '[data-tacheid] a.refresh, a[data-tacheid].refresh', function (e) {
    e.preventDefault();
    tacheWatch(tacheId(jQuery(this))); // Sert à rafraichir le STATUS
    tacheRefresh(tacheId(jQuery(this)));
});

jQuery(document).on('click', '[data-tacheid] a.stop, a[data-tacheid].stop', function (e) {
    if (!confirm('Stopper la tâche ?')) return false;
    e.preventDefault();
    tacheStop(tacheId(jQuery(this)));
    jQuery(this).remove();
});

jQuery(document).on('click', '[data-tacheid] a.delete, a[data-tacheid].delete', function (e) {
    if (!confirm('Supprimer la tâche ?')) return false;
    e.preventDefault();
    tacheDelete(tacheId(jQuery(this)));
    jQuery(this).remove();
});

jQuery(document).on('click', '[data-tacheid] a.start, a[data-tacheid].start', function (e) {
    e.preventDefault();
    if (jQuery(this).attr('href').match(/force/g)) {
        if (!confirm('Forcer la relance de la tâche ?')) return false;
    }
    tacheStart(tacheId(jQuery(this)));
});

// Bouton "paramètre"
jQuery(document).on('click', '[data-tacheid] i.showParametres', function (e) {
    e.preventDefault();
    jQuery(this).parent().find('div.showParametres').toggle();
});

// Bouton "afficher les détails de result (logs, warning, error)"
jQuery(document).on('click', '[data-tacheid] .details', function (e) {
    e.preventDefault();
    jQuery(this).next('code').toggle();
});