<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Version information class
 * @package tool_ally
 * @author    Guy Thomas <gthomas@moodlerooms.com>
 * @copyright Copyright (c) 2017 Blackboard Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_ally;

defined('MOODLE_INTERNAL') || die();

use core_component,
    core_plugin_manager;

/**
 * Version information class
 * @package tool_ally
 * @author    Guy Thomas <gthomas@moodlerooms.com>
 * @copyright Copyright (c) 2017 Blackboard Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class version_information {

    /**
     * @var bool|stdClass
     */
    public $core;

    /**
     * @var bool|stdClass
     */
    public $toolally;

    /**
     * @var bool|stdClass
     */
    public $filterally;

    /**
     * @var bool|stdClass
     */
    public $reportally;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->core = $this->get_component_version('core');

        $this->toolally = $this->get_component_version('tool_ally');

        $this->filterally = $this->get_component_version('filter_ally');
        $this->filterally->active = $this->check_filter_active();

        $this->reportally = $this->get_component_version('report_allylti');
    }

    /**
     * Returns the version information of an installed component.
     *
     * @param string $component component name
     * @return stdClass|bool version data or false if the component is not found
     */
    private function get_component_version($component) {
        global $CFG;

        list($type, $name) = core_component::normalize_component($component);

        // Get Moodle core version.
        if ($type === 'core') {
            return (object) [
                'version' => $CFG->version,
                'release' => $CFG->release,
                'branch' => $CFG->branch
            ];
        }

        // Get plugin version.
        $pluginman = core_plugin_manager::instance();
        $plug = $pluginman->get_plugin_info($component);
        if (!$plug) {
            return false;
        }
        $plugin = new \stdClass();
        $plugin->version = $plug->versiondb;
        $plugin->requires = $plug->versionrequires;
        $plugin->release = $plug->release;

        return $plugin;
    }

    protected function check_filter_active() {
        return !empty(filter_get_global_states()['ally']);
    }
}
