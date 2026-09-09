<?php
/**
 * @author    MEG Venture <info@megventure.com>
 * @copyright 2007-2026 MEG Venture
 * @license   All rights reserved
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * 1.0.1 -> 1.0.2: back-office icons move to the core's own icon set.
 *
 * Templates only — nothing in the database changes. They are re-read from disk,
 * so all this does is drop the caches that could still be serving the old markup
 * on a shop configured never to recompile.
 *
 * @param Module $module
 *
 * @return bool
 */
function upgrade_module_1_0_2($module)
{
    if (method_exists('Tools', 'clearSmartyCache')) {
        Tools::clearSmartyCache();
    }

    if (method_exists('Media', 'clearCache')) {
        Media::clearCache();
    }

    return true;
}
