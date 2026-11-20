<?php
/* Copyright (C) 2017       Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2024-2025  Frédéric France         <frederic.france@free.fr>
 * Copyright (C) 2025		Anthony Berton 			<anthony.berton@bb2a.fr>
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
 *    \file       htdocs/modulebuilder/template/webcam_card.php
 *    \ingroup    webcams
 *    \brief      Page to create/edit/view webcam
 */


// General defined Options
//if (! defined('CSRFCHECK_WITH_TOKEN'))     define('CSRFCHECK_WITH_TOKEN', '1');					// Force use of CSRF protection with tokens even for GET
//if (! defined('MAIN_AUTHENTICATION_MODE')) define('MAIN_AUTHENTICATION_MODE', 'aloginmodule');	// Force authentication handler
//if (! defined('MAIN_LANG_DEFAULT'))        define('MAIN_LANG_DEFAULT', 'auto');					// Force LANG (language) to a particular value
//if (! defined('MAIN_SECURITY_FORCECSP'))   define('MAIN_SECURITY_FORCECSP', 'none');				// Disable all Content Security Policies
//if (! defined('NOBROWSERNOTIF'))     		 define('NOBROWSERNOTIF', '1');					// Disable browser notification
//if (! defined('NOIPCHECK'))                define('NOIPCHECK', '1');						// Do not check IP defined into conf $dolibarr_main_restrict_ip
//if (! defined('NOLOGIN'))                  define('NOLOGIN', '1');						// Do not use login - if this page is public (can be called outside logged session). This includes the NOIPCHECK too.
//if (! defined('NOREQUIREAJAX'))            define('NOREQUIREAJAX', '1');       	  		// Do not load ajax.lib.php library
//if (! defined('NOREQUIREDB'))              define('NOREQUIREDB', '1');					// Do not create database handler $db
//if (! defined('NOREQUIREHTML'))            define('NOREQUIREHTML', '1');					// Do not load html.form.class.php
//if (! defined('NOREQUIREMENU'))            define('NOREQUIREMENU', '1');					// Do not load and show top and left menu
//if (! defined('NOREQUIRESOC'))             define('NOREQUIRESOC', '1');					// Do not load object $mysoc
//if (! defined('NOREQUIRETRAN'))            define('NOREQUIRETRAN', '1');					// Do not load object $langs
//if (! defined('NOREQUIREUSER'))            define('NOREQUIREUSER', '1');					// Do not load object $user
//if (! defined('NOSCANGETFORINJECTION'))    define('NOSCANGETFORINJECTION', '1');			// Do not check injection attack on GET parameters
//if (! defined('NOSCANPOSTFORINJECTION'))   define('NOSCANPOSTFORINJECTION', '1');			// Do not check injection attack on POST parameters
//if (! defined('NOSESSION'))                define('NOSESSION', '1');						// On CLI mode, no need to use web sessions
//if (! defined('NOSTYLECHECK'))             define('NOSTYLECHECK', '1');					// Do not check style html tag into posted data
//if (! defined('NOTOKENRENEWAL'))           define('NOTOKENRENEWAL', '1');					// Do not roll the Anti CSRF token (used if MAIN_SECURITY_CSRF_WITH_TOKEN is on)


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
 * @var Societe $mysoc
 */
include_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
include_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
include_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';
dol_include_once('/webcams/class/webcam.class.php');
dol_include_once('/webcams/lib/webcams_webcam.lib.php');

// Load translation files required by the page
$langs->loadLangs(array("webcams@webcams", "other"));

// Get parameters
$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$lineid   = GETPOSTINT('lineid');
//$socid = GETPOSTINT('socid');

$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$cancel = GETPOST('cancel');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : getDolDefaultContextPage(__FILE__); // To manage different context of search
$backtopage = GETPOST('backtopage', 'alpha');					// if not set, a default page will be used
$backtopageforcancel = GETPOST('backtopageforcancel', 'alpha');	// if not set, $backtopage will be used
$optioncss = GETPOST('optioncss', 'aZ'); // Option for the css output (always '' except when 'print')
$dol_openinpopup = GETPOST('dol_openinpopup', 'aZ09');

// Initialize a technical objects
$object = new Webcam($db);
$extrafields = new ExtraFields($db);
$diroutputmassaction = $conf->webcams->dir_output.'/temp/massgeneration/'.$user->id;
$hookmanager->initHooks(array($object->element.'card', 'globalcard')); // Note that conf->hooks_modules contains array
$soc = null;

// Fetch optionals attributes and labels
$extrafields->fetch_name_optionals_label($object->table_element);


$search_array_options = $extrafields->getOptionalsFromPost($object->table_element, '', 'search_');

// Initialize array of search criteria
$search_all = trim(GETPOST("search_all", 'alpha'));
$search = array();
foreach ($object->fields as $key => $val) {
	if (GETPOST('search_'.$key, 'alpha')) {
		$search[$key] = GETPOST('search_'.$key, 'alpha');
	}
}

if (empty($action) && empty($id) && empty($ref)) {
	$action = 'view';
}

