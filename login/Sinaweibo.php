<?php
/**
 * @copyright: xxxxxxxxxxxx @www.xxxxxx.com
 * @description: QQ第三方登录
 * @file: Sinaweibo.php
 * @author: xiongjianbang
 * @charset: UTF-8
 * @time: 2016-8-30  下午5:02:40
 * @version 1.0
 **/

defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH. 'libraries/SinaWeibo.class.php');

class Sinaweibo extends xxxxxxController {
	
	public function __construct(){
		parent::__construct();
		$this->load->library('session');
	}
	
	/**
	* @name:  index
	* @创建者： xiongjianbang
	* @作　用： 显示页面
	* @create:  2016-8-18 上午11:54:39
	*/
	public function index(){
		$o = new SaeTOAuthV2( WB_AKEY , WB_SKEY );
		$redirect_uri = WB_CALLBACK_URL;
		$redirect = $this->input->get('redirect');
		if(!empty($redirect)){
			$redirect_uri = WB_CALLBACK_URL . '?redirect=' . $redirect;
		}
		$data['sinaweibo_url'] = $o->getAuthorizeURL( $redirect_uri );
		$this->display('front/welcome/index',$data);
	}
	
	
	
	
	/**
	 * @name: callback
	 * @创建者： xiongjianbang
	 * @作　用： 回调地址
	 * @create:  2016-8-31 下午1:32:32
	 */
 	public function callback(){
		$o = new SaeTOAuthV2( WB_AKEY , WB_SKEY );
		$token = '';
		if (isset($_REQUEST['code'])) {
			$redirect_url = 'http://www.xxxxxx.com';
			$code = $this->input->get('code',TRUE);
			if (!empty($code)) {
				$keys = array();
				$keys['code'] = $_REQUEST['code'];
				$keys['redirect_uri'] = WB_CALLBACK_URL;
				try {
					$token = $o->getAccessToken( 'code', $keys ) ;
				} catch (OAuthException $e) {
				}
			}
			
			if ($token) {
				setcookie( 'weibojs_'.$o->client_id, http_build_query($token) );
				$c = new SaeTClientV2( WB_AKEY , WB_SKEY , $token['access_token'] );
				$ms  = $c->home_timeline(); // done
				$uid_get = $c->get_uid();
				$uid = $uid_get['uid'];
				$user_message = $c->show_user_by_id( $uid);//根据ID获取用户等基本信息
				$nickname = isset($user_message['name'])?$user_message['name']:'';
				$headimgurl = isset($user_message['profile_image_url'])?$user_message['profile_image_url']:'';
				$type = 'weibo';
				$unionid =  isset($uid_get['uid'])?$uid_get['uid']:'';
				$newdata = array(
						'nickname'   => $nickname,
						'headimgurl'  => $headimgurl,
						'type'   =>  $type,
						'unionid'   =>  $unionid,
				);
				$this->session->set_userdata($newdata);
				// 		header("Location:/sinaweibo/bind");
				echo '<script>window.location.href="/sinaweibo/bind";</script>';
				// 		echo '<a href="/sinaweibo/bind">恭喜您，新浪微博授权成功，请点击开始下一步流程，谢谢！</a>';
			}else{
				header("Location:/");
			}
		}
 	}
		

		
	
	
	/**
	* @name: bind 
	* @创建者： xiongjianbang
	* @作　用： 跳转
	* @create:  2016-9-23 上午9:21:29
	*/
	public function bind(){
		$params['nickname'] = $this->session->nickname;
		$params['headimgurl'] = $this->session->headimgurl;
		$params['unionid'] = $this->session->unionid;
		$params['type'] =  $this->session->type;
		$this->login($params);
		
	} 
	
	
	
}