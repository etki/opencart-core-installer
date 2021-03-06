<?php
namespace Etki\Composer\Installers\Opencart;

use Symfony\Component\Filesystem\Filesystem;

/**
 * This class is responsible for all file jungling.
 *
 * @version Release: 0.1.0
 * @since   0.1.1
 * @package Etki\Composer\Installers\Opencart
 * @author  Fike Etki <etki@etki.name>
 */
class FileJunglist
{
    /**
     * Filesystem nodes that have to be chmodded, according to Opencart
     * installation notes
     *
     * @type string[]
     * @since 0.1.1
     */
    protected $chmodNodes = array(
        'download',
        'system/cache',
        'system/logs',
        'system/download',
        'image',
        'image/cache',
        'image/catalog',
        'config.php',
        'admin/config.php',
        'config-dist.php',
        'admin/config-dist.php',
    );
    /**
     * List of configuration files
     *
     * @type string[]
     * @since 0.1.1
     */
    protected $modifiableFiles = array(
        'config.php',
        'admin/config.php',
        'image',
        'system/logs',
        'system/download',
    );

    /**
     * Saves config files during install.
     *
     * @param string $installPath Opencart install path.
     *
     * @return void
     * @since 0.1.0
     */
    public function saveModifiedFiles($installPath)
    {
        DebugPrinter::log('Saving modified items');
        $tmpRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR;
        $installPath = trim($installPath, '\\/') . DIRECTORY_SEPARATOR;
        $fsm = new Filesystem;
        DebugPrinter::log(
            'Items to search: %s',
            implode(', ', $this->modifiableFiles)
        );
        foreach ($this->modifiableFiles as $file) {
            $source = $installPath . $file;
            $target = $tmpRoot . md5($source);
            if (!$fsm->exists($source)) {
                DebugPrinter::log('Item `%s` is missing, skipping it', $source);
                continue;
            }
            $args = array($source, $target,);
            DebugPrinter::log('Saving `%s` to `%s`', $args);
            if (is_dir($source)) {
                $fsm->mirror($source, $target);
            } else {
                $fsm->copy($source, $target);
            }
        }
        DebugPrinter::log('Finished saving modified items');
    }

    /**
     * Restores previously saved config files.
     *
     * @param string $installPath Opencart installation path.
     *
     * @return void
     * @since 0.1.1
     */
    public function restoreModifiedFiles($installPath)
    {
        DebugPrinter::log('Restoring modified items');
        $tmpRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR;
        $installPath = trim($installPath, '\\/') . DIRECTORY_SEPARATOR;
        $fsm = new Filesystem;
        DebugPrinter::log(
            'Items to search: %s',
            implode(', ', $this->modifiableFiles)
        );
        foreach ($this->modifiableFiles as $file) {
            $target = $installPath . $file;
            $source = $tmpRoot . md5($target);
            if (!$fsm->exists($source)) {
                DebugPrinter::log('Item `%s` is missing, skipping it', $source);
                continue;
            }
            $args = array($source, $target,);
            DebugPrinter::log('Restoring `%s` to `%s`', $args);
            if ($fsm->exists($target)) {
                $fsm->remove($target);
            }
            $fsm->rename($source, $target, true);
        }
        DebugPrinter::log('Finished restoring modified items');
    }

    /**
     * Sets Opencart folder permissions as required in manual.
     *
     * @param string $installPath Opencart install path.
     * @param int    $basePerms   Base permissions. Please note that all
     *                            directories permissions will be masked by 111
     *                            (this will set all three ugo executive bits).
     *
     * @return void
     * @since 0.1.1
     */
    protected function setPermissions($installPath, $basePerms = 0644)
    {
        $fsm = new Filesystem;
        foreach ($this->chmodNodes as $fsNode) {
            $path = $installPath . DIRECTORY_SEPARATOR . $fsNode;
            $isFile = is_file($path);
            $isDir = is_dir($path);
            if (!$isFile && !$isDir) {
                DebugPrinter::log('Filesystem node %s not found', $path);
                continue;
            }
            $perms = $basePerms;
            if ($isDir) {
                $perms |= 0111;
            }
            $args = array($path, decoct($perms),);
            DebugPrinter::log('Chmodding `%s` to `%s`', $args);
            $fsm->chmod($path, $perms);
        }
    }

    /**
     * Moves installed files out of `upload` dir.
     *
     * @param string $installPath Opencart installation path.
     *
     * @return void
     * @since 0.1.0
     */
    public function rotateInstalledFiles($installPath)
    {
        $fsm = new Filesystem;
        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('oci-');
        DebugPrinter::log('Rotating files using `%s` dir', $tempDir);
        // unzipped contents may or may not contain `upload.*` directory,
        // which holds actual opencart contents.
        $dirs = glob($installPath . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
        foreach ($dirs as $key => $dir) {
            if ($dir[0] === '.') {
                unset($dirs[$key]);
            }
        }
        if (sizeof($dirs) === 1) {
            $subDirectory = $tempDir . DIRECTORY_SEPARATOR .
                dirname(reset($dirs));
            $fsm->rename($installPath, $tempDir);
            $fsm->rename($subDirectory, $installPath);
            $fsm->remove($tempDir);
        }
    }

    /**
     * Copies configuration files from their dists as specified by installation
     * notes.
     *
     * @param string $installPath Opencart installation path.
     *
     * @return void
     * @since 0.1.0
     */
    public function copyConfigFiles($installPath)
    {
        $filesystem = new Filesystem;
        $configFiles = array('/config', '/admin/config',);
        foreach ($configFiles as $configFile) {
            $source = $installPath . $configFile . '-dist.php';
            $target = $installPath . $configFile . '.php';
            if ($filesystem->exists($source)) {
                $filesystem->copy($source, $target);
            } else {
                DebugPrinter::log(
                    'File `%s` doesn\'t exist, though i am sure it should',
                    $source
                );
            }
        }
    }
}
