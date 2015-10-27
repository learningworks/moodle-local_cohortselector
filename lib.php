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

defined('MOODLE_INTERNAL') || die();

/**
 * This function extends the settings navigation block, if in a course
 * and have correct permissions to add cohort a linkage page will be added.
 *
 * @global type $SITE
 * @param settings_navigation $navigation
 * @param context $context
 * @throws coding_exception
 */
function local_cohortselector_extend_settings_navigation(settings_navigation $navigation, context $context) {
    global $SITE;

    if (!isloggedin()) {
        return;
    }

    if (is_null($navigation) or is_null($context)) {
        return;
    }

    if ($context->instanceid === $SITE->id) {
        return;
    }

    if (!enrol_is_enabled('cohort')) {
        return;
    }

    if (!has_capability('enrol/cohort:config', $context)) {
        return;
    }

    // Only add link when in the context of a course.
    if ($context instanceof context_course) {
        $courseadmin = $navigation->get('courseadmin');
        $users = $courseadmin->get('users');
        if ($users) {
            $url = new moodle_url('/local/cohortselector/manage.php', array('id' => $context->instanceid));
            $users->add(get_string('pluginname', 'local_cohortselector'), $url, navigation_node::TYPE_CUSTOM,
                null, 'localcohortselector', new pix_icon('i/enrolusers', ''));
        }
    }

}
