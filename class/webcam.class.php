<?php
/* Copyright (C) 2017       Laurent Destailleur      <eldy@users.sourceforge.net>
 * Copyright (C) 2023-2025  Frédéric France          <frederic.france@free.fr>
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

/**
 * \file        htdocs/modulebuilder/template/class/webcam.class.php
 * \ingroup     webcams
 * \brief       This file is a CRUD class file for Webcam (Create/Read/Update/Delete)
 */

// Put here all includes required by your class file
require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once __DIR__.'/../lib/webcams_compat.lib.php';
//require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
//require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';

/**
 * Class for Webcam
 */
class Webcam extends CommonObject
{
	/**
	 * @var string 		ID of module.
	 */
	public $module = 'webcams';

	/**
	 * @var string 		ID to identify managed object.
	 */
	public $element = 'webcam';

	/**
	 * @var string		Prefix to check for any trigger code of any business class to prevent bad value for trigger code.
	 * @see CommonTrigger::call_trigger()
	 */
	public $TRIGGER_PREFIX = 'WEBCAMS_WEBCAM';	// Will be used to build trgiger keys 'WEBCAMS_WEBCAM_MODIFY', ...

	/**
	 * @var string 		Name of table without prefix where object is stored. This is also the key used for extrafields management (so extrafields know the link to the parent table).
	 */
	public $table_element = 'webcams_webcam';

	/**
	 * @var string 		If permission must be checked with hasRight('webcams', 'read') and not hasright('webcams', 'webcam', 'read'), you can uncomment this line
	 */
	//public $element_for_permission = 'webcams';

	/**
	 * @var string 		String with name of icon for webcam. Must be a 'fa-xxx' fontawesome code (or 'fa-xxx_fa_color_size') or 'webcam@webcams' if picto is file 'img/object_webcam.png'.
	 */
	public $picto = 'fa-video';

	/**
	 * @var int<0,1>	Does object support extrafields ? 0=No, 1=Yes
	 */
	public $isextrafieldmanaged = 1;

	/**
	 * @var int<0,1>|string		Does this object support multicompany module ?
	 * 							0=No test on entity, 1=Test with field entity in local table, 'field@table'=Test entity into the field@table (example 'fk_soc@societe')
	 */
	public $ismultientitymanaged = 0;


	const STATUS_DRAFT = 0;
	const STATUS_VALIDATED = 1;
	const STATUS_CANCELED = 9;

	/**
	 *  'type' field format:
	 *  	'integer', 'integer:ObjectClass:PathToClass[:AddCreateButtonOrNot[:Filter[:Sortfield]]]',
	 *  	'select' (list of values are in 'options'. for integer list of values are in 'arrayofkeyval'),
	 *  	'sellist:TableName:LabelFieldName[:KeyFieldName[:KeyFieldParent[:Filter[:CategoryIdType[:CategoryIdList[:SortField]]]]]]',
	 *  	'chkbxlst:...',
	 *  	'varchar(x)',
	 *  	'text', 'text:none', 'html',
	 *   	'double(24,8)', 'real', 'price', 'stock',
	 *  	'date', 'datetime', 'timestamp', 'duration',
	 *  	'boolean', 'checkbox', 'radio', 'array',
	 *  	'email', 'phone', 'url', 'password', 'ip'
	 *		Note: Filter must be a Dolibarr Universal Filter syntax string. Example: "(t.ref:like:'SO-%') or (t.date_creation:>:'20160101') or (t.status:!=:0) or (t.nature:is:NULL)"
	 *  'length' the length of field. Example: 255, '24,8'
	 *  'label' the translation key.
	 *  'langfile' the key of the language file for translation.
	 *  'alias' the alias used into some old hard coded SQL requests
	 *  'picto' is code of a picto to show before value in forms
	 *  'enabled' is a condition when the field must be managed (Example: 1 or 'getDolGlobalInt("MY_SETUP_PARAM")' or 'isModEnabled("multicurrency")' ...)
	 *  'position' is the sort order of field.
	 *  'notnull' is set to 1 if not null in database. Set to -1 if we must set data to null if empty ('' or 0).
	 *  'visible' says if field is visible in list (Examples: 0=Not visible, 1=Visible on list and create/update/view forms, 2=Visible on list only, 3=Visible on create/update/view form only (not list), 4=Visible on list and update/view form (not create). 5=Visible on list and view form (not create/not update). 6=visible on list and update/view form (not update). Using a negative value means field is not shown by default on list but can be selected for viewing)
	 *  'noteditable' says if field is not editable (1 or 0)
	 *  'alwayseditable' says if field can be modified also when status is not draft ('1' or '0')
	 *  'default' is a default value for creation (can still be overwritten by the Setup of Default Values if the field is editable in creation form). Note: If default is set to '(PROV)' and field is 'ref', the default value will be set to '(PROVid)' where id is rowid when a new record is created.
	 *  'index' if we want an index in database.
	 *  'foreignkey'=>'tablename.field' if the field is a foreign key (it is recommended to name the field fk_...).
	 *  'searchall' is 1 if we want to search in this field when making a search from the quick search button.
	 *  'isameasure' must be set to 1 or 2 if field can be used for measure. Field type must be summable like integer or double(24,8). Use 1 in most cases, or 2 if you don't want to see the column total into list (for example for percentage)
	 *  'css' and 'cssview' and 'csslist' is the CSS style to use on field. 'css' is used in creation and update. 'cssview' is used in view mode. 'csslist' is used for columns in lists. For example: 'css'=>'minwidth300 maxwidth500 widthcentpercentminusx', 'cssview'=>'wordbreak', 'csslist'=>'tdoverflowmax200'
	 *  'placeholder' to set the placeholder of a varchar field.
	 *  'help' and 'helplist' is a 'TranslationString' to use to show a tooltip on field. You can also use 'TranslationString:keyfortooltiponlick' for a tooltip on click.
	 *  'showoncombobox' if value of the field must be visible into the label of the combobox that list record
	 *  'disabled' is 1 if we want to have the field locked by a 'disabled' attribute. In most cases, this is never set into the definition of $fields into class, but is set dynamically by some part of code like the constructor of the class.
	 *  'arrayofkeyval' to set a list of values if type is a list of predefined values. For example: array("0"=>"Draft","1"=>"Active","-1"=>"Cancel"). Note that type can be 'integer' or 'varchar'
	 *  'autofocusoncreate' to have field having the focus on a create form. Only 1 field should have this property set to 1.
	 *  'comment' is not used. You can store here any text of your choice. It is not used by application.
	 *	'validate' is 1 if you need to validate the field with $this->validateField(). Need MAIN_ACTIVATE_VALIDATION_RESULT.
	 *  'copytoclipboard' is 1 or 2 to allow to add a picto to copy value into clipboard (1=picto after label, 2=picto after value)
	 *  'description' is a description of the field. Can be used to help the MCP server.
	 *
	 *  Note: To have value dynamic, you can set value to 0 in definition and edit the value on the fly into the constructor.
	 */

