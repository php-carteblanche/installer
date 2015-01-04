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

namespace CarteBlancheInstaller\Composer;

use \Composer\Composer;
use \Composer\IO\IOInterface;
use \Composer\Script\Event;
use \Composer\Plugin\PluginInterface;
use \Composer\Plugin\PluginEvents;
use \Composer\EventDispatcher\EventSubscriberInterface;
use \Composer\Plugin\CommandEvent;
use \Composer\Plugin\PreFileDownloadEvent;
use \CarteBlancheInstaller\CarteBlancheInstaller;
use \CarteBlancheInstaller\Composer\Autoload\DumpAutoloadEventHandler;

class CarteBlancheInstallerPlugin
    implements PluginInterface, EventSubscriberInterface
{

    /**
     * @var \AssetsManager\Composer\Dispatch
     */
    protected $__installer;

    /**
     * Add the `\AssetsManager\Composer\Dispatch` installer
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->__installer = new CarteBlancheInstaller($io, $composer);
        $composer->getInstallationManager()->addInstaller($this->__installer);
    }

    /**
     * Composer events plugin's subscription
     */
    public static function getSubscribedEvents()
    {
        return array(
            PluginEvents::PRE_FILE_DOWNLOAD => array(
                array('onPreFileDownload', 0)
            ),
            PluginEvents::COMMAND => array(
                array('onCommand', 0)
            ),
        );
    }

    /**
     * Pre file download event dispatcher
     *
     * @param \Composer\Plugin\PreFileDownloadEvent $event
     */
    public function onPreFileDownload(PreFileDownloadEvent $event)
    {
/*
echo 'PRE FILE DOWNLOAD';
var_export(func_get_args());
*/
    }

    /**
     * Command event dispatcher
     *
     * @param \Composer\Plugin\CommandEvent $event
     */
    public function onCommand(CommandEvent $event)
    {
        switch ($event->getCommandName()) {
            case 'dump-autoload':
                $_this = new DumpAutoloadEventHandler(
                    $this->__installer->getComposer()->getPackage(),
                    $this->__installer->getComposer()
                );
                break;
            default: break;
        }
    }

}

// Endfile