<?php

require_once( 'config/config_params.php' );
require_once( BASE_DIR.'/src/getWeixinAccessToken.php' );
$at = new GetWeixinAccessToken();
echo $at->run( $_GET );
