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
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once __DIR__.'/class/webcam.class.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array("webcams@webcams", "companies"));

$socid = GETPOSTINT('socid');
if (!empty($user->socid)) {
	$socid = $user->socid;
}
if (!isModEnabled('webcams') || !$user->hasRight('webcams', 'webcam', 'read')) {
	accessforbidden();
}
$result = restrictedArea($user, 'societe', $socid, '&societe');

$object = new Societe($db);
if ($object->fetch($socid) <= 0) {
	accessforbidden();
}

llxHeader('', $object->name.' - '.$langs->trans('Webcams'), '', '', 0, 0, '', '', '', 'mod-webcams page-thirdparty');
print dol_get_fiche_head(societe_prepare_head($object), 'webcams', $langs->trans("ThirdParty"), -1, 'company');
dol_banner_tab($object, 'socid', '', ($user->socid ? 0 : 1), 'rowid', 'nom');
print dol_get_fiche_end();

$newbutton = '';
if ($user->hasRight('webcams', 'webcam', 'write')) {
	$newbutton = dolGetButtonTitle($langs->trans('New_Webcam'), '', 'fa fa-plus-circle', dol_buildpath('/webcams/webcam_card.php', 1).'?action=create&fk_soc='.$object->id.'&backtopage='.urlencode($_SERVER['PHP_SELF'].'?socid='.$object->id));
}
print load_fiche_titre($langs->trans('Webcams'), $newbutton, 'fa-video');

$sql = "SELECT t.rowid FROM ".$db->prefix()."webcams_webcam as t";
$sql .= " WHERE t.fk_soc = ".((int) $object->id)." AND t.entity IN (".getEntity('webcam').")";
$sql .= " ORDER BY t.status DESC, t.label, t.hostname";
$resql = $db->query($sql);

print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('Ref').'</th><th>'.$langs->trans('WebcamName').'</th><th>'.$langs->trans('WebcamLocation').'</th>';
print '<th>'.$langs->trans('WebcamInstallDate').'</th><th>'.$langs->trans('WebcamLastContact').'</th><th>'.$langs->trans('WebcamCamera').'</th><th>'.$langs->trans('Status').'</th></tr>';
$n = 0;
while ($resql && ($obj = $db->fetch_object($resql))) {
	$w = new Webcam($db);
	$w->fetch($obj->rowid);
	print '<tr class="oddeven"><td>'.$w->getNomUrl(1).'</td>';
	print '<td>'.dol_escape_htmltag((string) ($w->label ?: $w->hostname)).'</td>';
	print '<td>'.dol_escape_htmltag((string) $w->location).'</td>';
	print '<td>'.dol_print_date($w->date_installation, 'day').'</td>';
	print '<td>'.dol_print_date($w->last_contact, 'dayhour', 'tzuserrel').'</td>';
	print '<td>'.dol_escape_htmltag((string) $w->camera).'</td>';
	print '<td>'.$w->getLibStatut(5).'</td></tr>';
	$n++;
}
if (!$n) {
	print '<tr><td colspan="7"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}
print '</table></div>';

llxFooter();
$db->close();
