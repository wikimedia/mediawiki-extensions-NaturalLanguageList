<?php
/**
 * Natural Language List allows for creation of simple lists in
 * natural languages (e.g. 1, 2, 3, ... n-1 and n), and several
 * other sophisticated and useful list related functions.
 *
 *
 * Copyright (C) 2010 'Svip', 'Happy-melon', and others.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version; or the DWTFYWWI License version 1,
 * as detailed below.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * -----------------------------------------------------------------
 *                          DWTFYWWI LICENSE
 *                      Version 1, January 2006
 *
 * Copyright (C) 2006 Ævar Arnfjörð Bjarmason
 *
 *                        DWTFYWWI LICENSE
 *  TERMS AND CONDITIONS FOR COPYING, DISTRIBUTION AND MODIFICATION
 * 0. The author grants everyone permission to do whatever the fuck they
 * want with the software, whatever the fuck that may be.
 * -----------------------------------------------------------------
 */
if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'NaturalLanguageList' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['NaturalLanguageList'] = __DIR__ . '/i18n';
	$wgExtensionMessagesFiles['NaturalLanguageListMagic'] = __DIR__ . '/NaturalLanguageList.i18n.magic.php';
	wfWarn(
		'Deprecated PHP entry point used for the NaturalLanguageList extension. ' .
		'Please use wfLoadExtension() instead, ' .
		'see https://www.mediawiki.org/wiki/Special:MyLanguage/Manual:Extension_registration for more details.'
	);
	return;
} else {
	die( 'This version of the NaturalLanguageList extension requires MediaWiki 1.29+' );
}
