<?php

require_once __DIR__ .'/../lib/PHP-SQL-Parser/vendor/autoload.php';

use \PHPSQLParser\PHPSQLParser as PHPSQLParser;
use \PHPSQLParser\PHPSQLCreator as PHPSQLCreator;

require __DIR__.'/../vendor/autoload.php';

use Elasticsearch\ClientBuilder;

class ELKParser
{
    public $parser = null;
    public $query_init = '';
    public $query_builed = '';
    public $tablename = '';
    public $table1 = '';
    private $conditions=null;
    private $fields=null;
    private $sorts=null;
    private $url = '';
    private $params=null;

    static public $client=null;

	public function __construct()
    {

    }

    public function getResultFromElk($query) {
	    if(!empty($query)) {

            $this->query_init = $query;
            try {
                $this->parser = new PHPSQLParser($query);
            }
            catch (Exception $e) {
                echo 'erreur requête :: '.$query;
                return false;
            }

            if($this->ifQuery() && !empty($this->parser->parsed['FROM'])) {
                $this->table1 = $this->parser->parsed['FROM'][0]['table'];
                $this->tablename = preg_replace('/^'.MAIN_DB_PREFIX.'/i','', $this->table1);

                if($this->isForbidden()) return false;

                // $this->revokeAlias(); //TODO useless ?

                $this->buildSimpleQuery();

                if(!$this->runELKQuery()) return false;

            }

        }

    }

    public function isForbidden() {
	    global $conf;

	    if(empty($conf)) return true;

	    if(in_array($this->tablename,['const','overwrite_trans'])) return true;

	    return false;

    }

    public function populateConditionQuery() {
        $this->conditions = [];

        if(!empty($this->parser->parsed['WHERE'])) {
            foreach ($this->parser->parsed['WHERE'] as $row) {
                if (!empty($row['no_quotes']['parts']) && count($row['no_quotes']['parts']) == 2) {
                    $this->conditions[]=$row['no_quotes']['parts'][1];
                } else if (!empty($row['no_quotes']['parts']) && count($row['no_quotes']['parts']) == 1) {
                    $this->conditions[]=$row['no_quotes']['parts'][0];
                }
            }
        }
    }

    public function populateSortQuery() {
        $this->sorts = [];

        if(!empty($this->parser->parsed['ORDER'])) {
            foreach ($this->parser->parsed['ORDER'] as $row) {
                if (!empty($row['no_quotes']['parts']) && count($row['no_quotes']['parts']) == 2) {
                    $this->sorts[]=$row['no_quotes']['parts'][1];
                } else if (!empty($row['no_quotes']['parts']) && count($row['no_quotes']['parts']) == 1) {
                    $this->sorts[]=$row['no_quotes']['parts'][0];
                }
            }
        }
    }
    public function populateSelectFieldQuery() {
        $this->fields = [];

        if(!empty($this->parser->parsed['SELECT'])) {
            foreach ($this->parser->parsed['SELECT'] as $row) {
                if (!empty($row['no_quotes']['parts']) && count($row['no_quotes']['parts']) == 2) {
                    $this->fields[]=$row['no_quotes']['parts'][1];
                } else if (!empty($row['no_quotes']['parts']) && count($row['no_quotes']['parts']) == 1) {
                    $this->fields[]=$row['no_quotes']['parts'][0];
                }
            }
        }

    }


    public function buildQuery() {

        $builder = new PHPSQLCreator($this->parser->parsed);
        $this->query_builed = $builder->created;

        return $this->query_builed ;
    }

    public function buildELKQuery() {

	        $this->url = '/'.$this->tablename.'/_search';

	        $params = [];
	        if(!empty($this->conditions)) {
	            $params['query'] = [];
	            foreach($this->conditions as &$c) {

                }
            }

	        $this->params=json_encode($params);


    }

    private function buildSimpleQuery() {
        $this->revokeAlias(true);
	    $this->revokeFrom($this->tablename);

	    return $this->buildQuery();
    }

