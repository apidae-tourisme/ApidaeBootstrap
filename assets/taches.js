
/**
 * Lance la surveillance d'une tâche id
 * @param {L} id 
 */
var tachesPool = [];
var tachesFails = [];
var tachesWatched = [];
var frequence = 5000;
var tacheDebug = true;
var monitorMax = 200;

var apidaebundle_taches_path = '/apidaebundle/taches';

import dateformat from 'dateformat';

var tachesDateOpt = {
    'day': '2-digit',
    'month': '2-digit',
    'year': 'numeric',
    'hour': '2-digit',
    'minutes': '2-digit'
}


function tacheDelete(id) {
    var ajax = jQuery.ajax({
        url: apidaebundle_taches_path + '/delete/' + id,
        data: { 'force': 1 },
        dataType: 'json'
    });
    ajax.done(function (data) {
        if (data.code == 'SUCCESS') {
            jQuery('[data-tacheid="' + id + '"]').remove();
        }
    });
    ajax.fail(function (data) {
        console.log('tacheDelete fail', data, data.responseText);
    });
}

function tacheStart(id) {
    var ajax = jQuery.ajax({
        url: apidaebundle_taches_path + '/start/' + id,
        data: { force: 1 },
        dataType: 'json'
    });
    ajax.fail(function (data) {
        console.log('tacheStart fail', data, data.responseText);
    });
    ajax.done(function (data) {

        var tache = jQuery('[data-tacheid="' + id + '"]');
        tache.find('.result').html('');
        if (data.startdate != null && typeof data.startdate.date !== 'undefined' && data.startdate.date != null) {
            let d = new Date(data.startdate.date);
            tache.find('.startdate').html(dateformat(d, 'dd/mm/yyyy H:MM:ss'));
        }
        tache.find('.enddate').html('');
        tache.find('.status').html('TO_RUN'); // L'ajout du statut TO_RUN doit suffire à faire rentrer la tâche dans le monitorVisibleTasks
    });
}

