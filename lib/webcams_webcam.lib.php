<?php
/* Copyright (C) ---Replace with your own copyright and developer email---
 * Copyright (C) 2025       Frédéric France         <frederic.france@free.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    htdocs/modulebuilder/template/lib/webcams_webcam.lib.php
 * \ingroup webcams
 * \brief   Library files with common functions for Webcam
 */

require_once __DIR__.'/webcams_compat.lib.php';
require_once __DIR__.'/webcams_plan.lib.php';

/**
 * Prepare array of tabs for Webcam
 *
 * @param	Webcam	$object					Webcam
 * @return 	array<array{string,string,string}>	Array of tabs
 */
function webcamPrepareHead($object)
{
	global $db, $langs, $conf;

	$langs->load("webcams@webcams");

	// BEGIN MODULEBUILDER TABFLAG CONTACT
	$showtabofpagecontact = getDolGlobalInt('MAIN_WEBCAMS_SHOW_PAGE_OF_CONTACT');
	// END MODULEBUILDER TABFLAG CONTACT
	// BEGIN MODULEBUILDER TABFLAG NOTE
	$showtabofpagenote = getDolGlobalInt('MAIN_WEBCAMS_SHOW_PAGE_OF_NOTE', 1);
	// END MODULEBUILDER TABFLAG NOTE
	// BEGIN MODULEBUILDER TABFLAG DOCUMENT
	$showtabofpagedocument = getDolGlobalInt('MAIN_WEBCAMS_SHOW_PAGE_OF_DOCUMENT', 1);
	// END MODULEBUILDER TABFLAG DOCUMENT
	// BEGIN MODULEBUILDER TABFLAG AGENDA
	$showtabofpageagenda = getDolGlobalInt('MAIN_WEBCAMS_SHOW_PAGE_OF_AGENDA', 1);
	// END MODULEBUILDER TABFLAG AGENDA

	$h = 0;
	$head = array();

	$head[$h][0] = dolBuildUrl(dol_buildpath("/webcams/webcam_card.php", 1), ['id' => $object->id]);
	$head[$h][1] = $langs->trans("Webcam");
	$head[$h][2] = 'card';
	$h++;

	// BEGIN MODULEBUILDER TAB CONTACT
	if ($showtabofpagecontact) {
		$head[$h][0] = dolBuildUrl(dol_buildpath("/webcams/webcam_contact.php", 1), ['id' => $object->id]);
		$head[$h][1] = $langs->trans("Contacts");
		$head[$h][2] = 'contact';
		$h++;
	}
	// END MODULEBUILDER TAB CONTACT

	// BEGIN MODULEBUILDER TAB NOTE
	if ($showtabofpagenote) {
		if (isset($object->fields['note_public']) || isset($object->fields['note_private'])) {
			$nbNote = 0;
			if (!empty($object->note_private)) {
				$nbNote++;
			}
			if (!empty($object->note_public)) {
				$nbNote++;
			}
			$head[$h][0] = dolBuildUrl(dol_buildpath('/webcams/webcam_note.php', 1), ['id' => $object->id]);
			$head[$h][1] = $langs->trans('Notes');
			if ($nbNote > 0) {
				$head[$h][1] .= (!getDolGlobalInt('MAIN_OPTIMIZEFORTEXTBROWSER') ? '<span class="badge marginleftonlyshort">'.$nbNote.'</span>' : '');
			}
			$head[$h][2] = 'note';
			$h++;
		}
	}
	// END MODULEBUILDER TAB NOTE

	// BEGIN MODULEBUILDER TAB DOCUMENT
	if ($showtabofpagedocument) {
		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
		require_once DOL_DOCUMENT_ROOT.'/core/class/link.class.php';
		$upload_dir = $conf->webcams->dir_output."/webcam/".dol_sanitizeFileName($object->ref);
		$nbFiles = count(dol_dir_list($upload_dir, 'files', 0, '', '(\.meta|_preview.*\.png)$'));
		$nbLinks = Link::count($db, $object->element, $object->id);
		$head[$h][0] = dolBuildUrl(dol_buildpath("/webcams/webcam_document.php", 1), ['id' => $object->id]);
		$head[$h][1] = $langs->trans('Documents');
		if (($nbFiles + $nbLinks) > 0) {
			$head[$h][1] .= '<span class="badge marginleftonlyshort">'.($nbFiles + $nbLinks).'</span>';
		}
		$head[$h][2] = 'document';
		$h++;
	}
	// END MODULEBUILDER TAB DOCUMENT

	// Register of the fingerprints (O2, 1.4): days deposited by webcam-portal
	$nbreg = 0;
	$resql = $db->query("SELECT COUNT(*) as nb FROM ".$db->prefix()."webcams_register WHERE fk_webcam = ".((int) $object->id));
	if ($resql && ($o = $db->fetch_object($resql))) {
		$nbreg = (int) $o->nb;
	}
	$head[$h][0] = dolBuildUrl(dol_buildpath("/webcams/webcam_register.php", 1), ['id' => $object->id]);
	$head[$h][1] = $langs->trans('WebcamRegister');
	if ($nbreg > 0) {
		$head[$h][1] .= '<span class="badge marginleftonlyshort">'.$nbreg.'</span>';
	}
	$head[$h][2] = 'register';
	$h++;

	// BEGIN MODULEBUILDER TAB AGENDA
	if ($showtabofpageagenda) {
		$head[$h][0] = dolBuildUrl(dol_buildpath("/webcams/webcam_agenda.php", 1), ['id' => $object->id]);
		$head[$h][1] = $langs->trans("Events");
		$head[$h][2] = 'agenda';
		$h++;
	}
	// END MODULEBUILDER TAB AGENDA

	// Show more tabs from modules
	// Entries must be declared in modules descriptor with line
	//$this->tabs = array(
	//	'entity:+tabname:Title:@webcams:/webcams/mypage.php?id=__ID__'
	//); // to add new tab
	//$this->tabs = array(
	//	'entity:-tabname:Title:@webcams:/webcams/mypage.php?id=__ID__'
	//); // to remove a tab
	complete_head_from_modules($conf, $langs, $object, $head, $h, 'webcam@webcams');

	complete_head_from_modules($conf, $langs, $object, $head, $h, 'webcam@webcams', 'remove');

	return $head;
}

