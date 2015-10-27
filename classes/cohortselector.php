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
 * Main class for searching and getting current cohorts in a course.
 *
 * @author      Troy Williams
 * @package     local_courseselector {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright   2015 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_cohortselector;

defined('MOODLE_INTERNAL') || die();

class cohortselector {
    /**
     * @var int limit on results returned from sql query.
     */
    const MAX_RESULTS = 100;

    /**
     * Get result object that contains the current cohort enrolment instances
     * for a given course.
     *
     * @param $courseid
     * @return \stdClass
     * @throws \coding_exception
     */
    public static function current_cohorts($courseid) {
        global $DB;

        $result = new \stdClass();
        $result->matches = 0;
        $result->results = array();

        // Build count sql.
        $countsql = "SELECT
                      COUNT(1)
                       FROM {cohort} c
                      WHERE c.id IN (SELECT e.customint1
                                       FROM {enrol} e
                                      WHERE e.enrol = 'cohort'
                                        AND e.courseid = :courseid)";

        // Build select sql.
        $selectsql = "SELECT c.*
                        FROM {cohort} c
                       WHERE c.id IN (SELECT e.customint1
                                        FROM {enrol} e
                                       WHERE e.enrol = 'cohort'
                                         AND e.courseid = :courseid)
                    ORDER BY c.name ASC";

        // Add courseid to params.
        $params = array('courseid' => $courseid);
        // Get the raw count.
        $result->matches = $DB->count_records_sql($countsql, $params);
        if ($result->matches) {
            // Make label.
            $result->label = get_string('activecohortinstances', 'local_cohortselector', $result->matches);
            // Fetch cohorts.
            $cohorts = $DB->get_records_sql($selectsql, $params);
            foreach ($cohorts as $cohort) {
                $name = shorten_text(format_string($cohort->name), 25, true);
                $name = \core_text::strtoupper($name);
                $description = shorten_text(format_string($cohort->description), 80, true);
                $option = sprintf('%-25s %s', $name, $description);
                $result->results[$cohort->id] = $option;
            }
        } else {
            $result->label = get_string('noactivecohorts', 'local_cohortselector');
        }

        // Return result object;
        return $result;
    }

    /**
     * Search for available cohorts that can be added to a given course.
     *
     * @param $courseid
     * @param $query
     * @return \stdClass
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function search($courseid, $query) {
        global $DB;

        $result = new \stdClass();
        $result->label = '';
        $result->query = $query;
        $result->maxlimit = self::MAX_RESULTS;
        $result->matches = 0;
        $result->results = array();

        $wheresql = array(); // Will imploded with AND.

        // Build sql for excluded courses.
        $excludesql = '';
        $excludeparams = array();
        $existing = $DB->get_records('enrol', array('enrol' => 'cohort', 'courseid' => $courseid), '', 'customint1, id');
        if ($existing) {
            $excludes = array_keys($existing);
            list($excludesql, $excludeparams) = $DB->get_in_or_equal($excludes, SQL_PARAMS_NAMED, 'ex', false);
            $excludesql = 'c.id ' . $excludesql;
            $wheresql[] = $excludesql;
        }

        // Build search sql.
        $searchsql = '';
        $searchparams = array();
        if (!empty($query)) {
            $searchanywhere = get_user_preferences('cohortselector_option_searchanywhere', false);
            if ($searchanywhere) {
                $query = '%' . $query . '%';
            } else {
                $query = $query . '%';
            }
            $searchfields = array('c.name');
            if (get_user_preferences('cohortselector_option_idnumber', false)) {
                $searchfields[] = 'c.idnumber';
            }
            if (get_user_preferences('cohortselector_option_description', false)) {
                $searchfields[] = 'c.description';
            }
            for ($i = 0; $i < count($searchfields); $i++) {
                $searchlikes[$i] = $DB->sql_like($searchfields[$i], ":s{$i}", false, false);
                $searchparams["s{$i}"] = $query;
            }
            $searchsql = ' (' . implode(' OR ', $searchlikes) . ')';
            $wheresql[] = $searchsql;
        }

        // Make WHERE statement.
        $where = '';
        if (count($wheresql) > 1) {
            $where = 'WHERE ' . implode(' AND ', $wheresql);
        } else if (count($wheresql) == 1) {
            $where = 'WHERE ' . reset($wheresql);
        }

        // Put all the params together.
        $params = array_merge(array('contextlevel' => CONTEXT_COURSE), $excludeparams, $searchparams);
        // Build count statement.
        $countsql = "SELECT
                      COUNT(1)
                       FROM {cohort} c
                     $where";
        // Get the raw count.
        $result->matches = $DB->count_records_sql($countsql, $params);
        if ($result->matches <= $result->maxlimit) {
            // Build select statement.
            $sql = "SELECT c.*
                      FROM {cohort} c
                 LEFT JOIN {context} ctx ON (ctx.instanceid = c.id AND ctx.contextlevel = :contextlevel)
                    $where
                  ORDER BY c.name ASC";
            // Get the cohorts.
            $cohorts = $DB->get_records_sql($sql, $params, 0, $result->maxlimit);
            foreach ($cohorts as $cohort) {
                $name = shorten_text(format_string($cohort->name), 25, true);
                $name = \core_text::strtoupper($name);
                $description = shorten_text(format_string($cohort->description), 80, true);
                $option = sprintf('%s %s', $name, $description);
                $result->results[$cohort->id] = $option;
            }
        }

        // Build the label.
        if ($result->matches > $result->maxlimit) {
            if ($result->query) {
                $result->label = get_string('toomanycohortsmatchsearch', 'local_cohortselector', $result);
            } else {
                $result->label = get_string('toomanycohortstoshow', 'local_cohortselector', $result->matches);
            }
        } else {
            if (!empty($result->removed)) {
                $result->label = get_string('cohortsmatchingsearchremoved', 'local_cohortselector', $result);
            } else {
                $result->label = get_string('cohortsmatchingsearch', 'local_cohortselector', $result->matches);
            }
        }

        // Return result object;
        return $result;
    }

}
