<?php
 /*
        This code is under MIT License
        
        +--------------------------------+
        |   DO NOT MODIFY THIS HEADERS   |
        +--------------------------------+-----------------+
        |   Created by BiuStudio                           |
        |   Email: support@biuhub.net                      |
        |   Link: https://www.biurad.tk                    |
        |   Source: https://github.com/biustudios/biurad   |
        |   Real Name: Divine Niiquaye - Ghana             |
        |   Copyright Copyright (c) 2018-2019 BiuStudio    |
        |   License: https://biurad.tk/LICENSE.md          |
        +--------------------------------------------------+
        
        +--------------------------------------------------------------------------------+
        |   Version: 0.0.1.1, Relased at 18/02/2019 13:13 (GMT + 1.00)                   |
        +--------------------------------------------------------------------------------+
        
        +----------------+
        |   Tested on    |
        +----------------+-----+
        |  APACHE => 2.0.55    |
        |     PHP => 5.4       |
        +----------------------+
        
        +---------------------+
        |  How to report bug  |
        +---------------------+-----------------------------------------------------------------+
        |   You can e-mail me using the email addres written above. That email is also my msn   |
        |   contact, so you can use it for contact me on MSN.                                   |
        +---------------------------------------------------------------------------------------+
        
        +-----------+
        |  Notes    |
        +-----------+------------------------------------------------------------------------------------------------+
        |   - BiuRad's simple-as-possible architecture was inspired by several conference talks, slides              |
        |     and articles about php frameworks that - surprisingly and intentionally -                              |
        |     go back to the basics of programming, using procedural programming, static classes,                    |
        |     extremely simple constructs, not-totally-DRY code etc. while keeping the code extremely readable.      |
        |   - Features of Biuraad Php Framework
        |     +--> Proper security features, like CSRF blocking (via form tokens), encryption of cookie contents etc.|
        |     +--> Built with the official PHP password hashing functions, fitting the most modern password          |
                   hashing/salting web standards.                                                                    |
        |     +--> Uses [Post-Redirect-Get pattern](https://en.wikipedia.org/wiki/Post/Redirect/Get)                 |
        |     <--+ Uses URL rewriting ("beautiful URLs").                                                            |
        |   - Masses of comments                                                                                     |                                                                              |
        |     +--> Uses Libraries including Composer to load external dependencies.                                  |
        |     <--+ Proper security features, like CSRF blocking (via form tokens), encryption of cookie contents etc.|
        |   - Fits PSR-0/1/2/4 coding guideline.                                                                     |
        +------------------------------------------------------------------------------------------------------------+
        
        +------------------+
        |  Special Thanks  |
        +------------------+-----------------------------------------------------------------------------------------+
        |  I always thank the HTML FORUM COMMUNITY (http://www.html.it) for the advice about the regular expressions |
        |  A special thanks at github.com(http://www.github.com), because they provide me the list of php libraries, |
        |  snippets, and any more...                                                                                 |
        |  I thanks Php.net and Sololearn.com for its guildline in PHP Programming                                   |
        |  Finally, i thank Wikipedia for the countries's icons 20px                                                 |
        +------------------------------------------------------------------------------------------------------------+
*/
namespace Radion;

use RLis\Rade\RadeView as Rade;
use Radion\SharerManager as Sharer;

/**
 * The Viewer Manager
 * -----------------------------------------------------------------------
 *
 * Reads and render the template file. Responsible for injecting
 * dependencies from both Container and the Radion\SharerManager
 *
 */
class ViewerManager
{

    // the hive is where all data is stored, which is then usable from all template
    // files
    private static $hive = [];

