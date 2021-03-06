<?php
/**
 * This file is part of the CarteBlanche PHP framework.
 *
 * (c) Pierre Cassat <me@e-piwi.fr> and contributors
 *
 * License Apache-2.0 <http://github.com/php-carteblanche/carteblanche/blob/master/LICENSE>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CarteBlancheInstaller;

use \Composer\Composer;
use \Composer\Package\PackageInterface;
use \AssetsManager\Config;
use \Assets\Composer\TemplateEngineAutoloadGenerator;

/**
 * The framework installer for bundles, tools and Composer events
 *
 * @author  Piero Wbmstr <me@e-piwi.fr>
 */
class CarteBlancheAutoloadGenerator
    extends TemplateEngineAutoloadGenerator
{

    /**
     * @param \Composer\Package\PackageInterface $package
     * @param \Composer\Composer $composer
     */
    public function __construct(PackageInterface $package, Composer $composer)
    {
        parent::__construct($package, $composer);
        Config::load('CarteBlancheInstaller\CarteBlancheConfig');
    }

    /**
     * Build the complete database array
     * @return array
     */
    public function getFullDb()
    {
        $full_db = parent::getFullDb();
        $extra = $this->_package->getExtra();

        $packages = null;
        if (isset($full_db['packages'])) {
            $packages = $full_db['packages'];
            unset($full_db['packages']);
        }

        $full_db['config-dir'] = isset($extra['config-dir']) ? $extra['config-dir'] : Config::getDefault('config-dir');
        $full_db['config-vendor-dir'] = isset($extra['config-vendor-dir']) ? $extra['config-vendor-dir'] : Config::getDefault('config-vendor-dir');
        $full_db['var-dir'] = isset($extra['var-dir']) ? $extra['var-dir'] : Config::getDefault('var-dir');

        $root_data = $this->parseRootComposerExtra($this->_package);
        if (!empty($root_data)) {
            $root_data['relative_path'] = '../';
            $packages[$this->_package->getPrettyName()] = $root_data;
        }

        if (!empty($packages)) {
            $full_db['packages'] = $packages;
        }
        return $full_db;
    }

    /**
     * Parse the `composer.json` "extra" block of a package and return its transformed data
     *
     * @param \Composer\Package\PackageInterface $package
     * @param string $assets_package_dir
     * @param string $vendor_package_dir
     * @return array\null
     */
    public function parseComposerExtra(PackageInterface $package, $assets_package_dir, $vendor_package_dir)
    {
        $data = parent::parseComposerExtra($package, $assets_package_dir, $vendor_package_dir);
        if (is_null($data)) {
            $data = array();
        }
        if (strlen($vendor_package_dir)) {
            $vendor_package_dir = rtrim($vendor_package_dir, '/');
        }

        $installer = CarteBlancheInstaller::getInstanciatedInstance();
        $type = $installer->getPackageType($package);
        $installer->getAssetsInstallPath($package);
        if ($installer->mustHandlePackageType($type)) {
            $cb_path = $installer->getInstallPath($package);
            $cb_rel_path = str_replace($installer->getAppBasePath().'/', '', $cb_path);

            foreach (array('layouts_path', 'views_path', 'views_functions') as $entry) {
                if (isset($data[$entry])) {
                    $data[$entry] = str_replace($vendor_package_dir, $cb_rel_path, $data[$entry]);
                }
            }
            
            if ($installer->isPackageContains($package, 'assets-dir')) {
                $data['relative_path'] = str_replace(
                    $installer->getAssetsVendorDir() . '/', '', $installer->getAssetsInstallPath($package));
            }

            if ($installer->isPackageContains($package, 'config-dir', 'carte-blanche-configs')) {
                $files = $installer->getPackageConfigFiles($package);
                $base_from = rtrim($installer->getPackageBasePath($package), '/') . DIRECTORY_SEPARATOR
                    . rtrim($installer->guessConfigurationEntry($package, 'config-dir'), '/') . DIRECTORY_SEPARATOR;
                if (!empty($files)) {
                    foreach ($files as $i=>$file) {
                        $files[$i] = str_replace($base_from, '', $file);
                    }
                    $data['config_files'] = $files;
                }
            }

            if ($installer->isPackageContains($package, 'i18n-dir', 'carte-blanche-i18n')) {
                $files = $installer->getPackageLanguageFiles($package);
                $base_from = rtrim($installer->getPackageBasePath($package), '/') . DIRECTORY_SEPARATOR
                    . rtrim($installer->guessConfigurationEntry($package, 'i18n-dir'), '/') . DIRECTORY_SEPARATOR;
                if (!empty($files)) {
                    foreach ($files as $i=>$file) {
                        $files[$i] = str_replace($base_from, '', $file);
                    }
                    $data['language_files'] = $files;
                }
            }

        }

        return !empty($data) ? $data : null;
    }

    /**
     * Parse the `composer.json` "extra" block of the root package and return its transformed data
     *
     * @param \Composer\Package\PackageInterface $package
     * @return array\null
     */
    public function parseRootComposerExtra(PackageInterface $package)
    {
        $assets_package_dir = $this->_autoloader->getAssetsInstaller()->getAppBasePath();
        $vendor_package_dir = '';
        $data = parent::parseComposerExtra($package, $assets_package_dir, $vendor_package_dir);
        if (is_null($data)) $data = array();

        $installer = CarteBlancheInstaller::getInstanciatedInstance();
        $vendor_package_dir = $installer->getAppBasePath();
        $cb_path = $installer->getInstallPath($package);

        $cb_rel_path = str_replace($vendor_package_dir, '', $cb_path);
        foreach (array('layouts_path', 'views_path', 'views_functions') as $entry) {
            if (isset($data[$entry])) {
                $data[$entry] = str_replace($cb_path, $cb_rel_path, $data[$entry]);
            }
        }
        
        if ($installer->isPackageContains($package, 'assets-dir')) {
            $data['relative_path'] = str_replace(
                $installer->getAssetsVendorDir() . '/', '', $installer->getAssetsInstallPath($package));
        }

        if ($installer->isPackageContains($package, 'config-dir', 'carte-blanche-configs')) {
            $files = $installer->getRootPackageConfigFiles($package);
            if (!empty($files)) {
                $installer->installRootConfig($package);
                foreach ($files as $i=>$file) {
                    $files[$i] = basename($file);
                }
                $data['config_files'] = $files;
            }
        }

        return !empty($data) ? $data : null;
    }

}

// Endfile