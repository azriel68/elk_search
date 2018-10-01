<?php

/**
 * \file    class/actions_elk.class.php
 * \ingroup elk
 * \brief   This file is an example hook overload class file
 *          Put some comments here
 */

/**
 * Class ActionsELK
 */
class ActionsELK
{
	/**
	 * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;

	/**
	 * @var array Errors
	 */
	public $errors = array();

	/**
	 * Constructor
	 */
	public function __construct()
	{
	}

	public function setHtmlTitle($parameters, &$object, &$action, $hookmanager) {

        //TODO PHP7, replace override message by runkit_method_redefine

        dol_include_once('/elk/class/elk.class.php');

        if(GETPOST('legacyObject') || preg_match('/edit/',$action) || preg_match('/confirm/',$action)) return 0;
        else {

            $object = ELKParser::objectRedefineFetch('Societe','/elk/class/societe.class.php');
            $object = ELKParser::objectRedefineFetch('Client','/elk/class/societe.class.php');
            $object = ELKParser::objectRedefineFetch('Contact','/elk/class/contact.class.php');
            $object = ELKParser::objectRedefineFetch('Contrat','/elk/class/contrat.class.php');
        }

        return 0;

    }

	/**
	 * Overloading the doActions function : replacing the parent's function with the one below
	 *
	 * @param   array()         $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    &$object        The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          &$action        Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	function beforeFetch($parameters, &$object, &$action, $hookmanager)
	{

	    dol_include_once('/elk/class/elk.class.php');
        $classname = get_class($object);

        if(GETPOST('legacyObject') || preg_match('/edit/',$action) || preg_match('/confirm/',$action)) return 0;
	    else if(empty($object->element)) return 0;
	    else if($classname == 'Societe') {
            dol_include_once('/elk/class/societe.class.php');
            $object = ELKParser::objectToObject($object,'ELKSociete');
        }
        else if($classname == 'Client') {
            dol_include_once('/elk/class/societe.class.php');
            $object = ELKParser::objectToObject($object,'ELKClient');
        }
        else if($classname == 'Contact') {
            dol_include_once('/elk/class/contact.class.php');
            $object = ELKParser::objectToObject($object,'ELKContact');
        }
        else if($classname == 'Contrat') {
            dol_include_once('/elk/class/contrat.class.php');
            $object = ELKParser::objectToObject($object,'ELKContrat');
        }
        else {
            //exit(get_class($object));
        }

        return 0;

	}

	function doActions($parameters, &$object, &$action, $hookmanager) {
        return $this->beforeFetch($parameters, $object, $action, $hookmanager);
    }


    function printSearchForm($parameters, &$object, &$action, $hookmanager) {
        global $langs,$db,$conf;

        if (in_array('searchform',explode(':',$parameters['context'])))
        {
            $langs->load('elk@elk');

            $res = '';

            $res.='<form method="post" action="'.dol_buildpath('/elk/search.php',1).'">';
            $res.= '<div class="menu_titre menu_titre_search"><label for="sew_keyword"><a class="vsmenu" href="'.dol_buildpath('/elk/search.php',1).'">'.img_object($langs->trans('elk'),'elk@elk').' '.$langs->trans('ELKSearch').'</a></label></div>';
            $res.= '	<input type="text" size="10" name="keyword" title="'.$langs->trans('Keyword').'" class="flat" id="sew_keyword" /><input type="submit" value="'.$langs->trans('Go').'" class="button">
				</form>';

            $res.= '<script type="text/javascript">
            $("#sew_keyword").autocomplete({
                      source: function( request, response ) {
                        $.ajax({
                          url: "'.dol_buildpath('/elk/script/interface.php',1).'",
                          dataType: "json",
                          data: {
                            keyword: request.term
                            ,get:"search-all"
                          }
                          ,success: function( data ) {
                              var c = [];
                              $.each(data, function (i, cat) {

                                var first = true;
                                $.each(cat, function(j, obj) {

                                    if(first) {
                                        c.push({value:i, label:i, object:"title"});
                                        first = false;
                                    }

                                    c.push({ value: j, label:"  "+obj.label_clean, url:obj.url, desc:"  "+obj.desc, object:i});

                                });


                              });

                              response(c);



                          }
                        });
                      },
                      minLength: 1,
                      select: function( event, ui ) {

                            if(ui.item.url) {
                                document.location.href = ui.item.url;
                            }

                            return false;

                      },
                      open: function( event, ui ) {
                        $( this ).removeClass( "ui-corner-all" ).addClass( "ui-corner-top" );
                      },
                      close: function() {
                        $( this ).removeClass( "ui-corner-top" ).addClass( "ui-corner-all" );
                      }
             });

            $( "#sew_keyword" ).autocomplete( "instance" )._renderItem = function( ul, item ) {

                          $li = $( "<li style=\"white-space: nowrap;\" />" )
                                .attr( "data-value", item.value )
                                .append("<span class=\"select2-results\" >"+item.label+"</span>" )
                                .appendTo( ul );

                          if(item.object=="title") $li.css("font-weight","bold");

                          return $li;
             };

            </script>
          ';


        }

        $this->resprints = $res;


        return 0;
    }

}
