<h1><a href="<?php echo WOMBAT_BASE_URL.'/index' ?>">Wombat Webhooks</a></h1>

<div class="block api_connection_info" style="display:none;">
	<label><span>Username:</span><input type="text" name="api_username" value="athleticapi" placeholder="athleticapi"></label>
	<label><span>Path:</span><input type="text" name="api_path" value="https://store-pijlvyhy.mybigcommerce.com/api/v2/" placeholder="https://store-pijlvyhy.mybigcommerce.com/api/v2/"></label>
	<label><span>Token:</span><input type="text" name="api_token" value="d1ee45ad7d4a7c7c97b102eea8fd9663bb7ce9c9" placeholder="d1ee45ad7d3a7c7c97b102eea8fd9763bb7cf9c9"></label>
</div>
<p><button type="button" class="small right showbutton" data-showelement=".api_connection_info">Edit api connection info</button></p>

<div class="block">
	<dl>
		<dt>get_products</dt>
		<dd>
			<p>"Receive" endpoint (Wombat &laquo; Bigcommerce).</p><p>Accepts bc product sku &amp; echoes product (wombat)</p>
			
			<form class="one_column" action="<?php echo WOMBAT_BASE_URL.'/get_products' ?>">
				<fieldset>
					<legend>&rsaquo; get_products &rsaquo; <em>request</em></legend>
					<?php $default_input = array(
						'request_id' => '',
						'parameters' => array(
							'sku' => 'BIKE-TURQ'
						)
					); ?>
					<textarea><?php echo json_encode($default_input,JSON_PRETTY_PRINT); ?></textarea>
					<button type="submit">Send</button>
				</fieldset>
				<fieldset style="display:none;">
					<legend>&rsaquo; get_products &rsaquo; <em>response</em><span class="response_status"></span></legend>
					<div class="response"><textarea></textarea></div>
					<button type="button" class="response_clear">Clear</button>
				</fieldset>
			</form>
		</dd>
		
		<dt>add_product</dt>
		<dd>
			<p>"Send" endpoint (Wombat &raquo; Bigcommerce).</p><p>Accepts wombat object &amp; pushes into BC</p>
			
			<form class="one_column">
				<fieldset>
					<legend>&rsaquo; add_product &rsaquo; <em>request</em></legend>
					<textarea></textarea>
					<button type="submit">Send</button>
				</fieldset>
				<fieldset style="display:none;">
					<legend>&rsaquo; add_product &rsaquo; <em>response</em><span class="response_status"></span></legend>
					<div class="response"><textarea></textarea></div>
					<button type="button" class="response_clear">Clear</button>
				</fieldset>
			</form>
		</dd>
	</dl>
</div>

<script>
	$('form').on('submit',function(event){
		event.preventDefault();
		
		var $form = $(this);
		var $button = $form.find('button[type=submit]');
		
		if(!$button.is(':disabled'))
			WombatTestFront.request($form);
	});
	
	$('.response_clear').on('click',function(){
		$(this).parent().fadeOut(function(){
			$(this).parents('.two_columns').attr('class','one_column');
		});
	});
	
	$('button[type=submit]').after('<div class="loading-spinner" style="display:none;"></div>');
	
	var WombatTestFront = {
		request: function($form){
			// useful elements
			var $response = $form.find('.response');
			var $response_status = $form.find('.response_status');
			
			// button reflects request status...
			var $button = $form.find('button[type=submit]');
			var button_text = $button.text();
			$button.text('Loading...');
			$button.next('.loading-spinner').fadeIn();
			$button.prop('disabled', true);
			
			// get data to send with request
			var request_data = $form.find('fieldset:first textarea').val();
			var request_object;
			
			// parse input JSON
			try { request_object = $.parseJSON(request_data); }
			catch (e) { }
			
			if(!request_object) {
			
				$response_status.addClass('response_error');
				$response.find('textarea').val('Input JSON is invalid.');
				
				// update form to reflect complete request
				$button.text(button_text);
				$button.prop('disabled', false);
				$button.next('.loading-spinner').fadeOut();
				
				$response_status.html('error');
				$response_status.attr('title','Request URL:\n'+$form.attr('action'));
				
				$response.parents('.one_column').attr('class','two_columns');
				$response.parent().fadeIn();
			
			} else { // request object parsed
				// make request
				request_object.legacy_api = {
					username: encodeURIComponent($('.api_connection_info input[name=api_username]').val()),
					path: encodeURIComponent($('.api_connection_info input[name=api_path]').val()),
					token: encodeURIComponent($('.api_connection_info input[name=api_token]').val())
				};
				
				$response.find('textarea').val('Loading...');
				$response_status.attr('class','response_status').html('loading');
				
				$.ajax({
					type: "POST",
					url: $form.attr('action'),
					data: request_object,
					error: function(jqXHR,textStatus,errorThrown){
						$response_status.addClass('response_error');
						$response.find('textarea').val(errorThrown);
					},
					success: function(data,textStatus,jqXHR) {
						if(typeof data === 'object')
							data = JSON.stringify(data,null,4);
					
						$response_status.addClass('response_success');
						$response.find('textarea').val(data);
					},
					complete: function(jqXHR, textStatus) {
						// update form to reflect complete request
						$button.text(button_text);
						$button.prop('disabled', false);
						$button.next('.loading-spinner').fadeOut();
						
						$response_status.html(textStatus);
						$response_status.attr('title','Request URL:\n'+$form.attr('action'));
						
						$response.parents('.one_column').attr('class','two_columns');
						$response.parent().fadeIn();
					}
				});
			}
		}
	};
	
	$('.showbutton').on('click',function(){
		var data_show_ele = $(this).attr('data-showelement');
		$(data_show_ele).slideDown();
		$(this).parent().fadeOut();
	});
</script>