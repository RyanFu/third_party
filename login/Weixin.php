<?php
/**
 * @copyright: xxxxxxxxxxxx @www.xxxxxx.com
 * @description:微信登录接口
 * @file: Weixin.php
 * @author: xxxxxx
 * @charset: UTF-8
 * @time: 2016-7-18  下午19:09:12
 * @version 1.0
 **/


defined('BASEPATH') OR exit('No direct script access allowed');

class Weixin extends xxxxxxController {
	
	
	private $host_url;
	
	public function __construct(){
		parent::__construct();
		//跳转URL列表
		$this->arr_urls = array(
			'xxxxxxwww' => 'http://www.xxxxxx.com',
			'xxxxxxbook' => 'https://mall.xxxxxx.com',
			'xxxxxxorder' => 'http://mall.xxxxxx.com/order/add',
			'xxxxxxorder_list' => 'http://mall.xxxxxx.com/orders',
			'xxxxxxmall_buy' => 'https://mall.xxxxxx.com/order/buy',
			'uc_account' => 'http://tucenter.xxxxxx.com/account',
		);
		$this->host_url = $this->config->item('base_url');
	}
	
	
	/**
	* @name:  登录首页 
	* @创建者： xxxxxx
	* @作　用： 登录首页
	* @create:  2016-7-19 上午11:17:34
	*/
	public function index(){
		$xxxxxxunionid = isset($_COOKIE['xxxxxxunionid'])?$_COOKIE['xxxxxxunionid']:'';
		if(empty($xxxxxxunionid)){
			if ( strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false ) {
				$this->wx();
			}else{
				$this->pop();
			}
		}else{
			header('location:'.$this->host_url);
		}
	}
	
	/**
	* @name: bind 
	* @创建者： xxxxxx
	* @作　用： 绑定登录
	* @create:  2016-9-9 上午9:30:46
	*/
	public function bind(){
		$xxxxxxunionid = isset($_COOKIE['xxxxxxunionid'])?$_COOKIE['xxxxxxunionid']:'';
		if(empty($xxxxxxunionid)){
			$this->show_error_msg('您还没有登录',500);
		}
		if ( strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false ) {
			$this->wx();
		}else{
			$this->pop();
		}
	}
	
	/**
	* @name: pc
	* @创建者： xxxxxx
	* @作　用： PC登录页面
	* @create:  2016-7-18 下午7:29:39
	*/
	public function pc(){
		$redirect = $this->input->get('redirect');
		$redirect_url = '/account';
		if(!empty($redirect) && isset($this->arr_urls[$redirect])){
			$redirect_url = urlencode($this->arr_urls[$redirect]);
		}
		$redirect_uri = $this->host_url .  '/weixin/oauth';
		if(!empty($redirect_url)){
			$redirect_uri = $this->host_url . '/weixin/oauth?redirect='.$redirect_url;
		}
		$data['redirect'] = $redirect;
		$data['appid']= WX_OPEN_APPID;
		$data['redirect_uri'] = $redirect_uri;
		$this->display('front/weixin/index',$data);
	}
	
