<?php

function elkAdminPrepareHead()
{
    global $langs, $conf;

    $langs->load("elk@elk");

    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath("/elk/admin/elk_setup.php", 1);
    $head[$h][1] = $langs->trans("Parameters");
    $head[$h][2] = 'settings';
    $h++;

    complete_head_from_modules($conf, $langs, $object, $head, $h, 'elk');

    return $head;
}

/**
 * Return array of tabs to used on pages for third parties cards.
 *
 * @param 	TELK	$object		Object company shown
 * @return 	array				Array of tabs
 */
function elk_prepare_head($str)
{
    global $langs, $conf;
    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath('/elk/search.php?keyword='.urlencode($str), 1);
    $head[$h][1] = $langs->trans('ELKSearch');
    $head[$h][2] = 'search';
    $h++;

    complete_head_from_modules($conf,$langs,$object,$head,$h,'elk');
	
	return $head;
}
