<?php
/* Copyright (C) 2026 ViewLapse
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    lib/webcams_compat.lib.php
 * \ingroup webcams
 * \brief   Functions of recent Dolibarr versions used by the module, for Dolibarr 20
 */

if (!function_exists('dolBuildUrl')) {
	/**
	 * Return path of url (dolBuildUrl() of Dolibarr 21+, without the hook buildurl)
	 *
	 * @param	string							$url		Relative path to file
	 * @param	array<string,int|float|string>	$params		Params for the http query
	 * @param	bool							$addtoken	Add the CSRF token
	 * @param	string							$anchor		Anchor #xxx at end of URL
	 * @return	string									Url
	 */
	function dolBuildUrl($url, $params = array(), $addtoken = false, $anchor = '')
	{
		if (empty($params['mainmenu']) && GETPOSTISSET('mainmenu')) {
			$params['mainmenu'] = GETPOST('mainmenu', 'restricthtml');
		}
		if (!isset($params['leftmenu']) && GETPOSTISSET('leftmenu')) {
			$params['leftmenu'] = GETPOST('leftmenu', 'restricthtml');
		}
		if ($addtoken) {
			$params['token'] = newToken();
		}
		if ($params) {
			$url .= '?'.http_build_query($params);
		}
		if ($anchor) {
			$url .= '#'.preg_replace('/[^a-z]/i', '', $anchor);
		}
		return $url;
	}
}

if (!function_exists('getDolDefaultContextPage')) {
	/**
	 * Return the default context page string (Dolibarr 21+)
	 *
	 * @param	string	$s	Page path
	 * @return	string		Value returned
	 */
	function getDolDefaultContextPage($s)
	{
		return str_replace('_', '', basename(dirname($s)).basename($s, '.php'));
	}
}
