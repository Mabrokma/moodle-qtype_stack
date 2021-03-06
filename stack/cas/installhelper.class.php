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

// The file provides helper code for creating the files needed to connect to the CAS.

require_once(__DIR__.'/../../../../../config.php');

require_once(__DIR__ . '/../../locallib.php');
require_once(__DIR__ . '/../utils.class.php');
require_once(__DIR__ . '/casstring.units.class.php');
require_once(__DIR__ . '/connectorhelper.class.php');
require_once(__DIR__ . '/cassession.class.php');


class stack_cas_configuration {
    protected static $instance = null;

    /** @var This variable controls which optional packages are supported by STACK. */
    public static $maximalibraries = array('stats', 'distrib', 'descriptive', 'simplex');

    protected $settings;

    /** @var string the date when these settings were worked out. */
    protected $date;

    protected $maximacodepath;

    protected $logpath;

    protected $vnum;

    protected $blocksettings;

    /**
     * Constructor, initialises all the settings.
     */
    public function __construct() {
        global $CFG;
        $this->settings = get_config('qtype_stack');
        $this->date = date("F j, Y, g:i a");

        $this->maximacodepath = stack_utils::convert_slash_paths(
                $CFG->dirroot . '/question/type/stack/stack/maxima');

        $this->logpath = stack_utils::convert_slash_paths($CFG->dataroot . '/stack/logs');

        $this->vnum = (float) substr($this->settings->maximaversion, 2);

        $this->blocksettings = array();
        $this->blocksettings['MAXIMA_PLATFORM'] = $this->settings->platform;
        $this->blocksettings['maxima_tempdir'] = stack_utils::convert_slash_paths($CFG->dataroot . '/stack/tmp/');
        $this->blocksettings['IMAGE_DIR']     = stack_utils::convert_slash_paths($CFG->dataroot . '/stack/plots/');

        // These are used by the GNUplot "set terminal" command. Currently no user interface...
        $this->blocksettings['PLOT_TERMINAL'] = 'png';
        $this->blocksettings['PLOT_TERM_OPT'] = 'large transparent size 450,300';
        $this->blocksettings['PLOT_TERMINAL'] = 'svg';
        // Note, the quotes need to be protected below.
        $this->blocksettings['PLOT_TERM_OPT'] = 'size 480,300 dynamic font \",12\" linewidth 1.2';

        if ($this->settings->platform === 'win') {
            $this->blocksettings['DEL_CMD']     = 'del';
            $this->blocksettings['GNUPLOT_CMD'] = $this->get_plotcommand_win();
        } else {
            $this->blocksettings['DEL_CMD']     = 'rm';
            if ((trim($this->settings->plotcommand)) != '') {
                $this->blocksettings['GNUPLOT_CMD'] = $this->settings->plotcommand;
            } else if (is_readable('/Applications/Gnuplot.app/Contents/Resources/bin/gnuplot')) {
                $this->blocksettings['GNUPLOT_CMD'] = '/Applications/Gnuplot.app/Contents/Resources/bin/gnuplot';
            } else {
                $this->blocksettings['GNUPLOT_CMD'] = 'gnuplot';
            }
        }
        // Loop over this array to format them correctly...
        if ($this->settings->platform === 'win') {
            foreach ($this->blocksettings as $var => $val) {
                $this->blocksettings[$var] = addslashes(str_replace( '/', '\\', $val));
            }
        }

        $this->blocksettings['MAXIMA_VERSION_EXPECTED'] = $this->settings->maximaversion;
        $this->blocksettings['URL_BASE']       = '!ploturl!';
    }

    /**
     * Try to guess the gnuplot command on Windows.
     * @return string the command.
     */
    public function get_plotcommand_win() {
        global $CFG;
        if ($this->settings->plotcommand && $this->settings->plotcommand != 'gnuplot') {
            return $this->settings->plotcommand;
        }

        // This does its best to find your version of Gnuplot...
        $maximalocation = $this->maxima_win_location();

        $plotcommands = array();
        $plotcommands[] = $maximalocation. 'gnuplot/wgnuplot.exe';
        $plotcommands[] = $maximalocation. 'bin/wgnuplot.exe';
        $plotcommands[] = $maximalocation. 'gnuplot/bin/wgnuplot.exe';

        // I'm finally fed up with dealing with spaces in MS filenames.
        $newplotlocation = stack_utils::convert_slash_paths($CFG->dataroot . '/stack/wgnuplot.exe');
        foreach ($plotcommands as $plotcommand) {
            if (file_exists($plotcommand)) {
                        copy($plotcommand, $newplotlocation);
                        return $newplotlocation;
            }
        }

        throw new stack_exception('Could not locate GNUPlot.');
    }

