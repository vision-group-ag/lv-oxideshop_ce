<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

declare(strict_types=1);

namespace OxidEsales\EshopCommunity\Tests\Unit\Setup;

use Exception;
use oxDb;
use OxidEsales\EshopCommunity\Setup\Database;
use OxidEsales\EshopCommunity\Setup\Exception\LanguageParamsException;
use OxidEsales\EshopCommunity\Setup\Utilities;
use PDO;
use PHPUnit\Framework\MockObject\MockObject as Mock;
use StdClass;

final class DatabaseTest extends \OxidTestCase
{
    /** @var array Queries will be logged here. */
    private $loggedQueries;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loggedQueries = new StdClass();
    }

    protected function tearDown(): void
    {
        // restore database
        $dbRestore = self::_getDbRestore();
        $dbRestore->restoreDB();

        parent::tearDown();
    }

    /**
     * Testing SetupDb::execSql()
     */
    public function testExecSqlBadConnection(): void
    {
        /** @var Database|Mock $databaseMock */
        $databaseMock = $this->getMock(Database::class, ['getConnection']);
        $databaseMock->method('getConnection')->willThrowException(new Exception('Test'));

        $this->expectException('Exception');
        $this->expectExceptionMessage('Test');
        $databaseMock->execSql('select 1 + 1');
    }

    /**
     * Testing SetupDb::queryFile()
     */
    public function testQueryFileNotExistingFile(): void
    {
        $setup = $this->getMock("Setup", array("getStep", "setNextStep"));
        $setup->expects($this->once())->method("getStep")->with($this->equalTo("STEP_DB_INFO"));
        $setup->expects($this->once())->method("setNextStep");

        $language = $this->getMock("Language", array("getText"));
        $language->expects($this->once())->method("getText");

        /** @var Database|Mock $database */
        $database = $this->getMock('OxidEsales\\EshopCommunity\\Setup\\Database', array("getInstance"));
        $database
            ->method('getInstance')
            ->withConsecutive(['Setup'], ['Language'])
            ->willReturnOnConsecutiveCalls(
                $setup,
                $language
            );

        $this->expectException('Exception');
        $database->queryFile(time());
    }

    /**
     * Testing SetupDb::queryFile()
     */
    public function testQueryFile(): void
    {
        /** @var Mock $database */
        $database = $this->getMock('OxidEsales\\EshopCommunity\\Setup\\Database', array("getDatabaseVersion", "parseQuery", "execSql"));
        $database
            ->method('execSql')
            ->withConsecutive(["SET @@session.sql_mode = ''"], [1], [2], [3]);

        $database->method("getDatabaseVersion")->will($this->returnValue("5.1"));
        $database->method("parseQuery")->will($this->returnValue(array(1, 2, 3)));

        $database->queryFile(getShopBasePath() . '/config.inc.php');
    }

    /**
     * Testing SetupDb::getDatabaseVersion()
     */
    public function testGetDatabaseVersion(): void
    {
        $versionInfo = oxDb::getDb(oxDB::FETCH_MODE_ASSOC)->getAll("SHOW VARIABLES LIKE 'version'");
        $version = $versionInfo[0]["Value"];

        /** @var Mock $database */
        $database = $this->getMock('OxidEsales\\EshopCommunity\\Setup\\Database', array("getConnection"));
        $database->expects($this->once())->method("getConnection")->will($this->returnValue($this->createConnection()));
        $this->assertEquals($version, $database->getDatabaseVersion());
    }

    /**
     * Testing SetupDb::getConnection()
     */
    public function testGetConnection(): void
    {
        /** @var Mock $database */
        $database = $this->getMock('OxidEsales\\EshopCommunity\\Setup\\Database', array("openDatabase"));
        $database->expects($this->once())->method("openDatabase")->will($this->returnValue("testConnection"));

        $this->assertEquals("testConnection", $database->getConnection());
    }

    /**
     * Testing SetupDb::openDatabase().
     * Connection should not be established due to wrong access info.
     */
    public function testOpenDatabaseConnectionImpossible(): void
    {
        $parameters['dbHost'] = $this->getConfig()->getConfigParam('dbHost');
        $parameters['dbUser'] = $parameters['dbPwd'] = "wrong_password";

        $sessionMock = $this->getMockBuilder('OxidEsales\\EshopCommunity\\Setup\\Session')->disableOriginalConstructor()->getMock();

        /** @var Mock $database */
        $database = $this->getMock('OxidEsales\\EshopCommunity\\Setup\\Database', array("getInstance"));
        $database->method("getInstance")->will($this->returnValue($sessionMock));

        $this->expectException('Exception');

        $database->openDatabase($parameters);
    }

    /**
     * Testing SetupDb::openDatabase()
     */
    public function testOpenDatabaseImpossibleToSelectGivenDatabase(): void
    {
        $parameters = $this->getConnectionParameters();
        $parameters['dbName'] = "wrong_database_name";

        $this->expectException('Exception');

        $sessionMock = $this->getMockBuilder('OxidEsales\\EshopCommunity\\Setup\\Session')->disableOriginalConstructor()->getMock();
        $database = $this->getMock('OxidEsales\\EshopCommunity\\Setup\\Database', array("getInstance"));
        $database->method("getInstance")->will($this->returnValue($sessionMock));

        $database->openDatabase($parameters);
    }

    public function testOpenDatabaseWithValidCredentialsWillReturnExpected(): void
    {
        $parameters = $this->getConnectionParameters();

        $database = new Database();
        $this->assertInstanceOf(\PDO::class, $database->openDatabase($parameters));
    }

    /**
     * Testing SetupDb::createDb()
     */
    public function testCreateDb(): void
    {
        $oSetup = $this->getMock("Setup", array("setNextStep", "getStep"));
        $oSetup->expects($this->once())->method("setNextStep");
        $oSetup->expects($this->once())->method("getStep")->with($this->equalTo("STEP_DB_INFO"));

        $oLang = $this->getMock("Language", array("getText"));
        $oLang->expects($this->once())->method("getText")->with($this->equalTo("ERROR_COULD_NOT_CREATE_DB"));

        /** @var Mock $database */
        $database = $this->getMock('OxidEsales\\EshopCommunity\\Setup\\Database', array("execSql", "getInstance"));
        $database->method("execSql")->will($this->throwException(new Exception()));
        $database
            ->method('getInstance')
            ->withConsecutive(['Setup'], ['Language'])
            ->willReturnOnConsecutiveCalls(
                $oSetup,
                $oLang
            );

        $this->expectException('Exception');

        $database->createDb("");
    }

    /**
     * Testing SetupDb::saveShopSettings()
     */
    public function testSaveShopSettings(): void
    {
        $session = $this->getMock('OxidEsales\\EshopCommunity\\Setup\\Session', array("setSessionParam", "getSessionParam"), array(), '', null);

        $map = array(
            array('check_for_updates', null),
            array('country_lang', null),
        );
        if ($this->getTestConfig()->getShopEdition() == 'EE') {
            $map[] = array('send_technical_information_to_oxid', true);
        } else {
            $map[] = array('send_technical_information_to_oxid', false);
        }
        $session->method("getSessionParam")->willReturnMap($map);


        $setup = $this->getMock('OxidEsales\\EshopCommunity\\Setup\\Setup', array("getShopId"));
        $setup->method("getShopId")->willReturn(1);

        /** @var Mock $database */
        $database = $this->getMock('OxidEsales\\EshopCommunity\\Setup\\Database', array("execSql", "getInstance", "getConnection"));
        $map = array(
            array('Utilities', new Utilities()),
            array('Session', $session),
            array('Setup', $setup)
        );
        $database->method("getInstance")->willReturnMap($map);
        $database->method("getConnection")->willReturn($this->createConnection());

        $database->saveShopSettings(
            [
                'check_for_updates' => false,
                'sShopLang' => 'English'
            ]
        );
    }


    public function testSaveShopSettingsIfLanguageParamsIsNotArray(): void
    {
        // change aLanguageParams value
        $database = $this->getMock('OxidEsales\\EshopCommunity\\Setup\\Database', array("getConnection"));
        $database->method("getConnection")->willReturn($this->createConnection());
        $database->execSql("update oxconfig SET OXVARVALUE='test' where oxvarname='aLanguageParams'");

        // saveShopSettings
        $utils = $this->getMock('OxidEsales\\EshopCommunity\\Setup\\Utilities', array("generateUid"));
        $utils->method("generateUid")->willReturn("testid");

        $session = $this->getMock('OxidEsales\\EshopCommunity\\Setup\\Session', array("setSessionParam", "getSessionParam"), array(), '', null);

        $map = array(
            array('check_for_updates', null),
            array('country_lang', null),
        );
        if ($this->getTestConfig()->getShopEdition() == 'EE') {
            $map[] = array('send_technical_information_to_oxid', true);
        } else {
            $map[] = array('send_technical_information_to_oxid', false);
        }
        $session->method("getSessionParam")->willReturnMap($map);


        $setup = $this->getMock('OxidEsales\\EshopCommunity\\Setup\\Setup', array("getShopId"));
        $setup->method("getShopId")->willReturn(1);

        $database = $this->getMock('OxidEsales\\EshopCommunity\\Setup\\Database', array("execSql", "getInstance", "getConnection"));
        $map = array(
            array('Utilities', new Utilities()),
            array('Session', $session),
            array('Setup', $setup)
        );
        $database->method("getInstance")->willReturnMap($map);
        $database->method("getConnection")->willReturn($this->createConnection());

        $this->expectException(LanguageParamsException::class);
        $database->saveShopSettings(
            [
                'check_for_updates' => false,
                'sShopLang'         => 'English'
            ]
        );
    }

    /**
     * Testing SetupDb::writeAdminLoginData()
     */
    public function testWriteAdminLoginData(): void
    {
        $loginName = 'testLoginName';
        $password = 'testPassword';
        $uniqueId = 'testSalt';
        $baseShopId = 1;

        $setup = $this->getMock("Setup", array("getShopId"));
        $setup->expects($this->once())->method("getShopId")->will($this->returnValue($baseShopId));

        $utils = $this->getMock("Utilities", array("generateUID"));
        $utils->expects($this->once())->method("generateUID")->will($this->returnValue($uniqueId));

        /** @var Mock $database */
        $database = $this->getMock('OxidEsales\\EshopCommunity\\Setup\\Database', array("getInstance", "execSql"));
        $database
            ->method('getInstance')
            ->withConsecutive(['Setup'], ['Utilities'])
            ->willReturnOnConsecutiveCalls(
                $setup,
                $utils
            );

        $passSalt = hash('sha512', $password . $uniqueId);
        $query = "insert into oxuser (oxid, oxusername, oxpassword, oxpasssalt, oxrights, oxshopid)
                             values(:oxid, :oxusername, :oxpassword, :oxpasssalt, 'malladmin', :oxshopid)";

        $database->method("execSql")->with($this->equalTo
        (
            $query,
            [
                "oxid"       => $uniqueId,
                "oxusername" => $loginName,
                "oxpassword" => $password,
                "oxpasssalt" => $passSalt,
                "oxshopid"   => $baseShopId
            ]
        ));

        $database->writeAdminLoginData($loginName, $password);
    }

    protected function createConnection(): PDO
    {
        $config = $this->getConfig();
        $dsn = sprintf(
            'mysql:dbname=%s;host=%s;port=%s',
            $config->getConfigParam('dbName'),
            $config->getConfigParam('dbHost'),
            $config->getConfigParam('dbPort')
        );        return new PDO(
            $dsn,
            $config->getConfigParam('dbUser'),
            $config->getConfigParam('dbPwd'),
            [
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
            ]
        );
    }

    /**
     * Logs exec queries instead of executing them.
     * Prepared statements will be executed as usual and will not be logged.
     *
     * @return PDO
     */
    protected function createConnectionMock(): PDO
    {
        $config = $this->getConfig();
        $dsn = sprintf('mysql:host=%s;port=%s', $config->getConfigParam('dbHost'), $config->getConfigParam('dbPort'));
        $pdoMock = $this->getMock('PDO', array('exec'), array(
            $dsn,
            $config->getConfigParam('dbUser'),
            $config->getConfigParam('dbPwd')));

        $loggedQueries = $this->loggedQueries;
        $pdoMock->method('exec')
            ->willReturnCallback(static function ($query) use ($loggedQueries) {
                $loggedQueries->queries[] = $query;
            });

        return $pdoMock;
    }

    /**
     * Returns logged queries when mocked connection is used.
     *
     * @return array
     */
    protected function getLoggedQueries(): array
    {
        return $this->loggedQueries->queries;
    }

    private function getConnectionParameters(): array
    {
        $parameters = [];
        $config = $this->getConfig();
        $parameters['dbHost'] = $config->getConfigParam('dbHost');
        $parameters['dbPort'] = $config->getConfigParam('dbPort') ?? 3306;
        $parameters['dbUser'] = $config->getConfigParam('dbUser');
        $parameters['dbPwd'] = $config->getConfigParam('dbPwd');
        $parameters['dbName'] = $config->getConfigParam('dbName');
        return $parameters;
    }
}
