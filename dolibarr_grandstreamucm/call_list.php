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

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
dol_include_once('/grandstreamucm/class/calllog.class.php');

// Load translation files
$langs->loadLangs(array("grandstreamucm@grandstreamucm", "other"));

// Get parameters
$action = GETPOST('action', 'aZ09');
$massaction = GETPOST('massaction', 'alpha');
$show_files = GETPOST('show_files', 'int');
$confirm = GETPOST('confirm', 'alpha');
$toselect = GETPOST('toselect', 'array');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'callloglist';

// Security check
if (!$user->rights->grandstreamucm->read) {
    accessforbidden();
}

// List parameters
$socid = GETPOST('socid', 'int');
$search_all = trim(GETPOST('search_all', 'alphanohtml'));
$search_ref = GETPOST('search_ref', 'alpha');
$search_caller = GETPOST('search_caller', 'alpha');
$search_called = GETPOST('search_called', 'alpha');
$search_direction = GETPOST('search_direction', 'alpha');
$search_type = GETPOST('search_type', 'alpha');
$search_date_start = dol_mktime(0, 0, 0, GETPOST('search_date_startmonth', 'int'), GETPOST('search_date_startday', 'int'), GETPOST('search_date_startyear', 'int'));
$search_date_end = dol_mktime(23, 59, 59, GETPOST('search_date_endmonth', 'int'), GETPOST('search_date_endday', 'int'), GETPOST('search_date_endyear', 'int'));

// Load variable for pagination
$limit = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTISSET('pageplusone') ? (GETPOST('pageplusone') - 1) : GETPOST("page", 'int');
if (empty($page) || $page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
    $page = 0;
}
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;

if (!$sortfield) {
    $sortfield = "t.call_date";
}
if (!$sortorder) {
    $sortorder = "DESC";
}

// Initialize objects
$object = new CallLog($db);
$form = new Form($db);
$formcompany = new FormCompany($db);

/*
 * Actions
 */

if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
    $search_all = "";
    $search_ref = "";
    $search_caller = "";
    $search_called = "";
    $search_direction = "";
    $search_type = "";
    $search_date_start = "";
    $search_date_end = "";
    $toselect = array();
    $search_array_options = array();
}

/*
 * View
 */

$title = $langs->trans("CallLogs");
$help_url = '';

llxHeader('', $title, $help_url);

// Build SQL query
$sql = "SELECT";
$sql .= " t.rowid,";
$sql .= " t.ref,";
$sql .= " t.call_id,";
$sql .= " t.call_date,";
$sql .= " t.caller_number,";
$sql .= " t.caller_name,";
$sql .= " t.called_number,";
$sql .= " t.called_name,";
$sql .= " t.direction,";
$sql .= " t.call_type,";
$sql .= " t.duration,";
$sql .= " t.talk_duration,";
$sql .= " t.fk_soc,";
$sql .= " t.has_recording,";
$sql .= " s.nom as socname";
$sql .= " FROM ".MAIN_DB_PREFIX."grandstreamucm_calllog as t";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON t.fk_soc = s.rowid";
$sql .= " WHERE t.entity IN (".getEntity('calllog').")";

if ($socid > 0) {
    $sql .= " AND t.fk_soc = ".intval($socid);
}
if ($search_ref) {
    $sql .= natural_search("t.ref", $search_ref);
}
if ($search_caller) {
    $sql .= natural_search(array("t.caller_number", "t.caller_name"), $search_caller);
}
if ($search_called) {
    $sql .= natural_search(array("t.called_number", "t.called_name"), $search_called);
}
if ($search_direction) {
    $sql .= " AND t.direction = '".$db->escape($search_direction)."'";
}
if ($search_type) {
    $sql .= " AND t.call_type = '".$db->escape($search_type)."'";
}
if ($search_date_start) {
    $sql .= " AND t.call_date >= '".$db->idate($search_date_start)."'";
}
if ($search_date_end) {
    $sql .= " AND t.call_date <= '".$db->idate($search_date_end)."'";
}
if ($search_all) {
    $sql .= natural_search(array("t.ref", "t.caller_number", "t.caller_name", "t.called_number", "t.called_name"), $search_all);
}

// Count total nb of records
$nbtotalofrecords = '';
if (empty($conf->global->MAIN_DISABLE_FULL_SCANLIST)) {
    $resql = $db->query($sql);
    $nbtotalofrecords = $db->num_rows($resql);
}

$sql .= $db->order($sortfield, $sortorder);
if ($limit) {
    $sql .= $db->plimit($limit + 1, $offset);
}

