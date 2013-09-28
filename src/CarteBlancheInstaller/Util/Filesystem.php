<?php
/**
 * Template Engine - PHP framework package
 * Copyleft (c) 2013 Pierre Cassat and contributors
 * <www.ateliers-pierrot.fr> - <contact@ateliers-pierrot.fr>
 * License GPL-3.0 <http://www.opensource.org/licenses/gpl-3.0.html>
 * Sources <https://github.com/atelierspierrot/templatengine>
 */

namespace CarteBlancheInstaller\Util;

use AssetsManager\Composer\Util\Filesystem as OriginalFilesystem;

/**
 * This class just completes the default `AssetsManager\Composer\Util\Filesystem` with a `copyFile` method
 */
class Filesystem extends OriginalFilesystem
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
