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

defined('MOODLE_INTERNAL') || die();

/**
 * Main form, uses YUI Autocomplete javascript to search cohorts.
 *
 * @author      Troy Williams
 * @package     local_cohortselector {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright   2015 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->libdir . '/formslib.php');

class cohortselector_form extends moodleform {

    protected $course;

    public function definition() {
        global $PAGE;

        $searchtext = optional_param('cohortselector_searchtext', '', PARAM_TEXT);

        $mform  = $this->_form;
        $course = $this->_customdata['course'];
        $this->course = $course;

        $mform->disable_form_change_checker();

        $mform->addElement('html', html_writer::tag('h1', get_string('pluginname', 'local_cohortselector')));

        $currentcohorts = \local_cohortselector\cohortselector::current_cohorts($course->id);
        if (empty($currentcohorts->results)) {
            $currentcohorts->results = array('');
        }
        $currentcoursesdata = array($currentcohorts->label => $currentcohorts->results);

        $mform->addElement('selectgroups', 'cohortselector_remove', get_string('activecohorts', 'local_cohortselector'), $currentcoursesdata,
            array('size' => 10, 'multiple' => true));
        $mform->addElement('submit', 'removebutton', get_string('removeselected', 'local_cohortselector'));

        $mform->addElement('html', html_writer::empty_tag('br'));

        $found = \local_cohortselector\cohortselector::search($course->id, $searchtext);
        if (empty($found->results)) {
            $found->results = array('');
        }
        $foundcoursesdata = array($found->label => $found->results);

        $mform->addElement('selectgroups', 'cohortselector_add', '', $foundcoursesdata, array('size' => 10, 'multiple' => true));

        $searchgroup = array();
        $searchgroup[] = $mform->createElement('text', 'cohortselector_searchtext');
        $mform->setType('cohortselector_searchtext', PARAM_TEXT);
        $searchgroup[] = $mform->createElement('submit', 'cohortselector_searchbutton', get_string('search'));
        $mform->registerNoSubmitButton('cohortselector_searchbutton');
        $searchgroup[] = $mform->createElement('submit', 'cohortselector_clearbutton', get_string('clear'));
        $mform->registerNoSubmitButton('cohortselector_clearbutton');
        $searchgroup[] = $mform->createElement('submit', 'addbutton', get_string('addselected', 'local_cohortselector'));
        $mform->addGroup($searchgroup, 'searchgroup', get_string('search') , array(''), false);

        $mform->addElement('checkbox', 'cohortselector_option_searchanywhere', get_string('searchanywhere', 'local_cohortselector'));
        user_preference_allow_ajax_update('cohortselector_option_searchanywhere', 'bool');
        $searchanywhere = get_user_preferences('cohortselector_option_searchanywhere', true);
        $this->set_data(array('cohortselector_option_searchanywhere' => $searchanywhere));

        $mform->addElement('checkbox', 'cohortselector_option_description',
            get_string('includeinsearch', 'local_cohortselector', get_string('description')));

        user_preference_allow_ajax_update('cohortselector_option_description', 'bool');
        $includedescription = get_user_preferences('cohortselector_option_description', true);
        $this->set_data(array('cohortselector_option_description' => $includedescription));


        $mform->addElement('hidden', 'id', null);
        $mform->setType('id', PARAM_INT);
        $this->set_data(array('id' => $course->id));

        $actionbuttongroup = array();
        $actionbuttongroup[] =& $mform->createElement('submit', 'returntoenrolmentmethods', get_string('returntoenrolmentmethods', 'local_cohortselector'));
        $actionbuttongroup[] =& $mform->createElement('submit', 'returntocourse', get_string('returntocourse', 'local_cohortselector'));
        $actionbuttongroup[] =& $mform->createElement('cancel', 'cancel', get_string('cancel'));
        $mform->addGroup($actionbuttongroup, 'actionbuttongroup', '', ' ', false);

        // Add javascript module.
        $PAGE->requires->yui_module('moodle-local_cohortselector-selector',
            'M.local_cohortselector.selector.init',
            array($course->id, 'cohortselector'));

    }

    public function validation($data, $files) {
        global $DB, $CFG;
        $errors = array();
        return $errors;
    }

}

