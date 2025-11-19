<?php
/* Copyright (C) 2024 Your Company
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Class UpdateManager - Handles module updates from GitHub
 */
class GrandstreamUpdateManager
{
    /**
     * @var DoliDB Database handler
     */
    public $db;

    /**
     * @var string Current module version
     */
    public $currentVersion = '1.0.0';

    /**
     * @var string GitHub repository
     */
    private $githubRepo = 'fred-selest/odoo-integration-grandstream';

    /**
     * @var string GitHub API URL
     */
    private $githubApiUrl;

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
        $this->db = $db;
        $this->githubApiUrl = 'https://api.github.com/repos/'.$this->githubRepo;
    }

    /**
     * Check for available updates
     *
     * @return array|false Update info or false on error
     */
    public function checkForUpdates()
    {
        $url = $this->githubApiUrl.'/releases/latest';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Dolibarr-GrandstreamUCM');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            $this->error = 'Erreur cURL: '.$curlError;
            $this->errors[] = $this->error;
            return false;
        }

        if ($httpCode === 404) {
            return array(
                'update_available' => false,
                'current_version' => $this->currentVersion,
                'latest_version' => $this->currentVersion,
                'message' => 'Aucune release trouvée'
            );
        }

        if ($httpCode !== 200) {
            $this->error = 'Erreur HTTP: '.$httpCode;
            $this->errors[] = $this->error;
            return false;
        }

        $releaseData = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error = 'Erreur JSON: '.json_last_error_msg();
            $this->errors[] = $this->error;
            return false;
        }

        $latestVersion = ltrim($releaseData['tag_name'], 'v');
        $updateAvailable = $this->compareVersions($this->currentVersion, $latestVersion);

        // Find download URL
        $downloadUrl = '';
        if (!empty($releaseData['assets'])) {
            foreach ($releaseData['assets'] as $asset) {
                if (substr($asset['name'], -4) === '.zip') {
                    $downloadUrl = $asset['browser_download_url'];
                    break;
                }
            }
        }
        if (empty($downloadUrl)) {
            $downloadUrl = $releaseData['zipball_url'];
        }

        return array(
            'update_available' => $updateAvailable,
            'current_version' => $this->currentVersion,
            'latest_version' => $latestVersion,
            'release_notes' => $releaseData['body'],
            'release_date' => substr($releaseData['published_at'], 0, 10),
            'download_url' => $downloadUrl,
            'html_url' => $releaseData['html_url']
        );
    }

    /**
     * Compare version strings
     *
     * @param string $current Current version
     * @param string $latest Latest version
     * @return bool True if update available
     */
    private function compareVersions($current, $latest)
    {
        $currentParts = array_map('intval', explode('.', $current));
        $latestParts = array_map('intval', explode('.', $latest));

        // Pad arrays
        while (count($currentParts) < 3) {
            $currentParts[] = 0;
        }
        while (count($latestParts) < 3) {
            $latestParts[] = 0;
        }

        for ($i = 0; $i < 3; $i++) {
            if ($latestParts[$i] > $currentParts[$i]) {
                return true;
            } elseif ($latestParts[$i] < $currentParts[$i]) {
                return false;
            }
        }

        return false;
    }

    /**
     * Download and install update
     *
     * @param string $downloadUrl URL to download
     * @return bool Success
     */
    public function downloadAndInstall($downloadUrl)
    {
        global $conf;

        if (empty($downloadUrl)) {
            $this->error = 'URL de téléchargement manquante';
            $this->errors[] = $this->error;
            return false;
        }

        // Download file
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $downloadUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Dolibarr-GrandstreamUCM');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($content)) {
            $this->error = 'Échec du téléchargement: HTTP '.$httpCode;
            $this->errors[] = $this->error;
            return false;
        }

        // Save to temp file
        $tmpFile = tempnam(sys_get_temp_dir(), 'grandstream_update_');
        file_put_contents($tmpFile, $content);

        // Install
        $result = $this->installUpdate($tmpFile);

        // Cleanup
        unlink($tmpFile);

        return $result;
    }

    /**
     * Install update from zip file
     *
     * @param string $zipPath Path to zip file
     * @return bool Success
     */
    private function installUpdate($zipPath)
    {
        // Get module path
        $modulePath = dirname(dirname(__FILE__));
        $parentPath = dirname($modulePath);

        // Create backup
        $backupPath = $modulePath.'_backup_'.date('YmdHis');

        try {
            // Backup current module
            if (!$this->recurseCopy($modulePath, $backupPath)) {
                throw new Exception('Échec de la création de la sauvegarde');
            }

            // Extract zip
            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                throw new Exception('Impossible d\'ouvrir le fichier ZIP');
            }

            $tmpExtract = sys_get_temp_dir().'/grandstream_extract_'.uniqid();
            mkdir($tmpExtract, 0755, true);
            $zip->extractTo($tmpExtract);
            $zip->close();

            // Find module directory in extracted content
            $sourcePath = $this->findModuleDir($tmpExtract, 'dolibarr_grandstreamucm');

            if (!$sourcePath) {
                throw new Exception('Module non trouvé dans le package');
            }

            // Remove old files (except backups)
            $files = scandir($modulePath);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                $filePath = $modulePath.'/'.$file;
                if (is_dir($filePath)) {
                    $this->recurseDelete($filePath);
                } else {
                    unlink($filePath);
                }
            }

            // Copy new files
            if (!$this->recurseCopy($sourcePath, $modulePath)) {
                throw new Exception('Échec de la copie des nouveaux fichiers');
            }

            // Cleanup
            $this->recurseDelete($tmpExtract);
            $this->recurseDelete($backupPath);

            dol_syslog('GrandstreamUCM: Module updated successfully', LOG_INFO);

            return true;

        } catch (Exception $e) {
            // Restore backup
            if (is_dir($backupPath)) {
                $this->recurseDelete($modulePath);
                rename($backupPath, $modulePath);
            }

            $this->error = $e->getMessage();
            $this->errors[] = $this->error;

            dol_syslog('GrandstreamUCM: Update failed - '.$e->getMessage(), LOG_ERR);

            return false;
        }
    }

    /**
     * Find module directory in extracted content
     *
     * @param string $dir Directory to search
     * @param string $moduleName Module name to find
     * @return string|false Module path or false
     */
    private function findModuleDir($dir, $moduleName)
    {
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir.'/'.$item;

            if ($item === $moduleName && is_dir($path)) {
                return $path;
            }

            if (is_dir($path)) {
                $found = $this->findModuleDir($path, $moduleName);
                if ($found) {
                    return $found;
                }
            }
        }

        return false;
    }

    /**
     * Recursively copy directory
     *
     * @param string $src Source
     * @param string $dst Destination
     * @return bool Success
     */
    private function recurseCopy($src, $dst)
    {
        $dir = opendir($src);
        if (!$dir) {
            return false;
        }

        if (!is_dir($dst)) {
            mkdir($dst, 0755, true);
        }

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $srcPath = $src.'/'.$file;
            $dstPath = $dst.'/'.$file;

            if (is_dir($srcPath)) {
                $this->recurseCopy($srcPath, $dstPath);
            } else {
                copy($srcPath, $dstPath);
            }
        }

        closedir($dir);
        return true;
    }

    /**
     * Recursively delete directory
     *
     * @param string $dir Directory to delete
     * @return bool Success
     */
    private function recurseDelete($dir)
    {
        if (!is_dir($dir)) {
            return true;
        }

        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            if (is_dir($path)) {
                $this->recurseDelete($path);
            } else {
                unlink($path);
            }
        }

        return rmdir($dir);
    }

    /**
     * Cron method to check for updates
     *
     * @return int 0 if OK, -1 if KO
     */
    public function cronCheckUpdates()
    {
        global $conf, $user;

        dol_syslog('GrandstreamUCM: Checking for updates', LOG_INFO);

        $updateInfo = $this->checkForUpdates();

        if ($updateInfo && $updateInfo['update_available']) {
            // Store notification in database
            $message = sprintf(
                'Une nouvelle version (%s) de Grandstream UCM est disponible. Version actuelle: %s',
                $updateInfo['latest_version'],
                $this->currentVersion
            );

            // You could send email notification or create an event here
            dol_syslog('GrandstreamUCM: '.$message, LOG_INFO);

            // Store last check result
            dolibarr_set_const(
                $this->db,
                'GRANDSTREAMUCM_UPDATE_AVAILABLE',
                $updateInfo['latest_version'],
                'chaine',
                0,
                '',
                $conf->entity
            );
        } else {
            dolibarr_del_const($this->db, 'GRANDSTREAMUCM_UPDATE_AVAILABLE', $conf->entity);
        }

        dolibarr_set_const(
            $this->db,
            'GRANDSTREAMUCM_LAST_UPDATE_CHECK',
            date('Y-m-d H:i:s'),
            'chaine',
            0,
            '',
            $conf->entity
        );

        return 0;
    }
}
