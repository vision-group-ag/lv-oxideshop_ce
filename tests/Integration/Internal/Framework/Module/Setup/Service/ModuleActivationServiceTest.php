<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

declare(strict_types=1);

namespace OxidEsales\EshopCommunity\Tests\Integration\Internal\Framework\Module\Setup\Service;

use OxidEsales\EshopCommunity\Internal\Framework\Smarty\Module\TemplateExtension\TemplateBlockExtensionDaoInterface;
use OxidEsales\EshopCommunity\Internal\Transition\Adapter\ShopAdapterInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\Dao\ModuleConfigurationDaoInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\Dao\ShopConfigurationDaoInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\DataObject\ClassExtensionsChain;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\DataObject\ModuleConfiguration;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\DataObject\ModuleConfiguration\Template;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\DataObject\ShopConfiguration;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Path\ModulePathResolver;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Path\ModulePathResolverInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Setting\Setting;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Setup\Service\ModuleActivationServiceInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Module\State\ModuleStateServiceInterface;
use OxidEsales\EshopCommunity\Tests\Integration\Internal\Framework\Module\TestData\TestModule\SomeModuleService;
use OxidEsales\EshopCommunity\Tests\Integration\Internal\Module\TestData\TestModule\TestEvent;
use OxidEsales\EshopCommunity\Tests\TestContainerFactory;
use Psr\Container\ContainerInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\DataObject\ModuleConfiguration\ClassExtension;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\DataObject\ModuleConfiguration\Controller;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\DataObject\ModuleConfiguration\SmartyPluginDirectory;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use OxidEsales\EshopCommunity\Tests\Integration\IntegrationTestCase;

/**
 * @internal
 */
class ModuleActivationServiceTest extends IntegrationTestCase
{
    /**
     * @var ContainerInterface
     */
    private $container;
    private $shopId = 1;
    private $testModuleId = 'testModuleId';
    private $testContainerFactory = null;

    public function setup(): void
    {
        $this->container = $this->setupAndConfigureContainer();

        parent::setUp();
    }

    public function testActivation()
    {
        $this->persistModuleConfiguration($this->getTestModuleConfiguration());

        $moduleStateService = $this->container->get(ModuleStateServiceInterface::class);
        $moduleActivationService = $this->container->get(ModuleActivationServiceInterface::class);

        $moduleActivationService->activate($this->testModuleId, $this->shopId);

        $this->assertTrue($moduleStateService->isActive($this->testModuleId, $this->shopId));

        $moduleActivationService->deactivate($this->testModuleId, $this->shopId);

        $this->assertFalse($moduleStateService->isActive($this->testModuleId, $this->shopId));
    }

    public function testSetActivatedInModuleConfiguration()
    {
        $this->persistModuleConfiguration($this->getTestModuleConfiguration());

        $moduleConfigurationDao = $this->container->get(ModuleConfigurationDaoInterface::class);
        $moduleActivationService = $this->container->get(ModuleActivationServiceInterface::class);

        $moduleActivationService->activate($this->testModuleId, $this->shopId);
        $moduleConfiguration = $moduleConfigurationDao->get($this->testModuleId, $this->shopId);

        $this->assertTrue($moduleConfiguration->isActivated());

        $moduleActivationService->deactivate($this->testModuleId, $this->shopId);
        $moduleConfiguration = $moduleConfigurationDao->get($this->testModuleId, $this->shopId);

        $this->assertFalse($moduleConfiguration->isActivated());
    }

    public function testActivationOfModuleServices()
    {
        $moduleConfiguration = $this->getTestModuleConfiguration();
        $this->persistModuleConfiguration($moduleConfiguration);

        $moduleActivationService = $this->container->get(ModuleActivationServiceInterface::class);
        $moduleActivationService->activate($this->testModuleId, $this->shopId);

        $this->assertInstanceOf(
            SomeModuleService::class,
            $this->setupAndConfigureContainer()->get(SomeModuleService::class)
        );
    }

    /**
     * This checks the deactivation of the module by asserting that the test event
     * is not handled any more.
     *
     * As a side effect this tests also that the deactivation works in such a way
     * that shop aware services do not throw exceptions when the module is not
     * active any more.
     */
    public function testDeActivationOfModuleServices()
    {
        $moduleConfiguration = $this->getTestModuleConfiguration();
        $this->persistModuleConfiguration($moduleConfiguration);

        /** @var ModuleActivationServiceInterface $moduleActivationService */
        $moduleActivationService = $this->container->get(ModuleActivationServiceInterface::class);
        $moduleActivationService->activate($this->testModuleId, $this->shopId);

        // We need a new container to assert that the even subscriber now is active
        $this->container = $this->setupAndConfigureContainer();
        /** @var EventDispatcher $eventDispatcher */
        $eventDispatcher = $this->container->get(EventDispatcherInterface::class);
        /** @var TestEvent $event */
        $event = $eventDispatcher->dispatch(
            new TestEvent()
        );
        $this->assertTrue($event->isHandled());

        $moduleActivationService = $this->container->get(ModuleActivationServiceInterface::class);
        $moduleActivationService->deactivate($this->testModuleId, $this->shopId);

        // Again we need a new container to assert that our changes worked
        $this->container = $this->setupAndConfigureContainer();
        $eventDispatcher = $this->container->get(EventDispatcherInterface::class);
        $event = $eventDispatcher->dispatch(
            new TestEvent()
        );
        $this->assertFalse($event->isHandled());
    }

