<?php
/**
 * @copyright: xxxxxxxxxxxx @www.xxxxxx.com
 * @description: QQ第三方登录 
 * @file: Qq.php
 * @author: xiongjianbang
 * @charset: UTF-8
 * @time: 2016-8-30  下午3:01:42
 * @version 1.0
 **/


defined('BASEPATH') OR exit('No direct script access allowed');

class Qq extends xxxxxxController {
	
	public function __construct(){
		parent::__construct();
	}
	
	
	/**
	* @name:  login
	* @创建者： xiongjianbang
	* @作　用： QQ登录动作
	* @create:  2016-8-30 下午3:02:12
	*/
	public function index(){
		require_once(APPPATH. 'libraries/qq/API/qqConnectAPI.php');
		$qc = new QC();
		$qc->qq_login();
		
	}
	
	/**
	* @name: bind
	* @创建者： xiongjianbang
	* @作　用： bind
	* @create:  2016-9-14 上午10:21:36
	*/
	public function bind(){
		$xxxxxxunionid = isset($_COOKIE['xxxxxxunionid'])?$_COOKIE['xxxxxxunionid']:'';
		if(empty($xxxxxxunionid)){
			$this->show_error_msg('您还没有登录',500);
		}
		require_once(APPPATH. 'libraries/qq/API/qqConnectAPI.php');
		$qc = new QC();
		$qc->qq_login();
	
	}
	
	/**
	* @name: callback 
	* @创建者： xiongjianbang
	* @作　用： 回调测试地址
	* @create:  2016-8-30 下午3:02:25
	*/
	public function callback_1(){
		require_once(APPPATH. 'libraries/qq/API/qqConnectAPI.php');
		$qc = new QC();
		echo '登录成功';
		echo $qc->qq_callback();
		echo $qc->get_openid();
		
	} 

	/**
	* @name: callback 
	* @创建者： xiongjianbang
	* @作　用： 回调方法 
	* @create:  2016-9-14 下午1:17:45
	*/
	public function callback(){
		require_once(APPPATH. 'libraries/qq/API/qqConnectAPI.php');
		$qc = new QC();
		$acs = $qc->qq_callback();
		$open_id = $qc->get_openid();
		$qc = new QC($acs,$open_id);
		$arr_user_info = $qc->get_user_info();
		if(!empty($open_id) && !empty($arr_user_info)){
			$nickname = isset($arr_user_info['nickname'])?$arr_user_info['nickname']:'';
			$headimgurl = isset($arr_user_info['figureurl_qq_2'])?$arr_user_info['figureurl_qq_2']:'';
			$data = array(
					'unionid'=> $open_id,
					'type'=>'qq',
					'nickname' => $nickname,
					'headimgurl'=> $headimgurl
			);
			$this->login($data);
		}
		header('location:/account');
	}
		
	
}