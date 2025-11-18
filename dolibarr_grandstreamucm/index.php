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

dol_include_once('/grandstreamucm/class/calllog.class.php');

// Load translation files
$langs->loadLangs(array("grandstreamucm@grandstreamucm"));

// Security check
if (!$user->rights->grandstreamucm->read) {
    accessforbidden();
}

/*
 * View
 */

$title = $langs->trans("ModuleGrandstreamUCMName");
$help_url = '';

llxHeader('', $title, $help_url);

print load_fiche_titre($langs->trans("ModuleGrandstreamUCMName"), '', 'phone');

// Statistics
$calllog = new CallLog($db);

// Get global statistics
$sql = "SELECT COUNT(*) as total,";
$sql .= " SUM(CASE WHEN direction = 'inbound' THEN 1 ELSE 0 END) as inbound,";
$sql .= " SUM(CASE WHEN direction = 'outbound' THEN 1 ELSE 0 END) as outbound,";
$sql .= " SUM(CASE WHEN call_type = 'missed' THEN 1 ELSE 0 END) as missed,";
$sql .= " SUM(CASE WHEN call_type = 'answered' THEN 1 ELSE 0 END) as answered,";
$sql .= " SUM(COALESCE(talk_duration, duration, 0)) as total_duration";
$sql .= " FROM ".MAIN_DB_PREFIX."grandstreamucm_calllog";
$sql .= " WHERE entity IN (".getEntity('calllog').")";

$resql = $db->query($sql);
$stats = array(
    'total' => 0,
    'inbound' => 0,
    'outbound' => 0,
    'missed' => 0,
    'answered' => 0,
    'total_duration' => 0
);

if ($resql) {
    $obj = $db->fetch_object($resql);
    if ($obj) {
        $stats['total'] = $obj->total;
        $stats['inbound'] = $obj->inbound;
        $stats['outbound'] = $obj->outbound;
        $stats['missed'] = $obj->missed;
        $stats['answered'] = $obj->answered;
        $stats['total_duration'] = $obj->total_duration;
    }
}

// Statistics boxes
print '<div class="fichecenter">';

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder nohover centpercent">';

print '<tr class="liste_titre">';
print '<th colspan="4">'.$langs->trans("Statistics").'</th>';
print '</tr>';

// Row 1
print '<tr class="oddeven">';
print '<td class="titlefield"><strong>'.$langs->trans("TotalCalls").'</strong></td>';
print '<td><span class="badge badge-primary" style="font-size: 1.2em;">'.$stats['total'].'</span></td>';
print '<td>'.$langs->trans("TotalTalkTime").'</td>';
print '<td><strong>'.CallLog::formatTotalDuration($stats['total_duration']).'</strong></td>';
print '</tr>';

// Row 2
print '<tr class="oddeven">';
print '<td>'.$langs->trans("InboundCalls").'</td>';
print '<td><span class="badge badge-info">'.$stats['inbound'].'</span></td>';
print '<td>'.$langs->trans("OutboundCalls").'</td>';
print '<td><span class="badge badge-success">'.$stats['outbound'].'</span></td>';
print '</tr>';

// Row 3
print '<tr class="oddeven">';
print '<td>'.$langs->trans("AnsweredCalls").'</td>';
print '<td><span class="badge badge-success">'.$stats['answered'].'</span></td>';
print '<td>'.$langs->trans("MissedCalls").'</td>';
print '<td><span class="badge badge-danger">'.$stats['missed'].'</span></td>';
print '</tr>';

// Last sync
if (!empty($conf->global->GRANDSTREAMUCM_LAST_SYNC)) {
    print '<tr class="oddeven">';
    print '<td>'.$langs->trans("LastSync").'</td>';
    print '<td colspan="3">'.dol_print_date(strtotime($conf->global->GRANDSTREAMUCM_LAST_SYNC), 'dayhour').'</td>';
    print '</tr>';
}

print '</table>';
print '</div>';

print '</div>';

print '<br>';

// Recent calls
print '<div class="div-table-responsive">';
print '<table class="tagtable nobottomiftotal liste">';

print '<tr class="liste_titre">';
print '<th colspan="8">'.$langs->trans("RecentCalls").'</th>';
print '</tr>';

print '<tr class="liste_titre">';
print '<th>'.$langs->trans("Ref").'</th>';
print '<th class="center">'.$langs->trans("CallDate").'</th>';
print '<th>'.$langs->trans("Direction").'</th>';
print '<th>'.$langs->trans("CallType").'</th>';
print '<th>'.$langs->trans("CallerNumber").'</th>';
print '<th>'.$langs->trans("CalledNumber").'</th>';
print '<th class="right">'.$langs->trans("Duration").'</th>';
print '<th>'.$langs->trans("ThirdParty").'</th>';
print '</tr>';

// Fetch recent calls
$calls = $calllog->fetchAll('DESC', 'call_date', 10);

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
        print '<td class="tdoverflowmax100">'.dol_escape_htmltag($call->caller_number).'</td>';

        // Called
        print '<td class="tdoverflowmax100">'.dol_escape_htmltag($call->called_number).'</td>';

        // Duration
        print '<td class="right nowraponall">'.$call->getFormattedDuration().'</td>';

        // Third party
        print '<td class="tdoverflowmax150">';
        if ($call->fk_soc > 0) {
            $societe = new Societe($db);
            $societe->fetch($call->fk_soc);
            print $societe->getNomUrl(1);
        }
        print '</td>';

        print '</tr>';
    }
} else {
    print '<tr class="oddeven"><td colspan="8" class="opacitymedium">'.$langs->trans("NoCallsFound").'</td></tr>';
}

print '</table>';
print '</div>';

// Action buttons
print '<div class="tabsAction">';
print '<a class="butAction" href="'.dol_buildpath('/grandstreamucm/call_list.php', 1).'">'.$langs->trans("ViewAllCalls").'</a>';
if ($user->admin) {
    print '<a class="butAction" href="'.dol_buildpath('/grandstreamucm/admin/setup.php', 1).'">'.$langs->trans("Configuration").'</a>';
}
print '</div>';

// End of page
llxFooter();
$db->close();