    public function testActivationWillNotAffectPersistedConfigs(): void
    {
        $author = 'abc';
        $url = 'xyz';
        /** @var ModuleActivationServiceInterface $moduleActivationService */
        $moduleActivationService = $this->container->get(ModuleActivationServiceInterface::class);
        $moduleConfiguration = $this->getTestModuleConfiguration();
        $moduleConfiguration->setAuthor($author);
        $moduleConfiguration->setUrl($url);
        $this->persistModuleConfiguration($moduleConfiguration);

        $moduleActivationService->activate($this->testModuleId, $this->shopId);

        $this->assertSame($author, $moduleConfiguration->getAuthor());
        $this->assertSame($url, $moduleConfiguration->getUrl());

        $moduleActivationService->deactivate($this->testModuleId, $this->shopId);

        $this->assertSame($author, $moduleConfiguration->getAuthor());
        $this->assertSame($url, $moduleConfiguration->getUrl());
    }

    /**
     * @return ShopAdapterInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private function getModulePathResolverMock()
    {
        $modulePathResolverMock = $this
            ->getMockBuilder(ModulePathResolverInterface::class)
            ->getMock();

        $modulePathResolverMock
            ->method('getFullModulePathFromConfiguration')
            ->willReturn(__DIR__ . '/../../TestData/TestModule');

        return $modulePathResolverMock;
    }

    private function getTestModuleConfiguration(): ModuleConfiguration
    {
        $moduleConfiguration = new ModuleConfiguration();
        $moduleConfiguration->setId($this->testModuleId);
        $moduleConfiguration->setModuleSource('test');

        $setting = new Setting();
        $setting
            ->setName('test')
            ->setValue([1, 2])
            ->setType('aarr')
            ->setGroupName('group')
            ->setPositionInGroup(7)
            ->setConstraints([1, 2]);

        $moduleConfiguration->addModuleSetting($setting);

        $moduleConfiguration
            ->addController(
                new Controller(
                    'originalClassNamespace',
                    'moduleClassNamespace'
                )
            )->addController(
                new Controller(
                    'otherOriginalClassNamespace',
                    'moduleClassNamespace'
                )
            )
            ->addTemplate(new Template('originalTemplate', 'moduleTemplate'))
            ->addTemplate(new Template('otherOriginalTemplate', 'moduleTemplate'))
            ->addSmartyPluginDirectory(
                new SmartyPluginDirectory(
                    'SmartyPlugins/directory1'
                )
            )->addSmartyPluginDirectory(
                new SmartyPluginDirectory(
                    'SmartyPlugins/directory2'
                )
            )
            ->addClassExtension(
                new ClassExtension(
                    'originalClassNamespace',
                    'moduleClassNamespace'
                )
            )
            ->addClassExtension(
                new ClassExtension(
                    'otherOriginalClassNamespace',
                    'moduleClassNamespace'
                )
            );

        $setting = new Setting();
        $setting
            ->setName('grid')
            ->setValue('row')
            ->setType('str')
            ->setGroupName('frontend');
        $moduleConfiguration->addModuleSetting($setting);

        $setting = new Setting();
        $setting
            ->setName('array')
            ->setValue(['1', '2'])
            ->setType('arr')
            ->setGroupName('frontend');
        $moduleConfiguration->addModuleSetting($setting);

        return $moduleConfiguration;
    }

    /**
     * @param ModuleConfiguration $moduleConfiguration
     */
    private function persistModuleConfiguration(ModuleConfiguration $moduleConfiguration)
    {
        $chain = new ClassExtensionsChain();
        $chain->setChain([
            'originalClassNamespace' => ['moduleClassNamespace'],
        ]);

        $shopConfiguration = new ShopConfiguration();
        $shopConfiguration->setClassExtensionsChain($chain);
        $shopConfiguration->addModuleConfiguration($moduleConfiguration);

        $shopConfigurationDao = $this->container->get(ShopConfigurationDaoInterface::class);
        $shopConfigurationDao->save($shopConfiguration, $this->shopId);
    }

    /**
     * We need to replace services in the container with a mock
     *
     * @return \Symfony\Component\DependencyInjection\ContainerBuilder
     */
    private function setupAndConfigureContainer()
    {
        if ($this->testContainerFactory === null) {
            $this->testContainerFactory = new TestContainerFactory();
        }
        $container = $this->testContainerFactory->create();

        $container->set(ModulePathResolverInterface::class, $this->getModulePathResolverMock());
        $container->autowire(ModulePathResolverInterface::class, ModulePathResolver::class);

        $container->compile();

        return $container;
    }
}
