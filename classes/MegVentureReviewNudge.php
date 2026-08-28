<?php
/**
 * @author    MEG Venture <info@megventure.com>
 * @copyright 2019-2026 MEG Venture
 * @license   https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

/**
 * ============================================================================
 *  MegVentureReviewNudge — drop-in review-request line for the configuration
 *  page of MEG Venture modules.
 *
 *  A single line asking the merchant to leave a review, shown only on this
 *  module's own configure page, starting DELAY_DAYS after install. It goes
 *  away forever the moment the merchant clicks the review link, clicks
 *  "No thanks", or has simply seen it MAX_DISPLAYS times.
 *
 *  It makes no outbound request of any kind: the line is plain HTML, the
 *  review link routes through the configure page (so the click can be
 *  remembered without JavaScript) and then sends the merchant's own browser
 *  to the review form. Nothing is stored beyond the three configuration
 *  values named by configurationKeys().
 *  -----------------------------------------------------------------------
 */
class MegVentureReviewNudge
{
    /* Everything module-specific lives in these constants. */
    const CONFIG_PREFIX = 'LOGINASCUSTOMER_';
    const REVIEW_URL = 'https://megventure.com/{lang}/testimonials/write?id_product=LAC_PRODUCT_ID';
    const DELAY_DAYS = 21;
    const MAX_DISPLAYS = 3;

    /** Languages megventure.com actually serves; anything else falls back to en. */
    const REVIEW_URL_LANGS = array('en', 'tr', 'fr', 'es', 'de', 'it', 'nl', 'pl', 'pt');

    /**
     * The three configuration values this class owns — and the only thing it
     * ever stores. All carry the module's own prefix per the house rules.
     *
     * @return string[]
     */
    public static function configurationKeys()
    {
        return array(
            self::CONFIG_PREFIX . 'REVIEW_INSTALLED_AT',
            self::CONFIG_PREFIX . 'REVIEW_DISMISSED',
            self::CONFIG_PREFIX . 'REVIEW_DISPLAYS',
        );
    }

    /**
     * Called from the module's install(). A module reset (uninstall +
     * install) lands here too, so a reset restarts the quiet period.
     *
     * @return bool
     */
    public static function onInstall()
    {
        return Configuration::updateGlobalValue(self::CONFIG_PREFIX . 'REVIEW_INSTALLED_AT', time());
    }

    /**
     * Called from the version upgrade script. Existing installations have no
     * install date, so their clock starts at the upgrade. Safe to run twice:
     * an existing value is kept.
     *
     * @return bool
     */
    public static function ensureInstalledAt()
    {
        if ((int) Configuration::get(self::CONFIG_PREFIX . 'REVIEW_INSTALLED_AT') <= 0) {
            return Configuration::updateGlobalValue(self::CONFIG_PREFIX . 'REVIEW_INSTALLED_AT', time());
        }

        return true;
    }

    /**
     * Called from the module's uninstall(). Deletes exactly the keys in
     * configurationKeys() and nothing else.
     *
     * @return bool
     */
    public static function onUninstall()
    {
        foreach (self::configurationKeys() as $key) {
            Configuration::deleteByName($key);
        }

        return true;
    }

    /**
     * @return bool
     */
    public static function shouldDisplay()
    {
        if ((int) Configuration::get(self::CONFIG_PREFIX . 'REVIEW_DISMISSED')) {
            return false;
        }

        if ((int) Configuration::get(self::CONFIG_PREFIX . 'REVIEW_DISPLAYS') >= self::MAX_DISPLAYS) {
            return false;
        }

        $installedAt = (int) Configuration::get(self::CONFIG_PREFIX . 'REVIEW_INSTALLED_AT');
        if ($installedAt <= 0) {
            // No usable install date (interrupted upgrade, hand-edited row).
            // Start the clock now rather than showing the line immediately.
            Configuration::updateGlobalValue(self::CONFIG_PREFIX . 'REVIEW_INSTALLED_AT', time());

            return false;
        }

        return (time() - $installedAt) >= self::DELAY_DAYS * 86400;
    }

    /**
     * Handle the two links the line renders. Both arrive on the module's own
     * configure page URL, behind the same guard as every other write this
     * module accepts (an authenticated back-office employee on AdminModules).
     *
     * @param Module $module
     *
     * @return string Confirmation HTML after a dismiss, '' otherwise
     */
    public static function handleRequest($module)
    {
        $prefix = strtolower(self::CONFIG_PREFIX);

        if (Tools::getValue($prefix . 'review_go')) {
            self::dismiss();

            $iso = isset($module->context->language->iso_code) ? $module->context->language->iso_code : 'en';
            Tools::redirect(self::reviewUrl($iso));

            return '';
        }

        if (Tools::getValue($prefix . 'review_dismiss')) {
            self::dismiss();

            return '<div class="alert alert-success">'
                . $module->l('Okay, we will not ask again.', 'megventurereviewnudge')
                . '</div>';
        }

        return '';
    }

    /**
     * The line itself, or '' when it has nothing to say.
     *
     * @param Module $module       Supplies l() so the strings are translatable
     * @param string $configureUrl The module's own configure page URL
     *
     * @return string
     */
    public static function render($module, $configureUrl)
    {
        if (!self::shouldDisplay()) {
            return '';
        }

        // Only a plain page view counts toward MAX_DISPLAYS. Every action on
        // this page re-renders it via POST, so counting re-renders would let
        // one save-heavy session burn the whole allowance in a minute.
        if (empty($_POST)) {
            self::recordDisplay();
        }

        $prefix = strtolower(self::CONFIG_PREFIX);
        $sep = strpos($configureUrl, '?') === false ? '?' : '&';
        $goUrl = htmlspecialchars($configureUrl . $sep . $prefix . 'review_go=1', ENT_QUOTES, 'UTF-8');
        $dismissUrl = htmlspecialchars($configureUrl . $sep . $prefix . 'review_dismiss=1', ENT_QUOTES, 'UTF-8');

        return '<div class="alert alert-info megventure-review-nudge">'
            . $module->l('Happy with this module? A short review helps other merchants find it.', 'megventurereviewnudge')
            . ' <a href="' . $goUrl . '" target="_blank" rel="noopener">'
            . $module->l('Leave a review', 'megventurereviewnudge')
            . '</a> &middot; <a href="' . $dismissUrl . '">'
            . $module->l('No thanks', 'megventurereviewnudge')
            . '</a></div>';
    }

    /**
     * The review form for this module, in the given back-office language when
     * megventure.com serves it, in English otherwise.
     *
     * @param string $isoCode
     *
     * @return string
     */
    public static function reviewUrl($isoCode)
    {
        $iso = strtolower((string) $isoCode);
        if (!in_array($iso, self::REVIEW_URL_LANGS, true)) {
            $iso = 'en';
        }

        return str_replace('{lang}', $iso, self::REVIEW_URL);
    }

    private static function dismiss()
    {
        Configuration::updateGlobalValue(self::CONFIG_PREFIX . 'REVIEW_DISMISSED', 1);
    }

    private static function recordDisplay()
    {
        Configuration::updateGlobalValue(
            self::CONFIG_PREFIX . 'REVIEW_DISPLAYS',
            (int) Configuration::get(self::CONFIG_PREFIX . 'REVIEW_DISPLAYS') + 1
        );
    }
}
