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

namespace CarteBlancheInstaller\Composer;

use \Composer\Composer,
    \Composer\IO\IOInterface,
    \Composer\Script\Event,
    \Composer\Plugin\PluginInterface,
    \Composer\Plugin\PluginEvents,
    \Composer\EventDispatcher\EventSubscriberInterface,
    \Composer\Plugin\CommandEvent,
    \Composer\Plugin\PreFileDownloadEvent;

use \CarteBlancheInstaller\CarteBlancheInstaller;

class CarteBlancheInstallerPlugin
    implements PluginInterface, EventSubscriberInterface
{

    /**
     * @var object \AssetsManager\Composer\Dispatch
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
     * @param object \Composer\Plugin\PreFileDownloadEvent
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
     * @param object \Composer\Plugin\CommandEvent
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