	// BEGIN MODULEBUILDER PROPERTIES
	/**
	 * @inheritdoc
	 * Array with all fields and their property. Do not use it as a static var. It may be modified by constructor.
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1, 'css' => 'left', 'comment' => 'Id'),
		'ref' => array('type' => 'varchar(128)', 'label' => 'Ref', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 1, 'index' => 1, 'searchall' => 1, 'showoncombobox' => 1, 'validate' => 1, 'help' => 'WebcamRefHelp', 'lang' => 'webcams@webcams'),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'default' => '1', 'enabled' => 1, 'visible' => 0, 'notnull' => 1, 'position' => 20, 'index' => 1),
		'label' => array('type' => 'varchar(255)', 'label' => 'WebcamName', 'enabled' => 1, 'position' => 30, 'notnull' => 0, 'visible' => 1, 'alwayseditable' => 1, 'searchall' => 1, 'css' => 'minwidth300', 'cssview' => 'wordbreak', 'showoncombobox' => 2, 'validate' => 1, 'lang' => 'webcams@webcams'),
		'fk_soc' => array('type' => 'integer:Societe:societe/class/societe.class.php:1:((status:=:1) AND (entity:IN:__SHARED_ENTITIES__))', 'label' => 'Customer', 'picto' => 'company', 'enabled' => 'isModEnabled("societe")', 'position' => 40, 'notnull' => -1, 'visible' => 1, 'index' => 1, 'css' => 'maxwidth500 widthcentpercentminusxx', 'csslist' => 'tdoverflowmax150', 'validate' => 1, 'lang' => 'webcams@webcams'),
		'location' => array('type' => 'varchar(255)', 'label' => 'WebcamLocation', 'enabled' => 1, 'position' => 50, 'notnull' => 0, 'visible' => 1, 'alwayseditable' => 1, 'searchall' => 1, 'lang' => 'webcams@webcams'),
		'date_installation' => array('type' => 'date', 'label' => 'WebcamInstallDate', 'enabled' => 1, 'position' => 55, 'notnull' => 0, 'visible' => -1, 'alwayseditable' => 1, 'lang' => 'webcams@webcams'),
		'sim' => array('type' => 'varchar(128)', 'label' => 'WebcamSim', 'enabled' => 1, 'position' => 60, 'notnull' => 0, 'visible' => -1, 'alwayseditable' => 1, 'lang' => 'webcams@webcams'),
		'public_url' => array('type' => 'url', 'label' => 'WebcamPublicUrl', 'enabled' => 1, 'position' => 65, 'notnull' => 0, 'visible' => -1, 'alwayseditable' => 1, 'lang' => 'webcams@webcams'),
		'portal_slug' => array('type' => 'varchar(64)', 'label' => 'WebcamPortalSlug', 'enabled' => 1, 'position' => 66, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'url_current' => array('type' => 'url', 'label' => 'WebcamUrlCurrent', 'enabled' => 1, 'position' => 67, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'windy_published' => array('type' => 'integer', 'arrayofkeyval' => array('0' => 'No', '1' => 'Yes'), 'label' => 'WebcamWindyPublished', 'enabled' => 1, 'position' => 68, 'notnull' => 0, 'visible' => -5, 'default' => '0', 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'windy_id' => array('type' => 'varchar(64)', 'label' => 'WebcamWindyId', 'enabled' => 1, 'position' => 69, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'note_public' => array('type' => 'html', 'label' => 'NotePublic', 'enabled' => 1, 'position' => 70, 'notnull' => 0, 'visible' => 0, 'cssview' => 'wordbreak'),
		'note_private' => array('type' => 'html', 'label' => 'NotePrivate', 'enabled' => 1, 'position' => 71, 'notnull' => 0, 'visible' => 0, 'cssview' => 'wordbreak'),
		'last_contact' => array('type' => 'datetime', 'label' => 'WebcamLastContact', 'enabled' => 1, 'position' => 100, 'notnull' => 0, 'visible' => 5, 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'last_capture' => array('type' => 'datetime', 'label' => 'WebcamLastCapture', 'enabled' => 1, 'position' => 101, 'notnull' => 0, 'visible' => 5, 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'last_telemetry' => array('type' => 'datetime', 'label' => 'WebcamLastTelemetry', 'enabled' => 1, 'position' => 102, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'mac' => array('type' => 'varchar(17)', 'label' => 'WebcamMac', 'enabled' => 1, 'position' => 110, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1, 'index' => 1, 'searchall' => 1, 'lang' => 'webcams@webcams'),
		'hostname' => array('type' => 'varchar(100)', 'label' => 'WebcamHostname', 'enabled' => 1, 'position' => 111, 'notnull' => 0, 'visible' => 5, 'noteditable' => 1, 'searchall' => 1, 'lang' => 'webcams@webcams'),
		'router_model' => array('type' => 'varchar(150)', 'label' => 'WebcamRouterModel', 'enabled' => 1, 'position' => 112, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'openwrt' => array('type' => 'varchar(100)', 'label' => 'WebcamOpenwrt', 'enabled' => 1, 'position' => 113, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'kernel' => array('type' => 'varchar(50)', 'label' => 'WebcamKernel', 'enabled' => 1, 'position' => 114, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'app_version' => array('type' => 'varchar(30)', 'label' => 'WebcamAppVersion', 'enabled' => 1, 'position' => 115, 'notnull' => 0, 'visible' => 5, 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'board_fw' => array('type' => 'varchar(30)', 'label' => 'WebcamBoardFw', 'enabled' => 1, 'position' => 116, 'notnull' => 0, 'visible' => 5, 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'camera' => array('type' => 'varchar(150)', 'label' => 'WebcamCamera', 'enabled' => 1, 'position' => 117, 'notnull' => 0, 'visible' => 5, 'noteditable' => 1, 'searchall' => 1, 'lang' => 'webcams@webcams'),
		'camera_serial' => array('type' => 'varchar(64)', 'label' => 'WebcamCameraSerial', 'enabled' => 1, 'position' => 118, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1, 'searchall' => 1, 'lang' => 'webcams@webcams'),
		'vpn_ip' => array('type' => 'varchar(45)', 'label' => 'WebcamVpnIp', 'enabled' => 1, 'position' => 120, 'notnull' => 0, 'visible' => 5, 'noteditable' => 1, 'searchall' => 1, 'lang' => 'webcams@webcams'),
		'public_ip' => array('type' => 'varchar(45)', 'label' => 'WebcamPublicIp', 'enabled' => 1, 'position' => 121, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'wan_ip' => array('type' => 'varchar(45)', 'label' => 'WebcamWanIp', 'enabled' => 1, 'position' => 122, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'uptime' => array('type' => 'integer', 'label' => 'WebcamUptime', 'enabled' => 1, 'position' => 130, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'mem_free_kb' => array('type' => 'integer', 'label' => 'WebcamMemFree', 'enabled' => 1, 'position' => 131, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'disk_free_kb' => array('type' => 'integer', 'label' => 'WebcamDiskFree', 'enabled' => 1, 'position' => 132, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'queue' => array('type' => 'integer', 'label' => 'WebcamQueue', 'enabled' => 1, 'position' => 133, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'upload_protocol' => array('type' => 'varchar(10)', 'label' => 'WebcamUploadProtocol', 'enabled' => 1, 'position' => 140, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'upload_server' => array('type' => 'varchar(200)', 'label' => 'WebcamUploadServer', 'enabled' => 1, 'position' => 141, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'upload_directory' => array('type' => 'varchar(200)', 'label' => 'WebcamUploadDirectory', 'enabled' => 1, 'position' => 142, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'shot_interval' => array('type' => 'integer', 'label' => 'WebcamInterval', 'enabled' => 1, 'position' => 143, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'maintenance' => array('type' => 'integer', 'arrayofkeyval' => array('0' => 'No', '1' => 'Yes'), 'label' => 'WebcamMaintenance', 'enabled' => 1, 'position' => 150, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'heating' => array('type' => 'integer', 'arrayofkeyval' => array('0' => 'No', '1' => 'Yes'), 'label' => 'WebcamHeating', 'enabled' => 1, 'position' => 151, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'monitoring' => array('type' => 'integer', 'arrayofkeyval' => array('0' => 'No', '1' => 'Yes'), 'label' => 'WebcamMonitoring', 'enabled' => 1, 'position' => 152, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'latitude' => array('type' => 'double(10,6)', 'label' => 'WebcamLatitude', 'enabled' => 1, 'position' => 160, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'longitude' => array('type' => 'double(10,6)', 'label' => 'WebcamLongitude', 'enabled' => 1, 'position' => 161, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'temperature' => array('type' => 'double(5,1)', 'label' => 'WebcamTemperature', 'enabled' => 1, 'position' => 170, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'humidity' => array('type' => 'double(5,1)', 'label' => 'WebcamHumidity', 'enabled' => 1, 'position' => 171, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'temperature_ext' => array('type' => 'double(5,1)', 'label' => 'WebcamTemperatureExt', 'enabled' => 1, 'position' => 173, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'humidity_ext' => array('type' => 'double(5,1)', 'label' => 'WebcamHumidityExt', 'enabled' => 1, 'position' => 174, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'last_photo' => array('type' => 'varchar(200)', 'label' => 'WebcamLastPhoto', 'enabled' => 1, 'position' => 175, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'first_contact' => array('type' => 'datetime', 'label' => 'WebcamFirstContact', 'enabled' => 1, 'position' => 180, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'shutter_count' => array('type' => 'integer', 'label' => 'WebcamShutterCount', 'enabled' => 1, 'position' => 182, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'shots_total' => array('type' => 'integer', 'label' => 'WebcamShotsTotal', 'enabled' => 1, 'position' => 183, 'notnull' => 0, 'visible' => 5, 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'camera_hours' => array('type' => 'double(8,1)', 'label' => 'WebcamCameraHours', 'enabled' => 1, 'position' => 184, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'camera_power_cycles' => array('type' => 'integer', 'label' => 'WebcamCameraPowerCycles', 'enabled' => 1, 'position' => 185, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'counters_date' => array('type' => 'datetime', 'label' => 'WebcamCountersDate', 'enabled' => 1, 'position' => 186, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'battery_v' => array('type' => 'double(5,2)', 'label' => 'WebcamBatteryV', 'enabled' => 1, 'position' => 191, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'battery_v_min24' => array('type' => 'double(5,2)', 'label' => 'WebcamBatteryVMin24', 'enabled' => 1, 'position' => 192, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'battery_soc' => array('type' => 'double(5,1)', 'label' => 'WebcamBatterySoc', 'enabled' => 1, 'position' => 193, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'charge_state' => array('type' => 'varchar(20)', 'label' => 'WebcamChargeState', 'enabled' => 1, 'position' => 194, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'solar_wh_yesterday' => array('type' => 'integer', 'label' => 'WebcamSolarWhYesterday', 'enabled' => 1, 'position' => 195, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'victron_error' => array('type' => 'integer', 'label' => 'WebcamVictronError', 'enabled' => 1, 'position' => 196, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'victron_date' => array('type' => 'datetime', 'label' => 'WebcamVictronDate', 'enabled' => 1, 'position' => 197, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'report_json' => array('type' => 'text', 'label' => 'WebcamReportJson', 'enabled' => 1, 'position' => 199, 'notnull' => 0, 'visible' => 0, 'noteditable' => 1, 'lang' => 'webcams@webcams'),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 500, 'notnull' => 1, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 501, 'notnull' => 0, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'picto' => 'user', 'enabled' => 1, 'position' => 510, 'notnull' => 1, 'visible' => -2, 'foreignkey' => '0', 'csslist' => 'tdoverflowmax150'),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'picto' => 'user', 'enabled' => 1, 'position' => 511, 'notnull' => -1, 'visible' => -2, 'csslist' => 'tdoverflowmax150'),
		'last_main_doc' => array('type' => 'varchar(255)', 'label' => 'LastMainDoc', 'enabled' => 1, 'position' => 600, 'notnull' => 0, 'visible' => 0),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => 1, 'position' => 1000, 'notnull' => -1, 'visible' => -2),
		'model_pdf' => array('type' => 'varchar(255)', 'label' => 'Model pdf', 'enabled' => 1, 'position' => 1010, 'notnull' => -1, 'visible' => 0),
		'status' => array('type' => 'integer', 'label' => 'Status', 'enabled' => 1, 'position' => 2000, 'notnull' => 1, 'default' => '1', 'visible' => 1, 'index' => 1, 'arrayofkeyval' => array(0 => 'WebcamStatusToInstall', '1' => 'WebcamStatusInService', 9 => 'WebcamStatusRetired'), 'validate' => 1, 'lang' => 'webcams@webcams'),
	);

	/**
	 * @var int ID
	 */
	public $rowid;
	/** @var int Thirdparty ID */
	public $socid;
	/** @var mixed */
	public $ref;
	/** @var mixed */
	public $entity;
	/** @var mixed */
	public $label;
	/** @var mixed */
	public $fk_soc;
	/** @var mixed */
	public $location;
	/** @var mixed */
	public $date_installation;
	/** @var mixed */
	public $sim;
	/** @var mixed */
	public $public_url;
	/** @var mixed */
	public $note_public;
	/** @var mixed */
	public $note_private;
	/** @var mixed */
	public $last_contact;
	/** @var mixed */
	public $last_capture;
	/** @var mixed */
	public $mac;
	/** @var mixed */
	public $hostname;
	/** @var mixed */
	public $router_model;
	/** @var mixed */
	public $openwrt;
	/** @var mixed */
	public $kernel;
	/** @var mixed */
	public $app_version;
	/** @var mixed */
	public $board_fw;
	/** @var mixed */
	public $camera;
	/** @var mixed */
	public $vpn_ip;
	/** @var mixed */
	public $public_ip;
	/** @var mixed */
	public $wan_ip;
	/** @var mixed */
	public $uptime;
	/** @var mixed */
	public $mem_free_kb;
	/** @var mixed */
	public $disk_free_kb;
	/** @var mixed */
	public $queue;
	/** @var mixed */
	public $upload_protocol;
	/** @var mixed */
	public $upload_server;
	/** @var mixed */
	public $upload_directory;
	/** @var mixed */
	public $shot_interval;
	/** @var mixed */
	public $maintenance;
	/** @var mixed */
	public $heating;
	/** @var mixed */
	public $monitoring;
	/** @var mixed */
	public $latitude;
	/** @var mixed */
	public $longitude;
	/** @var mixed */
	public $temperature;
	/** @var mixed */
	public $humidity;
	/** @var mixed */
	public $temperature_ext;
	/** @var mixed */
	public $humidity_ext;
	/** @var mixed */
	public $portal_slug;
	/** @var mixed */
	public $url_current;
	/** @var mixed */
	public $windy_published;
	/** @var mixed */
	public $windy_id;
	/** @var mixed */
	public $last_telemetry;
	/** @var mixed */
	public $camera_serial;
	/** @var mixed */
	public $shutter_count;
	/** @var mixed */
	public $shots_total;
	/** @var mixed */
	public $camera_hours;
	/** @var mixed */
	public $camera_power_cycles;
	/** @var mixed */
	public $counters_date;
	/** @var mixed */
	public $battery_v;
	/** @var mixed */
	public $battery_v_min24;
	/** @var mixed */
	public $battery_soc;
	/** @var mixed */
	public $charge_state;
	/** @var mixed */
	public $solar_wh_yesterday;
	/** @var mixed */
	public $victron_error;
	/** @var mixed */
	public $victron_date;
	/** @var mixed */
	public $last_photo;
	/** @var mixed */
	public $first_contact;
	/** @var mixed */
	public $report_json;
	/** @var mixed */
	public $date_creation;
	/** @var mixed */
	public $tms;
	/** @var mixed */
	public $fk_user_creat;
	/** @var mixed */
	public $fk_user_modif;
	/** @var mixed */
	public $last_main_doc;
	/** @var mixed */
	public $import_key;
	/** @var mixed */
	public $model_pdf;
	/** @var mixed */
	public $status;
	// END MODULEBUILDER PROPERTIES

