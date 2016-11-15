<?php
namespace MediaWiki\Interwiki;

/**
 * InterwikiLookup implementing the "classic" interwiki storage (hardcoded up to MW 1.26).
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
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
 * @file
 */
use \Cdb\Exception as CdbException;
use \Cdb\Reader as CdbReader;
use Database;
use Hooks;
use Interwiki;
use Language;
use MapCacheLRU;
use WANObjectCache;

/**
 * InterwikiLookup implementing the "classic" interwiki storage (hardcoded up to MW 1.26).
 *
 * This implements two levels of caching (in-process array and a WANObjectCache)
 * and tree storage backends (SQL, CDB, and plain PHP arrays).
 *
 * All information is loaded on creation when called by $this->fetch( $prefix ).
 * All work is done on replica DB, because this should *never* change (except during
 * schema updates etc, which aren't wiki-related)
 *
 * @since 1.28
 */
class ClassicInterwikiLookup extends BaseInterwikiLookup {
	/**
	 * @param Language $contentLanguage Language object used to convert prefixes to lower case
	 * @param WANObjectCache $objectCache Cache for interwiki info retrieved from the database
	 * @param int $objectCacheExpiry Expiry time for $objectCache, in seconds
	 * @param bool|array|string $cdbData The path of a CDB file, or
	 *        an array resembling the contents of a CDB file,
	 *        or false to use the database.
	 * @param int $interwikiScopes Specify number of domains to check for messages:
	 *    - 1: Just local wiki level
	 *    - 2: wiki and global levels
	 *    - 3: site level as well as wiki and global levels
	 * @param string $fallbackSite The code to assume for the local site,
	 */
	function __construct(
		Language $contentLanguage,
		WANObjectCache $objectCache,
		$objectCacheExpiry,
		$cdbData,
		$interwikiScopes,
		$fallbackSite
	) {
		if ( is_array( $cdbData ) ) {
			$wrapped = new HashInterwikiLookup(
				$cdbData,
				$interwikiScopes,
				$fallbackSite
			);
		} else if( is_string( $cdbData ) ) {
			$wrapped = new CdbInterwikiLookup(
				$cdbData,
				$interwikiScopes,
				$fallbackSite
			);
		} else {
			$wrapped = new DatabaseInterwikiLookup(
				$objectCache,
				$objectCacheExpiry
			);
		}
		parent::__construct( $contentLanguage, $wrapped );
	}
}
