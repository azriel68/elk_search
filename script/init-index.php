<?php

	set_time_limit(0);

	require '../config.php';
	dol_include_once('/elk/class/elk.class.php');

	$res = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."societe");
	while($obj = $db->fetch_object($res)) {
		$object=new Societe($db);
		$object->fetch($obj->rowid);
		ELKParser::storeObject($object);


	}