$resql = $db->query($sql);
if (!$resql) {
    dol_print_error($db);
    exit;
}

$num = $db->num_rows($resql);

// Output page
$arrayofselected = is_array($toselect) ? $toselect : array();

$param = '';
if (!empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) {
    $param .= '&contextpage='.urlencode($contextpage);
}
if ($limit > 0 && $limit != $conf->liste_limit) {
    $param .= '&limit='.urlencode($limit);
}
if ($search_ref) {
    $param .= '&search_ref='.urlencode($search_ref);
}
if ($search_caller) {
    $param .= '&search_caller='.urlencode($search_caller);
}
if ($search_called) {
    $param .= '&search_called='.urlencode($search_called);
}
if ($search_direction) {
    $param .= '&search_direction='.urlencode($search_direction);
}
if ($search_type) {
    $param .= '&search_type='.urlencode($search_type);
}
if ($socid > 0) {
    $param .= '&socid='.urlencode($socid);
}

// List of mass actions available
$arrayofmassactions = array();
if ($user->rights->grandstreamucm->delete) {
    $arrayofmassactions['predelete'] = '<span class="fa fa-trash paddingrightonly"></span>'.$langs->trans("Delete");
}
$massactionbutton = $form->selectMassAction('', $arrayofmassactions);

print '<form method="POST" id="searchFormList" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="list">';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
print '<input type="hidden" name="page" value="'.$page.'">';
print '<input type="hidden" name="contextpage" value="'.$contextpage.'">';

print_barre_liste($title, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, 'phone', 0, '', '', $limit);

// Show filters
print '<div class="div-table-responsive">';
print '<table class="tagtable nobottomiftotal liste'.($moreforfilter ? " listwithfilterbefore" : "").'">';

// Header
print '<tr class="liste_titre">';
print '<td class="liste_titre"><input type="text" class="flat maxwidth75" name="search_ref" value="'.dol_escape_htmltag($search_ref).'"></td>';
print '<td class="liste_titre center">';
print '<input type="text" class="flat" style="width:60px" name="search_date_startday" value="'.dol_escape_htmltag(GETPOST('search_date_startday', 'int')).'" placeholder="'.dol_escape_htmltag($langs->trans("Day")).'">';
print '<input type="text" class="flat" style="width:60px" name="search_date_startmonth" value="'.dol_escape_htmltag(GETPOST('search_date_startmonth', 'int')).'" placeholder="'.dol_escape_htmltag($langs->trans("Month")).'">';
print '<input type="text" class="flat" style="width:60px" name="search_date_startyear" value="'.dol_escape_htmltag(GETPOST('search_date_startyear', 'int')).'" placeholder="'.dol_escape_htmltag($langs->trans("Year")).'">';
print '</td>';
print '<td class="liste_titre">';
$directions = array('' => '', 'inbound' => $langs->trans('Inbound'), 'outbound' => $langs->trans('Outbound'), 'internal' => $langs->trans('Internal'));
print $form->selectarray('search_direction', $directions, $search_direction, 0, 0, 0, '', 0, 0, 0, '', 'maxwidth100');
print '</td>';
print '<td class="liste_titre">';
$types = array('' => '', 'answered' => $langs->trans('Answered'), 'missed' => $langs->trans('Missed'), 'voicemail' => $langs->trans('Voicemail'), 'busy' => $langs->trans('Busy'));
print $form->selectarray('search_type', $types, $search_type, 0, 0, 0, '', 0, 0, 0, '', 'maxwidth100');
print '</td>';
print '<td class="liste_titre"><input type="text" class="flat maxwidth100" name="search_caller" value="'.dol_escape_htmltag($search_caller).'"></td>';
print '<td class="liste_titre"><input type="text" class="flat maxwidth100" name="search_called" value="'.dol_escape_htmltag($search_called).'"></td>';
print '<td class="liste_titre"></td>'; // Duration
print '<td class="liste_titre"></td>'; // Third party
print '<td class="liste_titre"></td>'; // Recording
print '<td class="liste_titre maxwidthsearch">';
$searchpicto = $form->showFilterButtons();
print $searchpicto;
print '</td>';
print '</tr>';