// Load object
include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php'; // Must be 'include', not 'include_once'.

// There is several ways to check permission.
// Set $enablepermissioncheck to 1 to enable a minimum low level of checks
$enablepermissioncheck = getDolGlobalInt('WEBCAMS_ENABLE_PERMISSION_CHECK');
if ($enablepermissioncheck) {
	$permissiontoread = $user->hasRight('webcams', 'webcam', 'read');
	$permissiontoadd = $user->hasRight('webcams', 'webcam', 'write'); // Used by the include of actions_addupdatedelete.inc.php and actions_lineupdown.inc.php
	$permissiontodelete = $user->hasRight('webcams', 'webcam', 'delete') || ($permissiontoadd && isset($object->status) && $object->status == $object::STATUS_DRAFT);
	$permissionnote = $user->hasRight('webcams', 'webcam', 'write'); // Used by the include of actions_setnotes.inc.php
	$permissiondellink = $user->hasRight('webcams', 'webcam', 'write'); // Used by the include of actions_dellink.inc.php
} else {
	$permissiontoread = 1;
	$permissiontoadd = 1; // Used by the include of actions_addupdatedelete.inc.php and actions_lineupdown.inc.php
	$permissiontodelete = 1;
	$permissionnote = 1;
	$permissiondellink = 1;
}

$upload_dir = $conf->webcams->multidir_output[isset($object->entity) ? $object->entity : 1].'/webcam';

// Security check (enable at least one, the most restrictive one)
//if ($user->socid > 0) accessforbidden();
//if ($user->socid > 0) $socid = $user->socid;
//$isdraft = (isset($object->status) && ($object->status == $object::STATUS_DRAFT) ? 1 : 0);
//restrictedArea($user, $object->module, $object, $object->table_element, $object->element, 'fk_soc', 'rowid', $isdraft);
if (!isModEnabled($object->module)) {
	accessforbidden("Module ".$object->module." not enabled");
}
if (!$permissiontoread) {
	accessforbidden();
}

$error = 0;


/*
 * Actions
 */

