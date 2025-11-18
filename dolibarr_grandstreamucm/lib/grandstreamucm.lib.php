<?php
/* Copyright (C) 2024 Your Company
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Prepare admin pages header for GrandstreamUCM
 *
 * @return array Array of tabs
 */
function grandstreamucmAdminPrepareHead()
{
    global $langs, $conf;

    $langs->load("grandstreamucm@grandstreamucm");

    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath("/grandstreamucm/admin/setup.php", 1);
    $head[$h][1] = $langs->trans("Settings");
    $head[$h][2] = 'settings';
    $h++;

    complete_head_from_modules($conf, $langs, null, $head, $h, 'grandstreamucm@grandstreamucm');

    complete_head_from_modules($conf, $langs, null, $head, $h, 'grandstreamucm@grandstreamucm', 'remove');

    return $head;
}

/**
 * Prepare call log header tabs
 *
 * @param CallLog $object CallLog object
 * @return array Array of tabs
 */
function calllogPrepareHead($object)
{
    global $langs, $conf;

    $langs->load("grandstreamucm@grandstreamucm");

    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath("/grandstreamucm/call_card.php", 1).'?id='.$object->id;
    $head[$h][1] = $langs->trans("CallDetails");
    $head[$h][2] = 'card';
    $h++;

    // Show notes tab
    if (!empty($conf->global->MAIN_ENABLE_NOTE_PUBLIC) || !empty($conf->global->MAIN_ENABLE_NOTE_PRIVATE)) {
        $nbNote = 0;
        if (!empty($object->note_private)) {
            $nbNote++;
        }
        if (!empty($object->note_public)) {
            $nbNote++;
        }
        $head[$h][0] = dol_buildpath("/grandstreamucm/call_note.php", 1).'?id='.$object->id;
        $head[$h][1] = $langs->trans("Notes");
        if ($nbNote > 0) {
            $head[$h][1] .= '<span class="badge marginleftonlyshort">'.$nbNote.'</span>';
        }
        $head[$h][2] = 'note';
        $h++;
    }

    complete_head_from_modules($conf, $langs, $object, $head, $h, 'calllog@grandstreamucm');

    complete_head_from_modules($conf, $langs, $object, $head, $h, 'calllog@grandstreamucm', 'remove');

    return $head;
}
