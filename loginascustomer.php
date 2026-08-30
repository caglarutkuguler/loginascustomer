<?php
/**
 * Login As Customer - One-Click Support Access
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/afl-3.0.php
 *
 * @author    MEG Venture <info@megventure.com>
 * @copyright 2019-2026 MEG Venture
 * @license   https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/classes/LoginAsCustomerToken.php';
require_once dirname(__FILE__) . '/classes/MegVentureReviewNudge.php';

class LoginAsCustomer extends Module
{
    public function __construct()
    {
        $this->name = 'loginascustomer';
        $this->tab = 'administration';
        $this->version = '1.0.1';
        $this->author = 'MEG Venture';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);

        parent::__construct();

        $this->displayName = $this->l('Login As Customer - One-Click Support Access');
        $this->description = $this->l('Log in to your storefront as any customer with one click, straight from the order or customer page, to see exactly what they see and give faster support.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall Login As Customer?');
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('displayAdminCustomers')
            && $this->registerHook('displayAdminOrder')
            && $this->registerHook('actionGetAdminOrderButtons')
            && Configuration::updateValue('LOGINASCUSTOMER_LANDING', 'my-account')
            && Configuration::updateValue('LOGINASCUSTOMER_TTL', 60)
            && Configuration::updateValue('LOGINASCUSTOMER_ON_CUSTOMER', 1)
            && Configuration::updateValue('LOGINASCUSTOMER_ON_ORDER', 1)
            && Configuration::updateValue('LOGINASCUSTOMER_NEWTAB', 1)
            && MegVentureReviewNudge::onInstall();
    }

    public function uninstall()
    {
        MegVentureReviewNudge::onUninstall();

        foreach (array(
            'LOGINASCUSTOMER_LANDING',
            'LOGINASCUSTOMER_TTL',
            'LOGINASCUSTOMER_ON_CUSTOMER',
            'LOGINASCUSTOMER_ON_ORDER',
            'LOGINASCUSTOMER_NEWTAB',
        ) as $key) {
            Configuration::deleteByName($key);
        }

        return parent::uninstall();
    }

    /**
     * Configuration page: settings form, a short how-it-works panel with live
     * health checks, the review-request line and the "more modules" promo strip.
     */
    public function getContent()
    {
        $output = MegVentureReviewNudge::handleRequest($this);

        if (Tools::isSubmit('submitLoginAsCustomer')) {
            $output .= $this->postProcess();
        }

        $output .= $this->renderInfoPanel();
        $output .= $this->renderForm();

        $configureUrl = $this->context->link->getAdminLink('AdminModules', true) . '&configure=' . $this->name;
        $output .= MegVentureReviewNudge::render($this, $configureUrl);

        require_once dirname(__FILE__) . '/classes/MegVentureAdsWidget.php';
        $output .= MegVentureAdsWidget::render('https://megventure.com/index.php?fc=module&module=virtualproductcombination&controller=adswidget');

        return $output;
    }

    private function postProcess()
    {
        $landing = Tools::getValue('LOGINASCUSTOMER_LANDING') === 'index' ? 'index' : 'my-account';

        $ttl = (int) Tools::getValue('LOGINASCUSTOMER_TTL');
        if ($ttl < 5) {
            $ttl = 5;
        } elseif ($ttl > 1440) {
            $ttl = 1440;
        }

        Configuration::updateValue('LOGINASCUSTOMER_LANDING', $landing);
        Configuration::updateValue('LOGINASCUSTOMER_TTL', $ttl);
        Configuration::updateValue('LOGINASCUSTOMER_ON_CUSTOMER', (int) (bool) Tools::getValue('LOGINASCUSTOMER_ON_CUSTOMER'));
        Configuration::updateValue('LOGINASCUSTOMER_ON_ORDER', (int) (bool) Tools::getValue('LOGINASCUSTOMER_ON_ORDER'));
        Configuration::updateValue('LOGINASCUSTOMER_NEWTAB', (int) (bool) Tools::getValue('LOGINASCUSTOMER_NEWTAB'));

        return $this->displayConfirmation($this->l('Settings updated.'));
    }

    private function renderInfoPanel()
    {
        $sslEnabled = (bool) Configuration::get('PS_SSL_ENABLED');

        $this->context->smarty->assign(array(
            'lac_ssl_enabled' => $sslEnabled,
            'lac_on_customer' => (bool) Configuration::get('LOGINASCUSTOMER_ON_CUSTOMER'),
            'lac_on_order' => (bool) Configuration::get('LOGINASCUSTOMER_ON_ORDER'),
            'lac_ttl' => (int) Configuration::get('LOGINASCUSTOMER_TTL'),
        ));

        return $this->display(__FILE__, 'views/templates/admin/configure.tpl');
    }

    private function renderForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'select',
                        'label' => $this->l('Land on'),
                        'name' => 'LOGINASCUSTOMER_LANDING',
                        'desc' => $this->l('Where the storefront opens after you connect as the customer.'),
                        'options' => array(
                            'query' => array(
                                array('id' => 'my-account', 'name' => $this->l('The customer account page')),
                                array('id' => 'index', 'name' => $this->l('The homepage')),
                            ),
                            'id' => 'id',
                            'name' => 'name',
                        ),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Link lifetime (minutes)'),
                        'name' => 'LOGINASCUSTOMER_TTL',
                        'class' => 'fixed-width-sm',
                        'desc' => $this->l('How long a connect link stays valid after it is generated (5 to 1440). Shorter is safer.'),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Show on customer pages'),
                        'name' => 'LOGINASCUSTOMER_ON_CUSTOMER',
                        'is_bool' => true,
                        'values' => array(
                            array('id' => 'oncust_on', 'value' => 1, 'label' => $this->l('Yes')),
                            array('id' => 'oncust_off', 'value' => 0, 'label' => $this->l('No')),
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Show on order pages'),
                        'name' => 'LOGINASCUSTOMER_ON_ORDER',
                        'is_bool' => true,
                        'values' => array(
                            array('id' => 'onord_on', 'value' => 1, 'label' => $this->l('Yes')),
                            array('id' => 'onord_off', 'value' => 0, 'label' => $this->l('No')),
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Open storefront in a new tab'),
                        'name' => 'LOGINASCUSTOMER_NEWTAB',
                        'is_bool' => true,
                        'values' => array(
                            array('id' => 'newtab_on', 'value' => 1, 'label' => $this->l('Yes')),
                            array('id' => 'newtab_off', 'value' => 0, 'label' => $this->l('No')),
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->identifier = $this->identifier;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->submit_action = 'submitLoginAsCustomer';
        $helper->default_form_language = (int) $this->context->language->id;
        $helper->tpl_vars = array(
            'fields_value' => array(
                'LOGINASCUSTOMER_LANDING' => Configuration::get('LOGINASCUSTOMER_LANDING'),
                'LOGINASCUSTOMER_TTL' => (int) Configuration::get('LOGINASCUSTOMER_TTL'),
                'LOGINASCUSTOMER_ON_CUSTOMER' => (int) Configuration::get('LOGINASCUSTOMER_ON_CUSTOMER'),
                'LOGINASCUSTOMER_ON_ORDER' => (int) Configuration::get('LOGINASCUSTOMER_ON_ORDER'),
                'LOGINASCUSTOMER_NEWTAB' => (int) Configuration::get('LOGINASCUSTOMER_NEWTAB'),
            ),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($fields_form));
    }

    /**
     * Builds the signed, short-lived storefront link that logs the current
     * back-office employee in as the given customer.
     *
     * @param int $idCustomer
     *
     * @return string|null Absolute URL, or null when it cannot be built
     */
    private function buildConnectLink($idCustomer)
    {
        $idCustomer = (int) $idCustomer;
        if ($idCustomer <= 0) {
            return null;
        }

        $idEmployee = isset($this->context->employee->id) ? (int) $this->context->employee->id : 0;
        $token = LoginAsCustomerToken::generate($idCustomer, $idEmployee);

        return $this->context->link->getModuleLink(
            'loginascustomer',
            'login',
            array(
                'id_customer' => $idCustomer,
                'id_employee' => $idEmployee,
                'lac_token' => $token,
            ),
            null
        );
    }

    /**
     * Resolves the customer id from whatever a back-office hook happens to pass
     * (id_customer on customer pages, id_order on order pages).
     *
     * @param array $params
     *
     * @return int
     */
    private function resolveCustomerId(array $params)
    {
        if (!empty($params['id_customer'])) {
            return (int) $params['id_customer'];
        }

        if (!empty($params['id_order'])) {
            $order = new Order((int) $params['id_order']);
            if (Validate::isLoadedObject($order)) {
                return (int) $order->id_customer;
            }
        }

        if (isset($params['customer']) && Validate::isLoadedObject($params['customer'])) {
            return (int) $params['customer']->id;
        }

        if (isset($params['order']) && Validate::isLoadedObject($params['order'])) {
            return (int) $params['order']->id_customer;
        }

        return 0;
    }

    private function renderConnectCard($idCustomer)
    {
        $idCustomer = (int) $idCustomer;
        if ($idCustomer <= 0) {
            return '';
        }

        $customer = new Customer($idCustomer);
        if (!Validate::isLoadedObject($customer)) {
            return '';
        }

        $link = $this->buildConnectLink($idCustomer);
        if ($link === null) {
            return '';
        }

        $this->context->smarty->assign(array(
            'lac_link' => $link,
            'lac_customer_name' => trim($customer->firstname . ' ' . $customer->lastname),
            'lac_customer_email' => $customer->email,
            'lac_ttl' => (int) Configuration::get('LOGINASCUSTOMER_TTL'),
            'lac_newtab' => (bool) Configuration::get('LOGINASCUSTOMER_NEWTAB'),
        ));

        return $this->display(__FILE__, 'views/templates/hook/connect.tpl');
    }

    public function hookDisplayAdminCustomers($params)
    {
        if (!Configuration::get('LOGINASCUSTOMER_ON_CUSTOMER')) {
            return '';
        }

        try {
            return $this->renderConnectCard($this->resolveCustomerId($params));
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function hookDisplayAdminOrder($params)
    {
        if (!Configuration::get('LOGINASCUSTOMER_ON_ORDER')) {
            return '';
        }

        try {
            return $this->renderConnectCard($this->resolveCustomerId($params));
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Adds a "Log in as customer" button to the order toolbar (PrestaShop 1.7.7+
     * only; on older versions the panel from displayAdminOrder is the entry point).
     */
    public function hookActionGetAdminOrderButtons(array $params)
    {
        if (!Configuration::get('LOGINASCUSTOMER_ON_ORDER')) {
            return;
        }
        if (empty($params['id_order']) || !isset($params['actions_bar_buttons_collection'])) {
            return;
        }

        // The button class moved namespaces in PrestaShop 8.1: it lived at
        // PrestaShopBundle\Controller\Admin\Sell\Order\ActionsBarButton on
        // 1.7.7-8.0, and at PrestaShop\PrestaShop\Core\Action\ActionsBarButton
        // on 8.1+ / 9. Pick whichever the running core actually ships; if the
        // API is absent (older cores) the displayAdminOrder panel is the entry
        // point, so we simply add no toolbar button.
        $buttonClass = null;
        if (class_exists('PrestaShop\\PrestaShop\\Core\\Action\\ActionsBarButton')) {
            $buttonClass = 'PrestaShop\\PrestaShop\\Core\\Action\\ActionsBarButton';
        } elseif (class_exists('PrestaShopBundle\\Controller\\Admin\\Sell\\Order\\ActionsBarButton')) {
            $buttonClass = 'PrestaShopBundle\\Controller\\Admin\\Sell\\Order\\ActionsBarButton';
        }
        if ($buttonClass === null) {
            return;
        }

        try {
            $order = new Order((int) $params['id_order']);
            if (!Validate::isLoadedObject($order)) {
                return;
            }

            $link = $this->buildConnectLink((int) $order->id_customer);
            if ($link === null) {
                return;
            }

            $attributes = array('href' => $link);
            if ((bool) Configuration::get('LOGINASCUSTOMER_NEWTAB')) {
                $attributes['target'] = '_blank';
            }

            $bar = $params['actions_bar_buttons_collection'];
            $bar->add(new $buttonClass('btn-secondary', $attributes, $this->l('Log in as customer')));
        } catch (\Throwable $e) {
            // Never let the toolbar button break the order page.
        }
    }
}