/**
 * Shutter life of the camera (shots), from setup (WEBCAMS_SHUTTER_LIFE)
 *
 * @return	int		Number of shots (100000 for Canon EOS 2000D/1500D)
 */
function webcamsShutterLife()
{
	$v = getDolGlobalInt('WEBCAMS_SHUTTER_LIFE', 100000);
	return $v > 0 ? $v : 100000;
}

/**
 * Shots used by the camera: shutter counter read from the camera if known,
 * otherwise the counter of the router (shots_total).
 *
 * @param	object		$obj	Webcam or row with shutter_count and shots_total
 * @return	int|null			Shots or null if unknown
 */
function webcamsShotsUsed($obj)
{
	if (isset($obj->shutter_count) && $obj->shutter_count !== null && $obj->shutter_count !== '') {
		return (int) $obj->shutter_count;
	}
	if (isset($obj->shots_total) && $obj->shots_total !== null && $obj->shots_total !== '') {
		return (int) $obj->shots_total;
	}
	return null;
}

/**
 * Level of wear of the shutter
 *
 * @param	int|null	$shots	Shots used
 * @return	string				'' (ok or unknown), 'warning' (from WEBCAMS_SHUTTER_WARN_PCT, default 90%), 'danger' (life reached)
 */
function webcamsShutterLevel($shots)
{
	if ($shots === null) {
		return '';
	}
	if ($shots >= webcamsShutterLife()) {
		return 'danger';
	}
	if ($shots >= webcamsShutterWarnShots()) {
		return 'warning';
	}
	return '';
}

/**
 * Percentage of the shutter life from which the camera is near the end of its
 * life, from setup (WEBCAMS_SHUTTER_WARN_PCT)
 *
 * @return	int		1-100 (default 90)
 */
function webcamsShutterWarnPct()
{
	$v = getDolGlobalInt('WEBCAMS_SHUTTER_WARN_PCT', 90);
	return ($v >= 1 && $v <= 100) ? $v : 90;
}

/**
 * Shots from which the camera is near the end of the shutter life. Same
 * threshold for the level (webcamsShutterLevel), the events in the agenda and
 * GET webcams/{id}/portal.
 *
 * @return	int
 */
function webcamsShutterWarnShots()
{
	return (int) ceil(webcamsShutterLife() * webcamsShutterWarnPct() / 100);
}

/**
 * Availability commitment of the rental contract, with the defaults of the
 * setup applied to the empty fields (as GET webcams/{id}/portal)
 *
 * @param	object	$obj	Webcam or row with the sla_* columns
 * @return	array{enabled:bool,offline_hours:int,response_days:int,target_pct:float,hours:string,weekdays:string,defaults:string[]}
 */
function webcamsSla($obj)
{
	$def = array(
		'offline_hours' => getDolGlobalInt('WEBCAMS_SLA_OFFLINE_HOURS', 3) > 0 ? getDolGlobalInt('WEBCAMS_SLA_OFFLINE_HOURS', 3) : 3,
		'response_days' => getDolGlobalInt('WEBCAMS_SLA_RESPONSE_DAYS', 3) > 0 ? getDolGlobalInt('WEBCAMS_SLA_RESPONSE_DAYS', 3) : 3,
		'target_pct' => webcamsSlaDefaultTarget(),
	);
	$res = array('enabled' => !empty($obj->sla_enabled), 'offline_hours' => 0, 'response_days' => 0, 'target_pct' => 0.0, 'hours' => '', 'weekdays' => '', 'defaults' => array());
	foreach ($def as $k => $v) {
		$col = 'sla_'.$k;
		$cur = isset($obj->$col) ? $obj->$col : null;
		if ($cur === null || $cur === '' || (float) $cur <= 0) {
			$res[$k] = $v;
			$res['defaults'][] = $k;
		} else {
			$res[$k] = ($k == 'target_pct') ? round((float) $cur, 2) : (int) $cur;
		}
	}
	$res['target_pct'] = (float) $res['target_pct'];
	$res['hours'] = isset($obj->sla_hours) ? (string) $obj->sla_hours : '';
	$res['weekdays'] = isset($obj->sla_weekdays) ? (string) $obj->sla_weekdays : '';
	return $res;
}

