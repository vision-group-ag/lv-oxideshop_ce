<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidEsales\EshopCommunity\Application\Controller\Admin;

use oxDb;
use oxField;

/**
 * Class manages delivery countries
 */
class DeliveryMainAjax extends \OxidEsales\Eshop\Application\Controller\Admin\ListComponentAjax
{
    /**
     * Columns array
     *
     * @var array
     */
    protected $_aColumns = ['container1' => [ // field , table,         visible, multilanguage, ident
        ['oxtitle', 'oxcountry', 1, 1, 0],
        ['oxisoalpha2', 'oxcountry', 1, 0, 0],
        ['oxisoalpha3', 'oxcountry', 0, 0, 0],
        ['oxunnum3', 'oxcountry', 0, 0, 0],
        ['oxid', 'oxcountry', 0, 0, 1]
    ],
                                 'container2' => [
                                     ['oxtitle', 'oxcountry', 1, 1, 0],
                                     ['oxisoalpha2', 'oxcountry', 1, 0, 0],
                                     ['oxisoalpha3', 'oxcountry', 0, 0, 0],
                                     ['oxunnum3', 'oxcountry', 0, 0, 0],
                                     ['oxid', 'oxobject2delivery', 0, 0, 1]
                                 ]
    ];

    /**
     * Returns SQL query for data to fetc
     *
     * @return string
     */
    protected function _getQuery()
    {
        $sCountryTable = $this->_getViewName('oxcountry');
        $oDb = \OxidEsales\Eshop\Core\DatabaseProvider::getDb();
        $sId = \OxidEsales\Eshop\Core\Registry::getConfig()->getRequestParameter('oxid');
        $sSynchId = \OxidEsales\Eshop\Core\Registry::getConfig()->getRequestParameter('synchoxid');

        // category selected or not ?
        if (!$sId) {
            $sQAdd = " from {$sCountryTable} where {$sCountryTable}.oxactive = '1' ";
        } else {
            $sQAdd = " from oxobject2delivery left join {$sCountryTable} " .
                     "on {$sCountryTable}.oxid=oxobject2delivery.oxobjectid " .
                     " where oxobject2delivery.oxdeliveryid = " . $oDb->quote($sId) .
                     " and oxobject2delivery.oxtype = 'oxcountry' ";
        }

        if ($sSynchId && $sSynchId != $sId) {
            $sQAdd .= " and {$sCountryTable}.oxid not in ( select {$sCountryTable}.oxid " .
                      "from oxobject2delivery left join {$sCountryTable} " .
                      "on {$sCountryTable}.oxid=oxobject2delivery.oxobjectid " .
                      " where oxobject2delivery.oxdeliveryid = " . $oDb->quote($sSynchId) .
                      " and oxobject2delivery.oxtype = 'oxcountry' ) ";
        }

        return $sQAdd;
    }

    /**
     * Removes chosen countries from delivery list
     */
    public function removeCountryFromDel()
    {
        $aChosenCntr = $this->_getActionIds('oxobject2delivery.oxid');
        if (\OxidEsales\Eshop\Core\Registry::getConfig()->getRequestParameter('all')) {
            $sQ = $this->_addFilter("delete oxobject2delivery.* " . $this->_getQuery());
            \OxidEsales\Eshop\Core\DatabaseProvider::getDb()->Execute($sQ);
        } elseif (is_array($aChosenCntr)) {
            $sQ = "delete from oxobject2delivery where oxobject2delivery.oxid in (" . implode(", ", \OxidEsales\Eshop\Core\DatabaseProvider::getDb()->quoteArray($aChosenCntr)) . ") ";
            \OxidEsales\Eshop\Core\DatabaseProvider::getDb()->Execute($sQ);
        }
    }

    /**
     * Adds chosen countries to delivery list
     */
    public function addCountryToDel()
    {
        $aChosenCntr = $this->_getActionIds('oxcountry.oxid');
        $soxId = \OxidEsales\Eshop\Core\Registry::getConfig()->getRequestParameter('synchoxid');

        // adding
        if (\OxidEsales\Eshop\Core\Registry::getConfig()->getRequestParameter('all')) {
            $sCountryTable = $this->_getViewName('oxcountry');
            $aChosenCntr = $this->_getAll($this->_addFilter("select $sCountryTable.oxid " . $this->_getQuery()));
        }

        if ($soxId && $soxId != "-1" && is_array($aChosenCntr)) {
            foreach ($aChosenCntr as $sChosenCntr) {
                $oObject2Delivery = oxNew(\OxidEsales\Eshop\Core\Model\BaseModel::class);
                $oObject2Delivery->init('oxobject2delivery');
                $oObject2Delivery->oxobject2delivery__oxdeliveryid = new \OxidEsales\Eshop\Core\Field($soxId);
                $oObject2Delivery->oxobject2delivery__oxobjectid = new \OxidEsales\Eshop\Core\Field($sChosenCntr);
                $oObject2Delivery->oxobject2delivery__oxtype = new \OxidEsales\Eshop\Core\Field('oxcountry');
                $oObject2Delivery->save();
            }
        }
    }
}
