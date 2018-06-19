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
 * Html file replacement support for core forum module
 * @author    Guy Thomas <gthomas@moodlerooms.com>
 * @copyright Copyright (c) 2018 Blackboard Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_ally\componentsupport;

defined ('MOODLE_INTERNAL') || die();

use tool_ally\componentsupport\interfaces\annotation_map;
use tool_ally\local_file;
use tool_ally\models\component;
use tool_ally\componentsupport\traits\html_content;
use tool_ally\componentsupport\interfaces\html_content as iface_html_content;

use moodle_url;

/**
 * Html file / content replacement support for core forum module
 * @author    Guy Thomas <gthomas@moodlerooms.com>
 * @copyright Copyright (c) 2017 / 2018 Blackboard Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class forum_component extends file_component_base implements iface_html_content, annotation_map {

    use html_content;

    protected $type = 'forum';

    protected $tablefields = [
        'forum' => ['intro'],
        'forum_posts' => ['message']
    ];

    public static function component_type() {
        return self::TYPE_MOD;
    }

    public function replace_file_links() {
        if (!$this->module_installed()) {
            return;
        }

        $file = $this->file;

        $area = $file->get_filearea();
        $itemid = $file->get_itemid();
        if ($area === 'post') {
            local_file::update_filenames_in_html('message', $this->type.'_posts', ' id = ? ',
                    ['id' => $itemid], $this->oldfilename, $file->get_filename());
        }
    }

    /**
     * Get discussion html content items.
     * @param int $courseid
     * @param null|int $discussionid
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     */
    private function get_discussion_html_content_items($courseid, $discussionid = null) {
        global $DB;

        if (!$this->module_installed()) {
            return [];
        }

        $array = [];

        // We are going to limit post content to that where user is admin or teacher, etc at course level.
        // Faster than doing it per module instance.
        $userids = $this->get_approved_author_ids_for_context(\context_course::instance($courseid));

        list($userinsql, $userparams) = $DB->get_in_or_equal($userids);

        // Just get discussions - we aren't going to bother with posts.
        $discussions = '{'.$this->type.'_discussions}';
        $posts = '{'.$this->type.'_posts}';

        if (!is_null($discussionid)) {
            $discussionfilter = ' AND fd.id = ? ';
            $params = [$courseid, $discussionid, FORMAT_HTML];
        } else {
            $discussionfilter = '';
            $params = [$courseid, FORMAT_HTML];
        }

        $sql = <<<SQL
            SELECT fp.*
              FROM $discussions fd
              JOIN $posts fp
                ON fd.course = ? $discussionfilter
               AND fp.discussion = fd.id
               AND fp.parent = 0
               AND fp.messageformat = ?
               AND fp.userid $userinsql
SQL;
        $params = array_merge($params, $userparams);
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $row) {
            $array[] = new component(
                $row->id, $this->type, $this->type.'_posts', 'message', $courseid, $row->modified,
                $row->messageformat, $row->subject);
        }
        $rs->close();

        return $array;
    }

    /**
     * Get forum intro content items.
     * @param int $courseid
     * @return array
     * @throws \dml_exception
     */
    private function get_forum_intro_html_content_items($courseid) {
        global $DB;

        if (!$this->module_installed()) {
            return [];
        }

        $array = [];

        $select = "course = ? AND introformat = ? AND intro !=''";
        $rs = $DB->get_recordset_select($this->type, $select, [$courseid, FORMAT_HTML]);
        foreach ($rs as $row) {
            $array[] = new component(
                $row->id, $this->type, $this->type, 'intro', $courseid, $row->timemodified,
                $row->introformat, $row->name);
        }
        $rs->close();

        return $array;
    }

    public function get_course_html_content_items($courseid) {
        if (!$this->module_installed()) {
            return [];
        }

        $introarray = $this->get_forum_intro_html_content_items($courseid);
        $discussionarray = $this->get_discussion_html_content_items($courseid);

        return array_merge($introarray, $discussionarray);
    }

    public function get_annotation_maps($courseid) {
        global $PAGE;

        if (!$this->module_installed()) {
            return [];
        }

        if ($PAGE->pagetype === 'mod-'.$this->type.'-discuss') {
            $params = $PAGE->url->params();
            if (!isset($params['d'])) {
                return [];
            }
            $contentitems = $this->get_discussion_html_content_items($courseid, $params['d']);
        } else {
            $contentitems = $this->get_forum_intro_html_content_items($courseid);
        }

        $posts = [];
        $forumintros = [];
        foreach ($contentitems as $contentitem) {
            if ($contentitem->table === $this->type . '_posts') {
                $posts[$contentitem->id] = $contentitem->entity_id();
            } else if ($contentitem->table === $this->type) {
                list($course, $cm) = get_course_and_cm_from_instance($contentitem->id, $this->type);
                $forumintros[$cm->id] = $contentitem->entity_id();
            }
        }

        return ['posts' => $posts, 'intros' => $forumintros];
    }

    public function get_html_content($id, $table, $field, $courseid = null) {
        if (!$this->module_installed()) {
            return null;
        }

        if ($table === $this->type.'_posts') {
            return $this->std_get_html_content($id, $table, $field, $courseid, 'subject', 'modified');
        }
        return $this->std_get_html_content($id, $table, $field);
    }

    public function get_all_html_content($id) {
        global $DB;

        if (!$this->module_installed()) {
            return [];
        }

        $main = $this->get_html_content($id, $this->type, 'intro');
        $discussions = '{'.$this->type.'_discussions}';
        $posts = '{'.$this->type.'_posts}';
        $sql = <<<SQL
            SELECT fp.*
              FROM $discussions fd
              JOIN $posts fp
               ON fp.discussion = fd.id
               AND fp.parent = 0
               AND fp.messageformat = ?
            WHERE fd.forum = ?
SQL;
        $params = [FORMAT_HTML, $id];
        $posts = $DB->get_records_sql($sql, $params);
        return array_merge([$main], $posts);
    }

    public function replace_html_content($id, $table, $field, $content) {
        return $this->std_replace_html_content($id, $table, $field, $content);
    }

    public function resolve_course_id($id, $table, $field) {
        global $DB;

        if (!$this->module_installed()) {
            return -1;
        }

        if ($table === $this->type) {
            $forum = $DB->get_record($this->type, ['id' => $id]);
            return $forum->course;
        }

        throw new \coding_exception('Invalid table used to recover course id '.$table);
    }

    /**
     * @param int $postid
     * @return int
     * @throws \dml_exception
     */
    private function get_discussion_id_from_post_id($postid) {
        global $DB;
        $post = $DB->get_record($this->type.'_posts', ['id' => $postid]);
        return $post->discussion;
    }

    /**
     * Attempt to make url for content.
     * @param int $id
     * @param string $table
     * @param string $field
     * @param int $courseid
     * @return null|string;
     */
    public function make_url($id, $table, $field = null, $courseid = null) {
        if (!isset($this->tablefields[$table])) {
            return null;
        }
        if ($table === $this->type) {
            list ($course, $cm) = get_course_and_cm_from_instance($id, $this->type);
            return new moodle_url('/mod/'.$this->type.'/view.php?id='.$cm->id).'';
        } else if ($table === $this->type.'_posts') {
            $discussionid = $this->get_discussion_id_from_post_id($id);
            return new moodle_url('/mod/'.$this->type.'/discuss.php?d='.$discussionid.'#p'.$id).'';
        }
        return null;
    }
}