/**
 * Default monthly availability promised (WEBCAMS_SLA_TARGET_PCT, 98)
 *
 * @return	float
 */
function webcamsSlaDefaultTarget()
{
	$v = str_replace(',', '.', getDolGlobalString('WEBCAMS_SLA_TARGET_PCT', '98'));
	return (is_numeric($v) && (float) $v > 0 && (float) $v <= 100) ? round((float) $v, 2) : 98.0;
}

/**
 * Percentage for display: 99,12% or 98%
 *
 * @param	float|string|null	$v		Value
 * @return	string
 */
function webcamsFormatPct($v)
{
	global $langs;
	if ($v === null || $v === '') {
		return '-';
	}
	$v = round((float) $v, 2);
	$dec = ($v == round($v)) ? 0 : (($v == round($v, 1)) ? 1 : 2);
	return price($v, 0, $langs, 0, $dec, $dec).'%';
}

/**
 * Month AAAA-MM for display (novembre 2026)
 *
 * @param	string		$period		AAAA-MM
 * @param	Translate	$outlangs	Language
 * @return	string
 */
function webcamsFormatPeriod($period, $outlangs = null)
{
	global $langs;
	$l = $outlangs ? $outlangs : $langs;
	if (!preg_match('/^(\d{4})-(\d{2})$/', (string) $period, $m)) {
		return (string) $period;
	}
	$l->load('main');
	return $l->transnoentitiesnoconv('Month'.$m[2]).' '.$m[1];
}

/**
 * Months of the availability register of a webcam, most recent first
 *
 * @param	DoliDB	$db			Database
 * @param	int		$webcamid	Id of webcam
 * @param	string	$from		First month AAAA-MM ('' = no limit)
 * @param	string	$to			Last month AAAA-MM ('' = no limit)
 * @param	int		$limit		Max rows (0 = all)
 * @return	array<int,object>	Rows of llx_webcams_availability
 */
function webcamsAvailabilityMonths($db, $webcamid, $from = '', $to = '', $limit = 0)
{
	$sql = "SELECT rowid, fk_soc, period, availability_pct, expected_hours, excluded_hours, available_hours, incidents, downtime_hours,";
	$sql .= " response_breaches, sla_enabled, target_pct, sla_met, final, date_creation, tms";
	$sql .= " FROM ".$db->prefix()."webcams_availability";
	$sql .= " WHERE fk_webcam = ".((int) $webcamid)." AND entity IN (".getEntity('webcam').")";
	if ($from !== '') {
		$sql .= " AND period >= '".$db->escape($from)."'";
	}
	if ($to !== '') {
		$sql .= " AND period <= '".$db->escape($to)."'";
	}
	$sql .= " ORDER BY period DESC";
	if ($limit > 0) {
		$sql .= $db->plimit($limit);
	}
	$rows = array();
	$resql = $db->query($sql);
	while ($resql && ($obj = $db->fetch_object($resql))) {
		$rows[] = $obj;
	}
	return $rows;
}

/**
 * Whether a month of the register is below the threshold: commitment not met
 * (sla_met = 0) or, without the field, percentage below the target
 *
 * @param	object		$row		Row of llx_webcams_availability
 * @param	float		$target		Target to use when the row has none
 * @return	bool|null				null if unknown (no useful hours)
 */
function webcamsAvailabilityBelow($row, $target)
{
	if ($row->sla_met !== null && $row->sla_met !== '' && !empty($row->sla_enabled)) {
		return !$row->sla_met;
	}
	if ($row->availability_pct === null || $row->availability_pct === '') {
		return null;
	}
	$t = ($row->target_pct !== null && $row->target_pct !== '') ? (float) $row->target_pct : (float) $target;
	return (float) $row->availability_pct < $t;
}

/**
 * Table of the availability of the last months, for the card of the webcam
 *
 * @param	DoliDB	$db			Database
 * @param	Webcam	$object		Webcam
 * @param	int		$months		Number of months
 * @return	string				HTML
 */