	/**
	* @name:  pop
	* @创建者： xxxxxx
	* @作　用： 登录弹框
	* @create:  2016-7-18 下午7:29:11
	*/
	public function pop(){
		$redirect = $this->input->get('redirect');
		$redirect_url = '/account';
		if(!empty($redirect) && isset($this->arr_urls[$redirect])){
			$redirect_url = urlencode($this->arr_urls[$redirect]);
		}
		$redirect_uri = $this->host_url.'/weixin/oauth';
		if(!empty($redirect_url)){
			$redirect_uri = $this->host_url.'/weixin/oauth?redirect='.$redirect_url;
		}
		$data['redirect'] = $redirect;
		$data['appid']= WX_OPEN_APPID;
		$data['redirect_uri'] = $redirect_uri;
		$this->load->view( 'front/weixin/pop',$data);
	}
	
	
	/**
	* @name:  open_oauth
	* @创建者： xxxxxx
	* @作　用： 登录验证
	* @create:  2016-7-18 下午7:29:24
	*/
	public function oauth($type='open'){
		$code = isset($_GET['code'])?$_GET['code']:'';
		$state = $_GET['state'];
		$redirect = isset($_GET['redirect'])?$_GET['redirect']:$this->host_url;
		if(empty($code)){
			header('location:'.$redirect);
		}
		switch ($type) {
			case 'open':
				$appid = WX_OPEN_APPID;
				$appsecret = WX_OPEN_APP_SECRET;
			break;
			case 'mp':
				$appid = WX_MP_APPID;
				$appsecret = WX_MP_APP_SECRET;
			break;
		}
		if (empty($code)) {
			$this->show_error_msg('授权失败',500);
		}
		//通过code获取access_token
		$token_url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid='.$appid.'&secret='.$appsecret.'&code='.$code.'&grant_type=authorization_code';
		$token = json_decode(file_get_contents($token_url));
		if (isset($token->errcode)) {
			$this->show_error_msg('获取ACCESS_TOKEN失败,请联系管理员',500);
		}
		$access_token_url = 'https://api.weixin.qq.com/sns/oauth2/refresh_token?appid='.$appid.'&grant_type=refresh_token&refresh_token='.$token->refresh_token;
		//转成对象
		$access_token = json_decode(file_get_contents($access_token_url));
		if (isset($access_token->errcode)) {
			$this->show_error_msg('获取ACCESS_TOKEN失败,请联系管理员',500);
		}
		$user_info_url = 'https://api.weixin.qq.com/sns/userinfo?access_token='.$access_token->access_token.'&openid='.$access_token->openid.'&lang=zh_CN';
		//转成对象
		$user_info = json_decode(file_get_contents($user_info_url));
		if (isset($user_info->errcode)) {
			$this->show_error_msg('获取用户信息失败,请联系管理员',500);
		}
		
		
		
		//昵称
		$nickname = $user_info->nickname;
		//用户头像
		$headimgurl = $user_info->headimgurl;
		//保存用户信息
		$unionid = '';
		if(isset($user_info->unionid)){
			$unionid = $user_info->unionid;
		}
		
		$data = array(
				'nickname' => $nickname,
				'type'=>'wechat',
				'unionid'=> $unionid,
				'headimgurl'=> $headimgurl
				
		);
		$this->login($data);
		if(!empty($redirect)){
			header('location:'.$redirect);
		}
	}
	
	
	/**
	 * @name: wx
	 * @创建者： xxxxxx
	 * @return： 微信手机端登录
	 * @create:  2016-7-18 下午8:02:04
	 */
	public function wx(){
		$appid = WX_MP_APPID;
		$redirect = $this->input->get('redirect');
		$redirect_url = 'https://mall.xxxxxx.com';
		if(!empty($redirect) && isset($this->arr_urls[$redirect])){
			$redirect_url = urlencode($this->arr_urls[$redirect]);
		}
		$redirect_uri = $this->host_url . '/weixin/oauth/mp';
		if(!empty($redirect_url)){
			$redirect_uri = $this->host_url . '/weixin/oauth/mp?redirect='.$redirect_url;
		}
		$url = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid='.$appid.'&redirect_uri='.$redirect_uri.'&response_type=code&scope=snsapi_userinfo&state=123#wechat_redirect';
		header("Location:".$url);
	}	
	
	/**
	* @name: out 
	* @创建者： xxxxxx
	* @作　用： 注销
	* @create:  2016-8-13 上午9:40:09
	*/
	public function out(){
		setcookie("xxxxxxunionid", "", time()-3600,'/','.xxxxxx.com');
		unset($_COOKIE['xxxxxxunionid']);
		header("Location:https://mall.xxxxxx.com");
	}
	
	/**
	 * @name: pay_oauth
	 * @创建者： xxxxxx
	 * @作　用： 获取openid
	 * @create:  2016-9-22 上午11:30:39
	 */
	public function pay_oauth(){
		$xxxxxxunionid = isset($_COOKIE['xxxxxxunionid'])?$_COOKIE['xxxxxxunionid']:'';
		if(empty($xxxxxxunionid)){
			$this->show_error_msg('您还没有登录',500);
		}
		$code = isset($_GET['code'])?$_GET['code']:'';
		$state = $_GET['state'];
		$redirect = isset($_GET['redirect'])?$_GET['redirect']:$this->host_url;
		if(empty($code)){
			header('location:'.$redirect);
		}
		$appid = WX_MP_APPID;
		$appsecret = WX_MP_APP_SECRET;
		if (empty($code)) {
			$this->show_error_msg('授权失败',500);
		}
		//通过code获取access_token
		$token_url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid='.$appid.'&secret='.$appsecret.'&code='.$code.'&grant_type=authorization_code';
		$token = json_decode(file_get_contents($token_url));
		if (isset($token->errcode)) {
			$this->show_error_msg('获取ACCESS_TOKEN失败,请联系管理员',500);
		}
		$access_token_url = 'https://api.weixin.qq.com/sns/oauth2/refresh_token?appid='.$appid.'&grant_type=refresh_token&refresh_token='.$token->refresh_token;
		//转成对象
		$access_token = json_decode(file_get_contents($access_token_url));
		if (isset($access_token->errcode)) {
			$this->show_error_msg('获取ACCESS_TOKEN失败,请联系管理员',500);
		}
		$user_info_url = 'https://api.weixin.qq.com/sns/userinfo?access_token='.$access_token->access_token.'&openid='.$access_token->openid.'&lang=zh_CN';
	
		$user_info = json_decode(file_get_contents($user_info_url),TRUE);
		if (isset($user_info->errcode)) {
			$this->show_error_msg('获取用户信息失败,请联系管理员',500);
		}
		$openid = !empty($user_info['openid'])?$user_info['openid']:'';
		if (empty($openid)) {
			$this->show_error_msg('openid信息失败,请联系管理员',500);
		}
		header('location:https://mall.xxxxxx.com/order/wx_online_pay?openid='.$openid);
		exit;
	
	}
}