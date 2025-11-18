<?php
/* Copyright (C) 2024 Your Company
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class CallLog
 */
class CallLog extends CommonObject
{
    /**
     * @var string ID to identify managed object
     */
    public $element = 'calllog';

    /**
     * @var string Name of table without prefix where object is stored
     */
    public $table_element = 'grandstreamucm_calllog';

    /**
     * @var int Does object support multicompany?
     */
    public $ismultientitymanaged = 1;

    /**
     * @var int Does object support extrafields?
     */
    public $isextrafieldmanaged = 1;

    /**
     * @var string String with name of icon for calllog
     */
    public $picto = 'phone';

    const STATUS_DRAFT = 0;
    const STATUS_VALIDATED = 1;

    /**
     * 'type' field format
     */
    public $fields = array(
        'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0),
        'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => 1, 'visible' => 0, 'position' => 5, 'notnull' => 1, 'default' => '1', 'index' => 1),
        'ref' => array('type' => 'varchar(128)', 'label' => 'Ref', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 4, 'noteditable' => 1, 'index' => 1, 'searchall' => 1, 'showoncombobox' => 1),
        'call_id' => array('type' => 'varchar(128)', 'label' => 'CallID', 'enabled' => 1, 'position' => 20, 'notnull' => 1, 'visible' => 1),
        'call_date' => array('type' => 'datetime', 'label' => 'CallDate', 'enabled' => 1, 'position' => 30, 'notnull' => 1, 'visible' => 1),
        'caller_number' => array('type' => 'varchar(64)', 'label' => 'CallerNumber', 'enabled' => 1, 'position' => 40, 'notnull' => 0, 'visible' => 1, 'searchall' => 1),
        'caller_name' => array('type' => 'varchar(255)', 'label' => 'CallerName', 'enabled' => 1, 'position' => 50, 'notnull' => 0, 'visible' => 1),
        'called_number' => array('type' => 'varchar(64)', 'label' => 'CalledNumber', 'enabled' => 1, 'position' => 60, 'notnull' => 0, 'visible' => 1, 'searchall' => 1),
        'called_name' => array('type' => 'varchar(255)', 'label' => 'CalledName', 'enabled' => 1, 'position' => 70, 'notnull' => 0, 'visible' => 1),
        'direction' => array('type' => 'varchar(32)', 'label' => 'Direction', 'enabled' => 1, 'position' => 80, 'notnull' => 1, 'visible' => 1),
        'call_type' => array('type' => 'varchar(32)', 'label' => 'CallType', 'enabled' => 1, 'position' => 90, 'notnull' => 1, 'visible' => 1),
        'duration' => array('type' => 'integer', 'label' => 'Duration', 'enabled' => 1, 'position' => 100, 'notnull' => 0, 'visible' => 1),
        'talk_duration' => array('type' => 'integer', 'label' => 'TalkDuration', 'enabled' => 1, 'position' => 110, 'notnull' => 0, 'visible' => 1),
        'fk_soc' => array('type' => 'integer:Societe:societe/class/societe.class.php', 'label' => 'ThirdParty', 'enabled' => 1, 'position' => 120, 'notnull' => 0, 'visible' => 1, 'index' => 1),
        'fk_socpeople' => array('type' => 'integer:Contact:contact/class/contact.class.php', 'label' => 'Contact', 'enabled' => 1, 'position' => 130, 'notnull' => 0, 'visible' => 1),
        'extension' => array('type' => 'varchar(32)', 'label' => 'Extension', 'enabled' => 1, 'position' => 140, 'notnull' => 0, 'visible' => 1),
        'trunk' => array('type' => 'varchar(64)', 'label' => 'Trunk', 'enabled' => 1, 'position' => 150, 'notnull' => 0, 'visible' => 1),
        'disposition' => array('type' => 'varchar(64)', 'label' => 'Disposition', 'enabled' => 1, 'position' => 160, 'notnull' => 0, 'visible' => 1),
        'recording_file' => array('type' => 'varchar(255)', 'label' => 'RecordingFile', 'enabled' => 1, 'position' => 170, 'notnull' => 0, 'visible' => 1),
        'has_recording' => array('type' => 'smallint', 'label' => 'HasRecording', 'enabled' => 1, 'position' => 180, 'notnull' => 0, 'visible' => 1),
        'note_private' => array('type' => 'html', 'label' => 'NotePrivate', 'enabled' => 1, 'position' => 190, 'notnull' => 0, 'visible' => 0),
        'note_public' => array('type' => 'html', 'label' => 'NotePublic', 'enabled' => 1, 'position' => 200, 'notnull' => 0, 'visible' => 0),
        'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 500, 'notnull' => 1, 'visible' => -2),
        'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 501, 'notnull' => 0, 'visible' => -2),
        'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'position' => 510, 'notnull' => 1, 'visible' => -2),
        'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => 1, 'position' => 511, 'notnull' => -1, 'visible' => -2),
        'status' => array('type' => 'integer', 'label' => 'Status', 'enabled' => 1, 'position' => 1000, 'notnull' => 1, 'visible' => 1, 'index' => 1, 'default' => '1')
    );

