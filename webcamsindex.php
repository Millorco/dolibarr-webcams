<?php
/* Copyright (C) 2001-2005  Rodolphe Quiedeville    <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2015  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012  Regis Houssin           <regis.houssin@inodbox.com>
 * Copyright (C) 2015       Jean-François Ferry     <jfefe@aternatik.fr>
 * Copyright (C) 2024       Frédéric France         <frederic.france@free.fr>
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
 *	\file       htdocs/modulebuilder/template/webcamsindex.php
 *	\ingroup    webcams
 *	\brief      Home page of webcams top menu
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include str_replace("..", "", $_SERVER["CONTEXT_DOCUMENT_ROOT"])."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}
/**
 * The main.inc.php has been included so the following variable are now defined:
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */
require_once __DIR__.'/class/webcam.class.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array("webcams@webcams", "companies"));

if (!isModEnabled('webcams') || !$user->hasRight('webcams', 'webcam', 'read')) {
	accessforbidden();
}

$now = dol_now();
$offlinehours = getDolGlobalInt('WEBCAMS_OFFLINE_HOURS', 13);
$filter = GETPOST('filter', 'aZ09');

// Latest versions from latest.json of luci-app-webcam (cached 12 h)
$latestapp = '';
$latestfw = '';
$latesturl = getDolGlobalString('WEBCAMS_LATEST_URL', 'https://raw.githubusercontent.com/Millorco/luci-app-webcam/main/latest.json');
if ($latesturl) {
	$cache = DOL_DATA_ROOT.'/webcams/temp'.'/latest.json';
	if (!is_file($cache) || filemtime($cache) < $now - 43200) {
		require_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';
		$res = getURLContent($latesturl, 'GET', '', 1, array(), array('https'), 2, -1, 4, 4);
		dol_mkdir(DOL_DATA_ROOT.'/webcams/temp');
		if (!empty($res['content']) && is_array(json_decode($res['content'], true))) {
			file_put_contents($cache, $res['content']);
		} elseif (is_file($cache)) {
			touch($cache);
		}
	}
	$latest = is_file($cache) ? json_decode((string) file_get_contents($cache), true) : null;
	$latestapp = (string) ($latest['app'] ?? '');
	$latestfw = (string) ($latest['fw'] ?? '');
}
$isold = function ($have, $latest) {
	return $have && $latest && version_compare(ltrim($have, 'vV'), ltrim($latest, 'vV'), '<');
};

$sql = "SELECT t.rowid, t.ref, t.label, t.hostname, t.status, t.fk_soc, t.last_contact, t.last_capture, t.app_version, t.board_fw,";
$sql .= " t.camera, t.vpn_ip, t.maintenance, s.nom as socname";
$sql .= " FROM ".$db->prefix()."webcams_webcam as t";
$sql .= " LEFT JOIN ".$db->prefix()."societe as s ON s.rowid = t.fk_soc";
$sql .= " WHERE t.entity IN (".getEntity('webcam').")";
$sql .= " ORDER BY s.nom, t.label, t.hostname";
$resql = $db->query($sql);
if (!$resql) {
	dol_print_error($db);
	exit;
}

$rows = array();
$stats = array('total' => 0, 'ok' => 0, 'offline' => 0, 'outdated' => 0, 'nocustomer' => 0);
while ($obj = $db->fetch_object($resql)) {
	if ((int) $obj->status === Webcam::STATUS_CANCELED) {
		continue;
	}
	$lc = $obj->last_contact ? $db->jdate($obj->last_contact) : 0;
	$obj->offline = (!$lc || $lc < $now - $offlinehours * 3600);
	$obj->outdated = $isold($obj->app_version, $latestapp) || $isold($obj->board_fw, $latestfw);
	$stats['total']++;
	$stats[$obj->offline ? 'offline' : 'ok']++;
	if ($obj->outdated) {
		$stats['outdated']++;
	}
	if (empty($obj->fk_soc)) {
		$stats['nocustomer']++;
	}
	$keep = true;
	if ($filter === 'offline') {
		$keep = $obj->offline;
	} elseif ($filter === 'outdated') {
		$keep = $obj->outdated;
	} elseif ($filter === 'nocustomer') {
		$keep = empty($obj->fk_soc);
	}
	if ($keep) {
		$rows[] = $obj;
	}
}

$customers = 0;
$resql = $db->query("SELECT COUNT(DISTINCT t.fk_soc) as n FROM ".$db->prefix()."webcams_webcam as t WHERE t.fk_soc > 0 AND t.status <> ".Webcam::STATUS_CANCELED." AND t.entity IN (".getEntity('webcam').")");
if ($resql && ($obj = $db->fetch_object($resql))) {
	$customers = (int) $obj->n;
}

llxHeader("", $langs->trans("WebcamsArea"), '', '', 0, 0, '', '', '', 'mod-webcams page-index');

