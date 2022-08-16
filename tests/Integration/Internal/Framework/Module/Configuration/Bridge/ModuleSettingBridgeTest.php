<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

declare(strict_types=1);

namespace OxidEsales\EshopCommunity\Tests\Integration\Internal\Framework\Module\Configuration\Bridge;

use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\Dao\ModuleConfigurationDaoInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Install\Service\ModuleConfigurationInstallerInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\Bridge\ModuleSettingBridgeInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Setting\SettingDaoInterface;
use OxidEsales\EshopCommunity\Tests\Integration\IntegrationTestCase;

final class ModuleSettingBridgeTest extends IntegrationTestCase
{
    public function setup(): void
    {
        parent::setUp();

        $modulePath = realpath(__DIR__ . '/../../TestData/TestModule/');

        $configurationInstaller = $this->get(ModuleConfigurationInstallerInterface::class);
        $configurationInstaller->install($modulePath);
    }

    public function testSave(): void
    {
        $bridge = $this->get(ModuleSettingBridgeInterface::class);
        $newValue = ['some new setting'];

        $bridge->save('test-setting', $newValue, 'test-module');

        $configurationDao = $this->get(ModuleConfigurationDaoInterface::class);
        $configuration = $configurationDao->get('test-module', 1);
        $this->assertSame($newValue, $configuration->getModuleSetting('test-setting')->getValue());
    }

    public function testGet(): void
    {
        $defaultModuleSettingValue = ['Preis', 'Hersteller'];
        $bridge = $this->get(ModuleSettingBridgeInterface::class);
        $this->assertSame($defaultModuleSettingValue, $bridge->get('test-setting', 'test-module'));
    }
}
