<?php 
/**
 * Qihoo Hao360Auth v1.0.0.1
 * @version 1.0.0.1  
 * @modifytime 2010-12-03  
 * @author hao.360.cn
 * @contact yangtao@360.cn
 * @example 
 *      // GET request token
 *      $auth = new Hao360Auth();
 *      $requestToken = $auth->getRequestToken();
 *      $authorizeURL = $auth->getAuthorizeURL($requestToken,"http://example.com/callback.php") 
 *      header("Location:$authorizeURL");
 *      // GET access token 
 *      $h = new Hao360Auth($request_token,$oauth_token_secret);
 *      $accessToken   = $h->getAccessToken($oauth_verifier) ;
 */

include_once("OAuth.php");

class Hao360Auth 
{ /*{{{*/
    public $http_code; 
    public $url; 
    public $server_domain="api.hao.360.cn";
    public $host = null;
    public $timeout = 10; 
    public $connecttimeout = 30;  
    public $ssl_verifypeer = FALSE; 
    public $format = 'json'; 
    public $decode_json = TRUE; 
    public $http_info; 
    public $useragent = 'Qihoo Hao360Auth v1.0.0.1 '; 

    function __construct($oauth_token = NULL, $oauth_token_secret = NULL) 
    { /*{{{*/
        $this->host = "http://".$this->server_domain."/" ;
        $this->sha1_method = new OAuthSignatureMethod_HMAC_SHA1(); 
        $this->consumer = new OAuthConsumer(APP_KEY, APP_SECRET); 
        if (!empty($oauth_token) && !empty($oauth_token_secret)) 
        { 
            $this->token = new OAuthConsumer($oauth_token, $oauth_token_secret); 
        } 
        else 
        { 
            $this->token = NULL; 
        } 
    } /*}}}*/

    /** 
     * Get a request_token  
     * 
     * @return array a key/value array containing oauth_token and oauth_token_secret 
     */ 
    function getRequestToken($oauth_callback = NULL) 
    { /*{{{*/
        $parameters = array(); 
        if (!empty($oauth_callback)) { 
            $parameters['oauth_callback'] = $oauth_callback; 
        }  

        $request = $this->oAuthRequest($this->requestTokenURL(), 'GET', $parameters); 
        $token = OAuthUtil::parse_parameters($request); 
        $this->token = new OAuthConsumer($token['oauth_token'], $token['oauth_token_secret']); 
        return $token; 
    } /*}}}*/

    /** 
     * Get the authorize URL 
     * 
     * @return string 
     */ 
    function getAuthorizeURL($token,$callbackurl) 
    { /*{{{*/
        if (is_array($token))  
            $token = $token['oauth_token']; 
        return $this->authorizeURL() . "?oauth_token={$token}&oauth_callback=" . urlencode($callbackurl); 
    } /*}}}*/

    /** 
     * Exchange the request token and secret for an access token and 
     * secret, to sign API calls. 
     * 
     * @return array array("oauth_token" => the access token, 
     *                "oauth_token_secret" => the access secret) 
     */ 
    function getAccessToken($oauth_verifier = FALSE, $oauth_token = false) 
    { /*{{{*/
        $parameters = array(); 
        if (!empty($oauth_verifier)) { 
            $parameters['oauth_verifier'] = $oauth_verifier; 
        } 
        $request = $this->oAuthRequest($this->accessTokenURL(), 'GET', $parameters); 
        $token = OAuthUtil::parse_parameters($request); 
        $this->token = new OAuthConsumer($token['oauth_token'], $token['oauth_token_secret']); 
        return $token; 
    } /*}}}*/

    function get($url, $parameters = array()) 
    { /*{{{*/
        $response = $this->oAuthRequest($url, 'GET', $parameters); 
        if ($this->format === 'json' && $this->decode_json) 
        { 
            return json_decode($response, true); 
        } 
        return $response; 
    } /*}}}*/

    function post($url, $parameters = array() , $multi = false) 
    { /*{{{*/
        
        $response = $this->oAuthRequest($url, 'POST', $parameters , $multi ); 
        if ($this->format === 'json' && $this->decode_json) { 
            return json_decode($response, true); 
        } 
        return $response; 
    } /*}}}*/

    function delete($url, $parameters = array()) 
    { /*{{{*/
        $response = $this->oAuthRequest($url, 'DELETE', $parameters); 
        if ($this->format === 'json' && $this->decode_json) { 
            return json_decode($response, true); 
        } 
        return $response; 
    } /*}}}*/

