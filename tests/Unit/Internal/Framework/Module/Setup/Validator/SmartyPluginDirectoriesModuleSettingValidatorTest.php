<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

declare(strict_types=1);

namespace OxidEsales\EshopCommunity\Tests\Unit\Internal\Framework\Module\Setup\Validator;

use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\DataObject\ModuleConfiguration;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Path\ModulePathResolverInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Setup\Validator\SmartyPluginDirectoriesValidator;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use OxidEsales\EshopCommunity\Internal\Framework\FileSystem\DirectoryNotExistentException;
use OxidEsales\EshopCommunity\Internal\Framework\FileSystem\DirectoryNotReadableException;
use PHPUnit\Framework\TestCase;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\DataObject\ModuleConfiguration\SmartyPluginDirectory;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Setup\Exception\ModuleSettingNotValidException;

class SmartyPluginDirectoriesModuleSettingValidatorTest extends TestCase
{

    /** @var vfsStreamDirectory */
    private $vfsStreamDirectory = null;

    /** @var ModulePathResolverInterface */
    private $modulePathResolver = null;

    public function setup(): void
    {
        parent::setUp();
        $this->modulePathResolver = $this->getMockBuilder(ModulePathResolverInterface::class)->getMock();
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testValidate()
    {
        $this->createModuleStructure();

        $this->modulePathResolver
            ->method('getFullModulePathFromConfiguration')
            ->willReturn(vfsStream::url('root/modules/smartyTestModule'));

        $validator = new SmartyPluginDirectoriesValidator($this->modulePathResolver);

        $moduleConfiguration = new ModuleConfiguration();
        $moduleConfiguration->addSmartyPluginDirectory(new SmartyPluginDirectory('smarty'));
        $moduleConfiguration->setId("smartyTestModule");

        $validator->validate($moduleConfiguration, 1);
    }

    public function testValidateThrowsExceptionIfNotExistingDirectoryConfigured(): void
    {
        $this->expectException(DirectoryNotExistentException::class);
        $this->createModuleStructure();

        $this->modulePathResolver
            ->method('getFullModulePathFromConfiguration')
            ->willReturn(vfsStream::url('root/modules/smartyTestModule'));

        $validator = new SmartyPluginDirectoriesValidator($this->modulePathResolver);

        $moduleConfiguration = new ModuleConfiguration();
        $moduleConfiguration->addSmartyPluginDirectory(new SmartyPluginDirectory('notExistingDirectory'));
        $moduleConfiguration->setId("smartyTestModule");

        $this->expectException(DirectoryNotExistentException::class);
        $validator->validate($moduleConfiguration, 1);
    }

    public function testValidateThrowsExceptionIfNonReadableDirectoryConfigured(): void
    {
        $this->expectException(DirectoryNotReadableException::class);
        $this->createModuleStructure();
        $this->changePermissionsOfSmartyPluginDirectoryToNonReadable();
        $this->assertSmartyPluginDirectoryIsNonReadable();

        $this->modulePathResolver
            ->method('getFullModulePathFromConfiguration')
            ->willReturn(vfsStream::url('root/modules/smartyTestModule'));

        $validator = new SmartyPluginDirectoriesValidator($this->modulePathResolver);

        $moduleConfiguration = new ModuleConfiguration();
        $moduleConfiguration->addSmartyPluginDirectory(new SmartyPluginDirectory('smarty'));
        $moduleConfiguration->setId("smartyTestModule");

        $this->expectException(DirectoryNotReadableException::class);
        $validator->validate($moduleConfiguration, 1);
    }

    public function testValidateThrowsExceptionIfNotArrayConfigured(): void
    {
        $this->expectException(ModuleSettingNotValidException::class);

        $validator = new SmartyPluginDirectoriesValidator($this->modulePathResolver);

        $moduleConfiguration = new ModuleConfiguration();
        $moduleConfiguration->addSmartyPluginDirectory(new SmartyPluginDirectory(''));
        $moduleConfiguration->setId("smartyTestModule");

        $this->expectException(ModuleSettingNotValidException::class);
        $validator->validate($moduleConfiguration, 1);
    }

    private function createModuleStructure()
    {
        $structure = [
            'modules' => [
                'smartyTestModule' => [
                    'smarty' => [
                        'smartyPlugin.php' => '*this is test smarty plugin*'
                    ]
                ]
            ]
        ];

        if (!$this->vfsStreamDirectory) {
            $this->vfsStreamDirectory = vfsStream::setup('root', null, $structure);
        }
    }

    private function changePermissionsOfSmartyPluginDirectoryToNonReadable(): void
    {
        $this->vfsStreamDirectory
            ->getChild('modules')
            ->getChild('smartyTestModule')
            ->getChild('smarty')
            ->chmod(0000);
    }

    private function assertSmartyPluginDirectoryIsNonReadable(): void
    {
        $this->assertFalse(
            $this->vfsStreamDirectory
                ->getChild('modules')
                ->getChild('smartyTestModule')
                ->getChild('smarty')
                ->isReadable(vfsStream::getCurrentUser(), vfsStream::getCurrentGroup())
        );
    }
}
