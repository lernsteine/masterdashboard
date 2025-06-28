<?php
defined('MOODLE_INTERNAL') || die();

class block_masterdashboard extends block_base {
    public function init() {
        $this->title = ''; // Kein Blocktitel
    }

    public function get_content() {
        global $USER, $PAGE, $CFG, $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }

        require_once($CFG->libdir . '/completionlib.php');
        require_once($CFG->dirroot . '/course/lib.php');

        $PAGE->requires->css(new moodle_url('/blocks/masterdashboard/styles.css'));

        $courses = enrol_get_users_courses($USER->id, true, '*');

        uasort($courses, function($a, $b) {
            return ($b->enddate ?? 0) <=> ($a->enddate ?? 0);
        });

        $fs = get_file_storage();
        $overdue = '';
        $inprogress = '';
        $completed = '';

        foreach ($courses as $course) {
            if (!$course->enablecompletion) continue;

            $completioninfo = new completion_info($course);
            $iscomplete = $completioninfo->is_course_complete($USER->id);
            $context = context_course::instance($course->id, IGNORE_MISSING);

            $imageurl = $OUTPUT->image_url('i/course');
            if ($fs && $context) {
                $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', false);
                foreach ($files as $file) {
                    if ($file->is_valid_image()) {
                        $imageurl = moodle_url::make_pluginfile_url(
                            $file->get_contextid(), $file->get_component(), $file->get_filearea(),
                            null, $file->get_filepath(), $file->get_filename()
                        );
                        break;
                    }
                }
            }

            $imgtag = html_writer::empty_tag('img', ['src' => $imageurl, 'class' => 'course-thumb', 'alt' => '']);
            $courselink = html_writer::link(
                new moodle_url('/course/view.php', ['id' => $course->id]),
                format_string($course->fullname),
                ['class' => 'coursename']
            );

            $dateinfo = '';
            $status = '';
            if ($iscomplete) {
                $status = 'completed';
                $dateinfo = get_string('completedon', 'block_masterdashboard') . ': ' . date('d.m.Y');
            } elseif (!empty($course->enddate) && time() > $course->enddate) {
                $status = 'overdue';
                $dateinfo = get_string('duedate', 'block_masterdashboard') . ': ' . date('d.m.Y', $course->enddate);
            } else {
                $status = 'inprogress';
                $dateinfo = get_string('enddate', 'block_masterdashboard') . ': ' . (!empty($course->enddate) ? date('d.m.Y', $course->enddate) : '-');
            }

            $info = html_writer::div($courselink, 'coursename') .
                    html_writer::div($dateinfo, 'date');
            $infowrap = html_writer::div($info, 'course-info');

            $card = html_writer::div(
                html_writer::div($imgtag, 'course-thumb-wrapper') . $infowrap,
                'course-card ' . $status
            );

            if ($status == 'completed') {
                $completed .= $card;
            } elseif ($status == 'inprogress') {
                $inprogress .= $card;
            } else {
                $overdue .= $card;
            }
        }

        // 🆕 Mehrsprachiger Begrüßungstext über Sprachdatei
       // $output = '<div class="block_masterdashboard">';
        // $output .= '<div class="dashboardintro">' . get_string('dashboardintro', 'block_masterdashboard') . '</div>';

        if (!empty($overdue)) {
            $output .= '<div class="section">';
            $output .= html_writer::div(get_string("overduecourses", "block_masterdashboard"), "sectiontitle");
            $output .= '<div class="course-grid">' . $overdue . '</div></div>';
        }
        if (!empty($inprogress)) {
            $output .= '<div class="section">';
            $output .= html_writer::div(get_string("inprogresscourses", "block_masterdashboard"), "sectiontitle");
            $output .= '<div class="course-grid">' . $inprogress . '</div></div>';
        }
        if (!empty($completed)) {
            $output .= '<div class="section">';
            $output .= html_writer::div(get_string("completedcourses", "block_masterdashboard"), "sectiontitle");
            $output .= '<div class="course-grid">' . $completed . '</div></div>';
        }

        $output .= '</div>';

        $this->content = new stdClass();
        $this->content->text = $output;
        $this->content->footer = '';
        return $this->content;
    }
}
