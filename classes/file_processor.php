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
 * File processor for Ally.
 * @package   tool_ally
 * @author    Guy Thomas <gthomas@moodlerooms.com>
 * @copyright Copyright (c) 2017 Blackboard Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_ally;

defined('MOODLE_INTERNAL') || die();

use tool_ally\push_config,
    tool_ally\push_file_updates,
    tool_ally\local_file,
    tool_ally\files_iterator,
    tool_ally\event\push_file_updates_error;

/**
 * File processor for Ally.
 * Can be used to process individual or groups of files.
 *
 * @package   tool_ally
 * @author    Guy Thomas <gthomas@moodlerooms.com>
 * @copyright Copyright (c) 2017 Blackboard Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class file_processor {

    /**
     * Push file updats to Ally without batching, etc.
     *
     * @param push_file_updates $updates
     * @param \stored_file $file
     * @return bool Successfully pushed file.
     */
    private static function push_update(push_file_updates $updates, \stored_file $file) {
        $config = self::get_config();
        if ($config->is_cli_only()) {
            // Skip update, will try after cron cleans up cli only usage.
            push_file_updates_error::create_from_msg(get_string('pushfileserror:skip', 'tool_ally'))->trigger();
            return false;
        }

        // Ignore draft files and files in the recycle bin.
        $filearea = $file->get_filearea();
        if ($filearea === 'draft' || $filearea === 'recyclebin_course') {
            return false;
        }
        $payload = [local_file::to_crud($file)];
        $updates->send($payload);
        return true;
    }

    /**
     * Get ally config.
     * @return null|push_config
     */
    private static function get_config() {
        static $config = null;
        if ($config === null || PHPUNIT_TEST) {
            $config = new push_config();
        }
        return $config;
    }

    /**
     * Push updates for files.
     * @param \stored_file $file;
     * @return bool Successfully pushed file.
     * @throws \Exception
     */
    public static function push_file_update(\stored_file $file) {
        $config = self::get_config();
        if (!$config->is_valid()) {
            return false;
        }

        // Make sure file has a course context - Ally doesn't support files without a course context at the moment.
        // We don't want to throw any errors or it wont be possible to add files outside of courses.
        $context = \context::instance_by_id($file->get_contextid());
        $coursecontext = $context->get_course_context(false);
        if (!$coursecontext) {
            return false;
        }

        $updates = new push_file_updates($config);
        return self::push_update($updates, $file);
    }
}
