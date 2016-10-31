<?php
/**
 * @copyright: @速玩广州 2015
* @description: 微信的分析类
* @author: xiongjianbang
* @file:weixin_parser.php
* @charset: UTF-8
* @time: 2015-04-25 18:22
* @version 1.0
**/
if (!defined('BASEPATH')) exit('No direct script access allowed');


class Weixin_parse{
    
    
    private $appid = 'wx98a63fb329fd0baf';
    private $secret = 'c27c0f5508798a08ab7240bd0de3e748';
    
    public function __construct(){
    	
    }
    
    /**
     * @name:get_access_token
     * @description: 通过code换取网页授权access_token
     * @param: $code=code值
     * @return:
     * {
     "access_token":"ACCESS_TOKEN",
     "expires_in":7200,
     "refresh_token":"REFRESH_TOKEN",
     "openid":"OPENID",
     "scope":"SCOPE",
     "unionid": "o6_bmasdasdsad6_2sgVt7hMZOPfL"
     }
     * @author: Xiong Jianbang
     * @create: 2015-5-19 下午6:54:26
     **/
    public function get_access_token($code=0){
        if(empty($code)){
            return FALSE;
        }
        $appid = $this->appid;
        $secret = $this->secret;
        $url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid={$appid}&secret={$secret}&code={$code}&grant_type=authorization_code";
        $json = $this->https_request($url);
        if(empty($json)){
            return FALSE;
        }
        $arr = json_decode($json,TRUE);
        if(empty($arr)){
            return FALSE;
        }
        return $arr;
    }
    
    /**
     * @name:get_simple_access_token
     * @description: 获取网页授权access_token
     * @return: 
     * {
           "access_token":"ACCESS_TOKEN",
           "expires_in":7200,
        }
     * @author: Xiong Jianbang
     * @create: 2015-5-19 下午6:54:26
     **/
    public function get_simple_access_token(){
        $CI =& get_instance();
        $CI->load->helper('file');
        $filename = $GLOBALS['APK_UPLOAD_DIR'].'/access_token.txt';
        if(is_file($filename)){
            $string = read_file($filename);
            if(empty($string)){
            	return $this->write_simple_access_token($filename);
            }
            $arr = json_decode($string,TRUE);
            $last_time = $arr['timestamp'];
            if(time()-$last_time>=7200){ //20分钟后过期
                return $this->write_simple_access_token($filename);
            }else{
               return $arr;
            }
        }else{
            return $this->write_simple_access_token($filename);
        }
    }
    
    /**
     * @name:write_access_token
     * @description: 写入access_token到临时文件
     * @param: $code=code值
     * @param:$filename=文件名
     * @author: Xiong Jianbang
     * @create: 2015-6-2 上午10:20:27
     **/
    private function write_simple_access_token($filename){
        $appid = $this->appid;
        $secret = $this->secret;
//         $url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid={$appid}&secret={$secret}&code={$code}&grant_type=authorization_code";
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appid}&secret={$secret}";
        $json = $this->https_request($url);
        if(empty($json)){
            return FALSE;
        }
        $arr = json_decode($json,TRUE);
        if(empty($arr)){
            return FALSE;
        }
        $arr['timestamp'] = time();
        $json = json_encode($arr);
        file_put_contents($filename, $json);
        return $arr;
    }
    
    /**
     * @name:check_access_token
     * @description: 检验授权凭证（access_token）是否有效
     * @param:Array包含如下参数
             * @param: $access_token=授权凭证
             * @param:$openid=公众号的普通用户的一个唯一的标识，只针对当前的公众号有效
             * @param:$refresh_token=刷新token
     * @return: 
     * {
           "access_token":"ACCESS_TOKEN",
           "expires_in":7200,
           "refresh_token":"REFRESH_TOKEN",
           "openid":"OPENID",
           "scope":"SCOPE",
           "unionid": "o6_bmasdasdsad6_2sgVt7hMZOPfL"
        }
     * @author: Xiong Jianbang
     * @create: 2015-5-20 上午9:52:36
     **/
    public function check_access_token($arr=array()){
        $access_token = $arr['access_token'];
        $openid = $arr['openid'];
        $refresh_token = $arr['refresh_token'];
        $appid = $this->appid;
        
        $url = "https://api.weixin.qq.com/sns/auth?access_token=$access_token&openid=$openid";
        $json = $this->https_request($url);
        if(empty($json)){
            return FALSE;
        }
        $arr_tmp = json_decode($json,TRUE);
        $errcode = isset($arr_tmp['errcode'])?$arr_tmp['errcode']:0;
        //表示验证失败，则重新获取
        if($errcode>0){
            //刷新access_token
            $url = "https://api.weixin.qq.com/sns/oauth2/refresh_token?appid=$appid&grant_type=refresh_token&refresh_token=$refresh_token";
            $json = $this->https_request($url);
            if(empty($json)){
                return FALSE;
            }
            $arr_new = json_decode($json,TRUE);
            if(empty($arr_new)){
                return FALSE;
            }
            return $arr_new;
        }
        return $arr;
    }
    
    /**
     * @name:get_user_info
     * @description: 获取用户信息
     * @param: $access_token=授权凭证
     * @param:$openid=公众号的普通用户的一个唯一的标识，只针对当前的公众号有效
     * @return: Array
     * @author: Xiong Jianbang
     * @create: 2015-5-20 上午10:04:45
     **/
    public function get_user_info($access_token,$openid){
        if(empty($access_token) || empty($openid)){
            return FALSE;
        }
        $url = "https://api.weixin.qq.com/sns/userinfo?access_token=$access_token&openid=$openid&lang=zh_CN";
        $json = $this->https_request($url);
        if(empty($json)){
        	return FALSE;
        }
        $arr = json_decode($json,TRUE);
        if(empty($arr)){
            return FALSE;
        }
        return $arr;
    }
    
    /**
     * @name:https_request
     * @description: 通过curl获取数据
     * @param: $url=网址
     * @param:$data=array
     * @return: $output
     * @author: Xiong Jianbang
     * @create: 2015-5-20 上午10:21:47
     **/
    public function https_request($url, $data = null) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        if (!empty($data)){ //POST方式 
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }
    
    
}