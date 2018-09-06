<?php

require_once __DIR__ .'/../lib/PHP-SQL-Parser/vendor/autoload.php';

use \PHPSQLParser\PHPSQLParser as PHPSQLParser;
use \PHPSQLParser\PHPSQLCreator as PHPSQLCreator;

class TELKParser
{
    var $parser = null;
    var $query_init = '';
    var $query_builed = '';
    var $tablename = '';
    var $table1 = '';
    var $conditions=null;
    var $fields=null;
    var $sorts=null;

	public function __construct($query)
	{

        $this->query_init = $query;
        $this->parser = new PHPSQLParser($query, true);

        if($this->ifQuery() && !empty($this->parser->parsed['FROM'])) {
            $this->table1 = $this->parser->parsed['FROM'][0]['table'];
            $this->tablename = preg_replace('/^'.MAIN_DB_PREFIX.'/i','', $this->table1);
            $this->revokeAlias(); //TODO useless ?
            $this->populateConditionQuery();
            $this->populateSelectFieldQuery();

            $this->buildQuery();
echo '<br />'.$this->query_builed .'<br><br>';
            print_r($this->parser->parsed);

            exit($this->tablename);
        }

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


    }
    private function changeAlias(&$row) {

	    if(($row['expr_type']=='colref' || $row['expr_type']=='table') /*&& $row['join_type']!='CROSS'*/) {

            if (!empty($row['no_quotes']['parts']) && count($row['no_quotes']['parts']) == 2) {
                $row['no_quotes']['parts'][0] = $this->TAlias[$row['no_quotes']['parts'][0]];
            } else if (!empty($row['no_quotes']['parts']) && count($row['no_quotes']['parts']) == 1) {
                $row['no_quotes']['parts'][1] = $row['no_quotes']['parts'][0];
                $row['no_quotes']['parts'][0] = $this->tablename;
            }

            if (empty($row['no_quotes']['delim'])) $row['no_quotes']['delim'] = '.';

            $row['base_expr'] = $row['no_quotes']['parts'][0] . $row['no_quotes']['delim'] . $row['no_quotes']['parts'][1];
        }

        if(!empty($row['sub_tree'])) {
            foreach($row['sub_tree'] as &$subtree) {
                $this->changeAlias($subtree);

            }

        }

    }
    private function revokeAlias() {
        echo $this->query_init.'<br>';

        $this->TAlias = array();
        foreach($this->parser->parsed['FROM'] as $k=>&$rowFrom) {
            $table = $rowFrom['table'];
            $table_crypt = preg_replace('/^'.MAIN_DB_PREFIX.'/i','',$table); /* 'al'.$k; */

            //if(empty($rowFrom['alias']['name'])) $rowFrom['alias']['name'] = $table;
            $this->TAlias[$rowFrom['alias']['name']] = $table_crypt;
            $rowFrom['alias']['name'] = $table_crypt;
        }

        foreach($this->parser->parsed['FROM'] as &$rowFrom) {
            $this->changeAlias($rowFrom);
            if(!empty($rowFrom['ref_type']) && $rowFrom['ref_type'] == 'ON') {
                foreach ($rowFrom['ref_clause'] as &$clause) {
                    $this->changeAlias($clause);
                }


            }

        }

        if(!empty($this->parser->parsed['SELECT'])) {
            foreach ($this->parser->parsed['SELECT'] as &$rowSelect) {
                $this->changeAlias($rowSelect);

            }
        }
        if(!empty($this->parser->parsed['WHERE'])) {
            foreach ($this->parser->parsed['WHERE'] as &$rowWhere) {
                $this->changeAlias($rowWhere);
            }
        }
        if(!empty($this->parser->parsed['ORDER'])) {
            foreach ($this->parser->parsed['ORDER'] as &$rowOrder) {
                $this->changeAlias($rowOrder);
            }
        }
        if(!empty($this->parser->parsed['GROUP'])) {
            foreach ($this->parser->parsed['GROUP'] as &$rowOrder) {
                $this->changeAlias($rowOrder);
            }
        }
    }

    public function ifQuery() {

	    return (!empty($this->parser->parsed['SELECT']));
    }

    public function getResult() {

	    return array();

    }


}