$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	$backurlforlist = dol_buildpath('/webcams/webcam_list.php', 1);

	if (empty($backtopage) || ($cancel && empty($id))) {
		if (empty($backtopage) || ($cancel && strpos($backtopage, '__ID__'))) {
			if (empty($id) && (($action != 'add' && $action != 'create') || $cancel)) {
				$backtopage = $backurlforlist;
			} else {
				$backtopage = dol_buildpath('/webcams/webcam_card.php', 1).'?id='.((!empty($id) && $id > 0) ? $id : '__ID__');
			}
		}
	}

	$triggermodname = $object->TRIGGER_PREFIX.'_MODIFY'; // Name of trigger action code to execute when we modify record. Used in actions_addupdatedelete.inc.php

	// Change of customer: propose a row of the archive after the rental (M13) for the old customer
	if ($action == 'update' && !$cancel && $permissiontoadd && $object->id > 0 && (int) $object->fk_soc > 0 && GETPOSTISSET('fk_soc') && GETPOSTINT('fk_soc') != (int) $object->fk_soc) {
		$backtopage = dol_buildpath('/webcams/webcam_card.php', 1).'?id='.((int) $object->id).'&action=proposearchive&archive_socid='.((int) $object->fk_soc).'&token='.newToken().'#archives';
	}

	// Archives after the rental (llx_webcams_archive)
	if ($action == 'addarchive' && $permissiontoadd && $object->id > 0) {
		$asoc = GETPOSTINT('archive_socid');
		$astart = dol_mktime(12, 0, 0, GETPOSTINT('archive_startmonth'), GETPOSTINT('archive_startday'), GETPOSTINT('archive_startyear'));
		$aend = dol_mktime(12, 0, 0, GETPOSTINT('archive_endmonth'), GETPOSTINT('archive_endday'), GETPOSTINT('archive_endyear'));
		$auntil = dol_mktime(12, 0, 0, GETPOSTINT('archive_untilmonth'), GETPOSTINT('archive_untilday'), GETPOSTINT('archive_untilyear'));
		$anote = dol_trunc(GETPOST('archive_note', 'alphanohtml'), 255, 'right', 'UTF-8', 1);
		$errs = array();
		if ($asoc <= 0) {
			$errs[] = $langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('Customer'));
		}
		if (!$aend) {
			$errs[] = $langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('WebcamRentalEnd'));
		}
		if (!$auntil) {
			$errs[] = $langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('WebcamArchiveUntil'));
		}
		if ($aend && $auntil && $auntil < $aend) {
			$errs[] = $langs->trans('WebcamArchiveUntilBeforeEnd');
		}
		if ($astart && $aend && $aend < $astart) {
			$errs[] = $langs->trans('WebcamRentalEnd').': '.$langs->trans('WebcamDateBeforeStart');
		}
		if ($errs) {
			setEventMessages(null, $errs, 'errors');
			$action = 'proposearchive';
		} else {
			$sql = "INSERT INTO ".$db->prefix()."webcams_archive (entity, fk_webcam, fk_soc, rental_start, rental_end, archive_until, note, fk_user_creat, date_creation)";
			$sql .= " VALUES (".((int) (empty($object->entity) ? $conf->entity : $object->entity)).", ".((int) $object->id).", ".((int) $asoc).", ";
			$sql .= ($astart ? "'".$db->escape(dol_print_date($astart, '%Y-%m-%d'))."'" : "NULL").", '".$db->escape(dol_print_date($aend, '%Y-%m-%d'))."', '".$db->escape(dol_print_date($auntil, '%Y-%m-%d'))."', ";
			$sql .= ($anote !== '' ? "'".$db->escape($anote)."'" : "NULL").", ".((int) $user->id).", '".$db->idate(dol_now())."')";
			if ($db->query($sql)) {
				setEventMessages($langs->trans('WebcamArchiveAdded'), null, 'mesgs');
				header('Location: '.$_SERVER['PHP_SELF'].'?id='.((int) $object->id).'#archives');
				exit;
			}
			setEventMessages($db->lasterrno() == 'DB_ERROR_RECORD_ALREADY_EXISTS' ? $langs->trans('WebcamArchiveExists') : $db->lasterror(), null, 'errors');
			$action = 'proposearchive';
		}
	}
	if ($action == 'confirm_deletearchive' && $confirm == 'yes' && $permissiontoadd && $object->id > 0) {
		$sql = "DELETE FROM ".$db->prefix()."webcams_archive WHERE rowid = ".GETPOSTINT('archiveid')." AND fk_webcam = ".((int) $object->id);
		if ($db->query($sql)) {
			setEventMessages($langs->trans('RecordDeleted'), null, 'mesgs');
			header('Location: '.$_SERVER['PHP_SELF'].'?id='.((int) $object->id).'#archives');
			exit;
		}
		setEventMessages($db->lasterror(), null, 'errors');
	}

	// Final film delivered by the operator (the portal sets only queued and ready)
	if ($action == 'confirm_filmdelivered' && $confirm == 'yes' && $permissiontoadd && $object->id > 0 && $object->film_status !== 'delivered') {
		$sql = "UPDATE ".$db->prefix()."webcams_webcam SET film_status = 'delivered', fk_user_modif = ".((int) $user->id)." WHERE rowid = ".((int) $object->id);
		if ($db->query($sql)) {
			$object->film_status = 'delivered';
			if (isModEnabled('agenda')) {
				require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
				$ev = new ActionComm($db);
				$ev->type_code = 'AC_OTH_AUTO';
				$ev->code = 'AC_WEBCAMS_FILM';
				$ev->label = dol_trunc($object->ref.' - '.$langs->transnoentitiesnoconv('WebcamEventFilmDelivered'), 255, 'right', 'UTF-8', 1);
				$ev->note_private = $langs->transnoentitiesnoconv('WebcamEventFilmDelivered');
				$ev->datep = dol_now();
				$ev->percentage = -1;
				$ev->userownerid = $user->id;
				$ev->socid = $object->fk_soc > 0 ? $object->fk_soc : 0;
				$ev->fk_element = $object->id;
				$ev->elementtype = $object->element.'@'.$object->module;
				$ev->create($user);
			}
			setEventMessages($langs->trans('WebcamFilmDelivered'), null, 'mesgs');
		} else {
			setEventMessages($db->lasterror(), null, 'errors');
		}
	}

	// Actions cancel, add, update, update_extras, confirm_validate, confirm_delete, confirm_deleteline, confirm_clone, confirm_close, confirm_setdraft, confirm_reopen
	include DOL_DOCUMENT_ROOT.'/core/actions_addupdatedelete.inc.php';

	// Actions when linking object each other
	include DOL_DOCUMENT_ROOT.'/core/actions_dellink.inc.php';

	// Actions when printing a doc from card
	include DOL_DOCUMENT_ROOT.'/core/actions_printing.inc.php';

	//BEGIN MODULEBUILDER LINES
	// Action to move up and down lines of object
	include DOL_DOCUMENT_ROOT.'/core/actions_lineupdown.inc.php';
	//END MODULEBUILDER LINES

	// Action to build doc
	include DOL_DOCUMENT_ROOT.'/core/actions_builddoc.inc.php';

	// Other special actions
	/*
	if ($action == 'set_thirdparty' && $permissiontoadd) {
		$object->setValueFrom('fk_soc', GETPOSTINT('fk_soc'), '', null, 'date', '', $user, $triggermodname);
	}
	if ($action == 'classin' && $permissiontoadd) {
		$object->setProject(GETPOSTINT('projectid'));
	}
	*/

	// Actions to send emails
	$triggersendname = 'WEBCAMS_WEBCAM_SENTBYMAIL';
	$autocopy = 'MAIN_MAIL_AUTOCOPY_WEBCAM_TO';
	$trackid = 'webcam'.$object->id;
	include DOL_DOCUMENT_ROOT.'/core/actions_sendmails.inc.php';
}



/*
 * View
 */

$form = new Form($db);
$formfile = new FormFile($db);

