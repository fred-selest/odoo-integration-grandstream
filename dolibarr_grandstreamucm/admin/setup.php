<?php
/* Copyright (C) 2024 Your Company
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../main.inc.php")) {
    $res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}
if (!$res) {
    die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/grandstreamucm/lib/grandstreamucm.lib.php');
dol_include_once('/grandstreamucm/class/grandstreamucm.class.php');

// Load translation files
$langs->loadLangs(array("admin", "grandstreamucm@grandstreamucm"));

// Access control
if (!$user->admin) {
    accessforbidden();
}

// Parameters
$action = GETPOST('action', 'aZ09');

$error = 0;

/*
 * Actions
 */

if ($action == 'update') {
    $error = 0;

    // Connection settings
    if (!$error) {
        $result = dolibarr_set_const($db, 'GRANDSTREAMUCM_HOST', GETPOST('GRANDSTREAMUCM_HOST', 'alpha'), 'chaine', 0, '', $conf->entity);
        if ($result < 0) {
            $error++;
        }
    }
    if (!$error) {
        $result = dolibarr_set_const($db, 'GRANDSTREAMUCM_PORT', GETPOST('GRANDSTREAMUCM_PORT', 'int'), 'chaine', 0, '', $conf->entity);
        if ($result < 0) {
            $error++;
        }
    }
    if (!$error) {
        $result = dolibarr_set_const($db, 'GRANDSTREAMUCM_USE_HTTPS', GETPOST('GRANDSTREAMUCM_USE_HTTPS', 'int'), 'chaine', 0, '', $conf->entity);
        if ($result < 0) {
            $error++;
        }
    }
    if (!$error) {
        $result = dolibarr_set_const($db, 'GRANDSTREAMUCM_USERNAME', GETPOST('GRANDSTREAMUCM_USERNAME', 'alpha'), 'chaine', 0, '', $conf->entity);
        if ($result < 0) {
            $error++;
        }
    }
    if (!$error) {
        $password = GETPOST('GRANDSTREAMUCM_PASSWORD', 'none');
        if (!empty($password)) {
            $result = dolibarr_set_const($db, 'GRANDSTREAMUCM_PASSWORD', $password, 'chaine', 0, '', $conf->entity);
            if ($result < 0) {
                $error++;
            }
        }
    }

    // Sync settings
    if (!$error) {
        $result = dolibarr_set_const($db, 'GRANDSTREAMUCM_SYNC_INTERVAL', GETPOST('GRANDSTREAMUCM_SYNC_INTERVAL', 'int'), 'chaine', 0, '', $conf->entity);
        if ($result < 0) {
            $error++;
        }
    }
    if (!$error) {
        $result = dolibarr_set_const($db, 'GRANDSTREAMUCM_DAYS_TO_SYNC', GETPOST('GRANDSTREAMUCM_DAYS_TO_SYNC', 'int'), 'chaine', 0, '', $conf->entity);
        if ($result < 0) {
            $error++;
        }
    }
    if (!$error) {
        $result = dolibarr_set_const($db, 'GRANDSTREAMUCM_DOWNLOAD_RECORDINGS', GETPOST('GRANDSTREAMUCM_DOWNLOAD_RECORDINGS', 'int'), 'chaine', 0, '', $conf->entity);
        if ($result < 0) {
            $error++;
        }
    }
    if (!$error) {
        $result = dolibarr_set_const($db, 'GRANDSTREAMUCM_AUTO_CREATE_CONTACTS', GETPOST('GRANDSTREAMUCM_AUTO_CREATE_CONTACTS', 'int'), 'chaine', 0, '', $conf->entity);
        if ($result < 0) {
            $error++;
        }
    }
    if (!$error) {
        $result = dolibarr_set_const($db, 'GRANDSTREAMUCM_VERIFY_SSL', GETPOST('GRANDSTREAMUCM_VERIFY_SSL', 'int'), 'chaine', 0, '', $conf->entity);
        if ($result < 0) {
            $error++;
        }
    }

    if (!$error) {
        setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
    } else {
        setEventMessages($langs->trans("Error"), null, 'errors');
    }
}