    public function maxima_win_location() {
        global $CFG;

        if ($this->settings->platform != 'win') {
            return '';
        }

        $locations = array();
        $locations[] = 'C:/maxima-' . $this->settings->maximaversion . '/';
        $locations[] = 'C:/Maxima-' . $this->settings->maximaversion . '/';
        $locations[] = 'C:/bin/Maxima-gcl-' . $this->settings->maximaversion . '/';
        $locations[] = 'C:/bin/Maxima-sbcl-' . $this->settings->maximaversion . '/';
        $locations[] = 'C:/Program Files/Maxima-' . $this->settings->maximaversion . '/';
        $locations[] = 'C:/Program Files (x86)/Maxima-' . $this->settings->maximaversion . '/';
        $locations[] = 'C:/Program Files/Maxima-sbcl-' . $this->settings->maximaversion . '/';
        $locations[] = 'C:/Program Files (x86)/Maxima-sbcl-' . $this->settings->maximaversion . '/';
        $locations[] = 'C:/Program Files/Maxima-gcl-' . $this->settings->maximaversion . '/';
        $locations[] = 'C:/Program Files (x86)/Maxima-gcl-' . $this->settings->maximaversion . '/';
        if ('5.25.1' == $this->settings->maximaversion) {
            $locations[] = 'C:/Program Files/Maxima-5.25.1-gcl/';
            $locations[] = 'C:/Program Files (x86)/Maxima-5.25.1-gcl/';
        }
        if ('5.28.0' == $this->settings->maximaversion) {
            $locations[] = 'C:/Program Files/Maxima-5.28.0-2/';
            $locations[] = 'C:/Program Files (x86)/Maxima-5.28.0-2/';
        }
        if ('5.31.1' == $this->settings->maximaversion) {
            $locations[] = 'C:/Program Files/Maxima-5.31.1-1/';
            $locations[] = 'C:/Program Files (x86)/Maxima-5.31.1-1/';
        }
        $locations[] = 'C:/Program Files/Maxima/';
        $locations[] = 'C:/Program Files (x86)/Maxima/';

        foreach ($locations as $location) {
            if (file_exists($location.'bin/maxima.bat')) {
                return $location;
            }
        }

        throw new stack_exception('Could not locate the directory into which Maxima is installed. Tried the following:' .
                implode(', ', $locations));
    }

    public function copy_maxima_bat() {
        global $CFG;

        if ($this->settings->platform != 'win') {
            return true;
        }
        $batchfilename = $this->maxima_win_location() . 'bin/maxima.bat';

        if (!copy($batchfilename, $CFG->dataroot . '/stack/maxima.bat')) {
            throw new stack_exception('Could not copy the Maxima batch file ' . $batchfilename .
                    ' to location ' . $CFG->dataroot . '/stack/maxima.bat');
        }
    }

    public function maxima_bat_is_ok() {
        global $CFG;

        if ($this->settings->platform != 'win') {
            return true;
        }

        return is_readable($CFG->dataroot . '/stack/maxima.bat');
    }

    public function get_maximalocal_contents() {
        $contents = <<<END
/* ***********************************************************************/
/* This file is automatically generated at installation time.            */
/* The purpose is to transfer configuration settings to Maxima.          */
/* Hence, you should not edit this file.  Edit your configuration.       */
/* This file is regularly overwritten, so your changes will be lost.     */
/* ***********************************************************************/

/* File generated on {$this->date} */

/* Add the location to Maxima's search path */
file_search_maxima:append( [sconcat("{$this->maximacodepath}/###.{mac,mc}")] , file_search_maxima)$
file_search_lisp:append( [sconcat("{$this->maximacodepath}/###.{lisp}")] , file_search_lisp)$
file_search_maxima:append( [sconcat("{$this->logpath}/###.{mac,mc}")] , file_search_maxima)$
file_search_lisp:append( [sconcat("{$this->logpath}/###.{lisp}")] , file_search_lisp)$

STACK_SETUP(ex):=block(
    MAXIMA_VERSION_NUM_EXPECTED:{$this->vnum},

END;
        foreach ($this->blocksettings as $name => $value) {
            $contents .= <<<END
    {$name}:"{$value}",

END;
        }
        $contents .= stack_cas_casstring_units::maximalocal_units();
        $contents .= <<<END
    true)$

END;

        if ($this->settings->platform == 'unix-optimised') {
            $contents .= <<<END
/* We are using an optimised lisp image with maxima and the stack libraries
   pre-loaded. That is why you don't see the familiar load("stackmaxima.mac")$ here.
   We do need to ensure the values of the variables is reset now.
*/
STACK_SETUP(true);
END;

        } else {
            $contents .= <<<END
/* Load the main libraries. */
load("stackmaxima.mac")$

END;
            $maximalib = $this->settings->maximalibraries;
            $maximalib = explode(',', $maximalib);
            foreach ($maximalib as $lib) {
                $lib = trim($lib);
                // Only include and load supported libraries.
                if (in_array($lib, self::$maximalibraries)) {
                    $contents .= 'load("'.$lib.'")$'."\n";
                }
            }

        }

        $contents .= 'print(sconcat("[ STACK-Maxima started, library version ", stackmaximaversion, " ]"))$'."\n";

        return $contents;
    }

