<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

declare(strict_types=1);

namespace OxidEsales\EshopCommunity\Tests\Integration\Internal\Transition\Smarty;

use OxidEsales\EshopCommunity\Core\Registry;
use OxidEsales\EshopCommunity\Internal\Framework\Smarty\Configuration\SmartyPluginsDataProviderInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Smarty\Configuration\SmartyConfigurationFactoryInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Smarty\SmartyBuilder;
use OxidEsales\EshopCommunity\Tests\Integration\IntegrationTestCase;

class SmartyBuilderTest extends IntegrationTestCase
{
    private $debugMode;

    public function setup(): void
    {
        parent::setUp();
        $this->debugMode = Registry::getConfig()->getConfigParam('iDebug');
    }

    public function tearDown(): void
    {
        Registry::getConfig()->setConfigParam('iDebug', $this->debugMode);
        parent::tearDown();
    }

    /**
     * @dataProvider smartySettingsDataProvider
     *
     * @param bool  $securityMode
     * @param array $smartySettings
     */
    public function testSmartySettingsAreSetCorrect($securityMode, $smartySettings)
    {
        $config = Registry::getConfig();
        $config->setConfigParam('blDemoShop', $securityMode);
        $config->setConfigParam('iDebug', 0);

        $configuration = $this->get(SmartyConfigurationFactoryInterface::class)->getConfiguration();
        $smarty = (new SmartyBuilder())
            ->setSettings($configuration->getSettings())
            ->setSecuritySettings($configuration->getSecuritySettings())
            ->registerPlugins($configuration->getPlugins())
            ->registerPrefilters($configuration->getPrefilters())
            ->registerResources($configuration->getResources())
            ->getSmarty();

        foreach ($smartySettings as $varName => $varValue) {
            $this->assertTrue(isset($smarty->$varName), $varName . ' setting was not set');
            $this->assertEquals($varValue, $smarty->$varName, 'Not correct value of the smarts setting: ' . $varName);
        }
    }

    /**
     * @return array
     */
    public function smartySettingsDataProvider()
    {
        return [
            'security on' => [1, $this->getSmartySettingsWithSecurityOn()],
            'security off' => [0, $this->getSmartySettingsWithSecurityOff()]
        ];
    }

    private function getSmartySettingsWithSecurityOn(): array
    {
        $config = Registry::getConfig();
        $templateDirectories = Registry::getUtilsView()->getTemplateDirs();
        $shopId = $config->getShopId();
        return [
            'security' => true,
            'php_handling' => 2,
            'left_delimiter' => '[{',
            'right_delimiter' => '}]',
            'caching' => false,
            'compile_dir' => $config->getConfigParam('sCompileDir') . "/smarty/",
            'cache_dir' => $config->getConfigParam('sCompileDir') . "/smarty/",
            'compile_id' => md5(reset($templateDirectories) . '__' . $shopId),
            'template_dir' => $templateDirectories,
            'debugging' => false,
            'compile_check' => $config->getConfigParam('blCheckTemplates'),
            'security_settings' => [
                'PHP_HANDLING' => false,
                'IF_FUNCS' =>
                    [
                        0 => 'array',
                        1 => 'list',
                        2 => 'isset',
                        3 => 'empty',
                        4 => 'count',
                        5 => 'sizeof',
                        6 => 'in_array',
                        7 => 'is_array',
                        8 => 'true',
                        9 => 'false',
                        10 => 'null',
                        11 => 'XML_ELEMENT_NODE',
                        12 => 'is_int',
                    ],
                'INCLUDE_ANY' => false,
                'PHP_TAGS' => false,
                'MODIFIER_FUNCS' =>
                    [
                        0 => 'count',
                        1 => 'round',
                        2 => 'floor',
                        3 => 'trim',
                        4 => 'implode',
                        5 => 'is_array',
                        6 => 'getimagesize',
                    ],
                'ALLOW_CONSTANTS' => true,
                'ALLOW_SUPER_GLOBALS' => true,
            ],
            'plugins_dir' => $this->getSmartyPlugins(),
        ];
    }

    private function getSmartySettingsWithSecurityOff(): array
    {
        $config = Registry::getConfig();
        $templateDirectories = Registry::getUtilsView()->getTemplateDirs();
        $shopId = $config->getShopId();
        return [
            'security' => false,
            'php_handling' => $config->getConfigParam('iSmartyPhpHandling'),
            'left_delimiter' => '[{',
            'right_delimiter' => '}]',
            'caching' => false,
            'compile_dir' => $config->getConfigParam('sCompileDir') . "/smarty/",
            'cache_dir' => $config->getConfigParam('sCompileDir') . "/smarty/",
            'compile_id' => md5(reset($templateDirectories) . '__' . $shopId),
            'template_dir' => $templateDirectories,
            'debugging' => false,
            'compile_check' => $config->getConfigParam('blCheckTemplates'),
            'plugins_dir' => $this->getSmartyPlugins(),
        ];
    }

    private function getSmartyPlugins()
    {
        /** @var SmartyPluginsDataProviderInterface $pluginProvider */
        $pluginProvider = $this->get(SmartyPluginsDataProviderInterface::class);
        return array_merge($pluginProvider->getPlugins(), ['plugins']);
    }
}
