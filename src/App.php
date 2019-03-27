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
 * The Application Container
 * -----------------------------------------------------------------------
 *
 * Containers are used for dependency injection, which allows us to reduce
 * coupling. It is a rather simple piece of code, but it is powerful.
 *
 */
class App {

	
	/**
     * Link a value inside the container.
     *
     * @param mixed $property_name property name that we want to register
     * @param mixed $value         the value/array/object/closure
     *
     */
    public function link($property_name, $value)
    {
        $this->$property_name = $value;
    }

    /**
     * Checks whether the container has a property.
     *
     * @param $property_name
     *
     * @return bool
     *
     */
    public function has($property_name)
    {
        return isset($this->$property_name);
    }

    /**
     * Check Every page to perform optional ajax
     * 
     * @static
     * @access public
     * @since Method available since Release 0.1.0
     */
    static function ajax_check($check = false) {?> <script src="<?= Config::get('info', 'URL'); ?>/assets/js/jquery-plugin.min.js"></script> <?php if($check == false) {echo ('<script>$(function() {$("html").attr("radion", "v__'.(Config::get('info', 'VERSION')).'");});</script>');} else {echo ('<script>$(document).ready(function(){$("html").attr("radion","gr__'.(Config::get('info', 'URL')).'");$("html").attr("version", "v__'.(Config::get('info', 'VERSION')).'");});$(document).on("click", "a:not([data-nd])", function() {var linkUrl = $(this).attr("href");loadPage(linkUrl, 0, null);return false;});$(window).bind("popstate", function() {var linkUrl = location.href;loadPage(linkUrl, 0, null);});/*** Send a GET or POST request dynamically** @param argUrl Contains the page URL* @param argParams String or serialized params to be passed to the request* @param argType Decides the type of the request: 1 for POST; 0 for GET;* @return string*/function loadPage(argUrl, argType, argParams) {if(argType == 1) {argType = "POST";} else {argType = "GET";if(argUrl != window.location){window.history.pushState({path: argUrl}, "", argUrl);}}$.ajax({url: argUrl,type: argType,data: argParams,success: function(data) {var result = data;$(document).scrollTop(0);$("v__'.Config::get('info', 'VERSION').'").replaceWith(result.v__'.Config::get('info', 'VERSION').');reload();}});/*** This function gets called every time a dynamic request is made*/function reload() {location.reload();}}</script>');}}
}