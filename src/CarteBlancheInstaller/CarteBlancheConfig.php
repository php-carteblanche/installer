<?php
/**
 * CarteBlanche - PHP framework package - Installers package
 * Copyleft (c) 2013 Pierre Cassat and contributors
 * <www.ateliers-pierrot.fr> - <contact@ateliers-pierrot.fr>
 * License Apache-2.0 <http://www.apache.org/licenses/LICENSE-2.0.html>
 * Sources <http://github.com/php-carteblanche/carteblanche>
 */

namespace CarteBlancheInstaller;

use \AssetsManager\Config\DefaultConfig;

/**
 * @author 		Piero Wbmstr <piero.wbmstr@gmail.com>
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
        $_ds = DIRECTORY_SEPARATOR;
        $_bs_filename = 'bootstrap.php';
        // hack
        @define('_ROOTFILE', false);
        // [src/bootstrap.php]/vendor/carte-blanche/installer/src/CarteBlancheInstaller/
        if (file_exists($_bs = realpath(__DIR__.$_ds.'..'.$_ds.'..'.$_ds.'..'.$_ds.'..'.$_ds.'..'.$_ds.$_bs_filename))) {
            include_once $_bs;
        } else {
            throw new \ErrorException(
                sprintf('Bootstrap file "%s" not found in project! (searched as "%s")', $_bs_filename, $_bs)
            );
        }
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
            'vendor-dir' => _SRCDIR._VENDORDIRNAME,
            // The default package assets directory name (related to package root dir)
            'assets-dir' => _WEBDIR,
            // The default third-party packages'assets directory name (related to package assets dir)
            'assets-vendor-dir' => _VENDORDIRNAME,
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
            // must impelements AssetsManager\Config\ConfiguratorInterface
            'assets-config-class' => null,
            // the AssetsPackage class
            // must implements AssetsManager\Package\AssetsPackageInterface
            'assets-package-class' => 'Assets\Package\Package',
            // the AssetsPreset class
            // must implements AssetsManager\Package\AssetsPresetInterface
            'assets-preset-class' => 'Assets\Package\Preset',
            // the AssetsInstaller class
            // must implements AssetsManager\Composer\Installer\AssetsInstallerInterface
            'assets-package-installer-class' => 'CarteBlancheInstaller\CarteBlancheInstaller',
            // the AssetsAutoloadGenerator class
            // must extends AssetsManager\Composer\Autoload\AbstractAutoloadGenerator
            'assets-autoload-generator-class' => 'CarteBlancheInstaller\CarteBlancheAutoloadGenerator',

// Template Engine
            // relative cache directory from assets-dir
            'cache-dir' => _TMPDIRNAME,
            // relative assets cache directory from assets-dir
            'cache-assets-dir' => _TMPDIRNAME.'assets',
            // relative layouts from root-dir
            'layouts' => _SRCDIR._VIEWSDIRNAME,
            // relative views from root-dir
            'views' => _SRCDIR._VIEWSDIRNAME,
            // relative views functions from root-dir
            // taken from `composer.json`
            'views-functions' => 'src/app_views_fcts.php',
            
// Carte Blanche
            // relative config files directory from root
            'config-dir' => _CONFIGDIR,
            // relative documentation files directory from root
            'doc-dir' => _DOCDIR,
            // relative language files directory from root
            'i18n-dir' => _LANGUAGEDIR,
            // relative var files directory from root
            'var-dir' => _VARDIR,
            // relative vendor config files directory from config directory
            'config-vendor-dir' => _VENDORDIRNAME,
            // relative vendor language files directory from config directory
            'i18n-vendor-dir' => _VENDORDIRNAME,
            // list of config files from package's root
            'carte-blanche-configs' => null,
            // list of language files from package's root
            'carte-blanche-i18n' => null,
            // relative bundles directory from root
            'bundle-dir' => _SRCDIR._BUNDLESDIR,
            // name mask of bundles
            'bundle-name' => 'carte-blanche/bundle-',
            // relative tools directory from root
            'tool-dir' => _SRCDIR._TOOLSDIR,
            // name mask of tools
            'tool-name' => 'carte-blanche/tool-',

        );
    }

}

// Endfile