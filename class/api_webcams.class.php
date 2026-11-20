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
dol_include_once('/webcams/lib/webcams_webcam.lib.php');



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
	 * @param	array	$request_data	JSON sent by the router: mac (required), hostname, model, openwrt, kernel, app_version, board_fw, camera, vpn_ip, wan_ip, uptime, mem_free_kb, disk_free_kb, queue, upload_protocol, upload_server, upload_directory, interval, maintenance, heating, monitoring, lat, lon, temperature, humidity, temperature_ext, humidity_ext (outside sensor, optional), last_capture_time, last_capture_file; optional since module 1.2: camera_serial, shutter_count, shots_total, camera_hours, camera_power_cycles, battery_v, charge_state
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
			'temperature_ext' => $num('temperature_ext', -80, 150),
			'humidity_ext' => $num('humidity_ext', 0, 100),
			'battery_v' => $num('battery_v', 0, 100),
			'charge_state' => (($cs = $str('charge_state', 20)) !== null && preg_match('/^[a-z][a-z0-9_ -]{0,19}$/', strtolower($cs))) ? strtolower($cs) : null,
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

		// Counters of the camera (luci-app-webcam 1.1): saved with the same rules as POST {id}/counters
		$counters = array(
			'shutter_count' => $int('shutter_count'),
			'shots_total' => $int('shots_total'),
			'camera_hours' => $num('camera_hours', 0, 9999999),
			'camera_power_cycles' => $int('camera_power_cycles'),
		);
		foreach ($counters as $k => $v) {
			if ($v !== null && $v > 2147483647) {
				$counters[$k] = null;
			}
		}
		$serial = $str('camera_serial', 64);
		if ($data['battery_v'] !== null || $data['charge_state'] !== null) {
			$data['victron_date'] = $now;
		}

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
			$object->camera_serial = $serial;
			$object->windy_published = 0;
			foreach ($counters as $k => $v) {
				$object->$k = $v;
			}
			if (array_filter($counters, function ($v) {
				return $v !== null;
			})) {
				$object->counters_date = $now;
			}
			if ($object->create($user, 1) <= 0) {
				throw new RestException(500, 'Error creating webcam', array_merge(array($object->error), $object->errors));
			}
			$events[] = 'Prima registrazione'.($data['hostname'] ? ' come '.$data['hostname'] : '');
			$level = webcamsShutterLevel(webcamsShotsUsed($object));
			if ($level) {
				$events[] = $this->_trans($level == 'danger' ? 'WebcamEventShutterLife' : 'WebcamEventShutterWarn', webcamsShotsUsed($object), webcamsShutterLife());
			}
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
			// Counters and serial number of the camera: same rules as POST {id}/counters
			// (a counter does not go back without a change of camera, a new serial
			// number is recorded in the agenda)
			$set = array();
			$updated = array();
			$ignored = array();
			$cevents = $this->_applyCounters($object, $serial, $counters, $data['camera'], $set, $updated, $ignored);
			$events = array_merge($events, $cevents);
			foreach ($set as $k => $v) {
				$object->$k = $v;
			}
			if ($updated) {
				$object->counters_date = $now;
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

	/* BEGIN API V2 (webcam-portal) */

	/**
	 * Find a webcam by its MAC address
	 *
	 * Used by webcam-portal to link one of its webcams to the Dolibarr card.
	 *
	 * @param	string	$mac	MAC address (aa:bb:cc:dd:ee:ff, aa-bb-cc-dd-ee-ff or aabbccddeeff)
	 * @return	Object			Object with cleaned properties, as GET webcams/{id}
	 * @phan-return	Webcam
	 * @phpstan-return	Webcam
	 *
	 * In the path the MAC must be written without ':' (aabbccddeeff or aa-bb-cc-dd-ee-ff),
	 * because Dolibarr refuses ':' in the URL path; with ':' use the query string
	 * (bymac?mac=aa:bb:cc:dd:ee:ff).
	 *
	 * @url	GET bymac/{mac}
	 * @url	GET bymac
	 *
	 * @throws RestException 400 Bad request
	 * @throws RestException 403 Not allowed
	 * @throws RestException 404 Not found
	 */
	public function getByMac($mac)
	{
		if (!DolibarrApiAccess::$user->hasRight('webcams', 'webcam', 'read')) {
			throw new RestException(403);
		}
		$hex = preg_replace('/[^0-9a-f]/', '', strtolower(trim((string) $mac)));
		if (strlen($hex) != 12 || !preg_match('/^[0-9a-f:\-\.]+$/', strtolower(trim((string) $mac)))) {
			throw new RestException(400, 'mac invalid');
		}
		$mac = implode(':', str_split($hex, 2));

		$sql = "SELECT rowid FROM ".$this->db->prefix()."webcams_webcam";
		$sql .= " WHERE mac = '".$this->db->escape($mac)."' AND entity IN (".getEntity('webcam').")";
		$resql = $this->db->query($sql);
		if (!$resql) {
			throw new RestException(503, 'Error when searching webcam: '.$this->db->lasterror());
		}
		$obj = $this->db->fetch_object($resql);
		if (!$obj) {
			throw new RestException(404, 'Webcam not found');
		}
		if (!DolibarrApi::_checkAccessToResource('webcam', $obj->rowid, 'webcams_webcam')) {
			throw new RestException(403, 'Access to instance id='.$obj->rowid.' of object not allowed for login '.DolibarrApiAccess::$user->login);
		}
		if ($this->webcam->fetch($obj->rowid) <= 0) {
			throw new RestException(404, 'Webcam not found');
		}
		return $this->_cleanObjectDatas($this->webcam);
	}

	/**
	 * Publication data written by webcam-portal
	 *
	 * Updates only portal_slug, url_current, windy_published and windy_id (fields
	 * missing from the request are not changed). A change of windy_published is
	 * recorded in the agenda of the webcam.
	 *
	 * @param	int		$id				ID of webcam
	 * @param	array	$request_data	{portal_slug, url_current, windy_published, windy_id, film_status (queued, ready), film_url}
	 * @phan-param ?array<string,mixed> $request_data
	 * @phpstan-param ?array<string,mixed> $request_data
	 * @return	array	{ok, id, ignored}
	 * @phan-return array<string,mixed>
	 * @phpstan-return array<string,mixed>
	 *
	 * @url	PUT {id}/portal
	 *
	 * @throws RestException 400 Bad request
	 * @throws RestException 403 Not allowed
	 * @throws RestException 404 Not found
	 * @throws RestException 500 System error
	 */
	public function putPortal($id, $request_data = null)
	{
		$object = $this->_fetchForPortal($id);
		$in = is_array($request_data) ? $request_data : array();

		$set = array();
		if (array_key_exists('portal_slug', $in)) {
			$v = $this->_inString($in, 'portal_slug', 64);
			if ($v !== null && !preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $v)) {
				throw new RestException(400, 'portal_slug invalid');
			}
			$set['portal_slug'] = $v;
		}
		if (array_key_exists('url_current', $in)) {
			$v = $this->_inString($in, 'url_current', 255);
			if ($v !== null && (!preg_match('/^https?:\/\//i', $v) || !filter_var($v, FILTER_VALIDATE_URL))) {
				throw new RestException(400, 'url_current invalid');
			}
			$set['url_current'] = $v;
		}
		if (array_key_exists('windy_published', $in)) {
			$v = $in['windy_published'];
			if (!in_array($v, array(0, 1, '0', '1', true, false, null), true)) {
				throw new RestException(400, 'windy_published must be 0 or 1');
			}
			$set['windy_published'] = in_array($v, array(1, '1', true), true) ? 1 : 0;
		}
		if (array_key_exists('windy_id', $in)) {
			$v = $this->_inString($in, 'windy_id', 64);
			if ($v !== null && !preg_match('/^[A-Za-z0-9_.:-]+$/', $v)) {
				throw new RestException(400, 'windy_id invalid');
			}
			$set['windy_id'] = $v;
		}
		// 1.4: final film (M10), written by the portal; delivered is set by the operator
		$ignored = array();
		$filmready = false;
		if (array_key_exists('film_status', $in)) {
			$v = $this->_inString($in, 'film_status', 16);
			if ($v !== null && !in_array($v, array('queued', 'ready'), true)) {
				throw new RestException(400, 'film_status must be queued or ready');
			}
			if ((string) $object->film_status === 'delivered') {
				$ignored[] = 'film_status';
			} elseif ($v !== (string) $object->film_status) {
				$set['film_status'] = $v;
				$filmready = ($v === 'ready');
			}
		}
		if (array_key_exists('film_url', $in)) {
			$v = $this->_inString($in, 'film_url', 255);
			if ($v !== null && (!preg_match('/^https?:\/\//i', $v) || !filter_var($v, FILTER_VALIDATE_URL))) {
				throw new RestException(400, 'film_url invalid');
			}
			$set['film_url'] = $v;
		}
		if (!$set && !$ignored && !array_key_exists('film_status', $in)) {
			throw new RestException(400, 'Nothing to update: send portal_slug, url_current, windy_published, windy_id, film_status or film_url');
		}

		$events = array();
		if (isset($set['windy_published']) && (int) $object->windy_published !== $set['windy_published']) {
			$events[] = $this->_trans($set['windy_published'] ? 'WebcamEventWindyOn' : 'WebcamEventWindyOff');
		}
		$this->_updateColumns($object, $set);
		foreach ($events as $text) {
			$this->_addEvent($object, $text);
		}
		if ($filmready) {
			$url = (string) $object->film_url;
			$this->_addEvent($object, $this->_trans('WebcamEventFilmReady'), 'AC_WEBCAMS_PORTAL', 0, '', $this->_trans('WebcamEventFilmReady').($url !== '' ? "\n".$url : ''));
		}

		return array('ok' => true, 'id' => (int) $object->id, 'ignored' => $ignored);
	}

	/**
	 * Counters of the camera and summary of the Victron charge controller, from webcam-portal
	 *
	 * Updates only the columns of the counters and Victron, counters_date,
	 * victron_date and last_telemetry. A counter lower than the saved one with the
	 * same camera serial number is refused for that field (listed in ignored).
	 * A different serial number is recorded in the agenda ("Camera changed").
	 *
	 * @param	int		$id				ID of webcam
	 * @param	array	$request_data	{time, camera: {model, serial, shutter_count, shots_total, power_on_hours, power_cycles}, power: {battery_v, battery_v_min24, soc_pct, charge_state, yield_yesterday_wh, error}}
	 * @phan-param ?array<string,mixed> $request_data
	 * @phpstan-param ?array<string,mixed> $request_data
	 * @return	array	{ok, id, updated, ignored}
	 * @phan-return array<string,mixed>
	 * @phpstan-return array<string,mixed>
	 *
	 * @url	POST {id}/counters
	 *
	 * @throws RestException 400 Bad request
	 * @throws RestException 403 Not allowed
	 * @throws RestException 404 Not found
	 * @throws RestException 500 System error
	 */
	public function postCounters($id, $request_data = null)
	{
		$object = $this->_fetchForPortal($id);
		$in = is_array($request_data) ? $request_data : array();
		$now = dol_now();
		$time = $this->_inTime($in, 'time', $now);

		$cam = (isset($in['camera']) && is_array($in['camera'])) ? $in['camera'] : array();
		$pow = (isset($in['power']) && is_array($in['power'])) ? $in['power'] : array();
		if (!$cam && !$pow) {
			throw new RestException(400, 'camera or power missing');
		}

		$counters = array(
			'shutter_count' => $this->_inNumber($cam, 'shutter_count', 0, 2147483647, 'camera.', true),
			'shots_total' => $this->_inNumber($cam, 'shots_total', 0, 2147483647, 'camera.', true),
			'camera_hours' => $this->_inNumber($cam, 'power_on_hours', 0, 9999999, 'camera.'),
			'camera_power_cycles' => $this->_inNumber($cam, 'power_cycles', 0, 2147483647, 'camera.', true),
		);
		$serial = $this->_inString($cam, 'serial', 64);
		$power = array(
			'battery_v' => $this->_inNumber($pow, 'battery_v', 0, 100, 'power.'),
			'battery_v_min24' => $this->_inNumber($pow, 'battery_v_min24', 0, 100, 'power.'),
			'battery_soc' => $this->_inNumber($pow, 'soc_pct', 0, 100, 'power.'),
			'charge_state' => $this->_inChargeState($pow, 'charge_state'),
			'solar_wh_yesterday' => $this->_inNumber($pow, 'yield_yesterday_wh', 0, 2147483647, 'power.', true),
			'victron_error' => $this->_inNumber($pow, 'error', 0, 32767, 'power.', true),
		);

		$set = array();
		$updated = array();
		$ignored = array();
		$events = $this->_applyCounters($object, $serial, $counters, $this->_inString($cam, 'model', 150), $set, $updated, $ignored);
		if ($updated) {
			$set['counters_date'] = $time;
		}
		$events = array_merge($events, $this->_applyPower($object, $power, $set, $updated));
		if (array_intersect(array_keys($power), $updated)) {
			$set['victron_date'] = $time;
		}
		$set['last_telemetry'] = $now;

		$this->_updateColumns($object, $set);
		foreach ($events as $text) {
			$this->_addEvent($object, $text);
		}

		return array('ok' => true, 'id' => (int) $object->id, 'updated' => $updated, 'ignored' => $ignored);
	}

	/**
	 * Event from webcam-portal, recorded in the agenda of the webcam
	 *
	 * Only important events (severity warning or more) and interventions should
	 * be sent. A second request with the same portal_event_id returns the event
	 * already created.
	 *
	 * @param	int		$id				ID of webcam
	 * @param	array	$request_data	{type, severity, time, label, note, portal_event_id, url}
	 * @phan-param ?array<string,mixed> $request_data
	 * @phpstan-param ?array<string,mixed> $request_data
	 * @return	array	{ok, id, actioncomm_id, duplicate}
	 * @phan-return array<string,mixed>
	 * @phpstan-return array<string,mixed>
	 *
	 * @url	POST {id}/events
	 *
	 * @throws RestException 400 Bad request
	 * @throws RestException 403 Not allowed
	 * @throws RestException 404 Not found
	 * @throws RestException 500 System error
	 * @throws RestException 501 Module Agenda not enabled
	 */
	public function postEvents($id, $request_data = null)
	{
		$object = $this->_fetchForPortal($id);
		if (!isModEnabled('agenda')) {
			throw new RestException(501, 'Module Agenda (events) is not enabled');
		}
		if (!DolibarrApiAccess::$user->hasRight('agenda', 'myactions', 'create')) {
			throw new RestException(403, 'Permission to create events in the agenda missing');
		}
		$in = is_array($request_data) ? $request_data : array();

		$type = $this->_inString($in, 'type', 40);
		if ($type === null || !preg_match('/^[a-z][a-z0-9_.]{1,39}$/', $type)) {
			throw new RestException(400, 'type missing or invalid');
		}
		$severity = $this->_inString($in, 'severity', 10);
		if (!in_array($severity, array('debug', 'info', 'notice', 'warning', 'error', 'critical'), true)) {
			throw new RestException(400, 'severity missing or invalid (debug, info, notice, warning, error, critical)');
		}
		$label = $this->_inString($in, 'label', 200);
		if ($label === null) {
			throw new RestException(400, 'label missing');
		}
		$note = isset($in['note']) && !is_array($in['note']) ? dol_substr(trim((string) $in['note']), 0, 5000) : '';
		$url = $this->_inString($in, 'url', 255);
		if ($url !== null && (!preg_match('/^https?:\/\//i', $url) || !filter_var($url, FILTER_VALIDATE_URL))) {
			throw new RestException(400, 'url invalid');
		}
		$refext = $this->_inPortalEventId($in);
		$time = $this->_inTime($in, 'time', dol_now());

		if ($refext) {
			$existing = $this->_findActionComm($object, $refext);
			if ($existing) {
				return array('ok' => true, 'id' => (int) $object->id, 'actioncomm_id' => $existing, 'duplicate' => true);
			}
		}

		$text = $note;
		$text .= ($text !== '' ? "\n\n" : '').$type.' ('.$severity.')';
		if ($url) {
			$text .= "\n".$url;
		}
		$acid = $this->_addEvent($object, $label, 'AC_WEBCAMS_PORTAL', $time, $refext, $text);
		if ($acid <= 0) {
			throw new RestException(500, 'Error creating event');
		}
		return array('ok' => true, 'id' => (int) $object->id, 'actioncomm_id' => $acid, 'duplicate' => false);
	}

	/**
	 * Open a ticket (module Ticket) for a fault of the webcam
	 *
	 * The ticket is linked to the customer of the webcam and to the webcam
	 * (block "Linked objects" of the card). A second request with the same
	 * portal_event_id returns the ticket already created. With portal_rule, if
	 * a ticket linked to the webcam for the same rule is still open (status not
	 * closed or canceled), no ticket is created: the message is added to that
	 * ticket as a private message and the answer has duplicate = true and
	 * reason = open_for_rule.
	 *
	 * @param	int		$id				ID of webcam
	 * @param	array	$request_data	{subject, message, type_code, severity_code, category_code, portal_event_id, portal_rule, portal_alert_id, due_date}
	 * @phan-param ?array<string,mixed> $request_data
	 * @phpstan-param ?array<string,mixed> $request_data
	 * @return	array	{ok, ticket_id, ticket_ref, duplicate, status, reason}
	 * @phan-return array<string,mixed>
	 * @phpstan-return array<string,mixed>
	 *
	 * @url	POST {id}/tickets
	 *
	 * @throws RestException 400 Bad request
	 * @throws RestException 403 Not allowed
	 * @throws RestException 404 Not found
	 * @throws RestException 500 System error
	 * @throws RestException 501 Module Ticket not enabled
	 */
	public function postTickets($id, $request_data = null)
	{
		$object = $this->_fetchForPortal($id);
		$user = $this->_checkTicketAccess();
		$in = is_array($request_data) ? $request_data : array();

		$subject = $this->_inString($in, 'subject', 255);
		if ($subject === null) {
			throw new RestException(400, 'subject missing');
		}
		$message = isset($in['message']) && !is_array($in['message']) ? trim((string) $in['message']) : '';
		if ($message === '') {
			throw new RestException(400, 'message missing');
		}
		$refext = $this->_inPortalEventId($in);
		$rule = $this->_inString($in, 'portal_rule', 255);
		if ($rule !== null && (strlen($rule) > 64 || !preg_match('/^[a-z][a-z0-9_]*\.[a-z0-9_.]+$/', $rule))) {
			throw new RestException(400, 'portal_rule invalid (rule id like enclosure.heater, max 64 characters)');
		}
		$alertid = null;
		if (isset($in['portal_alert_id']) && $in['portal_alert_id'] !== '') {
			$v = $in['portal_alert_id'];
			if (is_bool($v) || !is_numeric($v) || (int) $v <= 0 || floor((float) $v) != $v) {
				throw new RestException(400, 'portal_alert_id invalid');
			}
			$alertid = (int) $v;
		}
		$due = $this->_inString($in, 'due_date', 10);
		$duets = 0;
		if ($due !== null) {
			if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $due, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
				throw new RestException(400, 'due_date invalid (YYYY-MM-DD)');
			}
			$duets = dol_mktime(12, 0, 0, (int) $m[2], (int) $m[3], (int) $m[1]);
		}
		$html = $this->_ticketHtml($message);

		if ($refext) {
			// llx_ticket has no ref_ext: the reference is kept in extraparams
			$sql = "SELECT rowid, ref, fk_statut FROM ".$this->db->prefix()."ticket";
			$sql .= " WHERE extraparams LIKE '%".$this->db->escape($this->db->escapeforlike('"ref_ext":"'.$refext.'"'))."%' AND entity IN (".getEntity('ticket').")";
			$resql = $this->db->query($sql);
			if ($resql && ($obj = $this->db->fetch_object($resql))) {
				return array('ok' => true, 'ticket_id' => (int) $obj->rowid, 'ticket_ref' => $obj->ref, 'duplicate' => true, 'status' => (int) $obj->fk_statut);
			}
		}

		// One open ticket for webcam and rule
		if ($rule !== null) {
			$open = $this->_findOpenTicketForRule($object, $rule);
			if ($open) {
				if (!$refext || !$this->_findTicketMessage($open->id, $refext)) {
					$this->_addTicketMessage($open, $html, $refext);
				}
				return array('ok' => true, 'ticket_id' => (int) $open->id, 'ticket_ref' => $open->ref, 'duplicate' => true, 'reason' => 'open_for_rule', 'status' => (int) $open->status);
			}
		}

		$ticket = new Ticket($this->db);
		$ticket->subject = $subject;
		$ticket->message = $html.$this->_ticketFooter($object, $html, $duets);
		$ticket->type_code = $this->_dictCode('c_ticket_type', $this->_inString($in, 'type_code', 32));
		$ticket->severity_code = $this->_dictCode('c_ticket_severity', $this->_inString($in, 'severity_code', 32));
		$ticket->category_code = $this->_dictCode('c_ticket_category', $this->_inString($in, 'category_code', 32));
		$ticket->fk_soc = $object->fk_soc > 0 ? (int) $object->fk_soc : 0;
		$ticket->socid = $ticket->fk_soc;
		$ticket->origin_email = '';
		$ticket->track_id = generate_random_id(16);
		$ticket->ref = $ticket->getDefaultRef();
		$ticket->datec = dol_now();
		$ticket->context['caller'] = 'webcams';

		$extra = array();
		if ($refext) {
			$extra['ref_ext'] = $refext;
		}
		if ($rule !== null) {
			$extra['portal_rule'] = $rule;
		}
		if ($alertid !== null) {
			$extra['portal_alert_id'] = $alertid;
		}
		if ($due !== null) {
			$extra['due_date'] = $due;
		}
		// llx_ticket.extraparams is varchar(255) (setExtraParameters cuts at 250)
		if (strlen((string) json_encode($extra)) > 250) {
			throw new RestException(400, 'portal_rule, portal_alert_id and due_date too long for the ticket');
		}

		$this->db->begin();
		if ($ticket->create($user) <= 0) {
			$this->db->rollback();
			throw new RestException(500, 'Error creating ticket', array_merge(array($ticket->error), $ticket->errors));
		}
		if ($extra) {
			$ticket->extraparams = $extra;
			if ($ticket->setExtraParameters() <= 0) {
				$this->db->rollback();
				throw new RestException(500, 'Error saving portal_event_id in ticket', array($ticket->error));
			}
		}
		if ($ticket->add_object_linked($object->getElementType(), $object->id) <= 0) {
			$this->db->rollback();
			throw new RestException(500, 'Error linking ticket to webcam', array_merge(array($ticket->error), $ticket->errors));
		}
		$this->db->commit();

		return array('ok' => true, 'ticket_id' => (int) $ticket->id, 'ticket_ref' => $ticket->ref, 'duplicate' => false, 'status' => Ticket::STATUS_NOT_READ);
	}

	/**
	 * Add a message to a ticket opened by webcam-portal, or close it
	 *
	 * Only tickets linked to the webcam and opened by the portal (extraparams
	 * ref_ext portal:...). The message is added as a private message. With
	 * close = 1 the ticket is closed only if nobody is working on it (status
	 * not read or read); otherwise only the message is added and the answer has
	 * closed = false and close_refused = in_progress. The severity can only be
	 * raised. A second request with the same portal_event_id adds nothing.
	 *
	 * @param	int		$id				ID of webcam
	 * @param	int		$ticket_id		ID of ticket
	 * @param	array	$request_data	{message, close, severity_code, portal_event_id}
	 * @phan-param ?array<string,mixed> $request_data
	 * @phpstan-param ?array<string,mixed> $request_data
	 * @return	array	{ok, ticket_id, status, closed, duplicate, close_refused}
	 * @phan-return array<string,mixed>
	 * @phpstan-return array<string,mixed>
	 *
	 * @url	PUT {id}/tickets/{ticket_id}
	 *
	 * @throws RestException 400 Bad request
	 * @throws RestException 403 Not allowed
	 * @throws RestException 404 Not found
	 * @throws RestException 409 Ticket already closed or canceled
	 * @throws RestException 500 System error
	 * @throws RestException 501 Module Ticket not enabled
	 */
	public function putTicket($id, $ticket_id, $request_data = null)
	{
		$object = $this->_fetchForPortal($id);
		$user = $this->_checkTicketAccess();
		$in = is_array($request_data) ? $request_data : array();

		$ticket = new Ticket($this->db);
		if ((int) $ticket_id <= 0 || $ticket->fetch((int) $ticket_id) <= 0) {
			throw new RestException(404, 'Ticket not found');
		}
		if (!in_array((int) $ticket->entity, array_map('intval', explode(',', getEntity('ticket'))), true)) {
			throw new RestException(404, 'Ticket not found');
		}
		$extra = is_array($ticket->extraparams) ? $ticket->extraparams : array();
		if (empty($extra['ref_ext']) || strpos((string) $extra['ref_ext'], 'portal:') !== 0 || !$this->_ticketLinkedToWebcam($ticket->id, $object)) {
			throw new RestException(403, 'Ticket not opened by webcam-portal for this webcam');
		}

		$message = isset($in['message']) && !is_array($in['message']) ? trim((string) $in['message']) : '';
		if ($message === '') {
			throw new RestException(400, 'message missing');
		}
		$close = false;
		if (array_key_exists('close', $in) && $in['close'] !== null) {
			if (!in_array($in['close'], array(0, 1, '0', '1', true, false), true)) {
				throw new RestException(400, 'close must be 0 or 1');
			}
			$close = in_array($in['close'], array(1, '1', true), true);
		}
		$severity = $this->_inString($in, 'severity_code', 32);
		$refext = $this->_inPortalEventId($in);
		$status = (int) $ticket->status;

		// Same portal_event_id: nothing to add (retry after a lost answer)
		if ($refext && $this->_findTicketMessage($ticket->id, $refext)) {
			return array('ok' => true, 'ticket_id' => (int) $ticket->id, 'status' => $status, 'closed' => $status == Ticket::STATUS_CLOSED, 'duplicate' => true);
		}
		if ($status == Ticket::STATUS_CLOSED || $status == Ticket::STATUS_CANCELED) {
			throw new RestException(409, 'Ticket already closed or canceled (status '.$status.')');
		}

		$this->db->begin();
		$this->_addTicketMessage($ticket, $this->_ticketHtml($message), $refext);

		// Severity: only upwards (position in the dictionary)
		if ($severity !== null) {
			$code = $this->_dictCode('c_ticket_severity', $severity);
			if ($code === $severity && $code !== (string) $ticket->severity_code
				&& $this->_severityPos($code) > $this->_severityPos((string) $ticket->severity_code)) {
				$sql = "UPDATE ".$this->db->prefix()."ticket SET severity_code = '".$this->db->escape($code)."' WHERE rowid = ".((int) $ticket->id);
				if (!$this->db->query($sql)) {
					$this->db->rollback();
					throw new RestException(500, 'Error updating severity: '.$this->db->lasterror());
				}
				$ticket->severity_code = $code;
			}
		}

		$res = array('ok' => true, 'ticket_id' => (int) $ticket->id, 'status' => $status, 'closed' => false, 'duplicate' => false);
		if ($close) {
			if ($status == Ticket::STATUS_NOT_READ || $status == Ticket::STATUS_READ) {
				if ($ticket->close($user) <= 0) {
					$this->db->rollback();
					throw new RestException(500, 'Error closing ticket', array_merge(array($ticket->error), $ticket->errors));
				}
				$res['status'] = Ticket::STATUS_CLOSED;
				$res['closed'] = true;
			} else {
				// Somebody is working on it: the operator closes it
				$res['close_refused'] = 'in_progress';
			}
		}
		$this->db->commit();

		return $res;
	}

	/**
	 * What webcam-portal reads from Dolibarr for a webcam, in one call
	 *
	 * Customer, publication data, availability commitment of the rental
	 * contract (sla, with the defaults of the setup applied to the empty
	 * fields), shutter thresholds and links to rttys.
	 *
	 * @param	int		$id		ID of webcam
	 * @return	array	{ok, id, ref, status, customer, portal, sla, shutter, rttys, plan, options, archives}
	 * @phan-return array<string,mixed>
	 * @phpstan-return array<string,mixed>
	 *
	 * @url	GET {id}/portal
	 *
	 * @throws RestException 403 Not allowed
	 * @throws RestException 404 Not found
	 */
	public function getPortal($id)
	{
		$object = $this->_fetchForPortal($id);

		$customer = null;
		if ($object->fk_soc > 0) {
			$sql = "SELECT rowid, nom FROM ".$this->db->prefix()."societe WHERE rowid = ".((int) $object->fk_soc);
			$resql = $this->db->query($sql);
			if ($resql && ($obj = $this->db->fetch_object($resql))) {
				$customer = array('id' => (int) $obj->rowid, 'name' => (string) $obj->nom);
			}
		}
		$sla = webcamsSla($object);
		unset($sla['defaults']);
		$used = webcamsShotsUsed($object);
		$rttys = webcamsRttysUrls($object->ref);
		$planopt = webcamsPlanOptions($this->db, $object);
		$archives = array();
		foreach (webcamsArchives($this->db, $object->id, true) as $a) {
			$archives[] = array(
				'socid' => (int) $a->fk_soc,
				'rental_start' => $a->rental_start ? substr((string) $a->rental_start, 0, 10) : null,
				'rental_end' => substr((string) $a->rental_end, 0, 10),
				'archive_until' => substr((string) $a->archive_until, 0, 10),
			);
		}

		return array(
			'ok' => true,
			'id' => (int) $object->id,
			'ref' => (string) $object->ref,
			'status' => (int) $object->status,
			'customer' => $customer,
			'portal' => array(
				'portal_slug' => $object->portal_slug !== '' ? $object->portal_slug : null,
				'url_current' => $object->url_current !== '' ? $object->url_current : null,
				'windy_published' => (int) $object->windy_published,
				'windy_id' => $object->windy_id !== '' ? $object->windy_id : null,
			),
			'sla' => $sla,
			'shutter' => array(
				'life' => webcamsShutterLife(),
				'warn_pct' => webcamsShutterWarnPct(),
				'used' => $used,
				'level' => webcamsShutterLevel($used),
			),
			'rttys' => $rttys ? $rttys : new stdClass(),
			// 1.4 (API 2.2): price list, options, archives after the rental
			'plan' => $planopt['plan'],
			'options' => $planopt['options'],
			'archives' => $archives,
		);
	}

	/**
	 * Monthly availability of the webcam, computed by webcam-portal
	 *
	 * Inserts or updates the row of the month (fk_webcam, period). A final row
	 * (final = 1) cannot be changed any more (409: corrections are made by hand
	 * in Dolibarr). With final = 1 the last availability of the webcam is
	 * updated (if the month is the most recent) and an event is added in the
	 * agenda (code AC_WEBCAMS_SLA if the commitment is not met).
	 *
	 * @param	int		$id				ID of webcam
	 * @param	array	$request_data	{period, availability_pct, expected_hours, excluded_hours, available_hours, incidents, downtime_hours, response_breaches, sla_enabled, target_pct, sla_met, final}
	 * @phan-param ?array<string,mixed> $request_data
	 * @phpstan-param ?array<string,mixed> $request_data
	 * @return	array	{ok, id, period, final, updated, created}
	 * @phan-return array<string,mixed>
	 * @phpstan-return array<string,mixed>
	 *
	 * @url	POST {id}/availability
	 *
	 * @throws RestException 400 Bad request
	 * @throws RestException 403 Not allowed
	 * @throws RestException 404 Not found
	 * @throws RestException 409 Month already final
	 * @throws RestException 500 System error
	 */
	public function postAvailability($id, $request_data = null)
	{
		$object = $this->_fetchForPortal($id);
		$in = is_array($request_data) ? $request_data : array();

		$period = $this->_inPeriod($in, 'period');
		if ($period === null) {
			throw new RestException(400, 'period missing (YYYY-MM)');
		}
		if (strcmp($period, dol_print_date(dol_now() + 86400, '%Y-%m', 'gmt')) > 0) {
			throw new RestException(400, 'period in the future');
		}
		$bool = function ($k, $default) use ($in) {
			if (!array_key_exists($k, $in) || $in[$k] === null || $in[$k] === '') {
				return $default;
			}
			if (!in_array($in[$k], array(0, 1, '0', '1', true, false), true)) {
				throw new RestException(400, $k.' must be 0 or 1');
			}
			return in_array($in[$k], array(1, '1', true), true) ? 1 : 0;
		};
		$expected = $this->_inNumber($in, 'expected_hours', 0, 744, '', true);
		if ($expected === null) {
			throw new RestException(400, 'expected_hours missing');
		}
		$excluded = (int) $this->_inNumber($in, 'excluded_hours', 0, 744, '', true);
		$available = (int) $this->_inNumber($in, 'available_hours', 0, 744, '', true);
		if ($excluded > $expected || $available > $expected - $excluded) {
			throw new RestException(400, 'hours not consistent (excluded_hours <= expected_hours, available_hours <= expected_hours - excluded_hours)');
		}
		if (array_key_exists('availability_pct', $in)) {
			$pct = $this->_inNumber($in, 'availability_pct', 0, 100);
		} else {
			$pct = ($expected - $excluded) > 0 ? round($available * 100 / ($expected - $excluded), 2) : null;
		}
		$incidents = (int) $this->_inNumber($in, 'incidents', 0, 65535, '', true);
		$downtime = $this->_inNumber($in, 'downtime_hours', 0, 744);
		$downtime = $downtime === null ? 0.0 : round((float) $downtime, 1);
		$breaches = (int) $this->_inNumber($in, 'response_breaches', 0, 65535, '', true);
		$sla = webcamsSla($object);
		$slaenabled = $bool('sla_enabled', $sla['enabled'] ? 1 : 0);
		$target = $this->_inNumber($in, 'target_pct', 0, 100);
		if ($target === null && $slaenabled) {
			$target = $sla['target_pct'];
		}
		$slamet = $bool('sla_met', null);
		if (!$slaenabled) {
			$slamet = null;
		} elseif ($slamet === null && $pct !== null) {
			$slamet = ($pct >= $target && $breaches == 0) ? 1 : 0;
		}
		$final = $bool('final', 0);

		$table = $this->db->prefix().'webcams_availability';
		$sql = "SELECT rowid, final FROM ".$table." WHERE fk_webcam = ".((int) $object->id)." AND period = '".$this->db->escape($period)."'";
		$resql = $this->db->query($sql);
		if (!$resql) {
			throw new RestException(500, 'Error reading availability: '.$this->db->lasterror());
		}
		$row = $this->db->fetch_object($resql);
		if ($row && (int) $row->final) {
			throw new RestException(409, 'Availability of '.$period.' already final');
		}

		$num = function ($v) {
			return $v === null ? 'NULL' : (string) $v;
		};
		$values = array(
			'fk_soc' => $object->fk_soc > 0 ? (string) ((int) $object->fk_soc) : 'NULL',
			'availability_pct' => $num($pct),
			'expected_hours' => (string) ((int) $expected),
			'excluded_hours' => (string) $excluded,
			'available_hours' => (string) $available,
			'incidents' => (string) $incidents,
			'downtime_hours' => (string) $downtime,
			'response_breaches' => (string) $breaches,
			'sla_enabled' => (string) $slaenabled,
			'target_pct' => $num($target),
			'sla_met' => $num($slamet),
			'final' => (string) $final,
		);
		$this->db->begin();
		if ($row) {
			$parts = array();
			foreach ($values as $k => $v) {
				$parts[] = $k.' = '.$v;
			}
			$sql = "UPDATE ".$table." SET ".implode(', ', $parts)." WHERE rowid = ".((int) $row->rowid);
		} else {
			$sql = "INSERT INTO ".$table." (entity, fk_webcam, period, ".implode(', ', array_keys($values)).", date_creation)";
			$sql .= " VALUES (".((int) (empty($object->entity) ? 1 : $object->entity)).", ".((int) $object->id).", '".$this->db->escape($period)."', ".implode(', ', $values).", '".$this->db->idate(dol_now())."')";
		}
		if (!$this->db->query($sql)) {
			$this->db->rollback();
			throw new RestException(500, 'Error saving availability: '.$this->db->lasterror());
		}

		if ($final) {
			if ((string) $object->availability_period === '' || strcmp($period, (string) $object->availability_period) >= 0) {
				$this->_updateColumns($object, array('availability_pct' => $pct === null ? null : (float) $pct, 'availability_period' => $period));
			}
			$text = $this->_trans('WebcamEventAvailability', webcamsFormatPeriod($period), $pct === null ? $this->_trans('WebcamAvailabilityNoHours') : webcamsFormatPct($pct));
			if ($slaenabled) {
				$text .= ' ('.$this->_trans($slamet === 0 ? 'WebcamEventSlaNotMet' : ($slamet === 1 ? 'WebcamEventSlaMet' : 'WebcamEventSlaUnknown'), webcamsFormatPct($target)).')';
			}
			$note = $text."\n".$this->_trans('WebcamAvailabilityEventNote', $available, $expected - $excluded, $excluded, $incidents, $breaches);
			$this->_addEvent($object, $text, ($slaenabled && $slamet === 0) ? 'AC_WEBCAMS_SLA' : 'AC_WEBCAMS_PORTAL', 0, '', $note);
		}
		$this->db->commit();

		return array('ok' => true, 'id' => (int) $object->id, 'period' => $period, 'final' => (bool) $final, 'updated' => (bool) $row, 'created' => !$row);
	}

	/**
	 * Monthly availability registered for the webcam (from - to, oldest first)
	 *
	 * @param	int		$id		ID of webcam
	 * @param	string	$from	First month YYYY-MM (optional)
	 * @param	string	$to		Last month YYYY-MM (optional)
	 * @return	array	{ok, id, months: [...]}
	 * @phan-return array<string,mixed>
	 * @phpstan-return array<string,mixed>
	 *
	 * @url	GET {id}/availability
	 *
	 * @throws RestException 400 Bad request
	 * @throws RestException 403 Not allowed
	 * @throws RestException 404 Not found
	 */
	public function getAvailability($id, $from = '', $to = '')
	{
		$object = $this->_fetchForPortal($id);
		$from = $this->_inPeriod(array('from' => $from), 'from');
		$to = $this->_inPeriod(array('to' => $to), 'to');
		if ($from !== null && $to !== null && strcmp($from, $to) > 0) {
			throw new RestException(400, 'from after to');
		}
		$months = array();
		foreach (array_reverse(webcamsAvailabilityMonths($this->db, $object->id, (string) $from, (string) $to)) as $r) {
			$months[] = array(
				'period' => (string) $r->period,
				'availability_pct' => $r->availability_pct === null ? null : (float) $r->availability_pct,
				'expected_hours' => (int) $r->expected_hours,
				'excluded_hours' => (int) $r->excluded_hours,
				'available_hours' => (int) $r->available_hours,
				'incidents' => (int) $r->incidents,
				'downtime_hours' => (float) $r->downtime_hours,
				'response_breaches' => (int) $r->response_breaches,
				'sla_enabled' => (int) $r->sla_enabled,
				'target_pct' => $r->target_pct === null ? null : (float) $r->target_pct,
				'sla_met' => $r->sla_met === null ? null : (int) $r->sla_met,
				'final' => (int) $r->final,
				'customer_id' => $r->fk_soc === null ? null : (int) $r->fk_soc,
			);
		}
		return array('ok' => true, 'id' => (int) $object->id, 'months' => $months);
	}

	/**
	 * Open a draft intervention (module Interventions) for a visit of the technician
	 *
	 * Created for the customer of the webcam (required) and linked to the webcam.
	 * A second request with the same portal_event_id returns the intervention
	 * already created.
	 *
	 * @param	int		$id				ID of webcam
	 * @param	array	$request_data	{description, date (YYYY-MM-DD), portal_event_id, lines: [{description, duration_min}]}
	 * @phan-param ?array<string,mixed> $request_data
	 * @phpstan-param ?array<string,mixed> $request_data
	 * @return	array	{ok, fichinter_id, fichinter_ref}
	 * @phan-return array<string,mixed>
	 * @phpstan-return array<string,mixed>
	 *
	 * @url	POST {id}/interventions
	 *
	 * @throws RestException 400 Bad request
	 * @throws RestException 403 Not allowed
	 * @throws RestException 404 Not found
	 * @throws RestException 500 System error
	 * @throws RestException 501 Module Interventions not enabled
	 */
	public function postInterventions($id, $request_data = null)
	{
		$object = $this->_fetchForPortal($id);
		if (!isModEnabled('intervention')) {
			throw new RestException(501, 'Module Interventions is not enabled');
		}
		$user = DolibarrApiAccess::$user;
		if (!$user->hasRight('ficheinter', 'creer')) {
			throw new RestException(403, 'Permission to create interventions missing');
		}
		if (empty($object->fk_soc) || $object->fk_soc <= 0) {
			throw new RestException(400, 'The webcam has no customer: link it to a third party first');
		}
		require_once DOL_DOCUMENT_ROOT.'/fichinter/class/fichinter.class.php';
		$in = is_array($request_data) ? $request_data : array();

		$description = isset($in['description']) && !is_array($in['description']) ? trim((string) $in['description']) : '';
		if ($description === '') {
			throw new RestException(400, 'description missing');
		}
		$date = dol_now();
		if (isset($in['date']) && $in['date'] !== null && $in['date'] !== '') {
			if (!is_string($in['date']) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $in['date'], $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
				throw new RestException(400, 'date invalid (YYYY-MM-DD)');
			}
			$date = dol_mktime(12, 0, 0, (int) $m[2], (int) $m[3], (int) $m[1]);
		}
		$lines = array();
		if (isset($in['lines'])) {
			if (!is_array($in['lines'])) {
				throw new RestException(400, 'lines must be an array');
			}
			foreach (array_values($in['lines']) as $i => $l) {
				$ldesc = (is_array($l) && isset($l['description']) && !is_array($l['description'])) ? trim((string) $l['description']) : '';
				$lmin = (is_array($l) && isset($l['duration_min'])) ? $l['duration_min'] : 0;
				if ($ldesc === '' || !is_numeric($lmin) || $lmin < 0 || $lmin > 100000) {
					throw new RestException(400, 'lines['.$i.'] invalid: description and duration_min (minutes) required');
				}
				$lines[] = array($ldesc, (int) round($lmin * 60));
			}
		}
		$refext = $this->_inPortalEventId($in);

		if ($refext) {
			$sql = "SELECT rowid, ref FROM ".$this->db->prefix()."fichinter";
			$sql .= " WHERE ref_ext = '".$this->db->escape($refext)."' AND entity IN (".getEntity('intervention').")";
			$resql = $this->db->query($sql);
			if ($resql && ($obj = $this->db->fetch_object($resql))) {
				return array('ok' => true, 'fichinter_id' => (int) $obj->rowid, 'fichinter_ref' => $obj->ref, 'duplicate' => true);
			}
		}

		$fi = new Fichinter($this->db);
		$fi->socid = (int) $object->fk_soc;
		$fi->description = $description;
		$fi->entity = (int) $object->entity;
		$fi->status = 0;
		$fi->datec = dol_now();
		$fi->dateo = $date;
		$fi->context['caller'] = 'webcams';

		$this->db->begin();
		if ($fi->create($user) <= 0) {
			$this->db->rollback();
			throw new RestException(500, 'Error creating intervention', array_merge(array($fi->error), $fi->errors));
		}
		// Fichinter::create() does not save ref_ext
		if ($refext && !$this->db->query("UPDATE ".$this->db->prefix()."fichinter SET ref_ext = '".$this->db->escape($refext)."' WHERE rowid = ".((int) $fi->id))) {
			$this->db->rollback();
			throw new RestException(500, 'Error saving portal_event_id in intervention: '.$this->db->lasterror());
		}
		foreach ($lines as $l) {
			if ($fi->addline($user, $fi->id, $l[0], $date, $l[1]) <= 0) {
				$this->db->rollback();
				throw new RestException(500, 'Error adding line to intervention', array_merge(array($fi->error), $fi->errors));
			}
		}
		if ($fi->add_object_linked($object->getElementType(), $object->id) <= 0) {
			$this->db->rollback();
			throw new RestException(500, 'Error linking intervention to webcam', array_merge(array($fi->error), $fi->errors));
		}
		$this->db->commit();
		$fi->fetch($fi->id);

		return array('ok' => true, 'fichinter_id' => (int) $fi->id, 'fichinter_ref' => $fi->ref, 'duplicate' => false);
	}

	/**
	 * Attach a PDF to the documents of the webcam
	 *
	 * The file is saved in the same directory as the Documents tab of the webcam
	 * and indexed so it is shown there.
	 *
	 * @param	int		$id				ID of webcam
	 * @param	array	$request_data	{filename, filecontent (base64), fileencoding ("base64"), overwrite (0/1), description, sha256, doc_type, period, portal_doc_id, send_email: {template, link, resend}}
	 * @phan-param ?array<string,mixed> $request_data
	 * @phpstan-param ?array<string,mixed> $request_data
	 * @return	array	{ok, id, file, email}
	 * @phan-return array<string,mixed>
	 * @phpstan-return array<string,mixed>
	 *
	 * @url	POST {id}/documents
	 *
	 * @throws RestException 400 Bad request
	 * @throws RestException 403 Not allowed
	 * @throws RestException 404 Not found
	 * @throws RestException 409 File already exists
	 * @throws RestException 413 File too large
	 * @throws RestException 422 sha256 does not match
	 * @throws RestException 500 System error
	 */
	public function postDocuments($id, $request_data = null)
	{
		global $conf;

		$object = $this->_fetchForPortal($id);
		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
		$in = is_array($request_data) ? $request_data : array();

		$filename = dol_sanitizeFileName($this->_inString($in, 'filename', 200) ?? '');
		if ($filename === '' || !preg_match('/\.pdf$/i', $filename) || $filename[0] === '.') {
			throw new RestException(400, 'filename missing or not a .pdf');
		}
		$encoding = isset($in['fileencoding']) ? (string) $in['fileencoding'] : 'base64';
		if ($encoding !== 'base64') {
			throw new RestException(400, 'fileencoding must be base64');
		}
		if (empty($in['filecontent']) || !is_string($in['filecontent'])) {
			throw new RestException(400, 'filecontent missing');
		}
		if (strlen($in['filecontent']) > 14 * 1024 * 1024) {
			throw new RestException(413, 'File larger than 10 MB');
		}
		$content = base64_decode($in['filecontent'], true);
		if ($content === false || $content === '') {
			throw new RestException(400, 'filecontent is not valid base64');
		}
		if (strlen($content) > 10 * 1024 * 1024) {
			throw new RestException(413, 'File larger than 10 MB');
		}
		if (substr($content, 0, 5) !== '%PDF-') {
			throw new RestException(400, 'The file is not a PDF');
		}
		if (!empty($in['sha256'])) {
			if (!is_string($in['sha256']) || !hash_equals(strtolower($in['sha256']), hash('sha256', $content))) {
				throw new RestException(422, 'sha256 does not match the file');
			}
		}
		$overwrite = !empty($in['overwrite']);
		$description = $this->_inString($in, 'description', 255);

		// 1.4 (API 2.2): type, period, idempotence of the email, email to the customer
		$doctype = null;
		if (isset($in['doc_type']) && $in['doc_type'] !== '') {
			$doctype = is_string($in['doc_type']) ? $in['doc_type'] : '';
			if (!in_array($doctype, array('monthly_report', 'weather_certificate', 'weather_register', 'other'), true)) {
				throw new RestException(400, 'doc_type must be monthly_report, weather_certificate, weather_register or other');
			}
		}
		$period = null;
		if (isset($in['period']) && $in['period'] !== '') {
			$period = is_string($in['period']) ? $in['period'] : '';
			if (!preg_match('/^(19|20)\d{2}-(0[1-9]|1[0-2])$/', $period) && !$this->_isDayRange($period)) {
				throw new RestException(400, 'period invalid (YYYY-MM or YYYY-MM-DD_YYYY-MM-DD)');
			}
		}
		$portaldocid = null;
		if (isset($in['portal_doc_id']) && $in['portal_doc_id'] !== '') {
			$portaldocid = is_scalar($in['portal_doc_id']) ? (string) $in['portal_doc_id'] : '';
			if (!preg_match('/^[a-z][a-z0-9_]{0,31}:[A-Za-z0-9_.-]{1,64}$/', $portaldocid)) {
				throw new RestException(400, 'portal_doc_id invalid (report:<id> or certificate:<id>)');
			}
		}
		$sendemail = null;
		if (isset($in['send_email']) && $in['send_email'] !== '' && $in['send_email'] !== false) {
			if (!is_array($in['send_email'])) {
				throw new RestException(400, 'send_email must be an object {template, link, resend}');
			}
			$se = $in['send_email'];
			$tpl = isset($se['template']) && $se['template'] !== '' ? $se['template'] : ($doctype === 'monthly_report' ? 'report' : (in_array($doctype, array('weather_certificate', 'weather_register'), true) ? 'weather' : null));
			if (!in_array($tpl, array('report', 'weather'), true)) {
				throw new RestException(400, 'send_email.template must be report or weather');
			}
			$link = $this->_inString($se, 'link', 255);
			if ($link !== null && (!preg_match('/^https?:\/\//i', $link) || !filter_var($link, FILTER_VALIDATE_URL))) {
				throw new RestException(400, 'send_email.link invalid');
			}
			$sendemail = array('template' => $tpl, 'link' => (string) $link, 'resend' => !empty($se['resend']));
		}

		$entity = (int) ($object->entity ?: $conf->entity);
		$base = !empty($conf->webcams->multidir_output[$entity]) ? $conf->webcams->multidir_output[$entity] : $conf->webcams->dir_output;
		$rel = 'webcam/'.dol_sanitizeFileName($object->ref);
		$dir = $base.'/'.$rel;
		$dest = $dir.'/'.$filename;

		$exists = dol_is_file($dest);
		if ($exists && !$overwrite) {
			throw new RestException(409, 'File '.$filename.' already exists (send overwrite = 1 to replace it)');
		}
		if (dol_mkdir($dir) < 0) {
			throw new RestException(500, 'Cannot create directory of the webcam');
		}
		$tmp = $dest.'.tmp'.getmypid();
		if (file_put_contents($tmp, $content) === false || !@rename($tmp, $dest)) {
			@unlink($tmp);
			throw new RestException(500, 'Cannot write file');
		}
		dolChmod($dest);

		$relindex = preg_replace('/^'.preg_quote(DOL_DATA_ROOT, '/').'\//', '', $dir);
		if ($exists) {
			deleteFilesIntoDatabaseIndex($dir, $filename, '');
		}
		$res = addFileIntoDatabaseIndex($dir, $filename, '', 'api', 0, $object);
		$keywords = $doctype !== null ? 'webcams:'.$doctype.($period !== null ? ':'.$period : '') : '';
		if ($res > 0 && ($description || $keywords !== '')) {
			$parts = array();
			if ($description) {
				$parts[] = "description = '".$this->db->escape($description)."'";
			}
			if ($keywords !== '') {
				$parts[] = "keywords = '".$this->db->escape($keywords)."'";
			}
			$sql = "UPDATE ".$this->db->prefix()."ecm_files SET ".implode(', ', $parts);
			$sql .= " WHERE filepath = '".$this->db->escape($relindex)."' AND filename = '".$this->db->escape($filename)."'";
			$sql .= " AND entity = ".((int) $entity);
			$this->db->query($sql);
		}

		$this->_addEvent($object, $this->_trans($exists ? 'WebcamEventDocumentReplaced' : 'WebcamEventDocumentAdded', $filename), 'AC_WEBCAMS_PORTAL', 0, '', $description ? $description : '');

		$email = null;
		if ($sendemail !== null) {
			$email = $this->_sendDocument($object, $dest, $filename, $sendemail, (string) $period, (string) $portaldocid, $doctype);
		}

		return array('ok' => true, 'id' => (int) $object->id, 'file' => $rel.'/'.$filename, 'email' => $email);
	}

	/**
	 * Daily register of the fingerprints of the photos, from webcam-portal (O2)
	 *
	 * Dolibarr is the second witness: each day is saved once, with the time of
	 * the deposit, and never changed. Per day: new = created; same day and same
	 * day_hash = duplicate; same day with another day_hash = conflict (nothing
	 * is changed, event AC_WEBCAMS_REGISTER in the agenda).
	 *
	 * @param	int		$id				ID of webcam
	 * @param	array	$request_data	{days: [{day, timezone, hash_scheme, day_hash, prev_day_hash, photos, photos_unsynced, classification, reasons, wet_hours, frost_hours, fog_hours}]} (1-31 days)
	 * @phan-param ?array<string,mixed> $request_data
	 * @phpstan-param ?array<string,mixed> $request_data
	 * @return	array	{ok, id, results: [{day, status, registered_at}]}
	 * @phan-return array<string,mixed>
	 * @phpstan-return array<string,mixed>
	 *
	 * @url	POST {id}/register
	 *
	 * @throws RestException 400 Bad request
	 * @throws RestException 403 Not allowed
	 * @throws RestException 404 Not found
	 * @throws RestException 500 System error
	 */
	public function postRegister($id, $request_data = null)
	{
		$object = $this->_fetchForPortal($id);
		$in = is_array($request_data) ? $request_data : array();
		if (empty($in['days']) || !is_array($in['days']) || array_values($in['days']) !== $in['days']) {
			throw new RestException(400, 'days missing (list of 1-31 days)');
		}
		if (count($in['days']) > 31) {
			throw new RestException(400, 'Too many days (max 31 per request)');
		}
		$today = gmdate('Y-m-d', dol_now());
		$hex = '/^[0-9a-f]{64}$/';

		// Check all the days before writing anything
		$days = array();
		foreach ($in['days'] as $i => $d) {
			$p = 'days['.$i.'].';
			if (!is_array($d)) {
				throw new RestException(400, $p.' must be an object');
			}
			$day = isset($d['day']) && is_string($d['day']) ? $d['day'] : '';
			if (!$this->_isDay($day)) {
				throw new RestException(400, $p.'day invalid (YYYY-MM-DD)');
			}
			if (strcmp($day, $today) > 0) {
				throw new RestException(400, $p.'day in the future');
			}
			$tz = isset($d['timezone']) && is_string($d['timezone']) ? $d['timezone'] : '';
			if (!preg_match('/^[A-Za-z0-9_+\/-]{1,64}$/', $tz) || (!in_array($tz, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true) && $tz !== 'UTC')) {
				throw new RestException(400, $p.'timezone invalid (IANA name, e.g. Europe/Rome)');
			}
			$scheme = isset($d['hash_scheme']) && is_string($d['hash_scheme']) ? $d['hash_scheme'] : '';
			if (!preg_match('/^[a-z][a-z0-9]{0,15}$/', $scheme)) {
				throw new RestException(400, $p.'hash_scheme invalid (e.g. vlreg1)');
			}
			$hash = isset($d['day_hash']) && is_string($d['day_hash']) ? $d['day_hash'] : '';
			if (!preg_match($hex, $hash)) {
				throw new RestException(400, $p.'day_hash must be 64 lowercase hexadecimal characters');
			}
			$prev = (isset($d['prev_day_hash']) && $d['prev_day_hash'] !== '') ? $d['prev_day_hash'] : null;
			if ($prev !== null && (!is_string($prev) || !preg_match($hex, $prev))) {
				throw new RestException(400, $p.'prev_day_hash must be 64 lowercase hexadecimal characters or null');
			}
			$photos = $this->_inNumber($d, 'photos', 0, 2147483647, $p, true);
			if ($photos === null) {
				throw new RestException(400, $p.'photos missing');
			}
			$class = $this->_inString($d, 'classification', 16);
			if ($class !== null && !preg_match('/^[a-z][a-z_]{0,15}$/', $class)) {
				throw new RestException(400, $p.'classification invalid (ok, bad_weather, not_certifiable, no_data)');
			}
			$reasons = $this->_inString($d, 'reasons', 32);
			if ($reasons !== null && !preg_match('/^[a-z_]+(,[a-z_]+)*$/', $reasons)) {
				throw new RestException(400, $p.'reasons invalid (e.g. wet,frost,fog,dark)');
			}
			$days[] = array(
				'day' => $day,
				'timezone' => $tz,
				'hash_scheme' => $scheme,
				'day_hash' => $hash,
				'prev_day_hash' => $prev,
				'photos' => (int) $photos,
				'photos_unsynced' => (int) $this->_inNumber($d, 'photos_unsynced', 0, 2147483647, $p, true),
				'classification' => $class,
				'reasons' => $reasons,
				'wet_hours' => $this->_inNumber($d, 'wet_hours', 0, 24, $p, true),
				'frost_hours' => $this->_inNumber($d, 'frost_hours', 0, 24, $p, true),
				'fog_hours' => $this->_inNumber($d, 'fog_hours', 0, 24, $p, true),
			);
		}

		$table = $this->db->prefix().'webcams_register';
		$entity = (int) (empty($object->entity) ? 1 : $object->entity);
		$results = array();
		foreach ($days as $d) {
			$row = $this->_registerRow($object, $d['day']);
			if (!$row) {
				$q = function ($v) {
					return $v === null ? 'NULL' : "'".$this->db->escape((string) $v)."'";
				};
				$n = function ($v) {
					return $v === null ? 'NULL' : (string) ((int) $v);
				};
				$sql = "INSERT INTO ".$table." (entity, fk_webcam, fk_soc, day, timezone, hash_scheme, day_hash, prev_day_hash, photos, photos_unsynced,";
				$sql .= " classification, reasons, wet_hours, frost_hours, fog_hours, date_creation) VALUES (";
				$sql .= $entity.", ".((int) $object->id).", ".($object->fk_soc > 0 ? (int) $object->fk_soc : 'NULL').", ".$q($d['day']).", ".$q($d['timezone']).", ";
				$sql .= $q($d['hash_scheme']).", ".$q($d['day_hash']).", ".$q($d['prev_day_hash']).", ".$n($d['photos']).", ".$n($d['photos_unsynced']).", ";
				$sql .= $q($d['classification']).", ".$q($d['reasons']).", ".$n($d['wet_hours']).", ".$n($d['frost_hours']).", ".$n($d['fog_hours']).", ";
				$sql .= "'".$this->db->idate(dol_now())."')";
				if ($this->db->query($sql)) {
					$row = $this->_registerRow($object, $d['day']);
					$results[] = array('day' => $d['day'], 'status' => 'created', 'registered_at' => $row ? (int) $this->db->jdate($row->date_creation) : dol_now());
					continue;
				}
				// Two deposits at the same time: the other one won
				$row = $this->_registerRow($object, $d['day']);
				if (!$row) {
					throw new RestException(500, 'Error saving register: '.$this->db->lasterror());
				}
			}
			$at = (int) $this->db->jdate($row->date_creation);
			if (hash_equals((string) $row->day_hash, $d['day_hash'])) {
				$results[] = array('day' => $d['day'], 'status' => 'duplicate', 'registered_at' => $at);
				continue;
			}
			// Conflict: nothing changes, one event per day and hash for the operator
			$refext = 'register:'.$d['day'].':'.substr($d['day_hash'], 0, 16);
			if (!$this->_findActionComm($object, $refext)) {
				$text = $this->_trans('WebcamEventRegisterConflict', dol_print_date(dol_stringtotime($d['day'].' 12:00:00', 0), 'day'));
				$note = $text."\n".$this->_trans('WebcamRegisterConflictNote', $row->day_hash, $d['day_hash'], dol_print_date($at, 'dayhour', 'tzuserrel'));
				$this->_addEvent($object, $text, 'AC_WEBCAMS_REGISTER', 0, $refext, $note);
			}
			$results[] = array('day' => $d['day'], 'status' => 'conflict', 'registered_at' => $at);
		}

		return array('ok' => true, 'id' => (int) $object->id, 'results' => $results);
	}

	/**
	 * Days of the register saved in Dolibarr (from - to, oldest first), with the time of the deposit
	 *
	 * @param	int		$id		ID of webcam
	 * @param	string	$from	First day YYYY-MM-DD (optional)
	 * @param	string	$to		Last day YYYY-MM-DD (optional)
	 * @return	array	{ok, id, days: [...]}
	 * @phan-return array<string,mixed>
	 * @phpstan-return array<string,mixed>
	 *
	 * @url	GET {id}/register
	 *
	 * @throws RestException 400 Bad request
	 * @throws RestException 403 Not allowed
	 * @throws RestException 404 Not found
	 */
	public function getRegister($id, $from = '', $to = '')
	{
		$object = $this->_fetchForPortal($id);
		foreach (array('from' => $from, 'to' => $to) as $k => $v) {
			if ($v !== '' && $v !== null && !$this->_isDay((string) $v)) {
				throw new RestException(400, $k.' invalid (YYYY-MM-DD)');
			}
		}
		if ($from && $to && strcmp($from, $to) > 0) {
			throw new RestException(400, 'from after to');
		}
		$days = array();
		foreach (webcamsRegisterDays($this->db, $object->id, (string) $from, (string) $to) as $r) {
			$days[] = array(
				'day' => substr((string) $r->day, 0, 10),
				'timezone' => (string) $r->timezone,
				'hash_scheme' => (string) $r->hash_scheme,
				'day_hash' => (string) $r->day_hash,
				'prev_day_hash' => $r->prev_day_hash === null ? null : (string) $r->prev_day_hash,
				'photos' => (int) $r->photos,
				'photos_unsynced' => (int) $r->photos_unsynced,
				'classification' => $r->classification === null ? null : (string) $r->classification,
				'reasons' => $r->reasons === null ? null : (string) $r->reasons,
				'wet_hours' => $r->wet_hours === null ? null : (int) $r->wet_hours,
				'frost_hours' => $r->frost_hours === null ? null : (int) $r->frost_hours,
				'fog_hours' => $r->fog_hours === null ? null : (int) $r->fog_hours,
				'customer_id' => $r->fk_soc === null ? null : (int) $r->fk_soc,
				'registered_at' => (int) $this->db->jdate($r->date_creation),
			);
		}
		return array('ok' => true, 'id' => (int) $object->id, 'days' => $days);
	}

	/**
	 * Check the permission portal and load the webcam
	 *
	 * @param	int		$id		ID of webcam
	 * @return	Webcam
	 *
	 * @throws RestException 403 Not allowed
	 * @throws RestException 404 Not found
	 */
	private function _fetchForPortal($id)
	{
		if (!DolibarrApiAccess::$user->hasRight('webcams', 'webcam', 'portal')) {
			throw new RestException(403, 'Permission Webcams - Sync data from webcam-portal missing');
		}
		if (!DolibarrApi::_checkAccessToResource('webcam', $id, 'webcams_webcam')) {
			throw new RestException(403, 'Access to instance id='.((int) $id).' of object not allowed for login '.DolibarrApiAccess::$user->login);
		}
		$object = new Webcam($this->db);
		if ((int) $id <= 0 || $object->fetch((int) $id) <= 0) {
			throw new RestException(404, 'Webcam not found');
		}
		// entity is loaded only with multicompany (otherwise the field is disabled)
		if (!empty($object->entity) && !in_array((int) $object->entity, array_map('intval', explode(',', getEntity('webcam'))), true)) {
			throw new RestException(404, 'Webcam not found');
		}
		return $object;
	}

	/**
	 * Apply the counters of the camera to the webcam (shared by report and counters)
	 *
	 * @param	Webcam				$object		Webcam (not changed)
	 * @param	string|null			$serial		Serial number of the camera, null if unknown
	 * @param	array<string,int|float|null>	$counters	Column => value (null = not sent)
	 * @param	string|null			$model		Model of the camera, for the text of the event
	 * @param	array<string,mixed>	$set		Columns to write (completed)
	 * @param	string[]			$updated	Columns written (completed)
	 * @param	string[]			$ignored	Columns refused (completed)
	 * @return	string[]						Texts of the events for the agenda
	 */
	private function _applyCounters($object, $serial, $counters, $model, &$set, &$updated, &$ignored)
	{
		$events = array();
		$old = (string) $object->camera_serial;
		$changed = ($serial !== null && $old !== '' && $serial !== $old);
		if ($serial !== null && $serial !== $old) {
			$set['camera_serial'] = $serial;
			$updated[] = 'camera_serial';
			if ($changed) {
				$events[] = $this->_trans('WebcamEventCameraChanged', $old, $serial.($model ? ' ('.$model.')' : ''));
			}
		}
		$before = webcamsShotsUsed($object);
		$after = array('shutter_count' => $object->shutter_count, 'shots_total' => $object->shots_total);
		foreach ($counters as $k => $v) {
			if ($v === null) {
				// Counters of the old camera are not valid for the new one
				if ($changed && $object->$k !== null && $object->$k !== '') {
					$set[$k] = null;
					$after[$k] = null;
				}
				continue;
			}
			$cur = $object->$k;
			if (!$changed && $cur !== null && $cur !== '' && $v < (float) $cur) {
				$ignored[] = $k;
				continue;
			}
			$set[$k] = $v;
			$updated[] = $k;
			if (array_key_exists($k, $after)) {
				$after[$k] = $v;
			}
		}
		// Shutter life: one event when a threshold is crossed
		$now = webcamsShotsUsed((object) $after);
		if ($now !== null) {
			$life = webcamsShutterLife();
			$warn = webcamsShutterWarnShots();
			if ($changed) {
				$before = null;
			}
			if ($now >= $life && ($before === null || $before < $life)) {
				$events[] = $this->_trans('WebcamEventShutterLife', $now, $life);
			} elseif ($now >= $warn && $now < $life && ($before === null || $before < $warn)) {
				$events[] = $this->_trans('WebcamEventShutterWarn', $now, $life);
			}
		}
		return $events;
	}

	/**
	 * Apply the summary of the Victron charge controller to the webcam
	 *
	 * @param	Webcam				$object		Webcam (not changed)
	 * @param	array<string,int|float|string|null>	$power	Column => value (null = not sent)
	 * @param	array<string,mixed>	$set		Columns to write (completed)
	 * @param	string[]			$updated	Columns written (completed)
	 * @return	string[]						Texts of the events for the agenda
	 */
	private function _applyPower($object, $power, &$set, &$updated)
	{
		$events = array();
		foreach ($power as $k => $v) {
			if ($v === null) {
				continue;
			}
			$set[$k] = $v;
			$updated[] = $k;
		}
		if (isset($power['victron_error']) && $power['victron_error'] !== null) {
			$olderr = ($object->victron_error === null || $object->victron_error === '') ? 0 : (int) $object->victron_error;
			if ((int) $power['victron_error'] !== $olderr) {
				$events[] = $power['victron_error'] ? $this->_trans('WebcamEventVictronError', $power['victron_error']) : $this->_trans('WebcamEventVictronOk');
			}
		}
		return $events;
	}

	/**
	 * Write some columns of the webcam, without touching the others
	 *
	 * @param	Webcam				$object		Webcam (properties updated too)
	 * @param	array<string,mixed>	$set		Column => value (datetime columns as timestamp)
	 * @return	void
	 *
	 * @throws RestException 500 System error
	 */
	private function _updateColumns($object, $set)
	{
		$parts = array();
		foreach ($set as $k => $v) {
			if (!isset($object->fields[$k])) {
				continue;
			}
			$type = $object->fields[$k]['type'];
			if ($v === null || $v === '') {
				$sqlv = 'NULL';
			} elseif ($type == 'datetime') {
				$sqlv = "'".$this->db->idate($v)."'";
			} elseif (preg_match('/^(integer|double)/', $type)) {
				$sqlv = is_float($v) ? (string) $v : (string) ((int) $v);
			} else {
				$sqlv = "'".$this->db->escape((string) $v)."'";
			}
			$parts[] = $k.' = '.$sqlv;
			$object->$k = $v;
		}
		if (!$parts) {
			return;
		}
		$sql = "UPDATE ".$this->db->prefix().$object->table_element." SET ".implode(', ', $parts);
		$sql .= ", fk_user_modif = ".((int) DolibarrApiAccess::$user->id);
		$sql .= " WHERE rowid = ".((int) $object->id);
		if (!$this->db->query($sql)) {
			throw new RestException(500, 'Error updating webcam: '.$this->db->lasterror());
		}
	}

	/**
	 * Add an event in the agenda of the webcam, as report does
	 *
	 * @param	Webcam	$object		Webcam
	 * @param	string	$text		Text (label is "<ref> - <text>")
	 * @param	string	$code		Code of the event
	 * @param	int		$datep		Date of the event (0 = now)
	 * @param	string	$refext		External reference (portal:<id>)
	 * @param	string	$note		Private note (default: the text)
	 * @param	array<string,string>	$props	More properties of the event (email_from, email_to...)
	 * @return	int					Id of the event, 0 if module Agenda is off, <0 if error
	 */
	private function _addEvent($object, $text, $code = 'AC_WEBCAMS_PORTAL', $datep = 0, $refext = '', $note = '', $props = array())
	{
		if (!isModEnabled('agenda')) {
			return 0;
		}
		require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
		$user = DolibarrApiAccess::$user;
		$ev = new ActionComm($this->db);
		$ev->type_code = 'AC_OTH_AUTO';
		$ev->code = $code;
		$ev->label = dol_trunc($object->ref.' - '.$text, 255, 'right', 'UTF-8', 1);
		$ev->note_private = $note !== '' ? $note : $text;
		$ev->datep = $datep ? $datep : dol_now();
		$ev->percentage = -1;
		$ev->userownerid = $user->id;
		$ev->socid = $object->fk_soc > 0 ? $object->fk_soc : 0;
		$ev->fk_element = $object->id;
		$ev->elementtype = $object->element.'@'.$object->module;
		if ($refext) {
			$ev->ref_ext = $refext;
		}
		foreach ($props as $k => $v) {
			$ev->$k = $v;
		}
		$res = $ev->create($user);
		if ($res <= 0) {
			dol_syslog(__METHOD__.' '.$ev->error, LOG_ERR);
		}
		return $res;
	}

	/**
	 * Find an event of the webcam by external reference
	 *
	 * @param	Webcam	$object		Webcam
	 * @param	string	$refext		portal:<id>
	 * @return	int					Id of the event or 0
	 */
	private function _findActionComm($object, $refext)
	{
		$sql = "SELECT id FROM ".$this->db->prefix()."actioncomm";
		$sql .= " WHERE ref_ext = '".$this->db->escape($refext)."'";
		$sql .= " AND fk_element = ".((int) $object->id)." AND elementtype = '".$this->db->escape($object->element.'@'.$object->module)."'";
		$sql .= " AND entity IN (".getEntity('agenda').")";
		$resql = $this->db->query($sql);
		if ($resql && ($obj = $this->db->fetch_object($resql))) {
			return (int) $obj->id;
		}
		return 0;
	}

	/**
	 * Code of a dictionary of the module Ticket: the code sent if it exists and is
	 * active, otherwise the default of the dictionary (use_default), otherwise ''.
	 *
	 * @param	string		$table	c_ticket_type, c_ticket_severity or c_ticket_category
	 * @param	string|null	$code	Code sent
	 * @return	string
	 */
	private function _dictCode($table, $code)
	{
		$sql = "SELECT code FROM ".$this->db->prefix().$table." WHERE active = 1 AND entity IN (".getEntity($table).")";
		$sql .= " ORDER BY ".($code !== null ? "(code = '".$this->db->escape($code)."') DESC, " : "")."use_default DESC, pos ASC";
		$resql = $this->db->query($sql);
		$first = '';
		while ($resql && ($obj = $this->db->fetch_object($resql))) {
			if ($code !== null && $obj->code === $code) {
				return $code;
			}
			if ($first === '') {
				$first = (string) $obj->code;
			}
		}
		return $first;
	}

	/**
	 * Translated text with the language file of the module
	 *
	 * @param	string	$key	Key
	 * @param	mixed	$p1		Parameter 1
	 * @param	mixed	$p2		Parameter 2
	 * @param	mixed	$p3		Parameter 3
	 * @param	mixed	$p4		Parameter 4
	 * @param	mixed	$p5		Parameter 5
	 * @return	string
	 */
	private function _trans($key, $p1 = '', $p2 = '', $p3 = '', $p4 = '', $p5 = '')
	{
		global $langs;
		$langs->load('webcams@webcams');
		return $langs->transnoentitiesnoconv($key, $p1, $p2, $p3, $p4, $p5);
	}

	/**
	 * String from the request, without control characters, null if missing or empty
	 *
	 * @param	array<string,mixed>	$in		Request
	 * @param	string				$k		Key
	 * @param	int					$max	Maximum length
	 * @return	string|null
	 */
	private function _inString($in, $k, $max)
	{
		if (!isset($in[$k]) || is_array($in[$k]) || is_bool($in[$k])) {
			return null;
		}
		$v = trim((string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', (string) $in[$k]));
		return $v === '' ? null : dol_substr($v, 0, $max);
	}

	/**
	 * Number from the request: null if missing or null, 400 if not valid
	 *
	 * @param	array<string,mixed>	$in		Request
	 * @param	string				$k		Key
	 * @param	float				$min	Minimum
	 * @param	float				$max	Maximum
	 * @param	string				$prefix	Prefix of the key in the error message
	 * @param	bool				$isint	Integer
	 * @return	int|float|null
	 *
	 * @throws RestException 400 Bad request
	 */
	private function _inNumber($in, $k, $min, $max, $prefix = '', $isint = false)
	{
		if (!array_key_exists($k, $in) || $in[$k] === null) {
			return null;
		}
		$v = $in[$k];
		if (is_bool($v) || !is_numeric($v) || $v < $min || $v > $max || ($isint && floor((float) $v) != $v)) {
			throw new RestException(400, $prefix.$k.' invalid (number '.$min.'-'.$max.')');
		}
		return $isint ? (int) $v : round((float) $v, 2);
	}

	/**
	 * Charge state of the Victron (bulk, absorption, float, off, fault...)
	 *
	 * @param	array<string,mixed>	$in		Request
	 * @param	string				$k		Key
	 * @return	string|null
	 *
	 * @throws RestException 400 Bad request
	 */
	private function _inChargeState($in, $k)
	{
		$v = $this->_inString($in, $k, 20);
		if ($v !== null && !preg_match('/^[a-z][a-z0-9_ -]{0,19}$/', strtolower($v))) {
			throw new RestException(400, 'power.'.$k.' invalid');
		}
		return $v === null ? null : strtolower($v);
	}

	/**
	 * Unix time from the request (seconds UTC), $default if missing
	 *
	 * @param	array<string,mixed>	$in			Request
	 * @param	string				$k			Key
	 * @param	int					$default	Default
	 * @return	int
	 *
	 * @throws RestException 400 Bad request
	 */
	private function _inTime($in, $k, $default)
	{
		if (!isset($in[$k]) || $in[$k] === '') {
			return $default;
		}
		if (!is_numeric($in[$k]) || $in[$k] < 946684800 || $in[$k] > dol_now() + 86400) {
			throw new RestException(400, $k.' invalid (Unix time in seconds, not in the future)');
		}
		return (int) $in[$k];
	}

	/**
	 * External reference portal:<portal_event_id>, '' if not sent
	 *
	 * @param	array<string,mixed>	$in		Request
	 * @return	string
	 *
	 * @throws RestException 400 Bad request
	 */
	private function _inPortalEventId($in)
	{
		if (!isset($in['portal_event_id']) || $in['portal_event_id'] === '') {
			return '';
		}
		$v = $in['portal_event_id'];
		if (is_bool($v) || !is_numeric($v) || (int) $v <= 0 || floor((float) $v) != $v) {
			throw new RestException(400, 'portal_event_id invalid');
		}
		return 'portal:'.((int) $v);
	}

	/**
	 * Check that the module Ticket is on and the user can write tickets
	 *
	 * @return	User
	 *
	 * @throws RestException 403 Not allowed
	 * @throws RestException 501 Module Ticket not enabled
	 */
	private function _checkTicketAccess()
	{
		if (!isModEnabled('ticket')) {
			throw new RestException(501, 'Module Ticket is not enabled');
		}
		$user = DolibarrApiAccess::$user;
		if (!$user->hasRight('ticket', 'write')) {
			throw new RestException(403, 'Permission to create tickets missing');
		}
		require_once DOL_DOCUMENT_ROOT.'/ticket/class/ticket.class.php';
		return $user;
	}

	/**
	 * SQL condition: ticket t linked to the webcam, in both directions of llx_element_element
	 *
	 * @param	Webcam	$object		Webcam
	 * @return	string
	 */
	private function _sqlTicketLinked($object)
	{
		$type = $this->db->escape($object->getElementType());
		$sql = "EXISTS (SELECT 1 FROM ".$this->db->prefix()."element_element as ee WHERE";
		$sql .= " (ee.sourcetype = 'ticket' AND ee.fk_source = t.rowid AND ee.targettype = '".$type."' AND ee.fk_target = ".((int) $object->id).")";
		$sql .= " OR (ee.targettype = 'ticket' AND ee.fk_target = t.rowid AND ee.sourcetype = '".$type."' AND ee.fk_source = ".((int) $object->id)."))";
		return $sql;
	}

	/**
	 * Whether a ticket is linked to the webcam
	 *
	 * @param	int		$ticketid	Id of ticket
	 * @param	Webcam	$object		Webcam
	 * @return	bool
	 */
	private function _ticketLinkedToWebcam($ticketid, $object)
	{
		$sql = "SELECT t.rowid FROM ".$this->db->prefix()."ticket as t WHERE t.rowid = ".((int) $ticketid)." AND ".$this->_sqlTicketLinked($object);
		$resql = $this->db->query($sql);
		return $resql && $this->db->fetch_object($resql);
	}

	/**
	 * Open ticket (status not closed or canceled) linked to the webcam for a rule of the portal
	 *
	 * @param	Webcam	$object		Webcam
	 * @param	string	$rule		Id of the rule (extraparams portal_rule)
	 * @return	Ticket|null
	 */
	private function _findOpenTicketForRule($object, $rule)
	{
		$sql = "SELECT t.rowid, t.extraparams FROM ".$this->db->prefix()."ticket as t";
		$sql .= " WHERE t.entity IN (".getEntity('ticket').") AND t.fk_statut NOT IN (".Ticket::STATUS_CLOSED.", ".Ticket::STATUS_CANCELED.")";
		$sql .= " AND t.extraparams LIKE '%".$this->db->escape($this->db->escapeforlike('"portal_rule":'.json_encode($rule)))."%'";
		$sql .= " AND ".$this->_sqlTicketLinked($object);
		$sql .= " ORDER BY t.rowid DESC";
		$resql = $this->db->query($sql);
		while ($resql && ($obj = $this->db->fetch_object($resql))) {
			$extra = json_decode((string) $obj->extraparams, true);
			if (is_array($extra) && isset($extra['portal_rule']) && $extra['portal_rule'] === $rule) {
				$ticket = new Ticket($this->db);
				if ($ticket->fetch((int) $obj->rowid) > 0) {
					return $ticket;
				}
			}
		}
		return null;
	}

	/**
	 * Message of the ticket already added with this portal_event_id
	 *
	 * @param	int		$ticketid	Id of ticket
	 * @param	string	$refext		portal:<id>
	 * @return	int					Id of the event of the message or 0
	 */
	private function _findTicketMessage($ticketid, $refext)
	{
		$sql = "SELECT id FROM ".$this->db->prefix()."actioncomm";
		$sql .= " WHERE elementtype = 'ticket' AND fk_element = ".((int) $ticketid)." AND ref_ext = '".$this->db->escape($refext)."'";
		$resql = $this->db->query($sql);
		if ($resql && ($obj = $this->db->fetch_object($resql))) {
			return (int) $obj->id;
		}
		return 0;
	}

	/**
	 * Add a private message to a ticket, as "Add a private message" of the card
	 * (event TICKET_MSG_PRIVATE), with ref_ext = portal:<id> for idempotency.
	 * No email is sent.
	 *
	 * @param	Ticket	$ticket		Ticket
	 * @param	string	$html		Message (HTML)
	 * @param	string	$refext		portal:<id> or ''
	 * @return	int					Id of the event
	 *
	 * @throws RestException 500 System error
	 */
	private function _addTicketMessage($ticket, $html, $refext)
	{
		require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
		$user = DolibarrApiAccess::$user;
		$ac = new ActionComm($this->db);
		$ac->type_code = 'AC_OTH_AUTO';
		$ac->code = 'TICKET_MSG_PRIVATE';
		$ac->label = dol_trunc((string) $ticket->subject, 255, 'right', 'UTF-8', 1);
		$ac->note_private = $html;
		$ac->socid = $ticket->socid > 0 ? (int) $ticket->socid : 0;
		$ac->fk_project = $ticket->fk_project > 0 ? (int) $ticket->fk_project : 0;
		$ac->userownerid = $user->id;
		$ac->userassigned = array($user->id => array('id' => $user->id, 'transparency' => 0));
		$ac->datep = dol_now();
		$ac->percentage = -1;
		$ac->elementtype = 'ticket';
		$ac->fk_element = $ticket->id;
		$ac->elementid = $ticket->id;
		if ($refext) {
			$ac->ref_ext = $refext;
		}
		$res = $ac->create($user);
		if ($res <= 0) {
			throw new RestException(500, 'Error adding message to ticket', array_merge(array($ac->error), $ac->errors));
		}
		return $res;
	}

	/**
	 * Position of a severity in the dictionary of tickets (higher = more severe)
	 *
	 * @param	string	$code	Code of severity
	 * @return	int				-1 if unknown
	 */
	private function _severityPos($code)
	{
		if ($code === '') {
			return -1;
		}
		$sql = "SELECT pos FROM ".$this->db->prefix()."c_ticket_severity WHERE code = '".$this->db->escape($code)."' AND entity IN (".getEntity('c_ticket_severity').")";
		$resql = $this->db->query($sql);
		if ($resql && ($obj = $this->db->fetch_object($resql))) {
			return (int) $obj->pos;
		}
		return -1;
	}

	/**
	 * Message of the portal as HTML: plain text with new lines is escaped and
	 * converted, HTML is kept as it is
	 *
	 * @param	string	$message	Message
	 * @return	string
	 */
	private function _ticketHtml($message)
	{
		if (dol_textishtml($message)) {
			return $message;
		}
		return dol_nl2br(dol_escape_htmltag($message, 0, 1));
	}

	/**
	 * Data of the webcam and links (Dolibarr, portal, rttys) at the end of the
	 * text of a new ticket. Links already in the message are not repeated. No
	 * token, password or public IP.
	 *
	 * @param	Webcam	$object		Webcam
	 * @param	string	$html		Message of the ticket
	 * @param	int		$duets		Due date of the intervention (0 = none)
	 * @return	string				HTML
	 */
	private function _ticketFooter($object, $html, $duets)
	{
		global $langs;
		$langs->load('webcams@webcams');
		$lines = array();
		$add = function ($label, $value) use (&$lines) {
			if ($value !== null && $value !== '') {
				$lines[] = dol_escape_htmltag($label).': '.$value;
			}
		};
		$link = function ($url) use ($html) {
			return strpos($html, $url) === false && strpos($html, dol_escape_htmltag($url)) === false ? '<a href="'.dol_escape_htmltag($url).'">'.dol_escape_htmltag($url).'</a>' : '';
		};
		$date = function ($v) {
			return $v ? dol_print_date($v, 'dayhour', 'tzserver') : '';
		};
		$add($langs->transnoentitiesnoconv('Webcam'), dol_escape_htmltag($object->ref.($object->label ? ' - '.$object->label : '')));
		if ($object->fk_soc > 0) {
			$sql = "SELECT nom FROM ".$this->db->prefix()."societe WHERE rowid = ".((int) $object->fk_soc);
			$resql = $this->db->query($sql);
			if ($resql && ($obj = $this->db->fetch_object($resql))) {
				$add($langs->transnoentitiesnoconv('Customer'), dol_escape_htmltag($obj->nom));
			}
		}
		$add($langs->transnoentitiesnoconv('WebcamLocation'), dol_escape_htmltag((string) $object->location));
		$add($langs->transnoentitiesnoconv('WebcamLastContact'), $date($object->last_contact));
		$add($langs->transnoentitiesnoconv('WebcamLastCapture'), $date($object->last_capture));
		$add($langs->transnoentitiesnoconv('WebcamLastTelemetry'), $date($object->last_telemetry));
		if ($object->battery_v !== null && $object->battery_v !== '') {
			$add($langs->transnoentitiesnoconv('WebcamBatteryV'), price((float) $object->battery_v, 0, $langs, 0, 0, 2).($object->battery_v_min24 !== null && $object->battery_v_min24 !== '' ? ' (min 24 h '.price((float) $object->battery_v_min24, 0, $langs, 0, 0, 2).')' : ''));
		}
		$sla = webcamsSla($object);
		if ($sla['enabled']) {
			$add($langs->transnoentitiesnoconv('WebcamSla'), dol_escape_htmltag($langs->transnoentitiesnoconv('WebcamSlaSummary', $sla['offline_hours'], $sla['response_days'], webcamsFormatPct($sla['target_pct']))));
		}
		if ($duets) {
			$d = dol_print_date($duets, 'day', 'tzserver');
			if (strpos($html, $d) === false) {
				$add($langs->transnoentitiesnoconv('WebcamTicketDue'), $d);
			}
		}
		$add($langs->transnoentitiesnoconv('WebcamTicketCard'), $link(dol_buildpath('/webcams/webcam_card.php', 2).'?id='.((int) $object->id)));
		$urls = webcamsPortalUrls($object);
		if (!empty($urls['page'])) {
			$add($langs->transnoentitiesnoconv('WebcamPortalPage'), $link($urls['page']));
		}
		if (!empty($urls['current'])) {
			$add('current.jpg', $link($urls['current']));
		}
		$rttys = webcamsRttysUrls($object->ref);
		if ($rttys) {
			$add($langs->transnoentitiesnoconv('WebcamRemoteTerminal').' (rttys)', $link($rttys['terminal']));
			$add($langs->transnoentitiesnoconv('WebcamRemoteLuci').' (rttys)', $link($rttys['luci']));
		}
		return '<br><br>--<br>'.implode('<br>', $lines);
	}

	/**
	 * Month YYYY-MM from the request, null if missing
	 *
	 * @param	array<string,mixed>	$in		Request
	 * @param	string				$k		Key
	 * @return	string|null
	 *
	 * @throws RestException 400 Bad request
	 */
	private function _inPeriod($in, $k)
	{
		if (!isset($in[$k]) || $in[$k] === '') {
			return null;
		}
		if (!is_string($in[$k]) || !preg_match('/^(19|20)\d{2}-(0[1-9]|1[0-2])$/', $in[$k])) {
			throw new RestException(400, $k.' invalid (YYYY-MM)');
		}
		return $in[$k];
	}

	/**
	 * Row of the register of the webcam for a day
	 *
	 * @param	Webcam	$object		Webcam
	 * @param	string	$day		YYYY-MM-DD
	 * @return	object|null
	 */
	private function _registerRow($object, $day)
	{
		$sql = "SELECT rowid, day_hash, date_creation FROM ".$this->db->prefix()."webcams_register";
		$sql .= " WHERE fk_webcam = ".((int) $object->id)." AND day = '".$this->db->escape($day)."'";
		$resql = $this->db->query($sql);
		if ($resql && ($obj = $this->db->fetch_object($resql))) {
			return $obj;
		}
		return null;
	}

	/**
	 * Whether a string is a valid day YYYY-MM-DD
	 *
	 * @param	string	$v		Value
	 * @return	bool
	 */
	private function _isDay($v)
	{
		return is_string($v) && preg_match('/^(19|20)\d{2}-(\d{2})-(\d{2})$/', $v, $m) && checkdate((int) $m[2], (int) $m[3], (int) substr($v, 0, 4));
	}

	/**
	 * Whether a string is a period of days YYYY-MM-DD_YYYY-MM-DD (start <= end)
	 *
	 * @param	string	$v		Value
	 * @return	bool
	 */
	private function _isDayRange($v)
	{
		$p = explode('_', (string) $v);
		return count($p) == 2 && $this->_isDay($p[0]) && $this->_isDay($p[1]) && strcmp($p[0], $p[1]) <= 0;
	}

	/**
	 * Send a document just saved to the customer with an email template of
	 * Dolibarr (send_email of POST webcams/{id}/documents, API 2.2)
	 *
	 * @param	Webcam	$object			Webcam
	 * @param	string	$path			Full path of the PDF
	 * @param	string	$filename		Name of the file
	 * @param	array{template:string,link:string,resend:bool}	$send	Request
	 * @param	string	$period			AAAA-MM, AAAA-MM-GG_AAAA-MM-GG or ''
	 * @param	string	$portaldocid	report:<id>, certificate:<id> or ''
	 * @param	string|null	$doctype	Type of the document
	 * @return	array<string,mixed>		{requested, sent, duplicate, to, actioncomm_id, reason}
	 */
	private function _sendDocument($object, $path, $filename, $send, $period, $portaldocid, $doctype)
	{
		global $langs;

		$res = array('requested' => true, 'sent' => false, 'duplicate' => false, 'to' => array(), 'actioncomm_id' => null, 'reason' => null);
		$to = webcamsReportRecipients($this->db, $object);
		$res['to'] = $to;
		$refext = $portaldocid !== '' ? 'portal:'.$portaldocid : '';

		// Idempotence: the email of a document of the portal goes once (unless resend)
		if ($refext !== '' && empty($send['resend'])) {
			$found = $this->_findActionComm($object, $refext);
			if ($found) {
				$res['sent'] = true;
				$res['duplicate'] = true;
				$res['actioncomm_id'] = $found;
				return $res;
			}
		}

		$fail = function ($reason, $detail = '') use (&$res, $object, $filename, $to) {
			$res['reason'] = $reason;
			if ($detail !== '') {
				$res['detail'] = $detail;
			}
			$text = $this->_trans('WebcamEventEmailNotSent', $filename, $this->_trans('WebcamEmailReason_'.$reason));
			$note = $text.($to ? "\n".$this->_trans('WebcamEmailTo').': '.implode(', ', $to) : '').($detail !== '' ? "\n".$detail : '');
			$this->_addEvent($object, $text, 'AC_WEBCAMS_PORTAL', 0, '', $note);
			return $res;
		};

		if (!$to) {
			return $fail('no_recipient');
		}
		$tpl = webcamsEmailTemplate($this->db, $send['template']);
		if (!$tpl) {
			return $fail('template_missing', $send['template'] == 'weather' ? 'WEBCAMS_EMAIL_TEMPLATE_WEATHER' : 'WEBCAMS_EMAIL_TEMPLATE_REPORT');
		}
		$from = getDolGlobalString('WEBCAMS_EMAIL_FROM', getDolGlobalString('MAIN_MAIL_EMAIL_FROM'));
		if ($from === '') {
			return $fail('mail_error', 'No sender: set WEBCAMS_EMAIL_FROM or MAIN_MAIL_EMAIL_FROM');
		}
		if (getDolGlobalString('MAIN_DISABLE_ALL_MAILS')) {
			return $fail('mail_error', 'MAIN_DISABLE_ALL_MAILS is set');
		}

		// Language of the customer if set, otherwise the one of Dolibarr
		$object->fetch_thirdparty();
		$outlangs = $langs;
		if (is_object($object->thirdparty) && !empty($object->thirdparty->default_lang) && $object->thirdparty->default_lang != $langs->defaultlang) {
			$outlangs = new Translate('', $GLOBALS['conf']);
			$outlangs->setDefaultLang($object->thirdparty->default_lang);
		}
		$outlangs->loadLangs(array('main', 'webcams@webcams'));

		$subst = getCommonSubstitutionArray($outlangs, 0, null, $object);
		complete_substitutions_array($subst, $outlangs, $object);
		$subst['__WEBCAM_REF__'] = (string) $object->ref;
		$subst['__WEBCAM_LABEL__'] = (string) ($object->label !== null && $object->label !== '' ? $object->label : $object->ref);
		$subst['__WEBCAM_PERIOD__'] = $period !== '' ? webcamsFormatDocPeriod($period, $outlangs) : '';
		$subst['__WEBCAM_LINK__'] = (string) $send['link'];
		$subject = trim(make_substitutions((string) $tpl->topic, $subst, $outlangs));
		$body = make_substitutions((string) $tpl->content, $subst, $outlangs);
		if ($subject === '') {
			$subject = $filename;
		}

		require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
		$trackid = 'webcam'.$object->id;
		$mail = new CMailFile($subject, implode(', ', $to), $from, $body, array($path), array('application/pdf'), array($filename), '', '', 0, -1, '', '', $trackid, '', 'standard');
		if (!empty($mail->error) || !$mail->sendfile()) {
			return $fail('mail_error', dol_trunc(trim(strip_tags((string) $mail->error)), 500));
		}

		$res['sent'] = true;
		$text = $this->_trans('WebcamEventEmailSent', $filename, implode(', ', $to));
		$note = $this->_trans('WebcamEmailSubject').': '.$subject."\n".$this->_trans('WebcamEmailTo').': '.implode(', ', $to)."\n".$this->_trans('WebcamEmailFile').': '.$filename;
		if ($doctype) {
			$note .= "\n".'doc_type: '.$doctype.($period !== '' ? ' ('.$period.')' : '');
		}
		$evid = $this->_addEvent($object, $text, 'AC_WEBCAMS_SENTBYMAIL', 0, $refext, $note, array(
			'email_from' => $from,
			'email_to' => implode(', ', $to),
			'email_subject' => $subject,
			'email_msgid' => isset($mail->msgid) ? $mail->msgid : '',
		));
		$res['actioncomm_id'] = $evid > 0 ? (int) $evid : null;
		return $res;
	}

	/* END API V2 (webcam-portal) */

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
