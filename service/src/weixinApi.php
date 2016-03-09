<?php


require_once( '../config/config_params.php' );
require_once( BASE_DIR.'/src/getWeixinAccessToken.php' );
require_once( BASE_DIR.'/lib/KLogger.php' );
require_once( BASE_DIR.'/lib/Util.php' );

class DomobWeixinApi 
{
	public $appid;
	public $secret;
	public $timestamp;
	public $noncestr;
	public $jsapi_ticket;
	public $jsonpCallback;
	public $url;

	public function __construct() 
	{
		$this->config = include( BASE_DIR.'/config/config.php' );
		$this->log = new KLogger( LOG_DIR.'/weixin_api.log', KLogger::INFO );
	}
	
	public function run( $param ){
		$this->jsonpCallback = $param['jsonpCallback'];
		$this->checkDomainSafe( $param['url'] );
		if ( !array_key_exists( $param['appid'], $this->config['appid'] ) )
		{
			$value = $this->jsonpCallback."({'code':201, 'msg':'appid not set'})";
			$logVal = sprintf( 'run module: %s not set, RETURNVALUE: %s url: %s', 
				$param['appid'], $value, $param['url'] );
			$this->log->LogWarn( $logVal );
			exit( $value );
		}
		$this->url = $param['url'];	
		$this->appid = $param['appid'];
		$this->secret = $this->config['appid'][$this->appid]['secret'];
		$this->jsapi_ticket = isset( $param['jsapi_ticket'] ) ? $param['jsapi_ticket'] : NULL;
		try
		{
			$this->getSignature();
		}
		catch( Exception $e )
		{
			$logVal = sprintf( 'run module: %s url: %s', $e->getMessage(), $this->url );
			$this->log->LogWarn( $logVal );	
		}
	}


