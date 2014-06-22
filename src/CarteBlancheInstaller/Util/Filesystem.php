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

namespace CarteBlancheInstaller\Util;

use AssetsManager\Composer\Util\Filesystem as OriginalFilesystem;

/**
 * This class just completes the default `AssetsManager\Composer\Util\Filesystem` with a `copyFile` method
 */
class Filesystem
    extends OriginalFilesystem
{

    /**
     * Copy a set of files in a target directory
     * @param array $sources
     * @param string $target
     * @param bool $force
     */
    public function copyFiles($sources, $target, $force = false)
    {
        if (!file_exists($target)) {
            mkdir($target, 0777, true);
        }
        if (!is_array($sources)) {
            $sources = array($sources);
        }

        foreach ($sources as $file) {
            $targetPath = $target . DIRECTORY_SEPARATOR . basename($file);
            if (!file_exists($targetPath) || true===$force) {
                copy($file, $targetPath);
            }
        }
    }

}

// Endfile