$title = $langs->trans("Webcam")." - ".$langs->trans('Card');
//$title = $object->ref." - ".$langs->trans('Card');
if ($action == 'create') {
	$title = $langs->trans("NewObject", $langs->transnoentitiesnoconv("Webcam"));
}
$help_url = '';

llxHeader('', $title, $help_url, '', 0, 0, '', '', '', 'mod-webcams page-card');

// Example : Adding jquery code
// print '<script type="text/javascript">
// jQuery(document).ready(function() {
// 	function init_myfunc()
// 	{
// 		jQuery("#myid").removeAttr(\'disabled\');
// 		jQuery("#myid").attr(\'disabled\',\'disabled\');
// 	}
// 	init_myfunc();
// 	jQuery("#mybutton").click(function() {
// 		init_myfunc();
// 	});
// });
// </script>';

// Part to create
if ($action == 'create') {
	if (empty($permissiontoadd)) {
		accessforbidden('NotEnoughPermissions', 0, 1);
	}

	print load_fiche_titre($title, '', $object->picto);

	print '<form method="POST" action="'.dolBuildUrl($_SERVER["PHP_SELF"]).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';
	if ($backtopage) {
		print '<input type="hidden" name="backtopage" value="'.$backtopage.'">';
	}
	if ($backtopageforcancel) {
		print '<input type="hidden" name="backtopageforcancel" value="'.$backtopageforcancel.'">';
	}
	if ($dol_openinpopup) {
		print '<input type="hidden" name="dol_openinpopup" value="'.$dol_openinpopup.'">';
	}

	print dol_get_fiche_head(array(), '');


	print '<table class="border centpercent tableforfieldcreate">'."\n";

	// Common attributes
	include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_add.tpl.php';

	// Other attributes
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_add.tpl.php';

	print '</table>'."\n";

	print dol_get_fiche_end();

	print $form->buttonsSaveCancel("Create");

	print '</form>';

	//dol_set_focus('input[name="ref"]');
}

// Part to edit record
if (($id || $ref) && $action == 'edit') {
	print load_fiche_titre($langs->trans("Webcam"), '', $object->picto);

	print '<form method="POST" action="'.dolBuildUrl($_SERVER["PHP_SELF"]).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="update">';
	print '<input type="hidden" name="id" value="'.$object->id.'">';
	if ($backtopage) {
		print '<input type="hidden" name="backtopage" value="'.$backtopage.'">';
	}
	if ($backtopageforcancel) {
		print '<input type="hidden" name="backtopageforcancel" value="'.$backtopageforcancel.'">';
	}

	print dol_get_fiche_head();

	print '<table class="border centpercent tableforfieldedit">'."\n";

	// Common attributes
	include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_edit.tpl.php';

	// Other attributes
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_edit.tpl.php';

	print '</table>';

	print dol_get_fiche_end();

	print $form->buttonsSaveCancel();

	print '</form>';
}

