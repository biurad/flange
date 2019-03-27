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


/**
 * The Library Dependecy Manager
 * --------------------------------------
 *
 * This fetches all libraries needed for the framework.
 *
 * @category   Biurad
 */
class LoadManager
{
        /**
     * Module directory
     * @var String
     */
    private $moduleDirectory = null;

    /**
     * Module configuration
     * @var Array
     */
    private $moduleConfig = null;

    /**
     * Module constructor
     * @param String $moduleDirectory Module directory
     */
    public function __construct($moduleDirectory = null){

        if(is_null($moduleDirectory)) throw new Exception("Error : Fail to load library (Directory is null)", 1);
        $this->moduleDirectory = $moduleDirectory;

        // Load module
        $this->loadModule();
    }

    /**
     * Get module directory
     * @return String Module directory
     */
    public function getDirectory(){
        if(is_null($this->moduleDirectory)) throw new Exception("Error : Radion Library directory is empty", 1);
        return $this->moduleDirectory;
    }

    /**
     * Get module URL
     * @return String Module URL
     */
    public function getModuleURL(){ return BR_PATH.'Libraries/'.$this->getDirectory(); }

    /**
     * Get module PATH
     * @return String Module PATH
     */
    public function getModulePath(){ return BR_PATH.'Libraries/'.$this->getDirectory(); }

    /**
     * Load module
     */
    private function loadModule(){

        // Load module configuration file
        if(!file_exists($this->getModulePath().'/library.json')) throw new Exception("Error : Config file not exist for this library : ".$this->getDirectory(), 1);

        // Parse module configuration file from JSON
        //$this->moduleConfig = json_decode(file_get_contents($this->getModulePath().'/library.json'), true);

        //if(!$this->moduleConfig) throw new Exception("Error : Fail to open library config file for module : ".$this->_getDirectory(), 1);

    }

    /**
     * Check if module was enabled
     * @return boolean Module enable status
     */
    public function isEnable(){
        return true;
        if(!array_key_exists('enable', $this->moduleConfig)) return false;
        return $this->moduleConfig['enable'];
    }

    /**
     * Check module configuration file
     * @return Boolean Module configuration file was correct
     */
    public function checkConfig(){
        return true;
        if(count($this->moduleConfig) > 0) return true;
        return false;
    }

    /**
     * Load module controllers list
     * @return Array Controllers list
     */
    public function loadControllers(){
        $res = [];

        // Check if module directory controllers exist
        if(!file_exists($this->getModulePath().'/src')) return [];

        // Get list controllers list
        foreach (scandir($this->getModulePath().'/src') as $asset) {
        // Check validy controllers & is not directory
        if($asset == "." || $asset == ".." || is_dir($this->getModulePath().'/src/'.$asset)) continue;

        // Append assets
        $res[] = $asset;
        }
        return $res;
    }

    /**
     * This initializes the libraries function
     *
     * @param string $libs load all libraries
     * @throw BiuradException
     */
    public static function libraries($libs)
    {
        $file = BR_PATH . "Libraries/$libs.php";
        if (is_file($file)) {
            return include $file;
        }

        return self::coreLibraries($libs);
    }

    /**
     * The Core Libraries
     *
     * @param string $libs 
     * @throw Exception
     */
    public static function coreLibraries($libs)
    {
        if (!include BR_PATH . "Libraries/$libs/$libs.php") {
            throw new Exception("Library: \"$lib\" not found");
        }
    }
}
