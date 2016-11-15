<?php
namespace MediaWiki\Interwiki;

/**
 * Base InterwikiLookup implementation
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

use Language;
use MapCacheLRU;

class BaseInterwikiLookup implements InterwikiLookup {
	/**
	 * @var MapCacheLRU
	 */
	private $localCache;

	/**
	 * @var Language
	 */
	private $contentLanguage;

	/**
	 * @var InterwikiLookup
	 */
	private $delegate;

	public function __construct( Language $contentLanguage, $delegate ) {
		$this->contentLanguage = $contentLanguage;
		$this->localCache = new MapCacheLRU( 100 );
		$this->delegate = $delegate;
	}

	/**
	 * @param string $prefix Interwiki prefix to use
	 * @return bool Whether it exists
	 */
	public function isValidInterwiki( $prefix ) {
		$result = $this->fetch( $prefix );
		return (bool)$result;
	}

	/**
	 * @param string $prefix Interwiki prefix to use
	 * @return Interwiki|null|bool
	 */
	public function fetch( $prefix ) {
		if ( $prefix == '' ) {
			return null;
		}

		$prefix = $this->contentLanguage->lc( $prefix );
		if ( $this->localCache->has( $prefix ) ) {
			return $this->localCache->get( $prefix );
		}
		$iw = $this->delegate->fetch( $prefix );
		$this->localCache->set( $prefix, $iw );
		return $iw;
	}

	/**
	 * @param string $prefix
	 */
	public function getAllPrefixes( $local = null ) {
		return $this->delegate->getAllPrefixes( $local );
	}

	/**
	 * @param string $prefix
	 */
	public function invalidateCache( $prefix ) {
		// XXX: missing call to $contentLanguage->lc( $prefix ) ?
		$this->localCache->clear( $prefix );
		$this->delegate->invalidateCache( $prefix );
	}

	/**
	 * Resets locally cached Interwiki objects. This is intended for use during testing only.
	 * This does not invalidate entries in the persistent cache, as invalidateCache() does.
	 * @since 1.27
	 */
	public function resetLocalCache() {
		$this->localCache->clear();
	}
}
