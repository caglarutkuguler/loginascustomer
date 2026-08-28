<?php
/**
 * Login As Customer - One-Click Support Access
 *
 * @author    MEG Venture <info@megventure.com>
 * @copyright 2019-2026 MEG Venture
 * @license   https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *
 * Guards the review-request line. Runs on plain PHP: `php tests/ReviewNudgeTest.php`.
 *
 * The line must stay silent for the first 21 days (including on a fresh install),
 * go away forever on a click or a dismiss, give up after three unanswered
 * displays, and uninstall must remove exactly its three keys and nothing else.
 */

define('_PS_VERSION_', '8.1.0');
define('_PS_MODULE_DIR_', dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR);
define('_DB_PREFIX_', 'ps_');
define('_MYSQL_ENGINE_', 'InnoDB');
define('_COOKIE_KEY_', 'unit-test-cookie-key');

class Configuration
{
    public static $store = array();
    public static function get($k, $l = null, $s = null, $sh = null, $default = false)
    {
        return array_key_exists($k, self::$store) ? self::$store[$k] : false;
    }
    public static function updateValue($k, $v)
    {
        self::$store[$k] = is_bool($v) ? ($v ? '1' : '0') : (string) $v;
        return true;
    }
    public static function updateGlobalValue($k, $v)
    {
        return self::updateValue($k, $v);
    }
    public static function deleteByName($k)
    {
        unset(self::$store[$k]);
        return true;
    }
}

class Tools
{
    public static $values = array();
    public static $redirectedTo = null;
    public static function getValue($k, $d = false)
    {
        return array_key_exists($k, self::$values) ? self::$values[$k] : $d;
    }
    public static function isSubmit($k)
    {
        return array_key_exists($k, self::$values);
    }
    public static function redirect($url)
    {
        self::$redirectedTo = $url;
    }
}

class Validate
{
    public static function isLoadedObject($o) { return is_object($o) && !empty($o->id); }
}

abstract class Module
{
    public $name, $tab, $version, $author, $need_instance, $module_key, $bootstrap,
           $displayName, $description, $confirmUninstall, $ps_versions_compliancy,
           $context, $_path, $identifier, $active, $id, $warning;
    public function __construct() {}
    public function l($s, $specific = false) { return $s; }
    public function registerHook($h) { return true; }
    public function unregisterHook($h) { return true; }
    public function display($f, $t) { return ''; }
    public function displayConfirmation($s) { return ''; }
    public function install() { return true; }
    public function uninstall() { return true; }
}

require dirname(__DIR__) . '/loginascustomer.php';

/** What render()/handleRequest() receive: the module, for l() and the BO language. */
class FakeNudgeModule
{
    public $context;
    public function __construct($iso = 'tr')
    {
        $this->context = new stdClass();
        $this->context->language = new stdClass();
        $this->context->language->iso_code = $iso;
    }
    public function l($s, $specific = false) { return $s; }
}

$fail = 0;
function ok($cond, $label)
{
    global $fail;
    if ($cond) { echo "  ok   $label\n"; } else { echo "  FAIL $label\n"; $fail++; }
}

const DAY = 86400;
$configureUrl = 'index.php?controller=AdminModules&configure=loginascustomer';
$fake = new FakeNudgeModule('tr');

echo "1) Keys carry the module prefix\n";
$keys = MegVentureReviewNudge::configurationKeys();
ok(count($keys) === 3, 'exactly three configuration keys');
$unprefixed = array_filter($keys, function ($k) { return strpos($k, 'LOGINASCUSTOMER_') !== 0; });
ok($unprefixed === array(), 'all three carry the LOGINASCUSTOMER_ prefix (' . implode(', ', $keys) . ')');

echo "\n2) Fresh install: hidden on day 0\n";
Configuration::$store = array();
$m = new LoginAsCustomer();
ok($m->install(), 'install() succeeds');
$installedAt = (int) Configuration::get('LOGINASCUSTOMER_REVIEW_INSTALLED_AT');
ok($installedAt > 0 && abs(time() - $installedAt) < 5, 'install() wrote the installed-at timestamp');
ok(!MegVentureReviewNudge::shouldDisplay(), 'hidden on the day of install');
ok(MegVentureReviewNudge::render($fake, $configureUrl) === '', 'render() returns nothing on day 0');

echo "\n3) Quiet period: day 20 hidden, day 21 shown\n";
Configuration::$store['LOGINASCUSTOMER_REVIEW_INSTALLED_AT'] = (string) (time() - 20 * DAY);
ok(!MegVentureReviewNudge::shouldDisplay(), 'day 20: hidden');
Configuration::$store['LOGINASCUSTOMER_REVIEW_INSTALLED_AT'] = (string) (time() - 21 * DAY);
ok(MegVentureReviewNudge::shouldDisplay(), 'day 21: shown');

echo "\n4) The line itself\n";
$html = MegVentureReviewNudge::render($fake, $configureUrl);
ok(strpos($html, 'Happy with this module?') !== false, 'render() contains the request text');
ok(strpos($html, 'loginascustomer_review_go=1') !== false, 'render() contains the review link');
ok(strpos($html, 'loginascustomer_review_dismiss=1') !== false, 'render() contains the dismiss link');
ok(strpos($html, 'megventure.com') === false, 'no direct external URL in the HTML (click routes through the configure page)');
ok((int) Configuration::get('LOGINASCUSTOMER_REVIEW_DISPLAYS') === 1, 'the view was counted');

