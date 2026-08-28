<?php
/**
 * Login As Customer - One-Click Support Access
 *
 * @author    MEG Venture <info@megventure.com>
 * @copyright 2019-2026 MEG Venture
 * @license   https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'loginascustomer/classes/LoginAsCustomerToken.php';

class LoginascustomerLoginModuleFrontController extends ModuleFrontController
{
    public function __construct()
    {
        parent::__construct();
        // Force HTTPS only when the shop actually has SSL enabled; forcing it on
        // a plain-HTTP shop would break the redirect (see the hepsiburada note).
        $this->ssl = (bool) Configuration::get('PS_SSL_ENABLED');
    }

    public function init()
    {
        $this->display_column_left = false;
        $this->display_column_right = false;

        parent::init();
    }

    public function initContent()
    {
        parent::initContent();

        if (!Module::isInstalled('loginascustomer') || !Module::isEnabled('loginascustomer')) {
            Tools::redirect('index.php');
        }

        $idCustomer = (int) Tools::getValue('id_customer');
        $idEmployee = (int) Tools::getValue('id_employee');
        $token = Tools::getValue('lac_token');

        if ($idCustomer <= 0 || !LoginAsCustomerToken::isValid($idCustomer, $idEmployee, $token)) {
            Tools::redirect('index.php');
        }

        $customer = new Customer($idCustomer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php');
        }

        $customer->logged = 1;

        // Context::updateCustomer() is PrestaShop's own login mechanism on every
        // version 1.6 -> 9: it sets the cookie, associates the cart and, on the
        // versions that need it, registers the CustomerSession. Using it means
        // this module logs in exactly the way core does instead of hand-poking
        // cookie fields (which is what the 1.x module did and what broke on the
        // customer-session versions).
        if (method_exists($this->context, 'updateCustomer')) {
            $this->context->updateCustomer($customer);
        } else {
            $this->context->cookie->id_customer = (int) $customer->id;
            $this->context->cookie->customer_lastname = $customer->lastname;
            $this->context->cookie->customer_firstname = $customer->firstname;
            $this->context->cookie->passwd = $customer->passwd;
            $this->context->cookie->email = $customer->email;
            $this->context->cookie->is_guest = $customer->isGuest();
            $this->context->cookie->logged = 1;
            $this->context->customer = $customer;
        }

        if (isset($this->context->cookie)) {
            $this->context->cookie->write();
        }

        // Audit trail: who impersonated whom.
        if (class_exists('PrestaShopLogger')) {
            PrestaShopLogger::addLog(
                sprintf(
                    'Login As Customer: employee #%d connected as customer #%d (%s)',
                    $idEmployee,
                    $idCustomer,
                    $customer->email
                ),
                1,
                null,
                'Customer',
                $idCustomer,
                true,
                $idEmployee > 0 ? $idEmployee : null
            );
        }

        $landing = Configuration::get('LOGINASCUSTOMER_LANDING') === 'index'
            ? 'index.php'
            : 'index.php?controller=my-account';

        Tools::redirect($landing);
    }
}
