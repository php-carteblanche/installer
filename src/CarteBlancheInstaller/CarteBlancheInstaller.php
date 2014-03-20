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

use \RecursiveDirectoryIterator,
    \RecursiveIteratorIterator;

use \Composer\Composer,
    \Composer\IO\IOInterface,
    \Composer\Installer\LibraryInstaller,
    \Composer\Package\PackageInterface,
    \Composer\Repository\InstalledRepositoryInterface,
    \Composer\Script\Event;

use \AssetsManager\Composer\Installer\AssetsInstaller,
    \AssetsManager\Error,
    \AssetsManager\Config;

use \CarteBlancheInstaller\BootstrapGenerator,
    \CarteBlancheInstaller\CarteBlancheConfig,
    \CarteBlancheInstaller\CarteBlancheAutoloadGenerator,
    \CarteBlancheInstaller\Util\Filesystem;


/**
 * The framework installer for bundles, tools and Composer events
 *
 * @author 		Piero Wbmstr <piwi@ateliers-pierrot.fr>
 */
class CarteBlancheInstaller
    extends AssetsInstaller
{

    protected $config_dir;
    protected $config_vendor_dir;

    protected $i18n_dir;
    protected $i18n_vendor_dir;

    protected $doc_dir;

    /**
     * @var self
     */
    protected static $_instanciatedInstance;

    /**
     */
    public static function postAutoloadDump(Event $event)
    {
        $composer = $event->getComposer();
        $io = $event->getIO();
        $package = $composer->getPackage();

        $_this = new CarteBlancheAutoloadGenerator($package, $composer);

        $bt = new BootstrapGenerator( $composer, $io );
        if (false!=$bt->generate( $package )) {
            $io->write( '<info>Generating CarteBlanche bootstrap</info>' );
        } else {
            $io->write( 'ERROR while trying to generate CarteBlanche bootstrap!' );
        }
    }

    /**
     * Initializes installer: creation of requried directories if so
     *
     * {@inheritDoc}
     */
    public function __construct(IOInterface $io, Composer $composer, $type = 'library')
    {
/*
        $spl_loader         = realpath(__DIR__.'/../SplClassLoader.php');
        $cb_namespace       = realpath(__DIR__.'/../../../core/src/');
        $cb_defaultconfig   = realpath(__DIR__.'/../../../core/config/carteblanche.ini');

        // register the CarteBlanche namespace
        require_once __DIR__.'/../SplClassLoader.php';
        $libraryLoader = new \SplClassLoader('CarteBlanche', $cb_namespace);
        $libraryLoader->register();

//        parent::__construct($io, $composer, $type);
        $extra              = $composer->getPackage()->getExtra();
        $cb_config_files    = isset($extra['carteblanche-config-files']) ? $extra['carteblanche-config-files'] : null;

$configurator = new \CarteBlanche\App\Config();
//$configurator->load();
var_export($config_files);
//        Config::load($configurator);

exit('yo');
*/
        Config::load('CarteBlancheInstaller\CarteBlancheConfig');
        parent::__construct($io, $composer, $type);
        $this->filesystem = new Filesystem;

        $this->doc_dir = $this->guessConfigurationEntry($composer->getPackage(), 'doc-dir');
        $this->config_dir = $this->guessConfigurationEntry($composer->getPackage(), 'config-dir');
        $this->config_vendor_dir = $this->guessConfigurationEntry($composer->getPackage(), 'config-vendor-dir');
        $this->i18n_dir = $this->guessConfigurationEntry($composer->getPackage(), 'i18n-dir');
        $this->i18n_vendor_dir = $this->guessConfigurationEntry($composer->getPackage(), 'i18n-vendor-dir');
        $this->filesystem->ensureDirectoryExists(Config::get('bundle-dir'));
        $this->filesystem->ensureDirectoryExists(Config::get('tool-dir'));
        $this->filesystem->ensureDirectoryExists(Config::get('var-dir'));

        self::$_instanciatedInstance = $this;
    }

    /**
     * Get the current installer instance if it exists
     */
    public static function getInstanciatedInstance()
    {
        return self::$_instanciatedInstance;
    }

    /**
     * Get the object type by package type: `bundle`, `tool`, `core` or other
     *
     * @param PackageInterface $package
     * @return string
     */
    public static function getPackageType(PackageInterface $package)
    {
        $type = $package->getType();
        if ($type===CarteBlancheConfig::CARTEBLANCHE_BUNDLETYPE) {
            return 'bundle';
        } elseif ($type===CarteBlancheConfig::CARTEBLANCHE_TOOLTYPE) {
            return 'tool';
        } elseif ($type===CarteBlancheConfig::CARTEBLANCHE_CORETYPE) {
            return 'core';
        } else {
            return $type;
        }
    }

    /**
     * Test if the class must handle the package by its type
     *
     * @param string $type
     * @return bool
     */
    public static function mustHandlePackageType($type)
    {
        return in_array($type, array('bundle', 'tool', 'core'));
    }

    /**
     * {@inheritDoc}
     */
    public function getInstallPath(PackageInterface $package)
    {
        $type = self::getPackageType($package);
        if ('bundle'===$type) {
            return $this->getBundleInstallPath($package);
        } elseif ('tool'===$type) {
            return $this->getToolInstallPath($package);
        } elseif ('core'===$type) {
            return $this->getCoreInstallPath($package);
        } else {
            return parent::getInstallPath($package);
        }
    }

    /**
     * Get the package path based on package type: 'default', 'tool' or 'bundle'
     *
     * @param object $package Composer\Package\PackageInterface
     * @return string
     */
    public function getPackageBasePath(PackageInterface $package)
    {
        $type = self::getPackageType($package);
        $base = parent::getPackageBasePath($package);
        if ('tool'===$type) {
            $tool_name = self::extractShortName($package, 'tool-name');
            if ('defaults'===$tool_name) {
                $base = $this->getToolRootPath();
            } else {
                $base = $this->getToolRootPath() . '/' . $tool_name;
            }
        } elseif ('bundle'===$type) {
            $base = $this->getBundleRootPath() . '/' . self::extractShortName($package, 'bundle-name');
        } else {
            $base = parent::getPackageBasePath($package);
        }
        return $base;
    }
        
// ---------------------------
// Assets Installer
// ---------------------------

    /**
     * {@inheritDoc}
     */
    public function isInstalled(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        $type = self::getPackageType($package);
        if (self::mustHandlePackageType($type)) {
            if ($this->isPackageContains($package, 'assets-dir')) {
                $ok = parent::isInstalled($repo, $package);
                if (!$ok) return $ok;
            } else {
                $ok = LibraryInstaller::isInstalled($repo, $package);
                if (!$ok) return $ok;
            }
            if ($this->isPackageContains($package, 'config-dir', 'carte-blanche-configs')) {
                $ok = $this->isInstalledConfig($package);
            }
            if ($this->isPackageContains($package, 'i18n-dir', 'carte-blanche-i18n')) {
                $ok = $this->isInstalledLanguage($package);
            }
            if ($this->isPackageContains($package, 'doc-dir', 'carte-blanche-docs')) {
                $ok = $this->isInstalledDoc($package);
            }
            return $ok;
        } else {
            return parent::isInstalled($repo, $package);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        $type = self::getPackageType($package);
        if (self::mustHandlePackageType($type)) {
            if ($this->isPackageContains($package, 'assets-dir')) {
                parent::install($repo, $package);
            } else {
                LibraryInstaller::install($repo, $package);
            }
            if ($this->isPackageContains($package, 'config-dir', 'carte-blanche-configs')) {
                $this->installConfig($package);
            }
            if ($this->isPackageContains($package, 'i18n-dir', 'carte-blanche-i18n')) {
                $this->installLanguage($package);
            }
            if ($this->isPackageContains($package, 'doc-dir', 'carte-blanche-docs')) {
                $ok = $this->installDoc($package);
            }
        } else {
            return parent::install($repo, $package);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target)
    {
        $type = self::getPackageType($initial);
        if (self::mustHandlePackageType($type)) {
            if ($this->isPackageContains($initial, 'config-dir', 'carte-blanche-configs')) {
                $this->removeConfig($initial);
            }
            if ($this->isPackageContains($initial, 'assets-dir')) {
                parent::update($repo, $initial, $target);
            } else {
                LibraryInstaller::update($repo, $initial, $target);
            }
            if ($this->isPackageContains($target, 'config-dir', 'carte-blanche-configs')) {
                $this->installConfig($target);
            }
            if ($this->isPackageContains($target, 'i18n-dir', 'carte-blanche-i18n')) {
                $this->installLanguage($target);
            }
            if ($this->isPackageContains($target, 'doc-dir', 'carte-blanche-docs')) {
                $ok = $this->installDoc($target);
            }
        } else {
            return parent::update($repo, $initial, $target);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        $type = self::getPackageType($package);
        if (self::mustHandlePackageType($type)) {
            if ($this->isPackageContains($package, 'config-dir', 'carte-blanche-configs')) {
                $this->removeConfig($package);
            }
            if ($this->isPackageContains($package, 'i18n-dir', 'carte-blanche-i18n')) {
                $this->removeLanguage($package);
            }
            if ($this->isPackageContains($package, 'doc-dir', 'carte-blanche-docs')) {
                $ok = $this->removeDoc($package);
            }
            if ($this->isPackageContains($package, 'assets-dir')) {
                return parent::uninstall($repo, $package);
            } else {
                return LibraryInstaller::uninstall($repo, $package);
            }
        } else {
            return parent::uninstall($repo, $package);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function parseComposerExtra(PackageInterface $package, $package_dir)
    {
        $type = self::getPackageType($package);
        $data = parent::parseComposerExtra($package, $package_dir);
        if (!empty($data) && self::mustHandlePackageType($type)) {
            $data['carte_blanche_type'] = $type;
            $data['carte_blanche_path'] = $this->getInstallPath($package);
        }
        return $data;
    }
    
// ---------------------------
// Config files
// ---------------------------

    protected function getConfigDir()
    {
        $this->initializeConfigDir();
        return $this->config_dir;
    }

    protected function getConfigVendorDir()
    {
        $this->initializeConfigVendorDir();
        return $this->config_vendor_dir;
    }

    protected function initializeConfigDir()
    {
        $this->filesystem->ensureDirectoryExists($this->config_dir);
        $this->config_dir = realpath($this->config_dir);
    }

    protected function initializeConfigVendorDir()
    {
        $path = $this->getConfigDir() . '/' . (
            $this->config_vendor_dir ? str_replace($this->getConfigDir(), '', $this->config_vendor_dir) : ''
        );
        $this->filesystem->ensureDirectoryExists($path);
        $this->config_vendor_dir = realpath($path);
    }

    public function getConfigInstallPath(PackageInterface $package)
    {
        return $this->getConfigVendorDir();
    }

    public function getRootPackageConfigFiles(PackageInterface $package)
    {
        return $this->_getPackageFiles(
            $package, 'carte-blanche-configs', null, $this->config_dir, 
            $this->getAppBasePath(), 'configuration file'
        );
    }
    
    public function getPackageConfigFiles(PackageInterface $package)
    {
        return $this->_getPackageFiles(
            $package, 'carte-blanche-configs', 'config-dir', $this->config_dir, 
            $this->getPackageBasePath($package), 'configuration file'
        );
    }

    /**
     * Test if config files of a package already exists
     *
     * @param object $package Composer\Package\PackageInterface
     * @return bool
     */
    protected function isInstalledConfig(PackageInterface $package)
    {
        $from_files = $this->getPackageConfigFiles($package);
        if (empty($from_files)) {
            return;
        }
        $target = rtrim($this->getConfigInstallPath($package), '/') . DIRECTORY_SEPARATOR;
        $return = false;
        foreach ($from_files as $config) {
            if (file_exists($config)) {
                $link = $this->config_vendor_dir.'/'.basename($config);
                $return = file_exists($link);
            }
        }
        return $return;
    }

    /**
     * Move the config files of a package
     *
     * @param object $package Composer\Package\PackageInterface
     * @return bool
     */
    protected function installConfig(PackageInterface $package)
    {
        $from_files = $this->getPackageConfigFiles($package);
        if (empty($from_files)) {
            return;
        }
        $target = rtrim($this->getConfigInstallPath($package), '/') . DIRECTORY_SEPARATOR;
        $package_base_path = rtrim($this->getPackageBasePath($package), '/') . DIRECTORY_SEPARATOR;
        $this->io->write( 
            sprintf('  - Installing <info>%s</info> config files to <info>%s</info>', 
                $package->getPrettyName(),
                str_replace(dirname($this->config_dir) . '/', '', $target)
            )
        );
        $return = $this->doInstallConfig($from_files, $target);
        $this->io->write('');
        return $return;
    }

    /**
     * Remove the config files of a package
     *
     * @param object $package Composer\Package\PackageInterface
     * @return bool
     */
    protected function removeConfig(PackageInterface $package)
    {
        $from_files = $this->getPackageConfigFiles($package);
        if (empty($from_files)) {
            return;
        }
        $target = rtrim($this->getConfigInstallPath($package), '/') . DIRECTORY_SEPARATOR;
        $this->io->write( 
            sprintf('  - Removing <info>%s</info> config files to <info>%s</info>', 
                $package->getPrettyName(),
                str_replace(dirname($this->config_dir) . '/', '', $target)
            )
        );
        $return = $this->doRemoveConfig($from_files);
        $this->io->write('');
        return $return;
    }

    /**
     * Move the config files of the root package
     *
     * @param object $package Composer\Package\PackageInterface
     * @return bool
     */
    public function installRootConfig(PackageInterface $package)
    {
        $from_files = $this->getRootPackageConfigFiles($package);
        if (empty($from_files)) {
            return;
        }
        $target = rtrim($this->getConfigInstallPath($package), '/') . DIRECTORY_SEPARATOR;
        $this->io->write( 
            sprintf('  - Installing <info>%s</info> config files to <info>%s</info>', 
                $package->getPrettyName(),
                str_replace(dirname($this->config_dir) . '/', '', $target)
            )
        );
        $return = $this->doInstallConfig($from_files, $target);
        $this->io->write('');
        return $return;
    }

    /**
     * Remove the config files of the root package
     *
     * @param object $package Composer\Package\PackageInterface
     * @return bool
     */
    public function removeRootConfig(PackageInterface $package)
    {
        $from_files = $this->getRootPackageConfigFiles($package);
        if (empty($from_files)) {
            return;
        }
        $target = rtrim($this->getConfigInstallPath($package), '/') . DIRECTORY_SEPARATOR;
        $this->io->write( 
            sprintf('  - Removing <info>%s</info> config files to <info>%s</info>', 
                $package->getPrettyName(),
                str_replace(dirname($this->config_dir) . '/', '', $target)
            )
        );
        $return = $this->doRemoveConfig($from_files);
        $this->io->write('');
        return $return;
    }

    /**
     * Actually process the config links/files isntallation
     * @param array $config_files
     * @param string $target
     * @return bool
     */
    protected function doInstallConfig(array $config_files, $target)
    {
        $app_base_path = rtrim($this->getAppBasePath(), '/') . DIRECTORY_SEPARATOR;
        $return = false;
        foreach ($config_files as $config) {
            if (!file_exists($config)) {
                $this->io->write('    <warning>Skipped installation of '.basename($config).': file not found in package</warning>');
                continue;
            }
            $link = $this->config_vendor_dir.'/'.basename($config);
            if (file_exists($link)) {
                if (is_link($link)) {
                    chmod($link, 0777 & ~umask());
                }
                $this->io->write('    Skipped installation of '.basename($config).': name conflicts with an existing file');
                continue;
            }
            if (defined('PHP_WINDOWS_VERSION_BUILD')) {
                $return = $this->filesystem->copyFiles($config, $target);
            } else {
                $cwd = getcwd();
                try {
                    $relativeConfig = $this->filesystem->findShortestPath($link, $app_base_path . str_replace($app_base_path, '', $config));
                    chdir(dirname($link));
                    $return = symlink($relativeConfig, $link);
                } catch (\ErrorException $e) {
                    $return = $this->filesystem->copyFiles($config, $target);
                }
                chdir($cwd);
            }
            chmod($link, 0777 & ~umask());
        }
        return $return;
    }

    /**
     * Actually process the config links/files removing
     * @param array $config_files
     * @return bool
     */
    protected function doRemoveConfig(array $config_files)
    {
        $return = false;
        foreach ($config_files as $config) {
            $link = $this->config_vendor_dir.'/'.basename($config);
            if (is_link($link) || file_exists($link)) {
                $return = unlink($link);
            }
            if (file_exists($link.'.bat')) {
                $return = unlink($link.'.bat');
            }
        }
        return $return;
    }

// ---------------------------
// Documentation files
// ---------------------------

    protected function getDocDir()
    {
        $this->initializeDocDir();
        return $this->doc_dir;
    }

    protected function initializeDocDir()
    {
        $this->filesystem->ensureDirectoryExists($this->doc_dir);
        $this->doc_dir = realpath($this->doc_dir);
    }

    public function getDocInstallPath(PackageInterface $package)
    {
        return $this->getDocDir();
    }

    public function getPackageDocFiles(PackageInterface $package)
    {
        return $this->_getPackageFiles(
            $package, 'carte-blanche-docs', 'doc-dir', $this->i18n_dir, 
            $this->getPackageBasePath($package), 'documentation file'
        );
    }

    /**
     * Test if documentation files of a package already exists
     *
     * @param object $package Composer\Package\PackageInterface
     * @return bool
     */
    protected function isInstalledDoc(PackageInterface $package)
    {
        $from_files = $this->getPackageDocFiles($package);
        if (empty($from_files)) {
            return;
        }
        $target = rtrim($this->getDocInstallPath($package), '/') . DIRECTORY_SEPARATOR;
        $return = false;
        foreach ($from_files as $config) {
            if (file_exists($config)) {
                $link = $this->doc_dir.'/'.basename($config);
                $return = file_exists($link);
            }
        }
        return $return;
    }

    /**
     * Link the documentation files of a package
     *
     * @param object $package Composer\Package\PackageInterface
     * @return bool
     */
    protected function installDoc(PackageInterface $package)
    {
        $from_files = $this->getPackageDocFiles($package);
        if (empty($from_files)) {
            return;
        }
        $target = rtrim($this->getDocInstallPath($package), '/') . DIRECTORY_SEPARATOR;
        $package_base_path = rtrim($this->getPackageBasePath($package), '/') . DIRECTORY_SEPARATOR;
        $this->io->write( 
            sprintf('  - Linking <info>%s</info> documentation to <info>%s</info>', 
                $package->getPrettyName(),
                str_replace(dirname($this->doc_dir) . '/', '', $target)
            )
        );
        $return = $this->doInstallDoc($from_files, $target);
        $this->io->write('');
        return $return;
    }

    /**
     * Remove the config files of a package
     *
     * @param object $package Composer\Package\PackageInterface
     * @return bool
     */
    protected function removeDoc(PackageInterface $package)
    {
        $from_files = $this->getPackageDocFiles($package);
        if (empty($from_files)) {
            return;
        }
        $target = rtrim($this->getDocInstallPath($package), '/') . DIRECTORY_SEPARATOR;
        $this->io->write( 
            sprintf('  - Removing <info>%s</info> documentation to <info>%s</info>', 
                $package->getPrettyName(),
                str_replace(dirname($this->doc_dir) . '/', '', $target)
            )
        );
        $return = $this->doRemoveDoc($from_files);
        $this->io->write('');
        return $return;
    }

    /**
     * Actually process the config links/files isntallation
     * @param array $doc_files
     * @param string $target
     * @return bool
     */
    protected function doInstallDoc(array $doc_files, $target)
    {
        $app_base_path = rtrim($this->getAppBasePath(), '/') . DIRECTORY_SEPARATOR;
        $return = false;
        foreach ($doc_files as $doc) {
            if (!file_exists($doc)) {
                $this->io->write('    <warning>Skipped linking of '.basename($doc).': file not found in package</warning>');
                continue;
            }
            $link = $this->doc_dir.'/'.basename($doc);
            if (file_exists($link)) {
                if (is_link($link)) {
                    chmod($link, 0777 & ~umask());
                }
                $this->io->write('    Skipped linking of '.basename($doc).': name conflicts with an existing file');
                continue;
            }
            if (defined('PHP_WINDOWS_VERSION_BUILD')) {
                $return = $this->filesystem->copyFiles($doc, $target);
            } else {
                $cwd = getcwd();
                try {
                    $relativeConfig = $this->filesystem->findShortestPath($link, $app_base_path . str_replace($app_base_path, '', $doc));
                    chdir(dirname($link));
                    $return = symlink($relativeConfig, $link);
                } catch (\ErrorException $e) {
                    $return = $this->filesystem->copyFiles($doc, $target);
                }
                chdir($cwd);
            }
            chmod($link, 0777 & ~umask());
        }
        return $return;
    }

    /**
     * Actually process the config links/files removing
     * @param array $doc_files
     * @return bool
     */
    protected function doRemoveDoc(array $doc_files)
    {
        $return = false;
        foreach ($doc_files as $doc) {
            $link = $this->doc_dir.'/'.basename($doc);
            if (is_link($link) || file_exists($link)) {
                $return = unlink($link);
            }
            if (file_exists($link.'.bat')) {
                $return = unlink($link.'.bat');
            }
        }
        return $return;
    }

// ---------------------------
// Language files
// ---------------------------

    protected function getLanguageDir()
    {
        $this->initializeLanguageDir();
        return $this->i18n_dir;
    }

    protected function getLanguageVendorDir()
    {
        $this->initializeLanguageVendorDir();
        return $this->i18n_vendor_dir;
    }

    protected function initializeLanguageDir()
    {
        $this->filesystem->ensureDirectoryExists($this->i18n_dir);
        $this->i18n_dir = realpath($this->i18n_dir);
    }

    protected function initializeLanguageVendorDir()
    {
        $path = $this->getLanguageDir() . '/' . (
            $this->i18n_vendor_dir ? str_replace($this->getLanguageDir(), '', $this->i18n_vendor_dir) : ''
        );
        $this->filesystem->ensureDirectoryExists($path);
        $this->i18n_vendor_dir = realpath($path);
    }

    public function getLanguageInstallPath(PackageInterface $package)
    {
        return $this->getLanguageVendorDir();
    }

    public function getRootPackageLanguageFiles(PackageInterface $package)
    {
        return $this->_getPackageFiles(
            $package, 'carte-blanche-i18n', null, $this->i18n_dir,
            $this->getAppBasePath(), 'language file'
        );
    }

    public function getPackageLanguageFiles(PackageInterface $package)
    {
        return $this->_getPackageFiles(
            $package, 'carte-blanche-i18n', 'i18n-dir', $this->i18n_dir,
            $this->getPackageBasePath($package), 'language file'
        );
    }

    /**
     * Test if i18n files of a package already exists
     *
     * @param object $package Composer\Package\PackageInterface
     * @return bool
     */
    protected function isInstalledLanguage(PackageInterface $package)
    {
        $from_files = $this->getPackageLanguageFiles($package);
        if (empty($from_files)) {
            return;
        }
        $target = rtrim($this->getLanguageInstallPath($package), '/') . DIRECTORY_SEPARATOR;
        $return = false;
        foreach ($from_files as $i18n) {
            if (file_exists($i18n)) {
                $link = $this->i18n_vendor_dir.'/'.basename($i18n);
                $return = file_exists($link);
            }
        }
        return $return;
    }

    /**
     * Move the i18n files of a package
     *
     * @param object $package Composer\Package\PackageInterface
     * @return bool
     */
    protected function installLanguage(PackageInterface $package)
    {
        $from_files = $this->getPackageLanguageFiles($package);
        if (empty($from_files)) {
            return;
        }
        $target = rtrim($this->getLanguageInstallPath($package), '/') . DIRECTORY_SEPARATOR;
        $package_base_path = rtrim($this->getPackageBasePath($package), '/') . DIRECTORY_SEPARATOR;
        $this->io->write( 
            sprintf('  - Installing <info>%s</info> language files to <info>%s</info>', 
                $package->getPrettyName(),
                str_replace(dirname($this->i18n_dir) . '/', '', $target)
            )
        );
        $return = $this->doInstallLanguage($from_files, $target);
        $this->io->write('');
        return $return;
    }

    /**
     * Remove the i18n files of a package
     *
     * @param object $package Composer\Package\PackageInterface
     * @return bool
     */
    protected function removeLanguage(PackageInterface $package)
    {
        $from_files = $this->getPackageLanguageFiles($package);
        if (empty($from_files)) {
            return;
        }
        $target = rtrim($this->getLanguageInstallPath($package), '/') . DIRECTORY_SEPARATOR;
        $this->io->write( 
            sprintf('  - Removing <info>%s</info> language files from <info>%s</info>', 
                $package->getPrettyName(),
                str_replace(dirname($this->i18n_dir) . '/', '', $target)
            )
        );
        $return = $this->doRemoveLanguage($from_files);
        $this->io->write('');
        return $return;
    }

    /**
     * Move the i18n files of the root package
     *
     * @param object $package Composer\Package\PackageInterface
     * @return bool
     */
    public function installRootLanguage(PackageInterface $package)
    {
        $from_files = $this->getRootPackageLanguageFiles($package);
        if (empty($from_files)) {
            return;
        }
        $target = rtrim($this->getLanguageInstallPath($package), '/') . DIRECTORY_SEPARATOR;
        $this->io->write( 
            sprintf('  - Installing <info>%s</info> language files to <info>%s</info>', 
                $package->getPrettyName(),
                str_replace(dirname($this->i18n_dir) . '/', '', $target)
            )
        );
        $return = $this->doInstallLanguage($from_files, $target);
        $this->io->write('');
        return $return;
    }

    /**
     * Remove the i18n files of the root package
     *
     * @param object $package Composer\Package\PackageInterface
     * @return bool
     */
    public function removeRootLanguage(PackageInterface $package)
    {
        $from_files = $this->getRootPackageLanguageFiles($package);
        if (empty($from_files)) {
            return;
        }
        $target = rtrim($this->getLanguageInstallPath($package), '/') . DIRECTORY_SEPARATOR;
        $this->io->write( 
            sprintf('  - Removing <info>%s</info> language files from <info>%s</info>', 
                $package->getPrettyName(),
                str_replace(dirname($this->i18n_dir) . '/', '', $target)
            )
        );
        $return = $this->doRemoveLanguage($from_files);
        $this->io->write('');
        return $return;
    }

    /**
     * Actually process the i18n links/files isntallation
     * @param array $i18n_files
     * @param string $target
     * @return bool
     */
    protected function doInstallLanguage(array $i18n_files, $target)
    {
        $app_base_path = rtrim($this->getAppBasePath(), '/') . DIRECTORY_SEPARATOR;
        $return = false;
        foreach ($i18n_files as $i18n) {
            if (!file_exists($i18n)) {
                $this->io->write('    <warning>Skipped installation of '.basename($i18n).': file not found in package</warning>');
                continue;
            }
            $link = $this->i18n_vendor_dir.'/'.basename($i18n);
            if (file_exists($link)) {
                if (is_link($link)) {
                    chmod($link, 0777 & ~umask());
                }
                $this->io->write('    Skipped installation of '.basename($i18n).': name conflicts with an existing file');
                continue;
            }
            if (defined('PHP_WINDOWS_VERSION_BUILD')) {
                $return = $this->filesystem->copyFiles($i18n, $target);
            } else {
                $cwd = getcwd();
                try {
                    $relativeLanguage = $this->filesystem->findShortestPath($link, $app_base_path . str_replace($app_base_path, '', $i18n));
                    chdir(dirname($link));
                    $return = symlink($relativeLanguage, $link);
                } catch (\ErrorException $e) {
                    $return = $this->filesystem->copyFiles($i18n, $target);
                }
                chdir($cwd);
            }
            chmod($link, 0777 & ~umask());
        }
        return $return;
    }

    /**
     * Actually process the i18n links/files removing
     * @param array $i18n_files
     * @return bool
     */
    protected function doRemoveLanguage(array $i18n_files)
    {
        $return = false;
        foreach ($i18n_files as $i18n) {
            $link = $this->i18n_vendor_dir.'/'.basename($i18n);
            if (is_link($link) || file_exists($link)) {
                $return = unlink($link);
            }
            if (file_exists($link.'.bat')) {
                $return = unlink($link.'.bat');
            }
        }
        return $return;
    }

// ---------------------------
// Core
// ---------------------------

    /**
     * Returns the root installation path for the core
     *
     * @return string a path relative to the root of the composer.json that is being installed where the templates
     *     are stored.
     */
    public function getCoreRootPath()
    {
        return 'src/vendor';
    }

    /**
     * Determines the install path for CarteBlanche core
     */
    public function getCoreInstallPath(PackageInterface $package)
    {
        return realpath($this->getCoreRootPath()) . '/' . $package->getPrettyName();
    }

// ---------------------------
// Bundles
// ---------------------------

    /**
     * Returns the root installation path for bundles
     *
     * @return string a path relative to the root of the composer.json that is being installed where the templates
     *     are stored.
     */
    public function getBundleRootPath()
    {
        return 'src/bundles';
    }

    /**
     * Determines the install path for bundles
     *
     * The installation path is determined by checking whether the package is included in another composer configuration
     * or installed as part of the normal CarteBlanche installation.
     *
     * When the package is included as part of a different project it will be installed in the `src/tools` folder
     * of CarteBlanche (thus `/atelierspierrot/carte-blanche/src/bundles`); if it is installed as part of
     * CarteBlanche it will be installed in the root of the project (thus `/src/bundles`).
     *
     * @param PackageInterface $package
     * @throws \InvalidArgumentException if the name of the package does not start with `carte-blanche/tool-`.
     * @return string a path relative to the root of the composer.json that is being installed.
     */
    public function getBundleInstallPath(PackageInterface $package)
    {
        if (self::extractPrefix($package, 'bundle-name') != Config::get('bundle-name')) {
            throw new \InvalidArgumentException(
                sprintf('Unable to install bundle, CarteBlanche bundles should always start their package name with "%s"',
                    Config::get('bundle-name'))
            );
        }

        return $this->getBundleRootPath() . '/' . self::extractShortName($package, 'bundle-name');
    }

// ---------------------------
// Tools
// ---------------------------

    /**
     * Returns the root installation path for templates.
     *
     * @return string a path relative to the root of the composer.json that is being installed where the templates
     *     are stored.
     */
    public function getToolRootPath()
    {
        return 'src/tools';
    }

    /**
     * Determines the install path for a package
     *
     * The installation path is determined by checking whether the package is included in another composer configuration
     * or installed as part of the normal CarteBlanche installation.
     *
     * When the package is included as part of a different project it will be installed in the `src/tools` folder
     * of CarteBlanche (thus `/atelierspierrot/carte-blanche/src/tools`); if it is installed as part of
     * CarteBlanche it will be installed in the root of the project (thus `/src/tools`).
     *
     * @param PackageInterface $package
     * @throws \InvalidArgumentException if the name of the package does not start with `carte-blanche/tool-`.
     * @return string a path relative to the root of the composer.json that is being installed.
     */
    public function getToolInstallPath(PackageInterface $package)
    {
        if (self::extractPrefix($package, 'tool-name') != Config::get('tool-name')) {
            throw new \InvalidArgumentException(
                sprintf('Unable to install tool, CarteBlanche tools should always start their package name with "%s"',
                    Config::get('tool-name'))
            );
        }
        $tool_name = self::extractShortName($package, 'tool-name');
        if (in_array($tool_name, array('all', 'defaults'))) {
            return $this->getToolRootPath();
        } else {
            return $this->getToolRootPath() . '/' . $tool_name;
        }
    }

// ---------------------------
// Utilities
// ---------------------------

    public static function guessConfigurationEntry(PackageInterface $package, $config_entry)
    {
        if (empty($config_entry)) return array();
        $extra = $package->getExtra();
        return isset($extra[$config_entry]) ? $extra[$config_entry] : Config::get($config_entry);
    }

    /**
     * @return bool
     */
    public static function isPackageContains(PackageInterface $package, $type, $package_extra = null)
    {
        $extra = $package->getExtra();
        if (!is_null($package_extra)) {
            $files = self::guessConfigurationEntry($package, $package_extra);
            return (!empty($extra) && array_key_exists($type, $extra)) || (!empty($files));
        } else {
            return !empty($extra) && array_key_exists($type, $extra);
        }
    }

    /**
     * @param PackageInterface $package
     * @return string
     */
    public static function extractPrefix(PackageInterface $package, $type)
    {
        return substr($package->getPrettyName(), 0, strlen(Config::get($type)));
    }

    /**
     * @param PackageInterface $package
     * @return string
     */
    public static function extractShortName(PackageInterface $package, $type)
    {
        return substr($package->getPrettyName(), strlen(Config::get($type)));
    }

    /**
     * Get a list of files from package
     */
    protected function _getPackageFiles(
        PackageInterface $package,
        $files_type, $dir_type,
        $root_dir, $base_path,
        $name = ''
    ) {
        $package_files = array();
        $_files = $this->guessConfigurationEntry($package, $files_type);
        $_dir = $this->guessConfigurationEntry($package, $dir_type);
        if (empty($_dir) && empty($_files)) {
            return array();
        }
        if (!empty($_files) && !is_array($_files)) {
            $_files = array($_files);
        }
        $base_path = rtrim($base_path, '/') . DIRECTORY_SEPARATOR;

        if (!empty($_files)) {
            foreach ($_files as $file) {
                $from_file = $base_path . $file;
                if (file_exists($from_file)) {
                    $package_files[] = $from_file;
                } else {
                    $this->io->write( 
                        sprintf('Skipping %s <info>%s</info> from package <info>%s</info>: file not found!', 
                            $name,
                            str_replace(dirname($root_dir).'/', '', $from_file),
                            $package->getPrettyName()
                        )
                    );
                }
            }
        }

        if (!empty($_dir)) {
            $_dir_path = $base_path . $_dir;
            if (file_exists($_dir_path)) {
                $it = new RecursiveDirectoryIterator($_dir_path, RecursiveDirectoryIterator::SKIP_DOTS);
                $ri = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::SELF_FIRST);
                foreach ($ri as $file) {
                    if (!in_array($file->getPathname(), $package_files)) {
                        if ($file->isFile()) {
                            $package_files[] = $file->getPathname();
                        }
                    }
                }
            }
        }

        return $package_files;
    }

}

// Endfile