    public $rowid;
    public $entity;
    public $ref;
    public $call_id;
    public $call_date;
    public $caller_number;
    public $caller_name;
    public $called_number;
    public $called_name;
    public $direction;
    public $call_type;
    public $duration;
    public $talk_duration;
    public $fk_soc;
    public $fk_socpeople;
    public $extension;
    public $trunk;
    public $disposition;
    public $recording_file;
    public $has_recording;
    public $note_private;
    public $note_public;
    public $date_creation;
    public $tms;
    public $fk_user_creat;
    public $fk_user_modif;
    public $status;

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct(DoliDB $db)
    {
        global $conf, $langs;

        $this->db = $db;

        if (empty($conf->global->MAIN_SHOW_TECHNICAL_ID) && isset($this->fields['rowid'])) {
            $this->fields['rowid']['visible'] = 0;
        }

        if (empty($conf->multicompany->enabled) && isset($this->fields['entity'])) {
            $this->fields['entity']['enabled'] = 0;
        }
    }

    /**
     * Create object into database
     *
     * @param User $user User that creates
     * @param bool $notrigger false=launch triggers, true=disable triggers
     * @return int <0 if KO, Id of created object if OK
     */
    public function create(User $user, $notrigger = false)
    {
        global $conf;

        $this->status = self::STATUS_VALIDATED;

        if (empty($this->ref)) {
            $this->ref = $this->getNextNumRef();
        }

        return $this->createCommon($user, $notrigger);
    }

    /**
     * Load object in memory from the database
     *
     * @param int $id Id object
     * @param string $ref Ref
     * @return int <0 if KO, 0 if not found, >0 if OK
     */
    public function fetch($id, $ref = null)
    {
        return $this->fetchCommon($id, $ref);
    }

