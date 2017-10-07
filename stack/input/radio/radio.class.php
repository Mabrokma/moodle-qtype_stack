<?php
// This file is part of Stack - http://stack.maths.ed.ac.uk/
//
// Stack is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Stack is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Stack.  If not, see <http://www.gnu.org/licenses/>.

defined('MOODLE_INTERNAL') || die();

// Input that is a radio/multiple choice.
//
// @copyright  2015 University of Edinburgh.
// @author     Chris Sangwin.
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.

require_once(__DIR__ . '/../dropdown/dropdown.class.php');
class stack_radio_input extends stack_dropdown_input {

    protected $ddltype = 'radio';

    /*
     * Default ddldisplay for radio is 'LaTeX'.
     */
    protected $ddldisplay = 'LaTeX';

    public function render(stack_input_state $state, $fieldname, $readonly, $tavalue) {

        if ($this->errors) {
            return $this->render_error($this->errors);
        }

        $selected = $state->contents;
        $select = 0;
        if (array_key_exists(0, $selected)) {
            $select = $this->get_input_ddl_key($selected[0]);
        }

        $radiobuttons = array();
        $values = $this->get_choices();
        foreach ($values as $key => $ansid) {
            $inputattributes = array(
                'type' => 'radio',
                'name' => $fieldname.'_mcq',
                'value' => $key,
                'id' => $fieldname.'_'.$key
            );
            if ($select === $key) {
                $inputattributes['checked'] = 'checked';
            }
            if ($readonly) {
                $inputattributes['disabled'] = 'disabled';
            }
            $radiobuttons[] = html_writer::empty_tag('input', $inputattributes) . html_writer::tag('label', $ansid);
            if ('' === $key) {
                // This separates the "not answered" input from the others.
                $radiobuttons[] = '<br />';
            }
        }

        $result = html_writer::start_tag('div', array('class' => 'answer'));
        foreach ($radiobuttons as $key => $radio) {
            $result .= html_writer::tag('div', $radio);
        }
        $result .= html_writer::end_tag('div');

        if ($this->algebraic) {
            $contents = $state->contents;
            // If the student has selected an MCQ choice we don't put it in the algebraic input.
            if ($select > 0) {
                $contents = '';
            }
            $result .= $this->render_algebraic($contents, $fieldname, $readonly, $tavalue);
        }

        return $result;
    }
}
