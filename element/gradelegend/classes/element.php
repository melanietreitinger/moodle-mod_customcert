<?php
// This file is part of the customcert module for Moodle - http://moodle.org/
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
 * This file contains the customcert element grade's core interaction API.
 *
 * @package    customcertelement_gradelegend
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customcertelement_gradelegend;

defined('MOODLE_INTERNAL') || die();

/**
 * Grade - Course
 */
define('CUSTOMCERT_GRADE_COURSELEGEND', '0');

/**
 * The customcert element grade's core interaction API.
 *
 * @package    customcertelement_gradelegend
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class element extends \mod_customcert\element {

    /**
     * This function renders the form elements when adding a customcert element.
     *
     * @param \MoodleQuickForm $mform the edit_form instance
     */
    public function render_form_elements($mform) {
        global $COURSE;

        // Get the grade items we can display.
        $gradeitems = array();
        $gradeitems[CUSTOMCERT_GRADE_COURSELEGEND] = get_string('coursegrade', 'customcertelement_gradelegend');
        $gradeitems = $gradeitems + \mod_customcert\element_helper::get_grade_items($COURSE);

        // The grade items.
        $mform->addElement('select', 'gradeitem', get_string('gradeitem', 'customcertelement_gradelegend'), $gradeitems);
        $mform->addHelpButton('gradeitem', 'gradeitem', 'customcertelement_gradelegend');

        // The grade format.
        $mform->addElement('select', 'gradeformat', get_string('gradeformat', 'customcertelement_gradelegend'),
            self::get_grade_format_options());
        $mform->setType('gradeformat', PARAM_INT);
        $mform->addHelpButton('gradeformat', 'gradeformat', 'customcertelement_gradelegend');

        // The range elements
        $mform->addElement('header', 'itemranges', get_string('itemranges', 'customcertelement_gradelegend'));

        if (empty($this->get_decoded_data()->itemranges)) {
            $repeats = 1;
        } else {
            $repeats = count($this->get_decoded_data()->itemranges);
        }

        $ranges = [];

        $ranges[] = $mform->createElement(
            'text',
            'rangemin',
            get_string('rangemin', 'customcertelement_gradelegend')
        );

        $ranges[] = $mform->createElement(
            'text',
            'rangemax',
            get_string('rangemax', 'customcertelement_gradelegend')
        );

        $ranges[] = $mform->createElement(
            'textarea',
            'rangetext',
            get_string('rangetext', 'customcertelement_gradelegend'),
            'wrap="virtual" rows="5" cols="50"'
        );

        $ranges[] = $mform->createElement(
            'advcheckbox',
            'rangedelete',
            get_string('setdeleted', 'customcertelement_gradelegend'),
            '',
            [],
            [0, 1]
        );

        $ranges[] = $mform->createElement('html', '<hr>');

        $rangeoptions = array();
        $rangeoptions['rangemin']['type'] = PARAM_INT;
        $rangeoptions['rangemax']['type'] = PARAM_INT;
        $rangeoptions['rangetext']['type'] = PARAM_NOTAGS;
        $rangeoptions['rangedelete']['type'] = PARAM_BOOL;

        $addstring = get_string('addrange', 'customcertelement_gradelegend');
        $this->get_edit_element_form()->repeat_elements($ranges, $repeats, $rangeoptions, 'repeats', 'add', 1, $addstring, true);
        
        $mform->addElement('header', 'positioning', get_string('positioning', 'customcertelement_gradelegend'));
        parent::render_form_elements($mform);

    }

    /**
     * Performs validation on the element values.
     *
     * @param array $data the submitted data
     * @param array $files the submitted files
     * @return array the validation errors
     */
    public function validate_form_elements($data, $files) {
        $errors = parent::validate_form_elements($data, $files);

        // Check if at least one range is set.
        $error = get_string('error:atleastone', 'customcertelement_gradelegend');

        for ($i = 0; $i < $data['repeats']; $i++) {
            if (empty($data['rangedelete'][$i])) {
                $error = '';
            }
        }

        if (!empty($error)) {
            $errors['help'] = $error;
        }

        for ($i = 0; $i < $data['repeats']; $i++) {
            // Skip elements that needs to be deleted.
            if (!empty($data['rangedelete'][$i])) {
                continue;
            }

            if (empty($data['rangetext'][$i])) {
                $name = $this->build_element_name('rangetext', $i);
                $errors[$name] = get_string('error:rangetext', 'customcertelement_gradelegend');
            }

            // Check that max value is correctly set.
            if ( $data['rangemin'][$i] >= $data['rangemax'][$i] ) {
                $errors[$this->build_element_name('rangemax', $i)] = get_string('error:rangemax', 'customcertelement_gradelegend');
            }

        }

        return $errors;
    }

    /**
     * This will handle how form data will be saved into the data column in the
     * customcert_elements table.
     *
     * @param \stdClass $data the form data.
     * @return string the json encoded array
     */
    public function save_unique_data($data) {
        // Array of data we will be storing in the database.
        $arrtostore = array(
            'gradeitem' => $data->gradeitem,
            'gradeformat' => $data->gradeformat,
            'itemranges' => [],
        );

        for ($i = 0; $i < $data->repeats; $i++) {
            if (empty($data->rangedelete[$i])) {
                $arrtostore['itemranges'][] = [
                    'rangemin' => $data->rangemin[$i],
                    'rangemax' => $data->rangemax[$i],
                    'rangetext' => $data->rangetext[$i],
                ];
            }
         }
        // Encode these variables before saving into the DB.
        return json_encode($arrtostore);
    }

    /**
     * Handles rendering the element on the pdf.
     *
     * @param \pdf $pdf the pdf object
     * @param bool $preview true if it is a preview, false otherwise
     * @param \stdClass $user the user we are rendering this for
     */
    public function render($pdf, $preview, $user) {
        // If there is no element data, we have nothing to display.
        if (empty($this->get_data())) {
            return;
        }

	$gradelegend = '';
        $courseid = \mod_customcert\element_helper::get_courseid($this->id);

        // Decode the information stored in the database.
        $gradeinfo = json_decode($this->get_data()); 
        $gradeitem = $gradeinfo->gradeitem;
        

        $itemranges = $gradeinfo->itemranges;
        $gradeformat = $gradeinfo->gradeformat;

        // If we are previewing this certificate then just show a demonstration grade.
        if ($preview) {
            $courseitem = \grade_item::fetch_course_item($courseid);
            $grade = grade_format_gradevalue('100', $courseitem, true, $gradeinfo->gradeformat);;
        } else {
            if ($gradeitem == CUSTOMCERT_GRADE_COURSELEGEND) {
                $grade = \mod_customcert\element_helper::get_course_grade_info(
                    $courseid,
                    $gradeformat,
                    $user->id
                );
            } else if (strpos($gradeitem, 'gradeitem:') === 0) {
                $gradeitemid = substr($gradeitem, 10);
                $grade = \mod_customcert\element_helper::get_grade_item_info(
                    $gradeitemid,
                    $gradeformat,
                    $user->id
                );
            } else {
                $grade = \mod_customcert\element_helper::get_mod_grade_info(
                    $gradeitem,
                    $gradeformat,
                    $user->id
                );
            }

            if ($grade) {
                $grade = $grade->get_displaygrade();
            }
        }
        
        $intgrade = @(int)$grade;

        foreach($itemranges as $range){
          if($intgrade >= $range->rangemin && $intgrade <= $range->rangemax){
            $gradelegend = $range->rangetext;
          }
        }

        \mod_customcert\element_helper::render_content($pdf, $this, $gradelegend);
    }

    /**
     * Render the element in html.
     *
     * This function is used to render the element when we are using the
     * drag and drop interface to position it.
     *
     * @return string the html
     */
    public function render_html() {

        // If there is no element data, we have nothing to display.
        if (empty($this->get_data())) {
            return;
        }

        // Decode the information stored in the database.
        $gradeinfo = json_decode($this->get_data());
        $itemranges = $gradeinfo->itemranges;

        foreach($itemranges as $range){
          if(100 > $range->rangemin && 100 < $range->rangemax){
            $gradelegend = $range->rangetext;
          }
        }



        return \mod_customcert\element_helper::render_html_content($this, $gradelegend);
    }

    /**
     * Sets the data on the form when editing an element.
     *
     * @param \MoodleQuickForm $mform the edit_form instance
     */
    public function definition_after_data($mform) {
        // Set the item and format for this element.
        if (!empty($this->get_data()) && !$mform->isSubmitted()) {
            $gradeinfo = json_decode($this->get_data());

            $element = $mform->getElement('gradeitem');
            $element->setValue($gradeinfo->gradeitem);

            $element = $mform->getElement('gradeformat');
            $element->setValue($gradeinfo->gradeformat);

            foreach ($this->get_decoded_data()->itemranges as $key => $range) {
                $mform->setDefault($this->build_element_name('rangemin', $key), $range->rangemin);
                $mform->setDefault($this->build_element_name('rangemax', $key), $range->rangemax);
                $mform->setDefault($this->build_element_name('rangetext', $key), $range->rangetext);
            }

        }

        parent::definition_after_data($mform);
    }

    /**
     * This function is responsible for handling the restoration process of the element.
     *
     * We will want to update the course module the grade element is pointing to as it will
     * have changed in the course restore.
     *
     * @param \restore_customcert_activity_task $restore
     */
    public function after_restore($restore) {
        global $DB;

        $gradeinfo = json_decode($this->get_data());
        if ($newitem = \restore_dbops::get_backup_ids_record($restore->get_restoreid(), 'course_module', $gradeinfo->gradeitem)) {
            $gradeinfo->gradeitem = $newitem->newitemid;
            $DB->set_field('customcert_elements', 'data', $this->save_unique_data($gradeinfo), array('id' => $this->get_id()));
        }
    }

    /**
     * A helper function to build consistent form element name.
     *
     * @param string $name
     * @param string $num
     *
     * @return string
     */
    protected function build_element_name($name, $num) {
        return $name . '[' . $num . ']';
    }

    /**
     * Get decoded data stored in DB.
     *
     * @return \stdClass
     */
    protected function get_decoded_data() {
        return json_decode($this->get_data());
    }

    /**
     * Helper function to return all the possible grade formats.
     *
     * @return array returns an array of grade formats
     */
    public static function get_grade_format_options() {
        $gradeformat = array();
        $gradeformat[GRADE_DISPLAY_TYPE_REAL] = get_string('gradepoints', 'customcertelement_gradelegend');
        $gradeformat[GRADE_DISPLAY_TYPE_PERCENTAGE] = get_string('gradepercent', 'customcertelement_gradelegend');
        $gradeformat[GRADE_DISPLAY_TYPE_LETTER] = get_string('gradeletter', 'customcertelement_gradelegend');

        return $gradeformat;
    }
}
