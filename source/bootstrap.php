<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

define('INSTALLATION_ROOT_PATH', dirname(__DIR__));
define('OX_BASE_PATH', INSTALLATION_ROOT_PATH . DIRECTORY_SEPARATOR . 'source' . DIRECTORY_SEPARATOR);
define('OX_LOG_FILE', OX_BASE_PATH . 'log' . DIRECTORY_SEPARATOR . 'oxideshop.log');
define('OX_OFFLINE_FILE', OX_BASE_PATH . 'offline.html');
define('VENDOR_PATH', INSTALLATION_ROOT_PATH . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR);

/**
 * Provide a handler for catchable fatal errors, like failed requirement of files.
 * No information about paths or file names must be disclosed to the frontend,
 * as this would be a security problem on productive systems.
 * This error handler is just a last resort for exceptions, which are not caught by the application.
 *
 * As this is the last resort no further errors must happen.
 */
register_shutdown_function(
    function () {
        $handledErrorTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_RECOVERABLE_ERROR, E_USER_ERROR];
        $sessionResetErrorTypes = [E_ERROR];

        $error = error_get_last();
        if ($error !== null && in_array($error['type'], $handledErrorTypes)) {
            $errorType = array_flip(array_slice(get_defined_constants(true)['Core'], 0, 16, true))[$error['type']];

            $errorMessage = $error['message'];
            $unifiedNamespaceClassNotFound = preg_match(
                '/^Class \'OxidEsales\\\\Eshop\\\\.*\' not found/',
                $errorMessage,
                $matches
            );
            if (1 === $unifiedNamespaceClassNotFound) {
                $errorMessage .= '. Is an autogenerated class file missing? ' .
                                 'Please run "composer oe:unified-namespace:generate" and double-check for errors. ' .
                                 'Also double-check, if the class file for this very class was created.';
            }
            /** report the error */
            $logMessage = "[uncaught error] [type $errorType] [file {$error['file']}] [line {$error['line']}] [code ]" .
                          " [message {$errorMessage}]";

            /** write to log */
            $time = microtime(true);
            $micro = sprintf("%06d", ($time - floor($time)) * 1000000);
            $date = new \DateTime(date('Y-m-d H:i:s.' . $micro, $time));
            $timestamp = $date->format('d M H:i:s.u Y');
            $message = "[$timestamp] " . $logMessage . PHP_EOL;
            file_put_contents(OX_LOG_FILE, $message, FILE_APPEND);


            $bootstrapConfigFileReader = new \BootstrapConfigFileReader();
            if (!$bootstrapConfigFileReader->isDebugMode()) {
                \oxTriggerOfflinePageDisplay();
            }

            if (in_array($error['type'], $sessionResetErrorTypes)) {
                setcookie('sid', null, null, '/');
                setcookie('admin_sid', null, null, '/');
            }
        }
    }
);

// phpcs:disable
/**
 * Helper for loading and getting the config file contents
 */
class BootstrapConfigFileReader
{
    /**
     * BootstrapConfigFileReader constructor.
     */
    public function __construct()
    {
        include OX_BASE_PATH . "config.inc.php";
    }

    /**
     * Check if debug mode is On.
     *
     * @return bool
     */
    public function isDebugMode()
    {
        return (bool) $this->iDebug;
    }
}
// phpcs:enable

/**
 * Ensure shop config and autoload files are available.
 */
$configMissing = !is_readable(OX_BASE_PATH . "config.inc.php");
if ($configMissing || !is_readable(VENDOR_PATH . 'autoload.php')) {
    if ($configMissing) {
        $message = sprintf(
            "Error: Config file '%s' could not be found! Please use '%s.dist' to make a copy.",
            OX_BASE_PATH . "config.inc.php",
            OX_BASE_PATH . "config.inc.php"
        );
    } else {
        $message = "Error: Autoload file missing. Make sure you have ran the 'composer install' command.";
    }

    trigger_error($message, E_USER_ERROR);
}
unset($configMissing);

/**
 * Register basic the autoloaders. In this phase we still do not want to use other shop classes to make autoloading
 * as decoupled as possible.
 */