// Column headers
print '<tr class="liste_titre">';
print_liste_field_titre("Ref", $_SERVER["PHP_SELF"], "t.ref", "", $param, "", $sortfield, $sortorder);
print_liste_field_titre("CallDate", $_SERVER["PHP_SELF"], "t.call_date", "", $param, '', $sortfield, $sortorder, 'center ');
print_liste_field_titre("Direction", $_SERVER["PHP_SELF"], "t.direction", "", $param, '', $sortfield, $sortorder);
print_liste_field_titre("CallType", $_SERVER["PHP_SELF"], "t.call_type", "", $param, '', $sortfield, $sortorder);
print_liste_field_titre("CallerNumber", $_SERVER["PHP_SELF"], "t.caller_number", "", $param, '', $sortfield, $sortorder);
print_liste_field_titre("CalledNumber", $_SERVER["PHP_SELF"], "t.called_number", "", $param, '', $sortfield, $sortorder);
print_liste_field_titre("Duration", $_SERVER["PHP_SELF"], "t.talk_duration", "", $param, '', $sortfield, $sortorder, 'right ');
print_liste_field_titre("ThirdParty", $_SERVER["PHP_SELF"], "s.nom", "", $param, '', $sortfield, $sortorder);
print_liste_field_titre("HasRecording", $_SERVER["PHP_SELF"], "t.has_recording", "", $param, '', $sortfield, $sortorder, 'center ');
print_liste_field_titre('', $_SERVER["PHP_SELF"], "", '', '', '', $sortfield, $sortorder, 'maxwidthsearch ');
print '</tr>';

// Loop on records
$i = 0;
$totalarray = array();
while ($i < min($num, $limit)) {
    $obj = $db->fetch_object($resql);
    if (empty($obj)) {
        break;
    }

    $calllog = new CallLog($db);
    $calllog->id = $obj->rowid;
    $calllog->ref = $obj->ref;
    $calllog->call_id = $obj->call_id;
    $calllog->call_date = $obj->call_date;
    $calllog->caller_number = $obj->caller_number;
    $calllog->caller_name = $obj->caller_name;
    $calllog->called_number = $obj->called_number;
    $calllog->called_name = $obj->called_name;
    $calllog->direction = $obj->direction;
    $calllog->call_type = $obj->call_type;
    $calllog->duration = $obj->duration;
    $calllog->talk_duration = $obj->talk_duration;
    $calllog->fk_soc = $obj->fk_soc;
    $calllog->has_recording = $obj->has_recording;

    print '<tr class="oddeven">';

    // Ref
    print '<td class="nowraponall">';
    print '<a href="'.dol_buildpath('/grandstreamucm/call_card.php', 1).'?id='.$obj->rowid.'">'.$obj->ref.'</a>';
    print '</td>';

    // Date
    print '<td class="center nowraponall">'.dol_print_date($db->jdate($obj->call_date), 'dayhour').'</td>';

    // Direction
    print '<td>'.$calllog->getDirectionBadge().'</td>';

    // Type
    print '<td>'.$calllog->getCallTypeBadge().'</td>';

    // Caller
    print '<td class="tdoverflowmax150" title="'.dol_escape_htmltag($obj->caller_number.' - '.$obj->caller_name).'">';
    print dol_escape_htmltag($obj->caller_number);
    if ($obj->caller_name) {
        print '<br><span class="opacitymedium small">'.dol_escape_htmltag($obj->caller_name).'</span>';
    }
    print '</td>';

    // Called
    print '<td class="tdoverflowmax150" title="'.dol_escape_htmltag($obj->called_number.' - '.$obj->called_name).'">';
    print dol_escape_htmltag($obj->called_number);
    if ($obj->called_name) {
        print '<br><span class="opacitymedium small">'.dol_escape_htmltag($obj->called_name).'</span>';
    }
    print '</td>';

    // Duration
    print '<td class="right nowraponall">'.$calllog->getFormattedDuration().'</td>';

    // Third party
    print '<td class="tdoverflowmax150">';
    if ($obj->fk_soc > 0) {
        $societe = new Societe($db);
        $societe->fetch($obj->fk_soc);
        print $societe->getNomUrl(1);
    }
    print '</td>';

    // Recording
    print '<td class="center">';
    if ($obj->has_recording) {
        print '<span class="badge badge-success">Oui</span>';
    } else {
        print '<span class="badge badge-secondary">Non</span>';
    }
    print '</td>';

    // Action column
    print '<td class="nowrap center">';
    print '<a class="editfielda" href="'.dol_buildpath('/grandstreamucm/call_card.php', 1).'?id='.$obj->rowid.'">'.img_picto($langs->trans("View"), 'eye').'</a>';
    print '</td>';

    print '</tr>';

    $i++;
}

// Empty line if no results
if ($num == 0) {
    print '<tr class="oddeven"><td colspan="10" class="opacitymedium">'.$langs->trans("NoCallsFound").'</td></tr>';
}

print '</table>';
print '</div>';
print '</form>';

// End of page
llxFooter();
$db->close();
