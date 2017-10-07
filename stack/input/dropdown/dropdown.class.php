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

/**
 * Input that is a dropdown list/multiple choice that the teacher
 * has specified.
 *
 * @copyright  2015 University of Edinburgh
 * @author     Chris Sangwin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class stack_dropdown_input extends stack_input {

    /*
     * ddlvalues is an array of the types used.
     */
    protected $ddlvalues = array();

    /*
     * ddltype must be one of 'select', 'checkbox' or 'radio'.
     */
    protected $ddltype = 'select';

    /*
     * ddldisplay must be either 'LaTeX' or 'casstring' and it determines what is used for the displayed
     * string the student uses.  The default is LaTeX, but this doesn't always work in dropdowns.
     */
    protected $ddldisplay = 'casstring';

    /*
     * Controls whether a "not answered" option is presented to the students.
     */
    protected $nonotanswered = true;

    /*
     * This holds the value of those
     * entries which the teacher has indicated are correct.
     */
    protected $teacheranswervalue = '';

    /*
     * This holds a displayed form of $this->teacheranswer. We need to generate this from those
     * entries which the teacher has indicated are correct.
     */
    protected $teacheranswerdisplay = '';

    /*
     * These fields control whether or not an additional "algebraic" input type is shown to the students.
     */
    protected $algebraic = false;
    protected $algebraictext = '';

    protected function internal_contruct() {
        $options = $this->get_parameter('options');
        if (trim($options) != '') {
            $options = explode(',', $options);
            foreach ($options as $option) {
                $option = strtolower(trim($option));

                switch($option) {
                    // Does a student see LaTeX or cassting values?
                    case 'latex':
                        $this->ddldisplay = 'LaTeX';
                        break;

                    case 'latexinline':
                        $this->ddldisplay = 'LaTeX';
                        break;

                    case 'latexdisplay':
                        $this->ddldisplay = 'LaTeXdisplay';
                        break;

                    case 'latexdisplaystyle':
                        $this->ddldisplay = 'LaTeXdisplaystyle';
                        break;

                    case 'casstring':
                        $this->ddldisplay = 'casstring';
                        break;

                    case 'nonotanswered':
                        $this->nonotanswered = false;
                        break;

                    default:
                        $this->errors[] = stack_string('inputoptionunknown', $option);
                }
            }
        }

        // Sort out the default ddlvalues etc.
        $this->adapt_to_model_answer($this->teacheranswer);
        return true;
    }

    /*
     * For the dropdown, each expression must be a list of pairs:
     * [CAS expression, true/false].
     * The second Boolean value determines if this should be considered
     * correct.  If there is more than one correct answer then checkboxes
     * will always be used.
     */
    public function adapt_to_model_answer($teacheranswer) {

        // We need to reset the errors here, now we have a new teacher's answer.
        $this->errors = null;

        /*
         * Sort out the ddlvalues.
         * Each element must be an array with the keys:
         *   value - the CAS value.
         *   display - the LaTeX displayed value.
         *   correct - whether this is considered correct or not.  This is a PHP boolean.
         *
         * First extract strings as they cause trouble.
         */
        $str = $teacheranswer;
        $strings = stack_utils::all_substring_strings($str);
        foreach ($strings as $key => $string) {
            $str = str_replace('"'.$string.'"', "[STR:$key]", $str);
            // Also convert strings from escaped form to PHP-form.
            $strings[$key] = stack_utils::maxima_string_to_php_string('"' . $string . '"');
        }
        $values = stack_utils::list_to_array($str, false);
        if (empty($values)) {
            $this->errors[] = stack_string('ddl_badanswer', $teacheranswer);
            $this->teacheranswervalue = '[ERR]';
            $this->teacheranswerdisplay = '<code>'.'[ERR]'.'</code>';
            $this->ddlvalues = null;
            return false;
        }

        $numbercorrect = 0;
        $correctanswer = array();
        $correctanswerdisplay = array();
        $duplicatevalues = array();
        foreach ($values as $distractor) {
            $value = stack_utils::list_to_array($distractor, false);
            $ddlvalue = array();
            if (is_array($value)) {
                // Inject strings back if they exist.
                foreach ($value as $key => $something) {
                    if (strpos($something, '[STR:') !== false) {
                        foreach ($strings as $skey => $string) {
                            $value[$key] = str_replace("[STR:$skey]", '"' . $string . '"', $value[$key]);
                        }
                    }
                }
                if (count($value) >= 2) {
                    // Check for duplicates in the teacher's answer.
                    if (array_key_exists($value[0], $duplicatevalues)) {
                        $this->errors[] = stack_string('ddl_duplicates');
                    }
                    $duplicatevalues[$value[0]] = true;

                    // Store the answers.
                    $ddlvalue['value'] = $value[0];
                    $ddlvalue['display'] = $value[0];
                    if (array_key_exists(2, $value)) {
                        $ddlvalue['display'] = $value[2];
                    }
                    if ($value[1] == 'true') {
                        $ddlvalue['correct'] = true;
                    } else {
                        $ddlvalue['correct'] = false;
                    }
                    if ($ddlvalue['correct']) {
                        $numbercorrect += 1;
                        $correctanswer[] = $ddlvalue['value'];
                        $correctanswerdisplay[] = $ddlvalue['display'];
                    }
                    // If there is a 4th option in the list.
                    if (array_key_exists(3, $value) && strtolower($value[3]) == 'alginput') {
                        $this->algebraic = true;
                        if (array_key_exists(2, $value)) {
                            // Strip off any quote marks.
                            $algtext = trim($value[2]);
                            if (substr($algtext, 0, 1) == '"') {
                                $algtext = substr($algtext, 1, -1);
                            }
                            $this->algebraictext = $algtext;
                        }
                    } else {
                        $ddlvalues[] = $ddlvalue;
                    }
                } else {
                    $this->errors[] = stack_string('ddl_badanswer', $teacheranswer);
                }
            }
        }

        /*
         * The dropdown input is very unusual in that the "teacher's answer" contains a mix
         * of correct and incorrect responses.  The teacher may be happy with a subset
         * of the correct responses.  So, we create $this->teacheranswervalue to be a Maxima
         * list of the values of those things the teacher said are correct.
         */
        if ($this->ddltype != 'checkbox' && $numbercorrect === 0) {
            $this->errors[] = stack_string('ddl_nocorrectanswersupplied');
            return;
        }
        if ($this->ddltype == 'checkbox') {
            $this->teacheranswervalue = '['.implode(',', $correctanswer).']';
            $this->teacheranswerdisplay = '<code>'.'['.implode(',', $correctanswerdisplay).']'.'</code>';
        } else {
            // As a correct answer we only take the first element.  If we create a list then when we seek the teacher's
            // answer later we throw an exception that the correct answer can't be found.
            $this->teacheranswervalue = $correctanswer[0];
            $this->teacheranswerdisplay = '<code>'.implode(', ', $correctanswerdisplay).'</code>';
        }

        if (empty($ddlvalues)) {
            return;
        }

        if ($this->ddldisplay === 'casstring') {
            // By default, we wrap displayed values in <code> tags.
            foreach ($ddlvalues as $key => $value) {
                $display = trim($ddlvalues[$key]['display']);
                if (substr($display, 0, 1) == '"' && substr($display, 0, 1) == '"') {
                    $ddlvalues[$key]['display'] = substr($display, 1, strlen($display) - 2);
                } else {
                    $display = stack_utils::logic_nouns_sort($display, 'remove');
                    $ddlvalues[$key]['display'] = '<code>'.$display.'</code>';
                }
            }
            $this->ddlvalues = $this->key_order($ddlvalues);
            return;
        }

        // If we are displaying LaTeX we need to connect to the CAS to generate LaTeX from the displayed values.
        $csvs = array(0 => new stack_cas_casstring('[]'));
        // Create a displayed form of the teacher's answer.
        $csv = new stack_cas_casstring('teachans:'.$this->teacheranswervalue);
        $csv->get_valid('t');
        $csvs[] = $csv;
        foreach ($ddlvalues as $key => $value) {
            // We use the display term here because it might differ explicitly from the above "value".
            // So, we send the display form to LaTeX, and then replace it with the LaTeX below.
            $csv = new stack_cas_casstring('val'.$key.':'.$value['display']);
            $csv->get_valid('t');
            $csvs[] = $csv;
        }

        $at1 = new stack_cas_session($csvs, $this->options, 0);
        $at1->instantiate();

        if ('' != $at1->get_errors()) {
            $this->errors[] = $at1->get_errors();
            return;
        }

        // This sets display form in $this->ddlvalues.
        $this->teacheranswerdisplay = '\('.$at1->get_display_key('teachans').'\)';
        foreach ($ddlvalues as $key => $value) {
            // Was the original expression a string?  If so, don't use the LaTeX version.
            $display = trim($ddlvalues[$key]['display']);
            if (substr($display, 0, 1) == '"' && substr($display, 0, 1) == '"') {
                $ddlvalues[$key]['display'] = substr($display, 1, strlen($display) - 2);
            } else {
                // Note, we've chosen to add LaTeX maths environments here.
                $disp = $at1->get_display_key('val'.$key);
                switch ($this->ddldisplay) {
                    case 'LaTeX':
                        $ddlvalues[$key]['display'] = '\('.$disp.'\)';
                        break;
                    case 'LaTeXdisplay':
                        $ddlvalues[$key]['display'] = '\['.$disp.'\]';
                        break;
                    case 'LaTeXdisplaystyle':
                        $ddlvalues[$key]['display'] = '\(\displaystyle '.$disp.'\)';
                        break;
                    default:
                        $ddlvalues[$key]['display'] = '\(\displaystyle '.$disp.'\)';
                }
            }
        }

        $this->ddlvalues = $this->key_order($ddlvalues);
        return;
    }

    private function key_order($values) {

        // Make sure the array keys start at 1.  This avoids
        // potential confusion between keys 0 and ''.
        if ($this->nonotanswered) {
            $values = array_merge(array('' => array('value' => '',
                'display' => stack_string('notanswered'), 'correct' => false), 0 => null), $values);
        } else {
            $values = array_merge(array(0 => null), $values);
        }
        unset($values[0]);
        // For the 'checkbox' type remove the "not answered" option.  This isn't needed.
        if ('checkbox' == $this->ddltype) {
            if (array_key_exists('', $values)) {
                unset($values['']);
            }
        }
        return $values;
    }

    protected function extra_validation($contents) {
        if (!$this->algebraic && !array_key_exists($contents[0], $this->get_choices())) {
            return stack_string('dropdowngotunrecognisedvalue');
        }
        return '';
    }

    /**
     * Transforms the contents array into a maxima list.
     *
     * @param array|string $in
     * @return string
     */
    public function contents_to_maxima($contents) {
        return $contents[0];
    }

    /* This function always returns an array where the key is the key in the ddlvalues.
     */
    protected function get_choices() {
        if (empty($this->ddlvalues)) {
            return array();
        }

        $values = $this->ddlvalues;
        if (empty($values)) {
            $this->errors[] = stack_string('ddl_empty');
            return array();
        }

        $choices = array();
        foreach ($values as $key => $val) {
            $choices[$key] = $val['display'];
        }
        return $choices;
    }

    public function render(stack_input_state $state, $fieldname, $readonly, $tavalue) {
        if ($this->errors) {
            return $this->render_error($this->errors);
        }

        // Create html.
        $result = '';
        $values = $this->get_choices();
        $selected = $state->contents;

        $select = 0;
        if (array_key_exists(0, $selected)) {
            $select = $this->get_input_ddl_key($selected[0]);
        }

        $inputattributes = array();
        if ($readonly) {
            $inputattributes['disabled'] = 'disabled';
        }

        $notanswered = '';
        if (array_key_exists('', $values)) {
            $notanswered = $values[''];
        }
        if ($this->ddltype == 'select') {
            unset($values['']);
        }

        $result .= html_writer::select($values, $fieldname.'_mcq', $select,
            $notanswered, $inputattributes);

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

    /*
     * Create the optional "algebraic" input type.
     */
    protected function render_algebraic($contents, $fieldname, $readonly, $tavalue) {
        // This code is largely duplicated from algebraic input.
        $size = $this->parameters['boxWidth'] * 0.9 + 0.1;
        $attributes = array(
            'type'  => 'text',
            'name'  => $fieldname,
            'id'    => $fieldname,
            'size'  => $this->parameters['boxWidth'] * 1.1,
            'style' => 'width: '.$size.'em'
        );

        if ($this->is_blank_response($contents)) {
            $field = 'value';
            if ($this->parameters['syntaxAttribute'] == '1') {
                $field = 'placeholder';
            }
            $attributes[$field] = stack_utils::logic_nouns_sort($this->parameters['syntaxHint'], 'remove');
        } else {
            if ($this->ddltype == 'checkbox') {
                // Normally we'd take all elements of the contents array and make them into a list.
                // Here we only have space to put one into the algebraic input, and we don't want a list.
                $attributes['value'] = reset($contents);
            } else {
                $attributes['value'] = $this->contents_to_maxima($contents);
            }
        }

        if ($readonly) {
            $attributes['readonly'] = 'readonly';
        }

        return $this->algebraictext . html_writer::empty_tag('input', $attributes);
    }
    /**
     * Get the input variable that this input expects to process.
     * All the variable names should start with $this->name.
     * @return array string input name => PARAM_... type constant.
     */
    public function get_expected_data() {
        $expected = array();
        $expected[$this->name] = PARAM_RAW;
        $expected[$this->name.'_mcq'] = PARAM_RAW;
        if ($this->requires_validation()) {
            $expected[$this->name . '_val'] = PARAM_RAW;
        }
        return $expected;
    }

    public function add_to_moodleform_testinput(MoodleQuickForm $mform) {
        $mform->addElement('text', $this->name, $this->name);
        $mform->setDefault($this->name, '');
        $mform->setType($this->name, PARAM_RAW);
    }

    /**
     * Return the default values for the parameters.
     * @return array parameters` => default value.
     */
    public static function get_parameters_defaults() {

        return array(
            'mustVerify'         => false,
            'showValidation'     => 0,
            'options'            => '',
            'boxWidth'           => 15,
            'strictSyntax'       => false,
            'insertStars'        => 0,
            'syntaxHint'         => '',
            'syntaxAttribute'    => 0,
            'forbidWords'        => '',
            'allowWords'         => '',
            'forbidFloats'       => true,
            'lowestTerms'        => true,
            'sameType'           => true
        );
    }

    /**
     * This is used by the question to get the teacher's correct response.
     * The dropdown type needs to intercept this to filter the correct answers.
     * @param unknown_type $in
     */
    public function get_correct_response($in) {
        $this->adapt_to_model_answer($in);
        return $this->maxima_to_response_array($this->teacheranswervalue);
    }

    /**
     * @return string the teacher's answer, displayed to the student in the general feedback.
     */
    public function get_teacher_answer_display($value, $display) {
        // Can we really ignore the $value and $display inputs here and rely on the internal state?
        return stack_string('teacheranswershow_disp', array('display' => $this->teacheranswerdisplay));
    }

    /**
     * Transforms a Maxima expression into an array of raw inputs which are part of a response.
     * Most inputs are very simple, but textarea and matrix need more here.
     * @param array|string $in
     * @return string
     */
    public function maxima_to_response_array($in) {
        if ('' == $in) {
            return array($this->name . '_mcq' => '');
        }

        $response[$this->name . '_mcq'] = $this->get_input_ddl_key($in);

        if ($this->requires_validation()) {
            $response[$this->name . '_val'] = $this->get_input_ddl_key($in);
        }
        // The name field is used by the question testing mechanism for the full answer.
        $response[$this->name] = $in;

        return $response;
    }

    /**
     * Converts the input passed in via many input elements into an array.
     *
     * @param string $in
     * @return string
     * @access public
     */
    public function response_to_contents($response) {
        $mcq = false;
        if (array_key_exists($this->name.'_mcq', $response)) {
            $key = (int) $response[$this->name.'_mcq'];
            $mcq = $this->get_input_ddl_value($key);
        }
        $alg = false;
        if ($this->algebraic && array_key_exists($this->name, $response)) {
            $alg = trim($response[$this->name]);
        }
        $contents[0] = $mcq;
        if ($this->algebraic && $alg) {
            $contents[0] = $alg;
        }
        return $contents;
    }

    /**
     * Decide if the contents of this attempt is blank.
     *
     * @param array $contents a non-empty array of the student's input as a split array of raw strings.
     * @return string any error messages describing validation failures. An empty
     *      string if the input is valid - at least according to this test.
     */
    protected function is_blank_response($contents) {
        if ('' == $contents) {
            return true;
        }
        $allblank = true;
        foreach ($contents as $val) {
            if (!('' == trim($val)) && !('0' == trim($val))) {
                $allblank = false;
            }
        }
        return $allblank;
    }

    /*
     * In this type we use the array keys in $this->ddlvalues within the HTML interactions,
     * not the CAS values.  These next two methods map between the keys and the CAS values.
     */
    protected function get_input_ddl_value($key) {
        $val = '';
        // Resolve confusion over null values in the key.
        if (0 === $key || '0' === $key) {
            $key = '';
        }
        if (array_key_exists($key, $this->ddlvalues)) {
            return $this->ddlvalues[$key]['value'];
        }
        if (!$this->algebraic) {
            throw new stack_exception('stack_dropdown_input: could not find a value for key '.$key);
        }

        return false;
    }

    protected function get_input_ddl_key($value) {
        foreach ($this->ddlvalues as $key => $val) {
            if ($val['value'] == $value) {
                return $key;
            }
        }
        if (!$this->algebraic) {
            $this->errors[] = stack_string('ddl_unknown', $value);
        }

        return false;
    }
}
