<?php

require 'config.php';


require 'vendor/autoload.php';
        
use Elasticsearch\ClientBuilder;

$client = ClientBuilder::create()->build();
        

$params = [
   /* 'index' => 'societe',
    'type' => 'societe',*/
    'body' => [
        'query' => [
            'query_string' => [
                'query' => '26000'
            ]
        ]
    ]
];

llxHeader();

$results = $client->search($params);


if($results['hits']['total']>0) {


	foreach($results['hits']['hits'] as $r) {
		$cn = UCFirst($r['_index']);
		$o=new $cn($db);

		foreach($r['_source'] as $k=>$v) {
			
			if(!is_object($o->$k)) {
				try {
				 $o->$k = $v;
				}
				catch(Exception $e) {

				}
			}

		}
	
		echo $o->getNomUrl(1);

	}

}

llxFooter();
