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
 * Provides course categorization for the masterdashboard block.
 *
 * @package   block_masterdashboard
 * @copyright 2025 Ralf Hagemeister <ralf.hagemeister@lernsteine.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_masterdashboard;

/**
 * Library class for block_masterdashboard.
 */
class lib {

    /**
     * Returns categorized course data for the current user.
     *
     * @param \renderer_base $output Renderer object.
     * @return array Categorized course data.
     */
    public static function get_user_courses_data(\renderer_base $output): array {
        global $USER, $CFG;

        include_once($CFG->libdir . '/completionlib.php');

        $courses = enrol_get_users_courses($USER->id, true, '*');

        $categorized = [
            'overdue' => ['courses' => []],
            'inprogress' => ['courses' => []],
            'completed' => ['courses' => []],
        ];

        $dateformat = '%A, %d.%m.%Y';

        foreach ($courses as $course) {
            if ($course->id == SITEID) {
                continue;
            }

            $context = \context_course::instance($course->id);
            $completion = new \completion_info($course);
            $completiondata = $completion->get_completions($USER->id);

            $completed = true;
            foreach ($completiondata as $data) {
                if (!$data->is_complete()) {
                    $completed = false;
                    break;
                }
            }

            $enddate = $course->enddate ? userdate($course->enddate, $dateformat)
                : get_string('nocourseenddate', 'block_masterdashboard');

            $completiondate = $completed ? userdate(time(), $dateformat) : '';

            // Get course image from Moodle's file storage system.
            $image = '';
            $fs = get_file_storage();
            $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', false);

            foreach ($files as $file) {
                if ($file->is_valid_image()) {
                    $image = \moodle_url::make_pluginfile_url(
                        $file->get_contextid(),
                        $file->get_component(),
                        $file->get_filearea(),
                        $file->get_itemid(),
                        $file->get_filepath(),
                        $file->get_filename()
                    );
                    break;
                }
            }

            $courseurl = new \moodle_url('/course/view.php', ['id' => $course->id]);

            $courseinfo = [
                'id' => $course->id,
                'name' => $course->fullname,
                'enddate' => $enddate,
                'completiondate' => $completiondate,
                'image' => $image,
                'url' => $courseurl->out(false),
            ];

            if ($completed) {
                $categorized['completed']['courses'][] = $courseinfo;
            } else if ($course->enddate && time() > $course->enddate) {
                $courseinfo['duedate'] = userdate($course->enddate, $dateformat);
                $categorized['overdue']['courses'][] = $courseinfo;
            } else {
                $categorized['inprogress']['courses'][] = $courseinfo;
            }
        }

        return $categorized;
    }
}
