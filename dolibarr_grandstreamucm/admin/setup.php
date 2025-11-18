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

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

print '<table class="noborder centpercent">';

// Connection Settings
print '<tr class="liste_titre"><td colspan="3">'.$langs->trans("ConnectionSettings").'</td></tr>';

// UCM Host
print '<tr class="oddeven"><td class="titlefield">';
print $langs->trans("UCMHost").'</td><td>';
print '<input type="text" class="flat minwidth300" name="GRANDSTREAMUCM_HOST" value="'.dol_escape_htmltag($conf->global->GRANDSTREAMUCM_HOST).'" placeholder="192.168.1.100">';
print '</td><td></td></tr>';

// UCM Port
print '<tr class="oddeven"><td>';
print $langs->trans("UCMPort").'</td><td>';
print '<input type="number" class="flat" name="GRANDSTREAMUCM_PORT" value="'.($conf->global->GRANDSTREAMUCM_PORT ?: '8089').'" min="1" max="65535">';
print '</td><td></td></tr>';

// Use HTTPS
print '<tr class="oddeven"><td>';
print $langs->trans("UseHTTPS").'</td><td>';
print '<input type="checkbox" name="GRANDSTREAMUCM_USE_HTTPS" value="1"'.($conf->global->GRANDSTREAMUCM_USE_HTTPS ? ' checked' : '').'>';
print '</td><td></td></tr>';

// Username
print '<tr class="oddeven"><td>';
print $langs->trans("APIUsername").'</td><td>';
print '<input type="text" class="flat minwidth200" name="GRANDSTREAMUCM_USERNAME" value="'.dol_escape_htmltag($conf->global->GRANDSTREAMUCM_USERNAME).'">';
print '</td><td></td></tr>';

// Password
print '<tr class="oddeven"><td>';
print $langs->trans("APIPassword").'</td><td>';
print '<input type="password" class="flat minwidth200" name="GRANDSTREAMUCM_PASSWORD" placeholder="'.($conf->global->GRANDSTREAMUCM_PASSWORD ? '********' : '').'">';
print '</td><td></td></tr>';

// Sync Settings
print '<tr class="liste_titre"><td colspan="3">'.$langs->trans("SyncSettings").'</td></tr>';

// Sync Interval
print '<tr class="oddeven"><td>';
print $langs->trans("SyncInterval").'</td><td>';
print '<input type="number" class="flat" name="GRANDSTREAMUCM_SYNC_INTERVAL" value="'.($conf->global->GRANDSTREAMUCM_SYNC_INTERVAL ?: '15').'" min="1" max="1440">';
print ' '.$langs->trans("Minutes");
print '</td><td></td></tr>';

// Days to Sync
print '<tr class="oddeven"><td>';
print $langs->trans("DaysToSync").'</td><td>';
print '<input type="number" class="flat" name="GRANDSTREAMUCM_DAYS_TO_SYNC" value="'.($conf->global->GRANDSTREAMUCM_DAYS_TO_SYNC ?: '30').'" min="1" max="365">';
print ' '.$langs->trans("Days");
print '</td><td></td></tr>';

// Download Recordings
print '<tr class="oddeven"><td>';
print $langs->trans("DownloadRecordings").'</td><td>';
print '<input type="checkbox" name="GRANDSTREAMUCM_DOWNLOAD_RECORDINGS" value="1"'.($conf->global->GRANDSTREAMUCM_DOWNLOAD_RECORDINGS ? ' checked' : '').'>';
print '</td><td></td></tr>';

// Auto Create Contacts
print '<tr class="oddeven"><td>';
print $langs->trans("AutoCreateContacts").'</td><td>';
print '<input type="checkbox" name="GRANDSTREAMUCM_AUTO_CREATE_CONTACTS" value="1"'.($conf->global->GRANDSTREAMUCM_AUTO_CREATE_CONTACTS ? ' checked' : '').'>';
print '</td><td></td></tr>';

// Last Sync
if (!empty($conf->global->GRANDSTREAMUCM_LAST_SYNC)) {
    print '<tr class="oddeven"><td>';
    print $langs->trans("LastSync").'</td><td>';
    print dol_print_date(strtotime($conf->global->GRANDSTREAMUCM_LAST_SYNC), 'dayhour');
    print '</td><td></td></tr>';
}

print '</table>';

print '<br>';

// Save button
print '<div class="center">';
print '<input type="submit" class="button" value="'.$langs->trans("Save").'">';
print '</div>';

print '</form>';

print '<br>';

// Action buttons
print '<div class="center">';
print '<a class="button" href="'.$_SERVER["PHP_SELF"].'?action=test&token='.newToken().'">'.$langs->trans("TestConnection").'</a>';
print '&nbsp;&nbsp;';
print '<a class="button" href="'.$_SERVER["PHP_SELF"].'?action=sync&token='.newToken().'">'.$langs->trans("SyncNow").'</a>';
print '</div>';

print dol_get_fiche_end();

// End of page
llxFooter();
$db->close();