echo "\n5) Three unanswered displays, hidden on the fourth\n";
Configuration::$store['LOGINASCUSTOMER_REVIEW_DISPLAYS'] = '0';
ok(MegVentureReviewNudge::render($fake, $configureUrl) !== '', 'display 1 shown');
ok(MegVentureReviewNudge::render($fake, $configureUrl) !== '', 'display 2 shown');
ok(MegVentureReviewNudge::render($fake, $configureUrl) !== '', 'display 3 shown');
ok(MegVentureReviewNudge::render($fake, $configureUrl) === '', 'display 4: given up');
ok((int) Configuration::get('LOGINASCUSTOMER_REVIEW_DISPLAYS') === 3, 'counter stopped at 3');

echo "\n6) A form-POST re-render is shown but not counted\n";
Configuration::$store['LOGINASCUSTOMER_REVIEW_DISPLAYS'] = '1';
$_POST['submitLoginAsCustomer'] = '1';
ok(MegVentureReviewNudge::render($fake, $configureUrl) !== '', 'still shown while saving settings');
ok((int) Configuration::get('LOGINASCUSTOMER_REVIEW_DISPLAYS') === 1, 'but the save re-render did not burn a display');
$_POST = array();

echo "\n7) Dismissed: hidden forever\n";
Configuration::$store['LOGINASCUSTOMER_REVIEW_DISPLAYS'] = '0';
Tools::$values = array('loginascustomer_review_dismiss' => '1');
$banner = MegVentureReviewNudge::handleRequest($fake);
Tools::$values = array();
ok(strpos($banner, 'we will not ask again') !== false, 'dismiss answers with a confirmation');
ok((int) Configuration::get('LOGINASCUSTOMER_REVIEW_DISMISSED') === 1, 'dismissed flag written');
ok(!MegVentureReviewNudge::shouldDisplay(), 'hidden right after dismissing');

echo "\n8) Review link clicked: recorded, redirected, hidden forever\n";
Configuration::$store = array('LOGINASCUSTOMER_REVIEW_INSTALLED_AT' => (string) (time() - 30 * DAY));
Tools::$redirectedTo = null;
Tools::$values = array('loginascustomer_review_go' => '1');
MegVentureReviewNudge::handleRequest($fake);
Tools::$values = array();
ok(Tools::$redirectedTo === 'https://megventure.com/tr/testimonials/write?id_product=95',
   'redirected to the review form in the BO language (' . Tools::$redirectedTo . ')');
ok(!MegVentureReviewNudge::shouldDisplay(), 'never shown again after the click');
ok(MegVentureReviewNudge::reviewUrl('xx') === 'https://megventure.com/en/testimonials/write?id_product=95',
   'a language megventure.com does not serve falls back to en');

echo "\n9) ensureInstalledAt(): no timestamp -> gets one, not shown immediately; existing kept\n";
Configuration::$store = array('LOGINASCUSTOMER_ON_ORDER' => '1');
ok(MegVentureReviewNudge::ensureInstalledAt() === true, 'ensureInstalledAt() succeeds');
$stamp = (int) Configuration::get('LOGINASCUSTOMER_REVIEW_INSTALLED_AT');
ok($stamp > 0 && abs(time() - $stamp) < 5, 'a missing timestamp was written with the current time');
ok(!MegVentureReviewNudge::shouldDisplay(), 'not shown immediately after being stamped');
Configuration::$store['LOGINASCUSTOMER_REVIEW_INSTALLED_AT'] = '12345';
MegVentureReviewNudge::ensureInstalledAt();
ok(Configuration::get('LOGINASCUSTOMER_REVIEW_INSTALLED_AT') === '12345', 'an existing timestamp is never overwritten');

echo "\n10) Uninstall removes the three review keys and touches nothing foreign\n";
Configuration::$store = array(
    'LOGINASCUSTOMER_REVIEW_INSTALLED_AT' => '12345',
    'LOGINASCUSTOMER_REVIEW_DISMISSED' => '1',
    'LOGINASCUSTOMER_REVIEW_DISPLAYS' => '2',
    'LOGINASCUSTOMER_ON_ORDER' => '1',
    'theme' => 'another-modules-value',
    'MEGTESTIMONIAL_WHO' => 'another-modules-value',
);
MegVentureReviewNudge::onUninstall();
$reviewLeft = array_filter(array_keys(Configuration::$store), function ($k) { return strpos($k, 'LOGINASCUSTOMER_REVIEW_') === 0; });
ok($reviewLeft === array(), 'onUninstall() removed all three review keys');
ok(Configuration::get('LOGINASCUSTOMER_ON_ORDER') === '1', 'a non-review module key was left for the module');
ok(Configuration::get('theme') === 'another-modules-value', 'a bare foreign key was not touched');
ok(Configuration::get('MEGTESTIMONIAL_WHO') === 'another-modules-value', 'another module\'s prefixed key was not touched');
ok($m->uninstall(), 'full module uninstall() succeeds');
$prefixedLeft = array_filter(array_keys(Configuration::$store), function ($k) { return strpos($k, 'LOGINASCUSTOMER_') === 0; });
ok($prefixedLeft === array(), 'module uninstall removed every LOGINASCUSTOMER_ key');

echo "\n" . ($fail === 0 ? "OK - all passed\n" : "$fail test(s) FAILED\n");
exit($fail === 0 ? 0 : 1);