    /**
     * Load list of objects in memory from the database
     *
     * @param string $sortorder Sort Order
     * @param string $sortfield Sort field
     * @param int $limit limit
     * @param int $offset Offset
     * @param array $filter Filter array
     * @param string $filtermode Filter mode (AND or OR)
     * @return array|int <0 if KO, array of objects if OK
     */
    public function fetchAll($sortorder = '', $sortfield = '', $limit = 0, $offset = 0, array $filter = array(), $filtermode = 'AND')
    {
        global $conf;

        dol_syslog(__METHOD__, LOG_DEBUG);

        $records = array();

        $sql = "SELECT";
        $sql .= " t.rowid,";
        $sql .= " t.entity,";
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
        $sql .= " t.fk_socpeople,";
        $sql .= " t.extension,";
        $sql .= " t.trunk,";
        $sql .= " t.disposition,";
        $sql .= " t.recording_file,";
        $sql .= " t.has_recording,";
        $sql .= " t.note_private,";
        $sql .= " t.note_public,";
        $sql .= " t.date_creation,";
        $sql .= " t.tms,";
        $sql .= " t.fk_user_creat,";
        $sql .= " t.fk_user_modif,";
        $sql .= " t.status";
        $sql .= " FROM ".MAIN_DB_PREFIX.$this->table_element." as t";
        $sql .= " WHERE t.entity IN (".getEntity($this->element).")";

        // Manage filter
        $sqlwhere = array();
        if (count($filter) > 0) {
            foreach ($filter as $key => $value) {
                if ($key == 't.rowid') {
                    $sqlwhere[] = $key." = ".intval($value);
                } elseif (in_array($this->fields[$key]['type'], array('date', 'datetime', 'timestamp'))) {
                    $sqlwhere[] = $key." = '".$this->db->idate($value)."'";
                } elseif ($key == 'customsql') {
                    $sqlwhere[] = $value;
                } elseif (strpos($value, '%') === false) {
                    $sqlwhere[] = $key." IN (".$this->db->sanitize($this->db->escape($value)).")";
                } else {
                    $sqlwhere[] = $key." LIKE '%".$this->db->escape($value)."%'";
                }
            }
        }
        if (count($sqlwhere) > 0) {
            $sql .= " AND (".implode(" ".$filtermode." ", $sqlwhere).")";
        }

        if (!empty($sortfield)) {
            $sql .= $this->db->order($sortfield, $sortorder);
        }
        if (!empty($limit)) {
            $sql .= $this->db->plimit($limit, $offset);
        }

        $resql = $this->db->query($sql);
        if ($resql) {
            $num = $this->db->num_rows($resql);
            $i = 0;
            while ($i < ($limit ? min($limit, $num) : $num)) {
                $obj = $this->db->fetch_object($resql);

                $record = new self($this->db);
                $record->setVarsFromFetchObj($obj);

                $records[$record->id] = $record;

                $i++;
            }
            $this->db->free($resql);

            return $records;
        } else {
            $this->errors[] = 'Error '.$this->db->lasterror();
            dol_syslog(__METHOD__.' '.join(',', $this->errors), LOG_ERR);

            return -1;
        }
    }

    /**
     * Update object into database
     *
     * @param User $user User that modifies
     * @param bool $notrigger false=launch triggers, true=disable triggers
     * @return int <0 if KO, >0 if OK
     */
    public function update(User $user, $notrigger = false)
    {
        return $this->updateCommon($user, $notrigger);
    }

    /**
     * Delete object in database
     *
     * @param User $user User that deletes
     * @param bool $notrigger false=launch triggers, true=disable triggers
     * @return int <0 if KO, >0 if OK
     */
    public function delete(User $user, $notrigger = false)
    {
        return $this->deleteCommon($user, $notrigger);
    }

    /**
     * Get next reference
     *
     * @return string Next ref
     */
    public function getNextNumRef()
    {
        global $langs, $conf;

        $prefix = 'CALL';
        $mask = $prefix.'{yy}{mm}-{0000}';

        $numref = get_next_value($this->db, $mask, $this->table_element, 'ref', '', null, dol_now());

        if ($numref == '') {
            // Fallback
            $sql = "SELECT MAX(rowid) as max FROM ".MAIN_DB_PREFIX.$this->table_element;
            $resql = $this->db->query($sql);
            if ($resql) {
                $obj = $this->db->fetch_object($resql);
                $numref = $prefix.date('ym').'-'.sprintf('%04d', ($obj->max + 1));
            } else {
                $numref = $prefix.date('ym').'-0001';
            }
        }

        return $numref;
    }

    /**
     * Format duration in human readable format
     *
     * @return string Formatted duration
     */
    public function getFormattedDuration()
    {
        $seconds = $this->talk_duration ? $this->talk_duration : $this->duration;

        if (empty($seconds)) {
            return '00:00';
        }

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
        }

