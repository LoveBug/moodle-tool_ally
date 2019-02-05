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
 * Tests for main lib.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2016 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__.'/abstract_testcase.php');

/**
 * Tests for observer.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2016 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_ally_lib_testcase extends tool_ally_abstract_testcase {
    protected function setUp() {
        // Prevent it from creating a backup of the deleted module.
        set_config('coursebinenable', 0, 'tool_recyclebin');
    }

    /**
     * Test file deletion callback.
     */
    public function test_tool_ally_after_file_deleted() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course   = $this->getDataGenerator()->create_course();
        $resource = $this->getDataGenerator()->create_module('resource', ['course' => $course->id]);
        $file     = $this->get_resource_file($resource);
        $time     = time();

        course_delete_module($resource->cmid);

        $deletes = $DB->get_records('tool_ally_deleted_files');

        $this->assertCount(1, $deletes);

        $delete = current($deletes);

        $this->assertEquals($course->id, $delete->courseid);
        $this->assertEquals($file->get_pathnamehash(), $delete->pathnamehash);
        $this->assertEquals($file->get_contenthash(), $delete->contenthash);
        $this->assertEquals($file->get_mimetype(), $delete->mimetype);
        $this->assertGreaterThanOrEqual($time, $delete->timedeleted);
    }
}
