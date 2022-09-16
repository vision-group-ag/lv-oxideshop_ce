<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidEsales\EshopCommunity\Core;

use OxidEsales\Eshop\Core\Contract\IDisplayError;
use OxidEsales\Eshop\Core\Exception\StandardException;
use OxidEsales\Eshop\Core\Module\ModuleSmartyPluginDirectoryRepository;
use OxidEsales\Eshop\Core\Module\ModuleTemplateBlockRepository;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Facade\ActiveModulesDataProviderBridgeInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Module\TemplateExtension\TemplateBlockLoaderBridgeInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Templating\TemplateRendererBridgeInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Templating\TemplateRendererInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Theme\Bridge\AdminThemeBridgeInterface;
use Smarty;
use Webmozart\PathUtil\Path;

class UtilsView extends \OxidEsales\Eshop\Core\Base
{
    /**
     * Templates directories array
     *
     * @var array
     */
    protected $_aTemplateDir = [];

    /**
     * Templates directories array
     *
     * @var array
     */
    protected $_blIsTplBlocks = null;

    /**
     * Templating instance getter
     *
     * @return TemplateRendererInterface
     */
    private function getRenderer()
    {
        return $this->getContainer()->get(TemplateRendererBridgeInterface::class)->getTemplateRenderer();
    }

    /**
     * Returns rendered template output. According to debug configuration outputs
     * debug information.
     *
     * @param string $templateName template file name
     * @param object $oObject      object, witch template we wish to output
     *
     * @return string
     */
    public function getTemplateOutput($templateName, $oObject)
    {
        $debugMode = Registry::getConfig()->getConfigParam('iDebug');

        // assign
        $viewData = $oObject->getViewData();
        if (is_array($viewData)) {
            foreach (array_keys($viewData) as $viewName) {
                // show debug information
                if ($debugMode == 4) {
                    echo("TemplateData[$viewName] : \n");
                    var_export($viewData[$viewName]);
                }
            }
        } else {
            $viewData = [];
        }

        return $this->getRenderer()->renderTemplate($templateName, $viewData);
    }

    /**
     * adds the given errors to the view array
     *
     * @param array $aView  view data array
     * @param array $errors array of errors to pass to view
     */
    public function passAllErrorsToView(&$aView, $errors)
    {
        if (count($errors) > 0) {
            foreach ($errors as $sLocation => $aEx2) {
                foreach ($aEx2 as $sKey => $oEr) {
                    $aView['Errors'][$sLocation][$sKey] = unserialize($oEr);
                }
            }
        }
    }

    /**
     * Adds an exception to the array of displayed exceptions for the view
     * by default is displayed in the inc_header, but with the custom destination set to true
     * the exception won't be displayed by default but can be displayed where ever wanted in the tpl
     *
     * @param StandardException|IDisplayError|string $exception            an exception object or just a language local (string),
     *                                                                     which will be converted into a oxExceptionToDisplay object
     * @param bool                                   $blFull               if true the whole object is add to display (default false)
     * @param bool                                   $useCustomDestination true if the exception shouldn't be displayed
     *                                                                     at the default position (default false)
     * @param string                                 $customDestination    defines a name of the view variable containing
     *                                                                     the messages, overrides Parameter 'CustomError' ("default")
     * @param string                                 $activeController     defines a name of the controller, which should
     *                                                                     handle the error.
     */
    public function addErrorToDisplay($exception, $blFull = false, $useCustomDestination = false, $customDestination = "", $activeController = "")
    {
        //default
        $destination = 'default';
        $customDestination = $customDestination ? $customDestination : Registry::getRequest()->getRequestEscapedParameter('CustomError');
        if ($useCustomDestination && $customDestination) {
            $destination = $customDestination;
        }

        //starting session if not yet started as all exception
        //messages are stored in session
        $session = Registry::getSession();
        if (!$session->getId() && !$session->isHeaderSent()) {
            $session->setForceNewSession();
            $session->start();
        }

        $sessionErrors = Registry::getSession()->getVariable('Errors');
        if ($exception instanceof \OxidEsales\Eshop\Core\Exception\StandardException) {
            $exceptionToDisplay = oxNew(\OxidEsales\Eshop\Core\Exception\ExceptionToDisplay::class);
            $exceptionToDisplay->setMessage($exception->getMessage());
            $exceptionToDisplay->setExceptionType($exception->getType());

            if ($exception instanceof \OxidEsales\Eshop\Core\Exception\SystemComponentException) {
                $exceptionToDisplay->setMessageArgs($exception->getComponent());
            }

            $exceptionToDisplay->setValues($exception->getValues());
            $exceptionToDisplay->setStackTrace($exception->getTraceAsString());
            $exceptionToDisplay->setDebug($blFull);
            $exception = $exceptionToDisplay;
        } elseif ($exception instanceof \Throwable) {
            $tempException = $exception;
            $exception = oxNew(\OxidEsales\Eshop\Core\DisplayError::class);
            $exception->setMessage($tempException->getMessage());
        } elseif ($exception && !($exception instanceof \OxidEsales\Eshop\Core\Contract\IDisplayError)) {
            $tempException = $exception;
            $exception = oxNew(\OxidEsales\Eshop\Core\DisplayError::class);
            $exception->setMessage($tempException);
        } elseif ($exception instanceof \OxidEsales\Eshop\Core\Contract\IDisplayError) {
            // take the object
        } else {
            $exception = null;
        }

        if ($exception) {
            $sessionErrors[$destination][] = serialize($exception);
            Registry::getSession()->setVariable('Errors', $sessionErrors);

            if ($activeController == '') {
                $activeController = Registry::getRequest()->getRequestEscapedParameter('actcontrol');
            }
            if ($activeController) {
                $aControllerErrors[$destination] = $activeController;
                Registry::getSession()->setVariable('ErrorController', $aControllerErrors);
            }
        }
    }

