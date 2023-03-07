
/**
 * Lance la surveillance d'une tâche id
 * @param {L} id 
 */
var tachesPool = [];
var tachesFails = [];
var tachesWatched = [];
var frequence = 5000;
var tacheDebug = false;

var apidaebundle_taches_path = 'apidaebundle/taches';

import dateformat from 'dateformat';

var tachesDateOpt = {
    'day': '2-digit',
    'month': '2-digit',
    'year': 'numeric',
    'hour': '2-digit',
    'minutes': '2-digit'
}

/**
 * Remplit seulement le contenu de .status dans une [data-tacheid=?] contenu complet HTML d'une tâche à partir de son id
 * Ex : <span data-tacheid="1"><strong class="status"></strong></span>
 * Tant que la tâche est en status RUNNING, cette fonction s'auto relance toutes les frequence=5000ms
 * Dès que le status n'est plus RUNNING, lance un dernier tacheRefresh(id) (qui renseignera tout le reste : boutons, pid, fichier...)
 * 
 * Cette action se lance automatiquement au chargement de la page si on a déjà un status=RUNNING
 * <truc data-tacheid=?><machin class="status">RUNNING</machin></truc>
 * 
 * @param {*} id 
 */

function startTacheRunningWatchStatus(id) {
    if (tachesWatched.includes(id)) return false;
    tacheRunningWatchStatus(id);
}

function tacheRunningWatchStatus(id) {

    if (tacheDebug) console.log('tacheRunningWatchStatus', id);

    var status = jQuery('[data-tacheid="' + id + '"] .status:not(.badge)');
    if (status.length == 0) return;

    tachesWatched.push(id);

    var ajax = jQuery.get({
        url: apidaebundle_taches_path + "/status/" + id
    });

    ajax.done(function (data) {
        status.html(data);
        if (status.find('.badge.status').text() == 'RUNNING')
            setTimeout(function () { tacheRunningWatchStatus(id) }, frequence);
        else
            tacheRefresh(id);
    });

    ajax.fail(function (data) {
        console.log('fail', id, data);
        if (typeof tachesFails[id] == 'undefined') tachesFails[id] = 0;
        tachesFails[id]++;
        if (tachesFails[id] < 10)
            setTimeout(function () { tacheRunningWatchStatus(id) }, frequence);
    });

    tachesPool[id] = ajax;
}

/**
 * Met à jour les infos HTML détaillées d'une tâche.
 * Il faut que chaque info ait son placeholder au sein du container <truc data-tache-id="1">...</truc> :
 * Par exemple il faut un élément avec .status, un .fichier si c'est pertinent...
 * .status (pas mis à jour par tacheRefresh)
 * .fichier si la tâche a généré un fichier à télécharger
 * .startdate
 * .enddate
 * .result contiendra les logs
 * @param {*} id 
 */
function tacheRefresh(id) {

    var ajax = jQuery.get({
        'url': apidaebundle_taches_path + '/status/' + id,
        'data': { '_format': 'json' },
        'dataType': 'json'
    });

    ajax.done(function (data) {

        var tache = jQuery('[data-tacheid="' + data.id + '"]');
        if (tache.length == 0) return;
        fillTache(tache, data);
    });

    ajax.fail(function (data) {
        console.log('tacheRefresh fail', id, data);
    });

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

        jQuery('[data-tacheid="' + id + '"] .result').html('');

        if (data.startdate.date != null) {
            let d = new Date(data.startdate.date);
            jQuery('[data-tacheid="' + id + '"] .startdate').html(dateformat(d, 'dd/mm/yyyy H:MM:ss'));
        }

        jQuery('[data-tacheid="' + id + '"] .enddate').html('');
        if (typeof data === 'object' && typeof data.id === 'number') {
            setTimeout(function () { startTacheRunningWatchStatus(id) }, frequence);
        }
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

function tacheId(obj) {
    if (typeof obj.data('tacheid') != 'undefined') return obj.data('tacheid')
    if (obj.closest('[data-tacheid]').length > 0) return obj.closest('[data-tacheid]').data('tacheid');
    return false;
}

/**
 * Le rendu serveur peut ne pas s'être préoccupé de savoir s'il y avait une tâche existante pour une signature donnée.
 * On demande donc au JS de vérifier si le serveur nous a laissé des <truc class="tache" data-signature="?"></truc> dans la source :
 * Si c'est le cas, on va aller chercher les tâches correspondantes à ces signatures, et remplacer ça par des informations pertinentes
 */
function getTachesToMonitor() {
    var waiting = jQuery('.tache[data-signature]:not(.notask)');
    if (typeof waiting !== 'undefined' && waiting.length > 0) {
        if (waiting.length > 100) {
            console.log('pas de monitoring, trop de tâches à analyser');
        } else {
            var signatures = waiting.map(function () { return jQuery(this).data('signature') }).get();
            var ajax = jQuery.post({
                url: apidaebundle_taches_path + '/statusBy',
                data: { 'signatures': signatures },
                dataType: 'json'
            });
            ajax.done(function (results) {
                Object.entries(results).forEach(([key, result]) => {
                    let tache = jQuery('<div class="tache" data-tacheid="' + result.id + '"><span class="status">' + result.status_html + '</span></div>');
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

global.getTachesToMonitor = getTachesToMonitor;

/**
 * Renseigne les champs (html) de la tâche tache, avec les données data récupérées en ajax
 * @param element tache 
 * @param array data 
 */
function fillTache(tache, data) {
    if (typeof data.fichier != 'undefined')
        tache.find('.fichier').html('<small><a href="' + apidaebundle_taches_path + '/download/' + data.id + '">' + data.fichier + '</a></small>');
    else tache.find('.fichier').html('');

    if (typeof data.result != 'undefined') {
        // On affiche le result dans la case...
        tache.find('.result').html('<pre>' + JSON.stringify(data.result) + '</pre>');
        // Mais on va le MAJ en ajax histoire d'avoir l'affichage classique généré par le template
        jQuery.get({
            'url': apidaebundle_taches_path + '/result/' + data.id
        }, function (data) {
            tache.find('.result').html(data);
        });
    }
    else tache.find('.result').html('');


    if (typeof data.startdate != 'undefined' && data.startdate != null) {
        let d = new Date(data.startdate.date);
        tache.find('.startdate').html(dateformat(d, 'dd/mm/yyyy H:MM:ss'));
    }
    else tache.find('.startdate').html('');

    if (typeof data.enddate != 'undefined' && data.enddate != null && typeof data.enddate.date != undefined) {
        let d = new Date(data.enddate.date);
        tache.find('.enddate').html(dateformat(d, 'dd/mm/yyyy H:MM:ss'));
    }
    else tache.find('.enddate').html('');

    if (data.dateend != null) {

    }

    if (data.status == 'RUNNING') startTacheRunningWatchStatus(data.id);
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
            startTacheRunningWatchStatus(tacheId(jQuery(this)));
    });

    getTachesToMonitor();
});

jQuery(document).on('click', '[data-tacheid] a.refresh, a[data-tacheid].refresh', function (e) {
    e.preventDefault();
    startTacheRunningWatchStatus(tacheId(jQuery(this))); // Sert à rafraichir le STATUS
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