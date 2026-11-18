<?php
/* Copyright (C) 2026 ViewLapse
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Riquadro "Oggetti collegati" di un altro oggetto (ticket, intervento...)
 * collegato a una webcam: Dolibarr cerca il modello in
 * /<modulo>/<oggetto>/tpl/ per il tipo 'webcams_webcam' (getElementType()).
 */

// Protection to avoid direct call of template
if (empty($conf) || !is_object($conf)) {
	print "Error, template page can't be called as URL";
	exit(1);
}

include dirname(__DIR__, 2).'/core/tpl/linkedobjectblock_webcam.tpl.php';
