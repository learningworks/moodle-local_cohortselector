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
 * AJAX search handler. Uses YUI AutoComplete.
 *
 * @author      Troy Williams
 * @package     local_cohortselector {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright   2015 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/enrol/cohort/locallib.php');


$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/cohortselector/search.ajax.php');

echo $OUTPUT->header();

// Check access.
require_login();
require_sesskey();

$id     = required_param('id', PARAM_INT); // Get the course identifier parameter.
$query  = required_param('q', PARAM_TEXT); // Get the query text.

$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);
$result = \local_cohortselector\cohortselector::search($course->id, $query);

echo json_encode(array('result' => $result));