    /**
     * Finds, renders and displays a template file. Reports a 404 error in
     * case of missing files.
     *
     * @param string	$file		file name / path to the file
     * @param array		$data	array of data
     *
     * @static
     * @access public
     * @see Viewer::render()
     * @since Method available since Release 0.1.0
     */
    static function file($file, array $data = [])
    {
        if (Config::_get('theme', 'Template') === 'DEFAULT') {
            // Do you love displaying blank pages?
            if ($file === 'index' || $file === 'index.php') {
                ExceptionManager::report(404, true);
            } else {
                /**
                 * Get the path of the calling script and get it's containing Directory
                 * to enable include() style of accessing files
                 */
                $callingScriptPath = debug_backtrace()[0]['file'];
                $callingScriptDirectory = realpath(dirname($callingScriptPath));
                if (file_exists($callingScriptDirectory . '/' . 'Resources/Themes/' . Config::get(theme.theme_style) . '/views/' . $file)) {
                    self::render($callingScriptDirectory . '/' . 'Resources/Themes/' . Config::get(theme.theme_style) . '/views/' . $file, $data);
                } else if (file_exists($callingScriptDirectory . '/' . 'Resources/Themes/' . Config::get(theme.theme_style) . '/views/' . $file . '.php')) {
                    self::render($callingScriptDirectory . '/' . 'Resources/Themes/' . Config::get(theme.theme_style) . '/views/' . $file . '.php', $data);
                } else if (file_exists(BR_PATH . 'Resources/Themes/' . Config::get(theme.theme_style) . '/views/' . $file)) {
                    self::render('Resources/Themes/' . Config::get(theme.theme_style) . '/views/' . $file, $data);
                } else if (file_exists(BR_PATH . 'Resources/Themes/' . Config::get(theme.theme_style) . '/views/' . $file . '.php')) {
                    self::render(BR_PATH . 'Resources/Themes/' . Config::get(theme.theme_style) . '/views/' . $file . '.php', $data);
                } else {
                    ExceptionManager::report(404, true);
                }
            }
        } else if (Config::get(theme.Template) === 'RADEVIEW') {
            $views = 'Resources/Themes/' . Config::get(theme.theme_style) . '/views';
            $compiledFolder = 'Resources/' . Config::get(theme.storage_path) . '/framework';
            $rade = new Rade($views, $compiledFolder);
            define("RADEVIEW_MODE", 0); // (optional) 1=forced (test),2=run fast (production), 0=automatic, default value.
            // Do you love displaying blank pages?
            if ($file === 'index' || $file === 'index.php') {
                ExceptionManager::report(404, true);
            } else {
                /**
                 * Get the path of the calling script and get it's containing Directory
                 * to enable include() style of accessing files
                 */
                echo $rade->run($file, $data);
            }
        } else if (Config::get(theme.Template) === 'TPL') {
            /**
             * Get the path of the calling script and get it's containing Directory
             * to enable include() style of accessing files.
             */
            $calling_script_path = debug_backtrace()[0]['file'];
            $calling_script_directory = realpath(dirname($calling_script_path));

            /**
             * Check if file exists, try directories
             * 1. in the same directory as the calling script
             * 2. same as #1 but without .tpl.php
             * 3. Check in resources/views directory
             * 4. same as #3 but without .tpl.php
             * 5. check on the root directory
             * 6. same #5 but without .tpl.php
             */
            if (file_exists($render_path = $calling_script_directory . '/' . 'Resources/Themes/' . Config::get(theme.theme_style) . '/views/' . $file . '.tpl.php')) {
                self::render($render_path, $data);
            } elseif (file_exists($render_path = $calling_script_directory . '/' . 'Resources/Themes/' . Config::get(theme.theme_style) . '/views/' . $file)) {
                self::render($render_path, $data);
            } elseif (file_exists($render_path = BR_PATH . 'Resources/Themes/' . Config::get(theme.theme_style) . '/views/' . $file . '.tpl.php')) {
                self::render($render_path, $data);
            } elseif (file_exists($render_path = BR_PATH . 'Resources/Themes/' . Config::get(theme.theme_style) . '/views/' . $file)) {
                self::render($render_path, $data);
            } elseif (file_exists($render_path = BR_PATH . '/' . 'Resources/Themes/' . Config::get(theme.theme_style) . '/views/' . $file . '.tpl.php')) {
                self::render($render_path, $data);
            } elseif (file_exists($render_path = BR_PATH . '/' . 'Resources/Themes/' . Config::get(theme.theme_style) . '/views/' . $file)) {
                self::render($render_path, $data);
            } else {
                ExceptionManager::report(404, true);
            }
        } else {
            ExceptionManager::display('simple', 'Viewer Template Note Found', "Set your preferred Viewer Template in Theme's Config File");
        }
    }

    /**
     * Renders a template file. Inject dependencies from the Application
     * Container and the Radion\Sharer before viewing the file. Also,
     * extracts &$data into variables usable from the template files
     *
     * @param string	$file		file name / path to the file
     *
     * @static
     * @access private
     * @since Method available since Release 0.1.0
     */
    static private function render($file, $data)
    {
        extract($data);
        // Extract data retreived from the Sharer
        if (Sharer::get() !== null) {
            extract(Sharer::get());
        }

        // Merge data into the hive
        self::$hive = array_merge(self::$hive, get_defined_vars());
        unset($data);

        ob_start();
        //if(isAjax()) {
        //    include(sprintf('%s/%s/%s/views/%s.php', Config::get('theme', 'theme_folder'), Config::get('theme', 'theme_path'), Config::get(theme.theme_style), $file));
        //} else {
        //    include(sprintf('%s/%s/%s/views/%s.php', Config::get('theme', 'theme_folder'), Config::get('theme', 'theme_path'), Config::get(theme.theme_style), $file));
        //}
        include($file);
        $input = ob_get_contents();
        ob_end_clean();

        $output = preg_replace_callback('!\{\{(.*?)\}\}!', 'ViewerManager::replace', $input);


        echo ($output);
    }

    static private function replace($matches)
    {
        // If '.' is found in the $matches[1], assume it is an object
        // which have a property

        // else, assume it is a variable
        if (strpos($matches[1], '.') !== false) {
            // explode the part before and after '.'
            // the part before '.' is an object, while the part after '.' is a property
            list($object, $property) = explode('.', $matches[1]);

            // if a '()' is found in $property, we will then assume it to be a callable
            // method.
            if (strpos($property, '()') !== false) {
                // remove paranthesis
                list($function, $parenthesis) = explode('()', $property);

                // return the callable method of the object from the hive
                return (self::$hive[$object]->$function());
            } else {
                // return the property of the object from the hive
                return (self::$hive[$object]->$property);
            }
        } else {
            if (isset(self::$hive[$matches[1]])) {
                return self::$hive[$matches[1]];
            }
        }
    }
}