    /**
     * Templates directory setter
     *
     * @param string $templatesDirectory templates path
     */
    public function setTemplateDir($templatesDirectory)
    {
        if ($templatesDirectory && !in_array($templatesDirectory, $this->_aTemplateDir)) {
            $this->_aTemplateDir[] = $templatesDirectory;
        }
    }

    /**
     * Initializes and returns templates directory info array
     *
     * @return array
     */
    public function getTemplateDirs()
    {
        $config = Registry::getConfig();

        // buffer for CE (main) edition templates
        $mainTemplatesDirectory = $config->getTemplateDir($this->isAdmin());

        // main templates directory has not much priority anymore
        $this->setTemplateDir($mainTemplatesDirectory);

        // out directory can have templates too
        if (!$this->isAdmin()) {
            $this->setTemplateDir($this->addActiveThemeId($config->getOutDir(true)));
        }

        return $this->_aTemplateDir;
    }

    /**
     * Returns a full path to Smarty compile dir
     *
     * @deprecated since 6.13.0 please use
     *
     * @return string
     */
    public function getSmartyDir()
    {
        $config = Registry::getConfig();

        //check for the Smarty dir
        $compileDir = $config->getConfigParam('sCompileDir');
        $smartyDir = $compileDir . "/smarty/";
        if (!is_dir($smartyDir)) {
            @mkdir($smartyDir);
        }

        if (!is_writable($smartyDir)) {
            $smartyDir = $compileDir;
        }

        return $smartyDir;
    }

    /**
     * Template blocks getter: retrieve sorted blocks for overriding in templates
     *
     * @param string $templateFileName filename of rendered template
     *
     * @see smarty_prefilter_oxblock
     *
     * @return array
     */
    public function getTemplateBlocks($templateFileName)
    {
        $templateBlocksWithContent = [];

        $config = Registry::getConfig();

        $tplDir = trim($config->getConfigParam('_sTemplateDir'), '/\\');
        $templateFileName = str_replace(['\\', '//'], '/', $templateFileName);
        if (preg_match('@/' . preg_quote($tplDir, '@') . '/(.*)$@', $templateFileName, $m)) {
            $templateFileName = $m[1];
        }

        if ($this->isShopTemplateBlockOverriddenByActiveModule()) {
            $shopId = $config->getShopId();

            $activeModulesIds = $this->getContainer()->get(ActiveModulesDataProviderBridgeInterface::class)->getModuleIds();
            $activeThemeIds = oxNew(\OxidEsales\Eshop\Core\Theme::class)->getActiveThemesList();

            $templateBlockRepository = oxNew(ModuleTemplateBlockRepository::class);
            $activeBlockTemplates = $templateBlockRepository->getBlocks($templateFileName, $activeModulesIds, $shopId, $activeThemeIds);

            if ($activeBlockTemplates) {
                $activeBlockTemplatesByTheme = $this->filterTemplateBlocks($activeBlockTemplates);

                $templateBlocksWithContent = $this->fillTemplateBlockWithContent($activeBlockTemplatesByTheme);
            }
        }

        return $templateBlocksWithContent;
    }

    /**
     * Add active theme at the end of theme path to form full path to templates.
     *
     * @param string $themePath
     *
     * @return string
     */
    protected function addActiveThemeId(string $themePath): string
    {
        $themeId = $this->isAdmin()
            ? $this->getContainer()->get(AdminThemeBridgeInterface::class)->getActiveTheme()
            : Registry::getConfig()->getConfigParam('sTheme');

        return Path::join(
            $themePath,
            $themeId,
            'tpl'
        )
            . DIRECTORY_SEPARATOR;
    }