// Part to show record
if ($object->id > 0 && (empty($action) || ($action != 'edit' && $action != 'create'))) {
	$formconfirm = '';

	// Confirmation to delete (using preloaded confirm popup)
	if ($action == 'delete' || ($conf->use_javascript_ajax && empty($conf->dol_use_jmobile))) {
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('DeleteWebcam'), $langs->trans('ConfirmDeleteObject'), 'confirm_delete', '', 0, 'action-delete');
	}
	//BEGIN MODULEBUILDER LINES
	// Confirmation to delete line
	if ($action == 'deleteline') {
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id.'&lineid='.$lineid, $langs->trans('DeleteLine'), $langs->trans('ConfirmDeleteLine'), 'confirm_deleteline', '', 0, 1);
	}
	//END MODULEBUILDER LINES

	// Clone confirmation
	if ($action == 'clone') {
		// Create an array for form
		$formquestion = array();
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('ToClone'), $langs->trans('ConfirmCloneAsk', $object->ref), 'confirm_clone', $formquestion, 'yes', 1);
	}

	// Confirmation of action xxxx (You can use it for xxx = 'close', xxx = 'reopen', ...)
	// if ($action == 'xxx') {
	// 	$text = $langs->trans('ConfirmActionXxx', $object->ref);
	// 	if (isModEnabled('notification')) {
	// 		require_once DOL_DOCUMENT_ROOT . '/core/class/notify.class.php';
	// 		$notify = new Notify($db);
	// 		$text .= '<br>';
	// 		$text .= $notify->confirmMessage('WEBCAM_CLOSE', $object->socid, $object);
	// 	}

	// 	$formquestion = array();

	// 	$forcecombo=0;
	// 	if ($conf->browser->name == 'ie') $forcecombo = 1;	// There is a bug in IE10 that make combo inside popup crazy
	// 	$formquestion = array(
	// 		// 'text' => $langs->trans("ConfirmClone"),
	// 		// array('type' => 'checkbox', 'name' => 'clone_content', 'label' => $langs->trans("CloneMainAttributes"), 'value' => 1),
	// 		// array('type' => 'checkbox', 'name' => 'update_prices', 'label' => $langs->trans("PuttingPricesUpToDate"), 'value' => 1),
	// 		// array('type' => 'other',    'name' => 'idwarehouse',   'label' => $langs->trans("SelectWarehouseForStockDecrease"), 'value' => $formproduct->selectWarehouses(GETPOST('idwarehouse')?GETPOST('idwarehouse'):'ifone', 'idwarehouse', '', 1, 0, 0, '', 0, $forcecombo))
	// 	);
	// 	$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('XXX'), $text, 'confirm_xxx', $formquestion, 0, 1, 220);
	// }

	if ($action == 'deletearchive') {
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id.'&archiveid='.GETPOSTINT('archiveid'), $langs->trans('WebcamArchiveDelete'), $langs->trans('WebcamArchiveDeleteConfirm'), 'confirm_deletearchive', '', 0, 1);
	}
	if ($action == 'filmdelivered') {
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('WebcamFilmSetDelivered'), $langs->trans('WebcamFilmSetDeliveredConfirm'), 'confirm_filmdelivered', '', 'yes', 1);
	}

	// Call Hook formConfirm
	$parameters = array('formConfirm' => $formconfirm, 'lineid' => $lineid);
	$reshook = $hookmanager->executeHooks('formConfirm', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
	if (empty($reshook)) {
		$formconfirm .= $hookmanager->resPrint;
	} elseif ($reshook > 0) {
		$formconfirm = $hookmanager->resPrint;
	}

	// Print form confirm
	print $formconfirm;



	$head = webcamPrepareHead($object);

	print dol_get_fiche_head($head, 'card', $langs->trans("Webcam"), -1, $object->picto, 0, '', '', 0, '', 1);


	// Object card
	// ------------------------------------------------------------

	$linkback = '<a href="'.dol_buildpath('/webcams/webcam_list.php', 1).'?restore_lastsearch_values=1'.(!empty($socid) ? '&socid='.$socid : '').'">'.$langs->trans("BackToList").'</a>';

	$morehtmlref = '<div class="refidno">';
	/*
		// Ref customer
		$morehtmlref .= $form->editfieldkey("RefCustomer", 'ref_client', $object->ref_client, $object, $usercancreate, 'string', '', 0, 1);
		$morehtmlref .= $form->editfieldval("RefCustomer", 'ref_client', $object->ref_client, $object, $usercancreate, 'string'.(getDolGlobalInt('THIRDPARTY_REF_INPUT_SIZE') ? ':'.getDolGlobalInt('THIRDPARTY_REF_INPUT_SIZE') : ''), '', null, null, '', 1);
		// Thirdparty
		$morehtmlref .= '<br>'.$object->thirdparty->getNomUrl(1, 'customer');
		if (!getDolGlobalInt('MAIN_DISABLE_OTHER_LINK') && $object->thirdparty->id > 0) {
			$morehtmlref .= ' (<a href="'.DOL_URL_ROOT.'/commande/list.php?socid='.$object->thirdparty->id.'&search_societe='.urlencode($object->thirdparty->name).'">'.$langs->trans("OtherOrders").'</a>)';
		}
		// Project
		if (isModEnabled('project')) {
			$langs->load("projects");
			$morehtmlref .= '<br>';
			if ($permissiontoadd) {
				$morehtmlref .= img_picto($langs->trans("Project"), 'project', 'class="pictofixedwidth"');
				if ($action != 'classify') {
					$morehtmlref .= '<a class="editfielda" href="'.dolBuildUrl($_SERVER['PHP_SELF'], ['action' => 'classify', 'id' => $object->id], true).'">'.img_edit($langs->transnoentitiesnoconv('SetProject')).'</a> ';
				}
				$morehtmlref .= $form->form_project($_SERVER['PHP_SELF'].'?id='.$object->id, $object->socid, $object->fk_project, ($action == 'classify' ? 'projectid' : 'none'), 0, 0, 0, 1, '', 'maxwidth300');
			} else {
				if (!empty($object->fk_project)) {
					$proj = new Project($db);
					$proj->fetch($object->fk_project);
					$morehtmlref .= $proj->getNomUrl(1);
					if ($proj->title) {
						$morehtmlref .= '<span class="opacitymedium"> - '.dol_escape_htmltag($proj->title).'</span>';
					}
				}
			}
		}
	*/
	$morehtmlref .= '</div>';


	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

	print '<div class="fichecenter">';
	print '<div class="fichehalfleft">';
	print '<div class="underbanner clearboth"></div>';
	print '<table class="border centpercent tableforfield">'."\n";

	// Common attributes
	$keyforbreak = 'last_contact';	// Technical data from the router in the second column
	// Counters, Victron and portal are shown below with their own layout
	$customfields = array('shutter_count', 'shots_total', 'camera_hours', 'camera_power_cycles', 'counters_date',
		'battery_v', 'battery_v_min24', 'battery_soc', 'charge_state', 'solar_wh_yesterday', 'victron_error', 'victron_date',
		'portal_slug', 'url_current', 'windy_published', 'windy_id',
		'sla_enabled', 'sla_offline_hours', 'sla_response_days', 'sla_target_pct', 'sla_hours', 'sla_weekdays',
		'availability_pct', 'availability_period',
		'plan_level', 'plan_note', 'rental_start', 'rental_end', 'sponsor_enabled', 'sponsor_slots', 'sponsor_start', 'sponsor_end',
		'opt_weather_cert', 'film_option', 'film_format', 'film_duration', 'film_status', 'film_url', 'report_enabled', 'report_email');
	$savedfields = $object->fields;
	foreach ($customfields as $k) {
		unset($object->fields[$k]);
	}
	//unset($object->fields['fk_project']);				// Hide field already shown in banner
	//unset($object->fields['fk_soc']);					// Hide field already shown in banner
	include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_view.tpl.php';

	// Other attributes. Fields from hook formObjectOptions and Extrafields.
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_view.tpl.php';

	$object->fields = $savedfields;
	print '<tr class="liste_titre"><td colspan="2">'.img_picto('', 'fa-tags', 'class="pictofixedwidth"').$langs->trans('WebcamPlanBlock').'</td></tr>';
	print webcamsCardPlanRows($db, $object);
	print '<tr class="liste_titre"><td colspan="2">'.img_picto('', 'fa-tools', 'class="pictofixedwidth"').$langs->trans('WebcamServiceBlock').'</td></tr>';
	print webcamsCardTechRows($object);

	print '</table>';
	print '</div>';
	print '</div>';

	print '<div class="clearboth"></div>';

	print dol_get_fiche_end();

	//BEGIN MODULEBUILDER LINES
	/*
	 * Lines
	 */

	if (!empty($object->table_element_line)) {
		// Show object lines
		$result = $object->getLinesArray();

		print '	<form name="addproduct" id="addproduct" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.(($action != 'editline') ? '' : '#line_'.GETPOSTINT('lineid')).'" method="POST">
		<input type="hidden" name="token" value="' . newToken().'">
		<input type="hidden" name="action" value="' . (($action != 'editline') ? 'addline' : 'updateline').'">
		<input type="hidden" name="mode" value="">
		<input type="hidden" name="page_y" value="">
		<input type="hidden" name="id" value="' . $object->id.'">
		';

		if (!empty($conf->use_javascript_ajax) && $object->status == 0) {
			include DOL_DOCUMENT_ROOT.'/core/tpl/ajaxrow.tpl.php';
		}

		print '<div class="div-table-responsive-no-min">';
		if (!empty($object->lines) || ($object->status == $object::STATUS_DRAFT && $permissiontoadd && $action != 'selectlines' && $action != 'editline')) {
			print '<table id="tablelines" class="noborder noshadow" width="100%">';
		}

		if (!empty($object->lines)) {
			$object->printObjectLines($action, $mysoc, null, GETPOSTINT('lineid'), 1);
		}

		//BEGIN MODULEBUILDER LINES
		// Form to add new line
		if ($object->status == 0 && $permissiontoadd && $action != 'selectlines') {
			if ($action != 'editline') {
				// Add products/services form

				$parameters = array();
				$reshook = $hookmanager->executeHooks('formAddObjectLine', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
				if ($reshook < 0) {
					setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
				}
				if (empty($reshook)) {
					$object->formAddObjectLine(1, $mysoc, $soc);
				}
			}
		}
		//END MODULEBUILDER LINES

		if (!empty($object->lines) || ($object->status == $object::STATUS_DRAFT && $permissiontoadd && $action != 'selectlines' && $action != 'editline')) {
			print '</table>';
		}
		print '</div>';

		print "</form>\n";
	}
	//END MODULEBUILDER LINES
	// Buttons for actions

	if ($action != 'presend' && $action != 'editline') {
		print '<div class="tabsAction">'."\n";
		$parameters = array();
		$reshook = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
		if ($reshook < 0) {
			setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
		}

		if (empty($reshook)) {
			// Send
			if (empty($user->socid)) {
				print dolGetButtonAction('', $langs->trans('SendMail'), 'email', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=presend&token='.newToken().'&mode=init#formmailbeforetitle');
			}

			// Back to draft
			if ($object->status == $object::STATUS_VALIDATED) {
				print dolGetButtonAction('', $langs->trans('SetToDraft'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=confirm_setdraft&confirm=yes&token='.newToken(), '', $permissiontoadd);
			}

			// Remote access with rttys (device id = ref of the webcam)
			$rttys = webcamsRttysUrls($object->ref);
			if ($rttys) {
				print dolGetButtonAction($langs->trans('WebcamRemoteTerminalHelp', $object->ref), $langs->trans('WebcamRemoteTerminal'), 'default', $rttys['terminal'], '', 1, array('attr' => array('target' => '_blank', 'rel' => 'noopener')));
				print dolGetButtonAction($langs->trans('WebcamRemoteTerminalHelp', $object->ref), $langs->trans('WebcamRemoteLuci'), 'default', $rttys['luci'], '', 1, array('attr' => array('target' => '_blank', 'rel' => 'noopener')));
			}

			// Final film: delivered by the operator
			if ((string) $object->film_status === 'ready') {
				print dolGetButtonAction('', $langs->trans('WebcamFilmSetDelivered'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=filmdelivered&token='.newToken(), '', $permissiontoadd);
			}

			// Modify
			print dolGetButtonAction('', $langs->trans('Modify'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=edit&token='.newToken(), '', $permissiontoadd);

			// Validate
			if ($object->status == $object::STATUS_DRAFT) {
				if (empty($object->table_element_line) || (is_array($object->lines) && count($object->lines) > 0)) {
					print dolGetButtonAction('', $langs->trans('Validate'), 'default', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=confirm_validate&confirm=yes&token='.newToken(), '', $permissiontoadd);
				} else {
					$langs->load("errors");
					print dolGetButtonAction($langs->trans("ErrorAddAtLeastOneLineFirst"), $langs->trans("Validate"), 'default', '#', '', 0);
				}
			}

			// Clone
			if ($permissiontoadd) {
				print dolGetButtonAction('', $langs->trans('ToClone'), 'clone', $_SERVER['PHP_SELF'].'?id='.$object->id.(!empty($object->socid) ? '&socid='.$object->socid : '').'&action=clone&token='.newToken(), '', $permissiontoadd);
			}

			/*
			// Disable / Enable
			if ($permissiontoadd) {
				if ($object->status == $object::STATUS_ENABLED) {
					print dolGetButtonAction('', $langs->trans('Disable'), 'default', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=disable&token='.newToken(), '', $permissiontoadd);
				} else {
					print dolGetButtonAction('', $langs->trans('Enable'), 'default', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=enable&token='.newToken(), '', $permissiontoadd);
				}
			}
			if ($permissiontoadd) {
				if ($object->status == $object::STATUS_VALIDATED) {
					print dolGetButtonAction('', $langs->trans('Cancel'), 'default', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=close&token='.newToken(), '', $permissiontoadd);
				} else {
					print dolGetButtonAction('', $langs->trans('Re-Open'), 'default', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=reopen&token='.newToken(), '', $permissiontoadd);
				}
			}
			*/

			// Delete (with preloaded confirm popup)
			$deleteUrl = $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=delete&token='.newToken();
			$buttonId = 'action-delete-no-ajax';
			if ($conf->use_javascript_ajax && empty($conf->dol_use_jmobile)) {	// We can use preloaded confirm if not jmobile
				$deleteUrl = '';
				$buttonId = 'action-delete';
			}
			$params = array();
			print dolGetButtonAction('', $langs->trans("Delete"), 'delete', $deleteUrl, $buttonId, $permissiontodelete, $params);
		}
		print '</div>'."\n";
	}


	// Select mail models is same action as presend
	if (GETPOST('modelselected')) {
		$action = 'presend';
	}

	if ($action != 'presend') {
		print '<div class="fichecenter"><div class="fichehalfleft">';
		print '<a name="builddoc"></a>'; // ancre

		$includedocgeneration = 0;

		// Documents
		if ($includedocgeneration) {
			$objref = dol_sanitizeFileName($object->ref);
			$relativepath = $objref.'/'.$objref.'.pdf';
			$filedir = $conf->webcams->dir_output.'/'.$object->element.'/'.$objref;
			$urlsource = $_SERVER["PHP_SELF"]."?id=".$object->id;
			$genallowed = $permissiontoread; // If you can read, you can build the PDF to read content
			$delallowed = $permissiontoadd; // If you can create/edit, you can remove a file on card
			print $formfile->showdocuments('webcams:Webcam', $object->element.'/'.$objref, $filedir, $urlsource, $genallowed, $delallowed, $object->model_pdf, 1, 0, 0, 28, 0, '', '', '', $langs->defaultlang);
		}

		// Show links to link elements
		$tmparray = $form->showLinkToObjectBlock($object, array(), array('webcam'), 1);
		if (is_array($tmparray)) {
			$linktoelem = $tmparray['linktoelem'];
			$htmltoenteralink = $tmparray['htmltoenteralink'];
			print $htmltoenteralink;
			$somethingshown = $form->showLinkedObjectBlock($object, $linktoelem);
		} else {
			// backward compatibility
			$somethingshown = $form->showLinkedObjectBlock($object, $tmparray);
		}

		// Features from the level and the options (help, the portal computes them)
		print '<br>';
		print webcamsFeatureTable($object);

		// Archives after the rental (M13)
		print '<a name="archives"></a><br>';
		$archives = webcamsArchives($db, $object->id);
		$today = webcamsToday();
		print load_fiche_titre($langs->trans('WebcamArchives'), '<span class="opacitymedium small">'.$langs->trans('WebcamArchivesHelp').'</span>', 'fa-archive');
		print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
		print '<tr class="liste_titre"><th>'.$langs->trans('Customer').'</th><th class="center">'.$langs->trans('WebcamRental').'</th><th class="center">'.$langs->trans('WebcamArchiveUntil').'</th><th>'.$langs->trans('Note').'</th><th></th></tr>';
		foreach ($archives as $a) {
			$active = strcmp(substr((string) $a->archive_until, 0, 10), $today) >= 0;
			print '<tr class="oddeven">';
			print '<td class="tdoverflowmax200"><a href="'.DOL_URL_ROOT.'/societe/card.php?socid='.((int) $a->fk_soc).'">'.img_picto('', 'company', 'class="pictofixedwidth"').dol_escape_htmltag((string) $a->socname).'</a></td>';
			print '<td class="center nowraponall">'.($a->rental_start ? dol_print_date($db->jdate($a->rental_start), 'day') : '...').' - '.dol_print_date($db->jdate($a->rental_end), 'day').'</td>';
			print '<td class="center nowraponall">'.dolGetBadge(dol_print_date($db->jdate($a->archive_until), 'day'), '', $active ? 'success' : 'secondary', '', '', array('attr' => array('title' => $langs->trans($active ? 'WebcamArchiveActive' : 'WebcamArchiveExpired')))).'</td>';
			print '<td>'.dol_escape_htmltag((string) $a->note).'</td>';
			print '<td class="right">'.($permissiontoadd ? '<a class="reposition" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=deletearchive&archiveid='.((int) $a->rowid).'&token='.newToken().'">'.img_delete().'</a>' : '').'</td>';
			print '</tr>';
		}
		if (!$archives) {
			print '<tr><td colspan="5"><span class="opacitymedium">'.$langs->trans('WebcamArchivesNone').'</span></td></tr>';
		}
		print '</table></div>';
		if ($permissiontoadd) {
			$propose = ($action == 'proposearchive');
			$asoc = GETPOSTINT('archive_socid');
			if ($propose) {
				print info_admin($langs->trans('WebcamArchivePropose'), 0, 0, 'info');
			}
			print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'#archives">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="addarchive">';
			print '<table class="noborder centpercent"><tr class="oddeven">';
			print '<td>'.$form->select_company($asoc > 0 ? $asoc : '', 'archive_socid', '', 1, 0, 0, array(), 0, 'minwidth150 maxwidth200').'</td>';
			$dstart = $propose ? $object->rental_start : '';
			$dend = $propose ? dol_now() : '';
			if (GETPOSTISSET('archive_endyear')) {
				$dstart = dol_mktime(12, 0, 0, GETPOSTINT('archive_startmonth'), GETPOSTINT('archive_startday'), GETPOSTINT('archive_startyear'));
				$dend = dol_mktime(12, 0, 0, GETPOSTINT('archive_endmonth'), GETPOSTINT('archive_endday'), GETPOSTINT('archive_endyear'));
			}
			$duntil = GETPOSTISSET('archive_untilyear') ? dol_mktime(12, 0, 0, GETPOSTINT('archive_untilmonth'), GETPOSTINT('archive_untilday'), GETPOSTINT('archive_untilyear')) : '';
			print '<td class="nowraponall">'.$form->selectDate($dstart ? $dstart : -1, 'archive_start', 0, 0, 1, '', 1, 0).' - '.$form->selectDate($dend ? $dend : -1, 'archive_end', 0, 0, 1, '', 1, 0).'</td>';
			print '<td class="nowraponall">'.$form->selectDate($duntil ? $duntil : -1, 'archive_until', 0, 0, 1, '', 1, 0).'</td>';
			print '<td><input type="text" name="archive_note" class="flat maxwidth150" maxlength="255" value="'.dol_escape_htmltag(GETPOST('archive_note', 'alphanohtml')).'" placeholder="'.dol_escape_htmltag($langs->trans('Note')).'"></td>';
			print '<td class="right"><input type="submit" class="button small" value="'.dol_escape_htmltag($langs->trans('Add')).'"></td>';
			print '</tr></table></form>';
		}

		print '</div><div class="fichehalfright">';

		// Availability of the last 12 months (register written by webcam-portal)
		print webcamsAvailabilityTable($db, $object, 12);

		// BEGIN MODULEBUILDER TAB AGENDA
		$MAXEVENT = 10;

		$morehtmlcenter = dolGetButtonTitle($langs->trans('SeeAll'), '', 'fa fa-bars imgforviewmode', dol_buildpath('/webcams/webcam_agenda.php', 1).'?id='.$object->id);

		$includeeventlist = 0;

		// List of actions on element
		if ($includeeventlist) {
			include_once DOL_DOCUMENT_ROOT.'/core/class/html.formactions.class.php';
			$formactions = new FormActions($db);
			$somethingshown = $formactions->showactions($object, $object->element.'@'.$object->module, (is_object($object->thirdparty) ? $object->thirdparty->id : 0), 1, '', $MAXEVENT, '', $morehtmlcenter);
		}
		// END MODULEBUILDER TAB AGENDA

		print '</div></div>';
	}

	//Select mail models is same action as presend
	if (GETPOST('modelselected')) {
		$action = 'presend';
	}

	// Presend form
	$modelmail = 'webcams_webcam'; // same type as the templates of POST webcams/{id}/documents
	$defaulttopic = 'InformationMessage';
	$diroutput = $conf->webcams->dir_output;
	$trackid = 'webcam'.$object->id;

	include DOL_DOCUMENT_ROOT.'/core/tpl/card_presend.tpl.php';
}

// End of page
llxFooter();
$db->close();
