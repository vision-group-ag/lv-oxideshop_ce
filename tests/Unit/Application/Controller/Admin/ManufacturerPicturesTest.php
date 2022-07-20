<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidEsales\EshopCommunity\Tests\Unit\Application\Controller\Admin;

use OxidEsales\Eshop\Core\Config;
use OxidEsales\EshopCommunity\Application\Model\Manufacturer;
use OxidEsales\EshopCommunity\Core\Exception\ExceptionToDisplay;
use \oxField;
use \oxDb;
use OxidEsales\EshopCommunity\Core\Registry;
use \oxRegistry;
use \oxTestModules;

/**
 * Tests for Manufacturer_Pictures class
 */
class ManufacturerPicturesTest extends \OxidTestCase
{
    /**
     * Initialize the fixture.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->_oManufacturer = oxNew('oxManufacturer');
        $this->_oManufacturer->setId("testManId");
        $this->_oManufacturer->save();
    }

    /**
     * Tear down the fixture.
     */
    protected function tearDown(): void
    {
        $this->cleanUpTable('oxmanufacturers');

        parent::tearDown();
    }

    /**
     * Manufacturer_Pictures::save() test case
     */
    public function testSaveAdditionalTest()
    {
        oxTestModules::addFunction('oxmanufacturer', 'save', '{ return true; }');
        $this->getConfig()->setConfigParam('iPicCount', 0);

        $oView = $this->getMock(\OxidEsales\Eshop\Application\Controller\Admin\ManufacturerPictures::class, array("resetContentCache"));
        $oView->expects($this->once())->method('resetContentCache');

        $iCnt = 7;
        $this->getConfig()->setConfigParam('iPicCount', $iCnt);

        $oView->save();
    }

    /**
     * Manufacturer_Pictures::Render() test case
     */
    public function testRender()
    {
        $this->setRequestParameter("oxid", oxDb::getDb()->getOne("select oxid from oxmanufacturers"));

        // testing..
        $oView = oxNew('Manufacturer_Pictures');
        $sTplName = $oView->render();

        // testing view data
        $aViewData = $oView->getViewData();
        $this->assertTrue($aViewData["edit"] instanceof Manufacturer);

        $this->assertEquals('manufacturer_pictures', $sTplName);
    }

    /**
     * Manufacturer_Pictures::deletePicture() test case - deleting thumbnail
     * 
     * @dataProvider setupSqlFilesProvider
     */
    public function testDeletePicture($picIndex)
    {
        $this->setRequestParameter("oxid", "testManId");
        $this->setRequestParameter("masterPicIndex", $picIndex);
        $oDb = oxDb::getDb(oxDB::FETCH_MODE_ASSOC);

        $oArtPic = oxNew('manufacturer_pictures');

        $this->_oManufacturer->oxmanufacturers__oxpic . $picIndex = new oxField("testThumb" . $picIndex . "jpg");
        $this->_oManufacturer->save();

        $oArtPic->deletePicture();

        $this->assertEquals("", $oDb->getOne("select oxthumb from oxmanufacturers where oxid='testManId' "));
    }

    public function setupSqlFilesProvider()
    {
        return [
            [1],
            [2],
            [3]
        ];
    }

    /**
     * Manufacturer_Pictures::save() - in demo shop mode
     *
     * @return null
     */
    public function testSave_demoShopMode()
    {
        $oConfig = $this->getMock(\OxidEsales\Eshop\Core\Config::class, array("isDemoShop"));
        $oConfig->expects($this->once())->method('isDemoShop')->will($this->returnValue(true));

        oxRegistry::getSession()->deleteVariable("Errors");

        $oArtPic = $this->getProxyClass("Manufacturer_Pictures");
        Registry::set(Config::class, $oConfig);
        $oArtPic->save();

        $aEx = oxRegistry::getSession()->getVariable("Errors");
        $oEx = unserialize($aEx["default"][0]);

        $this->assertTrue($oEx instanceof ExceptionToDisplay);
    }

    /**
     * Manufacturer_Pictures::deletePicture() - in demo shop mode
     *
     * @return null
     */
    public function testDeletePicture_demoShopMode()
    {
        $oConfig = $this->getMock(\OxidEsales\Eshop\Core\Config::class, array("isDemoShop"));
        $oConfig->expects($this->once())->method('isDemoShop')->will($this->returnValue(true));

        oxRegistry::getSession()->deleteVariable("Errors");

        $oArtPic = $this->getProxyClass("Manufacturer_Pictures");
        Registry::set(Config::class, $oConfig);
        $oArtPic->deletePicture();

        $aEx = oxRegistry::getSession()->getVariable("Errors");
        $oEx = unserialize($aEx["default"][0]);

        $this->assertTrue($oEx instanceof ExceptionToDisplay);
    }

    /**
     * test for bug#0002041: editing inherited product pictures in subshop changes default shop for product
     */
    public function testSubshopStaysSame()
    {
        $oManufacturer = $this->getMock(\OxidEsales\Eshop\Application\Model\Manufacturer::class, array('load', 'save', 'assign'));
        $oManufacturer->expects($this->once())->method('load')->with($this->equalTo('asdasdasd'))->will($this->returnValue(true));
        $oManufacturer->expects($this->once())->method('assign')->with($this->equalTo(array('s' => 'test')))->will($this->returnValue(null));
        $oManufacturer->expects($this->once())->method('save')->will($this->returnValue(null));

        oxTestModules::addModuleObject('oxmanufacturer', $oManufacturer);

        $this->setRequestParameter('oxid', 'asdasdasd');
        $this->setRequestParameter('editval', array('s' => 'test'));
        $oArtPic = $this->getProxyClass("Manufacturer_Pictures");
        $oArtPic->save();
    }
}
