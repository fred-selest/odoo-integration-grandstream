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
dol_include_once('/grandstreamucm/class/updatemanager.class.php');

// Load translation files
$langs->loadLangs(array("admin", "grandstreamucm@grandstreamucm"));

// Access control
if (!$user->admin) {
    accessforbidden();
}

// Parameters
$action = GETPOST('action', 'aZ09');

// Initialize update manager
$updateManager = new GrandstreamUpdateManager($db);

/*
 * Actions
 */

if ($action == 'check') {
    $updateInfo = $updateManager->checkForUpdates();

    if ($updateInfo === false) {
        setEventMessages($updateManager->error, null, 'errors');
    }
}

if ($action == 'install') {
    $downloadUrl = GETPOST('download_url', 'alpha');

    if ($updateManager->downloadAndInstall($downloadUrl)) {
        setEventMessages('Mise à jour installée avec succès. Veuillez vider le cache et recharger la page.', null, 'mesgs');
    } else {
        setEventMessages($updateManager->error, null, 'errors');
    }
}

/*
 * View
 */

$page_name = "Mises à jour";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

// Configuration tabs
$head = grandstreamucmAdminPrepareHead();
$head[] = array(dol_buildpath("/grandstreamucm/admin/updates.php", 1), 'Mises à jour', 'updates');

print dol_get_fiche_head($head, 'updates', $langs->trans("ModuleGrandstreamUCMName"), -1, 'phone');

// Current version info
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';

print '<tr class="liste_titre"><td colspan="2">Informations de version</td></tr>';

print '<tr class="oddeven">';
print '<td class="titlefield">Version actuelle</td>';
print '<td><strong>'.$updateManager->currentVersion.'</strong></td>';
print '</tr>';

// Last check
$lastCheck = $conf->global->GRANDSTREAMUCM_LAST_UPDATE_CHECK;
if ($lastCheck) {
    print '<tr class="oddeven">';
    print '<td>Dernière vérification</td>';
    print '<td>'.dol_print_date(strtotime($lastCheck), 'dayhour').'</td>';
    print '</tr>';
}

print '</table>';
print '</div>';

print '<br>';

// Check for updates button
print '<div class="center">';
print '<a class="button" href="'.$_SERVER["PHP_SELF"].'?action=check&token='.newToken().'">Vérifier les mises à jour</a>';
print '</div>';

print '<br>';

// Display update info if we just checked
if ($action == 'check' && isset($updateInfo) && is_array($updateInfo)) {
    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';

    print '<tr class="liste_titre"><td colspan="2">Résultat de la vérification</td></tr>';

    print '<tr class="oddeven">';
    print '<td class="titlefield">Dernière version disponible</td>';
    print '<td><strong>'.$updateInfo['latest_version'].'</strong></td>';
    print '</tr>';

    if (!empty($updateInfo['release_date'])) {
        print '<tr class="oddeven">';
        print '<td>Date de publication</td>';
        print '<td>'.$updateInfo['release_date'].'</td>';
        print '</tr>';
    }

    print '<tr class="oddeven">';
    print '<td>Mise à jour disponible</td>';
    print '<td>';
    if ($updateInfo['update_available']) {
        print '<span class="badge badge-warning">Oui - Nouvelle version disponible</span>';
    } else {
        print '<span class="badge badge-success">Non - Vous êtes à jour</span>';
    }
    print '</td>';
    print '</tr>';

    print '</table>';
    print '</div>';

    // Show release notes
    if (!empty($updateInfo['release_notes'])) {
        print '<br>';
        print '<div class="div-table-responsive-no-min">';
        print '<table class="noborder centpercent">';
        print '<tr class="liste_titre"><td>Notes de version</td></tr>';
        print '<tr class="oddeven"><td>';
        print '<pre style="white-space: pre-wrap; font-family: inherit;">'.dol_escape_htmltag($updateInfo['release_notes']).'</pre>';
        print '</td></tr>';
        print '</table>';
        print '</div>';
    }

    // Install button if update available
    if ($updateInfo['update_available']) {
        print '<br>';
        print '<div class="center">';
        print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
        print '<input type="hidden" name="token" value="'.newToken().'">';
        print '<input type="hidden" name="action" value="install">';
        print '<input type="hidden" name="download_url" value="'.dol_escape_htmltag($updateInfo['download_url']).'">';

        print '<div class="warning">';
        print '<strong>Attention :</strong> La mise à jour va télécharger et remplacer les fichiers du module. ';
        print 'Assurez-vous d\'avoir une sauvegarde avant de continuer.';
        print '</div>';

        print '<br>';
        print '<input type="submit" class="button" value="Télécharger et installer la mise à jour" ';
        print 'onclick="return confirm(\'Êtes-vous sûr de vouloir installer la mise à jour ?\');">';
        print '</form>';

        // Manual download link
        print '<br><br>';
        print '<a href="'.$updateInfo['html_url'].'" target="_blank">Voir la release sur GitHub</a>';

        print '</div>';
    }
}

// Show update available notification if stored
$storedUpdate = $conf->global->GRANDSTREAMUCM_UPDATE_AVAILABLE;
if ($storedUpdate && $action != 'check') {
    print '<div class="warning">';
    print '<strong>Mise à jour disponible !</strong><br>';
    print 'La version '.$storedUpdate.' est disponible. Cliquez sur "Vérifier les mises à jour" pour plus de détails.';
    print '</div>';
}

print dol_get_fiche_end();

// End of page
llxFooter();
$db->close();
