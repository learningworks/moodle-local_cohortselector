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
 *
 * @author      Troy Williams
 * @package     local_cohortselector {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright   2015 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/enrol/cohort/locallib.php');
require_once($CFG->dirroot . '/local/cohortselector/cohortselector_form.php');

$id = required_param('id', PARAM_INT); // Get the course identifier parameter.

$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);
$context = context_course::instance($course->id, MUST_EXIST);

$pageurl = new moodle_url('/local/cohortselector/manage.php');
$pageurl->param('id', $course->id);

$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('admin');

navigation_node::override_active_url(new moodle_url('/enrol/instances.php', array('id' => $course->id)));

require_login($course);
require_capability('moodle/course:enrolconfig', $context);
require_capability('enrol/cohort:config', $context);

// If not enabled redirect enrolment management page.
if (!enrol_is_enabled('cohort')) {
    notice(get_string('notenabled', 'local_cohort'), new moodle_url('/admin/settings.php?section=manageenrols'));
}

if (optional_param('links_clearbutton', 0, PARAM_RAW) && confirm_sesskey()) {
    redirect($pageurl);
}

// Get the cohort enrolment plugin.
$enrol = enrol_get_plugin('cohort');
// Get role identifier, default to student.
$roleid = $enrol->get_config('roleid', null);
if (is_null($roleid)) {
    $student = get_archetype_roles('student');
    $student = reset($student);
    $roleid = $plugin->get_config('roleid', $student->id);
}

if (!$enrol->get_newinstance_link($course->id)) {
    redirect(new moodle_url('/enrol/instances.php', array('id' => $course->id, '')));
}

$mform = new cohortselector_form($pageurl->out(false), array('course' => $course));
// Redirect to instance page on cancel.
if ($mform->is_cancelled()) {
    redirect(new moodle_url('/enrol/instances.php', array('id' => $course->id)));
}
// Handle the add and the removes.
if ($mform->is_submitted()) {
    if (optional_param('returntocourse', false, PARAM_BOOL)) {
        redirect(new moodle_url('/course/view.php', array('id' => $course->id)));
    }

    $data = $mform->get_data();
    // Process cohorts to be added.
    if (isset($data->addbutton) && !empty($data->cohortselector_add)) {
        foreach ($data->cohortselector_add as $courseidtolink) {
            if (!empty($courseidtolink)) { // Because of formlib selectgroups.
                $enrol->add_instance($course, array('customint1' => $courseidtolink, 'roleid' => $roleid));
            }
        }
        $trace = new \null_progress_trace();
        enrol_cohort_sync($trace, $course->id);
        redirect(new moodle_url('/local/cohortselector/manage.php', array('id' => $course->id)));
    }
    // Process cohorts to be removed.
    if (isset($data->removebutton) && !empty($data->cohortselector_remove)) {
        list($insql, $inparams) = $DB->get_in_or_equal($data->cohortselector_remove, SQL_PARAMS_NAMED);
        $params = array_merge(array('courseid' => $data->id), $inparams);
        $instances = $DB->get_records_select('enrol',
            "enrol = 'cohort' AND courseid = :courseid AND customint1 ". $insql,
            $params);
        foreach ($instances as $instance) {
            $enrol->delete_instance($instance);
        }
        $trace = new \null_progress_trace();
        enrol_cohort_sync($trace, $course->id);
        redirect(new moodle_url('/local/cohortselector/manage.php', array('id' => $course->id)));
    }
}

$PAGE->set_heading($course->fullname);
$PAGE->set_title(get_string('pluginname', 'local_cohortselector'));
echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