        return sprintf('%02d:%02d', $minutes, $secs);
    }

    /**
     * Get label for direction
     *
     * @return string Label
     */
    public function getDirectionLabel()
    {
        global $langs;

        $labels = array(
            'inbound' => $langs->trans('Inbound'),
            'outbound' => $langs->trans('Outbound'),
            'internal' => $langs->trans('Internal')
        );

        return isset($labels[$this->direction]) ? $labels[$this->direction] : $this->direction;
    }

    /**
     * Get label for call type
     *
     * @return string Label
     */
    public function getCallTypeLabel()
    {
        global $langs;

        $labels = array(
            'answered' => $langs->trans('Answered'),
            'missed' => $langs->trans('Missed'),
            'voicemail' => $langs->trans('Voicemail'),
            'busy' => $langs->trans('Busy'),
            'failed' => $langs->trans('Failed')
        );

        return isset($labels[$this->call_type]) ? $labels[$this->call_type] : $this->call_type;
    }

    /**
     * Get call statistics for a third party
     *
     * @param int $socid Third party ID
     * @return array Statistics
     */
    public function getStatsBySociete($socid)
    {
        $stats = array(
            'total' => 0,
            'inbound' => 0,
            'outbound' => 0,
            'missed' => 0,
            'answered' => 0,
            'total_duration' => 0,
            'last_call' => null
        );

        $sql = "SELECT COUNT(*) as total,";
        $sql .= " SUM(CASE WHEN direction = 'inbound' THEN 1 ELSE 0 END) as inbound,";
        $sql .= " SUM(CASE WHEN direction = 'outbound' THEN 1 ELSE 0 END) as outbound,";
        $sql .= " SUM(CASE WHEN call_type = 'missed' THEN 1 ELSE 0 END) as missed,";
        $sql .= " SUM(CASE WHEN call_type = 'answered' THEN 1 ELSE 0 END) as answered,";
        $sql .= " SUM(COALESCE(talk_duration, duration, 0)) as total_duration,";
        $sql .= " MAX(call_date) as last_call";
        $sql .= " FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " WHERE fk_soc = ".intval($socid);

        $resql = $this->db->query($sql);
        if ($resql) {
            $obj = $this->db->fetch_object($resql);
            if ($obj) {
                $stats['total'] = $obj->total;
                $stats['inbound'] = $obj->inbound;
                $stats['outbound'] = $obj->outbound;
                $stats['missed'] = $obj->missed;
                $stats['answered'] = $obj->answered;
                $stats['total_duration'] = $obj->total_duration;
                $stats['last_call'] = $obj->last_call;
            }
            $this->db->free($resql);
        }

        return $stats;
    }

    /**
     * Format total duration
     *
     * @param int $seconds Total seconds
     * @return string Formatted duration
     */
    public static function formatTotalDuration($seconds)
    {
        if (empty($seconds)) {
            return '0s';
        }

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        $parts = array();
        if ($hours > 0) {
            $parts[] = $hours.'h';
        }
        if ($minutes > 0) {
            $parts[] = $minutes.'m';
        }
        if ($secs > 0 || empty($parts)) {
            $parts[] = $secs.'s';
        }

        return implode(' ', $parts);
    }

    /**
     * Return HTML string to display the direction with badge
     *
     * @return string HTML string
     */
    public function getDirectionBadge()
    {
        $colors = array(
            'inbound' => 'info',
            'outbound' => 'success',
            'internal' => 'secondary'
        );

        $color = isset($colors[$this->direction]) ? $colors[$this->direction] : 'secondary';

        return '<span class="badge badge-'.$color.'">'.$this->getDirectionLabel().'</span>';
    }

    /**
     * Return HTML string to display the call type with badge
     *
     * @return string HTML string
     */
    public function getCallTypeBadge()
    {
        $colors = array(
            'answered' => 'success',
            'missed' => 'danger',
            'voicemail' => 'warning',
            'busy' => 'secondary',
            'failed' => 'danger'
        );

        $color = isset($colors[$this->call_type]) ? $colors[$this->call_type] : 'secondary';

        return '<span class="badge badge-'.$color.'">'.$this->getCallTypeLabel().'</span>';
    }
}
