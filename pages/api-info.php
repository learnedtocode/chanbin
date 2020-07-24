<?php

$paste = Paste::load($route_params['paste_id']);
if (!$paste) {
	set_http_status_code(400);
	serve_json(['id' => $route_params['paste_id'], 'error' => 'invalid_paste_id']);
}

serve_json(['id' => $route_params['paste_id'], 'info' => $paste->getInfoText(', ')]);
