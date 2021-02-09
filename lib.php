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
 * lib.php - Contains plugin specific functions called by Modules.
 *
 * @package    plagiarism_urkund
 * @author     Dan Marsden <dan@danmarsden.com>
 * @copyright  2021 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Hook to add specific settings to a module settings page
 *
 * @param moodleform $formwrapper
 * @param MoodleQuickForm $mform
 */
function local_assignduedate_coursemodule_standard_elements($formwrapper, $mform) {
    global $DB;
    $matches = array();
    if (!preg_match('/^mod_([^_]+)_mod_form$/', get_class($formwrapper), $matches)) {
        return;
    }
    $modulename = "mod_" . $matches[1];
    if ($modulename != 'mod_assign') {
        return;
    }
    $cmid = null;
    if ($cm = $formwrapper->get_coursemodule()) {
        $cmid = $cm->id;
    }
    $mform->addElement('header', 'displayduedatedesc', get_string('pluginname', 'local_assignduedate'));
    $name = get_string('displayduedate', 'local_assignduedate');
    $mform->addElement('checkbox', 'displayduedate', $name);

    $mform->addHelpButton('displayduedate', 'displayduedate', 'local_assignduedate');
    if (!empty($cmid)) {
        $existing = $DB->get_field('local_assignduedate', 'displayduedate', array('cmid' => $cmid));
        $mform->setDefault('displayduedate', $existing);
    } else {
        $mform->setDefault('displayduedate', get_config('local_assignduedate', 'displayduedate'));
    }

    $mform->hideIf('displayduedate', 'duedate[enabled]', 'notchecked');
}

/**
 * Hook to save specific settings on a module settings page.
 *
 * @param stdClass $data
 * @param stdClass $course
 */
function local_assignduedate_coursemodule_edit_post_actions($moduleinfo, $course) {
    global $DB;
    if (empty($moduleinfo->modulename) || $moduleinfo->modulename != 'assign' || empty($moduleinfo->coursemodule)) {
        return ;
    }
    $existing = $DB->get_record('local_assignduedate', array('cmid' => $moduleinfo->coursemodule));
    $value = empty($moduleinfo->displayduedate) ? 0 : $moduleinfo->displayduedate;
    if (!empty($existing) && $value != $existing->displayduedate) {
        $existing->displayduedate = $value;
        $DB->update_record('local_assignduedate', $existing);
    } else if (empty($existing)) {
        $new = new stdClass();
        $new->cmid = $moduleinfo->coursemodule;
        $new->displayduedate = $value;
        $DB->insert_record('local_assignduedate', $new);
    }
}

/**
 * Adds the due date field to the display of the content if set.
 *
 * @param cm_info $cm
 */
function local_assignduedate_assign_cm_info_view(cm_info $cm) {
    global $CFG, $USER, $DB;
    require_once($CFG->dirroot . '/mod/assign/locallib.php');
    $showduedate = $DB->record_exists('local_assignduedate', array('displayduedate' => 1, 'cmid' => $cm->id));
    if ($showduedate) {
        $assign = new assign($cm->context, $cm, $cm->course);
        // Check if there is an override for this user.
        $overrides = $assign->override_exists($USER->id);
        if (!empty($overrides->duedate)) {
            $duedate = $overrides->duedate;
        } else {
            $duedate = $assign->get_instance($USER->id)->duedate;
        }

        $result = html_writer::start_tag('div', array('class' => 'due-date'));
        $result .= html_writer::tag('p', get_string('duedate', 'assign') . ': ' . userdate($duedate));
        $result .= html_writer::end_tag('div');
        $cm->set_after_link($result);
    }
}