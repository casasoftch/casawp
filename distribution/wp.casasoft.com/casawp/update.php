<?php
/**
 * The remote host file to process update requests.
 *
 */

if ( !isset( $_POST['action'] ) ) {
	echo '0';
	exit;
}

//set up the properties common to both requests 
$obj = new stdClass();
$obj->slug = 'casawp';  
$obj->name = 'CASAWP';
$obj->plugin_name = 'casawp';
$obj->new_version = '3.5.2';
$obj->requires_php = '8.1';
// the url for the plugin homepage
$obj->url = 'https://immobilien-plugin.ch';
//the download location for the plugin zip file (can be any internet host)
//$obj->package = 'https://github.com/CasasoftCH/casawp/archive/release/2.0.3.zip';
$obj->package = 'https://wp.casasoft.com/casawp/latest.zip';

switch ( $_POST['action'] ) {

case 'version':  
	echo serialize( $obj );
	break;  
case 'info':   
	$obj->requires = '4.0';  
	$obj->tested = '7.1.3';
	$obj->downloaded = 12540;  
	$obj->last_updated = '2026-10-14';
	$obj->sections = array(  
		'description' => 'The newest version of the CASAWP plugin',  
		'changelog' => '<h4>3.5.2</h4><ul><li>Cache failed remote media downloads across properties and languages.</li><li>Apply explicit remote media download timeouts and log failure status, duration, and retry cooldown.</li><li>Discard failed or empty downloads before they can become local media files.</li></ul>'
	);
	$obj->download_link = $obj->package;  
	echo serialize($obj);  
	break;
case 'license':  
	echo serialize( $obj );  
	break;  
}  

?>
