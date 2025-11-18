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

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
dol_include_once('/grandstreamucm/class/calllog.class.php');
dol_include_once('/grandstreamucm/lib/grandstreamucm.lib.php');

// Load translation files
$langs->loadLangs(array("grandstreamucm@grandstreamucm", "other"));

// Get parameters
$id = GETPOST('id', 'int');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');

// Initialize objects
$object = new CallLog($db);
$form = new Form($db);
$formfile = new FormFile($db);

// Fetch object
if ($id > 0 || !empty($ref)) {
    $object->fetch($id, $ref);
}

// Security check
if (!$user->rights->grandstreamucm->read) {
    accessforbidden();
}

/*
 * Actions
 */

if ($action == 'add_note' && $user->rights->grandstreamucm->write) {
    $object->note_private = GETPOST('note_private', 'restricthtml');
    $object->note_public = GETPOST('note_public', 'restricthtml');
    $result = $object->update($user);
    if ($result > 0) {
        setEventMessages($langs->trans("NoteSaved"), null, 'mesgs');
    } else {
        setEventMessages($object->error, $object->errors, 'errors');
    }
}

if ($action == 'confirm_delete' && $confirm == 'yes' && $user->rights->grandstreamucm->delete) {
    $result = $object->delete($user);
    if ($result > 0) {
        setEventMessages($langs->trans("RecordDeleted"), null, 'mesgs');
        header('Location: '.dol_buildpath('/grandstreamucm/call_list.php', 1));
        exit;
    } else {
        setEventMessages($object->error, $object->errors, 'errors');
    }
}

/*
 * View
 */

$title = $langs->trans("CallDetails").' - '.$object->ref;
$help_url = '';

llxHeader('', $title, $help_url);

if ($object->id > 0) {
    // Confirm delete
    if ($action == 'delete') {
        print $form->formconfirm(
            $_SERVER["PHP_SELF"].'?id='.$object->id,
            $langs->trans('DeleteCall'),
            $langs->trans('ConfirmDeleteCall'),
            'confirm_delete',
            '',
            0,
            1
        );
    }

    $head = calllogPrepareHead($object);

    print dol_get_fiche_head($head, 'card', $langs->trans("CallLog"), -1, 'phone');

    // Object card
    $linkback = '<a href="'.dol_buildpath('/grandstreamucm/call_list.php', 1).'">'.$langs->trans("BackToList").'</a>';

    dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref');

    print '<div class="fichecenter">';
    print '<div class="fichehalfleft">';
    print '<div class="underbanner clearboth"></div>';
    print '<table class="border centpercent tableforfield">';

    // Call ID
    print '<tr><td class="titlefield">'.$langs->trans("CallID").'</td><td>'.$object->call_id.'</td></tr>';

    // Date
    print '<tr><td>'.$langs->trans("CallDate").'</td><td>'.dol_print_date($db->jdate($object->call_date), 'dayhour').'</td></tr>';

    // Direction
    print '<tr><td>'.$langs->trans("Direction").'</td><td>'.$object->getDirectionBadge().'</td></tr>';

    // Type
    print '<tr><td>'.$langs->trans("CallType").'</td><td>'.$object->getCallTypeBadge().'</td></tr>';

    // Duration
    print '<tr><td>'.$langs->trans("Duration").'</td><td>'.$object->getFormattedDuration().'</td></tr>';

    // Disposition
    if ($object->disposition) {
        print '<tr><td>'.$langs->trans("Disposition").'</td><td>'.$object->disposition.'</td></tr>';
    }

    // Extension
    if ($object->extension) {
        print '<tr><td>'.$langs->trans("Extension").'</td><td>'.$object->extension.'</td></tr>';
    }

    // Trunk
    if ($object->trunk) {
        print '<tr><td>'.$langs->trans("Trunk").'</td><td>'.$object->trunk.'</td></tr>';
    }

    print '</table>';
    print '</div>';

    print '<div class="fichehalfright">';
    print '<div class="underbanner clearboth"></div>';
    print '<table class="border centpercent tableforfield">';

    // Caller
    print '<tr><td class="titlefield">'.$langs->trans("CallerNumber").'</td><td>'.$object->caller_number.'</td></tr>';
    if ($object->caller_name) {
        print '<tr><td>'.$langs->trans("CallerName").'</td><td>'.$object->caller_name.'</td></tr>';
    }

    // Called
    print '<tr><td>'.$langs->trans("CalledNumber").'</td><td>'.$object->called_number.'</td></tr>';
    if ($object->called_name) {
        print '<tr><td>'.$langs->trans("CalledName").'</td><td>'.$object->called_name.'</td></tr>';
    }

    // Third party
    print '<tr><td>'.$langs->trans("ThirdParty").'</td><td>';
    if ($object->fk_soc > 0) {
        $societe = new Societe($db);
        $societe->fetch($object->fk_soc);
        print $societe->getNomUrl(1);
    } else {
        print '<span class="opacitymedium">'.$langs->trans("None").'</span>';
    }
    print '</td></tr>';

    // Recording
    print '<tr><td>'.$langs->trans("HasRecording").'</td><td>';
    if ($object->has_recording) {
        print '<span class="badge badge-success">Oui</span>';
        if ($object->recording_file) {
            $recordingPath = $conf->grandstreamucm->dir_output.'/recordings/'.$object->recording_file;
            if (file_exists($recordingPath)) {
                print ' <a href="'.DOL_URL_ROOT.'/document.php?modulepart=grandstreamucm&file=recordings/'.$object->recording_file.'" target="_blank">';
                print img_picto($langs->trans("DownloadRecording"), 'download').' '.$langs->trans("DownloadRecording");
                print '</a>';
            }
        }
    } else {
        print '<span class="badge badge-secondary">Non</span>';
    }
    print '</td></tr>';

    print '</table>';
    print '</div>';
    print '</div>';

    print '<div class="clearboth"></div>';

    // Notes section
    print '<div class="fichecenter">';
    print '<div class="underbanner clearboth"></div>';

    print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="add_note">';

    print '<table class="border centpercent tableforfield">';

    // Note privée
    print '<tr><td class="titlefield tdtop">'.$langs->trans("NotePrivate").'</td><td>';
    $doleditor = new DolEditor('note_private', $object->note_private, '', 150, 'dolibarr_notes', 'In', true, false, true, ROWS_3, '90%');
    print $doleditor->Create(1);
    print '</td></tr>';

    // Note publique
    print '<tr><td class="tdtop">'.$langs->trans("NotePublic").'</td><td>';
    $doleditor = new DolEditor('note_public', $object->note_public, '', 150, 'dolibarr_notes', 'In', true, false, true, ROWS_3, '90%');
    print $doleditor->Create(1);
    print '</td></tr>';

    print '</table>';

    if ($user->rights->grandstreamucm->write) {
        print '<div class="center">';
        print '<input type="submit" class="button" value="'.$langs->trans("Save").'">';
        print '</div>';
    }

    print '</form>';
    print '</div>';

    print dol_get_fiche_end();

    // Action buttons
    print '<div class="tabsAction">';

    // View third party
    if ($object->fk_soc > 0) {
        print '<a class="butAction" href="'.DOL_URL_ROOT.'/societe/card.php?socid='.$object->fk_soc.'">'.$langs->trans("ViewThirdParty").'</a>';
    }

    // Delete
    if ($user->rights->grandstreamucm->delete) {
        print '<a class="butActionDelete" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=delete&token='.newToken().'">'.$langs->trans("Delete").'</a>';
    }

    print '</div>';

} else {
    dol_print_error($db, 'Call log not found');
}

// End of page
llxFooter();
$db->close();