function tacheStop(id) {
    var ajax = jQuery.ajax({
        'url': apidaebundle_taches_path + '/stop/' + id,
        'data': { 'force': 1 },
        dataType: 'json'
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

function getTacheId(obj) {
    if (typeof obj.data('tacheid') != 'undefined') return obj.data('tacheid')
    if (obj.closest('[data-tacheid]').length > 0) return obj.closest('[data-tacheid]').data('tacheid');
    return false;
}

function getTacheById(id) {
    return jQuery('[data-tacheid="' + id + '"]');
}


/**
 * Renseigne les champs (html) de la tâche tache, avec les données data récupérées en ajax
 * @param element tache 
 * @param array data 
 */
function fillTache(tache, data) {
    if (typeof data.fichier != 'undefined' && data.fichier != null)
        tache.find('.fichier').html('<small><a href="' + apidaebundle_taches_path + '/download/' + data.id + '">' + data.fichier + '</a></small>');
    else tache.find('.fichier').html('');

    if (typeof data.result != 'undefined' && tache.find('.result').length > 0) {
        if (Array.isArray(data.result) && typeof tache.result_html != 'undefined' && data.result.length > 0) {
            tache.find('.result').html(tache.result_html);
        } else tache.find('.result').html('');
    }
    else tache.find('.result').html('');

    if (typeof data.startdate != 'undefined' && data.startdate != null && tache.find('.startdate').length > 0) {
        let d = new Date(data.startdate.date);
        tache.find('.startdate').html(dateformat(d, 'dd/mm/yyyy H:MM:ss'));
    }
    else tache.find('.startdate').html('');

    if (typeof data.enddate != 'undefined' && data.enddate != null && typeof data.enddate.date != undefined && tache.find('.enddate').length > 0) {
        let d = new Date(data.enddate.date);
        tache.find('.enddate').html(dateformat(d, 'dd/mm/yyyy H:MM:ss'));
    }
    else tache.find('.enddate').html('');

    if (typeof data.status != 'undefined' && data.status != null && tache.find('.status').length > 0) {
        if (typeof data.status_html != 'undefined') {
            tache.find('.status').html(data.status_html);
        }
    }

    tache.find('.badge.status').fadeOut(200).fadeIn(200);
}












/**
 * Le rendu serveur peut ne pas s'être préoccupé de savoir s'il y avait une tâche existante pour une signature donnée.
 * On demande donc au JS de vérifier si le serveur nous a laissé des <truc class="tache" data-signature="?"></truc> dans la source :
 * Si c'est le cas, on va aller chercher les tâches correspondantes à ces signatures, et remplacer ça par des informations pertinentes
 */
var generateTachesToMonitorFromSignature_running = false;
function generateTachesToMonitorFromSignature() {
    var taches = jQuery('.tache[data-signature]:not(.notask)');
    if (tacheDebug) console.log('generateTachesToMonitorFromSignature', taches.length, generateTachesToMonitorFromSignature_running);
    if (typeof taches !== 'undefined' && taches.length > 0 && !generateTachesToMonitorFromSignature_running) {
        if (taches.length > monitorMax) {
            console.log('generateTachesToMonitorFromSignature stop : trop de tâches à analyser (' + taches.length + ')');
        } else {
            var signatures = taches.map(function () { return jQuery(this).data('signature') }).get();
            generateTachesToMonitorFromSignature_running = true;
            var ajax = jQuery.post({
                url: apidaebundle_taches_path + '/statusBy',
                data: { 'signatures': signatures },
                dataType: 'json'
            });
            ajax.always(function () {
                generateTachesToMonitorFromSignature_running = false;
            });
            ajax.done(function (results) {
                Object.entries(results.taches).forEach(([key, result]) => {
                    let tache = jQuery('<div class="tache" data-tacheid="' + result.id + '"><span class="status"></span></div>');
                    jQuery('.tache[data-signature="' + result.signature + '"]').replaceWith(tache);
                    fillTache(tache, result);
                });
                jQuery('.tache[data-signature]').each(function () {
                    jQuery(this).addClass('notask');
                });
            });
            ajax.fail(function (data) {
                console.log('fail', data);
            });
        }
    }
}

/**
 * Cherche les tâches TO_RUN et RUNNING visible à l'instant T sur la page
 * Vérifie leur statut par une reqûete ajax et le met à jour sur l'interface si nécessaire
 */
var monitorVisibleTasks_running = false;
function monitorVisibleTasks() {
    var taches = jQuery('[data-tacheid] .badge.status[data-status="RUNNING"], [data-tacheid] .badge.status[data-status="TO_RUN"]');
    if (tacheDebug) console.log('monitorVisibleTasks', taches.length, monitorVisibleTasks_running);
    if (taches.length > 0 && !monitorVisibleTasks_running) {
        if (taches.length > monitorMax) {
            console.log('monitorVisibleTasks : trop de tâches à monitorer (' + taches.length + ')');
        } else {
            var ids = taches.map(function () { return jQuery(this).closest('[data-tacheid]').data('tacheid') }).get();
            monitorVisibleTasks_running = true;
            var ajax = jQuery.post({
                url: apidaebundle_taches_path + '/statusBy',
                data: {
                    ids: ids
                },
                dataType: 'json'
            });
            ajax.always(function () {
                monitorVisibleTasks_running = false;
            });
            ajax.fail(function (data) {
                console.log('fail', data);
            });
            ajax.done(function (results) {
                Object.entries(results.taches).forEach(([key, result]) => {
                    let tache = getTacheById(result.id);
                    fillTache(tache, result);
                });
            });
        }
    }
}










/**
 * BINDS
 */

/**
 * Toutes les 5 secondes, on regarde si on a des tâches par signature,
 * puis on va voir si on a des TO_RUN ou RUNNING en cours pour voir où elles en sont.
 */
const interval = setInterval(function () {
    generateTachesToMonitorFromSignature();
    monitorVisibleTasks();
}, frequence);

jQuery(document).on('click', '[data-tacheid] a.stop, a[data-tacheid].stop', function (e) {
    if (!confirm('Stopper la tâche ?')) return false;
    e.preventDefault();
    tacheStop(getTacheId(jQuery(this)));
    jQuery(this).remove();
});

jQuery(document).on('click', '[data-tacheid] a.delete, a[data-tacheid].delete', function (e) {
    if (!confirm('Supprimer la tâche ?')) return false;
    e.preventDefault();
    tacheDelete(getTacheId(jQuery(this)));
    jQuery(this).remove();
});

jQuery(document).on('click', '[data-tacheid] a.start, a[data-tacheid].start', function (e) {
    e.preventDefault();
    if (jQuery(this).attr('href').match(/force/g)) {
        if (!confirm('Forcer la relance de la tâche ?')) return false;
    }
    tacheStart(getTacheId(jQuery(this)));
    jQuery(this).replaceWith('<i class="fas fa-spinner"></i>');
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