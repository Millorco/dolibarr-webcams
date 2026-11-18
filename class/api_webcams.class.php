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
	 * @param	array	$request_data	{portal_slug, url_current, windy_published, windy_id}
	 * @phan-param ?array<string,mixed> $request_data
	 * @phpstan-param ?array<string,mixed> $request_data
	 * @return	array	{ok, id}
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
		if (!$set) {
			throw new RestException(400, 'Nothing to update: send portal_slug, url_current, windy_published or windy_id');
		}

		$events = array();
		if (isset($set['windy_published']) && (int) $object->windy_published !== $set['windy_published']) {
			$events[] = $this->_trans($set['windy_published'] ? 'WebcamEventWindyOn' : 'WebcamEventWindyOff');
		}
		$this->_updateColumns($object, $set);
		foreach ($events as $text) {
			$this->_addEvent($object, $text);
		}

		return array('ok' => true, 'id' => (int) $object->id);
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
	 * portal_event_id returns the ticket already created.
	 *
	 * @param	int		$id				ID of webcam
	 * @param	array	$request_data	{subject, message, type_code, severity_code, category_code, portal_event_id}
	 * @phan-param ?array<string,mixed> $request_data
	 * @phpstan-param ?array<string,mixed> $request_data
	 * @return	array	{ok, ticket_id, ticket_ref, duplicate}
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
		if (!isModEnabled('ticket')) {
			throw new RestException(501, 'Module Ticket is not enabled');
		}
		$user = DolibarrApiAccess::$user;
		if (!$user->hasRight('ticket', 'write')) {
			throw new RestException(403, 'Permission to create tickets missing');
		}
		require_once DOL_DOCUMENT_ROOT.'/ticket/class/ticket.class.php';
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

		if ($refext) {
			// llx_ticket has no ref_ext: the reference is kept in extraparams
			$sql = "SELECT rowid, ref FROM ".$this->db->prefix()."ticket";
			$sql .= " WHERE extraparams LIKE '%".$this->db->escape($this->db->escapeforlike('"ref_ext":"'.$refext.'"'))."%' AND entity IN (".getEntity('ticket').")";
			$resql = $this->db->query($sql);
			if ($resql && ($obj = $this->db->fetch_object($resql))) {
				return array('ok' => true, 'ticket_id' => (int) $obj->rowid, 'ticket_ref' => $obj->ref, 'duplicate' => true);
			}
		}

		$ticket = new Ticket($this->db);
		$ticket->subject = $subject;
		$ticket->message = $message;
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

		$this->db->begin();
		if ($ticket->create($user) <= 0) {
			$this->db->rollback();
			throw new RestException(500, 'Error creating ticket', array_merge(array($ticket->error), $ticket->errors));
		}
		if ($refext) {
			$ticket->extraparams = is_array($ticket->extraparams) ? $ticket->extraparams : array();
			$ticket->extraparams['ref_ext'] = $refext;
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

		return array('ok' => true, 'ticket_id' => (int) $ticket->id, 'ticket_ref' => $ticket->ref, 'duplicate' => false);
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
	 * @param	array	$request_data	{filename, filecontent (base64), fileencoding ("base64"), overwrite (0/1), description, sha256}
	 * @phan-param ?array<string,mixed> $request_data
	 * @phpstan-param ?array<string,mixed> $request_data
	 * @return	array	{ok, id, file}
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
		if ($res > 0 && $description) {
			$sql = "UPDATE ".$this->db->prefix()."ecm_files SET description = '".$this->db->escape($description)."'";
			$sql .= " WHERE filepath = '".$this->db->escape($relindex)."' AND filename = '".$this->db->escape($filename)."'";
			$sql .= " AND entity = ".((int) $entity);
			$this->db->query($sql);
		}

		$this->_addEvent($object, $this->_trans($exists ? 'WebcamEventDocumentReplaced' : 'WebcamEventDocumentAdded', $filename), 'AC_WEBCAMS_PORTAL', 0, '', $description ? $description : '');

		return array('ok' => true, 'id' => (int) $object->id, 'file' => $rel.'/'.$filename);
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
			$warn = (int) floor($life * getDolGlobalInt('WEBCAMS_SHUTTER_WARN_PCT', 90) / 100);
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
	 * @return	int					Id of the event, 0 if module Agenda is off, <0 if error
	 */
	private function _addEvent($object, $text, $code = 'AC_WEBCAMS_PORTAL', $datep = 0, $refext = '', $note = '')
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
	 * @return	string
	 */
	private function _trans($key, $p1 = '', $p2 = '')
	{
		global $langs;
		$langs->load('webcams@webcams');
		return $langs->transnoentitiesnoconv($key, $p1, $p2);
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
