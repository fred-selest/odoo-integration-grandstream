<?php
/* Copyright (C) 2024 Your Company
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \defgroup grandstreamucm Module GrandstreamUCM
 * \brief Grandstream UCM integration module for Dolibarr
 */

/**
 * \file htdocs/grandstreamucm/core/modules/modGrandstreamUCM.class.php
 * \ingroup grandstreamucm
 * \brief Module descriptor for Grandstream UCM integration
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Class to describe and enable module GrandstreamUCM
 */
class modGrandstreamUCM extends DolibarrModules
{
    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        global $langs, $conf;

        $this->db = $db;

        // Module ID (must be unique)
        $this->numero = 500100;

        // Module family
        $this->family = "interface";
        $this->module_position = '90';

        // Module name
        $this->name = preg_replace('/^mod/i', '', get_class($this));

        // Module description
        $this->description = "Integration with Grandstream UCM for call logs and recordings";
        $this->descriptionlong = "This module provides integration with Grandstream UCM to retrieve call logs, display call history in contact records, and play call recordings directly from Dolibarr.";

        // Module version
        $this->version = '1.0.0';

        // Author
        $this->editor_name = 'Your Company';
        $this->editor_url = 'https://www.yourcompany.com';

        // Module constants
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);

        // Module picture
        $this->picto = 'phone';

        // Data directories to create when module is enabled
        $this->dirs = array(
            "/grandstreamucm/temp",
            "/grandstreamucm/recordings"
        );

        // Config pages
        $this->config_page_url = array("setup.php@grandstreamucm");

        // Dependencies
        $this->hidden = false;
        $this->depends = array();
        $this->requiredby = array();
        $this->conflictwith = array();
        $this->langfiles = array("grandstreamucm@grandstreamucm");

        // Constants
        $this->const = array(
            1 => array('GRANDSTREAMUCM_MYCONSTANT', 'chaine', 'avalue', 'This is a constant to add', 1, 'current', 1)
        );

        // Array to add new pages in new tabs
        $this->tabs = array(
            'thirdparty:+grandstreamucm:Calls:grandstreamucm@grandstreamucm:$user->rights->grandstreamucm->read:/grandstreamucm/thirdparty_calls.php?id=__ID__'
        );

        // Dictionaries
        $this->dictionaries = array();

        // Boxes/Widgets
        $this->boxes = array(
            0 => array(
                'file' => 'grandstreamucmwidget1.php@grandstreamucm',
                'note' => 'Widget for recent calls',
                'enabledbydefaulton' => 'Home'
            )
        );

        // Cronjobs
        $this->cronjobs = array(
            0 => array(
                'label' => 'Sync Grandstream UCM Calls',
                'jobtype' => 'method',
                'class' => '/grandstreamucm/class/grandstreamucm.class.php',
                'objectname' => 'GrandstreamUCM',
                'method' => 'cronSyncCalls',
                'parameters' => '',
                'comment' => 'Synchronize call logs from Grandstream UCM',
                'frequency' => 15,
                'unitfrequency' => 60,
                'status' => 1,
                'test' => '$conf->grandstreamucm->enabled',
                'priority' => 50
            )
        );

        // Permissions
        $this->rights = array();
        $this->rights_class = 'grandstreamucm';

        $r = 0;

        $this->rights[$r][0] = $this->numero + $r + 1;
        $this->rights[$r][1] = 'Read call logs';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'read';
        $r++;

        $this->rights[$r][0] = $this->numero + $r + 1;
        $this->rights[$r][1] = 'Create/Update call logs';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'write';
        $r++;

        $this->rights[$r][0] = $this->numero + $r + 1;
        $this->rights[$r][1] = 'Delete call logs';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'delete';
        $r++;

        $this->rights[$r][0] = $this->numero + $r + 1;
        $this->rights[$r][1] = 'Configure module';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'admin';
        $r++;

        // Menus
        $this->menu = array();
        $r = 0;

        // Top menu
        $this->menu[$r++] = array(
            'fk_menu' => '',
            'type' => 'top',
            'titre' => 'GrandstreamUCM',
            'prefix' => img_picto('', $this->picto, 'class="paddingright pictofixedwidth valignmiddle"'),
            'mainmenu' => 'grandstreamucm',
            'leftmenu' => '',
            'url' => '/grandstreamucm/index.php',
            'langs' => 'grandstreamucm@grandstreamucm',
            'position' => 1000 + $r,
            'enabled' => '$conf->grandstreamucm->enabled',
            'perms' => '$user->rights->grandstreamucm->read',
            'target' => '',
            'user' => 2
        );

        // Left menu - Call logs
        $this->menu[$r++] = array(
            'fk_menu' => 'fk_mainmenu=grandstreamucm',
            'type' => 'left',
            'titre' => 'Call Logs',
            'prefix' => img_picto('', 'object_list', 'class="paddingright pictofixedwidth"'),
            'mainmenu' => 'grandstreamucm',
            'leftmenu' => 'grandstreamucm_calllogs',
            'url' => '/grandstreamucm/call_list.php',
            'langs' => 'grandstreamucm@grandstreamucm',
            'position' => 1000 + $r,
            'enabled' => '$conf->grandstreamucm->enabled',
            'perms' => '$user->rights->grandstreamucm->read',
            'target' => '',
            'user' => 2
        );

        // Left menu - Statistics
        $this->menu[$r++] = array(
            'fk_menu' => 'fk_mainmenu=grandstreamucm',
            'type' => 'left',
            'titre' => 'Statistics',
            'prefix' => img_picto('', 'stats', 'class="paddingright pictofixedwidth"'),
            'mainmenu' => 'grandstreamucm',
            'leftmenu' => 'grandstreamucm_stats',
            'url' => '/grandstreamucm/stats.php',
            'langs' => 'grandstreamucm@grandstreamucm',
            'position' => 1000 + $r,
            'enabled' => '$conf->grandstreamucm->enabled',
            'perms' => '$user->rights->grandstreamucm->read',
            'target' => '',
            'user' => 2
        );
    }

    /**
     * Function called when module is enabled.
     *
     * @param string $options Options when enabling module
     * @return int 1 if OK, 0 if KO
     */
    public function init($options = '')
    {
        global $conf, $langs;

        $result = $this->_load_tables('/grandstreamucm/sql/');
        if ($result < 0) {
            return -1;
        }

        // Create default configuration values
        $this->_init_config();

        return $this->_init($options);
    }

    /**
     * Function called when module is disabled.
     *
     * @param string $options Options when disabling module
     * @return int 1 if OK, 0 if KO
     */
    public function remove($options = '')
    {
        $sql = array();
        return $this->_remove($sql, $options);
    }

    /**
     * Initialize configuration values
     */
    private function _init_config()
    {
        global $conf;

        // Default configuration values
        $configs = array(
            'GRANDSTREAMUCM_HOST' => '',
            'GRANDSTREAMUCM_PORT' => '8089',
            'GRANDSTREAMUCM_USE_HTTPS' => '1',
            'GRANDSTREAMUCM_USERNAME' => '',
            'GRANDSTREAMUCM_PASSWORD' => '',
            'GRANDSTREAMUCM_SYNC_INTERVAL' => '15',
            'GRANDSTREAMUCM_DAYS_TO_SYNC' => '30',
            'GRANDSTREAMUCM_AUTO_CREATE_CONTACTS' => '1',
            'GRANDSTREAMUCM_DOWNLOAD_RECORDINGS' => '1',
            'GRANDSTREAMUCM_LAST_SYNC' => ''
        );

        foreach ($configs as $key => $value) {
            if (!isset($conf->global->$key)) {
                dolibarr_set_const($this->db, $key, $value, 'chaine', 0, '', $conf->entity);
            }
        }
    }
}
