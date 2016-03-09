<?php

/**
 * Author : zhangli < zhangli1582102953@126.com >
 * Date : 2015年2月6日
 *
 * 这是一个统一获取微信accessToken的类
 *
 * Usage:
 * 	   $at = new GetWeixinAccessToken();
 *	   echo $at->run(array('appid'=>APPID, secret'=>'SECRET)); 
 */

// 不重复的session_id值 
session_id( '89fcf488c4eed040cea884b5061729e4' );
session_start();

define( 'PARAM_FILE', '../config/config_params.php' );
if( file_exists(PARAM_FILE) ) require_once(PARAM_FILE);

require_once( BASE_DIR.'/lib/KLogger.php' );
require_once( BASE_DIR.'/lib/Util.php' );
class GetWeixinAccessToken 
{
	public $appid;
	public $secret; 

	public function __construct() 
	{
		$this->log = new KLogger( LOG_DIR.'/weixin_access_token.log', KLogger::INFO );
	}

	public function run( $param ) 
	{
		if ( isset( $param['force'] ) && $param['force'] == true ) 
		{

			$this->forceRefurbishToken = $param['force'];
		} 
		else 
		{
			$this->forceRefurbishToken = false;
			
		}


		try 
		{
			$this->checkParam( $param['appid'], $param['secret'] );
			return $this->getToken();
		}
		catch ( Exception $e ) 
		{
			return $e->getMessage();
		}

	}
	
	public function checkParam( $appid, $secret ) 
	{
		if ( !empty( $appid ) && !empty( $secret ) ) {
			$this->appid = $appid;
			$this->secret = $secret;
		}
		else 
		{
			throw new Exception('{"code":202, "msg": "Parameter error"}');
		}		
	}

	public function getToken() 
	{
		$url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.$this->appid.'&secret='.$this->secret;

		if ( isset($_SESSION['weixinaccesstoken'][$this->appid]['timestamp']) )
			$timestamp = $_SESSION['weixinaccesstoken'][$this->appid]['timestamp'];
		else 
			$timestamp = '';


		if ( isset( $_SESSION['weixinaccesstoken'][$this->appid]['expires_in'] ) )
			$expires_in = $_SESSION['weixinaccesstoken'][$this->appid]['expires_in'];
		else
			$expires_in = 7200;

		if ( empty( $timestamp ) || time() - $timestamp > $expires_in || $this->forceRefurbishToken ) 
		{
			unset( $_SESSION['weixinaccesstoken'][$this->appid] );
		}

		if ( !isset( $_SESSION['weixinaccesstoken'][$this->appid] ) )
		{
			$access_token = json_decode( Util::customCurl( $url ) );
			if ( isset( $access_token->access_token ) )
			{
				$token = $access_token->access_token;
				$expires_in = $access_token->expires_in;
				$_SESSION['weixinaccesstoken'][$this->appid] = array(
					'token' => $token,
					'timestamp' => time(),
					'expires_in' => $expires_in
				); 
				$logVal = sprintf( '{"code":200, "access_token": "%s", "uri":"%s", "client_ip":"%s"}', 
					$token, $_SERVER['REQUEST_URI'], $_SERVER['REMOTE_ADDR'] );
				$this->log->LogInfo( $logVal );

				return '{"code":200, "access_token":"'.$token.'"}';
			} 
			else 
			{
				$notTokenLogVal = sprintf( '{"code":201, "msg": "%s", "uri":"%s", "client_ip":"%s"}', 
					json_encode($access_token), $_SERVER['REQUEST_URI'], $_SERVER['REMOTE_ADDR'] ); 
				$this->log->LogWarn( $notTokenLogVal );
				throw new Exception( '{"code":201, "msg": "get access_token fail"}' );
			}

		} 
		else 
		{
			$existAppLogVal = sprintf( '{"code":200, "access_token": "%s", "uri":"%s", "client_ip":"%s"}', 
				$_SESSION['weixinaccesstoken'][$this->appid]['token'], $_SERVER['REQUEST_URI'], 
				$_SERVER['REMOTE_ADDR'] ); 
			$this->log->LogInfo( $existAppLogVal );
			return '{"code":200, "access_token":"'.$_SESSION['weixinaccesstoken'][$this->appid]['token'].'"}';
		}
	}


}