	//域名安全检查
	public function checkDomainSafe( $url )
	{
		$prevHost = parse_url( $_SERVER['HTTP_REFERER'] );
		$hostArr = explode( '.', $prevHost['host'] );
		$count = count( $hostArr );
		if ( $count > 2 ){
			$hostUrl = $hostArr[$count-2].'.'.$hostArr[$count-1];
		}
		else if ( $count == 2 )
		{
			$hostUrl = $prevHost['host'];
		}
		if ( !in_array( $hostUrl, $this->config['safeUrl'] ) )
		{
			$logVal = sprintf( 'checkDomainSafe module: {"code":207, "msg":"domain invalid"} 
				url: %s', $url );
			$value = $this->jsonpCallback."({'code':207, 'msg':'domain invalid'})";
			$this->log->LogWarn( $logVal );	
			exit( $value );
		}
	}


	//获取微信 token
	public function getWeixinToken(){
		$at = new GetWeixinAccessToken(); 
		$params = array('appid'=>$this->appid, 'secret'=>$this->secret);
		$access_token = $at->run( $params );
		if ( json_decode( $access_token )->code == 200 )
		{
			return json_decode( $access_token )->access_token;
		}
		return '';
	}

	
	//防止超出调用上限
	private function apiAstrict( $str, $total )
	{
		$date = $_SESSION['weixinjssdkapiAstrict'][$this->appid]['date'];
		if ( isset( $date ) && $date != date( 'Y-m-d', time() ) )
		{
			unset( $_SESSION['weixinjssdkapiAstrict'][$this->appid] );
		}

		if ( !isset( $_SESSION['weixinjssdkapiAstrict'][$this->appid]['date'] ) ){
			$_SESSION['weixinjssdkapiAstrict'][$this->appid]['date'] = date( 'Y-m-d', time() );
		}

		array_push( $_SESSION['weixinjssdkapiAstrict'][$this->appid]['jsapi_ticket'][], $str );
		$count = count( $_SESSION['weixinjssdkapiAstrict'][$this->appid]['jsapi_ticket'] );
		$logVal = sprintf( 'apiAstrict module [%s]: count %s total %s, url: %s', 
			$this->appid, $count, $total, $this->url );
		$this->log->LogWarn( $logVal );
		if ( $count > $total )
		{
			exit( $this->jsonpCallback."({'code':204, 'msg':'api astrict'})" );
		}
	}

	//获取signature
	public function getSignature()
	{
		$token = $this->getWeixinToken();
		if ( isset( $_SESSION['weixinjssdkjsapiticket'][$this->appid]['expires_in'] ) )
			$expires_in = $_SESSION['weixinjssdkjsapiticket'][$this->appid]['expires_in'];
		else
			$expires_in = 7200;

		if ( isset( $_SESSION['weixinjssdkjsapiticket'][$this->appid]['timestamp'] ) )	
			$ts = $_SESSION['weixinjssdkjsapiticket'][$this->appid]['timestamp'];
		else
			$ts = '';


		if ( empty($ts) || time() - $ts > $expires_in )
		{
			unset( $_SESSION['weixinjssdkjsapiticket'][$this->appid] );
			$logVal = sprintf( 'getSignature module: timestamp %s, expires_in %s, unsetAppid[%s]: %s, url: %s', 
				$ts, $expires_in, $this->appid, json_encode( $_SESSION['weixinjssdkjsapiticket'][$this->appid] ), 
				$this->url );
			$this->log->LogInfo( $logVal );
		}

		//获取jsapi_ticket
		if ( isset($_SESSION['weixinjssdkjsapiticket'][$this->appid]['jsapi_ticket'] ) )
		{
			$this->jsapi_ticket = $_SESSION['weixinjssdkjsapiticket'][$this->appid]['jsapi_ticket'];
		}
		else if ( !isset( $_SESSION['weixinjssdkjsapiticket'][$this->appid] ) && !empty( $token ) )
		{
			$this->apiAstrict( $this->appid, 24 / ($expires_in / 3600) + 3 );
			$token_url = 'https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token='.$token.'&type=jsapi';
			$ticket = Util::customCurl( $token_url );
			$this->jsapi_ticket = json_decode( $ticket )->ticket;
			$expires_in = json_decode( $ticket )->expires_in;
			$_SESSION['weixinjssdkjsapiticket'][$this->appid] = array(
					'jsapi_ticket' => $this->jsapi_ticket,
					'timestamp' => time(),
					'expires_in' => $expires_in				
			);
			$getTokenLog = sprintf( 'getSignature module: get access_token [%s] %s, get jsapi_ticket %s url: %s', 
				$this->appid, $token, $this->jsapi_ticket, $this->url );
			$this->log->LogInfo( $getTokenLog );
		}
		else
		{
			$this->log->LogInfo( 'getSignature module: Get token['.$this->appid.'] failure; url: '.$this->url );
			exit( $this->jsonpCallback."({'code':203, 'msg':'Get token failure'})" );
		}
		
		$this->noncestr = $this->generate_password();
		$timestamp = time();
		//获取signature
		$data = array(
			'url' => $this->url,
			'jsapi_ticket' => $this->jsapi_ticket,
			'noncestr' => $this->noncestr,
			'timestamp' => $timestamp,
		);
		ksort( $data );
		$str = urldecode( http_build_query( $data ) );
		$jsapiArr = array( 'code'=>200, 
				  'signature'=>sha1( $str ), 
				  'jsapi_ticket'=>$this->jsapi_ticket, 
				  'timestamp'=>$timestamp, 
				  'noncestr'=>$this->noncestr, 
				  'appid'=>$this->appid );
		$jsapiJson = json_encode( $jsapiArr );
		$this->log->LogInfo( 'getSignature module: '.$jsapiJson.' url: '.$this->url );
		
		$callbackarr = array(
				'code'=>200, 
				'signature'=>sha1( $str ), 
				'jsapi_ticket'=>$this->jsapi_ticket, 
				'timestamp'=>$timestamp, 
				'noncestr'=>$this->noncestr, 
				'appid'=>$this->appid );
		$callbackstr = json_encode( $callbackarr );
		exit( $this->jsonpCallback.'('.$callbackstr.')' );
	}

	//随机值
	function generate_password( $length = 16 ) 
	{  
		// 密码字符集，可任意添加你需要的字符  
		$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';  
		$password = '';  
		for ( $i = 0; $i < $length; $i++ )  
		{  
		$password .= $chars[ mt_rand(0, strlen($chars) - 1) ];  
		}  
		return $password;  
	}	

}

$dwa = new DomobWeixinApi();
$dwa->run( $_GET );
