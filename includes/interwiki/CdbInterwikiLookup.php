<?php
namespace MediaWiki\Interwiki;

/**
 * DB InterwikiLookup implementation
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
use Interwiki;

abstract class CdbInterwikiLookup implements PreCachedInterwikiLookup {
	/**
	 * @var string
	 */
	private $cdbData;

	/**
	 * @var CdbReader|null
	 */
	private $cdbReader = null;

	function __construct(
		$interwikiScopes,
		$fallbackSite,
		$cdbData
	) {
		parent::__construct( $interwikiScopes, $fallbackSite );
		$this->cdbData = $cdbData;
		$this->interwikiScopes = $interwikiScopes;
		$this->fallbackSite = $fallbackSite;
	}

	protected function getCacheValue( $key ) {
		try {
			if ( $this->cdbReader === null ) {
				$this->cdbReader = \Cdb\Reader::open( $this->cdbData );
			}

			return $this->cdbReader->get( $key );
		} catch ( CdbException $e ) {
			wfDebug( __METHOD__ . ": CdbException caught, error message was "
				. $e->getMessage() );
		}
		return false;
	}
}
