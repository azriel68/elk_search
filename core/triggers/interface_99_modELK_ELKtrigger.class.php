<?php


require __DIR__.'/../../vendor/autoload.php';

use Elasticsearch\ClientBuilder;


/**
 * Trigger class
 */
class InterfaceELKtrigger
{

    private $db;

    /**
     * Constructor
     *
     * 	@param		DoliDB		$db		Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;

        $this->name = preg_replace('/^Interface/i', '', get_class($this));
        $this->family = "demo";
        $this->description = "Triggers of this module are empty functions."
            . "They have no effect."
            . "They are provided for tutorial purpose only.";
        // 'development', 'experimental', 'dolibarr' or version
        $this->version = 'development';
        $this->picto = 'elk@elk';
    }

    /**
     * Trigger name
     *
     * 	@return		string	Name of trigger file
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Trigger description
     *
     * 	@return		string	Description of trigger file
     */
    public function getDesc()
    {
        return $this->description;
    }

    /**
     * Trigger version
     *
     * 	@return		string	Version of trigger file
     */
    public function getVersion()
    {
        global $langs;
        $langs->load("admin");

        if ($this->version == 'development') {
            return $langs->trans("Development");
        } elseif ($this->version == 'experimental')

                return $langs->trans("Experimental");
        elseif ($this->version == 'dolibarr') return DOL_VERSION;
        elseif ($this->version) return $this->version;
        else {
            return $langs->trans("Unknown");
        }
    }

    /**
     * Function called when a Dolibarrr business event is done.
     * All functions "run_trigger" are triggered if file
     * is inside directory core/triggers
     *
     * 	@param		string		$action		Event action code
     * 	@param		Object		$object		Object
     * 	@param		User		$user		Object user
     * 	@param		Translate	$langs		Object langs
     * 	@param		conf		$conf		Object conf
     * 	@return		int						<0 if KO, 0 if no triggered ran, >0 if OK
     */
    public function run_trigger($action, $object, $user, $langs, $conf)
    {
        // Put here code you want to execute when a Dolibarr business events occurs.
        // Data and type of action are stored into $object and $action
        // Users

	if ($action == 'USER_LOGIN' || empty($object->element) || empty($object->table_element) ) {
		return 0;
	}	
        
        $client = ClientBuilder::create()->build();
        
        $params = [
            'index' => $object->table_element,
            'type' => $object->element,
            'id' => $object->id,
            'body' => (array) $object
        ];

	unset($params['body']['db']);
	foreach($params['body'] as $k=>$v) {
		if($k[0]=='*') unset($params['body'][$k]);
	}
	
// var_dump($params);        
        // Document will be indexed to my_index/my_type/my_id
        $response = $client->index($params);
/*
	unset($params['body']);
	$response = $client->get($params);
var_dump($response);exit;
*/

        return 0;
    }
}