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
 * \file    htdocs/modulebuilder/template/lib/webcams.lib.php
 * \ingroup webcams
 * \brief   Library files with common functions for Webcams
 */

/**
 * Prepare admin pages header
 *
 * @return array<array{string,string,string}>
 */
function webcamsAdminPrepareHead()
{
	global $langs, $conf;

	// global $db;
	// $extrafields = new ExtraFields($db);
	// $extrafields->fetch_name_optionals_label('webcam');

	$langs->load("webcams@webcams");

	$h = 0;
	$head = array();

	$head[$h][0] = dolBuildUrl(dol_buildpath("/webcams/admin/setup.php", 1));
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;

	/*
	$head[$h][0] = dolBuildUrl(dol_buildpath("/webcams/admin/webcam_extrafields.php", 1));
	$head[$h][1] = $langs->trans("ExtraFields");
	$nbExtrafields = (isset($extrafields->attributes['webcam']['label']) && is_countable($extrafields->attributes['webcam']['label'])) ? count($extrafields->attributes['webcam']['label']) : 0;
	if ($nbExtrafields > 0) {
		$head[$h][1] .= '<span class="badge marginleftonlyshort">' . $nbExtrafields . '</span>';
	}
	$head[$h][2] = 'webcam_extrafields';
	$h++;

	$head[$h][0] = dolBuildUrl(dol_buildpath("/webcams/admin/webcamline_extrafields.php", 1));
	$head[$h][1] = $langs->trans("ExtraFieldsLines");
	$nbExtrafields = (isset($extrafields->attributes['webcamline']['label']) && is_countable($extrafields->attributes['webcamline']['label'])) ? count($extrafields->attributes['webcam']['label']) : 0;
	if ($nbExtrafields > 0) {
		$head[$h][1] .= '<span class="badge marginleftonlyshort">' . $nbExtrafields . '</span>';
	}
	$head[$h][2] = 'webcam_extrafieldsline';
	$h++;
	*/

	$head[$h][0] = dolBuildUrl(dol_buildpath("/webcams/admin/about.php", 1));
	$head[$h][1] = $langs->trans("About");
	$head[$h][2] = 'about';
	$h++;

	// Show more tabs from modules
	// Entries must be declared in modules descriptor with line
	//$this->tabs = array(
	//	'entity:+tabname:Title:@webcams:/webcams/mypage.php?id=__ID__'
	//); // to add new tab
	//$this->tabs = array(
	//	'entity:-tabname:Title:@webcams:/webcams/mypage.php?id=__ID__'
	//); // to remove a tab
	complete_head_from_modules($conf, $langs, null, $head, $h, 'webcams@webcams');

	complete_head_from_modules($conf, $langs, null, $head, $h, 'webcams@webcams', 'remove');

	return $head;
}
