<?php

	require 'config.php';
	dol_include_once('/core/lib/functions.lib.php');
	dol_include_once('/elk/lib/elk.lib.php');
	
	$langs->load('elk@elk');
	
	$keyword = GETPOST('keyword');
	if(empty($keyword) && GETPOST('sall')) $keyword = GETPOST('sall');

	llxHeader('', $langs->trans('ELKSearch'), '', '', 0, 0, array('/elk/js/jquery.tile.min.js')  );
	$head = elk_prepare_head($keyword);
	dol_fiche_head($head, 'search', $langs->trans('elk'), 0, 'elk@elk');

	
	?>
	<style type="text/css">
		#results {
			position:relative;
			margin-top:15px;
		}
		
		#results span.loading {
			padding : 20px;
			background-color: #f64f1c;
			border-radius: 10px;
			top:50px;
			left:50px;
			position:relative;
		}
		
		#results div.result {
			
			width:300px; 
			float:left;
			
			border-color: #bbb #aaa #aaa;
		    border-style: solid;
		    border-width: 1px;
		    box-shadow: 3px 3px 4px #ddd;
		    margin: 0 10px 14px 0;
		   
		   
			
		}
		.highlight {
			font-weight: bold;
		}
	</style>
	
	<input type="text" name="keyword" id="keyword" value="" />
	<input type="button" name="btseach" id="btseach" value="Rechercher" />
	
	<div id="results">
		
	</div>
	<div style="clear:both"></div>

	<script type="text/javascript">
		var url = "<?php echo dol_buildpath('/elk/search.php?keyword=', 1) ?>";
		var TSearch = ['product','company','contact','event','contrat'];
	
		$(document).ready(function() {
			
			$("#btseach").click(function() {
				
				var keyword = $("#keyword").val();
				$('#results').html("<span class=\"loading\">Chargement...</span>");
				$('a#search').attr('href', url+keyword);
				
				for(x in TSearch) {
					
					$.ajax({
						url : "./script/interface.php"
						,data :{
							get:'search'
							,type:TSearch[x]
							,keyword : keyword
						}
						
					}).done(function(data) {
						$('#results span.loading').remove();
						
						$div = $('<div class="result" />');
						$div.append(data);
						
						$('#results').append($div);
						
						$('#results div.result').tile();

                        jQuery("#results div.result .classfortooltip").tooltip({
                                show: { collision: "flipfit", effect:'toggle', delay:50 },
                                hide: { delay: 50 },
                                tooltipClass: "mytooltip",
                                content: function () {
                                return $(this).prop('title');		/* To force to get title as is */
                            }
                        });
					})
					
					
				}
				
				
			});
			
			<?php
				if($keyword!='') {
					?>
					$("#keyword").val("<?php echo $keyword; ?>");
					$("#btseach").click();
					<?php
				}			
			
			?>
			
		});
	</script>


	<?php
	
	
	llxFooter();


