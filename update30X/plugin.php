<?php
/*
Plugin Name: Update 30X
Plugin URI: https://github.com/joshp23/YOURLS-Update-30X
Description: Update a link if a 30X is returned
Version: 0.0.2
Author: Josh Panter
Author URI: https://unfettered.net
*/
// No direct call
if( !defined( 'YOURLS_ABSPATH' ) ) die();
/*
 *
 * Check a destination URL
 * @return url
 *
*/
function update30X_cURL( $url ) {
	$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT ,0); 
		curl_setopt($ch, CURLOPT_TIMEOUT, 3); // TODO Is this reasonable?
	$html = curl_exec($ch);
	$finalURL = curl_getinfo($ch,CURLINFO_EFFECTIVE_URL );
	curl_close($ch);
	return $finalURL;
}
/*
 *
 * API-Updates
 *
*/
yourls_add_filter( 'api_action_u30X', 'update30X_api' );
function update30X_api() {

	// only authorized users
	$auth = yourls_is_valid_user();
	if( $auth !== true ) {
		$format = ( isset($_REQUEST['format']) ? $_REQUEST['format'] : 'xml' );
		$callback = ( isset($_REQUEST['callback']) ? $_REQUEST['callback'] : '' );
		yourls_api_output( $format, array(
			'simple' => $auth,
			'message' => $auth,
			'errorCode' => 403,
			'callback' => $callback,
		) );
	}

	// checking a single keyword XXX
	if( isset ( $_REQUEST['keyword'] ) ) {
		$keyword = $_REQUEST['keyword'];
		$url = yourls_get_keyword_longurl( $keyword );
		if( $url ) {
			$scheme = parse_url( $url, PHP_URL_SCHEME);
			if( in_array( $scheme, array( 'http', 'https' ) ) ) {
			$check = update30X_cURL( $url );
				if( $check && ( $url !== $check ) ) {
					$update = update30X_alter_record( $url, $check );
					// success!
					if( $update ) {
						$return = array(
							'simple' 	=> 'Success: Link Updated',
							'message'	=> 'Success: Link Updated',
							'statusCode'=> 200,
							'newURL' 	=> $check
						);
					// fail
					} else {
						$return = array(
							'simple' 	=> 'Update failed',
							'message' 	=> 'Update failed',
							'statusCode'=> 500,
							'newURL' 	=> $check
						);
					}
				// no update required
				} else {
					$return = array(
						'simple' 	=> 'No update Required',
						'message' 	=> 'No update Required',
						'statusCode'=> 200
					);
				}
			} else {
				// no valid link scheme found
				$return = array(
					'simple' 	=> 'Invalid link type',
					'message' 	=> 'Invalid link type',
					'statusCode'=> 501,
					'scheme' 	=> $scheme
				);
			}	
		} else {
			// No URL for KW
			$return = array(
				'simple' 	=> 'URL not found: check the keyword',
				'message' 	=> 'URL not found: check the keyword',
				'statusCode'=> 404
			);
		}
		return $return;
	}

	// Prepare the list of links
	global $ydb;
	$table = YOURLS_DB_TABLE_URL;

	// Checking for single domain restriction XXX
	if( isset ( $_REQUEST['domain'] ) ) {
		$domain = $_REQUEST['domain'];
		$where = "`url` LIKE '%$domain%'";
	} else {
		$where = "1=1";
	}
	// version check for YOURLS DB connctor
	if (version_compare(YOURLS_VERSION, '1.7.3') >= 0) {
		$sql = "SELECT * FROM `$table` WHERE $where";
		$links = $ydb->fetchObjects($sql);
	} else {
		$links = $ydb->get_results("SELECT * FROM `$table` $where");
	}

	// do the thing! XXX
	if( $links ) {
		$r=$s=$f=0;
		foreach( $links as $link ) {
			$url = $link->url;
			$check = update30X_cURL( $url );
				$dead = array();
			if( $check ) {
				if( $url !== $check ) {
					$r++;
					$update = update30X_alter_record( $url, $check );
					if( $update ) 
						$s++;
					else
						$f++;
				}
			} else { 
				$dead[] = $link;
			}
		}
		$return['updatesRequired'] = $r;
		$return['successfull'] = $s;
		$return['failed'] = $f;
		$return['deadLinks'] = $dead;
	} else {
		$return['error'] = 'Database connection failure';
		$return['code'] = 500;
	}
	return $return;
}
/*
 *
 * Do a single record update
 * @return bool
 *
*/
// Do a single record update
function update30X_alter_record( $old, $new ) {
	global $ydb;
	$table = YOURLS_DB_TABLE_URL;
	$update = null;
	if (version_compare(YOURLS_VERSION, '1.7.3') >= 0) {
		$binds = array('old' => $old, 'new' => $new);
		$sql = "UPDATE `$table` SET `url` = REPLACE(`url`, :old, :new) WHERE `url` = :old";
		$update = $ydb->fetchAffected($sql, $binds);
	} else {
		$update = $ydb->query("UPDATE `$table` SET `url` = REPLACE(`url`, '$old', '$new') WHERE `url` = '$old'");
	}
	return $update;
}
/*
 *
 * Index the URL table in YOURLS DB on activation
 * @return url
 *
*/
yourls_add_action( 'activated_update30X/plugin.php', 'update30X_activated' );
function update30X_activated() {

	global $ydb;
	$table = YOURLS_DB_TABLE_URL;
	$index = 'idx_urls';

	if (version_compare(YOURLS_VERSION, '1.7.3') >= 0) {
		$binds = array('index' => $index);
		$sql = "SHOW INDEX FROM  `$table`  WHERE Key_name = :index";
		$query = $ydb->fetchAffected($sql, $binds);
		if( $query == null ) {
			$sql = "ALTER TABLE `$table` ADD INDEX :index (`url`(30)";
			$query = $ydb->fetchAffected($sql, $binds);
		}
	} else {
		$query = $ydb->query("SHOW INDEX FROM `$table` WHERE Key_name = $index");
		if( $query == null )
			$query = $ydb->query("ALTER TABLE `$table` ADD INDEX `$index` (`url`(30)");
	}
	if ($query === false)
		echo "Unable to properly index URL table. Please see README";
}
