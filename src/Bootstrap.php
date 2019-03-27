<?php
/*
This code is under MIT License

+--------------------------------+
|   DO NOT MODIFY THIS HEADERS   |
+--------------------------------+-----------------+
|   Created by BiuStudio                           |
|   Email: support@biuhub.net                      |
|   Link: https://www.biurad.ml                    |
|   Source: https://github.com/biustudios/         |
|   Real Name: Divine Niiquaye - Ghana             |
|   Copyright Copyright (c) 2018-2019 BiuStudio    |
|   License: https://biurad.ml/LICENSE.md          |
+--------------------------------------------------+

+--------------------------------------------------------------------------------+
|   Version: 0.0.1.1, Relased at 18/02/2019 13:13 (GMT + 1.00)                       |
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

class Bootstrap
{
    /**
     * Module list available
     * @var Array Module Array
     */
    private $modulesList = [];

    /**
     * Application constructor
     * @param boolean $loadmodules If load module or just access to config data
     */
    public function __construct($loadmodules = false)
    {

        // If loadmodule, load modules
        if ($loadmodules) {
            $this->loadModules();
        }

    }

    /**
     * Load module function
     */
    public function loadModules()
    {

        // Get list modules available in application
        foreach (scandir(BR_PATH.'Libraries') as $directory) {

            // Check if file is an file
            if ($directory == "." || $directory == "..") {
                continue;
            }

            // Get directory path
            $directoryPath = BR_PATH.'Libraries/' . $directory;

            // Check if file parsed is a directory (module need to be a directory)
            if (!is_dir($directoryPath)) {

                // Save error in log file
                error_log('Fail to load module : ' . $directory . ' --> is not a directory');
                continue;
            }

            // Load module
            $ModuleLoad = new LoadManager($directory);

            // Check module configuratino file
            if (!$ModuleLoad->checkConfig()) {

                // Save error in log file
                error_log('Fail to load module : ' . $directory . ' --> wrong configuration');
                continue;
            }

            // Check if module is enabled
            if ($ModuleLoad->isEnable()) {
                // If enabled, save in module list
                $this->modulesList[$directory] = $ModuleLoad;
                if (!file_exists($this->getModulePath() . '/autoload.php')) {
                    throw new Exception("Error : Autoload file not exist for this library : " . $this->getDirectory(), 1);
                }

                require $this->getModulePath() . '/autoload.php';
            }
        }
    }
}
