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
if (!$res) {
    die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
dol_include_once('/grandstreamucm/class/calllog.class.php');

// Load translation files
$langs->loadLangs(array("grandstreamucm@grandstreamucm", "companies"));

// Get parameters
$id = GETPOST('id', 'int');
$socid = GETPOST('socid', 'int');
$action = GETPOST('action', 'aZ09');

if ($id > 0) {
    $socid = $id;
}

// Security check
if (!$user->rights->grandstreamucm->read) {
    accessforbidden();
}
if (!$user->rights->societe->lire) {
    accessforbidden();
}

// Initialize objects
$object = new Societe($db);
$form = new Form($db);
$calllog = new CallLog($db);

// Fetch third party
if ($socid > 0) {
    $object->fetch($socid);
}

/*
 * View
 */

$title = $langs->trans("CallsForThirdParty").' - '.$object->name;
$help_url = '';

llxHeader('', $title, $help_url);

if ($object->id > 0) {
    $head = societe_prepare_head($object);

    print dol_get_fiche_head($head, 'grandstreamucm', $langs->trans("ThirdParty"), -1, 'company');

    // Linkback
    $linkback = '<a href="'.DOL_URL_ROOT.'/societe/list.php?restore_lastsearch_values=1">'.$langs->trans("BackToList").'</a>';

    dol_banner_tab($object, 'socid', $linkback, ($user->socid ? 0 : 1), 'rowid', 'nom');

    print '<div class="fichecenter">';
    print '<div class="underbanner clearboth"></div>';

    // Call statistics
    $stats = $calllog->getStatsBySociete($object->id);

    print '<div class="div-table-responsive-no-min">';
    print '<table class="border centpercent tableforfield">';

    print '<tr class="liste_titre"><td colspan="4">'.$langs->trans("CallStatistics").'</td></tr>';

    // Row 1
    print '<tr class="oddeven">';
    print '<td class="titlefield">'.$langs->trans("TotalCalls").'</td>';
    print '<td><strong>'.$stats['total'].'</strong></td>';
    print '<td>'.$langs->trans("AnsweredCalls").'</td>';
    print '<td><span class="badge badge-success">'.$stats['answered'].'</span></td>';
    print '</tr>';

    // Row 2
    print '<tr class="oddeven">';
    print '<td>'.$langs->trans("InboundCalls").'</td>';
    print '<td><span class="badge badge-info">'.$stats['inbound'].'</span></td>';
    print '<td>'.$langs->trans("MissedCalls").'</td>';
    print '<td><span class="badge badge-danger">'.$stats['missed'].'</span></td>';
    print '</tr>';

    // Row 3
    print '<tr class="oddeven">';
    print '<td>'.$langs->trans("OutboundCalls").'</td>';
    print '<td><span class="badge badge-success">'.$stats['outbound'].'</span></td>';
    print '<td>'.$langs->trans("TotalTalkTime").'</td>';
    print '<td><strong>'.CallLog::formatTotalDuration($stats['total_duration']).'</strong></td>';
    print '</tr>';

    // Last call
    if ($stats['last_call']) {
        print '<tr class="oddeven">';
        print '<td>'.$langs->trans("LastCall").'</td>';
        print '<td colspan="3">'.dol_print_date($db->jdate($stats['last_call']), 'dayhour').'</td>';
        print '</tr>';
    }

    print '</table>';
    print '</div>';

    print '</div>';

    print dol_get_fiche_end();

    // List of calls
    print '<br>';

    print '<div class="div-table-responsive">';
    print '<table class="tagtable nobottomiftotal liste">';

    // Header
    print '<tr class="liste_titre">';
    print '<th>'.$langs->trans("Ref").'</th>';
    print '<th class="center">'.$langs->trans("CallDate").'</th>';
    print '<th>'.$langs->trans("Direction").'</th>';
    print '<th>'.$langs->trans("CallType").'</th>';
    print '<th>'.$langs->trans("CallerNumber").'</th>';
    print '<th>'.$langs->trans("CalledNumber").'</th>';
    print '<th class="right">'.$langs->trans("Duration").'</th>';
    print '<th class="center">'.$langs->trans("HasRecording").'</th>';
    print '<th>'.$langs->trans("Notes").'</th>';
    print '<th></th>';
    print '</tr>';

    // Fetch calls for this third party
    $filter = array('t.fk_soc' => $object->id);
    $calls = $calllog->fetchAll('DESC', 'call_date', 100, 0, $filter);

    if (is_array($calls) && count($calls) > 0) {
        foreach ($calls as $call) {
            print '<tr class="oddeven">';

            // Ref
            print '<td class="nowraponall">';
            print '<a href="'.dol_buildpath('/grandstreamucm/call_card.php', 1).'?id='.$call->id.'">'.$call->ref.'</a>';
            print '</td>';

            // Date
            print '<td class="center nowraponall">'.dol_print_date($db->jdate($call->call_date), 'dayhour').'</td>';

            // Direction
            print '<td>'.$call->getDirectionBadge().'</td>';

            // Type
            print '<td>'.$call->getCallTypeBadge().'</td>';

            // Caller
            print '<td class="tdoverflowmax100">';
            print dol_escape_htmltag($call->caller_number);
            if ($call->caller_name) {
                print '<br><span class="opacitymedium small">'.dol_escape_htmltag($call->caller_name).'</span>';
            }
            print '</td>';

            // Called
            print '<td class="tdoverflowmax100">';
            print dol_escape_htmltag($call->called_number);
            if ($call->called_name) {
                print '<br><span class="opacitymedium small">'.dol_escape_htmltag($call->called_name).'</span>';
            }
            print '</td>';

            // Duration
            print '<td class="right nowraponall">'.$call->getFormattedDuration().'</td>';

            // Recording
            print '<td class="center">';
            if ($call->has_recording) {
                print '<span class="badge badge-success">Oui</span>';
                if ($call->recording_file) {
                    $recordingPath = $conf->grandstreamucm->dir_output.'/recordings/'.$call->recording_file;
                    if (file_exists($recordingPath)) {
                        print ' <a href="'.DOL_URL_ROOT.'/document.php?modulepart=grandstreamucm&file=recordings/'.$call->recording_file.'" target="_blank">';
                        print img_picto($langs->trans("DownloadRecording"), 'download');
                        print '</a>';
                    }
                }
            } else {
                print '<span class="badge badge-secondary">Non</span>';
            }
            print '</td>';

            // Notes
            print '<td class="tdoverflowmax200">';
            if ($call->note_private) {
                print '<span title="'.dol_escape_htmltag(dol_string_nohtmltag($call->note_private)).'">';
                print dol_trunc(dol_string_nohtmltag($call->note_private), 30);
                print '</span>';
            }
            print '</td>';

            // Actions
            print '<td class="nowrap center">';
            print '<a href="'.dol_buildpath('/grandstreamucm/call_card.php', 1).'?id='.$call->id.'">';
            print img_picto($langs->trans("View"), 'eye');
            print '</a>';
            print '</td>';

            print '</tr>';
        }
    } else {
        print '<tr class="oddeven"><td colspan="10" class="opacitymedium">'.$langs->trans("NoCallsFound").'</td></tr>';
    }

    print '</table>';
    print '</div>';

    // Action buttons
    print '<div class="tabsAction">';
    print '<a class="butAction" href="'.dol_buildpath('/grandstreamucm/call_list.php', 1).'?socid='.$object->id.'">'.$langs->trans("ViewAllCalls").'</a>';
    print '</div>';

} else {
    dol_print_error($db, 'Third party not found');
}

// End of page
llxFooter();
$db->close();
