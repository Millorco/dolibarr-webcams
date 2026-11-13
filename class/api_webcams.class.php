<?php
/* Copyright (C) 2015		Jean-François Ferry		<jfefe@aternatik.fr>
 * Copyright (C) 2024-2025  Frédéric France			<frederic.france@free.fr>
 * Copyright (C) ---Replace with your own copyright and developer email---
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

use Luracast\Restler\RestException;

dol_include_once('/webcams/class/webcam.class.php');



/**
 * \file    htdocs/modulebuilder/template/class/api_webcams.class.php
 * \ingroup webcams
 * \brief   File for API management of webcam.
 */

/**
 * API class for webcams webcam
 *
 * @access protected
 * @class  DolibarrApiAccess {@requires user,external}
 */
class Webcams extends DolibarrApi
{
	/**
	 * @var Webcam {@type Webcam}
	 */
	public $webcam;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		global $db;
		$this->db = $db;
		$this->webcam = new Webcam($this->db);
	}


	/* BEGIN MODULEBUILDER API WEBCAM */

	/**
	 * Get properties of a webcam object
	 *
	 * Return an array with webcam information
	 *
	 * @param	int		$id				ID of webcam
	 * @return  Object					Object with cleaned properties
	 * @phan-return	Webcam			Object with cleaned properties
	 * @phpstan-return	Webcam			Object with cleaned properties
	 *
	 * @phan-return  Webcam
	 *
	 * @url	GET {id}
	 *
	 * @throws RestException 403 Not allowed
	 * @throws RestException 404 Not found
	 */
	public function get($id)
	{
		if (!DolibarrApiAccess::$user->hasRight('webcams', 'webcam', 'read')) {
			throw new RestException(403);
		}
		if (!DolibarrApi::_checkAccessToResource('webcam', $id, 'webcams_webcam')) {
			throw new RestException(403, 'Access to instance id='.$id.' of object not allowed for login '.DolibarrApiAccess::$user->login);
		}

		$result = $this->webcam->fetch($id);
		if (!$result) {
			throw new RestException(404, 'Webcam not found');
		}

		return $this->_cleanObjectDatas($this->webcam);
	}


	/**
	 * List webcams
	 *
	 * Get a list of webcams
	 *
	 * @param 	string		   $sortfield			Sort field
	 * @param 	string		   $sortorder			Sort order
	 * @param 	int			   $limit				Limit for list
	 * @param 	int			   $page				Page number
	 * @param 	string         $sqlfilters          Other criteria to filter answers separated by a comma. Syntax example "(t.ref:like:'SO-%') and (t.date_creation:>:'20160101')"
	 * @param 	string		   $properties			Restrict the data returned to these properties. Ignored if empty. Comma separated list of properties names
	 * @return  array                               Array of Webcam objects
	 * @phan-return array<int,Webcam>
	 * @phpstan-return array<int,Webcam>
	 *
	 * @throws RestException 403 Not allowed
	 * @throws RestException 503 System error
	 *
	 * @url	GET /
	 */
	public function index($sortfield = "t.rowid", $sortorder = 'ASC', $limit = 100, $page = 0, $sqlfilters = '', $properties = '')
	{
		global $hookmanager;

		$obj_ret = array();
		$tmpobject = new Webcam($this->db);

		if (!DolibarrApiAccess::$user->hasRight('webcams', 'webcam', 'read')) {
			throw new RestException(403);
		}

		$socid = DolibarrApiAccess::$user->socid ?: 0;

		$restrictonsocid = 0; // Set to 1 if there is a field socid in table of object

		// If the internal user must only see his customers, force searching by him
		$search_sale = 0;
		if ($restrictonsocid && !DolibarrApiAccess::$user->hasRight('societe', 'client', 'voir') && !$socid) {
			$search_sale = DolibarrApiAccess::$user->id;
		}
		if (!isModEnabled('societe')) {
			$search_sale = 0; // If module thirdparty not enabled, sale representative is something that does not exists
		}

		$sql = "SELECT t.rowid";
		$sql .= " FROM ".$this->db->prefix().$tmpobject->table_element." AS t";
		if (!empty($tmpobject->isextrafieldmanaged) && (int) $tmpobject->isextrafieldmanaged == 1) {
			$sql .= " LEFT JOIN ".$this->db->prefix().$tmpobject->table_element."_extrafields AS ef ON (ef.fk_object = t.rowid)"; // Modification VMR Global Solutions to include extrafields as search parameters in the API GET call, so we will be able to filter on extrafields
		}
		if (!empty($tmpobject->ismultientitymanaged) && (int) $tmpobject->ismultientitymanaged == 1) {
			$sql .= " WHERE t.entity IN (".getEntity($tmpobject->element).")";
		} elseif (preg_match('/^\w+@\w+$/', (string) $tmpobject->ismultientitymanaged)) {
			$tmparray = explode('@', (string) $tmpobject->ismultientitymanaged);
			$sql .= " LEFT JOIN ".$this->db->prefix().$this->db->sanitize($tmparray[1])." as pt ON t.".$this->db->sanitize($tmparray[0])." = pt.rowid";
			$sql .= " WHERE pt.entity IN (".getEntity($tmpobject->element).")";
		} else {
			$sql .= " WHERE 1 = 1";
		}
		if ($restrictonsocid && $socid) {
			$sql .= " AND t.fk_soc = ".((int) $socid);
		}
		// Search on sale representative
		if ($search_sale && $search_sale != '-1') {
			if ($search_sale == -2) {
				$sql .= " AND NOT EXISTS (SELECT sc.fk_soc FROM ".$this->db->prefix()."societe_commerciaux as sc WHERE sc.fk_soc = t.fk_soc)";
			} elseif ($search_sale > 0) {
				$sql .= " AND EXISTS (SELECT sc.fk_soc FROM ".$this->db->prefix()."societe_commerciaux as sc WHERE sc.fk_soc = t.fk_soc AND sc.fk_user = ".((int) $search_sale).")";
			}
		}
		// Add where from hooks and sqlfilters
		$parameters = array('sqlfilters' => $sqlfilters, 'apiroute' => 'webcam', 'apimethod' => __METHOD__);
		$action = 'list';
		$reshook = $hookmanager->executeHooks('printFieldListWhere', $parameters, $tmpobject, $action); // Note that $action and $object may have been modified by hook
		if ($reshook > 0) {
			$sql = $hookmanager->resPrint;
		} elseif ($reshook == 0) {
			$sql .= $hookmanager->resPrint;
		}

		if ($sqlfilters) {
			$errormessage = '';
			$sql .= forgeSQLFromUniversalSearchCriteria($sqlfilters, $errormessage);
			if ($errormessage) {
				throw new RestException(400, 'Error when validating parameter sqlfilters -> '.$errormessage);
			}
		}

		$sql .= $this->db->order($sortfield, $sortorder);
		if ($limit) {
			if ($page < 0) {
				$page = 0;
			}
			$offset = $limit * $page;

			$sql .= $this->db->plimit($limit + 1, $offset);
		}

		$result = $this->db->query($sql);
		$i = 0;
		if ($result) {
			$num = $this->db->num_rows($result);
			$min = min($num, ($limit <= 0 ? $num : $limit));
			while ($i < $min) {
				$obj = $this->db->fetch_object($result);
				$tmp_object = new Webcam($this->db);
				if ($tmp_object->fetch($obj->rowid)) {
					$obj_ret[] = $this->_filterObjectProperties($this->_cleanObjectDatas($tmp_object), $properties);
				}
				$i++;
			}
		} else {
			throw new RestException(503, 'Error when retrieving webcam list: '.$this->db->lasterror());
		}

		return $obj_ret;
	}

	/**
	 * Create webcam object
	 *
	 * @param array $request_data   Request data
	 * @phan-param ?array<string,mixed> $request_data
	 * @phpstan-param ?array<string,mixed> $request_data
	 * @return int  				ID of webcam
	 *
	 * @throws RestException 403 Not allowed
	 * @throws RestException 500 System error
	 *
	 * @url	POST /
	 */
	public function post($request_data = null)
	{
		if (!DolibarrApiAccess::$user->hasRight('webcams', 'webcam', 'write')) {
			throw new RestException(403);
		}

		// Check mandatory fields
		$result = $this->_validateWebcam($request_data);

		foreach ($request_data as $field => $value) {
			if ($field === 'caller') {
				// Add a mention of caller so on trigger called after action, we can filter to avoid a loop if we try to sync back again with the caller @phan-suppress-next-line PhanTypeInvalidDimOffset
				$this->webcam->context['caller'] = sanitizeVal((string) $request_data['caller'], 'aZ09');
				continue;
			}

			if ($field == 'array_options' && is_array($value)) {
				$this->webcam->fetch_optionals();	// To force the load of the extrafields definition by fetch_name_optionals_label()

				foreach ($value as $index => $val) {
					$this->webcam->array_options[$index] = $this->_checkValExtrafieldsForAPI($index, $val, $this->webcam);
				}
				continue;
			}

			$this->webcam->$field = $this->_checkValForAPI((string) $field, $value, $this->webcam);
		}

		// Clean data
		// $this->webcam->abc = sanitizeVal($this->webcam->abc, 'alphanohtml');

		if ($this->webcam->create(DolibarrApiAccess::$user) < 0) {
			throw new RestException(500, "Error creating Webcam", array_merge(array($this->webcam->error), $this->webcam->errors));
		}
		return $this->webcam->id;
	}

	/**
	 * Report from a webcam router (inventory_report of luci-app-webcam)
	 *
	 * Creates the webcam the first time (identified by its MAC address) and then
	 * updates only the technical data. Data entered by hand (customer, name,
	 * location, notes...) is never changed. Changes of versions, camera, VPN
	 * address and reboots are recorded as events in the webcam agenda.
	 *
	 * @param	array	$request_data	JSON sent by the router: mac (required), hostname, model, openwrt, kernel, app_version, board_fw, camera, vpn_ip, wan_ip, uptime, mem_free_kb, disk_free_kb, queue, upload_protocol, upload_server, upload_directory, interval, maintenance, heating, monitoring, lat, lon, temperature, humidity, last_capture_time, last_capture_file
	 * @phan-param ?array<string,mixed> $request_data
	 * @phpstan-param ?array<string,mixed> $request_data
	 * @return	array	{ok, id, new}
	 * @phan-return array<string,mixed>
	 * @phpstan-return array<string,mixed>
	 *
	 * @throws RestException 400 Bad request
	 * @throws RestException 403 Not allowed
	 * @throws RestException 500 System error
	 *
	 * @url	POST report
	 */
	public function report($request_data = null)
	{
		global $conf;

		$user = DolibarrApiAccess::$user;
		if (!$user->hasRight('webcams', 'webcam', 'write')) {
			throw new RestException(403);
		}
		$in = is_array($request_data) ? $request_data : array();

		$mac = strtolower(trim((string) ($in['mac'] ?? '')));
		if (!preg_match('/^([0-9a-f]{2}:){5}[0-9a-f]{2}$/', $mac)) {
			throw new RestException(400, 'mac missing or invalid');
		}

		$str = function ($k, $max = 150) use ($in) {
			if (!isset($in[$k]) || is_array($in[$k])) {
				return null;
			}
			$v = trim((string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', (string) $in[$k]));
			return $v === '' ? null : dol_substr($v, 0, $max);
		};
		$int = function ($k) use ($in) {
			return (isset($in[$k]) && is_numeric($in[$k]) && $in[$k] >= 0 && $in[$k] <= 4294967295) ? (int) $in[$k] : null;
		};
		$num = function ($k, $min, $max) use ($in) {
			return (isset($in[$k]) && is_numeric($in[$k]) && $in[$k] >= $min && $in[$k] <= $max) ? (float) $in[$k] : null;
		};
		$bool = function ($k) use ($in) {
			if (!array_key_exists($k, $in) || $in[$k] === null || $in[$k] === '') {
				return null;
			}
			return in_array($in[$k], array(true, 1, '1', 'true'), true) ? 1 : 0;
		};
		$ip = function ($v) {
			return ($v && filter_var($v, FILTER_VALIDATE_IP)) ? $v : null;
		};

		$now = dol_now();
		$last = $int('last_capture_time');
		$data = array(
			'hostname' => $str('hostname', 100),
			'router_model' => $str('model'),
			'openwrt' => $str('openwrt', 100),
			'kernel' => $str('kernel', 50),
			'app_version' => $str('app_version', 30),
			'board_fw' => $str('board_fw', 30),
			'camera' => $str('camera'),
			'vpn_ip' => $ip($str('vpn_ip', 45)),
			'wan_ip' => $ip($str('wan_ip', 45)),
			'public_ip' => $ip(getUserRemoteIP()),
			'uptime' => $int('uptime'),
			'mem_free_kb' => $int('mem_free_kb'),
			'disk_free_kb' => $int('disk_free_kb'),
			'queue' => $int('queue'),
			'upload_protocol' => $str('upload_protocol', 10),
			'upload_server' => $str('upload_server', 200),
			'upload_directory' => $str('upload_directory', 200),
			'shot_interval' => $int('interval'),
			'maintenance' => $bool('maintenance'),
			'heating' => $bool('heating'),
			'monitoring' => $bool('monitoring'),
			'latitude' => $num('lat', -90, 90),
			'longitude' => $num('lon', -180, 180),
			'temperature' => $num('temperature', -80, 150),
			'humidity' => $num('humidity', 0, 100),
			'last_capture' => $last ? $last : null,
			'last_photo' => $str('last_capture_file', 200),
			'report_json' => json_encode($in, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
			'last_contact' => $now,
		);
		$tracked = array(
			'hostname' => 'WebcamHostname', 'router_model' => 'WebcamRouterModel', 'openwrt' => 'WebcamOpenwrt',
			'app_version' => 'WebcamAppVersion', 'board_fw' => 'WebcamBoardFw', 'camera' => 'WebcamCamera',
			'vpn_ip' => 'WebcamVpnIp', 'upload_server' => 'WebcamUploadServer',
		);

		$object = new Webcam($this->db);
		$sql = "SELECT rowid FROM ".$this->db->prefix()."webcams_webcam";
		$sql .= " WHERE mac = '".$this->db->escape($mac)."' AND entity IN (".getEntity('webcam').")";
		$resql = $this->db->query($sql);
		$obj = $resql ? $this->db->fetch_object($resql) : null;

		$events = array();
		$isnew = !$obj;
		if ($isnew) {
			$object->ref = 'WC-'.strtoupper(str_replace(':', '', $mac));
			$object->label = $data['hostname'];
			$object->mac = $mac;
			$object->first_contact = $now;
			$object->status = Webcam::STATUS_VALIDATED;
			foreach ($data as $k => $v) {
				$object->$k = $v;
			}
			if ($object->create($user, 1) <= 0) {
				throw new RestException(500, 'Error creating webcam', array_merge(array($object->error), $object->errors));
			}
			$events[] = 'Prima registrazione'.($data['hostname'] ? ' come '.$data['hostname'] : '');
		} else {
			$object->fetch($obj->rowid);
			global $langs;
			$langs->load('webcams@webcams');
			foreach ($tracked as $k => $label) {
				if ($data[$k] !== null && (string) $object->$k !== (string) $data[$k]) {
					$events[] = $langs->transnoentitiesnoconv($label).': '.($object->$k !== null && $object->$k !== '' ? $object->$k : '-').' -> '.$data[$k];
				}
			}
			if ($data['uptime'] !== null && $object->uptime !== null && $object->uptime !== '' && $object->last_contact) {
				$since = $now - (int) $object->last_contact;
				if ((int) $data['uptime'] < (int) $object->uptime + $since - 600) {
					$events[] = 'Riavvio (acceso da '.convertSecondToTime((int) $data['uptime'], 'allhourmin').')';
				}
			}
			// A field missing from the report does not erase the saved value
			foreach ($data as $k => $v) {
				if ($v !== null) {
					$object->$k = $v;
				}
			}
			if ($object->update($user, 1) <= 0) {
				throw new RestException(500, 'Error updating webcam', array_merge(array($object->error), $object->errors));
			}
		}

		if ($events && isModEnabled('agenda')) {
			require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
			foreach ($events as $text) {
				$ev = new ActionComm($this->db);
				$ev->type_code = 'AC_OTH_AUTO';
				$ev->code = 'AC_WEBCAMS_REPORT';
				$ev->label = dol_trunc($object->ref.' - '.$text, 255);
				$ev->note_private = $text;
				$ev->datep = $now;
				$ev->percentage = -1;
				$ev->userownerid = $user->id;
				$ev->socid = $object->fk_soc > 0 ? $object->fk_soc : 0;
				$ev->fk_element = $object->id;
				$ev->elementtype = $object->element.'@'.$object->module;
				$ev->create($user);
			}
		}

		return array('ok' => true, 'id' => (int) $object->id, 'new' => $isnew);
	}

	/**
	 * Update webcam
	 *
	 * @param 	int   		$id             Id of webcam to update
	 * @param 	array 		$request_data   Data
	 * @phan-param ?array<string,mixed>	$request_data
	 * @phpstan-param ?array<string,mixed>	$request_data
	 * @return 	Object						Object after update
	 * @phan-return Webcam
	 * @phpstan-return Webcam
	 *
	 * @throws RestException 403 Not allowed
	 * @throws RestException 404 Not found
	 * @throws RestException 500 System error
	 *
	 * @url	PUT {id}
	 */
	public function put($id, $request_data = null)
	{
		if (!DolibarrApiAccess::$user->hasRight('webcams', 'webcam', 'write')) {
			throw new RestException(403);
		}
		if (!DolibarrApi::_checkAccessToResource('webcam', $id, 'webcams_webcam')) {
			throw new RestException(403, 'Access to instance id='.$this->webcam->id.' of object not allowed for login '.DolibarrApiAccess::$user->login);
		}

		$result = $this->webcam->fetch($id);
		if (!$result) {
			throw new RestException(404, 'Webcam not found');
		}

		foreach ($request_data as $field => $value) {
			if ($field == 'id') {
				continue;
			}
			if ($field === 'caller') {
				// Add a mention of caller so on trigger called after action, we can filter to avoid a loop if we try to sync back again with the caller
				$this->webcam->context['caller'] = sanitizeVal($request_data['caller'], 'aZ09');
				continue;
			}

			if ($field == 'array_options' && is_array($value)) {
				foreach ($value as $index => $val) {
					$this->webcam->array_options[$index] = $this->_checkValExtrafieldsForAPI($index, $val, $this->webcam);
				}
				continue;
			}

			$this->webcam->$field = $this->_checkValForAPI((string) $field, $value, $this->webcam);
		}

		// Clean data
		// $this->webcam->abc = sanitizeVal($this->webcam->abc, 'alphanohtml');

		if ($this->webcam->update(DolibarrApiAccess::$user, 0) > 0) {
			return $this->get($id);
		} else {
			throw new RestException(500, $this->webcam->error);
		}
	}

	/**
	 * Delete webcam
	 *
	 * @param   int     $id   Webcam ID
	 * @return  array
	 * @phan-return array<string,array{code:int,message:string}>
	 * @phpstan-return array<string,array{code:int,message:string}>
	 *
	 * @throws RestException 403 Not allowed
	 * @throws RestException 404 Not found
	 * @throws RestException 409 Nothing to do
	 * @throws RestException 500 System error
	 *
	 * @url	DELETE {id}
	 */
	public function delete($id)
	{
		if (!DolibarrApiAccess::$user->hasRight('webcams', 'webcam', 'delete')) {
			throw new RestException(403);
		}
		if (!DolibarrApi::_checkAccessToResource('webcam', $id, 'webcams_webcam')) {
			throw new RestException(403, 'Access to instance id='.$this->webcam->id.' of object not allowed for login '.DolibarrApiAccess::$user->login);
		}

		$result = $this->webcam->fetch($id);
		if (!$result) {
			throw new RestException(404, 'Webcam not found');
		}

		if ($this->webcam->delete(DolibarrApiAccess::$user) == 0) {
			throw new RestException(409, 'Error when deleting Webcam : '.$this->webcam->error);
		} elseif ($this->webcam->delete(DolibarrApiAccess::$user) < 0) {
			throw new RestException(500, 'Error when deleting Webcam : '.$this->webcam->error);
		}

		return array(
			'success' => array(
				'code' => 200,
				'message' => 'Webcam deleted'
			)
		);
	}


	/**
	 * Validate fields before creating or updating object
	 *
	 * @param	array		$data   Array of data to validate
	 * @phan-param		?array<string,null|int|float|string> $data
	 * @phpstan-param	?array<string,null|int|float|string> $data
	 * @return	array
	 * @phan-return		array<string,null|int|float|string>|array{}
	 * @phpstan-return	array<string,null|int|float|string>|array{}
	 *
	 * @throws	RestException
	 */
	private function _validateWebcam($data)
	{
		if (!is_array($data)) {
			$data = array();
		}
		$webcam = array();
		foreach ($this->webcam->fields as $field => $propfield) {
			if (in_array($field, array('rowid', 'entity', 'date_creation', 'tms', 'fk_user_creat')) || $propfield['notnull'] != 1) {
				continue; // Not a mandatory field
			}
			if (!isset($data[$field])) {
				throw new RestException(400, "$field field missing");
			}
			$webcam[$field] = $data[$field];
		}
		return $webcam;
	}

	/* END MODULEBUILDER API WEBCAM */



	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 * Clean sensitive object data fields
	 * @phpstan-template T
	 *
	 * @param   Object  $object     Object to clean
	 * @return  Object              Object with cleaned properties
	 *
	 * @phpstan-param T $object
	 * @phpstan-return T
	 */
	protected function _cleanObjectDatas($object)
	{
		// phpcs:enable
		$object = parent::_cleanObjectDatas($object);

		unset($object->rowid);
		unset($object->canvas);
		//BEGIN MODULEBUILDER LINES
		// If object has lines, remove $db property
		if (isset($object->lines) && is_array($object->lines) && count($object->lines) > 0) {
			$nboflines = count($object->lines);
			for ($i = 0; $i < $nboflines; $i++) {
				$this->_cleanObjectDatas($object->lines[$i]);

				unset($object->lines[$i]->lines);
				unset($object->lines[$i]->note);
			}
		}
		//END MODULEBUILDER LINES
		return $object;
	}
}
