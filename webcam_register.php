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
 *  \file       webcam_register.php
 *  \ingroup    webcams
 *  \brief      Tab "Registro" of the webcam: daily fingerprints deposited by
 *              webcam-portal (O2, module 1.4). Read only: the rows never change.
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
dol_include_once('/webcams/class/webcam.class.php');
dol_include_once('/webcams/lib/webcams_webcam.lib.php');

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array('webcams@webcams', 'other'));

$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$month = GETPOST('month', 'alpha');
if (!preg_match('/^(19|20)\d{2}-(0[1-9]|1[0-2])$/', (string) $month)) {
	$month = '';
}
$limit = GETPOSTINT('limit') ? GETPOSTINT('limit') : 62;
$page = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
if (empty($page) || $page < 0) {
	$page = 0;
}

$object = new Webcam($db);
$extrafields = new ExtraFields($db);
$hookmanager->initHooks(array($object->element.'register', 'globalcard'));
$extrafields->fetch_name_optionals_label($object->table_element);
$action = '';
include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php';

if (!isModEnabled('webcams')) {
	accessforbidden();
}
if (!$user->hasRight('webcams', 'webcam', 'read') || empty($object->id)) {
	accessforbidden();
}


/*
 * View
 */

$form = new Form($db);
llxHeader('', $langs->trans('Webcam').' - '.$langs->trans('WebcamRegister'), '', '', 0, 0, '', '', '', 'mod-webcams page-card_register');

$head = webcamPrepareHead($object);
print dol_get_fiche_head($head, 'register', $langs->trans('Webcam'), -1, $object->picto, 0, '', '', 0, '', 1);
$linkback = '<a href="'.dol_buildpath('/webcams/webcam_list.php', 1).'?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';
dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', '<div class="refidno"></div>');
print '<div class="underbanner clearboth"></div>';
print '<span class="opacitymedium">'.$langs->trans('WebcamRegisterHelp').'</span><br><br>';
print dol_get_fiche_end();

$from = $month !== '' ? $month.'-01' : '';
$to = $month !== '' ? $month.'-31' : '';
$rows = webcamsRegisterDays($db, $object->id, $from, $to, 'DESC', $limit + 1, $limit * $page);
$num = count($rows);

$param = '&id='.((int) $object->id).($month !== '' ? '&month='.urlencode($month) : '');
$filter = '<form method="GET" action="'.$_SERVER['PHP_SELF'].'" class="inline-block">';
$filter .= '<input type="hidden" name="id" value="'.((int) $object->id).'">';
$filter .= '<input type="month" name="month" class="flat" value="'.dol_escape_htmltag($month).'" placeholder="AAAA-MM"> ';
$filter .= '<input type="submit" class="button small" value="'.dol_escape_htmltag($langs->trans('Filter')).'">';
$filter .= '</form>';

print_barre_liste($langs->trans('WebcamRegister'), $page, $_SERVER['PHP_SELF'], $param, '', '', $filter, $num, '', 'fa-fingerprint', 0, '', '', $limit, 0, 0, 1);

print '<div class="div-table-responsive"><table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans('Day').'</th>';
print '<th class="center">'.$langs->trans('WebcamRegisterClassification').'</th>';
print '<th>'.$langs->trans('WebcamRegisterReasons').'</th>';
print '<th class="right" title="'.dol_escape_htmltag($langs->trans('WebcamRegisterHoursHelp')).'">'.$langs->trans('WebcamRegisterHours').'</th>';
print '<th class="right">'.$langs->trans('WebcamRegisterPhotos').'</th>';
print '<th>'.$langs->trans('WebcamRegisterDayHash').'</th>';
print '<th>'.$langs->trans('WebcamRegisterChain').'</th>';
print '<th class="center">'.$langs->trans('WebcamRegisterRegisteredAt').'</th>';
print '</tr>';

$colors = array('ok' => 'success', 'bad_weather' => 'warning', 'not_certifiable' => 'secondary', 'no_data' => 'secondary');
$i = 0;
foreach ($rows as $k => $r) {
	if ($i >= $limit) {
		break;
	}
	$i++;
	// Chain: prev_day_hash equals the day_hash of the day before (if shown)
	$older = isset($rows[$k + 1]) ? $rows[$k + 1] : null;
	$chain = '';
	if ($r->prev_day_hash !== null && $older && substr((string) $older->day, 0, 10) === dol_print_date(dol_time_plus_duree(dol_stringtotime(substr((string) $r->day, 0, 10).' 12:00:00', 0), -1, 'd'), '%Y-%m-%d')) {
		$chain = $r->prev_day_hash === $older->day_hash ? img_picto($langs->trans('WebcamRegisterChainOk'), 'tick') : dolGetBadge($langs->trans('WebcamRegisterChainBroken'), '', 'danger');
	} elseif ($r->prev_day_hash !== null) {
		$chain = '<span class="opacitymedium" title="'.dol_escape_htmltag($r->prev_day_hash).'">'.dol_escape_htmltag(substr((string) $r->prev_day_hash, 0, 16)).'</span>';
	}
	$cls = (string) $r->classification;
	print '<tr class="oddeven">';
	print '<td class="nowraponall">'.dol_print_date($db->jdate($r->day), 'daytext').' <span class="opacitymedium small">'.dol_escape_htmltag($r->timezone).'</span></td>';
	print '<td class="center">'.($cls !== '' ? dolGetBadge($langs->trans('WebcamRegisterClass_'.$cls) !== 'WebcamRegisterClass_'.$cls ? $langs->trans('WebcamRegisterClass_'.$cls) : $cls, '', isset($colors[$cls]) ? $colors[$cls] : 'secondary') : '').'</td>';
	print '<td>'.dol_escape_htmltag((string) $r->reasons).'</td>';
	print '<td class="right nowraponall">'.($r->wet_hours === null ? '-' : (int) $r->wet_hours).' / '.($r->frost_hours === null ? '-' : (int) $r->frost_hours).' / '.($r->fog_hours === null ? '-' : (int) $r->fog_hours).'</td>';
	print '<td class="right nowraponall">'.((int) $r->photos).((int) $r->photos_unsynced ? ' <span class="opacitymedium" title="'.dol_escape_htmltag($langs->trans('WebcamRegisterUnsynced')).'">(+'.((int) $r->photos_unsynced).')</span>' : '').'</td>';
	print '<td class="tdoverflowmax200"><span class="small" style="font-family: monospace" title="'.dol_escape_htmltag($r->hash_scheme.' '.$r->day_hash).'">'.dol_escape_htmltag($r->day_hash).'</span></td>';
	print '<td class="center">'.$chain.'</td>';
	print '<td class="center nowraponall">'.dol_print_date($db->jdate($r->date_creation), 'dayhour', 'tzuserrel').'</td>';
	print '</tr>';
}
if (!$i) {
	print '<tr><td colspan="8"><span class="opacitymedium">'.$langs->trans('WebcamRegisterNone').'</span></td></tr>';
}
print '</table></div>';

llxFooter();
$db->close();
