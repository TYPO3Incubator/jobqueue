<?php
namespace T3DD\Build;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

if (getenv('TYPO3_PATH_WEB')) {
    putenv('TYPO3_PATH_WEB='. realpath(__DIR__ . '/' . ltrim(getenv('TYPO3_PATH_WEB'), '/')));
}

$symlinks = [
    'Classes',
    'Configuration',
    'Resources',
    'Tests',
    'composer.json',
    'ext_emconf.php',
    'ext_icon.png',
    'ext_localconf.php',
    'ext_tables.php',
    'ext_tables.sql'
];

$baseDir = dirname(__DIR__). '/';
$baseTarget = dirname(__DIR__).'/web/typo3conf/ext/jobqueue/';

// first check if the ext directory exists
if(!is_dir($baseTarget)) {
    mkdir($baseTarget);
}

// symlink files and folders from this repo into installation
foreach($symlinks as $fileOrFolder) {
    $target = $baseDir . $fileOrFolder;
    $link = $baseTarget . $fileOrFolder;
    if(!is_link($link)) {
        symlink($target, $link);
    }
}


require dirname(__DIR__) . '/../vendor/typo3/cms/typo3/sysext/core/Build/FunctionalTestsBootstrap.php';