/*
 * Require and register composer autoloader.
 * This autoloader will load classes in the real existing namespace like '\OxidEsales\EshopCommunity\Core\UtilsObject'
 * It will always come first, even if you move it after the other autoloaders as it registers itself with prepend = true
 */
require_once VENDOR_PATH . 'autoload.php';

/**
 * Where CORE_AUTOLOADER_PATH points depends on how OXID eShop has been installed. If it is installed as part of a
 * compilation, the directory 'Core', where the auto load classes are located, does not reside inside OX_BASE_PATH,
 * but inside VENDOR_PATH.
 */
if (!is_dir(OX_BASE_PATH . 'Core')) {
    define('CORE_AUTOLOADER_PATH', (new \OxidEsales\Facts\Facts())->getCommunityEditionSourcePath() .
            DIRECTORY_SEPARATOR .
            'Core' . DIRECTORY_SEPARATOR .
            'Autoload' . DIRECTORY_SEPARATOR);
} else {
    define('CORE_AUTOLOADER_PATH', OX_BASE_PATH . 'Core' . DIRECTORY_SEPARATOR . 'Autoload' . DIRECTORY_SEPARATOR);
}

/*
 * Register the backwards compatibility autoloader.
 * This autoloader will load classes for reasons of backwards compatibility like 'oxArticle'.
 */
require_once CORE_AUTOLOADER_PATH . 'BackwardsCompatibilityAutoload.php';
spl_autoload_register([OxidEsales\EshopCommunity\Core\Autoload\BackwardsCompatibilityAutoload::class, 'autoload']);

/**
 * Register the module autoloader.
 */
require_once CORE_AUTOLOADER_PATH . 'ModuleAutoload.php';
spl_autoload_register([\OxidEsales\EshopCommunity\Core\Autoload\ModuleAutoload::class, 'autoload']);

/**
 * Store the shop configuration in the Registry prior including the custom bootstrap functionality.
 * Like this the shop configuration is available there.
 */
$configFile = new \OxidEsales\Eshop\Core\ConfigFile(OX_BASE_PATH . "config.inc.php");
\OxidEsales\Eshop\Core\Registry::set(\OxidEsales\Eshop\Core\ConfigFile::class, $configFile);
unset($configFile);

/**
 * Set exception handler before including modules/functions.php so it can be overwritten easiliy by shop operators.
 */
$debugMode = (bool) \OxidEsales\Eshop\Core\Registry::get(\OxidEsales\Eshop\Core\ConfigFile::class)->getVar('iDebug');
set_exception_handler(
    [
        new \OxidEsales\Eshop\Core\Exception\ExceptionHandler($debugMode),
        'handleUncaughtException'
    ]
);
unset($debugMode);

/**
 * Generic utility method file.
 * The global object factory function oxNew is defined here.
 */
require_once OX_BASE_PATH . 'oxfunctions.php';

/**
 * Custom bootstrap functionality.
 */
if (@is_readable(OX_BASE_PATH . 'modules/functions.php')) {
    include OX_BASE_PATH . 'modules/functions.php';
}

/**
 * The functions defined conditionally in this file may have been overwritten in 'modules/functions.php',
 * so their functionality may have changed completely.
 */
require_once OX_BASE_PATH . 'overridablefunctions.php';

//sets default PHP ini params
ini_set('session.name', 'sid');
ini_set('session.use_cookies', 0);
ini_set('session.use_trans_sid', 0);
ini_set('url_rewriter.tags', '');

if (!function_exists('oxTriggerOfflinePageDisplay')) {
    /**
     * Bulletproof offline page loader
     */
    function oxTriggerOfflinePageDisplay()
    {
        // Do not display the offline page, if this running in CLI mode
        if ('cli' !== strtolower(php_sapi_name())) {
            header("HTTP/1.1 500 Internal Server Error");
            header("Connection: close");

            /**
             * Render an error message.
             * If offline.php exists its content is displayed.
             * Like this the error message is overridable within that file.
             */
            if (is_readable(OX_OFFLINE_FILE)) {
                echo file_get_contents(OX_OFFLINE_FILE);
            };
        }
    }
}