    /** 
     * Format and sign an OAuth / API request 
     * 
     * @return string 
     */ 
    function oAuthRequest($url, $method, $parameters , $multi = false) 
    { /*{{{*/
        if (strrpos($url, 'http://') !== 0 && strrpos($url, 'http://') !== 0) 
        { 
            $url = "{$this->host}{$url}.{$this->format}"; 
        } 
        // echo $url ; 
        $request = OAuthRequest::from_consumer_and_token($this->consumer, $this->token, $method, $url, $parameters); 
        $request->sign_request($this->sha1_method, $this->consumer, $this->token); 
//        echo "<pre>";
//        var_dump($request);
        switch ($method) 
        { 
        case 'GET': 
//            echo $request->to_url(); 
            return $this->http($request->to_url(), 'GET'); 
        default: 
            return $this->http($request->get_normalized_http_url(), $method, $request->to_postdata($multi) , $multi ); 
        } 
    } /*}}}*/
    function http($url, $method, $postfields = NULL , $multi = false) 
    { /*{{{*/
        $this->http_info = array(); 
        $ci = curl_init(); 
        /* Curl settings */ 
        curl_setopt($ci, CURLOPT_USERAGENT, $this->useragent); 
        curl_setopt($ci, CURLOPT_CONNECTTIMEOUT, $this->connecttimeout); 
        curl_setopt($ci, CURLOPT_TIMEOUT, $this->timeout); 
        curl_setopt($ci, CURLOPT_RETURNTRANSFER, TRUE); 

        curl_setopt($ci, CURLOPT_SSL_VERIFYPEER, $this->ssl_verifypeer); 

        curl_setopt($ci, CURLOPT_HEADERFUNCTION, array($this, 'getHeader')); 

        curl_setopt($ci, CURLOPT_HEADER, FALSE); 

        switch ($method) { 
        case 'POST': 
            curl_setopt($ci, CURLOPT_POST, TRUE); 
            if (!empty($postfields)) { 
                curl_setopt($ci, CURLOPT_POSTFIELDS, $postfields); 
                //echo "=====post data======\r\n";
                //echo $postfields;
            } 
            break; 
        case 'DELETE': 
            curl_setopt($ci, CURLOPT_CUSTOMREQUEST, 'DELETE'); 
            if (!empty($postfields)) { 
                $url = "{$url}?{$postfields}"; 
            } 
        } 

        $header_array = array(); 
        /*
        /////////////
        $header_array["FetchUrl"] = $url; 
        $header_array['TimeStamp'] = date('Y-m-d H:i:s'); 
        $header_array['AccessKey'] = SAE_ACCESSKEY; 
        $content="FetchUrl"; 
        $content.=$header_array["FetchUrl"]; 
        $content.="TimeStamp"; 
        $content.=$header_array['TimeStamp']; 
        $content.="AccessKey"; 
        $content.=$header_array['AccessKey']; 
        $header_array['Signature'] = base64_encode(hash_hmac('sha256',$content, SAE_SECRETKEY ,true)); 
        ////////////
        */
        
        $header_array2=array(); 
        if( $multi ) 
        	$header_array2 = array("Content-Type: multipart/form-data; boundary=" . OAuthUtil::$boundary , "Expect: ");
        foreach($header_array as $k => $v) 
            array_push($header_array2,$k.': '.$v); 

        curl_setopt($ci, CURLOPT_HTTPHEADER, $header_array2 ); 
        curl_setopt($ci, CURLINFO_HEADER_OUT, TRUE ); 
        curl_setopt($ci, CURLOPT_URL, $url); 

        $response = curl_exec($ci); 
        $this->http_code = curl_getinfo($ci, CURLINFO_HTTP_CODE); 
        $this->http_info = array_merge($this->http_info, curl_getinfo($ci)); 
        $this->url = $url; 
//        echo "<pre>";
//        echo '=====info====='."\r\n";
//        print_r( curl_getinfo($ci) ); 
        curl_close ($ci); 
        return $response; 
    } /*}}}*/
    function getHeader($ch, $header) 
    { /*{{{*/
        $i = strpos($header, ':'); 
        if (!empty($i)) 
        { 
            $key = str_replace('-', '_', strtolower(substr($header, 0, $i))); 
            $value = trim(substr($header, $i + 2)); 
            $this->http_header[$key] = $value; 
        } 
        return strlen($header); 
    } /*}}}*/

    function accessTokenURL()  
    { /*{{{*/
        return 'http://'.$this->server_domain.'/oauth/access_token.php'; 
    } /*}}}*/
    function authorizeURL()
    {/*{{{*/
        return 'http://'.$this->server_domain.'/oauth/authorize.php'; 
    } /*}}}*/
    function requestTokenURL() 
    { /*{{{*/
        return 'http://'.$this->server_domain.'/oauth/request_token.php'; 
    } /*}}}*/
    function lastStatusCode() 
    { /*{{{*/
        return $this->http_status; 
    } /*}}}*/
    function lastAPICall() 
    { /*{{{*/
        return $this->last_api_call; 
    } /*}}}*/
} /*}}}*/