function webcamsAvailabilityTable($db, $object, $months = 12)
{
	global $langs;

	$langs->load('webcams@webcams');
	$sla = webcamsSla($object);
	$from = dol_print_date(dol_time_plus_duree(dol_now(), -($months - 1), 'm'), '%Y-%m');
	$rows = webcamsAvailabilityMonths($db, $object->id, $from);

	$out = load_fiche_titre($langs->trans('WebcamAvailability'), '<span class="opacitymedium small">'.$langs->trans('WebcamAvailabilityLastMonths', $months).'</span>', 'fa-chart-line');
	$out .= '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
	$out .= '<tr class="liste_titre"><th>'.$langs->trans('WebcamAvailabilityPeriod').'</th><th class="right">'.$langs->trans('WebcamAvailabilityPct').'</th>';
	$out .= '<th class="right">'.$langs->trans('WebcamSlaTargetPct').'</th>';
	$out .= '<th class="right" title="'.dol_escape_htmltag($langs->trans('WebcamAvailabilityHoursHelp')).'">'.$langs->trans('WebcamAvailabilityHours').'</th>';
	$out .= '<th class="right">'.$langs->trans('WebcamAvailabilityIncidents').'</th><th class="right">'.$langs->trans('WebcamAvailabilityBreaches').'</th>';
	$out .= '<th class="center">'.$langs->trans('WebcamAvailabilityFinal').'</th></tr>';
	foreach ($rows as $row) {
		$target = ($row->target_pct !== null && $row->target_pct !== '') ? (float) $row->target_pct : $sla['target_pct'];
		$below = webcamsAvailabilityBelow($row, $target);
		$pct = webcamsFormatPct($row->availability_pct);
		if ($below === true) {
			$pct = dolGetBadge($pct, '', !empty($row->sla_enabled) ? 'danger' : 'warning', '', '', array('attr' => array('title' => $langs->trans(!empty($row->sla_enabled) ? 'WebcamSlaNotMet' : 'WebcamAvailabilityBelow'))));
		} elseif ($below === false && !empty($row->sla_enabled)) {
			$pct = dolGetBadge($pct, '', 'success', '', '', array('attr' => array('title' => $langs->trans('WebcamSlaMet'))));
		}
		$out .= '<tr class="oddeven">';
		$out .= '<td class="nowraponall">'.dol_escape_htmltag(webcamsFormatPeriod($row->period)).'</td>';
		$out .= '<td class="right nowraponall">'.$pct.'</td>';
		$out .= '<td class="right nowraponall'.(empty($row->sla_enabled) ? ' opacitymedium' : '').'">'.webcamsFormatPct($target).'</td>';
		$out .= '<td class="right nowraponall">'.((int) $row->available_hours).' / '.((int) $row->expected_hours - (int) $row->excluded_hours);
		if ((int) $row->excluded_hours) {
			$out .= ' <span class="opacitymedium">(-'.((int) $row->excluded_hours).')</span>';
		}
		$out .= '</td>';
		$out .= '<td class="right">'.((int) $row->incidents).((float) $row->downtime_hours > 0 ? ' <span class="opacitymedium">('.price((float) $row->downtime_hours, 0, $langs, 0, 0, 1).' h)</span>' : '').'</td>';
		$out .= '<td class="right">'.((int) $row->response_breaches ? '<span class="error">'.((int) $row->response_breaches).'</span>' : '0').'</td>';
		$out .= '<td class="center">'.(!empty($row->final) ? img_picto($langs->trans('WebcamAvailabilityFinal'), 'tick') : '<span class="opacitymedium">'.$langs->trans('WebcamAvailabilityProvisional').'</span>').'</td>';
		$out .= '</tr>';
	}
	if (!$rows) {
		$out .= '<tr><td colspan="7"><span class="opacitymedium">'.$langs->trans('WebcamAvailabilityNone').'</span></td></tr>';
	}
	$out .= '</table></div>';
	return $out;
}

/**
 * Links to rttys for a webcam. The device id in rttys is the ref of the
 * webcam (WC- followed by the MAC in capitals without ':'), set by
 * luci-app-webcam as id of rtty.
 *
 * @param	string	$ref	Ref of the webcam
 * @return	array{terminal:string,luci:string}|array{}	Empty if WEBCAMS_RTTYS_URL is not set
 */
function webcamsRttysUrls($ref)
{
	$base = rtrim(getDolGlobalString('WEBCAMS_RTTYS_URL'), '/');
	if ($base === '' || !preg_match('/^WC-[0-9A-F]{12}$/', (string) $ref)) {
		return array();
	}
	return array(
		'terminal' => $base.'/api/connect/'.rawurlencode($ref),
		'luci' => $base.'/api/web/'.rawurlencode($ref).'/http/127.0.0.1%3A80%2F',
	);
}

/**
 * Public URLs of the webcam in webcam-portal
 *
 * @param	Webcam	$object		Webcam
 * @return	array{current?:string,json?:string,page?:string}
 */
function webcamsPortalUrls($object)
{
	$res = array();
	$base = rtrim(getDolGlobalString('WEBCAMS_PORTAL_URL'), '/');
	$slug = (string) $object->portal_slug;
	if ($object->url_current) {
		$res['current'] = (string) $object->url_current;
	} elseif ($base && $slug) {
		$res['current'] = $base.'/cam/'.rawurlencode($slug).'/current.jpg';
	}
	if ($base && $slug) {
		$res['json'] = $base.'/cam/'.rawurlencode($slug).'/current.json';
		$res['page'] = $base.'/cam/'.rawurlencode($slug).'/';
	}
	return $res;
}

