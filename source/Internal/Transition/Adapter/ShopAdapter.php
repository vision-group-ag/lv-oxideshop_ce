<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

declare(strict_types=1);

namespace OxidEsales\EshopCommunity\Internal\Transition\Adapter;

use OxidEsales\Eshop\Core\Module\ModuleSmartyPluginDirectoryRepository;
use OxidEsales\Eshop\Core\Module\ModuleVariablesLocator;
use OxidEsales\Eshop\Core\NamespaceInformationProvider;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\Routing\ShopControllerMapProvider;
use OxidEsales\Eshop\Core\ShopIdCalculator as EshopShopIdCalculator;
use OxidEsales\EshopCommunity\Application\Model\Shop;

class ShopAdapter implements ShopAdapterInterface
{
    /**
     * @var EshopShopIdCalculator
     */
    private $shopIdCalculator;

    /**
     * @param string $string
     *
     * @return string
     */
    public function translateString($string): string
    {
        $lang = Registry::getLang();

        return $lang->translateString($string);
    }

    /**
     * @param string $moduleId
     */
    public function invalidateModuleCache(string $moduleId)
    {
        /**
         * @TODO we have to implement it in ModuleCacheServiceInterface or use ModuleCache::resetCache() method.
         */

        $this->invalidateModulesCache();
    }

    public function invalidateModulesCache(): void
    {
        $utils = Registry::getUtils();
        $utils->resetLanguageCache();
        $utils->resetMenuCache();
        $utils->oxResetFileCache(true);

        ModuleVariablesLocator::resetModuleVariables();

        if (extension_loaded('apc') && ini_get('apc.enabled')) {
            apc_clear_cache();
        }
    }

    /**
     * @return string
     */
    public function generateUniqueId(): string
    {
        return Registry::getUtilsObject()->generateUId();
    }

    /**
     * @return array
     */
    public function getShopControllerClassMap(): array
    {
        $shopControllerMapProvider = oxNew(ShopControllerMapProvider::class);

        return $shopControllerMapProvider->getControllerMap();
    }

    /**
     * @param string $namespace
     * @return bool
     */
    public function isNamespace(string $namespace): bool
    {
        return NamespaceInformationProvider::isNamespacedClass($namespace);
    }

    /**
     * @param string $namespace
     * @return bool
     */
    public function isShopUnifiedNamespace(string $namespace): bool
    {
        return NamespaceInformationProvider::classBelongsToShopUnifiedNamespace($namespace);
    }

    /**
     * @param string $namespace
     * @return bool
     */
    public function isShopEditionNamespace(string $namespace): bool
    {
        return NamespaceInformationProvider::classBelongsToShopEditionNamespace($namespace);
    }

    /**
     * @return \Smarty
     */
    public function getSmartyInstance(): \Smarty
    {
        return Registry::getUtilsView()->getSmarty();
    }

    /**
     * @return bool
     */
    public function validateShopId(int $shopId): bool
    {
        $shopModel = oxNew(Shop::class);
        $shopModel->load($shopId);
        return $shopModel->isLoaded();
    }

    public function getModuleSmartyPluginDirectories(): array
    {
        $moduleSmartyPluginDirectoryRepository = $this->getSmartyPluginDirectoryRepository();
        $moduleSmartyPluginDirectories = $moduleSmartyPluginDirectoryRepository->get();

        return $moduleSmartyPluginDirectories->getWithFullPath();
    }

    private function getSmartyPluginDirectoryRepository(): ModuleSmartyPluginDirectoryRepository
    {
        $subShopSpecificCache = oxNew(
            \OxidEsales\Eshop\Core\SubShopSpecificFileCache::class,
            $this->getShopIdCalculator()
        );

        $moduleVariablesLocator = oxNew(
            ModuleVariablesLocator::class,
            $subShopSpecificCache,
            $this->getShopIdCalculator()
        );

        return oxNew(
            ModuleSmartyPluginDirectoryRepository::class,
            $moduleVariablesLocator
        );
    }

    private function getShopIdCalculator(): EshopShopIdCalculator
    {
        if (is_null($this->shopIdCalculator)) {
            $moduleVariablesCache = oxNew(\OxidEsales\Eshop\Core\FileCache::class);

            $this->shopIdCalculator = oxNew(
                EshopShopIdCalculator::class,
                $moduleVariablesCache
            );
        }
        return $this->shopIdCalculator;
    }

    /**
     * Get active themes list.
     * Examples:
     *      if flow theme is active we will get ['flow']
     *      if azure is extended by some other we will get ['azure', 'extending_theme']
     *
     * @return array
     */
    public function getActiveThemesList(): array
    {
        $config = Registry::getConfig();

        $activeThemeList = [];
        if (!$config->isAdmin()) {
            $activeThemeList[] = $config->getConfigParam('sTheme');

            if ($customThemeId = $config->getConfigParam('sCustomTheme')) {
                $activeThemeList[] = $customThemeId;
            }
        }

        return $activeThemeList;
    }

    public function getCustomTheme(): string
    {
        return (string) Registry::getConfig()->getConfigParam('sCustomTheme');
    }
}
