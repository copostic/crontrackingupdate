<?php
//colissimoApiKey = 'G+2HonDVb8CUBeSc5azGohsJyZ3bV115s9OUnkpDHrfjO7COqNEJPKRAqF+ZCYi2';
//dhlApiKey = 'ztqqG6lYN7tiMJhlAWI1EGp3itlgPHkA';

require_once(_PS_MODULE_DIR_.'crontrackingupdate/models/GlobalTrackingUpdate.php');

class CronTrackingUpdateUpdateTrackingModuleFrontController extends ModuleFrontController
{
	public $ssl = true;

	public function display()
	{
		$this->ajax = true;
		$tracker = new GlobalTrackingUpdate();
		$tracker->updateAllStatuses();
		$this->ajaxRender('');
	}
}