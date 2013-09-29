<?php
/**
 * CarteBlanche - PHP framework package - Installers package
 * Copyleft (c) 2013 Pierre Cassat and contributors
 * <www.ateliers-pierrot.fr> - <contact@ateliers-pierrot.fr>
 * License GPL-3.0 <http://www.opensource.org/licenses/gpl-3.0.html>
 * Sources <https://github.com/atelierspierrot/carte-blanche>
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
 * @author 		Piero Wbmstr <piero.wbmstr@gmail.com>
 */
class CarteBlancheInstaller
    extends AssetsInstaller
{

    protected $config_dir;
    protected $config_vendor_dir;

    protected $i18n_dir;
    protected $i18n_vendor_dir;

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
        Config::load('CarteBlancheInstaller\CarteBlancheConfig');
        parent::__construct($io, $composer, $type);
        $this->filesystem = new Filesystem;

        $this->config_dir = $this->guessConfigDir($composer->getPackage());
        $this->config_vendor_dir = $this->guessConfigVendorDir($composer->getPackage());
        $this->i18n_dir = $this->guessLanguageDir($composer->getPackage());
        $this->i18n_vendor_dir = $this->guessLanguageVendorDir($composer->getPackage());
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
            $tool_name = self::extractToolShortName($package);
            if ('defaults'===$tool_name) {
                $base = $this->getToolRootPath();
            } else {
                $base = $this->getToolRootPath() . '/' . $tool_name;
            }
        } elseif ('bundle'===$type) {
            $base = $this->getBundleRootPath() . '/' . self::extractBundleShortName($package);
        } else {
            $base = parent::getPackageBasePath($package);
        }
        return $base;
    }
        
    /**
     * @return bool
     */
    public static function containsAssets(PackageInterface $package)
    {
        $extra = $package->getExtra();
        return !empty($extra) && array_key_exists('assets-dir', $extra);
    }

    /**
     * @return bool
     */
    public static function containsConfig(PackageInterface $package)
    {
        $extra = $package->getExtra();
        $config_file = self::guessConfigFiles($package);
        return (!empty($extra) && array_key_exists('config-dir', $extra)) || (!empty($config_file));
    }

    /**
     * @return bool
     */
    public static function containsLanguages(PackageInterface $package)
    {
        $extra = $package->getExtra();
        $ln_file = self::guessLanguageFiles($package);
        return (!empty($extra) && array_key_exists('i18n-dir', $extra)) || (!empty($ln_file));
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
            if ($this->containsAssets($package)) {
                $ok = parent::isInstalled($repo, $package);
                if (!$ok) return $ok;
            } else {
                $ok = LibraryInstaller::isInstalled($repo, $package);
                if (!$ok) return $ok;
            }
            if ($this->containsConfig($package)) {
                $ok = $this->isInstalledConfig($package);
            }
            if ($this->containsLanguages($package)) {
                $ok = $this->isInstalledLanguage($package);
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
            if ($this->containsAssets($package)) {
                parent::install($repo, $package);
            } else {
                LibraryInstaller::install($repo, $package);
            }
            if ($this->containsConfig($package)) {
                $this->installConfig($package);
            }
            if ($this->containsLanguages($package)) {
                $this->installLanguage($package);
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
            if ($this->containsConfig($initial)) {
                $this->removeConfig($initial);
            }
            if ($this->containsAssets($initial)) {
                parent::update($repo, $initial, $target);
            } else {
                LibraryInstaller::update($repo, $initial, $target);
            }
            if ($this->containsConfig($target)) {
                $this->installConfig($target);
            }
            if ($this->containsLanguages($target)) {
                $this->installLanguage($target);
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
            if ($this->containsConfig($package)) {
                $this->removeConfig($package);
            }
            if ($this->containsLanguages($package)) {
                $this->removeLanguage($package);
            }
            if ($this->containsAssets($package)) {
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

    public static function guessConfigDir(PackageInterface $package)
    {
        $extra = $package->getExtra();
        return isset($extra['config-dir']) ? $extra['config-dir'] : Config::get('config-dir');
    }

    public static function guessConfigVendorDir(PackageInterface $package)
    {
        $extra = $package->getExtra();
        return isset($extra['config-vendor-dir']) ? $extra['config-vendor-dir'] : Config::get('config-vendor-dir');
    }

    public static function guessConfigFiles(PackageInterface $package)
    {
        $extra = $package->getExtra();
        return isset($extra['carte-blanche-configs']) ? $extra['carte-blanche-configs'] : Config::get('carte-blanche-configs');
    }

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
        $package_config_files = array();
        $config_files = $this->guessConfigFiles($package);
        if (empty($config_files)) {
            return array();
        }
        if (!empty($config_files) && !is_array($config_files)) {
            $config_files = array($config_files);
        }
        $base_path = rtrim($this->getAppBasePath(), '/') . DIRECTORY_SEPARATOR;

        if (!empty($config_files)) {
            foreach ($config_files as $file) {
                $from_file = $base_path . $file;
                if (file_exists($from_file)) {
                    $package_config_files[] = $from_file;
                } else {
                    $this->io->write( 
                        sprintf('Skipping config file <info>%s</info> from package <info>%s</info>: file not found!', 
                            str_replace(dirname($this->config_dir) . '/', '', $from_file),
                            $package->getPrettyName()
                        )
                    );
                }
            }
        }

        return $package_config_files;
    }
    
    public function getPackageConfigFiles(PackageInterface $package)
    {
        $package_config_files = array();
        $config_files = $this->guessConfigFiles($package);
        $config_dir = $this->guessConfigDir($package);
        if (empty($config_dir) && empty($config_files)) {
            return array();
        }
        if (!empty($config_files) && !is_array($config_files)) {
            $config_files = array($config_files);
        }
        $base_path = rtrim($this->getPackageBasePath($package), '/') . DIRECTORY_SEPARATOR;

        if (!empty($config_files)) {
            foreach ($config_files as $file) {
                $from_file = $base_path . $file;
                if (file_exists($from_file)) {
                    $package_config_files[] = $from_file;
                } else {
                    $this->io->write( 
                        sprintf('Skipping config file <info>%s</info> from package <info>%s</info>: file not found!', 
                            str_replace(dirname($this->config_dir) . '/', '', $from_file),
                            $package->getPrettyName()
                        )
                    );
                }
            }
        }

        if (!empty($config_dir)) {
            $config_dir_path = $base_path . $config_dir;
            if (file_exists($config_dir_path)) {
                $it = new RecursiveDirectoryIterator($config_dir_path, RecursiveDirectoryIterator::SKIP_DOTS);
                $ri = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::SELF_FIRST);
                foreach ($ri as $file) {
                    if (!in_array($file->getPathname(), $package_config_files)) {
                        if ($file->isFile()) {
                            $package_config_files[] = $file->getPathname();
                        }
                    }
                }
            }
        }
        
        return $package_config_files;
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
// Language files
// ---------------------------

    public static function guessLanguageDir(PackageInterface $package)
    {
        $extra = $package->getExtra();
        return isset($extra['i18n-dir']) ? $extra['i18n-dir'] : Config::get('i18n-dir');
    }

    public static function guessLanguageVendorDir(PackageInterface $package)
    {
        $extra = $package->getExtra();
        return isset($extra['i18n-vendor-dir']) ? $extra['i18n-vendor-dir'] : Config::get('i18n-vendor-dir');
    }

    public static function guessLanguageFiles(PackageInterface $package)
    {
        $extra = $package->getExtra();
        return isset($extra['carte-blanche-i18n']) ? $extra['carte-blanche-i18n'] : Config::get('carte-blanche-i18n');
    }

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
        $package_i18n_files = array();
        $i18n_files = $this->guessLanguageFiles($package);
        if (empty($i18n_files)) {
            return array();
        }
        if (!empty($i18n_files) && !is_array($i18n_files)) {
            $i18n_files = array($i18n_files);
        }
        $base_path = rtrim($this->getAppBasePath(), '/') . DIRECTORY_SEPARATOR;

        if (!empty($i18n_files)) {
            foreach ($i18n_files as $file) {
                $from_file = $base_path . $file;
                if (file_exists($from_file)) {
                    $package_i18n_files[] = $from_file;
                } else {
                    $this->io->write( 
                        sprintf('Skipping language file <info>%s</info> from package <info>%s</info>: file not found!', 
                            str_replace(dirname($this->i18n_dir) . '/', '', $from_file),
                            $package->getPrettyName()
                        )
                    );
                }
            }
        }

        return $package_i18n_files;
    }
    
    public function getPackageLanguageFiles(PackageInterface $package)
    {
        $package_i18n_files = array();
        $i18n_files = $this->guessLanguageFiles($package);
        $i18n_dir = $this->guessLanguageDir($package);
        if (empty($i18n_dir) && empty($i18n_files)) {
            return array();
        }
        if (!empty($i18n_files) && !is_array($i18n_files)) {
            $i18n_files = array($i18n_files);
        }
        $base_path = rtrim($this->getPackageBasePath($package), '/') . DIRECTORY_SEPARATOR;

        if (!empty($i18n_files)) {
            foreach ($i18n_files as $file) {
                $from_file = $base_path . $file;
                if (file_exists($from_file)) {
                    $package_i18n_files[] = $from_file;
                } else {
                    $this->io->write( 
                        sprintf('Skipping language file <info>%s</info> from package <info>%s</info>: file not found!', 
                            str_replace(dirname($this->i18n_dir) . '/', '', $from_file),
                            $package->getPrettyName()
                        )
                    );
                }
            }
        }

        if (!empty($i18n_dir)) {
            $i18n_dir_path = $base_path . $i18n_dir;
            if (file_exists($i18n_dir_path)) {
                $it = new RecursiveDirectoryIterator($i18n_dir_path, RecursiveDirectoryIterator::SKIP_DOTS);
                $ri = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::SELF_FIRST);
                foreach ($ri as $file) {
                    if (!in_array($file->getPathname(), $package_i18n_files)) {
                        if ($file->isFile()) {
                            $package_i18n_files[] = $file->getPathname();
                        }
                    }
                }
            }
        }
        
        return $package_i18n_files;
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
// Bundles
// ---------------------------

    /**
     * Returns the root installation path for templates.
     *
     * @return string a path relative to the root of the composer.json that is being installed where the templates
     *     are stored.
     */
    public function getBundleRootPath()
    {
        return 'src/bundles';
    }

    /**
     * Determines the install path for templates,
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
        if (self::extractBundlePrefix($package) != Config::get('bundle-name')) {
            throw new \InvalidArgumentException(
                sprintf('Unable to install bundle, CarteBlanche bundles should always start their package name with "%s"',
                    Config::get('bundle-name'))
            );
        }

        return $this->getBundleRootPath() . '/' . self::extractBundleShortName($package);
    }

    /**
     * Extract the first 21 characters ("carte-blanche/bundle-") of the package name; which is expected to be the prefix.
     *
     * @param PackageInterface $package
     * @return string
     */
    public static function extractBundlePrefix(PackageInterface $package)
    {
        return substr($package->getPrettyName(), 0, strlen(Config::get('bundle-name')));
    }

    /**
     * Extract the everything after the first 21 characters of the package name; which is expected to be the short name.
     *
     * @param PackageInterface $package
     * @return string
     */
    public static function extractBundleShortName(PackageInterface $package)
    {
        return substr($package->getPrettyName(), strlen(Config::get('bundle-name')));
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
        if (self::extractToolPrefix($package) != Config::get('tool-name')) {
            throw new \InvalidArgumentException(
                sprintf('Unable to install tool, CarteBlanche tools should always start their package name with "%s"',
                    Config::get('tool-name'))
            );
        }
        $tool_name = self::extractToolShortName($package);
        if ('all'===$tool_name) {
            return $this->getToolRootPath();
        } else {
            return $this->getToolRootPath() . '/' . $tool_name;
        }
    }

    /**
     * Extract the first 19 characters ("carte-blanche/tool-") of the package name; which is expected to be the prefix.
     *
     * @param PackageInterface $package
     * @return string
     */
    public static function extractToolPrefix(PackageInterface $package)
    {
        return substr($package->getPrettyName(), 0, strlen(Config::get('tool-name')));
    }

    /**
     * Extract the everything after the first 19 characters of the package name; which is expected to be the short name.
     *
     * @param PackageInterface $package
     * @return string
     */
    public static function extractToolShortName(PackageInterface $package)
    {
        return substr($package->getPrettyName(), strlen(Config::get('tool-name')));
    }

}

// Endfile