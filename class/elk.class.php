<?php

require_once __DIR__ .'/../lib/PHP-SQL-Parser/vendor/autoload.php';

use \PHPSQLParser\PHPSQLParser as PHPSQLParser;
use \PHPSQLParser\PHPSQLCreator as PHPSQLCreator;

require __DIR__.'/../vendor/autoload.php';

use Elasticsearch\ClientBuilder;
use Santik\SqlElasticSearchQueryConverter\SqlElasticSearchQueryConverter;

class ELKResult {

    private $results = null;

    function __construct($data)
    {
        $this->results = $data;
    }

    public function fetch_object() {
        if(empty($this->results['hits']['hits'])) return false;

        $result = json_decode( json_encode( (object) ((array_shift($this->results['hits']['hits'] ))['_source']) ) );

        return $result;
    }

    public function fetch_array() {
        if(empty($this->results['hits']['hits'])) return false;

        $result = array_shift($this->results['hits']['hits']);
        return $result['_source'];
    }
    public function free_result() {
        $this->results = null;
    }
}

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
    private $results=null;

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

                if(!$this->runELKSQLQuery()) return false;

                return true;
            }

            return false;
        }

    }

    public function isForbidden() {
	    global $conf;

	    if(empty($conf)) return true;

	    if(in_array($this->tablename,['const','overwrite_trans','user_rights','usergroup_rights'])) return true;

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
        $this->revokeSelect();
        //unset($this->parser->parsed['ORDER'], $this->parser->parsed['GROUP']);
	    return $this->buildQuery();
    }

    private function runELKQuery() {
        /*$query = $this->buildSimpleQuery();
        exit($query);
        $query = substr($this->buildSimpleQuery(), strpos($query,'WHERE'));

        $esQuery = SqlElasticSearchQueryConverter::convert($query, 'field');
        print_r($esQuery);
        exit;

        $index = $this->parser->parsed['FROM'][0]['table'];

        $filter=array();
        foreach($this->parser->parsed['WHERE'] as &$w) {
            if($w['expr_type']=='colref') {
                $colname = $w['base_expr'];
                unset($type, $values);
            }
            elseif($w['expr_type']=='operator') {
                if( $w['base_expr'] == 'IN') $type='terms';
                else if( $w['base_expr'] == '=') $type='terms';
                if( $w['base_expr'] == 'LIKE') $type='match';
            }
            elseif($w['expr_type']=='in-list') {
                $values = array();
                foreach($w['sub_tree'] as &$row) {
                    $values[]=$row['base_expr'];
                }
            }
            elseif($w['expr_type']=='const') {
                $values = array();
                $values[] = substr($w['base_expr'],1,-1);
            }

            if(isset($colname, $type, $values)) {
                if(empty($filter[$type]))$filter[$type]=array();
                $filter[$type][$colname] = $values;
            }
        }
*/
        $curl = curl_init();
//if(strpos($this->query_builed,'user'))echo $this->query_builed;
        $params = array(
            'query'=>array('bool'=>array('filter'=>$filter))
        );
        $auth_header = '';
        $content_header = "Content-Type:application/json";


        curl_setopt_array($curl, array(
            CURLOPT_HTTPHEADER=> array($auth_header,$content_header),
            CURLOPT_URL =>  (empty($conf->global->ELK_HOSTS) ? 'http://172.17.0.1:9200' : $conf->global->ELK_HOSTS) .'/'.$index.'/_search',
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
                print_r($params);
                var_dump(json_encode( $params ), $data);
                exit;
                return false;
            }
            else {
             //   var_dump($params,$data);
                //TODO return values
                return false;
            }
        }
    }

    private function runELKSQLQuery() {

        global $conf;

        $this->buildSimpleQuery();
/*
        if(empty(self::$client)) {

            $builder = ClientBuilder::create()->setSSLVerification(false);
            if (!empty($conf->global->ELK_HOSTS)) $builder->setHosts(explode(',', $conf->global->ELK_HOSTS));

            self::$client = $builder->build();
        }
*/
        $curl = curl_init();
//if(strpos($this->query_builed,'user'))echo $this->query_builed;
        $params = array(

        );
        $auth_header = '';
        $content_header = "Content-Type:application/json";

        $url = (empty($conf->global->ELK_HOSTS) ? 'http://172.17.0.1:9200' : $conf->global->ELK_HOSTS) .'/_sql/?sql='.urlencode($this->query_builed);

        curl_setopt_array($curl, array(
            CURLOPT_HTTPHEADER=> array($auth_header,$content_header),
            CURLOPT_URL =>  $url,
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
            $data = json_decode($response, true);
//var_dump($data,$this->query_builed);
            if(!empty($data->error) || empty($data->hits->hits)) {

                return false;
            }
            else {

                $this->results = new ELKResult($data) ;

                return true;
            }
        }

    }

    private function revokeSelect() {
        $this->parser->parsed['SELECT']=array(
            array(
                'expr_type' => 'colref',
                'alias' => null,
                'base_expr' => '*',
                'sub_tree' => null,
                'delim' => null,

            )
        );

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
                if (!empty($row['no_quotes']['parts']) && count($row['no_quotes']['parts']) == 2) {
                    $row['base_expr'] = $row['no_quotes']['parts'][1];
                }
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
                @$this->TAlias[$rowFrom['alias']['name']] = $alias;
                $rowFrom['alias']['name'] = $alias;

            }
        }

        $types = array('FROM','SELECT','WHERE','ORDER','GROUP');
        foreach($types as $type) {
            if(!empty($this->parser->parsed[$type])) {
                foreach ($this->parser->parsed[$type] as &$row) {
                    $this->changeAlias($row, $removeAlias);
                    if (!empty($row['ref_type']) && $row['ref_type'] == 'ON' && !empty($row['ref_clause'])) {
                        foreach ($row['ref_clause'] as &$clause) {
                            $this->changeAlias($clause, $removeAlias);
                        }
                    }
                }
            }
        }

    }

    public function ifQuery() {

	    return (!empty($this->parser->parsed['SELECT']));
    }

    public function getResult() {

	    return $this->results;

    }

    public static function fetch(&$object, $id) {
        global $conf;

        if(empty(self::$client)) {

            $builder = ClientBuilder::create()->setSSLVerification(false);
            if (!empty($conf->global->ELK_HOSTS)) $builder->setHosts(explode(',', $conf->global->ELK_HOSTS));

            self::$client = $builder->build();
        }

        $params = [
            'index'=>$object->table_element
            ,'type'=>$object->element
            ,'id'=>$id
        ];

        try {
            $response = self::$client->get($params);
        }
        catch(Exception $e) {
           null;
        }

        if(empty($response['_source'])) return false;
        else return $response['_source'];

    }

    public static function objectToObject(&$instance, $className) {

	    $o=new $className($instance->db);
	    foreach($instance as $k=>$v) {
	        $o->{$k} = $v;
        }

        return $o;

	    /*return unserialize(sprintf(
            'O:%d:"%s"%s',
            strlen($className),
            $className,
            strstr(strstr(serialize($instance), '"'), ':')
        ));*/
    }

    public static function setObjectByStorage(&$object, &$row) {

        foreach($row as $k=>$v) {
            if((is_object($object) && property_exists($object,$k))
                || (is_array($object) && array_key_exists($k, $object))) {

                if(is_object($object->{$k}) || is_array($object->{$k})) self::setObjectByStorage($object->{$k}, $row[$k]);
                else if(is_object($object)) {
                    $object->{$k} = $row[$k];
                }
                else if(is_array($object)) {
                    $object[$k] = $row[$k];
                }
            }
        }


    }



    public static function storeObject($object) {
        global $conf;

        if(empty(self::$client)) {

            $builder = ClientBuilder::create()->setSSLVerification(false);
            if (!empty($conf->global->ELK_HOSTS)) $builder->setHosts(explode(',', $conf->global->ELK_HOSTS));

            self::$client = $builder->build();
        }

        $data = self::cleanData($object);
        $data = self::completeNeededData($data);

        $params = [
            'index' => $object->table_element,
            'type' => $object->element,
            'id' => $object->id,
            'body' => $data
        ];

        $response = self::$client->index($params); // TODO check response

    }

    private static function completeNeededData($data) {
	    //TODO complete date in an object to answer all request

        if(!empty($data['newref']))$data['ref'] = $data['newref'];

        return $data;
    }

    private static function cleanData($object) {
        $removes = ['db', 'fields','default_range','oldcopy','restrictiononfksoc','childtables','fieldsforcombobox','childtablesoncascade','linked_objects'];
        $data = array();
        foreach($object as $k=>&$v) {
            if(in_array($k, $removes)) null;
            else if(is_object($v)) $data[$k] = self::cleanData($v);
            else $data[$k] = $v;
        }

        return $data;
    }

}