	//BEGIN MODULEBUILDER LINES
	// If this object has a subtable with lines

	/**
	 * @var string    Name of subtable line
	 */
	public $table_element_line = '';

	/**
	 * @var string    Field with ID of parent key if this object has a parent
	 */
	public $fk_element = 'fk_webcam';

	/**
	 * @var string    Name of subtable class that manage subtable lines
	 */
	public $class_element_line = 'Webcamline';

	/**
	 * @var array<array<string>|string>		List of child tables. To test if we can delete object.
	 */
	protected $childtables = array();

	/**
	 * @var string[]	List of child tables. To know object to delete on cascade.
	 *               	If name matches '@ClassNAme:FilePathClass;ParentFkFieldName' it will
	 *               	call method deleteByParentField(parentId, ParentFkFieldName) to fetch and delete child object
	 */
	protected $childtablesoncascade = array();

	/**
	 * @var WebcamLine[]     Array of subtable lines
	 */
	public $lines = array();

	//END MODULEBUILDER LINES

	/**
	 * Constructor
	 *
	 * @param	DoliDB $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		global $langs;

		$this->db = $db;

		if (!getDolGlobalInt('MAIN_SHOW_TECHNICAL_ID') && isset($this->fields['rowid']) && !empty($this->fields['ref'])) {
			$this->fields['rowid']['visible'] = 0;
		}
		if (!isModEnabled('multicompany') && isset($this->fields['entity'])) {
			$this->fields['entity']['enabled'] = 0;
		}

		// Example to show how to set values of fields definition dynamically
		/*if ($user->hasRight('webcams', 'webcam', 'read')) {
			$this->fields['myfield']['visible'] = 1;
			$this->fields['myfield']['noteditable'] = 0;
		}*/

