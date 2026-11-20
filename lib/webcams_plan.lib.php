<?php
/* Copyright (C) 2026 ViewLapse
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
 * \file    webcams/lib/webcams_plan.lib.php
 * \ingroup webcams
 * \brief   Listino, opzioni, matrice livello -> funzioni, archivi dopo il
 *          noleggio e registro delle impronte (modulo 1.4, API Dolibarr 2.2)
 */

/**
 * Plan level: base, standard or pro (empty or unknown = base)
 *
 * @param	mixed	$v		Value
 * @return	string
 */
function webcamsPlanLevel($v)
{
	$v = strtolower(trim((string) $v));
	return in_array($v, array('base', 'standard', 'pro'), true) ? $v : 'base';
}

/**
 * Matrix level -> features of the contract (api-dolibarr-v2.md 2.2, the only
 * source). The portal applies it; Dolibarr shows it as help on the card.
 *
 * @return	array<string,array{levels:string[],option:string}>	Feature id => levels where it is on, option that turns it on at any level
 */
function webcamsFeatureMatrix()
{
	$all = array('base', 'standard', 'pro');
	$sp = array('standard', 'pro');
	$p = array('pro');
	return array(
		'archive' => array('levels' => $all, 'option' => ''),
		'availability' => array('levels' => $all, 'option' => ''),
		'overlay' => array('levels' => $sp, 'option' => ''),
		'same_moment' => array('levels' => $sp, 'option' => ''),
		'timelapse' => array('levels' => $sp, 'option' => ''),
		'photo_of_day' => array('levels' => $sp, 'option' => ''),
		'monthly_report' => array('levels' => $sp, 'option' => ''),
		'telegram' => array('levels' => $sp, 'option' => ''),
		'timelapse_portrait' => array('levels' => $p, 'option' => ''),
		'timelapse_custom' => array('levels' => $p, 'option' => ''),
		'stats' => array('levels' => $p, 'option' => ''),
		'sponsor' => array('levels' => $p, 'option' => 'sponsor_enabled'),
		'weather_cert' => array('levels' => $p, 'option' => 'opt_weather_cert'),
		'final_film' => array('levels' => array(), 'option' => 'film_option'),
	);
}

/**
 * Date of a 'date' field of the webcam as AAAA-MM-GG
 *
 * @param	int|string|null		$ts		Timestamp (as loaded by fetch) or AAAA-MM-GG
 * @return	string|null
 */
function webcamsDateYmd($ts)
{
	if ($ts === null || $ts === '') {
		return null;
	}
	if (is_string($ts) && preg_match('/^\d{4}-\d{2}-\d{2}/', $ts)) {
		return substr($ts, 0, 10);
	}
	return dol_print_date((int) $ts, '%Y-%m-%d', 'tzserver');
}

/**
 * Today as AAAA-MM-GG (time zone of the server, as the 'date' fields)
 *
 * @return	string
 */
function webcamsToday()
{
	return dol_print_date(dol_now(), '%Y-%m-%d', 'tzserver');
}

/**
 * Features of a webcam from its level and options (as the portal computes
 * them with Features::forWebcam(); here only for the help of the card)
 *
 * @param	object		$obj	Webcam
 * @param	string		$today	AAAA-MM-GG ('' = today)
 * @return	array<string,array{on:bool,by:string}>	Feature id => on, reason (level, option, period, '')
 */
