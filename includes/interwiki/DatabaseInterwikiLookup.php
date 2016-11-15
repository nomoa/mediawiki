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

use Interwiki;
use Database;
use Hooks;
use WANObjectCache;

class DatabaseInterwikiLookup implements InterwikiLookup {
	/**
	 * @var WANObjectCache
	 */
	private $objectCache;

	/**
	 * @var int
	 */
	private $objectCacheExpiry;

	public function __construct( WANObjectCache $objectCache, $objectCacheExpiry ) {
		$this->objectCache = $objectCache;
		$this->objectCacheExpiry = $objectCacheExpiry;
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
		$iw = $this->load( $prefix );
		if ( !$iw ) {
			$iw = false;
		}
		return $iw;
	}


	/**
	 * Returns all interwiki prefixes
	 *
	 * @param string|null $local If set, limits output to local/non-local interwikis
	 * @return array[] Interwiki rows, where each row is an associative array
	 */
	public function getAllPrefixes( $local = null ) {
		if ( $this->cdbData ) {
			return $this->getAllPrefixesCached( $local );
		}

		return $this->getAllPrefixesDB( $local );
	}

	/**
	 * Purge the in-process and object cache for an interwiki prefix
	 * @param string $prefix
	 */
	public function invalidateCache( $prefix ) {
		$key = $this->objectCache->makeKey( 'interwiki', $prefix );
		$this->objectCache->delete( $key );
	}

	/**
	 * Load the interwiki, trying first memcached then the DB
	 *
	 * @param string $prefix The interwiki prefix
	 * @return Interwiki|bool Interwiki if $prefix is valid, otherwise false
	 */
	private function load( $prefix ) {
		$iwData = [];
		if ( !Hooks::run( 'InterwikiLoadPrefix', [ $prefix, &$iwData ] ) ) {
			return $this->loadFromArray( $iwData );
		}

		if ( is_array( $iwData ) ) {
			$iw = $this->loadFromArray( $iwData );
			if ( $iw ) {
				return $iw; // handled by hook
			}
		}

		$iwData = $this->objectCache->getWithSetCallback(
			$this->objectCache->makeKey( 'interwiki', $prefix ),
			$this->objectCacheExpiry,
			function ( $oldValue, &$ttl, array &$setOpts ) use ( $prefix ) {
				$dbr = wfGetDB( DB_REPLICA ); // TODO: inject LoadBalancer

				$setOpts += Database::getCacheSetOptions( $dbr );

				$row = $dbr->selectRow(
					'interwiki',
					self::selectFields(),
					[ 'iw_prefix' => $prefix ],
					__METHOD__
				);

				return $row ? (array)$row : '!NONEXISTENT';
			}
		);

		if ( is_array( $iwData ) ) {
			return $this->loadFromArray( $iwData ) ?: false;
		}

		return false;
	}

	/**
	 * Fill in member variables from an array (e.g. memcached result, Database::fetchRow, etc)
	 *
	 * @param array $mc Associative array: row from the interwiki table
	 * @return Interwiki|bool Interwiki object or false if $mc['iw_url'] is not set
	 */
	private function loadFromArray( $mc ) {
		if ( isset( $mc['iw_url'] ) ) {
			$url = $mc['iw_url'];
			$local = isset( $mc['iw_local'] ) ? $mc['iw_local'] : 0;
			$trans = isset( $mc['iw_trans'] ) ? $mc['iw_trans'] : 0;
			$api = isset( $mc['iw_api'] ) ? $mc['iw_api'] : '';
			$wikiId = isset( $mc['iw_wikiid'] ) ? $mc['iw_wikiid'] : '';

			return new Interwiki( null, $url, $api, $wikiId, $local, $trans );
		}

		return false;
	}

	/**
	 * Fetch all interwiki prefixes from DB
	 *
	 * @param string|null $local If not null, limits output to local/non-local interwikis
	 * @return array[] Interwiki rows
	 */
	private function getAllPrefixesDB( $local ) {
		$db = wfGetDB( DB_REPLICA ); // TODO: inject DB LoadBalancer

		$where = [];

		if ( $local !== null ) {
			if ( $local == 1 ) {
				$where['iw_local'] = 1;
			} elseif ( $local == 0 ) {
				$where['iw_local'] = 0;
			}
		}

		$res = $db->select( 'interwiki',
			self::selectFields(),
			$where, __METHOD__, [ 'ORDER BY' => 'iw_prefix' ]
		);

		$retval = [];
		foreach ( $res as $row ) {
			$retval[] = (array)$row;
		}

		return $retval;
	}

	/**
	 * Return the list of interwiki fields that should be selected to create
	 * a new Interwiki object.
	 * @return string[]
	 */
	private static function selectFields() {
		return [
			'iw_prefix',
			'iw_url',
			'iw_api',
			'iw_wikiid',
			'iw_local',
			'iw_trans'
		];
	}
}

