<?php 
	$single = new CasaWp\Single($post);
	$prevNext = $single->getPrevNext($_GET['query']);
    echo json_encode($prevNext);
?>


