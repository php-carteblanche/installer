<?php
/**
 * CarteBlanche - PHP framework package - Composer installer package
 * (c) Pierre Cassat and contributors
 * 
 * Sources <http://github.com/php-carteblanche/installer>
 *
 * License Apache-2.0
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CarteBlancheInstaller;

use \AssetsManager\Config\DefaultConfig;

/**
 * @author 		Piero Wbmstr <piwi@ateliers-pierrot.fr>
 */
class CarteBlancheConfig
    extends DefaultConfig
{

    /**
     * Core package type
     */
    const CARTEBLANCHE_CORETYPE = 'carte-blanche-core';

    /**
     * Bundles package type
     */
    const CARTEBLANCHE_BUNDLETYPE = 'carte-blanche-bundle';

    /**
     * Tools package type
     */
    const CARTEBLANCHE_TOOLTYPE = 'carte-blanche-tool';

    /**
     * The real configuration entries
     * @return array
     */
    public static function getDefaults()
    {
        return array(

// Assets Manager
            // The default package type handles by the installer
            'package-type' => array(
                'library-assets', 'library-assets-template',
                self::CARTEBLANCHE_CORETYPE,
                self::CARTEBLANCHE_TOOLTYPE,
                self::CARTEBLANCHE_BUNDLETYPE,
            ),
            // The default package vendor directory name (related to package root dir)
            'vendor-dir' => 'src/vendor',
            // The default package assets directory name (related to package root dir)
            'assets-dir' => 'www',
            // The default third-party packages'assets directory name (related to package assets dir)
            'assets-vendor-dir' => 'vendor',
            // The default package root directory is set on `$_SERVER['DOCUMENT_ROOT']`
            'document-root' => $_SERVER['DOCUMENT_ROOT'],
            // The assets database file created on install
            'assets-db-filename' => 'carteblanche.json',
            // Composition of an `assets-presets` statement in `composer.json`
            // array pairs like "statement name => adapter"
            'use-statements' => array(
                'css' => 'Css',
                'js' => 'Javascript',
                'jsfiles_footer' => 'Javascript',
                'jsfiles_header' => 'Javascript',
                'require' => 'Requirement'
            ),
            // the configuration class (this class, can be null but must be present)
            // must impelement AssetsManager\Config\ConfiguratorInterface
            'assets-config-class' => null,
            // the AssetsPackage class
            // must implements AssetsManager\Package\AssetsPackageInterface
            'assets-package-class' => 'Assets\Package\Package',
            // the AssetsPreset class
            // must implements AssetsManager\Package\AssetsPresetInterface
            'assets-preset-class' => 'Assets\Package\Preset',
            // the AssetsInstaller class
            // must implements AssetsManager\Composer\Installer\AssetsInstallerInterface
            'assets-package-installer-class' => '\CarteBlancheInstaller\CarteBlancheInstaller',
            // the AssetsAutoloadGenerator class
            // must extend AssetsManager\Composer\Autoload\AbstractAutoloadGenerator
            'assets-autoload-generator-class' => '\CarteBlancheInstaller\CarteBlancheAutoloadGenerator',

// Template Engine
            // relative cache directory from assets-dir
            'cache-dir' => 'tmp',
            // relative assets cache directory from assets-dir
            'cache-assets-dir' => 'tmp/assets',
            // relative layouts from root-dir
            'layouts' => 'src/views',
            // relative views from root-dir
            'views' => 'src/views',
            // relative views functions from root-dir
            // taken from `composer.json`
            'views-functions' => 'src/app_views_fcts.php',
            
// Carte Blanche
            // relative config files directory from root
            'config-dir' => 'config',
            // relative documentation files directory from root
            'doc-dir' => 'doc',
            // relative language files directory from root
            'i18n-dir' => 'i18n',
            // relative var files directory from root
            'var-dir' => 'var',
            // relative vendor config files directory from config directory
            'config-vendor-dir' => 'vendor',
            // relative vendor language files directory from config directory
            'i18n-vendor-dir' => 'vendor',
            // list of config files from package's root
            'carte-blanche-configs' => null,
            // list of language files from package's root
            'carte-blanche-i18n' => null,
            // relative bundles directory from root
            'bundle-dir' => 'src/bundles',
            // name mask of bundles
            'bundle-name' => 'carte-blanche/bundle-',
            // relative tools directory from root
            'tool-dir' => 'src/tools',
            // name mask of tools
            'tool-name' => 'carte-blanche/tool-',

        );
    }

}

// Endfile