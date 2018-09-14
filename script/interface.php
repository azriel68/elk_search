<?php

	require('../config.php');

    require '../vendor/autoload.php';

    use Elasticsearch\ClientBuilder;

	dol_include_once('/product/class/product.class.php');
	dol_include_once('/societe/class/societe.class.php');
	dol_include_once('/comm/propal/class/propal.class.php');
	dol_include_once('/projet/class/project.class.php');
	dol_include_once('/projet/class/task.class.php');
	dol_include_once('/comm/action/class/actioncomm.class.php');
	dol_include_once('/compta/facture/class/facture.class.php');
	dol_include_once('/commande/class/commande.class.php');
	dol_include_once('/fourn/class/fournisseur.commande.class.php');
	dol_include_once('/expedition/class/expedition.class.php');
    dol_include_once('/contrat/class/contrat.class.php');

	$langs->load('searcheverywhere@searcheverywhere');
	$langs->load('orders');

	$get = GETPOST('get');

    $builder = ClientBuilder::create()->setSSLVerification(false);
    if (!empty($conf->global->ELK_HOSTS)) $builder->setHosts(explode(',', $conf->global->ELK_HOSTS));

    $client = $builder->build();

	switch ($get) {
		case 'search':

			_search($client,GETPOST('type'), GETPOST('keyword'));

			break;

		case 'search-all':

		    $TObjectType=array('product','company','contact','event','contrat');
		    $conf->global->SEARCHEVERYWHERE_NB_ROWS = 5;
		    $TResult=array();
		    foreach($TObjectType as $type) {

		        $TResult[$langs->transnoentities(ucfirst($type))] = _search($client,$type, GETPOST('keyword'), true);

		    }

		    echo json_encode($TResult);

		default:

			break;
	}



function _search(&$client, $type, $keyword, $asArray=false) {
	global $db, $conf, $langs;

	$index = $type;
	$objname = ucfirst($type);
	$id_field = 'rowid';
	$complete_label = true;
	$show_find_field = false;
	$sql_join = '';

	$TResult=array();

	if($type == 'company') {
		$index = 'societe';
		$objname = 'Societe';
		$complete_label = false;
	}
	elseif($type == 'contrat') {
		$index = 'contrat';
		$objname = 'Contrat';
	}
	elseif($type == 'task') {
		$index = 'projet_task';

	}
	elseif($type == 'event') {
		$index = 'actioncomm';
		$objname = 'ActionComm';
		$id_field = 'id';
		$complete_label = false;
	}
	elseif($type == 'contact') {
		$index = 'socpeople';

		$complete_label = false;
	}

	$o=new $objname($db);

    $params = [
        'index'=>$index
        ,'type'=>$o->element
        ,'body' => [
            'query' => [
                'query_string' => [
                    'query' => $keyword
                ]
            ]
        ]
    ];

    if(!empty($conf->global->SEARCHEVERYWHERE_SEARCH_ONLY_IN_ENTITY)) $params['query']['bool']['term']['entity'] = $conf->entity;

    /*if(!empty($conf->global->SEARCHEVERYWHERE_NB_ROWS)) $sql.= 'LIMIT '.$conf->global->SEARCHEVERYWHERE_NB_ROWS;
	else $sql.= 'LIMIT 20 ';
*/
    try {
        $results = $client->search($params);
        $nb_results = !empty($results['error']) ? 0 : $results['hits']['total'];

    }
    catch(Exception $e) {
        exit(''); // on ne rend aucun résultat
    }

//var_dump($results,$params);

	$libelle = ucfirst($objname);
	if($objname == 'CommandeFournisseur') $libelle = 'SupplierOrder';

	if(!$asArray) print '<table class="border" width="100%"><tr class="liste_titre"><td colspan="2">'.$langs->trans( $libelle ).' <span class="badge">'.$nb_results.'</span></td></tr>';

	if($nb_results == 0) {
	    if(!$asArray) 	print '<td colspan="2">'.$langs->trans('NoResult').'</td>';
	}
	else{
		foreach($results['hits']['hits'] as &$row) {

			$o=new $objname($db);
            _setObjectByStorage($o, $row['_source']);
			if($o->id<=0) continue;

			$label = '';
			if($complete_label) {
				if(empty($label) && !empty( $o->label )) $label = $o->label;
				else if(empty($label) && !empty( $o->name )) $label = $o->name;
				else if(empty($label) && !empty( $o->nom )) $label = $o->nom;
				else if(empty($label) && !empty( $o->title )) $label = $o->title;
				else if(empty($label) && !empty( $o->ref )) $label = $o->ref;
				else if(empty($label) && !empty( $o->id)) $label = $o->id;
			}

			if(method_exists($o, 'getNomUrl')) {
				$label = trim($o->getNomUrl(1).' '.$label);
			}

			if(method_exists($o, 'getLibStatut')) {
				$statut = $o->getLibStatut(3);

			}

			$desc = '';

			if($show_find_field) {
				foreach($o as $k=>$v) {
					if(is_string($v) && preg_match("/" . $keyword . "/", $v)) {
						$desc .= '<br />'.$k.' : '.preg_replace("/" . $keyword . "/", "<span class='highlight'>" . $keyword . "</span>", $v);
					}


				}

			}

			preg_match_all('/<a[^>]+href=([\'"])(?<href>.+?)\1[^>]*>/i', $label, $match);

			$url = is_array($match['href']) ? $match['href'][0] : $match['href'];

			if($asArray) {
			    $TResult[] = array(
			        'label'=>$label
			        ,'label_clean'=>strip_tags($label)
			        ,'url'=>$url
			        ,'desc'=>$desc
			        ,'statut'=>$statut
			    );
			}
			else {

    			print '<tr>
    				<td>'.$label.$desc.'</td>
    				<td align="right">'.$statut.'</td>
    			</tr>';
			}

		}

	}


	if(!$asArray) print '</table>';
    else return $TResult;
}
function _setObjectByStorage(&$object, $row) {

    foreach($row as $k=>$v) {
        if((is_object($object) && property_exists($object,$k))
            || (is_array($object) && array_key_exists($k, $object))) {

            if(is_object($object->{$k}) || is_array($object->{$k})) _setObjectByStorage($object->{$k}, $row[$k]);
            else if(is_object($object)) {
                $object->{$k} = $row[$k];
            }
            else if(is_array($object)) {
                $object[$k] = $row[$k];
            }
        }
    }



}