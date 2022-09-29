<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

declare(strict_types=1);

namespace OxidEsales\EshopCommunity\Internal\Framework\Smarty\Module\MetaData;

interface MetaDataDaoInterface
{
    public function get(string $modulePath): array;
}
