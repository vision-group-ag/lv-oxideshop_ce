<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

declare(strict_types=1);

namespace OxidEsales\EshopCommunity\Tests\Unit\Internal\Transition\Smarty\Configuration;

use OxidEsales\EshopCommunity\Internal\Framework\Smarty\Configuration\ModuleSmartyPluginsDataProvider;
use OxidEsales\EshopCommunity\Internal\Framework\Smarty\Configuration\SmartyPluginsDataProvider;
use OxidEsales\EshopCommunity\Internal\Transition\Adapter\ShopAdapterInterface;
use OxidEsales\EshopCommunity\Internal\Transition\Utility\BasicContextInterface;

class ModuleSmartyPluginsDataProviderTest extends \PHPUnit\Framework\TestCase
{
    public function testGetPlugins()
    {
        $contextMock = $this->getContextMock();

        $dataProvider = new ModuleSmartyPluginsDataProvider(
            new SmartyPluginsDataProvider($contextMock),
            $this->getShopAdapterMock()
        );

        $settings = ['testModuleDir', 'testShopPath/Core/Smarty/Plugin'];

        $this->assertEquals($settings, $dataProvider->getPlugins());
    }

    private function getContextMock(): BasicContextInterface
    {
        $contextMock = $this
            ->getMockBuilder(BasicContextInterface::class)
            ->getMock();

        $contextMock
            ->method('getCommunityEditionSourcePath')
            ->willReturn('testShopPath');

        return $contextMock;
    }

    private function getShopAdapterMock(): ShopAdapterInterface
    {
        $shopAdapterMock = $this
            ->getMockBuilder(ShopAdapterInterface::class)
            ->getMock();

        $shopAdapterMock
            ->method('getModuleSmartyPluginDirectories')
            ->willReturn(['testModuleDir']);

        return $shopAdapterMock;
    }
}
