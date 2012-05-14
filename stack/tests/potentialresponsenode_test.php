<?php
// This file is part of Stack - http://stack.bham.ac.uk/
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

/**
 * Unit tests for stack_potentialresponse_node.
 *
 * @copyright  2012 The University of Birmingham
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(__FILE__) . '/../potentialresponsenode.class.php');
require_once(dirname(__FILE__) . '/../cas/castext.class.php');
require_once(dirname(__FILE__) . '/../../locallib.php');


/**
 * Unit tests for stack_potentialresponse_node.
 *
 * @copyright  2012 The University of Birmingham
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group qtype_stack
 */
class stack_potentialresponse_node_test extends qtype_stack_testcase {

    public function test_constructor() {
        $sans = new stack_cas_casstring('x^2+2*x+1');
        $tans = new stack_cas_casstring('(x+1)^2');
        $tans->validate('t');
        $options = new stack_options();
        $node = new stack_potentialresponse_node($sans, $tans, 'AlgEquiv', '', false);
        $node->add_branch(0, '=', 0, '', -1, '', '1-0-0');
        $node->add_branch(1, '=', 1, '', -1, '', '1-0-1');

        $this->assertEquals($sans, $node->sans);
        $this->assertEquals($tans, $node->tans);
    }

    public function test_do_test_pass() {
        $sans = new stack_cas_casstring('x^2+2*x+1');
        $tans = new stack_cas_casstring('(x+1)^2');
        $tans->validate('t');
        $node = new stack_potentialresponse_node($sans, $tans, 'AlgEquiv', '', false);
        $node->add_branch(0, '=', 0, '', -1, 'Boo!', '1-0-0');
        $node->add_branch(1, '=', 2, '', 3, 'Yeah!', '1-0-1');

        $options = new stack_options();
        $result = $node->do_test('x^2+2*x+1', '(x+1)^2', '', $options, 0);

        $this->assertEquals(true, $result['valid']);
        $this->assertEquals('', $result['errors']);
        $this->assertEquals(1, $result['result']);
        $this->assertEquals('Yeah!', $result['feedback']);
        $this->assertEquals(3, $result['nextnode']);
    }

    public function test_do_test_fail() {
        $sans = new stack_cas_casstring('x^2+2*x-1');
        $tans = new stack_cas_casstring('(x+1)^2');
        $tans->validate('t');
        $node = new stack_potentialresponse_node($sans, $tans, 'AlgEquiv', '', false);
        $node->add_branch(0, '=', 0, '', -1, 'Boo!', '1-0-0');
        $node->add_branch(1, '=', 2, '', 3, 'Yeah!', '1-0-1');

        $options = new stack_options();
        $result = $node->do_test('x^2+2*x-1', '(x+1)^2', '', $options, 0);

        $this->assertEquals(true, $result['valid']);
        $this->assertEquals('', $result['errors']);
        $this->assertEquals(0, $result['result']);
        $this->assertEquals('Boo!', $result['feedback']);
        $this->assertEquals(-1, $result['nextnode']);
    }

    public function test_do_test_cas_error() {
        $sans = new stack_cas_casstring('x^2+2*x-1');
        $tans = new stack_cas_casstring('(x+1)^2');
        $tans->validate('t');
        $node = new stack_potentialresponse_node($sans, $tans, 'AlgEquiv', '', false);
        $node->add_branch(0, '=', 0, '', -1, 'Boo!', '1-0-0');
        $node->add_branch(1, '=', 2, '', 3, 'Yeah!', '1-0-1');

        $options = new stack_options();
        $result = $node->do_test('1/0', '(x+1)^2', '', $options, 0);

        $this->assertEquals(false, $result['valid']);
        $this->assertNotEquals('', $result['errors']);
        $this->assertEquals(0, $result['result']);
        $this->assertEquals('The answer test failed to execute correctly: please alert your teacher. Boo!', $result['feedback']);
        $this->assertEquals(-1, $result['nextnode']);
    }

    public function test_do_test_pass_atoption() {
        $sans = new stack_cas_casstring('(x+1)^2');
        $tans = new stack_cas_casstring('(x+1)^2');
        $tans->validate('t');
        $node = new stack_potentialresponse_node($sans, $tans, 'FacForm', 'x', false);
        $node->add_branch(0, '=', 0, '', -1, 'Boo!', '1-0-0');
        $node->add_branch(1, '=', 2, '', 3, 'Yeah!', '1-0-1');

        $options = new stack_options();
        $result = $node->do_test('(x+1)^2', '(x+1)^2', 'x', $options, 0);

        $this->assertEquals(true, $result['valid']);
        $this->assertEquals('', $result['errors']);
        $this->assertEquals(1, $result['result']);
        $this->assertEquals('Yeah!', $result['feedback']);
        $this->assertEquals(3, $result['nextnode']);
    }

    public function test_do_test_fail_atoption() {
        $sans = new stack_cas_casstring('ans1');
        $tans = new stack_cas_casstring('3*(x+2)');
        $tans->validate('t');
        $node = new stack_potentialresponse_node($sans, $tans, 'FacForm', 'x', false);
        $node->add_branch(0, '=', 0, '', -1, 'Boo!', '1-0-0');
        $node->add_branch(1, '=', 2, '', 3, 'Yeah!', '1-0-1');

        $options = new stack_options();
        $result = $node->do_test('3*x+6', '3*(x+2)', 'x', $options, 0);

        $this->assertEquals(true, $result['valid']);
        $this->assertEquals('', $result['errors']);
        $this->assertEquals(0, $result['result']);
        $this->assertEquals('Your answer is not factored. You need to take out a common factor. Boo!', $result['feedback']);

    }

    public function test_do_test_fail_quiet() {
        $sans = new stack_cas_casstring('ans1');
        $tans = new stack_cas_casstring('3*(x+2)');
        $tans->validate('t');
        $node = new stack_potentialresponse_node($sans, $tans, 'FacForm', 'x', true);
        $node->add_branch(0, '+', 0.5, '', -1, 'Boo! Your answer should be in factored form, i.e. @factor(ans1)@.', '1-0-0');
        $node->add_branch(1, '=', 2, '', 3, 'Yeah!', '1-0-1');

        $options = new stack_options();
        $result = $node->do_test('3*x+6', '3*(x+2)', 'x', $options, 1);

        $this->assertEquals('Boo! Your answer should be in factored form, i.e. @factor(ans1)@.', $result['feedback']);

        $this->assertEquals(1.5, $result['newscore']);

        $data = array('factor(ans1)', 'ans1', '3*(x+2)', 'x');
        $this->assertEquals($data, $node->get_required_cas_strings());
    }
}