if ($action == 'test') {
    $ucm = new GrandstreamUCM($db);

    if ($ucm->testConnection()) {
        setEventMessages($langs->trans("ConnectionSuccess"), null, 'mesgs');
    } else {
        setEventMessages($langs->trans("ConnectionFailed").': '.$ucm->error, null, 'errors');
    }
}

if ($action == 'sync') {
    $ucm = new GrandstreamUCM($db);

    $result = $ucm->syncCalls($user);

    if ($result >= 0) {
        setEventMessages($langs->trans("SyncCompleted").': '.$result.' '.$langs->trans("CallLogs"), null, 'mesgs');
    } else {
        setEventMessages($langs->trans("SyncFailed").': '.$ucm->error, null, 'errors');
    }
}

/*
 * View
 */

$page_name = "GrandstreamUCMSetup";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

// Configuration tabs
$head = grandstreamucmAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans("ModuleGrandstreamUCMName"), -1, 'phone');

// Help box - Guide de configuration
print '<div class="info" style="margin-bottom: 15px;">';
print '<strong><i class="fa fa-info-circle"></i> Guide de configuration :</strong>';
print '<ol style="margin: 10px 0;">';
print '<li>Connectez-vous à l\'interface web de votre UCM (ex: https://192.168.1.100:8089)</li>';
print '<li>Allez dans <strong>Valeur &gt; CDR &gt; CDR API</strong> et activez l\'API</li>';
print '<li>Créez un utilisateur API dans <strong>Maintenance &gt; Utilisateur API</strong></li>';
print '<li>Renseignez les informations ci-dessous puis cliquez sur "Tester la connexion"</li>';
print '</ol>';
print '</div>';

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

print '<table class="noborder centpercent">';

// Connection Settings
print '<tr class="liste_titre"><td colspan="3">'.$langs->trans("ConnectionSettings").'</td></tr>';

// UCM Host
print '<tr class="oddeven"><td class="titlefield">';
print $langs->trans("UCMHost");
print '</td><td>';
print '<input type="text" class="flat minwidth300" name="GRANDSTREAMUCM_HOST" value="'.dol_escape_htmltag($conf->global->GRANDSTREAMUCM_HOST).'" placeholder="Ex: 192.168.1.100">';
print '</td><td class="opacitymedium small">Adresse IP ou nom d\'hôte de votre UCM<br>Exemples : 192.168.1.100, ucm.monentreprise.com</td></tr>';

// UCM Port
print '<tr class="oddeven"><td>';
print $langs->trans("UCMPort").'</td><td>';
print '<input type="number" class="flat" name="GRANDSTREAMUCM_PORT" value="'.($conf->global->GRANDSTREAMUCM_PORT ?: '8089').'" min="1" max="65535">';
print '</td><td class="opacitymedium small">Port API du UCM<br>Par défaut : 8089 (HTTPS) ou 8088 (HTTP)</td></tr>';

// Use HTTPS
print '<tr class="oddeven"><td>';
print $langs->trans("UseHTTPS").'</td><td>';
print '<input type="checkbox" name="GRANDSTREAMUCM_USE_HTTPS" value="1"'.($conf->global->GRANDSTREAMUCM_USE_HTTPS ? ' checked' : '').'>';
print '</td><td class="opacitymedium small">Recommandé pour une connexion sécurisée</td></tr>';

// Verify SSL
print '<tr class="oddeven"><td>';
print 'Vérifier le certificat SSL</td><td>';
print '<input type="checkbox" name="GRANDSTREAMUCM_VERIFY_SSL" value="1"'.(!empty($conf->global->GRANDSTREAMUCM_VERIFY_SSL) ? ' checked' : '').'>';
print '</td><td class="opacitymedium small">Désactivez si votre UCM utilise un certificat auto-signé</td></tr>';

