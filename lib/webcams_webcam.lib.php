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
	$life = webcamsShutterLife();
	$pct = getDolGlobalInt('WEBCAMS_SHUTTER_WARN_PCT', 90);
	if ($shots >= $life) {
		return 'danger';
	}
	if ($shots >= $life * $pct / 100) {
		return 'warning';
	}
	return '';
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
