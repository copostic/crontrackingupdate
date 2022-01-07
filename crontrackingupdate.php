<?php
/**
 * 2007-2021 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2021 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

if (!defined('_PS_VERSION_')) {
	exit;
}

use PrestaShop\PrestaShop\Core\Domain\Order\CancellationActionType;

class crontrackingupdate extends Module
{
	protected $config_form = false;

	public function __construct()
	{
		$this->name = 'crontrackingupdate';
		$this->tab = 'shipping_logistics';
		$this->version = '0.9.0';
		$this->author = 'Corentin POSTIC';
		$this->need_instance = 1;

		if (!$this->_path) {
			$this->_path = __PS_BASE_URI__ . 'modules/' . $this->name . '/';
		}
		/**
		 * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
		 */
		$this->bootstrap = true;

		parent::__construct();

		$this->displayName = $this->l('CRON Tracking Update');
		$this->description = $this->l('Mise à jour automatique des status de livraison DHL/Colissimo');

		$this->confirmUninstall = $this->l('Êtes-vous sûr de vouloir supprimer le module ?');

		$this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_];
	}

	/**
	 * Don't forget to create update methods if needed:
	 * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
	 */
	public function install()
	{
		Configuration::updateValue('CRONTRACKINGUPDATE_COLISSIMO_API_KEY', '');
		Configuration::updateValue('CRONTRACKINGUPDATE_COLISSIMO_CARRIERS', '');
		Configuration::updateValue('CRONTRACKINGUPDATE_DHL_API_KEY', '');
		Configuration::updateValue('CRONTRACKINGUPDATE_DHL_CARRIERS', '');
		Configuration::updateValue('CRONTRACKINGUPDATE_ORDER_STATES', '');
		Configuration::updateValue('CRONTRACKINGUPDATE_DELIVERED_STATE_ID', '');

		$this->createFakeEmployee();

		return parent::install() &&
			$this->registerHook('displayInsideCategoryElementor') &&
			$this->registerHook('header');
	}

	public function uninstall()
	{
		Configuration::deleteByName('CRONTRACKINGUPDATE_COLISSIMO_API_KEY');
		Configuration::deleteByName('CRONTRACKINGUPDATE_COLISSIMO_CARRIERS');
		Configuration::deleteByName('CRONTRACKINGUPDATE_DHL_API_KEY');
		Configuration::deleteByName('CRONTRACKINGUPDATE_DHL_CARRIERS');
		Configuration::deleteByName('CRONTRACKINGUPDATE_ORDER_STATES');
		Configuration::deleteByName('CRONTRACKINGUPDATE_DELIVERED_STATE_ID');

		return parent::uninstall();
	}

	/**
	 * Log
	 * @param string|array|object $message element to log
	 * @param string $level log level
	 * @param string $fileName log file
	 */
	public static function log($message, $level = 'debug', $fileName = 'crontrackingupdate.txt')
	{

		$fileDir = _PS_ROOT_DIR_ . '/var/logs/';

		if (is_array($message) || is_object($message)) {
			$message = print_r($message, true);
		}

		$formatted_message = '*' . $level . '* ' . " -- " . date('Y/m/d - H:i:s') . ': ' . $message . "\r\n";

		return file_put_contents($fileDir . $fileName, $formatted_message, FILE_APPEND);
	}

	/**
	 * Load the configuration form
	 */
	public function getContent()
	{
		/**
		 * If values have been submitted in the form, process.
		 */
		if (Tools::isSubmit('submitcrontrackingupdateModule') === true) {
			$this->postProcess();
		}

		$this->context->smarty->assign('module_dir', $this->_path);

		$output = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');

		return $output . $this->renderForm();
	}

	/**
	 * Create the form that will be displayed in the configuration of your module.
	 */
	protected function renderForm()
	{
		$helper = new HelperForm();

		$helper->show_toolbar = false;
		$helper->table = $this->table;
		$helper->module = $this;
		$helper->default_form_language = $this->context->language->id;
		$helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

		$helper->identifier = $this->identifier;
		$helper->submit_action = 'submitcrontrackingupdateModule';
		$helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
			. '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');

		$helper->tpl_vars = [
			'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
			'languages' => $this->context->controller->getLanguages(),
			'id_language' => $this->context->language->id,];

		return $helper->generateForm([$this->getConfigForm()]);
	}

	/**
	 * Create the structure of your form.
	 */
	protected function getConfigForm()
	{
		return [
			'form' => [
				'legend' => [
					'title' => $this->l('Paramètres'),
					'icon' => 'icon-cogs',
				],
				'input' => [
					[
						'type' => 'text',
						'label' => $this->l('Clé API Colissimo'),
						'name' => 'CRONTRACKINGUPDATE_COLISSIMO_API_KEY',
						'lang' => false,
						'required' => true,
						'maxlength' => 512,
						'hint' => $this->l('Clé OK-API Colissimo'),

					],
					[
						'type' => 'select',
						'label' => $this->l('Transporteurs Colissimo'),
						'name' => 'CRONTRACKINGUPDATE_COLISSIMO_CARRIERS[]',
						'multiple' => true,
						'required' => true,
						'hint' => $this->l('Tous les transporteurs dont le tracking sera mis à jour via l\'API Colissimo'),
						'options' => [
							'query' => Carrier::getCarriers((int) $this->context->language->id, true, false, false, null, Carrier::ALL_CARRIERS),
							'id' => 'id_carrier',
							'name' => 'name',
						],

					],
					[
						'type' => 'text',
						'label' => $this->l('Clé API DHL'),
						'name' => 'CRONTRACKINGUPDATE_DHL_API_KEY',
						'lang' => false,
						'required' => true,
						'maxlength' => 512,
						'hint' => $this->l('Clé API DHL'),

					],
					[
						'type' => 'select',
						'label' => $this->l('Transporteurs DHL'),
						'name' => 'CRONTRACKINGUPDATE_DHL_CARRIERS[]',
						'multiple' => true,
						'required' => true,
						'hint' => $this->l('Tous les transporteurs dont le tracking sera mis à jour via l\'API DHL'),
						'options' => [
							'query' => Carrier::getCarriers((int) $this->context->language->id, true),
							'id' => 'id_carrier',
							'name' => 'name',
						],

					],
					[
						'type' => 'select',
						'label' => $this->l('Statuts de commandes'),
						'name' => 'CRONTRACKINGUPDATE_ORDER_STATES[]',
						'multiple' => true,
						'required' => true,
						'hint' => $this->l('Tous les status de commande concernés par la mise à jour du statut'),
						'options' => [
							'query' => OrderState::getOrderStates((int) $this->context->language->id),
							'id' => 'id_order_state',
							'name' => 'name',
						],

					],
					[
						'type' => 'select',
						'label' => $this->l('Statut de commande livrée'),
						'name' => 'CRONTRACKINGUPDATE_DELIVERED_STATE_ID',
						'multiple' => false,
						'required' => true,
						'hint' => $this->l('Le statut de commande indiquant que celle-ci est livrée'),
						'options' => [
							'query' => OrderState::getOrderStates((int) $this->context->language->id),
							'id' => 'id_order_state',
							'name' => 'name',
						],

					],
				],
				'submit' => [
					'title' => $this->l('Save'),
				],
			],
		];
	}

	/**
	 * Set values for the inputs.
	 */
	protected function getConfigFormValues()
	{
		return [
			'CRONTRACKINGUPDATE_COLISSIMO_API_KEY' => Configuration::get('CRONTRACKINGUPDATE_COLISSIMO_API_KEY'),
			'CRONTRACKINGUPDATE_COLISSIMO_CARRIERS[]' => explode(',', Configuration::get('CRONTRACKINGUPDATE_COLISSIMO_CARRIERS')),
			'CRONTRACKINGUPDATE_DHL_API_KEY' => Configuration::get('CRONTRACKINGUPDATE_DHL_API_KEY'),
			'CRONTRACKINGUPDATE_DHL_CARRIERS[]' => explode(',', Configuration::get('CRONTRACKINGUPDATE_DHL_CARRIERS')),
			'CRONTRACKINGUPDATE_ORDER_STATES[]' => explode(',', Configuration::get('CRONTRACKINGUPDATE_ORDER_STATES')),
			'CRONTRACKINGUPDATE_DELIVERED_STATE_ID' => Configuration::get('CRONTRACKINGUPDATE_DELIVERED_STATE_ID'),
		];
	}

	/**
	 * Save form data.
	 */
	protected function postProcess()
	{
		$form_values = $this->getConfigFormValues();

		foreach (array_keys($form_values) as $key) {
			$k = strpos($key, "[]") !== false ? substr($key, 0, -2) : $key;
			$value = Tools::getValue($k);
			Configuration::updateValue($k, is_array($value) ?  implode(',', $value) : $value);
		}
	}

	public function hookHeader()
	{
		$this->context->controller->registerStylesheet('modules-' . $this->name . '-hook-category-style', 'modules/' . $this->name . '/views/css/front.css', ['media' => 'all', 'priority' => 150]);
		$this->context->controller->registerJavascript('modules-' . $this->name . '-hook-category-script', 'modules/' . $this->name . '/views/js/front.js', ['position' => 'bottom', 'priority' => 150]);
	}

	private function createFakeEmployee() {
		if(Employee::customerExists("noreply@smptrading.com")) {
			$employee = Employee::getByEmail("noreply@smptrading.com");
			Configuration::updateValue('CRONTRACKINGUPDATE_EMPLOYEE_ID', $employee->id);
			return;
		}

		$employee = new Employee();
		$employee->firstname = "Utilisateur";
		$employee->lastname = "Script mise à jour";
		$employee->id_lang = (int)Configuration::get('PS_LANG_DEFAULT');
		$employee->id_profile = _PS_ADMIN_PROFILE_;
		$employee->email = "noreply@smptrading.com";
		$employee->passwd = Tools::hash("BatmanWasHere");
		$employee->save();

		Configuration::updateValue('CRONTRACKINGUPDATE_EMPLOYEE_ID', $employee->id);
	}
}
