<?php

class Util
{
        /*
         * 通用请求方法
         * $url string request link
         * $param Array params
         */

        public static function customCurl($url, $param = '', $type = 'GET')
        {
                if ( is_array( $param ) )
                {
                        foreach ( $param as $k => $v )
                        {
                                $value[] = $k.'='.$v;
                        }
                        $value = implode( '&', $value );
                }
                else
                {
                        $value = $param;
                }

                $ch = curl_init();
                if ( $type != 'GET' )
                {
                        curl_setopt( $ch, CURLOPT_URL, $url );
                        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
                        curl_setopt( $ch, CURLOPT_POST, 1 );
                        curl_setopt( $ch, CURLOPT_POSTFIELDS, $value );
                }
                else
                {
                        curl_setopt( $ch, CURLOPT_URL, $url );
                        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
                }
                $content = curl_exec( $ch );
                curl_close( $ch );
                return $content;

        }
}