		// Unset fields that are disabled
		foreach ($this->fields as $key => $val) {
			if (isset($val['enabled']) && empty($val['enabled'])) {
				unset($this->fields[$key]);
			}
		}

		// Translate some data of arrayofkeyval
		if (is_object($langs)) {
			foreach ($this->fields as $key => $val) {
				if (!empty($val['arrayofkeyval']) && is_array($val['arrayofkeyval'])) {
					foreach ($val['arrayofkeyval'] as $key2 => $val2) {
						$this->fields[$key]['arrayofkeyval'][$key2] = $langs->trans($val2);
					}
				}
			}
		}
	}

	/**
	 * Create object into database
	 *
	 * @param	User		$user		User that creates
	 * @param	int<0,1> 	$notrigger	0=launch triggers after, 1=disable triggers
	 * @return	int<-1,max>				Return integer <0 if KO, Id of created object if OK
	 */
	public function create(User $user, $notrigger = 0)
	{
		$result = $this->createCommon($user, $notrigger);

		// uncomment lines below if you want to validate object after creation
		// if ($result > 0) {
		// $this->fetch($this->id); // needed to retrieve some fields (ie date_creation for masked ref)
		// $resultupdate = $this->validate($user, $notrigger);
		// if ($resultupdate < 0) { return $resultupdate; }
		// }

		return $result;
	}

	/**
	 * Clone an object into another one
	 *
	 * @param	User 	$user		User that creates
	 * @param	int 	$fromid		Id of object to clone
	 * @return	self|int<-1,-1>		New object created, <0 if KO
	 */
	public function createFromClone(User $user, $fromid)
	{
		global $langs, $extrafields;
		$error = 0;

		dol_syslog(__METHOD__, LOG_DEBUG);

		$object = new self($this->db);

		$this->db->begin();

		// Load source object
		$result = $object->fetchCommon($fromid);
		//BEGIN MODULEBUILDER LINES
		if ($result > 0 && !empty($object->table_element_line)) {
			$object->fetchLines();
		}

		// get lines so they will be clone
		foreach ($this->lines as $line) {
			$line->fetch_optionals();
		}
		//END MODULEBUILDER LINES
		// Reset some properties
		unset($object->id);
		unset($object->fk_user_creat);
		unset($object->import_key);

		// Clear fields
		if (property_exists($object, 'ref')) {
			$object->ref = empty($this->fields['ref']['default']) ? "Copy_Of_".$object->ref : $this->fields['ref']['default'];
		}
		if (property_exists($object, 'label')) {
			$object->label = empty($this->fields['label']['default']) ? $langs->trans("CopyOf")." ".$object->label : $this->fields['label']['default'];
		}
		if (property_exists($object, 'status')) {
			$object->status = self::STATUS_DRAFT;
		}
		if (property_exists($object, 'date_creation')) {
			$object->date_creation = dol_now();
		}
		if (property_exists($object, 'date_modification')) {
			$object->date_modification = null;
		}
		// ...
		// Clear extrafields that are unique
		if (is_array($object->array_options) && count($object->array_options) > 0) {
			$extrafields->fetch_name_optionals_label($this->table_element);
			foreach ($object->array_options as $key => $option) {
				$shortkey = preg_replace('/options_/', '', $key);
				if (!empty($extrafields->attributes[$this->table_element]['unique'][$shortkey])) {
					//var_dump($key);
					//var_dump($clonedObj->array_options[$key]); exit;
					unset($object->array_options[$key]);
				}
			}
		}

		// Create clone
		$object->context['createfromclone'] = 'createfromclone';
		$result = $object->createCommon($user);
		if ($result < 0) {
			$error++;
			$this->setErrorsFromObject($object);
		}

		if (!$error) {
			// copy internal contacts
			if ($this->copy_linked_contact($object, 'internal') < 0) {
				$error++;
			}
		}

		if (!$error) {
			// copy external contacts if same company
			if (!empty($object->socid) && ((property_exists($this, 'fk_soc') && ($this->fk_soc == $object->socid)) || (property_exists($this, 'socid') && ($this->socid == $object->socid)))) {	// @phpstan-ignore-line
				if ($this->copy_linked_contact($object, 'external') < 0) {
					$error++;
				}
			}
		}

		unset($object->context['createfromclone']);

		// End
		if (!$error) {
			$this->db->commit();
			return $object;
		} else {
			$this->db->rollback();
			return -1;
		}
	}

	/**
	 * Load object in memory from the database
	 *
	 * @param	int    		$id   			Id object
	 * @param	string 		$ref  			Ref
	 * @param	int<0,1>	$noextrafields	0=Default to load extrafields, 1=No extrafields
	 * @param	int<0,1>	$nolines		0=Default to load lines, 1=No lines
	 * @return	int<-1,1>					Return integer <0 if KO, 0 if not found, >0 if OK
	 */
	public function fetch($id, $ref = null, $noextrafields = 0, $nolines = 0)
	{
		$result = $this->fetchCommon($id, $ref, '', $noextrafields);
		//BEGIN MODULEBUILDER LINES
		if ($result > 0 && !empty($this->table_element_line) && empty($nolines)) {
			$this->fetchLines($noextrafields);
		}
		//END MODULEBUILDER LINES
		return $result;
	}

	/**
	 * Load object lines in memory from the database
	 *
	 * @param	int<0,1>	$noextrafields	0=Default to load extrafields, 1=No extrafields
	 * @return 	int<-1,1>					Return integer <0 if KO, 0 if not found, >0 if OK
	 */
	public function fetchLines($noextrafields = 0)
	{
		$this->lines = array();

		$result = $this->fetchLinesCommon('', $noextrafields);
		return $result;
	}
	//END MODULEBUILDER LINES
	/**
	 * Load list of objects in memory from the database.
	 * Using a fetchAll() with limit = 0 is a very bad practice. Instead try to forge yourself an optimized SQL request with
	 * your own loop with start and stop pagination.
	 *
	 * @param	string		$sortorder	Sort Order
	 * @param	string		$sortfield	Sort field
	 * @param	int<0,max>	$limit		Limit the number of lines returned
	 * @param	int<0,max>	$offset		Offset
	 * @param	string		$filter		Filter as an Universal Search string.
	 *                                  Example: '((client:=:1) OR ((client:>=:2) AND (client:<=:3))) AND (client:!=:8) AND (nom:like:'a%')'
	 * @param	string		$filtermode	No longer used
	 * @return	array<int,self>|int<-1,-1>	 <0 if KO, array of pages if OK
	 */
	public function fetchAll($sortorder = '', $sortfield = '', $limit = 1000, $offset = 0, string $filter = '', $filtermode = 'AND')
	{
		dol_syslog(__METHOD__, LOG_DEBUG);

		$records = array();

		$sql = "SELECT ";
		$sql .= $this->getFieldList('t');
		$sql .= " FROM ".$this->db->prefix().$this->table_element." as t";
		if (!empty($this->isextrafieldmanaged) && $this->isextrafieldmanaged == 1) {
			$sql .= " LEFT JOIN ".$this->db->prefix().$this->table_element."_extrafields as te ON te.fk_object = t.rowid";
		}
		if (!empty($this->ismultientitymanaged) && (int) $this->ismultientitymanaged == 1) {
			$sql .= " WHERE t.entity IN (".getEntity($this->element).")";
		} elseif (preg_match('/^\w+@\w+$/', (string) $this->ismultientitymanaged)) {
			$tmparray = explode('@', (string) $this->ismultientitymanaged);
			$sql .= " LEFT JOIN ".$this->db->prefix().$this->db->sanitize($tmparray[1])." as pt ON t.".$this->db->sanitize($tmparray[0])." = pt.rowid";
			$sql .= " WHERE pt.entity IN (".getEntity($this->element).")";
		} else {
			$sql .= " WHERE 1 = 1";
		}

		// Manage filter
		$errormessage = '';
		$sql .= forgeSQLFromUniversalSearchCriteria($filter, $errormessage);
		if ($errormessage) {
			$this->errors[] = $errormessage;
			dol_syslog(__METHOD__.' '.implode(',', $this->errors), LOG_ERR);
			return -1;
		}

		if (!empty($sortfield)) {
			$sql .= $this->db->order($sortfield, $sortorder);
		}
		if (!empty($limit)) {
			$sql .= $this->db->plimit($limit, $offset);
		}

		$resql = $this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			$i = 0;
			while ($i < ($limit ? min($limit, $num) : $num)) {
				$obj = $this->db->fetch_object($resql);

				$record = new self($this->db);
				$record->setVarsFromFetchObj($obj);

				if (!empty($record->isextrafieldmanaged)) {
					$record->fetch_optionals();
				}

				$records[$record->id] = $record;

				$i++;
			}
			$this->db->free($resql);

			return $records;
		} else {
			$this->errors[] = 'Error '.$this->db->lasterror();
			dol_syslog(__METHOD__.' '.implode(',', $this->errors), LOG_ERR);

			return -1;
		}
	}

	/**
	 * Update object into database
	 *
	 * @param	User		$user		User that modifies
	 * @param	int<0,1>	$notrigger	0=launch triggers after, 1=disable triggers
	 * @return	int<-1,1>				Return integer <0 if KO, >0 if OK
	 */
	public function update(User $user, $notrigger = 0)
	{
		return $this->updateCommon($user, $notrigger);
	}

	/**
	 * Delete object in database
	 *
	 * @param	User		$user		User that deletes
	 * @param	int<0,1> 	$notrigger	0=launch triggers, 1=disable triggers
	 * @return	int<-1,1>				Return integer <0 if KO, >0 if OK
	 */
	public function delete(User $user, $notrigger = 0)
	{
		return $this->deleteCommon($user, $notrigger);
		//return $this->deleteCommon($user, $notrigger, 1);
	}

	//BEGIN MODULEBUILDER LINES
	/**
	 *  Delete a line of object in database
	 *
	 *	@param	User		$user		User that delete
	 *  @param	int			$idline		Id of line to delete
	 *  @param	int<0,1>	$notrigger	0=launch triggers after, 1=disable triggers
	 *  @return	int<-2,1>				>0 if OK, <0 if KO
	 */
	public function deleteLine(User $user, $idline, $notrigger = 0)
	{
		if ($this->status < 0) {
			$this->error = 'ErrorDeleteLineNotAllowedByObjectStatus';
			return -2;
		}

		return $this->deleteLineCommon($user, $idline, $notrigger);
	}
	//END MODULEBUILDER LINES


	/**
	 *	Validate object
	 *
	 *	@param	User		$user		User making status change
	 *  @param	int<0,1>	$notrigger	1=Does not execute triggers, 0= execute triggers
	 *	@return	int<-1,1>				Return integer <=0 if OK, 0=Nothing done, >0 if KO
	 */
	public function validate($user, $notrigger = 0)
	{
		global $conf;

		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

		$error = 0;

		// Protection
		if ($this->status == self::STATUS_VALIDATED) {
			dol_syslog(get_class($this)."::validate action abandoned: already validated", LOG_WARNING);
			return 0;
		}

		/* if (! ((!getDolGlobalInt('MAIN_USE_ADVANCED_PERMS') && $user->hasRight('webcams', 'webcam', 'write'))
		 || (getDolGlobalInt('MAIN_USE_ADVANCED_PERMS') && $user->hasRight('webcams', 'webcam_advance', 'validate')))
		 {
		 $this->error='NotEnoughPermissions';
		 dol_syslog(get_class($this)."::valid ".$this->error, LOG_ERR);
		 return -1;
		 }*/

		$now = dol_now();

		$this->db->begin();

		// Define new ref
		if (preg_match('/^[\(]?PROV/i', $this->ref) || empty($this->ref)) { // empty should not happened, but when it occurs, the test save life
			$num = $this->getNextNumRef();
		} else {
			$num = (string) $this->ref;
		}
		$this->newref = $num;

		if (!empty($num)) {
			// Validate
			$sql = "UPDATE ".$this->db->prefix().$this->table_element;
			$sql .= " SET ";
			if (!empty($this->fields['ref'])) {
				$sql .= " ref = '".$this->db->escape($num)."',";
			}
			$sql .= " status = ".self::STATUS_VALIDATED;
			if (!empty($this->fields['date_validation'])) {
				$sql .= ", date_validation = '".$this->db->idate($now)."'";
			}
			if (!empty($this->fields['fk_user_valid'])) {
				$sql .= ", fk_user_valid = ".((int) $user->id);
			}
			$sql .= " WHERE rowid = ".((int) $this->id);

			dol_syslog(get_class($this)."::validate()", LOG_DEBUG);
			$resql = $this->db->query($sql);
			if (!$resql) {
				dol_print_error($this->db);
				$this->error = $this->db->lasterror();
				$error++;
			}

			if (!$error && !$notrigger) {
				// Call trigger
				$result = $this->call_trigger('WEBCAM_VALIDATE', $user);
				if ($result < 0) {
					$error++;
				}
				// End call triggers
			}
		}

		if (!$error) {
			$this->oldref = $this->ref;

			// Rename directory if dir was a temporary ref
			if (preg_match('/^[\(]?PROV/i', $this->ref)) {
				// Now we rename also files into index
				$sql = 'UPDATE '.$this->db->prefix()."ecm_files set filename = CONCAT('".$this->db->escape($this->newref)."', SUBSTR(filename, ".(strlen($this->ref) + 1).")), filepath = 'webcam/".$this->db->escape($this->newref)."'";
				$sql .= " WHERE filename LIKE '".$this->db->escape($this->ref)."%' AND filepath = 'webcam/".$this->db->escape($this->ref)."' and entity = ".((int) $conf->entity);
				$resql = $this->db->query($sql);
				if (!$resql) {
					$error++;
					$this->error = $this->db->lasterror();
				}
				$sql = 'UPDATE '.$this->db->prefix()."ecm_files set filepath = 'webcam/".$this->db->escape($this->newref)."'";
				$sql .= " WHERE filepath = 'webcam/".$this->db->escape($this->ref)."' and entity = ".((int) $conf->entity);
				$resql = $this->db->query($sql);
				if (!$resql) {
					$error++;
					$this->error = $this->db->lasterror();
				}

				// We rename directory ($this->ref = old ref, $num = new ref) in order not to lose the attachments
				$oldref = dol_sanitizeFileName($this->ref);
				$newref = dol_sanitizeFileName($num);
				$dirsource = $conf->webcams->dir_output.'/webcam/'.$oldref;
				$dirdest = $conf->webcams->dir_output.'/webcam/'.$newref;
				if (!$error && file_exists($dirsource)) {
					dol_syslog(get_class($this)."::validate() rename dir ".$dirsource." into ".$dirdest);

					if (@rename($dirsource, $dirdest)) {
						dol_syslog("Rename ok");
						// Rename docs starting with $oldref with $newref
						$listoffiles = dol_dir_list($conf->webcams->dir_output.'/webcam/'.$newref, 'files', 1, '^'.preg_quote($oldref, '/'));
						foreach ($listoffiles as $fileentry) {
							$dirsource = $fileentry['name'];
							$dirdest = preg_replace('/^'.preg_quote($oldref, '/').'/', $newref, $dirsource);
							$dirsource = $fileentry['path'].'/'.$dirsource;
							$dirdest = $fileentry['path'].'/'.$dirdest;
							@rename($dirsource, $dirdest);
						}
					}
				}
			}
		}

		// Set new ref and current status
		if (!$error) {
			$this->ref = $num;
			$this->status = self::STATUS_VALIDATED;
		}

		if (!$error) {
			$this->db->commit();
			return 1;
		} else {
			$this->db->rollback();
			return -1;
		}
	}


	/**
	 *	Set draft status
	 *
	 *	@param	User		$user		Object user that modify
	 *  @param	int<0,1>	$notrigger	1=Does not execute triggers, 0=Execute triggers
	 *	@return	int<0,1>				Return integer <0 if KO, >0 if OK
	 */
	public function setDraft($user, $notrigger = 0)
	{
		// Protection
		if ($this->status <= self::STATUS_DRAFT) {
			return 0;
		}

		/* if (! ((!getDolGlobalInt('MAIN_USE_ADVANCED_PERMS') && $user->hasRight('webcams','write'))
		 || (getDolGlobalInt('MAIN_USE_ADVANCED_PERMS') && $user->hasRight('webcams','webcams_advance','validate'))))
		 {
		 $this->error='Permission denied';
		 return -1;
		 }*/

		return $this->setStatusCommon($user, self::STATUS_DRAFT, $notrigger, 'WEBCAMS_WEBCAM_UNVALIDATE');
	}

	/**
	 *	Set cancel status
	 *
	 *	@param	User		$user		Object user that modify
	 *  @param	int<0,1>	$notrigger	1=Does not execute triggers, 0=Execute triggers
	 *	@return	int<-1,1>				Return integer <0 if KO, 0=Nothing done, >0 if OK
	 */
	public function cancel($user, $notrigger = 0)
	{
		// Protection
		if ($this->status != self::STATUS_VALIDATED) {
			return 0;
		}

		/* if (! ((!getDolGlobalInt('MAIN_USE_ADVANCED_PERMS') && $user->hasRight('webcams','write'))
		 || (getDolGlobalInt('MAIN_USE_ADVANCED_PERMS') && $user->hasRight('webcams','webcams_advance','validate'))))
		 {
		 $this->error='Permission denied';
		 return -1;
		 }*/

		return $this->setStatusCommon($user, self::STATUS_CANCELED, $notrigger, 'WEBCAMS_WEBCAM_CANCEL');
	}

	/**
	 *	Set back to validated status
	 *
	 *	@param	User		$user			Object user that modify
	 *  @param	int<0,1>	$notrigger		1=Does not execute triggers, 0=Execute triggers
	 *	@return	int<-1,1>					Return integer <0 if KO, 0=Nothing done, >0 if OK
	 */
	public function reopen($user, $notrigger = 0)
	{
		// Protection
		if ($this->status == self::STATUS_VALIDATED) {
			return 0;
		}

		/*if (! ((!getDolGlobalInt('MAIN_USE_ADVANCED_PERMS') && $user->hasRight('webcams','write'))
		 || (getDolGlobalInt('MAIN_USE_ADVANCED_PERMS') && $user->hasRight('webcams','webcams_advance','validate'))))
		 {
		 $this->error='Permission denied';
		 return -1;
		 }*/

		return $this->setStatusCommon($user, self::STATUS_VALIDATED, $notrigger, 'WEBCAMS_WEBCAM_REOPEN');
	}

	/**
	 * getTooltipContentArray
	 *
	 * @param	array<string,string> 	$params 	Params to construct tooltip data
	 * @since 	v18
	 * @return	array{optimize?:string,picto?:string,ref?:string}
	 */
	public function getTooltipContentArray($params)
	{
		global $langs;

		$datas = [];

		if (getDolGlobalInt('MAIN_OPTIMIZEFORTEXTBROWSER')) {
			return ['optimize' => $langs->trans("ShowWebcam")];
		}
		$datas['picto'] = img_picto('', $this->picto).' <u>'.$langs->trans("Webcam").'</u>';
		if (isset($this->status)) {
			$datas['picto'] .= ' '.$this->getLibStatut(5);
		}
		if (property_exists($this, 'ref')) {
			$datas['ref'] = '<br><b>'.$langs->trans('Ref').':</b> '.$this->ref;
		}
		if (property_exists($this, 'label')) {
			$datas['label'] = '<br>'.$langs->trans('Label').':</b> '.$this->label;
		}

		return $datas;
	}

	/**
	 *  Return a link to the object card (with optionally the picto)
	 *
	 *  @param	int     $withpicto                  Include picto in link (0=No picto, 1=Include picto into link, 2=Only picto)
	 *  @param	string  $option                     On what the link point to ('nolink', ...)
	 *  @param	int     $notooltip                  1=Disable tooltip
	 *  @param	string  $morecss                    Add more css on link
	 *  @param	int     $save_lastsearch_value      -1=Auto, 0=No save of lastsearch_values when clicking, 1=Save lastsearch_values whenclicking
	 *  @return	string                              String with URL
	 */
	public function getNomUrl($withpicto = 0, $option = '', $notooltip = 0, $morecss = '', $save_lastsearch_value = -1)
	{
		global $conf, $langs, $hookmanager;

		if (!empty($conf->dol_no_mouse_hover)) {
			$notooltip = 1; // Force disable tooltips
		}

		$result = '';
		$params = [
			'id' => (string) $this->id,
			'objecttype' => $this->element.($this->module ? '@'.$this->module : ''),
			'option' => $option,
		];
		$classfortooltip = 'classfortooltip';
		$dataparams = '';
		if (getDolGlobalInt('MAIN_ENABLE_AJAX_TOOLTIP')) {
			$classfortooltip = 'classforajaxtooltip';
			$dataparams = ' data-params="'.dol_escape_htmltag(json_encode($params)).'"';
			$label = '';
		} else {
			$label = implode($this->getTooltipContentArray($params));
		}

		$baseurl = dol_buildpath('/webcams/webcam_card.php', 1);
		$query = ['id' => $this->id];
		if ($option !== 'nolink') {
			// Add param to save lastsearch_values or not
			$add_save_lastsearch_values = ($save_lastsearch_value == 1 ? 1 : 0);
			if ($save_lastsearch_value == -1 && isset($_SERVER["PHP_SELF"]) && preg_match('/list\.php/', $_SERVER["PHP_SELF"])) {
				$add_save_lastsearch_values = 1;
			}
			if ($add_save_lastsearch_values) {
				$query = array_merge($query, ['save_lastsearch_values' => 1]);
			}
		}
		$url = dolBuildUrl($baseurl, $query);

		$linkclose = '';
		if (empty($notooltip)) {
			if (getDolGlobalInt('MAIN_OPTIMIZEFORTEXTBROWSER')) {
				$label = $langs->trans("ShowWebcam");
				$linkclose .= ' alt="'.dolPrintHTMLForAttribute($label).'"';
			}
			$linkclose .= ($label ? ' title="'.dolPrintHTMLForAttribute($label).'"' : ' title="tocomplete"');
			$linkclose .= $dataparams.' class="'.$classfortooltip.($morecss ? ' '.$morecss : '').'"';
		} else {
			$linkclose = ($morecss ? ' class="'.$morecss.'"' : '');
		}

		if ($option == 'nolink') {
			$linkstart = '<span';
		} else {
			$linkstart = '<a href="'.$url.'"';
		}
		$linkstart .= $linkclose.'>';
		if ($option == 'nolink') {
			$linkend = '</span>';
		} else {
			$linkend = '</a>';
		}

		$result .= $linkstart;

		if (empty($this->showphoto_on_popup)) {
			if ($withpicto) {
				$result .= img_object(($notooltip ? '' : $label), ($this->picto ? $this->picto : 'generic'), (($withpicto != 2) ? 'class="paddingright"' : ''), 0, 0, $notooltip ? 0 : 1);
			}
		} else {
			if ($withpicto) {
				require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

				list($class, $module) = explode('@', $this->picto);
				$upload_dir = $conf->$module->multidir_output[$conf->entity]."/$class/".dol_sanitizeFileName($this->ref);
				$filearray = dol_dir_list($upload_dir, "files");
				$filename = $filearray[0]['name'];
				if (!empty($filename)) {
					$pospoint = strpos($filearray[0]['name'], '.');

					$pathtophoto = $class.'/'.$this->ref.'/thumbs/'.substr($filename, 0, $pospoint).'_mini'.substr($filename, $pospoint);
					if (!getDolGlobalString(strtoupper($module.'_'.$class).'_FORMATLISTPHOTOSASUSERS')) {
						$result .= '<div class="floatleft inline-block valignmiddle divphotoref"><div class="photoref"><img class="photo'.$module.'" alt="No photo" border="0" src="'.DOL_URL_ROOT.'/viewimage.php?modulepart='.$module.'&entity='.$conf->entity.'&file='.urlencode($pathtophoto).'"></div></div>';
					} else {
						$result .= '<div class="floatleft inline-block valignmiddle divphotoref"><img class="photouserphoto userphoto" alt="No photo" border="0" src="'.DOL_URL_ROOT.'/viewimage.php?modulepart='.$module.'&entity='.$conf->entity.'&file='.urlencode($pathtophoto).'"></div>';
					}

					$result .= '</div>';
				} else {
					$result .= img_object(($notooltip ? '' : $label), ($this->picto ? $this->picto : 'generic'), ($notooltip ? (($withpicto != 2) ? 'class="paddingright"' : '') : 'class="'.(($withpicto != 2) ? 'paddingright ' : '').'"'), 0, 0, $notooltip ? 0 : 1);
				}
			}
		}

		if ($withpicto != 2) {
			$result .= $this->ref;
		}

		$result .= $linkend;
		//if ($withpicto != 2) $result.=(($addlabel && $this->label) ? $sep . dol_trunc($this->label, ($addlabel > 1 ? $addlabel : 0)) : '');

		global $action, $hookmanager;
		$hookmanager->initHooks(array($this->element.'dao'));
		$parameters = array('id' => $this->id, 'getnomurl' => &$result);
		$reshook = $hookmanager->executeHooks('getNomUrl', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks
		if ($reshook > 0) {
			$result = $hookmanager->resPrint;
		} else {
			$result .= $hookmanager->resPrint;
		}

		return $result;
	}

	/**
	 *	Return a thumb for kanban views
	 *
	 *	@param	string	    			$option		Where point the link (0=> main card, 1,2 => shipment, 'nolink'=>No link)
	 *  @param	?array<string,mixed>	$arraydata	Array of data
	 *  @return	string								HTML Code for Kanban thumb.
	 */
	public function getKanbanView($option = '', $arraydata = null)
	{
		global $conf, $langs;

		$selected = (empty($arraydata['selected']) ? 0 : $arraydata['selected']);

		$return = '<div class="box-flex-item box-flex-grow-zero">';
		$return .= '<div class="info-box info-box-sm">';
		$return .= '<span class="info-box-icon bg-infobox-action">';
		$return .= img_picto('', $this->picto);
		$return .= '</span>';
		$return .= '<div class="info-box-content">';
		$return .= '<span class="info-box-ref inline-block tdoverflowmax150 valignmiddle">'.(method_exists($this, 'getNomUrl') ? $this->getNomUrl() : $this->ref).'</span>';
		if ($selected >= 0) {
			$return .= '<input id="cb'.$this->id.'" class="flat checkforselect fright" type="checkbox" name="toselect[]" value="'.$this->id.'"'.($selected ? ' checked="checked"' : '').'>';
		}
		if (property_exists($this, 'label')) {
			$return .= ' <div class="inline-block opacitymedium valignmiddle tdoverflowmax100">'.$this->label.'</div>';
		}
		if (property_exists($this, 'thirdparty') && is_object($this->thirdparty)) {
			$return .= '<br><div class="info-box-ref tdoverflowmax150">'.$this->thirdparty->getNomUrl(1).'</div>';
		}
		if (property_exists($this, 'amount')) {
			$return .= '<br>';
			$return .= '<span class="info-box-label amount">'.price($this->amount, 0, $langs, 1, -1, -1, $conf->currency).'</span>';
		}
		if (method_exists($this, 'getLibStatut')) {
			$return .= '<br><div class="info-box-status">'.$this->getLibStatut(3).'</div>';
		}
		$return .= '</div>';
		$return .= '</div>';
		$return .= '</div>';

		return $return;
	}

	/**
	 *  Return the label of the status
	 *
	 *  @param	int<0,6>	$mode          0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto, 6=Long label + Picto
	 *  @return	string 			       Label of status
	 */
	public function getLabelStatus($mode = 0)
	{
		return $this->LibStatut($this->status, $mode);
	}

	/**
	 *  Return the label of the status
	 *
	 *  @param	int<0,6>	$mode	0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto, 6=Long label + Picto
	 *  @return	string				Label of status
	 */
	public function getLibStatut($mode = 0)
	{
		return $this->LibStatut($this->status, $mode);
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *  Return the label of a given status
	 *
	 *  @param	int			$status		Id status
	 *  @param	int<0,6>	$mode		0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto, 6=Long label + Picto
	 *  @return	string					Label of status
	 */
	public function LibStatut($status, $mode = 0)
	{
		// phpcs:enable
		if (is_null($status)) {
			return '';
		}

		$paramsBadge = array('badgeParams' => array('attr' => array(
			'data-status-element' => $this->element,
			'data-status' => (int) $status
		)));


		if (empty($this->labelStatus) || empty($this->labelStatusShort)) {
			global $langs;
			$langs->load("webcams@webcams");
			$this->labelStatus[self::STATUS_DRAFT] = $langs->transnoentitiesnoconv('WebcamStatusToInstall');
			$this->labelStatus[self::STATUS_VALIDATED] = $langs->transnoentitiesnoconv('WebcamStatusInService');
			$this->labelStatus[self::STATUS_CANCELED] = $langs->transnoentitiesnoconv('WebcamStatusRetired');
			$this->labelStatusShort = $this->labelStatus;
		}

		$statusType = 'status'.$status;
		if ($status == self::STATUS_VALIDATED) {
			$statusType = 'status4';
		}
		if ($status == self::STATUS_CANCELED) {
			$statusType = 'status6';
		}

		return dolGetStatus($this->labelStatus[$status], $this->labelStatusShort[$status], '', $statusType, $mode, '', $paramsBadge);
	}

	/**
	 *	Load the info information in the object
	 *
	 *	@param	int		$id       Id of object
	 *	@return	void
	 */
	public function info($id)
	{
		$sql = "SELECT t.rowid, t.date_creation as datec";
		if (!empty($this->isextrafieldmanaged) && $this->isextrafieldmanaged == 1) {
			$sql .= ", GREATEST(t.tms, COALESCE(te.tms, t.tms)) as datem";
		} else {
			$sql .= ", t.tms as datem";
		}
		if (!empty($this->fields['date_validation'])) {
			$sql .= ", t.date_validation as datev";
		}
		if (!empty($this->fields['fk_user_creat'])) {
			$sql .= ", t.fk_user_creat";
		}
		if (!empty($this->fields['fk_user_modif'])) {
			$sql .= ", t.fk_user_modif";
		}
		if (!empty($this->fields['fk_user_valid'])) {
			$sql .= ", t.fk_user_valid";
		}
		$sql .= " FROM ".$this->db->prefix().$this->table_element." as t";
		if (!empty($this->isextrafieldmanaged) && $this->isextrafieldmanaged == 1) {
			$sql .= " LEFT JOIN ".$this->db->prefix().$this->table_element."_extrafields as te ON te.fk_object = t.rowid";
		}
		$sql .= " WHERE t.rowid = ".((int) $id);

		$result = $this->db->query($sql);
		if ($result) {
			if ($this->db->num_rows($result)) {
				$obj = $this->db->fetch_object($result);

				$this->id = $obj->rowid;

				if (!empty($this->fields['fk_user_creat'])) {
					$this->user_creation_id = $obj->fk_user_creat;
				}
				if (!empty($this->fields['fk_user_modif'])) {
					$this->user_modification_id = $obj->fk_user_modif;
				}
				if (!empty($this->fields['fk_user_valid'])) {
					$this->user_validation_id = $obj->fk_user_valid;
				}
				$this->date_creation = $this->db->jdate($obj->datec);
				$this->date_modification = empty($obj->datem) ? '' : $this->db->jdate($obj->datem);
				if (!empty($obj->datev)) {
					$this->date_validation = empty($obj->datev) ? '' : $this->db->jdate($obj->datev);
				}
			}

			$this->db->free($result);
		} else {
			dol_print_error($this->db);
		}
	}

	/**
	 * Initialize object with example values
	 * Id must be 0 if object instance is a specimen
	 *
	 * @return	int
	 */
	public function initAsSpecimen()
	{
		// Set here init that are not commonf fields
		// $this->property1 = ...
		// $this->property2 = ...

		return $this->initAsSpecimenCommon();
	}

	//BEGIN MODULEBUILDER LINES
	/**
	 * 	Create an array of lines
	 *
	 * 	@return	array<CommonObjectLine>|int		array of lines if OK, <0 if KO
	 */
	public function getLinesArray()
	{
		$this->lines = array();

		$objectline = new WebcamLine($this->db);
		$result = $objectline->fetchAll('ASC', 'position', 0, 0, '(fk_webcam:=:'.((int) $this->id).')');

		if (is_numeric($result)) {
			$this->setErrorsFromObject($objectline);
			return $result;
		} else {
			/** @phpstan-ignore-next-line */
			$this->lines = $result;
			return $this->lines;
		}
	}
	//END MODULEBUILDER LINES

	/**
	 *  Returns the reference to the following non used object depending on the active numbering module.
	 *
	 *  @return	string      		Object free reference
	 */
	public function getNextNumRef()
	{
		global $langs, $conf;
		$langs->load("webcams@webcams");

		if (!getDolGlobalString('WEBCAMS_WEBCAM_ADDON')) {
			$conf->global->WEBCAMS_WEBCAM_ADDON = 'mod_webcam_standard';
		}

		if (getDolGlobalString('WEBCAMS_WEBCAM_ADDON')) {
			$mybool = false;

			$file = getDolGlobalString('WEBCAMS_WEBCAM_ADDON').".php";
			$classname = getDolGlobalString('WEBCAMS_WEBCAM_ADDON');

			// Include file with class
			$dirmodels = array_merge(array('/'), (array) $conf->modules_parts['models']);
			foreach ($dirmodels as $reldir) {
				$dir = dol_buildpath($reldir."core/modules/webcams/");

				// Load file with numbering class (if found)
				$mybool = $mybool || @include_once $dir.$file;
			}

			if (!$mybool) {
				dol_print_error(null, "Failed to include file ".$file);
				return '';
			}

			if (class_exists($classname)) {
				$obj = new $classname();
				'@phan-var-force ModeleNumRefWebcam $obj';
				$numref = $obj->getNextValue($this);

				if ($numref != '' && $numref != '-1') {
					return $numref;
				} else {
					$this->error = $obj->error;
					//dol_print_error($this->db,get_class($this)."::getNextNumRef ".$obj->error);
					return "";
				}
			} else {
				print $langs->trans("Error")." ".$langs->trans("ClassNotFound").' '.$classname;
				return "";
			}
		} else {
			print $langs->trans("ErrorNumberingModuleNotSetup", $this->element);
			return "";
		}
	}

	/**
	 *  Create a document onto disk according to template module.
	 *
	 *  @param	string		$modele			Force template to use ('' to not force)
	 *  @param	Translate	$outputlangs	object lang a utiliser pour traduction
	 *  @param	int<0,1>	$hidedetails    Hide details of lines
	 *  @param	int<0,1>	$hidedesc       Hide description
	 *  @param	int<0,1>	$hideref        Hide ref
	 *  @param	?array<string,string>  $moreparams     Array to provide more information
	 *  @return	int         				0 if KO, 1 if OK
	 */
	public function generateDocument($modele, $outputlangs, $hidedetails = 0, $hidedesc = 0, $hideref = 0, $moreparams = null)
	{
		global $langs;

		$result = 0;
		$includedocgeneration = 0;

		$langs->load("webcams@webcams");

		if (!dol_strlen($modele)) {
			if (!empty($this->model_pdf)) {
				$modele = $this->model_pdf;
			} else {
				$modele = getDolGlobalString('WEBCAM_ADDON_PDF', 'standard_webcam');
			}
		}

		$modelpath = "core/modules/webcams/doc/";

		if ($includedocgeneration && !empty($modele)) {
			$result = $this->commonGenerateDocument($modelpath, $modele, $outputlangs, $hidedetails, $hidedesc, $hideref, $moreparams);
		}

		return $result;
	}

	/**
	 * Return validation test result for a field.
	 * Need MAIN_ACTIVATE_VALIDATION_RESULT to be called.
	 *
	 * @param   array<string,array{type:string,label:string,enabled:int<0,2>|string,position:int,notnull?:int,visible:int<-2,5>|string,noteditable?:int<0,1>,default?:int<0,1>|string,index?:int,foreignkey?:string,searchall?:int<0,1>,isameasure?:int<0,1>,css?:string,csslist?:string,help?:string,showoncombobox?:int<0,2>,disabled?:int<0,1>,arrayofkeyval?:array<int|string,string>,comment?:string,validate?:int<0,1>}>  $fields Array of properties of field to show
	 * @param	string  $fieldKey            Key of attribute
	 * @param	string  $fieldValue          value of attribute
	 * @return	bool 						Return false if fail, true on success, set $this->error for error message
	 */
	public function validateField($fields, $fieldKey, $fieldValue)
	{
		// Add your own validation rules here.
		// ...

		return parent::validateField($fields, $fieldKey, $fieldValue);
	}

	/**
	 * Action executed by scheduler
	 * CAN BE A CRON TASK. In such a case, parameters come from the schedule job setup field 'Parameters'
	 * Use public function doScheduledJob($param1, $param2, ...) to get parameters
	 *
	 * @return	int			0 if OK, <>0 if KO (this function is used also by cron so only 0 is OK)
	 */
	public function doScheduledJob()
	{
		//global $conf, $langs;

		//$conf->global->SYSLOG_FILE = 'DOL_DATA_ROOT/dolibarr_mydedicatedlogfile.log';

		$error = 0;
		$this->output = '';
		$this->error = '';

		dol_syslog(__METHOD__." start", LOG_INFO);

		//$now = dol_now();

		$this->db->begin();

		// ...

		$this->db->commit();

		dol_syslog(__METHOD__." end", LOG_INFO);

		return $error;
	}
}


require_once DOL_DOCUMENT_ROOT.'/core/class/commonobjectline.class.php';
//BEGIN MODULEBUILDER LINES
/**
 * Class WebcamLine. You can also remove this and generate a CRUD class for lines objects.
 */
class WebcamLine extends CommonObjectLine
{
	// To complete with content of an object WebcamLine
	// We should have a field rowid, fk_webcam and position

	/**
	 * To overload
	 * @see CommonObjectLine
	 */
	public $parent_element = '';		// Example: '' or 'webcam'

	/**
	 * To overload
	 * @see CommonObjectLine
	 */
	public $fk_parent_attribute = '';	// Example: '' or 'fk_webcam'

	/**
	 * @var int<0,1>	Does object support extrafields ? 0=No, 1=Yes
	 */
	public $isextrafieldmanaged = 1;

	/**
	 * @var int<0,1>|string|null  	Does this object support multicompany module ?
	 * 								0=No test on entity, 1=Test with field entity in local table, 'field@table'=Test entity into the field@table (example 'fk_soc@societe')
	 */
	public $ismultientitymanaged = 0;


	/**
	 * Constructor
	 *
	 * @param	DoliDB $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		$this->db = $db;
	}
}
//END MODULEBUILDER LINES