// Username
print '<tr class="oddeven"><td>';
print $langs->trans("APIUsername").'</td><td>';
print '<input type="text" class="flat minwidth200" name="GRANDSTREAMUCM_USERNAME" value="'.dol_escape_htmltag($conf->global->GRANDSTREAMUCM_USERNAME).'" placeholder="Ex: api_user">';
print '</td><td class="opacitymedium small">Créé dans UCM > Maintenance > Utilisateur API</td></tr>';

// Password
print '<tr class="oddeven"><td>';
print $langs->trans("APIPassword").'</td><td>';
print '<input type="password" class="flat minwidth200" name="GRANDSTREAMUCM_PASSWORD" placeholder="'.($conf->global->GRANDSTREAMUCM_PASSWORD ? '********' : '').'">';
print '</td><td class="opacitymedium small">Mot de passe de l\'utilisateur API</td></tr>';

// Sync Settings
print '<tr class="liste_titre"><td colspan="3">'.$langs->trans("SyncSettings").'</td></tr>';

// Sync Interval
print '<tr class="oddeven"><td>';
print $langs->trans("SyncInterval").'</td><td>';
print '<input type="number" class="flat" name="GRANDSTREAMUCM_SYNC_INTERVAL" value="'.($conf->global->GRANDSTREAMUCM_SYNC_INTERVAL ?: '15').'" min="1" max="1440">';
print ' '.$langs->trans("Minutes");
print '</td><td class="opacitymedium small">Intervalle entre les synchronisations<br>Recommandé : 15 min (standard), 5 min (temps réel)</td></tr>';

// Days to Sync
print '<tr class="oddeven"><td>';
print $langs->trans("DaysToSync").'</td><td>';
print '<input type="number" class="flat" name="GRANDSTREAMUCM_DAYS_TO_SYNC" value="'.($conf->global->GRANDSTREAMUCM_DAYS_TO_SYNC ?: '30').'" min="1" max="365">';
print ' '.$langs->trans("Days");
print '</td><td class="opacitymedium small">Nombre de jours d\'historique à récupérer<br>Recommandé : 30 jours</td></tr>';

// Download Recordings
print '<tr class="oddeven"><td>';
print $langs->trans("DownloadRecordings").'</td><td>';
print '<input type="checkbox" name="GRANDSTREAMUCM_DOWNLOAD_RECORDINGS" value="1"'.($conf->global->GRANDSTREAMUCM_DOWNLOAD_RECORDINGS ? ' checked' : '').'>';
print '</td><td class="opacitymedium small">Télécharge les enregistrements d\'appels<br>Attention : nécessite plus d\'espace disque</td></tr>';

// Auto Create Contacts
print '<tr class="oddeven"><td>';
print $langs->trans("AutoCreateContacts").'</td><td>';
print '<input type="checkbox" name="GRANDSTREAMUCM_AUTO_CREATE_CONTACTS" value="1"'.($conf->global->GRANDSTREAMUCM_AUTO_CREATE_CONTACTS ? ' checked' : '').'>';
print '</td><td class="opacitymedium small">Crée automatiquement un tiers pour chaque numéro inconnu</td></tr>';

// Sync Status
print '<tr class="liste_titre"><td colspan="3">Statut de synchronisation</td></tr>';

// Last Sync
print '<tr class="oddeven"><td>';
print $langs->trans("LastSync").'</td><td>';
if (!empty($conf->global->GRANDSTREAMUCM_LAST_SYNC)) {
    print dol_print_date(strtotime($conf->global->GRANDSTREAMUCM_LAST_SYNC), 'dayhour');
} else {
    print '<span class="opacitymedium">Jamais synchronisé</span>';
}
print '</td><td></td></tr>';

// Last Sync Status
if (!empty($conf->global->GRANDSTREAMUCM_LAST_SYNC_STATUS)) {
    $statusClass = '';
    $statusLabel = '';
    switch ($conf->global->GRANDSTREAMUCM_LAST_SYNC_STATUS) {
        case 'success':
            $statusClass = 'badge badge-status4';
            $statusLabel = 'Succès';
            break;
        case 'partial':
            $statusClass = 'badge badge-status1';
            $statusLabel = 'Partiel';
            break;
        case 'failed':
            $statusClass = 'badge badge-status8';
            $statusLabel = 'Échoué';
            break;
    }
    print '<tr class="oddeven"><td>';
    print 'Statut</td><td>';
    print '<span class="'.$statusClass.'">'.$statusLabel.'</span>';
    print '</td><td></td></tr>';
}

