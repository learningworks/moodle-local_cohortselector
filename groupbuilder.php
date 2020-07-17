<?php
/* This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/enrol/cohort/locallib.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->libdir . '/grouplib.php');

$id = required_param('id', PARAM_INT); // Get the course identifier parameter.
$makegroups = optional_param('makegroups', null, PARAM_TEXT);

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
$context = context_course::instance($course->id, MUST_EXIST);
$pageurl = new moodle_url('/local/cohortselector/groupbuilder.php');
$pageurl->param('id', $course->id);
$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_heading($course->fullname);
$PAGE->set_title(get_string('creategroupsfromcohorts', 'local_cohortselector'));
$PAGE->set_pagelayout('admin');

require_login($course);
require_capability('moodle/course:enrolconfig', $context);
require_capability('moodle/course:managegroups', $context);

// If not enabled redirect enrolment management page.
if (!enrol_is_enabled('cohort')) {
    notice(
        get_string('notenabled', 'local_cohort'),
        new moodle_url('/admin/settings.php?section=manageenrols')
    );
}
$courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);
$groupsurl = new moodle_url('/group/index.php', ['id' => $course->id]);
// Making some SQL.
$selectfields = "SELECT e.*,
                        c.name as cohortname,
                        c.idnumber as cohortidnumber ";
$basesql = "FROM {enrol} e
            JOIN {cohort} c ON c.id = e.customint1 AND enrol = 'cohort'
           WHERE courseid = :courseid AND (customint2 = :customint2 OR customint2 IS NULL)";
$params = ['enrol' => 'cohort', 'courseid' => $course->id, 'customint2' => 0];
$count = $DB->count_records_sql("SELECT COUNT(1) " . $basesql, $params);
if ($count == 0) {
    redirect(
        $groupsurl,
        get_string('nocohortswithoutgroups', 'local_cohortselector'),
        2
    );
} else if (empty($makegroups)) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('creategroupsquestion', 'local_cohortselector'));
    $url = clone($PAGE->url);
    $url->param('makegroups', sesskey());
    $createbutton = new single_button(
        $url, get_string('creategroupsandlinkcohorts', 'local_cohortselector')
    );
    $returnbutton = new single_button(
        $courseurl,
        get_string('returntocourse', 'local_cohortselector'),
        'get',
        false
    );
    echo $OUTPUT->render($createbutton);
    echo $OUTPUT->render($returnbutton);
    echo $OUTPUT->footer();
} else if ($makegroups == sesskey()) {
    $enrolmentplugin = enrol_get_plugin('cohort');
    $cohortwithoutgroups = $DB->get_records_sql($selectfields . $basesql, $params);
    foreach ($cohortwithoutgroups as $cohortwithoutgroup) {
        if (trim($cohortwithoutgroup->cohortidnumber) !== '') {
            $group = groups_get_group_by_idnumber($course->id, $cohortwithoutgroup->cohortidnumber);
        } else {
            $group = groups_get_group_by_name($course->id, $cohortwithoutgroup->cohortname);
        }
        if (!$group) {
            $group = new stdClass();
            $group->courseid = $course->id;
            $group->name = $cohortwithoutgroup->cohortname;
            $group->idnumber = $cohortwithoutgroup->cohortidnumber;
            $group->id = groups_create_group($group);
        }
        $cohortwithoutgroup->customint2 = $group->id;
        $enrolmentplugin->update_instance($cohortwithoutgroup, $cohortwithoutgroup); // Bizarre I know.
    }
    redirect($groupsurl);
} else {
    redirect($courseurl);
}