/**
 * Rows of the card with counters of the camera, Victron and portal (second column)
 *
 * @param	Webcam	$object		Webcam
 * @return	string				HTML (rows of a table with 2 columns)
 */
function webcamsCardTechRows($object)
{
	global $langs;

	$langs->load('webcams@webcams');
	$row = function ($label, $html) {
		return '<tr><td class="titlefield">'.$label.'</td><td>'.$html.'</td></tr>'."\n";
	};
	$esc = function ($v) {
		return dol_escape_htmltag((string) $v);
	};
	$has = function ($v) {
		return $v !== null && $v !== '';
	};
	$date = function ($v) {
		return $v ? '<span class="opacitymedium small"> - '.dol_print_date($v, 'dayhour', 'tzuserrel').'</span>' : '';
	};
	$out = '';

	// Availability commitment of the rental contract (sla_*)
	$sla = webcamsSla($object);
	$def = function ($k) use ($sla, $langs) {
		return in_array($k, $sla['defaults']) ? ' <span class="opacitymedium small">('.$langs->trans('Default').')</span>' : '';
	};
	if ($sla['enabled']) {
		$html = dolGetBadge($langs->trans('Yes'), '', 'success').' ';
		$html .= $langs->trans('WebcamSlaSummary', $sla['offline_hours'], $sla['response_days'], webcamsFormatPct($sla['target_pct']));
		$html .= $def('offline_hours').$def('response_days').$def('target_pct');
		$html .= '<br><span class="opacitymedium small">'.$langs->trans('WebcamSlaHours').': '.($sla['hours'] !== '' ? $esc($sla['hours']) : $langs->trans('WebcamSla24h'));
		$html .= ' - '.$langs->trans('WebcamSlaWeekdays').': '.$esc(webcamsFormatWeekdays($sla['weekdays'])).'</span>';
	} else {
		$html = $langs->trans('No');
	}
	$out .= $row($langs->trans('WebcamSla'), $html);
	if ($has($object->availability_period)) {
		$html = webcamsFormatPct($object->availability_pct);
		if ($sla['enabled'] && $has($object->availability_pct)) {
			$html = dolGetBadge($html, '', (float) $object->availability_pct < $sla['target_pct'] ? 'danger' : 'success');
		}
		$out .= $row($langs->trans('WebcamAvailabilityLast'), $html.' <span class="opacitymedium">'.$esc(webcamsFormatPeriod($object->availability_period)).'</span>');
	}

	// Camera counters
	$shots = webcamsShotsUsed($object);
	if ($shots !== null) {
		$life = webcamsShutterLife();
		$level = webcamsShutterLevel($shots);
		$pct = round($shots * 100 / $life);
		$txt = $langs->trans('WebcamShutterUsed', $pct.'%', price($shots, 0, $langs, 0, 0));
		if ($level == 'danger') {
			$html = dolGetBadge($langs->trans('WebcamShutterEnd'), '', 'danger').' '.$txt;
		} elseif ($level == 'warning') {
			$html = dolGetBadge($langs->trans('WebcamShutterNearEnd'), '', 'warning').' '.$txt;
		} else {
			$html = $txt;
		}
		$out .= $row($langs->trans('WebcamShutterLife'), $html.' <span class="opacitymedium small">/ '.price($life, 0, $langs, 0, 0).'</span>');
	}
	if ($has($object->shutter_count)) {
		$out .= $row($langs->trans('WebcamShutterCount'), price($object->shutter_count, 0, $langs, 0, 0));
	}
	if ($has($object->shots_total)) {
		$out .= $row($langs->trans('WebcamShotsTotal'), price($object->shots_total, 0, $langs, 0, 0).$date($object->counters_date));
	}
	if ($has($object->camera_hours) || $has($object->camera_power_cycles)) {
		$out .= $row($langs->trans('WebcamCameraHours').' / '.$langs->trans('WebcamCameraPowerCycles'), ($has($object->camera_hours) ? price($object->camera_hours, 0, $langs, 0, 1) : '-').' h / '.($has($object->camera_power_cycles) ? (int) $object->camera_power_cycles : '-'));
	}

	// Victron
	if ($has($object->battery_v) || $has($object->charge_state) || $has($object->solar_wh_yesterday)) {
		$v = $has($object->battery_v) ? price($object->battery_v, 0, $langs, 0, 2).' V' : '-';
		if ($has($object->battery_v_min24)) {
			$v .= ' <span class="opacitymedium">(min 24 h '.price($object->battery_v_min24, 0, $langs, 0, 2).' V)</span>';
		}
		if ($has($object->battery_soc)) {
			$v .= ' - '.price($object->battery_soc, 0, $langs, 0, 0).'%';
		}
		$out .= $row($langs->trans('WebcamVictron').' - '.$langs->trans('WebcamBatteryV'), $v.$date($object->victron_date));
		$s = $has($object->charge_state) ? $esc($object->charge_state) : '-';
		if ($has($object->victron_error) && (int) $object->victron_error) {
			$s .= ' '.dolGetBadge($langs->trans('WebcamVictronError').' '.((int) $object->victron_error), '', 'danger');
		}
		$out .= $row($langs->trans('WebcamChargeState'), $s);
		if ($has($object->solar_wh_yesterday)) {
			$out .= $row($langs->trans('WebcamSolarWhYesterday'), price($object->solar_wh_yesterday, 0, $langs, 0, 0).' Wh');
		}
	}

	// Portal
	$urls = webcamsPortalUrls($object);
	if ($urls || $has($object->portal_slug)) {
		$links = array();
		if (!empty($urls['current'])) {
			$links[] = '<a href="'.$esc($urls['current']).'" target="_blank" rel="noopener">'.img_picto('', 'image', 'class="pictofixedwidth"').'current.jpg</a>';
		}
		if (!empty($urls['page'])) {
			$links[] = '<a href="'.$esc($urls['page']).'" target="_blank" rel="noopener">'.img_picto('', 'globe', 'class="pictofixedwidth"').$langs->trans('WebcamPortalPage').'</a>';
		}
		if (!empty($urls['json'])) {
			$links[] = '<a href="'.$esc($urls['json']).'" target="_blank" rel="noopener">JSON</a>';
		}
		$out .= $row($langs->trans('WebcamPortal').($has($object->portal_slug) ? ' <span class="opacitymedium">('.$esc($object->portal_slug).')</span>' : ''), implode(' &nbsp; ', $links));
	}
	$windy = (int) $object->windy_published;
	$out .= $row($langs->trans('WebcamWindyPublished'), ($windy ? dolGetBadge($langs->trans('Yes'), '', 'success') : $langs->trans('No')).($has($object->windy_id) ? ' <span class="opacitymedium">id '.$esc($object->windy_id).'</span>' : ''));

	return $out;
}

