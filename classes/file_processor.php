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
    tool_ally\files_iterator;

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
     */
    private static function push_update(push_file_updates $updates, \stored_file $file) {
        // Ignore draft files and files in the recycle bin.
        $filearea = $file->get_filearea();
        if ($filearea === 'draft' || $filearea === 'recyclebin_course') {
            return;
        }
        $payload = [local_file::to_crud($file)];
        $updates->send($payload);
    }

    /**
     * Push file updates to Ally without batching, etc.
     *
     * @param push_file_updates $updates
     * @param files_iterator $files
     * @throws \Exception
     */
    private static function push_updates(push_file_updates $updates, files_iterator $files) {
        $payload = [];
        try {
            foreach ($files as $file) {
                // Ignore draft files and files in the recycle bin.
                $filearea = $file->get_filearea();
                if ($filearea === 'draft' || $filearea === 'recyclebin_course') {
                    continue;
                }
                $payload[] = local_file::to_crud($file);
            }
            if (!empty($payload)) {
                $updates->send($payload);
            }
        } catch (\Exception $e) {
            // Don't throw any errors - if it fails then the scheduled task will take care of it.
            unset($payload);
        }
    }

    /**
     * Get ally config.
     * @return null|push_config
     */
    private static function get_config() {
        static $config = null;
        if ($config === null) {
            $config = new push_config();
        }
        return $config;
    }

    /**
     * Push updates for files.
     * @param files_iterator $files
     * @throws \Exception
     */
    public static function push_file_updates(files_iterator $files) {
        $config = self::get_config();
        if (!$config->is_valid()) {
            return;
        }
        $updates = new push_file_updates($config);
        self::push_updates($updates, $files);
    }

    /**
     * Push updates for files.
     * @param \stored_file $file;
     * @throws \Exception
     */
    public static function push_file_update(\stored_file $file) {
        $config = self::get_config();
        if (!$config->is_valid()) {
            return;
        }

        // Make sure file has a course context - Ally doesn't support files without a course context at the moment.
        // We don't want to throw any errors or it wont be possible to add files outside of courses.
        $context = \context::instance_by_id($file->get_contextid());
        $coursecontext = $context->get_course_context(false);
        if (!$coursecontext) {
            return;
        }

        $updates = new push_file_updates($config);
        self::push_update($updates, $file);
    }
}