function webcamsFeatures($obj, $today = '')
{
	$today = $today !== '' ? $today : webcamsToday();
	$level = webcamsPlanLevel(isset($obj->plan_level) ? $obj->plan_level : '');
	$res = array();
	foreach (webcamsFeatureMatrix() as $id => $f) {
		$on = in_array($level, $f['levels'], true);
		$by = $on ? 'level' : '';
		if (!$on && $f['option'] !== '') {
			$opt = isset($obj->{$f['option']}) ? $obj->{$f['option']} : null;
			if ($f['option'] == 'film_option' ? in_array($opt, array('auto', 'edited'), true) : !empty($opt)) {
				$on = true;
				$by = 'option';
			}
		}
		if ($id == 'sponsor' && $on) {
			$start = webcamsDateYmd(isset($obj->sponsor_start) ? $obj->sponsor_start : null);
			$end = webcamsDateYmd(isset($obj->sponsor_end) ? $obj->sponsor_end : null);
			if (($start !== null && strcmp($today, $start) < 0) || ($end !== null && strcmp($today, $end) > 0)) {
				$on = false;
				$by = 'period';
			}
		}
		$res[$id] = array('on' => $on, 'by' => $by);
	}
	return $res;
}

/**
 * Recipients of the monthly report: report_email split at the commas and
 * checked with isValidEmail(), otherwise the email of the third party.
 *
 * @param	DoliDB	$db		Database
 * @param	object	$obj	Webcam
 * @return	string[]
 */
function webcamsReportRecipients($db, $obj)
{
	$res = array();
	foreach (preg_split('/[,;\s]+/', (string) (isset($obj->report_email) ? $obj->report_email : '')) as $e) {
		if ($e !== '' && isValidEmail($e) && !in_array($e, $res, true)) {
			$res[] = $e;
		}
	}
	if (!$res && !empty($obj->fk_soc) && $obj->fk_soc > 0) {
		$resql = $db->query("SELECT email FROM ".$db->prefix()."societe WHERE rowid = ".((int) $obj->fk_soc));
		if ($resql && ($o = $db->fetch_object($resql))) {
			foreach (preg_split('/[,;\s]+/', (string) $o->email) as $e) {
				if ($e !== '' && isValidEmail($e) && !in_array($e, $res, true)) {
					$res[] = $e;
				}
			}
		}
	}
	return $res;
}

/**
 * Plan and options of the webcam, as plan and options of GET webcams/{id}/portal
 *
 * @param	DoliDB	$db		Database
 * @param	object	$obj	Webcam
 * @return	array{plan:array<string,mixed>,options:array<string,mixed>}
 */
function webcamsPlanOptions($db, $obj)
{
	$str = function ($k) use ($obj) {
		return (isset($obj->$k) && $obj->$k !== null) ? (string) $obj->$k : '';
	};
	$slots = (isset($obj->sponsor_slots) && (int) $obj->sponsor_slots > 0) ? (int) $obj->sponsor_slots : 2;
	$film = in_array($str('film_option'), array('auto', 'edited'), true) ? $str('film_option') : '';
	$format = in_array($str('film_format'), array('landscape', 'portrait', 'both'), true) ? $str('film_format') : 'landscape';
	$duration = (isset($obj->film_duration) && (int) $obj->film_duration > 0) ? (int) $obj->film_duration : 60;
	$report = !isset($obj->report_enabled) || $obj->report_enabled === null || $obj->report_enabled === '' || !empty($obj->report_enabled);
	return array(
		'plan' => array(
			'level' => webcamsPlanLevel($str('plan_level')),
			'note' => $str('plan_note'),
			'rental_start' => webcamsDateYmd(isset($obj->rental_start) ? $obj->rental_start : null),
			'rental_end' => webcamsDateYmd(isset($obj->rental_end) ? $obj->rental_end : null),
		),
		'options' => array(
			'sponsor' => array(
				'enabled' => !empty($obj->sponsor_enabled),
				'slots' => $slots,
				'start' => webcamsDateYmd(isset($obj->sponsor_start) ? $obj->sponsor_start : null),
				'end' => webcamsDateYmd(isset($obj->sponsor_end) ? $obj->sponsor_end : null),
			),
			'weather_cert' => !empty($obj->opt_weather_cert),
			'film' => array('option' => $film, 'format' => $format, 'duration_s' => $duration, 'status' => $str('film_status'), 'url' => $str('film_url')),
			'report' => array('enabled' => $report, 'recipients' => webcamsReportRecipients($db, $obj)),
		),
	);
}