/**
 * Days of the commitment for display (lun, mar... or all)
 *
 * @param	string	$weekdays	ISO digits 1-7, '' = all
 * @return	string
 */
function webcamsFormatWeekdays($weekdays)
{
	global $langs;
	$langs->load('main');
	if ($weekdays === '' || $weekdays === '1234567') {
		return $langs->transnoentitiesnoconv('WebcamSlaAllDays');
	}
	// Short names of Dolibarr: ShortMonday... (ISO 1 = Monday)
	$names = array(1 => 'ShortMonday', 2 => 'ShortTuesday', 3 => 'ShortWednesday', 4 => 'ShortThursday', 5 => 'ShortFriday', 6 => 'ShortSaturday', 7 => 'ShortSunday');
	$res = array();
	foreach (str_split($weekdays) as $d) {
		if (isset($names[(int) $d])) {
			$res[] = $langs->transnoentitiesnoconv($names[(int) $d]);
		}
	}
	return implode(' ', $res);
}

/**
 * Rows of the card with the price list and the options (block "Listino e servizi")
 *
 * @param	DoliDB	$db			Database
 * @param	Webcam	$object		Webcam
 * @return	string				HTML (rows of a table with 2 columns)
 */
function webcamsCardPlanRows($db, $object)
{
	global $langs;

	$langs->load('webcams@webcams');
	$row = function ($label, $html) {
		return '<tr><td class="titlefield">'.$label.'</td><td>'.$html.'</td></tr>'."\n";
	};
	$esc = function ($v) {
		return dol_escape_htmltag((string) $v);
	};
	$day = function ($v) {
		$d = webcamsDateYmd($v);
		return $d === null ? '' : dol_print_date(dol_stringtotime($d.' 12:00:00', 0), 'day');
	};
	$po = webcamsPlanOptions($db, $object);
	$plan = $po['plan'];
	$opt = $po['options'];
	$out = '';

	$colors = array('base' => 'secondary', 'standard' => 'info', 'pro' => 'success');
	$html = dolGetBadge($langs->trans('WebcamPlan'.ucfirst($plan['level'])), '', $colors[$plan['level']]);
	if ($plan['note'] !== '') {
		$html .= ' <span class="opacitymedium">'.$esc($plan['note']).'</span>';
	}
	$out .= $row($langs->trans('WebcamPlanLevel'), $html);
	if ($plan['rental_start'] || $plan['rental_end']) {
		$out .= $row($langs->trans('WebcamRental'), ($plan['rental_start'] ? $day($object->rental_start) : '...').' - '.($plan['rental_end'] ? $day($object->rental_end) : '...'));
	}

	// Sponsor (M12)
	$features = webcamsFeatures($object);
	$s = $opt['sponsor'];
	if ($s['enabled'] || $plan['level'] == 'pro') {
		$html = $features['sponsor']['on'] ? dolGetBadge($langs->trans('WebcamFeatureOn'), '', 'success') : dolGetBadge($langs->trans('WebcamSponsorOutOfPeriod'), '', 'warning');
		$html .= ' '.$langs->trans('WebcamSponsorSlotsN', $s['slots']);
		if ($s['start'] || $s['end']) {
			$html .= ' <span class="opacitymedium">('.($s['start'] ? $day($object->sponsor_start) : '...').' - '.($s['end'] ? $day($object->sponsor_end) : '...').')</span>';
		}
		if (!$s['enabled']) {
			$html .= ' <span class="opacitymedium small">('.$langs->trans('WebcamIncludedInPro').')</span>';
		}
	} else {
		$html = $langs->trans('No');
	}
	$out .= $row($langs->trans('WebcamSponsor'), $html);

	// Bad weather certificate (O2)
	$html = $features['weather_cert']['on'] ? dolGetBadge($langs->trans('Yes'), '', 'success').($opt['weather_cert'] ? '' : ' <span class="opacitymedium small">('.$langs->trans('WebcamIncludedInPro').')</span>') : $langs->trans('No');
	$out .= $row($langs->trans('WebcamOptWeatherCert'), $html);

	// Final film (M10)
	$f = $opt['film'];
	if ($f['option'] !== '') {
		$html = dolGetBadge($langs->trans($f['option'] == 'edited' ? 'WebcamFilmEdited' : 'WebcamFilmAuto'), '', 'info');
		$html .= ' '.$langs->trans($f['format'] == 'both' ? 'WebcamFilmBoth' : ($f['format'] == 'portrait' ? 'WebcamFilmPortrait' : 'WebcamFilmLandscape')).', '.$langs->trans('WebcamFilmSeconds', $f['duration_s']);
	} else {
		$html = $langs->trans('No');
	}
	if ($f['status'] !== '') {
		$st = array('queued' => 'secondary', 'ready' => 'warning', 'delivered' => 'success');
		$html .= '<br>'.dolGetBadge($langs->trans('WebcamFilm'.ucfirst($f['status'])), '', isset($st[$f['status']]) ? $st[$f['status']] : 'secondary');
		if ($f['url'] !== '') {
			$html .= ' <a href="'.$esc($f['url']).'" target="_blank" rel="noopener">'.img_picto('', 'globe', 'class="pictofixedwidth"').$langs->trans('WebcamFilmUrl').'</a>';
		}
	}
	$out .= $row($langs->trans('WebcamFilmOption'), $html);

	// Monthly report (O3)
	$r = $opt['report'];
	if (!$features['monthly_report']['on']) {
		$html = '<span class="opacitymedium">'.$langs->trans('WebcamNotInPlan').'</span>';
	} elseif (!$r['enabled']) {
		$html = $langs->trans('WebcamReportNoEmail');
	} elseif ($r['recipients']) {
		$html = img_picto('', 'email', 'class="pictofixedwidth"').$esc(implode(', ', $r['recipients']));
		if ((string) $object->report_email === '') {
			$html .= ' <span class="opacitymedium small">('.$langs->trans('WebcamReportEmailOfThirdparty').')</span>';
		}
	} else {
		$html = dolGetBadge($langs->trans('WebcamReportNoRecipient'), '', 'warning');
	}
	$out .= $row($langs->trans('WebcamReport'), $html);

	return $out;
}

