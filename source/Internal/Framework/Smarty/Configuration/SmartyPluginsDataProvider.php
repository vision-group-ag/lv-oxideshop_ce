<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

declare(strict_types=1);

namespace OxidEsales\EshopCommunity\Internal\Framework\Smarty\Configuration;

use OxidEsales\EshopCommunity\Internal\Transition\Utility\BasicContextInterface;

class SmartyPluginsDataProvider implements SmartyPluginsDataProviderInterface
{
    public function __construct(private BasicContextInterface $context)
    {
    }

    public function getPlugins(): array
    {
        return [$this->getShopSmartyPluginDirectory()];
    }

    private function getShopSmartyPluginDirectory(): string
    {
        return $this->getEditionsRootPaths() . DIRECTORY_SEPARATOR . 'Internal/Framework/Smarty/Plugin';
    }

    private function getEditionsRootPaths(): string
    {
        return $this->context->getCommunityEditionSourcePath();
    }
}
