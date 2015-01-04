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

namespace CarteBlancheInstaller\Composer\Autoload;

use \Composer\Composer;
use \Composer\Package\PackageInterface;
use \AssetsManager\Composer\Autoload\DumpAutoloadEventHandler as BaseDumpAutoloadEventHandler;

class DumpAutoloadEventHandler
    extends BaseDumpAutoloadEventHandler
{

    /**
     * @param \Composer\Package\PackageInterface $package
     * @param \Composer\Composer $composer
     */
    public function __construct(PackageInterface $package, Composer $composer)
    {
        parent::__construct($package, $composer);
    }

}
