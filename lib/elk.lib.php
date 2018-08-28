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
    $head[$h][0] = dol_buildpath("/elk/admin/elk_about.php", 1);
    $head[$h][1] = $langs->trans("About");
    $head[$h][2] = 'about';
    $h++;

    // Show more tabs from modules
    // Entries must be declared in modules descriptor with line
    //$this->tabs = array(
    //	'entity:+tabname:Title:@elk:/elk/mypage.php?id=__ID__'
    //); // to add new tab
    //$this->tabs = array(
    //	'entity:-tabname:Title:@elk:/elk/mypage.php?id=__ID__'
    //); // to remove a tab
    complete_head_from_modules($conf, $langs, $object, $head, $h, 'elk');

    return $head;
}

/**
 * Return array of tabs to used on pages for third parties cards.
 *
 * @param 	TELK	$object		Object company shown
 * @return 	array				Array of tabs
 */
function elk_prepare_head(TELK $object)
{
    global $db, $langs, $conf, $user;
    $h = 0;
    $head = array();
    $head[$h][0] = dol_buildpath('/elk/card.php', 1).'?id='.$object->getId();
    $head[$h][1] = $langs->trans("ELKCard");
    $head[$h][2] = 'card';
    $h++;
	
	// Show more tabs from modules
    // Entries must be declared in modules descriptor with line
    // $this->tabs = array('entity:+tabname:Title:@elk:/elk/mypage.php?id=__ID__');   to add new tab
    // $this->tabs = array('entity:-tabname:Title:@elk:/elk/mypage.php?id=__ID__');   to remove a tab
    complete_head_from_modules($conf,$langs,$object,$head,$h,'elk');
	
	return $head;
}

function getFormConfirm(&$PDOdb, &$form, &$object, $action)
{
    global $langs,$conf,$user;

    $formconfirm = '';

    if ($action == 'validate' && !empty($user->rights->elk->write))
    {
        $text = $langs->trans('ConfirmValidateELK', $object->ref);
        $formconfirm = $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('ValidateELK'), $text, 'confirm_validate', '', 0, 1);
    }
    elseif ($action == 'delete' && !empty($user->rights->elk->write))
    {
        $text = $langs->trans('ConfirmDeleteELK');
        $formconfirm = $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('DeleteELK'), $text, 'confirm_delete', '', 0, 1);
    }
    elseif ($action == 'clone' && !empty($user->rights->elk->write))
    {
        $text = $langs->trans('ConfirmCloneELK', $object->ref);
        $formconfirm = $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('CloneELK'), $text, 'confirm_clone', '', 0, 1);
    }

    return $formconfirm;
}
