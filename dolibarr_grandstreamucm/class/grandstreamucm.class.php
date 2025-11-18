<?php
/* Copyright (C) 2024 Your Company
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
dol_include_once('/grandstreamucm/class/calllog.class.php');

/**
 * Class GrandstreamUCM - API connector for Grandstream UCM
 */
class GrandstreamUCM
{
    /**
     * @var DoliDB Database handler
     */
    public $db;

    /**
     * @var string UCM host
     */
    private $host;

    /**
     * @var int UCM port
     */
    private $port;

    /**
     * @var bool Use HTTPS
     */
    private $useHttps;

    /**
     * @var string API username
     */
    private $username;

    /**
     * @var string API password
     */
    private $password;

    /**
     * @var string Session cookie
     */
    private $sessionCookie;

    /**
     * @var array Errors
     */
    public $errors = array();

    /**
     * @var string Last error
     */
    public $error;

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        global $conf;

        $this->db = $db;

        // Load configuration
        $this->host = $conf->global->GRANDSTREAMUCM_HOST;
        $this->port = $conf->global->GRANDSTREAMUCM_PORT ?: 8089;
        $this->useHttps = !empty($conf->global->GRANDSTREAMUCM_USE_HTTPS);
        $this->username = $conf->global->GRANDSTREAMUCM_USERNAME;
        $this->password = $conf->global->GRANDSTREAMUCM_PASSWORD;
    }

    /**
     * Get API base URL
     *
     * @return string Base URL
     */
    private function getBaseUrl()
    {
        $protocol = $this->useHttps ? 'https' : 'http';
        return $protocol.'://'.$this->host.':'.$this->port.'/api';
    }

    /**
     * Make API request
     *
     * @param string $endpoint API endpoint
     * @param array $data POST data
     * @param string $method HTTP method
     * @return array|false Response data or false on error
     */
    private function apiRequest($endpoint, $data = array(), $method = 'POST')
    {
        $url = $this->getBaseUrl().'/'.$endpoint;

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $headers = array('Content-Type: application/json');

        if ($this->sessionCookie) {
            $headers[] = 'Cookie: '.$this->sessionCookie;
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'GET' && !empty($data)) {
            $url .= '?'.http_build_query($data);
            curl_setopt($ch, CURLOPT_URL, $url);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        curl_close($ch);

        if ($curlError) {
            $this->error = 'Erreur cURL: '.$curlError;
            $this->errors[] = $this->error;
            dol_syslog(__METHOD__.' '.$this->error, LOG_ERR);
            return false;
        }

        if ($httpCode !== 200) {
            $this->error = 'Erreur HTTP: '.$httpCode;
            $this->errors[] = $this->error;
            dol_syslog(__METHOD__.' '.$this->error, LOG_ERR);
            return false;
        }

        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error = 'Erreur JSON: '.json_last_error_msg();
            $this->errors[] = $this->error;
            return false;
        }

        return $result;
    }

    /**
     * Login to UCM API
     *
     * @return bool True on success
     */
    public function login()
    {
        $data = array(
            'username' => $this->username,
            'password' => $this->password
        );

        $result = $this->apiRequest('login', $data);

        if ($result && isset($result['response']) && $result['response'] === 'success') {
            $this->sessionCookie = isset($result['cookie']) ? $result['cookie'] : '';
            return true;
        }

        $this->error = 'Échec de l\'authentification au serveur UCM';
        $this->errors[] = $this->error;
        return false;
    }

    /**
     * Test connection to UCM
     *
     * @return bool True if connection successful
     */
    public function testConnection()
    {
        return $this->login();
    }

    /**
     * Fetch call logs from UCM
     *
     * @param string $startDate Start date (Y-m-d H:i:s)
     * @param string $endDate End date (Y-m-d H:i:s)
     * @return array|false Call logs or false on error
     */
    public function fetchCallLogs($startDate, $endDate)
    {
        if (!$this->login()) {
            return false;
        }

        $params = array(
            'start_time' => $startDate,
            'end_time' => $endDate
        );

        $result = $this->apiRequest('cdr', $params, 'GET');

        if ($result) {
            if (isset($result['cdr'])) {
                return $result['cdr'];
            } elseif (is_array($result)) {
                return $result;
            }
        }

        return array();
    }

    /**
     * Download recording file
     *
     * @param string $recordingFile Recording filename
     * @return string|false File content or false on error
     */
    public function downloadRecording($recordingFile)
    {
        if (!$this->login()) {
            return false;
        }

        $url = $this->getBaseUrl().'/recording?file='.urlencode($recordingFile);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Cookie: '.$this->sessionCookie));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($httpCode === 200 && $response) {
            return $response;
        }

        $this->error = 'Échec du téléchargement de l\'enregistrement';
        $this->errors[] = $this->error;
        return false;
    }

    /**
     * Sync calls from UCM to Dolibarr
     *
     * @param User $user User performing sync
     * @return int Number of synced calls or -1 on error
     */
    public function syncCalls($user)
    {
        global $conf;

        dol_syslog(__METHOD__.' Start sync', LOG_INFO);

        // Calculate date range
        $daysToSync = $conf->global->GRANDSTREAMUCM_DAYS_TO_SYNC ?: 30;
        $endDate = date('Y-m-d H:i:s');
        $startDate = date('Y-m-d H:i:s', strtotime("-{$daysToSync} days"));

        // Fetch call logs
        $callLogs = $this->fetchCallLogs($startDate, $endDate);

        if ($callLogs === false) {
            return -1;
        }

        $syncedCount = 0;
        $callLog = new CallLog($this->db);

        foreach ($callLogs as $callData) {
            try {
                if ($this->processCallData($callData, $callLog, $user)) {
                    $syncedCount++;
                }
            } catch (Exception $e) {
                dol_syslog(__METHOD__.' Error: '.$e->getMessage(), LOG_ERR);
                continue;
            }
        }

        // Update last sync date
        dolibarr_set_const($this->db, 'GRANDSTREAMUCM_LAST_SYNC', date('Y-m-d H:i:s'), 'chaine', 0, '', $conf->entity);

        dol_syslog(__METHOD__.' Synced '.$syncedCount.' calls', LOG_INFO);

        return $syncedCount;
    }

    /**
     * Process a single call data record
     *
     * @param array $callData Call data from UCM
     * @param CallLog $callLog CallLog object
     * @param User $user User performing sync
     * @return bool True if processed successfully
     */
    private function processCallData($callData, $callLog, $user)
    {
        global $conf;

        // Get call ID
        $callId = isset($callData['uniqueid']) ? $callData['uniqueid'] : (isset($callData['call_id']) ? $callData['call_id'] : '');
        if (empty($callId)) {
            return false;
        }

        // Check if call already exists
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."grandstreamucm_calllog";
        $sql .= " WHERE call_id = '".$this->db->escape($callId)."'";
        $sql .= " AND entity = ".$conf->entity;

        $resql = $this->db->query($sql);
        if ($resql && $this->db->num_rows($resql) > 0) {
            return false; // Already exists
        }

        // Parse call data
        $callerNumber = $this->normalizePhoneNumber(isset($callData['src']) ? $callData['src'] : (isset($callData['caller']) ? $callData['caller'] : ''));
        $calledNumber = $this->normalizePhoneNumber(isset($callData['dst']) ? $callData['dst'] : (isset($callData['called']) ? $callData['called'] : ''));

        // Determine direction
        $direction = 'inbound';
        if (isset($callData['direction'])) {
            $direction = $callData['direction'];
        } elseif (isset($callData['type'])) {
            if (in_array($callData['type'], array('outbound', 'out'))) {
                $direction = 'outbound';
            }
        }

        // Determine call type
        $disposition = isset($callData['disposition']) ? strtolower($callData['disposition']) : '';
        $callType = 'answered';
        if (strpos($disposition, 'answer') !== false) {
            $callType = 'answered';
        } elseif (strpos($disposition, 'busy') !== false) {
            $callType = 'busy';
        } elseif (strpos($disposition, 'no answer') !== false || strpos($disposition, 'missed') !== false) {
            $callType = 'missed';
        } elseif (strpos($disposition, 'voicemail') !== false) {
            $callType = 'voicemail';
        } else {
            $callType = 'failed';
        }

        // Parse date
        $callDateStr = isset($callData['calldate']) ? $callData['calldate'] : (isset($callData['start_time']) ? $callData['start_time'] : '');
        $callDate = strtotime($callDateStr) ?: time();

        // Find or create third party
        $phoneToSearch = ($direction == 'inbound') ? $callerNumber : $calledNumber;
        $socId = $this->findOrCreateThirdParty($phoneToSearch, isset($callData['src_name']) ? $callData['src_name'] : (isset($callData['caller_name']) ? $callData['caller_name'] : ''));

        // Create call log
        $newCallLog = new CallLog($this->db);
        $newCallLog->call_id = $callId;
        $newCallLog->call_date = date('Y-m-d H:i:s', $callDate);
        $newCallLog->caller_number = $callerNumber;
        $newCallLog->caller_name = isset($callData['src_name']) ? $callData['src_name'] : (isset($callData['caller_name']) ? $callData['caller_name'] : '');
        $newCallLog->called_number = $calledNumber;
        $newCallLog->called_name = isset($callData['dst_name']) ? $callData['dst_name'] : (isset($callData['called_name']) ? $callData['called_name'] : '');
        $newCallLog->direction = $direction;
        $newCallLog->call_type = $callType;
        $newCallLog->duration = isset($callData['duration']) ? intval($callData['duration']) : 0;
        $newCallLog->talk_duration = isset($callData['billsec']) ? intval($callData['billsec']) : 0;
        $newCallLog->fk_soc = $socId ?: null;
        $newCallLog->extension = isset($callData['extension']) ? $callData['extension'] : '';
        $newCallLog->trunk = isset($callData['trunk']) ? $callData['trunk'] : '';
        $newCallLog->disposition = isset($callData['disposition']) ? $callData['disposition'] : '';
        $newCallLog->has_recording = !empty($callData['recordingfile']) ? 1 : 0;

        $result = $newCallLog->create($user);

        if ($result < 0) {
            dol_syslog(__METHOD__.' Error creating call log: '.join(',', $newCallLog->errors), LOG_ERR);
            return false;
        }

        // Download recording if enabled and available
        if (!empty($conf->global->GRANDSTREAMUCM_DOWNLOAD_RECORDINGS) && !empty($callData['recordingfile'])) {
            $this->saveRecording($newCallLog, $callData['recordingfile']);
        }

        return true;
    }

    /**
     * Normalize phone number
     *
     * @param string $phone Phone number
     * @return string Normalized number
     */
    private function normalizePhoneNumber($phone)
    {
        if (empty($phone)) {
            return '';
        }

        // Remove non-numeric characters except +
        return preg_replace('/[^\d+]/', '', $phone);
    }

    /**
     * Find existing third party or create new one
     *
     * @param string $phoneNumber Phone number
     * @param string $name Name
     * @return int|false Third party ID or false
     */
    private function findOrCreateThirdParty($phoneNumber, $name = '')
    {
        global $conf, $user;

        if (empty($phoneNumber)) {
            return false;
        }

        // Search for existing third party by phone
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."societe";
        $sql .= " WHERE (phone = '".$this->db->escape($phoneNumber)."'";
        $sql .= " OR phone LIKE '%".$this->db->escape(str_replace('+', '', $phoneNumber))."%')";
        $sql .= " AND entity IN (".getEntity('societe').")";

        $resql = $this->db->query($sql);
        if ($resql && $this->db->num_rows($resql) > 0) {
            $obj = $this->db->fetch_object($resql);
            return $obj->rowid;
        }

        // Also check contacts
        $sql = "SELECT fk_soc FROM ".MAIN_DB_PREFIX."socpeople";
        $sql .= " WHERE (phone = '".$this->db->escape($phoneNumber)."'";
        $sql .= " OR phone_mobile = '".$this->db->escape($phoneNumber)."'";
        $sql .= " OR phone LIKE '%".$this->db->escape(str_replace('+', '', $phoneNumber))."%')";
        $sql .= " AND entity IN (".getEntity('socpeople').")";
        $sql .= " AND fk_soc IS NOT NULL";

        $resql = $this->db->query($sql);
        if ($resql && $this->db->num_rows($resql) > 0) {
            $obj = $this->db->fetch_object($resql);
            return $obj->fk_soc;
        }

        // Create new third party if auto-create is enabled
        if (!empty($conf->global->GRANDSTREAMUCM_AUTO_CREATE_CONTACTS)) {
            $societe = new Societe($this->db);
            $societe->name = !empty($name) ? $name : $phoneNumber;
            $societe->phone = $phoneNumber;
            $societe->client = 2; // Prospect
            $societe->note_private = 'Créé automatiquement depuis un appel Grandstream';

            $result = $societe->create($user);

            if ($result > 0) {
                dol_syslog(__METHOD__.' Created new third party for '.$phoneNumber, LOG_INFO);
                return $societe->id;
            }
        }

        return false;
    }

    /**
     * Save recording file
     *
     * @param CallLog $callLog Call log object
     * @param string $recordingFile Recording filename
     * @return bool Success
     */
    private function saveRecording($callLog, $recordingFile)
    {
        global $conf;

        $content = $this->downloadRecording($recordingFile);
        if (!$content) {
            return false;
        }

        // Save to Dolibarr document directory
        $dir = $conf->grandstreamucm->dir_output.'/recordings';
        if (!dol_is_dir($dir)) {
            dol_mkdir($dir);
        }

        $filename = $callLog->call_id.'.wav';
        $filepath = $dir.'/'.$filename;

        $result = file_put_contents($filepath, $content);

        if ($result !== false) {
            $callLog->recording_file = $filename;
            $callLog->update($GLOBALS['user']);
            return true;
        }

        return false;
    }

    /**
     * Cron method to sync calls
     *
     * @return int 0 if OK, -1 if KO
     */
    public function cronSyncCalls()
    {
        global $user;

        dol_syslog(__METHOD__.' Start cron sync', LOG_INFO);

        $result = $this->syncCalls($user);

        if ($result >= 0) {
            dol_syslog(__METHOD__.' Cron sync completed: '.$result.' calls', LOG_INFO);
            return 0;
        }

        dol_syslog(__METHOD__.' Cron sync failed', LOG_ERR);
        return -1;
    }
}
