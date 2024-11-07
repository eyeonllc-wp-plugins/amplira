<?php

function amplira_debug($item = array(), $die = true, $display = true) {
	if( is_array($item) || is_object($item) ) {
		echo '<pre class="debug" style="padding-left:180px;'.($display?'':'display:none').'">'; print_r($item); echo '</pre>';
	} else {
		echo '<div class="debug" style="padding-left:180px;'.($display?'':'display:none').'">'.$item.'</div>';
	}
	
	if( $die ) {
		die();
	}
}
