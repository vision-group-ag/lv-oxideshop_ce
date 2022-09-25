<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

declare(strict_types=1);

namespace OxidEsales\EshopCommunity\Tests\Integration\Internal\Transition\Smarty;

use OxidEsales\Eshop\Core\Config;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\UtilsView;
use OxidEsales\EshopCommunity\Internal\Framework\Smarty\SmartyContext;
use OxidEsales\EshopCommunity\Tests\Unit\Internal\ContextStub;
use PHPUnit\Framework\TestCase;

class SmartyContextTest extends TestCase
{
    public function getTemplateEngineDebugModeDataProvider(): array
    {
        return [
            [1, true],
            [3, true],
            [4, true],
            [6, false],
            ['two', false],
            ['5', false]
        ];
    }

    /**
     * @dataProvider getTemplateEngineDebugModeDataProvider
     */
    public function testGetTemplateEngineDebugMode(mixed $configValue, bool $debugMode): void
    {
        Registry::getConfig()->setConfigParam('iDebug', $configValue);
        $utilsView = $this->getUtilsViewMock();

        $smartyContext = new SmartyContext(new ContextStub(), $utilsView);
        $this->assertSame($debugMode, $smartyContext->getTemplateEngineDebugMode());
    }

    public function showTemplateNamesDataProvider(): array
    {
        return [
            [8, false, true],
            [8, true, false],
            [5, false, false],
            [5, false, false],
        ];
    }

    /**
     * @dataProvider showTemplateNamesDataProvider
     */
    public function testShowTemplateNames(int $configValue, bool $adminMode, bool $result): void
    {
        Registry::getConfig()->setConfigParam('iDebug', $configValue);
        Registry::getConfig()->setAdminMode($adminMode);
        $utilsView = $this->getUtilsViewMock();

        $smartyContext = new SmartyContext(new ContextStub(), $utilsView);
        $this->assertSame($result, $smartyContext->showTemplateNames());
    }

    public function testGetTemplateSecurityMode(): void
    {
        Registry::getConfig()->setConfigParam('iDebug', true);

        $utilsView = $this->getUtilsViewMock();

        $smartyContext = new SmartyContext(new ContextStub(), $utilsView);
        $this->assertSame(true, $smartyContext->getTemplateSecurityMode());
    }

    public function testGetTemplateCompileCheckMode(): void
    {
        Registry::getConfig()->setConfigParam('blCheckTemplates', true);

        $utilsView = $this->getUtilsViewMock();

        $smartyContext = new SmartyContext(new ContextStub(), $utilsView);
        $this->assertSame(true, $smartyContext->getTemplateCompileCheckMode());
    }

    public function testGetTemplateCompileCheckModeInProductiveMode(): void
    {
        Registry::getConfig()->setConfigParam('blCheckTemplates', true);
        Registry::getConfig()->setConfigParam('blProductive', true);

        $utilsView = $this->getUtilsViewMock();

        $smartyContext = new SmartyContext(new ContextStub(), $utilsView);
        $this->assertFalse($smartyContext->getTemplateCompileCheckMode());
    }

    public function testGetTemplatePhpHandlingMode(): void
    {
        Registry::getConfig()->setConfigParam('iSmartyPhpHandling', 1);

        $utilsView = $this->getUtilsViewMock();

        $smartyContext = new SmartyContext(new ContextStub(), $utilsView);
        $this->assertSame(1, $smartyContext->getTemplatePhpHandlingMode());
    }

    public function testGetTemplatePath(): void
    {
        $config = $this->getConfigMock();
        $config->method('isAdmin')
            ->will($this->returnValue(false));
        $config->method('getTemplatePath')
            ->with('testTemplate', false)
            ->will($this->returnValue('templatePath'));

        $utilsView = $this->getUtilsViewMock();

        $smartyContext = new SmartyContext(new ContextStub(), $config, $utilsView);
        $this->assertSame('templatePath', $smartyContext->getTemplatePath('testTemplate'));
    }

    public function testGetTemplateCompileDirectory(): void
    {
        $config = $this->getConfigMock();
        $utilsView = $this->getUtilsViewMock();
        $utilsView->method('getSmartyDir')
        ->will($this->returnValue('testCompileDir'));

        $smartyContext = new SmartyContext(new ContextStub(), $config, $utilsView);
        $this->assertSame('testCompileDir', $smartyContext->getTemplateCompileDirectory());
    }

    public function testGetTemplateDirectories(): void
    {
        $config = $this->getConfigMock();
        $utilsView = $this->getUtilsViewMock();
        $utilsView->method('getTemplateDirs')
            ->will($this->returnValue(['testTemplateDir']));

        $smartyContext = new SmartyContext(new ContextStub(), $config, $utilsView);
        $this->assertSame(['testTemplateDir'], $smartyContext->getTemplateDirectories());
    }

    public function testGetTemplateCompileId(): void
    {
        $templateDirectories = ['testCompileDir'];
        $shopId = 1;
        $context = new ContextStub();
        $context->setCurrentShopId($shopId);
        $config = $this->getConfigMock();
        $utilsView = $this->getUtilsViewMock();
        $utilsView->method('getTemplateDirs')
            ->will($this->returnValue($templateDirectories));

        $smartyContext = new SmartyContext($context, $config, $utilsView);
        $this->assertSame(md5(reset($templateDirectories) . '__' . $shopId), $smartyContext->getTemplateCompileId());
    }

    public function testGetSourcePath(): void
    {
        $config = $this->getConfigMock();
        $utilsView = $this->getUtilsViewMock();
        $basicContext = new ContextStub();
        $basicContext->setSourcePath('testSourcePath');

        $smartyContext = new SmartyContext($basicContext, $config, $utilsView);
        $this->assertSame('testSourcePath', $smartyContext->getSourcePath());
    }

    public function testIsSmartyForContentDeactivated(): void
    {
        $config = $this->getConfigMock();
        $config->method('getConfigParam')
            ->with('deactivateSmartyForCmsContent')
            ->will($this->returnValue(1));

        $smartyContext = new SmartyContext(new ContextStub(), $config, $this->getUtilsViewMock());
        $this->assertTrue($smartyContext->isSmartyForContentDeactivated());
    }

    /**
     * @return Config
     */
    private function getConfigMock()
    {
        $configMock = $this
            ->getMockBuilder(Config::class)
            ->getMock();

        return $configMock;
    }

    /**
     * @return UtilsView
     */
    private function getUtilsViewMock()
    {
        $utilsViewMock = $this
            ->getMockBuilder(UtilsView::class)
            ->getMock();

        return $utilsViewMock;
    }
}