    private function runELKQuery() {

        global $conf;

        if(empty(self::$client)) {

            $builder = ClientBuilder::create()->setSSLVerification(false);
            if (!empty($conf->global->ELK_HOSTS)) $builder->setHosts(explode(',', $conf->global->ELK_HOSTS));

            self::$client = $builder->build();
        }

        $curl = curl_init();
//if(strpos($this->query_builed,'user'))echo $this->query_builed;
        $params = array(
            'query'=>$this->query_builed
            ,'fetch_size'=>5
        );
        $auth_header = '';
        $content_header = "Content-Type:application/json";


        curl_setopt_array($curl, array(
            CURLOPT_HTTPHEADER=> array($auth_header,$content_header),
            CURLOPT_URL =>  (empty($conf->global->ELK_HOSTS) ? 'http://172.17.0.1:9200' : $conf->global->ELK_HOSTS) .'/_xpack/sql?format=json',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode( $params ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            return false;
        } else {
            $data = json_decode($response);
            if(!empty($data->error)) {
                return false;
            }
            else {
                var_dump($data);
            }
        }

    }

    private function revokeFrom($table) {

	    foreach($this->parser->parsed['FROM'] as $k=>$rowFrom) {

            if($rowFrom['table']!=$this->table1 || $rowFrom['expr_type']!='table') unset($this->parser->parsed['FROM'][$k]);

        }

        ksort($this->parser->parsed['FROM']);

        $this->parser->parsed['FROM'][0]['table'] = $this->tablename;
    }

    private function changeAlias(&$row, $removeAlias = false) {

	    if(($row['expr_type']=='colref' || $row['expr_type']=='table') /*&& $row['join_type']!='CROSS'*/) {

	        if($removeAlias) {

	            unset($row['alias'],$row['no_quotes']);

            }
            else{
                if (!empty($row['no_quotes']['parts']) && count($row['no_quotes']['parts']) == 2) {
                    $row['no_quotes']['parts'][0] = $this->TAlias[$row['no_quotes']['parts'][0]];
                } else if (!empty($row['no_quotes']['parts']) && count($row['no_quotes']['parts']) == 1) {
                    $row['no_quotes']['parts'][1] = $row['no_quotes']['parts'][0];
                    $row['no_quotes']['parts'][0] = $this->tablename;
                }

                if (empty($row['no_quotes']['delim'])) $row['no_quotes']['delim'] = '.';

                $row['base_expr'] = $row['no_quotes']['parts'][0] . $row['no_quotes']['delim'] . $row['no_quotes']['parts'][1];

            }
        }

        if(!empty($row['sub_tree'])) {
            foreach($row['sub_tree'] as &$subtree) {
                $this->changeAlias($subtree);

            }

        }

    }
    private function revokeAlias($removeAlias=false) {

        $this->TAlias = array();
        foreach($this->parser->parsed['FROM'] as $k=>&$rowFrom) {
            $table = $rowFrom['table'];
            $alias = preg_replace('/^'.MAIN_DB_PREFIX.'/i','',$table); /* 'al'.$k; */

            if($removeAlias) unset($rowFrom['alias']['name'] );
            else {
                //if(empty($rowFrom['alias']['name'])) $rowFrom['alias']['name'] = $table;
                @            $this->TAlias[$rowFrom['alias']['name']] = $alias;
                $rowFrom['alias']['name'] = $alias;

            }
        }

        foreach($this->parser->parsed['FROM'] as &$rowFrom) {
            $this->changeAlias($rowFrom, $removeAlias);
            if(!empty($rowFrom['ref_type']) && $rowFrom['ref_type'] == 'ON') {
                foreach ($rowFrom['ref_clause'] as &$clause) {
                    $this->changeAlias($clause, $removeAlias);
                }

            }

        }

        if(!empty($this->parser->parsed['SELECT'])) {
            foreach ($this->parser->parsed['SELECT'] as &$rowSelect) {
                $this->changeAlias($rowSelect, $removeAlias);

            }
        }
        if(!empty($this->parser->parsed['WHERE'])) {
            foreach ($this->parser->parsed['WHERE'] as &$rowWhere) {
                $this->changeAlias($rowWhere, $removeAlias);
            }
        }
        if(!empty($this->parser->parsed['ORDER'])) {
            foreach ($this->parser->parsed['ORDER'] as &$rowOrder) {
                $this->changeAlias($rowOrder, $removeAlias);
            }
        }
        if(!empty($this->parser->parsed['GROUP'])) {
            foreach ($this->parser->parsed['GROUP'] as &$rowOrder) {
                $this->changeAlias($rowOrder, $removeAlias);
            }
        }
    }

    public function ifQuery() {

	    return (!empty($this->parser->parsed['SELECT']));
    }

    public function getResult() {

	    return array();

    }

    public static function storeObject(&$object) {
        global $conf;

        if(empty(self::$client)) {

            $builder = ClientBuilder::create()->setSSLVerification(false);
            if (!empty($conf->global->ELK_HOSTS)) $builder->setHosts(explode(',', $conf->global->ELK_HOSTS));

            self::$client = $builder->build();
        }

        $params = [
            'index' => $object->table_element,
            'type' => $object->element,
            'id' => $object->id,
            'body' => (array) $object
        ];

        $response = self::$client->index($params); // TODO check response

    }


}