/**
 * Matrix level -> features, read only, with the column of this webcam
 *
 * @param	Webcam	$object		Webcam
 * @return	string				HTML
 */
function webcamsFeatureTable($object)
{
	global $langs;

	$langs->load('webcams@webcams');
	$level = webcamsPlanLevel($object->plan_level);
	$features = webcamsFeatures($object);
	$out = load_fiche_titre($langs->trans('WebcamFeatures'), '<span class="opacitymedium small">'.$langs->trans('WebcamFeaturesHelp').'</span>', 'fa-list-check');
	$out .= '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
	$out .= '<tr class="liste_titre"><th>'.$langs->trans('WebcamFeature').'</th>';
	foreach (array('base', 'standard', 'pro') as $l) {
		$out .= '<th class="center'.($l == $level ? ' badge-status4' : '').'">'.$langs->trans('WebcamPlan'.ucfirst($l)).'</th>';
	}
	$out .= '<th>'.$langs->trans('WebcamFeatureOption').'</th><th class="center">'.$langs->trans('WebcamFeatureThisWebcam').'</th></tr>';
	$optlabels = array('sponsor_enabled' => 'WebcamSponsorEnabled', 'opt_weather_cert' => 'WebcamOptWeatherCert', 'film_option' => 'WebcamFilmOption');
	foreach (webcamsFeatureMatrix() as $id => $f) {
		$out .= '<tr class="oddeven"><td>'.$langs->trans('WebcamFeature_'.$id).' <span class="opacitymedium small">'.$id.'</span></td>';
		foreach (array('base', 'standard', 'pro') as $l) {
			$out .= '<td class="center">'.(in_array($l, $f['levels'], true) ? img_picto('', 'tick') : '').'</td>';
		}
		$out .= '<td class="small">'.($f['option'] ? $langs->trans($optlabels[$f['option']]) : '').'</td>';
		$st = $features[$id];
		if ($st['on']) {
			$cell = dolGetBadge($langs->trans('WebcamFeatureOn'), '', 'success').($st['by'] == 'option' ? ' <span class="opacitymedium small">('.$langs->trans('WebcamFeatureByOption').')</span>' : '');
		} elseif ($st['by'] == 'period') {
			$cell = dolGetBadge($langs->trans('WebcamSponsorOutOfPeriod'), '', 'warning');
		} else {
			$cell = '<span class="opacitymedium">-</span>';
		}
		$out .= '<td class="center nowraponall">'.$cell.'</td></tr>';
	}
	$out .= '</table></div>';
	return $out;
}

