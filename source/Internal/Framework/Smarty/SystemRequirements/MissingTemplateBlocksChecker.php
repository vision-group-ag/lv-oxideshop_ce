<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

declare(strict_types=1);

namespace OxidEsales\EshopCommunity\Internal\Framework\Smarty\SystemRequirements;

use OxidEsales\EshopCommunity\Internal\Framework\Module\TemplateExtension\TemplateBlockExtension;
use OxidEsales\EshopCommunity\Internal\Framework\Module\TemplateExtension\TemplateBlockExtensionDaoInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Templating\Loader\TemplateLoaderInterface;
use OxidEsales\EshopCommunity\Internal\Transition\Adapter\ShopAdapterInterface;
use OxidEsales\EshopCommunity\Internal\Transition\Utility\ContextInterface;

class MissingTemplateBlocksChecker implements MissingTemplateBlocksCheckerInterface
{
    public function __construct(
        private TemplateBlockExtensionDaoInterface $templateBlockExtensionDao,
        private ContextInterface $context,
        private TemplateLoaderInterface $adminLoader,
        private TemplateLoaderInterface $frontendLoader,
        private ShopAdapterInterface $shopAdapter
    ){}

    public function collectMissingTemplateBlockExtensions(): array
    {
        $result = [];
        $analyzed = [];

        $templateBlockExtensions = $this->templateBlockExtensionDao
            ->getExtensionsByTheme($this->context->getCurrentShopId(), [$this->shopAdapter->getActiveThemeId()]);

        if (count($templateBlockExtensions)) {
            /** @var TemplateBlockExtension $templateBlockExtension */
            foreach ($templateBlockExtensions as $templateBlockExtension) {
                $template = $templateBlockExtension->getExtendedBlockTemplatePath();
                $blockName = $templateBlockExtension->getName();

                if (isset($analyzed[$template], $analyzed[$template][$blockName])) {
                    $blockExistsInTemplate = $analyzed[$template][$blockName];
                } else {
                    $blockExistsInTemplate = $this->checkTemplateBlock($template, $blockName);
                    $analyzed[$template][$blockName] = $blockExistsInTemplate;
                }

                if (!$blockExistsInTemplate) {
                    $result[] = [
                        'module'   => $templateBlockExtension->getModuleId(),
                        'block'    => $blockName,
                        'template' => $template,
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * check if given template contains the given block
     *
     * @param string $sTemplate  template file name
     * @param string $sBlockName block name
     *
     * @see getMissingTemplateBlocks
     *
     * @return bool
     */
    protected function checkTemplateBlock(string $sTemplate, string $sBlockName): bool
    {
        $templateLoader = $this->frontendLoader;
        if (!$templateLoader->exists($sTemplate)) {
            $templateLoader = $this->adminLoader;
            if (!$templateLoader->exists($sTemplate)) {
                return false;
            }
        }

        $sFile = $templateLoader->getContext($sTemplate);
        $sBlockNameQuoted = preg_quote($sBlockName, '/');

        return (bool) preg_match('/\[\{\s*block\s+name\s*=\s*([\'"])' . $sBlockNameQuoted . '\1\s*\}\]/is', $sFile);
    }

}
