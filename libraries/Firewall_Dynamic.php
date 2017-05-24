<?php

/**
 * Firewall Dynamic for Webconfig.
 *
 * @category   apps
 * @package    firewall-dynamic
 * @subpackage libraries
 * @author     eGloo <team@egloo.ca>
 * @copyright  2017 Avantech
 * @license    http://www.gnu.org/copyleft/lgpl.html GNU Lesser General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/firewall_dynamic/
 */

///////////////////////////////////////////////////////////////////////////////
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU Lesser General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU Lesser General Public License for more details.
//
// You should have received a copy of the GNU Lesser General Public License
// along with this program.  If not, see <http://www.gnu.org/licenses/>.
//
///////////////////////////////////////////////////////////////////////////////

///////////////////////////////////////////////////////////////////////////////
// N A M E S P A C E
///////////////////////////////////////////////////////////////////////////////

namespace clearos\apps\firewall_dynamic;

///////////////////////////////////////////////////////////////////////////////
// B O O T S T R A P
///////////////////////////////////////////////////////////////////////////////

$bootstrap = getenv('CLEAROS_BOOTSTRAP') ? getenv('CLEAROS_BOOTSTRAP') : '/usr/clearos/framework/shared';
require_once $bootstrap . '/bootstrap.php';

///////////////////////////////////////////////////////////////////////////////
// T R A N S L A T I O N S
///////////////////////////////////////////////////////////////////////////////

clearos_load_language('firewall_dynamic');

///////////////////////////////////////////////////////////////////////////////
// D E P E N D E N C I E S
///////////////////////////////////////////////////////////////////////////////

use \clearos\apps\base\File as File;
use \clearos\apps\base\Configuration_File as Configuration_File;
use \clearos\apps\base\Folder as Folder;
use \clearos\apps\base\Engine as Engine;
use \clearos\apps\users\User_Factory as User_Factory;
use \clearos\apps\users\User_Manager_Factory;

clearos_load_library('base/File');
clearos_load_library('base/Coniguration_File');
clearos_load_library('base/Folder');
clearos_load_library('base/Engine');
clearos_load_library('users/User_Factory');
clearos_load_library('users/User_Manager_Factory');

// Exceptions
//-----------

use \Exception as Exception;
use \clearos\apps\base\Engine_Exception as Engine_Exception;
use \clearos\apps\base\Validation_Exception as Validation_Exception;

clearos_load_library('base/Engine_Exception');
clearos_load_library('base/Validation_Exception');

///////////////////////////////////////////////////////////////////////////////
// C L A S S
///////////////////////////////////////////////////////////////////////////////

/**
 * Firewall Dynamic for Webconfig.
 *
 * @category   apps
 * @package    firewall-dynamic
 * @subpackage libraries
 * @author     eGloo <team@egloo.ca>
 * @copyright  2017 Avantech
 * @license    http://www.gnu.org/copyleft/lgpl.html GNU Lesser General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/firewall_dynamic/
 */

class Firewall_Dynamic extends Engine
{
    ///////////////////////////////////////////////////////////////////////////////
    // C O N S T A N T S
    ///////////////////////////////////////////////////////////////////////////////

    const FOLDER_RULES = '/var/clearos/firewall_dynamic/rules/';
    const FILE_CONFIG = '/etc/clearos/firewall_dynamic.conf';
    const DEFAULT_WINDOW = 30; // Default Window of 30 minutes until timeout

    ///////////////////////////////////////////////////////////////////////////////
    // V A R I A B L E S
    ///////////////////////////////////////////////////////////////////////////////

    protected $is_loaded = FALSE;
    protected $config = array();


    /**
     * Firewall_Dynamic constructor.
     */

    public function __construct()
    {
        clearos_profile(__METHOD__, __LINE__);
    }

    /**
     * Get mode.
     *
     * @return string block mode (allow_all or block_all)
     * @throws Engine_Exception
     */

    function get_window($rule)
    {
        clearos_profile(__METHOD__, __LINE__);

        if (! $this->is_loaded)
            $this->_load_config();

        if (!isset($this->config[$rule . "_window"]))
            return self::DEFAULT_WINDOW;
        return $this->config[$rule . "_window"];
    }

    /**
     * Get rule.
     *
     * @param String rule
     *
     * @return void
     * @throws Engine_Exception, Validation_Exception
     */

    public function get_rule($rule)
    {
        $rules = $this->get_rules();
        return $rules[$rule];
    }

    /**
     * Get rules.
     *
     * @return void
     * @throws Engine_Exception, Validation_Exception
     */