print load_fiche_titre($langs->trans("WebcamsArea"), '<a class="butAction" href="'.dol_buildpath('/webcams/webcam_list.php', 1).'">'.$langs->trans("List_Webcam").'</a>', 'fa-video');

$tile = function ($label, $value, $href, $color) {
	return '<a class="webcams-tile" href="'.dol_escape_htmltag($href).'"><span class="webcams-tile-n" style="color:'.$color.'">'.$value.'</span><span class="opacitymedium">'.dol_escape_htmltag($label).'</span></a>';
};
$base = dol_buildpath('/webcams/webcamsindex.php', 1);
print '<style>.webcams-tiles{display:flex;flex-wrap:wrap;gap:12px;margin:10px 0 20px}.webcams-tile{display:flex;flex-direction:column;min-width:150px;padding:12px 16px;border:1px solid var(--colortopbordertitle1,#ddd);border-radius:8px;text-decoration:none!important}.webcams-tile-n{font-size:2em;font-weight:bold;line-height:1.2}</style>';
print '<div class="webcams-tiles">';
print $tile($langs->trans('WebcamsInService'), $stats['total'], $base, 'inherit');
print $tile($langs->trans('WebcamStatusInService').' ✓', $stats['ok'], $base, '#1a7f37');
print $tile($langs->trans('WebcamsNotResponding'), $stats['offline'], $base.'?filter=offline', $stats['offline'] ? '#b42318' : 'inherit');
print $tile($langs->trans('WebcamsOutdated'), $stats['outdated'], $base.'?filter=outdated', $stats['outdated'] ? '#b54708' : 'inherit');
print $tile($langs->trans('WebcamsWithoutCustomer'), $stats['nocustomer'], $base.'?filter=nocustomer', $stats['nocustomer'] ? '#b54708' : 'inherit');
print $tile($langs->trans('Customers'), $customers, DOL_URL_ROOT.'/societe/list.php?type=c', 'inherit');
print '</div>';

print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('Status').'</th><th>'.$langs->trans('Webcam').'</th><th>'.$langs->trans('Customer').'</th>';
print '<th>'.$langs->trans('WebcamLastContact').'</th><th>'.$langs->trans('WebcamLastCapture').'</th><th>'.$langs->trans('WebcamAppVersion').'</th>';
print '<th>'.$langs->trans('WebcamBoardFw').'</th><th>'.$langs->trans('WebcamCamera').'</th><th>'.$langs->trans('WebcamVpnIp').'</th></tr>';

$webcam = new Webcam($db);
$soc = new Societe($db);
foreach ($rows as $obj) {
	$webcam->id = $obj->rowid;
	$webcam->ref = $obj->ref;
	$webcam->label = $obj->label ? $obj->label : $obj->hostname;
	$webcam->status = $obj->status;
	print '<tr class="oddeven">';
	if ($obj->offline) {
		print '<td>'.dolGetBadge($langs->trans('WebcamsNotResponding'), '', 'danger').'</td>';
	} elseif ($obj->maintenance) {
		print '<td>'.dolGetBadge($langs->trans('WebcamMaintenance'), '', 'warning').'</td>';
	} else {
		print '<td>'.$webcam->getLibStatut(5).'</td>';
	}
	print '<td class="tdoverflowmax250">'.$webcam->getNomUrl(1).' '.dol_escape_htmltag($webcam->label).'</td>';
	if ($obj->fk_soc) {
		$soc->id = $obj->fk_soc;
		$soc->name = $obj->socname;
		print '<td class="tdoverflowmax200">'.$soc->getNomUrl(1).'</td>';
	} else {
		print '<td class="opacitymedium">-</td>';
	}
	foreach (array('last_contact', 'last_capture') as $k) {
		$d = $obj->$k ? $db->jdate($obj->$k) : 0;
		print '<td class="nowraponall" title="'.($d ? dol_print_date($d, 'dayhour', 'tzuserrel') : '').'">'.($d ? dol_print_date($d, 'dayhour', 'tzuserrel') : '-').'</td>';
	}
	foreach (array(array($obj->app_version, $latestapp), array($obj->board_fw, $latestfw)) as $v) {
		print '<td>'.($isold($v[0], $v[1]) ? '<span class="warning" title="'.dol_escape_htmltag($v[1]).'">'.dol_escape_htmltag($v[0]).' ↑</span>' : dol_escape_htmltag((string) $v[0])).'</td>';
	}
	print '<td class="tdoverflowmax150">'.dol_escape_htmltag((string) $obj->camera).'</td>';
	print '<td>'.($obj->vpn_ip ? '<a href="http://'.dol_escape_htmltag($obj->vpn_ip).'/" target="_blank" rel="noopener">'.dol_escape_htmltag($obj->vpn_ip).'</a>' : '').'</td>';
	print '</tr>';
}
if (!$rows) {
	print '<tr><td colspan="9"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}
print '</table></div>';

llxFooter();
$db->close();