print '</table>';

print '<br>';

// Save button
print '<div class="center">';
print '<input type="submit" class="button button-save" value="'.$langs->trans("Save").'">';
print '</div>';

print '</form>';

print '<br>';

// Action buttons
print '<div class="center">';
print '<a class="button" href="'.$_SERVER["PHP_SELF"].'?action=test&token='.newToken().'"><i class="fa fa-plug"></i> '.$langs->trans("TestConnection").'</a>';
print '&nbsp;&nbsp;';
print '<a class="button" href="'.$_SERVER["PHP_SELF"].'?action=sync&token='.newToken().'"><i class="fa fa-refresh"></i> '.$langs->trans("SyncNow").'</a>';
print '</div>';

print '<br>';

// Example configurations
print '<div class="fichecenter">';
print '<div class="fichehalfleft">';
print '<div class="underbanner clearboth"></div>';
print '<table class="border centpercent tableforfield">';
print '<tr class="liste_titre"><td colspan="2"><i class="fa fa-building"></i> Configuration type - Petite entreprise</td></tr>';
print '<tr class="oddeven"><td>Hôte UCM</td><td><strong>192.168.1.100</strong></td></tr>';
print '<tr class="oddeven"><td>Port</td><td><strong>8089</strong></td></tr>';
print '<tr class="oddeven"><td>HTTPS</td><td><strong>Oui</strong></td></tr>';
print '<tr class="oddeven"><td>Vérifier SSL</td><td><strong>Non</strong> (certificat auto-signé)</td></tr>';
print '<tr class="oddeven"><td>Intervalle sync</td><td><strong>15 minutes</strong></td></tr>';
print '<tr class="oddeven"><td>Jours à sync</td><td><strong>30 jours</strong></td></tr>';
print '</table>';
print '</div>';

print '<div class="fichehalfright">';
print '<div class="underbanner clearboth"></div>';
print '<table class="border centpercent tableforfield">';
print '<tr class="liste_titre"><td colspan="2"><i class="fa fa-building-o"></i> Configuration type - Multi-sites</td></tr>';
print '<tr class="oddeven"><td>Hôte UCM</td><td><strong>ucm-paris.monentreprise.local</strong></td></tr>';
print '<tr class="oddeven"><td>Port</td><td><strong>8089</strong></td></tr>';
print '<tr class="oddeven"><td>HTTPS</td><td><strong>Oui</strong></td></tr>';
print '<tr class="oddeven"><td>Vérifier SSL</td><td><strong>Oui</strong> (certificat valide)</td></tr>';
print '<tr class="oddeven"><td>Intervalle sync</td><td><strong>5 minutes</strong></td></tr>';
print '<tr class="oddeven"><td>Jours à sync</td><td><strong>30 jours</strong></td></tr>';
print '</table>';
print '</div>';
print '</div>';

print '<div class="clearboth"></div><br>';

// Troubleshooting section
print '<div class="warning" style="margin-top: 15px;">';
print '<strong><i class="fa fa-exclamation-triangle"></i> Dépannage des erreurs courantes :</strong>';
print '<ul style="margin: 10px 0;">';
print '<li><strong>"Connexion refusée"</strong> : Vérifiez l\'adresse IP, le port et que le pare-feu autorise la connexion</li>';
print '<li><strong>"Authentification échouée"</strong> : Vérifiez les identifiants API dans UCM > Maintenance > Utilisateur API</li>';
print '<li><strong>"Erreur SSL"</strong> : Désactivez "Vérifier SSL" si vous utilisez un certificat auto-signé</li>';
print '<li><strong>"Timeout"</strong> : Réduisez le nombre de jours à synchroniser ou augmentez l\'intervalle</li>';
print '</ul>';
print '</div>';

print dol_get_fiche_end();

// End of page
llxFooter();
$db->close();
