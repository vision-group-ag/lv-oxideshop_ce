<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

declare(strict_types=1);

namespace OxidEsales\EshopCommunity\Tests\Integration\Setup;

use OxidEsales\EshopCommunity\Core\SystemRequirements;
use OxidEsales\EshopCommunity\Internal\Container\ContainerFactory;
use OxidEsales\EshopCommunity\Internal\Framework\SystemRequirements\Bridge\SystemSecurityCheckerBridge;
use OxidEsales\EshopCommunity\Internal\Framework\SystemRequirements\Bridge\SystemSecurityCheckerBridgeInterface;
use OxidEsales\EshopCommunity\Setup\{Controller, Database, Exception\SetupControllerExitException, Language, Session};
use OxidEsales\EshopCommunity\Tests\Integration\Internal\ContainerTrait;
use OxidEsales\EshopCommunity\Tests\Integration\Internal\TestContainerFactory;
use OxidEsales\TestingLibrary\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

require_once OX_BASE_PATH . 'Setup' . DIRECTORY_SEPARATOR . 'functions.php';

class TestSetupController extends Controller
{
    public function setInstance($key, $object)
    {
        $storageKey = $this->getClassKey($key);
        static::$_aInstances[$storageKey] = $object;
    }

    public function getClassKey($instanceName)
    {
        return parent::getClass($instanceName);
    }
}

/**
 * SetupCoreTest tests
 */
class ControllerTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->unsetPost();
    }

    protected function tearDown(): void
    {
        $this->unsetPost();

        parent::tearDown();
    }

    /**
     * Test case that no database settings are supplied.
     */
    public function testDbConnectNoDataSupplied()
    {
        $controller = $this->getTestController();

        // NOTE: OxidTestCase::expectException is not what we need here, try/catch is better suited
        try {
            $controller->dbConnect();
        } catch (SetupControllerExitException $exception) {
            $view = $controller->getView();

            $this->assertStringContainsString('ERROR: Please fill in all needed fields!', $view->getMessages()[0]);
            $this->assertEquals('400', $view->getNextSetupStep()); //STEP_DB_INFO
            $this->assertNull($view->getViewParam('aDB'));
        }
    }

    public function testDbConnectWithEmptyDbPortShowsError(): void
    {
        $databaseCredentials = $this->getDatabaseCredentials();
        $databaseCredentials['dbPort'] = '';
        $this->setPostDatabase($databaseCredentials);

        $controller = $this->getTestController();
        $this->expectException(SetupControllerExitException::class);
        $controller->dbConnect();
        $view = $controller->getView();

        $this->assertStringContainsString('ERROR: Please fill in all needed fields!', $view->getMessages()[0]);
    }

    /**
     * Test case that all is well but database does not yet exist.
     * We get an Exception from Database::opeDatabase that is caught in Controller::dbConnect and
     * then database is created in Controller::dbConnect catch block.
     */
    public function testDbConnectAllIsWellButDatabaseNotYetCreated()
    {
        $this->setPostDatabase($this->getDatabaseCredentials());

        $controller = $this->getTestController(true);

        // NOTE: OxidTestCase::expectException is not what we need here, try/catch is better suited
        try {
            $controller->dbConnect();
        } catch (SetupControllerExitException $exception) {
            $view = $controller->getView();

            $this->assertStringContainsString('ERROR: Database not available and also cannot be created!', $view->getMessages()[0]);
            $this->assertEquals('400', $view->getNextSetupStep()); //STEP_DB_INFO
            $this->assertNotNull($view->getViewParam('aDB'));

            $this->assertNull($view->getViewParam('blCreated'));
            $this->assertEquals(1, $view->getViewParam('blCreated'));
        }
    }

    public function testDbConnectAllIsWellAndDatabaseAlreadyExists()
    {
        $databaseName = $this->getConfig()->getConfigParam('dbName');
        $this->setPostDatabase($this->getDatabaseCredentials($databaseName));

        $controller = $this->getTestController();

        // NOTE: OxidTestCase::expectException is not what we need here, try/catch is better suited
        try {
            $controller->dbConnect();
        } catch (SetupControllerExitException $exception) {
            $view = $controller->getView();

            $this->assertStringContainsString('ERROR: Seems there is already OXID eShop installed in database', $view->getMessages()[0]);
            $this->assertStringContainsString('If you want to overwrite all existing data and install anyway click', $view->getMessages()[1]);
            $this->assertStringContainsString('ow=1', $view->getMessages()[1]);
            $this->assertNull($view->getViewParam('blCreated'));
            $this->assertNotNull($view->getViewParam('aDB'));
        }
    }

    public function testSystemReqWithCryptographicallySufficientConfigurationWillSetExpectedViewData(): void
    {
        $moduleId = 'cryptographically_sufficient_configuration';
        $controller = $this->getTestController();

        $controller->systemReq();

        $view = $controller->getView();
        $this->assertNotEmpty($view);
        $serverConfigurations = $view->getViewParam('aGroupModuleInfo')['Server configuration'];
        $key = array_search(
            $moduleId,
            array_column($serverConfigurations, 'module'),
            true
        );
        $this->assertNotFalse($key);
        $this->assertEquals(SystemRequirements::MODULE_STATUS_OK, $serverConfigurations[$key]['state']);
        $this->assertNotEmpty($serverConfigurations[$key]['modulename']);
    }

    /**
     * @param bool   $expectDbCreation Expect Database::createDb call.
     * @param array  $sessionData      Store this data in the session.
     *
     * @return TestSetupController
     */
    protected function getTestController($expectDbCreation = false)
    {
        $sessionMock = $this->getMock(Session::class, ['getSid'], [], '', false);

        $languageMock = $this->getMock(Language::class, ['getInstance', 'getLanguage'], [], '', false);
        $languageMock->expects($this->any())->method('getLanguage')->will($this->returnValue('en'));

        $databaseMock = $this->getMock(Database::class, ['createDb', 'testCreateView']);
        $exception = new \Exception('bail out before we do harm while testing');
        $databaseMock->expects($this->any())->method('testCreateView')->will($this->throwException($exception));

        if ($expectDbCreation) {
            //we do not really want to create a new database while testing
            $databaseMock->expects($this->once())->method('createDb');
        } else {
            $databaseMock->expects($this->never())->method('createDb');
        }

        $controller = oxNew(TestSetupController::class);
        $controller->setInstance('Session', $sessionMock);
        $controller->setInstance('Language', $languageMock);
        $controller->setInstance('Database', $databaseMock);

        $this->assertEmpty($controller->getView()->getMessages());

        return $controller;
    }

    /**
     * @param array $databaseSettings The settings we want to write into the POST for the database.
     */
    protected function setPostDatabase($databaseSettings)
    {
        $this->setPostData(['aDB' => $databaseSettings]);
    }

    /**
     * Test helper.
     *
     * @param array $parameters
     */
    protected function setPostData($parameters)
    {
        foreach ($parameters as $key => $value) {
            $_POST[$key] = $value;
        }
    }

    /**
     * @param string $databaseName
     *
     * @return array
     */
    protected function getDatabaseCredentials($databaseName = '')
    {
        if (!$databaseName) {
            $databaseName = time();
        }

        $myConfig = $this->getConfig();
        $parameters['dbHost'] = $myConfig->getConfigParam('dbHost');
        $parameters['dbPort'] = $myConfig->getConfigParam('dbPort') ? $myConfig->getConfigParam('dbPort') : 3306;
        $parameters['dbUser'] = $myConfig->getConfigParam('dbUser');
        $parameters['dbPwd'] = $myConfig->getConfigParam('dbPwd');
        $parameters['dbName'] = $databaseName;

        return $parameters;
    }

    protected function unsetPost()
    {
        $_POST = [];
    }
}