/**
 * Archives after the rental of a webcam (llx_webcams_archive)
 *
 * @param	DoliDB	$db			Database
 * @param	int		$webcamid	Id of webcam
 * @param	bool	$activeonly	Only rows with archive_until >= today
 * @return	array<int,object>	Rows (with socname), oldest rental_end first
 */
function webcamsArchives($db, $webcamid, $activeonly = false)
{
	$sql = "SELECT a.rowid, a.fk_soc, a.rental_start, a.rental_end, a.archive_until, a.note, a.fk_user_creat, a.date_creation, s.nom as socname";
	$sql .= " FROM ".$db->prefix()."webcams_archive as a";
	$sql .= " LEFT JOIN ".$db->prefix()."societe as s ON s.rowid = a.fk_soc";
	$sql .= " WHERE a.fk_webcam = ".((int) $webcamid)." AND a.entity IN (".getEntity('webcam').")";
	if ($activeonly) {
		$sql .= " AND a.archive_until >= '".$db->escape(webcamsToday())."'";
	}
	$sql .= " ORDER BY a.rental_end ASC, a.rowid ASC";
	$rows = array();
	$resql = $db->query($sql);
	while ($resql && ($obj = $db->fetch_object($resql))) {
		$rows[] = $obj;
	}
	return $rows;
}

/**
 * Days of the register of the fingerprints of a webcam (llx_webcams_register)
 *
 * @param	DoliDB	$db			Database
 * @param	int		$webcamid	Id of webcam
 * @param	string	$from		First day AAAA-MM-GG ('' = no limit)
 * @param	string	$to			Last day AAAA-MM-GG ('' = no limit)
 * @param	string	$order		ASC or DESC
 * @param	int		$limit		Max rows (0 = all)
 * @param	int		$offset		Offset
 * @return	array<int,object>
 */
function webcamsRegisterDays($db, $webcamid, $from = '', $to = '', $order = 'ASC', $limit = 0, $offset = 0)
{
	$sql = "SELECT rowid, fk_soc, day, timezone, hash_scheme, day_hash, prev_day_hash, photos, photos_unsynced, classification, reasons,";
	$sql .= " wet_hours, frost_hours, fog_hours, date_creation";
	$sql .= " FROM ".$db->prefix()."webcams_register";
	$sql .= " WHERE fk_webcam = ".((int) $webcamid)." AND entity IN (".getEntity('webcam').")";
	if ($from !== '') {
		$sql .= " AND day >= '".$db->escape($from)."'";
	}
	if ($to !== '') {
		$sql .= " AND day <= '".$db->escape($to)."'";
	}
	$sql .= " ORDER BY day ".($order == 'DESC' ? 'DESC' : 'ASC');
	if ($limit > 0) {
		$sql .= $db->plimit($limit, $offset);
	}
	$rows = array();
	$resql = $db->query($sql);
	while ($resql && ($obj = $db->fetch_object($resql))) {
		$rows[] = $obj;
	}
	return $rows;
}

/**
 * Email templates of the module (type webcams_webcam), id => label
 *
 * @param	DoliDB	$db		Database
 * @return	array<int,string>
 */
function webcamsEmailTemplates($db)
{
	$res = array();
	$sql = "SELECT rowid, label, lang FROM ".$db->prefix()."c_email_templates";
	$sql .= " WHERE type_template = 'webcams_webcam' AND active = 1 AND entity IN (".getEntity('c_email_templates').")";
	$sql .= " ORDER BY position, label";
	$resql = $db->query($sql);
	while ($resql && ($obj = $db->fetch_object($resql))) {
		$res[(int) $obj->rowid] = $obj->label.($obj->lang ? ' ('.$obj->lang.')' : '');
	}
	return $res;
}