    /**
     * Leave only one element for items grouped by fields: OXTEMPLATE and OXBLOCKNAME
     *
     * Pick only one element from each group if OXTHEME contains (by following priority):
     * - Active theme id
     * - Parent theme id of active theme
     * - Undefined
     *
     * Example of $activeBlockTemplates:
     *
     *  OXTEMPLATE = "requested_template_name"  OXBLOCKNAME = "block_name_a" (group a)
     *  OXTHEME = ""
     *  "content_a_default"
     *
     *  OXTEMPLATE = "requested_template_name"  OXBLOCKNAME = "block_name_a" (group a)
     *  OXTHEME = "parent_of_active_theme"
     *  "content_a_parent"
     *
     *  OXTEMPLATE = "requested_template_name"  OXBLOCKNAME = "block_name_a" (group a)
     *  OXTHEME = "active_theme"
     *  "content_a_active"
     *
     *
     *  OXTEMPLATE = "requested_template_name"  OXBLOCKNAME = "block_name_b" (group b)
     *  OXTHEME = ""
     *  "content_b_default"
     *
     *  OXTEMPLATE = "requested_template_name"  OXBLOCKNAME = "block_name_b" (group b)
     *  OXTHEME = "parent_of_active_theme"
     *  "content_b_parent"
     *
     *
     *  OXTEMPLATE = "requested_template_name"  OXBLOCKNAME = "block_name_c" (group c)
     *  OXTHEME = ""
     *  OXFILE = "x"
     *  "content_c_x_default"
     *
     *  OXTEMPLATE = "requested_template_name"  OXBLOCKNAME = "block_name_c" (group c)
     *  OXTHEME = ""
     *  OXFILE = "y"
     *  "content_c_y_default"
     *
     * Example of return:
     *
     *  OXTEMPLATE = "requested_template_name"  OXBLOCKNAME = "block_name_a" (group a)
     *  OXTHEME = "active_theme"
     *  "content_a_active"
     *
     *
     *  OXTEMPLATE = "requested_template_name"  OXBLOCKNAME = "block_name_b" (group b)
     *  OXTHEME = "parent_of_active_theme"
     *  "content_b_parent"
     *
     *
     *  OXTEMPLATE = "requested_template_name"  OXBLOCKNAME = "block_name_c" (group c)
     *  OXTHEME = ""
     *  OXFILE = "x"
     *  "content_c_x_default"
     *
     *  OXTEMPLATE = "requested_template_name"  OXBLOCKNAME = "block_name_c" (group c)
     *  OXTHEME = ""
     *  OXFILE = "y"
     *  "content_c_y_default"
     *
     * @param array $activeBlockTemplates list of template blocks with all parameters.
     *
     * @return array list of blocks with their content.
     */
    private function filterTemplateBlocks($activeBlockTemplates)
    {
        $templateBlocks = $activeBlockTemplates;

        $templateBlocksToExchange = $this->formListOfDuplicatedBlocks($activeBlockTemplates);

        if ($templateBlocksToExchange['theme']) {
            $templateBlocks = $this->removeDefaultBlocks($activeBlockTemplates, $templateBlocksToExchange);
        }

        if ($templateBlocksToExchange['custom_theme']) {
            $templateBlocks = $this->removeParentBlocks($templateBlocks, $templateBlocksToExchange);
        }

        return $templateBlocks;
    }

    /**
     * Form list of blocks which has duplicates for specific theme.
     *
     * @param array $activeBlockTemplates
     *
     * @return array
     */
    private function formListOfDuplicatedBlocks($activeBlockTemplates)
    {
        $templateBlocksToExchange = [];
        $customThemeId = Registry::getConfig()->getConfigParam('sCustomTheme');

        foreach ($activeBlockTemplates as $activeBlockTemplate) {
            if ($activeBlockTemplate['OXTHEME']) {
                if ($customThemeId && $customThemeId === $activeBlockTemplate['OXTHEME']) {
                    $templateBlocksToExchange['custom_theme'][] = $this->prepareBlockKey($activeBlockTemplate);
                } else {
                    $templateBlocksToExchange['theme'][] = $this->prepareBlockKey($activeBlockTemplate);
                }
            }
        }

        return $templateBlocksToExchange;
    }