    public function get_rules()
    {
        clearos_profile(__METHOD__, __LINE__);
        $rules = array();
        $folder = new Folder(self::FOLDER_RULES);
        $files = $folder->get_listing();
        foreach($files as $file) {
            $file = new File(self::FOLDER_RULES . $file);
                
            $xml_source = $file->get_contents();

            $xml = simplexml_load_string($xml_source);
            if ($xml === FALSE)
                throw new Engine_Exception(lang('firewall_dynamic_invalid_rule'), CLEAROS_ERROR);

            $name = (String)$xml->attributes()->name;
            $basename = (String)$xml->attributes()->basename;
            if (!empty($basename))
                clearos_load_language($basename);
            $rules[$name] = array(
                'description' => empty($basename) ? (String)$xml->description : lang((String)$xml->description),
                'enabled' => (int)$xml->enabled,
                'trigger' => empty($basename) ? (String)$xml->trigger : lang((String)$xml->trigger),
                'window' => (int)$xml->window,
                'group' => (String)$xml->group,
            );
        }
        return $rules;
    }

    /**
     * Set rule state.
     *
     * @param boolean state
     * @param String  rule
     *
     * @return void
     * @throws Engine_Exception, Validation_Exception
     */

    public function set_rule_state($state, $rule)
    {
        $file = new File(self::FOLDER_RULES . $rule . '.xml');
        if (!$file->exists())
            throw new Engine_Exception(lang('firewall_dynamic_rule_not_found'), CLEAROS_ERROR);
        $xml_source = $file->get_contents();

        $xml = simplexml_load_string($xml_source);
        if ($xml === FALSE)
            throw new Engine_Exception(lang('firewall_dynamic_invalid_rule'), CLEAROS_ERROR);
        $xml->enabled = $state;
        $xml->asXML(self::FOLDER_RULES . $rule . '.xml');
    }

    /**
     * Create firewall (iptables) rule structure.
     *
     * @param String rule
     *
     * @return void
     * @throws Engine_Exception, Validation_Exception
     */

    public function create_rule($rule, $substitutions = [])
    {
        clearos_profile(__METHOD__, __LINE__);

        $file = new File(self::FOLDER_RULES . $rule . '.xml');
        if (!$file->exists())
            throw new Engine_Exception(lang('firewall_dynamic_rule_not_found'), CLEAROS_ERROR);
            
        $xml_source = $file->get_contents();

        $xml = simplexml_load_string($xml_source);
        if ($xml === FALSE)
            throw new Engine_Exception(lang('firewall_dynamic_invalid_rule'), CLEAROS_ERROR);

        $table = $xml->table->attributes()->name;
        $chain = $xml->table->chain->attributes()->name;
        $rule = $xml->table->chain->rule;

        $cmd = "\$IPTABLES -t " . $table . " ";
        if ($xml->position == 'INSERT')
            $cmd .= "-I $chain ";
        else
            $cmd .= "-A $chain ";
        foreach ($rule->conditions->match as $match) {
            if ($match->attributes()->explicit == null) {
                foreach($match->children() as $key => $value) {
                    if (empty($value) && !array_key_exists($key, $substitutions))
                        continue;
                    $cmd .= "-$key " . (array_key_exists($key, $substitutions) ? $substitutions[$key] : $value) . " ";
                }
                continue;
                
            }
            $params = "";
            foreach($match->children() as $key => $value) {
                echo "$key\n";
                if (empty($value) && !array_key_exists($key, $substitutions))
                    continue;
                $params .= "--$key " . (array_key_exists($key, $substitutions) ? $substitutions[$key] : $value) . " ";
            }
            if (!empty($params))
                $cmd .= "-m " . $match->attributes()->explicit . " " . $params;
        }
        if ($xml->table->chain->rule->jump != null)
            $cmd .= "-j " . $xml->table->chain->rule->jump;
        echo "$cmd\n";
    }

    /**
     * Loads configuration.
     *
     * @return void
     * @throws Engine_Exception
     */

    private function _load_config()
    {
        clearos_profile(__METHOD__, __LINE__);

        $configfile = new Configuration_File(self::FILE_CONFIG);

        $this->config = $configfile->load();

        $this->is_loaded = TRUE;
    }

    /**
     * Generic set routine.
     *
     * @param string $key   key name
     * @param string $value value for the key
     *
     * @return  void
     * @throws Engine_Exception
     */

    private function _set_parameter($key, $value)
    {
        clearos_profile(__METHOD__, __LINE__);

        try {
            $file = new File(self::FILE_CONFIG, TRUE);
            $match = $file->replace_lines("/^$key\s*=\s*/", "$key = $value\n");

            if (!$match)
                $file->add_lines("$key = $value\n");
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }

        $this->is_loaded = FALSE;
    }

    ///////////////////////////////////////////////////////////////////////////////
    // V A L I D A T I O N   M E T H O D S
    ///////////////////////////////////////////////////////////////////////////////

}
