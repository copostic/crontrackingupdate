<?php

class GlobalTrackingUpdate
{

	private $colissimoApiKey = [];
	private $colissimoCarriers = [];
	private $dhlApiKey = [];
	private $dhlCarriers = [];
	private $statesToCheck = [];
	private $deliveredStateId = '';
	private $fakeEmployeeId = '';
	private $db;

	public function __construct()
	{
		$this->db = new PDO("mysql:host=localhost;port=3306;dbname=" . _DB_NAME_ . "", _DB_USER_, _DB_PASSWD_);;
		$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->colissimoApiKey = Configuration::get('CRONTRACKINGUPDATE_COLISSIMO_API_KEY');
		$this->colissimoCarriers = explode(',', Configuration::get('CRONTRACKINGUPDATE_COLISSIMO_CARRIERS'));
		$this->dhlApiKey = Configuration::get('CRONTRACKINGUPDATE_DHL_API_KEY');
		$this->dhlCarriers = explode(',', Configuration::get('CRONTRACKINGUPDATE_DHL_CARRIERS'));
		$this->statesToCheck = explode(',', Configuration::get('CRONTRACKINGUPDATE_ORDER_STATES'));
		$this->deliveredStateId = Configuration::get('CRONTRACKINGUPDATE_DELIVERED_STATE_ID');
		$this->fakeEmployeeId = Configuration::get('CRONTRACKINGUPDATE_EMPLOYEE_ID');
	}

	public function getColissimoTrackingStatus(string $trackingNumber): bool
	{
		$ch = curl_init();
		try {
			curl_setopt($ch, CURLOPT_URL, "https://api.laposte.fr/suivi/v2/idships/$trackingNumber");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, [
				'X-Okapi-Key: ' . $this->colissimoApiKey,
				'Accept: application/json'
			]);

			$response = curl_exec($ch);

			if (curl_errno($ch)) {
				echo curl_error($ch);
				die();
			}

			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$result = json_decode($response);
			return intval($http_code) === 200 &&
				property_exists($result, 'shipment') &&
				$result->shipment->isFinal === true;
		} catch (Throwable $ex) {
			return false;
		} finally {
			curl_close($ch);
		}
	}

	public function getDhlTrackingStatus(string $trackingNumber): bool
	{
		$ch = curl_init();
		try {
			curl_setopt($ch, CURLOPT_URL, "https://api-eu.dhl.com/track/shipments?trackingNumber=$trackingNumber");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, [
				'DHL-API-Key: ' . $this->dhlApiKey,
				'Accept: application/json'
			]);

			$response = curl_exec($ch);

			if (curl_errno($ch)) {
				echo curl_error($ch);
				die();
			}

			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$result = json_decode($response);
			return intval($http_code) === 200 &&
				property_exists($result->shipments[0]->status, 'statusCode') &&
				$result->shipments[0]->status->statusCode === 'delivered';
		} catch (Exception $exception) {
			return false;
		} finally {
			curl_close($ch);
		}
	}

	public function getOrdersList(bool $forceAll = false): array
	{
		if ($forceAll) {
			$where = " WHERE oc.`tracking_number` IS NOT NULL && oc.tracking_number <> ''";

		} else {
			$states = $this->statesToCheck;
			$statesString = implode(',', $states);
			$statesWhere = count($states) > 1 ? "IN(" . $statesString . ")" : "= $statesString";
			$where = " WHERE o.current_state $statesWhere";
		}

		$ordersList =
			$this->db->prepare("SELECT oc.`id_order`, oc.`id_carrier`, oc.`tracking_number` FROM " . _DB_PREFIX_ . "order_carrier AS oc 
			LEFT JOIN " . _DB_PREFIX_ . "orders AS o ON o.`id_order` = oc.`id_order` " . $where);
		$ordersList->execute();
		return $ordersList->fetchAll();
	}

	public function updateAllStatuses(bool $forceAll = false)
	{
		$orders = $this->getOrdersList($forceAll);
		foreach ($orders as $order) {
			$isDelivered = false;

			if (array_search((int)$order['id_carrier'], $this->colissimoCarriers) !== -1) {
				$isDelivered = $this->getColissimoTrackingStatus($order['tracking_number']);
			} else if (array_search((int)$order['id_carrier'], $this->dhlCarriers) !== -1) {
				$isDelivered = $this->getDhlTrackingStatus($order['tracking_number']);
			}


			if($isDelivered) {
				$psOrder = new Order($order['id_order']);
				$psOrder->setCurrentState($this->deliveredStateId, $this->fakeEmployeeId);
			}
		}
	}
}