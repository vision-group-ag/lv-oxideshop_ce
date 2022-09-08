<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

declare(strict_types=1);

namespace OxidEsales\EshopCommunity\Internal\Framework\Smarty\Configuration;

use OxidEsales\EshopCommunity\Internal\Transition\Adapter\ShopAdapterInterface;

class ModuleSmartyPluginsDataProvider implements SmartyPluginsDataProviderInterface
{
    public function __construct(
        private SmartyPluginsDataProviderInterface $dataProvider,
        private ShopAdapterInterface $shopAdapter
    )
    {
    }

    public function getPlugins(): array
    {
        return array_merge($this->getModuleSmartyPluginDirectories(), $this->dataProvider->getPlugins());
    }

    /**
     * @return array
     */
    private function getModuleSmartyPluginDirectories(): array
    {
        return $this->shopAdapter->getModuleSmartyPluginDirectories();
    }
}