    /**
     * Remove default blocks whose have duplicate for specific theme.
     *
     * @param array $activeBlockTemplates
     * @param array $templateBlocksToExchange
     *
     * @return array
     */
    private function removeDefaultBlocks($activeBlockTemplates, $templateBlocksToExchange)
    {
        $templateBlocks = [];
        foreach ($activeBlockTemplates as $activeBlockTemplate) {
            if (
                !in_array($this->prepareBlockKey($activeBlockTemplate), $templateBlocksToExchange['theme'])
                || $activeBlockTemplate['OXTHEME']
            ) {
                $templateBlocks[] = $activeBlockTemplate;
            }
        }

        return $templateBlocks;
    }

    /**
     * Remove parent theme blocks whose have duplicate for custom theme.
     *
     * @param array $templateBlocks
     * @param array $templateBlocksToExchange
     *
     * @return array
     */
    private function removeParentBlocks($templateBlocks, $templateBlocksToExchange)
    {
        $activeBlockTemplates = $templateBlocks;
        $templateBlocks = [];
        $customThemeId = Registry::getConfig()->getConfigParam('sCustomTheme');
        foreach ($activeBlockTemplates as $activeBlockTemplate) {
            if (
                !in_array($this->prepareBlockKey($activeBlockTemplate), $templateBlocksToExchange['custom_theme'])
                || $activeBlockTemplate['OXTHEME'] === $customThemeId
            ) {
                $templateBlocks[] = $activeBlockTemplate;
            }
        }

        return $templateBlocks;
    }

    /**
     * Fill array with template content or skip if template does not exist.
     * Logs error message if template does not exist.
     *
     * Example of $activeBlockTemplates:
     *
     *  OXTEMPLATE = "requested_template_name"  OXBLOCKNAME = "block_name_a"
     *  "content_a_active"
     *
     *  OXTEMPLATE = "requested_template_name"  OXBLOCKNAME = "block_name_b"
     *  OXFILE = "x"
     *  "content_b_x_default"
     *
     *  OXTEMPLATE = "requested_template_name"  OXBLOCKNAME = "block_name_b"
     *  OXFILE = "y"
     *  "content_b_y_default"
     *
     * Example of return:
     *
     * $templateBlocks = [
     *   block_name_a = [
     *     0 => "content_a_active"
     *   ],
     *   block_name_c = [
     *     0 => "content_b_x_default",
     *     1 => "content_b_y_default"
     *   ]
     * ]
     *
     * @param array $blockTemplates
     *
     * @return array
     */
    private function fillTemplateBlockWithContent($blockTemplates)
    {
        $templateBlocksWithContent = [];

        foreach ($blockTemplates as $activeBlockTemplate) {
            try {
                if (!is_array($templateBlocksWithContent[$activeBlockTemplate['OXBLOCKNAME']])) {
                    $templateBlocksWithContent[$activeBlockTemplate['OXBLOCKNAME']] = [];
                }
                $templateBlocksWithContent[$activeBlockTemplate['OXBLOCKNAME']][] = $this
                    ->getContainer()
                    ->get(TemplateBlockLoaderBridgeInterface::class)
                    ->getContent(
                        $activeBlockTemplate['OXFILE'],
                        $activeBlockTemplate['OXMODULE']
                    );
            } catch (\OxidEsales\Eshop\Core\Exception\StandardException $exception) {
                Registry::getLogger()->error($exception->getMessage(), [$exception]);
            }
        }

        return $templateBlocksWithContent;
    }

    /**
     * Check if at least one active module overrides at least one template (in active shop).
     * To win performance when:
     * - no active modules exists.
     * - none active module overrides template.
     *
     * @return bool
     */
    private function isShopTemplateBlockOverriddenByActiveModule()
    {
        if ($this->_blIsTplBlocks !== null) {
            return $this->_blIsTplBlocks;
        }

        $moduleOverridesTemplate = false;

        $activeModulesIds = $this->getContainer()->get(ActiveModulesDataProviderBridgeInterface::class)->getModuleIds();
        if (count($activeModulesIds)) {
            $templateBlockRepository = oxNew(ModuleTemplateBlockRepository::class);

            $blocksCount = $templateBlockRepository->getBlocksCount($activeModulesIds, Registry::getConfig()->getShopId());

            if ($blocksCount) {
                $moduleOverridesTemplate = true;
            }
        }

        $this->_blIsTplBlocks = $moduleOverridesTemplate;

        return $moduleOverridesTemplate;
    }

    /**
     * Prepare indicator for template block.
     * This indicator might be used to identify same template block for different theme.
     *
     * @param array $activeBlockTemplate
     *
     * @return string
     */
    private function prepareBlockKey($activeBlockTemplate)
    {
        return $activeBlockTemplate['OXTEMPLATE'] . $activeBlockTemplate['OXBLOCKNAME'];
    }
}
