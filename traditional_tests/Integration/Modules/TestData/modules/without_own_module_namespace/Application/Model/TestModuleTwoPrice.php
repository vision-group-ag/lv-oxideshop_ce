<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidEsales\EshopCommunity\Tests\Integration\Modules\TestData\modules\without_own_module_namespace\Application\Model;

class TestModuleTwoPrice extends testmoduletwoprice_parent
{

    /**
     * Double the original price
     *
     * @return double
     */
    public function getPrice()
    {
        $return = parent::getPrice();
        return 3 * $return;
    }
}
