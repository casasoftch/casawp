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
$obj->new_version = '3.4.5';
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
	$obj->tested = '6.9.4';  
	$obj->downloaded = 12540;  
	$obj->last_updated = '2026-05-06';  
	$obj->sections = array(  
		'description' => 'The newest version of the CASAWP plugin',  
		'changelog' => 'See Readme'  
	);
	$obj->download_link = $obj->package;  
	echo serialize($obj);  
case 'license':  
	echo serialize( $obj );  
	break;  
}  

?>