/**
 * Head of the list of documents of the services (type and period from
 * ecm_files.keywords = webcams:<doc_type>:<period>)
 *
 * @param	DoliDB	$db			Database
 * @param	Webcam	$object		Webcam
 * @param	string	$reldir		Directory relative to DOL_DATA_ROOT (filepath of ecm_files)
 * @param	string	$doctype	Filter ('' = all)
 * @return	string				HTML
 */
function webcamsServiceDocumentsTable($db, $object, $reldir, $doctype = '')
{
	global $langs;

	$langs->load('webcams@webcams');
	$types = array('monthly_report', 'weather_certificate', 'weather_register', 'other');
	$sql = "SELECT rowid, filename, filepath, keywords, description, date_c, share FROM ".$db->prefix()."ecm_files";
	$sql .= " WHERE filepath = '".$db->escape($reldir)."' AND keywords LIKE 'webcams:%'";
	if ($doctype !== '' && in_array($doctype, $types, true)) {
		$sql .= " AND keywords LIKE '".$db->escape('webcams:'.$doctype)."%'";
	}
	$sql .= " AND entity IN (".getEntity('ecmfiles').")";
	$sql .= " ORDER BY keywords DESC, filename";
	$resql = $db->query($sql);

	$filter = '<form method="GET" action="'.$_SERVER['PHP_SELF'].'" class="inline-block">';
	$filter .= '<input type="hidden" name="id" value="'.((int) $object->id).'">';
	$filter .= '<select name="doctype" class="flat" onchange="this.form.submit()">';
	$filter .= '<option value="">'.$langs->trans('WebcamDocTypeAll').'</option>';
	foreach ($types as $t) {
		$filter .= '<option value="'.$t.'"'.($doctype === $t ? ' selected' : '').'>'.$langs->trans('WebcamDocType_'.$t).'</option>';
	}
	$filter .= '</select> <noscript><input type="submit" class="button small" value="'.$langs->trans('Refresh').'"></noscript></form>';

	$out = load_fiche_titre($langs->trans('WebcamServiceDocuments'), $filter, 'fa-file-pdf');
	$out .= '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
	$out .= '<tr class="liste_titre"><th>'.$langs->trans('WebcamDocType').'</th><th>'.$langs->trans('WebcamAvailabilityPeriod').'</th><th>'.$langs->trans('File').'</th><th>'.$langs->trans('Description').'</th><th class="center">'.$langs->trans('DateCreation').'</th></tr>';
	$n = 0;
	while ($resql && ($obj = $db->fetch_object($resql))) {
		$k = explode(':', (string) $obj->keywords, 3);
		$t = isset($k[1]) ? $k[1] : '';
		$p = isset($k[2]) ? $k[2] : '';
		$url = DOL_URL_ROOT.'/document.php?modulepart=webcams&file='.urlencode('webcam/'.dol_sanitizeFileName($object->ref).'/'.$obj->filename);
		$out .= '<tr class="oddeven"><td>'.dol_escape_htmltag(in_array($t, $types, true) ? $langs->trans('WebcamDocType_'.$t) : $t).'</td>';
		$out .= '<td class="nowraponall">'.dol_escape_htmltag($p !== '' ? webcamsFormatDocPeriod($p) : '').'</td>';
		$out .= '<td><a href="'.dol_escape_htmltag($url).'" target="_blank">'.img_mime($obj->filename, '', 'pictofixedwidth').dol_escape_htmltag($obj->filename).'</a></td>';
		$out .= '<td>'.dol_escape_htmltag((string) $obj->description).'</td>';
		$out .= '<td class="center nowraponall">'.dol_print_date($db->jdate($obj->date_c), 'dayhour', 'tzuserrel').'</td></tr>';
		$n++;
	}
	if (!$n) {
		$out .= '<tr><td colspan="5"><span class="opacitymedium">'.$langs->trans('WebcamServiceDocumentsNone').'</span></td></tr>';
	}
	$out .= '</table></div>';
	return $out;
}