/**
 * Email template to use for a kind of document: the one chosen in the setup
 * (WEBCAMS_EMAIL_TEMPLATE_REPORT / _WEATHER), otherwise the one created by the
 * module at activation.
 *
 * @param	DoliDB	$db		Database
 * @param	string	$kind	report or weather
 * @return	object|null		Row of llx_c_email_templates (topic, content...)
 */
function webcamsEmailTemplate($db, $kind)
{
	$const = $kind == 'weather' ? 'WEBCAMS_EMAIL_TEMPLATE_WEATHER' : 'WEBCAMS_EMAIL_TEMPLATE_REPORT';
	$sql = "SELECT rowid, label, lang, topic, content, email_from, email_tocc, email_tobcc FROM ".$db->prefix()."c_email_templates";
	$sql .= " WHERE type_template = 'webcams_webcam' AND active = 1 AND entity IN (".getEntity('c_email_templates').")";
	$id = getDolGlobalInt($const);
	if ($id > 0) {
		$resql = $db->query($sql." AND rowid = ".((int) $id));
		if ($resql && ($obj = $db->fetch_object($resql))) {
			return $obj;
		}
		return null; // The template chosen was deleted or disabled
	}
	$resql = $db->query($sql." AND module = 'webcams' AND label = '".$db->escape(webcamsEmailTemplateDefaults()[$kind == 'weather' ? 'weather' : 'report']['label'])."' ORDER BY rowid");
	if ($resql && ($obj = $db->fetch_object($resql))) {
		return $obj;
	}
	return null;
}

/**
 * Templates created at activation of the module (editable in
 * Setup - Emails - Email templates, type "Webcam")
 *
 * @return	array<string,array{label:string,topic:string,content:string}>
 */
function webcamsEmailTemplateDefaults()
{
	return array(
		'report' => array(
			'label' => 'Rapporto mensile webcam',
			'topic' => 'Rapporto mensile della webcam __WEBCAM_LABEL__ - __WEBCAM_PERIOD__',
			'content' => "Buongiorno,<br><br>in allegato il rapporto mensile della webcam <b>__WEBCAM_LABEL__</b> (__WEBCAM_REF__) per __WEBCAM_PERIOD__.<br><br>Le immagini e l'archivio sono qui: __WEBCAM_LINK__<br><br>Cordiali saluti,<br>__MYCOMPANY_NAME__",
		),
		'weather' => array(
			'label' => 'Certificato giornate di maltempo',
			'topic' => 'Certificato delle giornate di maltempo - __WEBCAM_LABEL__ - __WEBCAM_PERIOD__',
			'content' => "Buongiorno,<br><br>in allegato il certificato delle giornate di maltempo registrate dalla webcam <b>__WEBCAM_LABEL__</b> (__WEBCAM_REF__) per __WEBCAM_PERIOD__.<br><br>Il certificato riporta il registro delle impronte delle foto, depositato ogni giorno anche nel nostro gestionale, e i limiti della classificazione meteo.<br><br>__WEBCAM_LINK__<br><br>Cordiali saluti,<br>__MYCOMPANY_NAME__",
		),
	);
}

/**
 * Period of a document for display: novembre 2026 or 01/11/2026 - 15/11/2026
 *
 * @param	string		$period		AAAA-MM or AAAA-MM-GG_AAAA-MM-GG
 * @param	Translate	$outlangs	Language
 * @return	string
 */
function webcamsFormatDocPeriod($period, $outlangs = null)
{
	if (preg_match('/^(\d{4})-(\d{2})-(\d{2})_(\d{4})-(\d{2})-(\d{2})$/', (string) $period, $m)) {
		return $m[3].'/'.$m[2].'/'.$m[1].' - '.$m[6].'/'.$m[5].'/'.$m[4];
	}
	return webcamsFormatPeriod((string) $period, $outlangs);
}
