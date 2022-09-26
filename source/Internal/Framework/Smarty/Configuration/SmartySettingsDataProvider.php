<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

declare(strict_types=1);

namespace OxidEsales\EshopCommunity\Internal\Framework\Smarty\Configuration;

use OxidEsales\EshopCommunity\Internal\Framework\Smarty\Extension\SmartyTemplateHandlerInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Smarty\SmartyContextInterface;

class SmartySettingsDataProvider implements SmartySettingsDataProviderInterface
{
    public function __construct(
        private SmartyContextInterface $context,
        private SmartyTemplateHandlerInterface $smartyTemplateHandler
    )
    {
    }

    /**
     * Define and return basic smarty settings
     *
     * @return array
     */
    public function getSettings(): array
    {
        return [
            'caching' => false,
            'left_delimiter' => '[{',
            'right_delimiter' => '}]',
            'template_dir' => $this->context->getTemplateDirectories(),
            'compile_id' => $this->context->getTemplateCompileId(),
            'default_template_handler_func' => [$this->smartyTemplateHandler, 'handleTemplate'],
            'debugging' => $this->context->getTemplateEngineDebugMode(),
            'compile_check' => $this->context->getTemplateCompileCheckMode(),
            'php_handling' => (int) $this->context->getTemplatePhpHandlingMode(),
            'security' => false
        ];
    }
}