    /**
     * @return stack_cas_configuration the singleton instance of this class.
     */
    protected static function get_instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get the full path for the maximalocal.mac file.
     * @return string the full path to where the maximalocal.mac file should be stored.
     */
    public static function maximalocal_location() {
        global $CFG;
        return stack_utils::convert_slash_paths($CFG->dataroot . '/stack/maximalocal.mac');
    }

    /**
     * Get the full path to the folder where plot files are stored.
     * @return string the full path to where the maximalocal.mac file should be stored.
     */
    public static function images_location() {
        global $CFG;
        return stack_utils::convert_slash_paths($CFG->dataroot . '/stack/plots');
    }

    /**
     * Create the maximalocal.mac file, overwriting it if it already exists.
     */
    public static function create_maximalocal() {
        make_upload_directory('stack');
        make_upload_directory('stack/logs');
        make_upload_directory('stack/plots');
        make_upload_directory('stack/tmp');

        self::get_instance()->copy_maxima_bat();

        if (!file_put_contents(self::maximalocal_location(), self::generate_maximalocal_contents())) {
            throw new stack_exception('Failed to write Maxima configuration file.');
        }
    }

    /**
     * Generate the contents for the maximalocal configuration file.
     * @return string the contents that the maximalocal.mac file should have.
     */
    public static function generate_maximalocal_contents() {
        return self::get_instance()->get_maximalocal_contents();
    }

    /**
     * Generate the contents for the maximalocal configuration file.
     * @return string the contents that the maximalocal.mac file should have.
     */
    public static function maxima_bat_is_missing() {
        return !self::get_instance()->maxima_bat_is_ok();
    }

    /**
     * Generate the directoryname
     * @return string the contents that the maximalocal.mac file should have.
     */
    public static function confirm_maxima_win_location() {
        return self::get_instance()->maxima_win_location();
    }

    /**
     * This function checks the current setting match to the supported packages.
     */
    protected function get_validate_maximalibraries() {

        $valid = true;
        $message = '';
        $maximalib = $this->settings->maximalibraries;
        $maximalib = explode(',', $maximalib);
        foreach ($maximalib as $lib) {
            $lib = trim($lib);
            // Only include and load supported libraries.
            if ($lib !== '' && !in_array($lib, self::$maximalibraries)) {
                $valid = false;
                $a = $lib;
                $message .= stack_string('settingmaximalibraries_error', $a);
            }
        }
        return(array($valid, $message));
    }

    /**
     * This function checks the current setting match to the supported packages.
     */
    public static function validate_maximalibraries() {
        return self::get_instance()->get_validate_maximalibraries();
    }

    /*
     * This function genuinely recreates the maxima image and stores the results in
     * the configuration settings.
     */
    public static function create_auto_maxima_image() {
        $config = get_config('qtype_stack');
            // Do not try to generate the optimised image on MS platforms.
        if ($config->platform == 'win') {
            return false;
        }

        /*
         * Revert to the plain unix platform.  This will genuinely call the CAS, and
         * as a result create a new image.
         */
        $oldplatform = $config->platform;
        $oldmaximacommand = $config->maximacommand;
        set_config('platform', 'unix', 'qtype_stack');
        set_config('maximacommand', '', 'qtype_stack');

        // Try to make a new version of the maxima local file.
        self::create_maximalocal();
        // Try to actually connect to Maxima.
        list($message, $genuinedebug, $result) = stack_connection_helper::stackmaxima_genuine_connect();

        // Check if the libraries look like they are messing things up.
        if (strpos($genuinedebug, 'eval_string not found') > 0) {
            // If so, get rid of the libraries and try again.
            set_config('maximalibraries', '', 'qtype_stack');
            list($message, $genuinedebug, $result) = stack_connection_helper::stackmaxima_genuine_connect();
        }

        $revert = false;
        if ($result && ($oldplatform == 'unix' || $oldplatform == 'unix-optimised')) {
            // Try to auto make the optimised image.
            list($message, $genuinedebug, $result, $commandline)
                 = stack_connection_helper::stackmaxima_auto_maxima_optimise($genuinedebug, true);

            if ($result) {
                set_config('platform', 'unix-optimised', 'qtype_stack');
                set_config('maximacommand', $commandline, 'qtype_stack');
                // We need to regenerate this file to supress stackmaxima.mac and libraries being reloaded.
                self::create_maximalocal();

                // Now we need to check this actually works.
                $cs = new stack_cas_casstring('a:1+1');
                $ts = new stack_cas_session(array($cs));
                $ts->instantiate();
                if ($ts->get_value_key('a') != '2') {
                    $revert = true;
                }
            } else {
                $revert = true;
            }
        } else {
            $revert = true;
        }

        if ($revert) {
            set_config('platform', $oldplatform, 'qtype_stack');
            set_config('maximacommand', $oldmaximacommand , 'qtype_stack');
            self::create_maximalocal();
        }
    }
}
