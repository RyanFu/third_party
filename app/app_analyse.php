<?php
/**
* @create: 2014-9-25
* @author: xiongjianbang
* @name:Apk_analyse类
* @description:游戏包文件分析类
* 
* 因为此类中包含的字段与game_model里的传参重大关联，特写意义如下
* 
* $arr_apk = array(
*       'package_name'    =>'包名',
*       'version_code'       =>'版本号，一般为数字，如：220',
*       'version_name'      =>'版本名称 如 ：1.2.4',  
*       'sign'                       => '包签名',
*       'game_name'         => 'APK包的名称，即中文标题',
*       'zh_desc'                =>'中文描述'
*       'en_title'                  => '英文标题'
*       'en_desc'               => '英文描述'
*       'save_path'            =>  '上传APP文件的相对路径 如：/game/2014/09/25/com.teyonjp.drawslasher_5423fea49cc81.apk'
*       'file_size'                => '文件大小'
*       'ico_img'                => 'ICO 图片的相对路径 如：/game/2014/10/10/ca2951be1c84329a97f39651d3c52bad.jpg'
*       'is_mzwsdk'           => '是否打入拇指玩SDK'
*       'moga'                    => '是否支持手柄'
*       'screenshot_img'  => '手机截图，这是一个原始图片的相对地址数组，如 array('/game/2014/10/10/ca2951be1c84329a97f39651d3c52bad.jpg')'
*       ''
* );
* 
* 
* $gpk_arr = array(
*           'package_name'   => '包名';
*			'version_code'	   =>	'版本号，一般为数字，如：220',
*			'version_name'	   =>	'版本名称 如 ：1.2.4',  
*			'cpu'                       =>  CPU号，在新版中对应GPU;
*			'sign'                      =>  '包签名',
* )
*/

require_once(APPPATH.'/libraries/encryp.class.php');
class App_analyse{
	
	private $apk_file;
	private $arr_errors;
	private $game_model;
	
	public function __construct($config=array()){
	    if(!empty($config)){
	       $this->apk_file = $GLOBALS['APK_UPLOAD_DIR']  . $config['file_path'];
	    }
	    if(empty($config['file_path']) && strlen($config['file_path'])<=4 ){
	    	exit('没有获取到文件路径');
	    }
	    set_time_limit(0);
	   $this->arr_errors = array(
	   			'apk_not_verify' => 'APK文件错误',
	   		    'badging_bad' => '分析失败',
	   		    'empty_package_name' => '获取不到包名',
	   		   'empty_version_code' => '获取不到版本号',
	   		   'empty_version_name' => '获取不到版本名称',
	   );
	}
	
	
	/**
	 * @name:apk_dec
	 * @description: 解密apk
	 * @param: apk路径
	 * @return: 直接执行命令，无返回值
	 * @author: Xiong Jianbang
	 * @create: 2015-2-13 上午11:43:14
	 **/
	private function apk_dec($apk_path){
		$commend=$GLOBALS['CLI_JAVA_HOME'].'java -jar d '.$apk_path;
		exec($commend);
	}
	
	/**
	* @name:get_sign
	* @description:获取sign值
	* @param:$apk_path=apk的绝对路径
	* @return: 返回sing值
	* @create: 2014-10-10
	* @author: xiongjianbang
	*/	
	private function get_sign($apk_path){
		//先解密
		$command=$GLOBALS['CLI_JAVA_HOME'] . 'java -jar '. $GLOBALS['CLI_CERT_JAR_HOME'] .'  '.$apk_path." 2>&1";
		exec($command,$arr);
		return $arr[0];
	}
	
	/**
	* @name:process
	* @description:分析APK包文件
	* @param:NULL
	* @return:返回包的信息数组
	*     类似 array(
	*     		'package_name'=> '包名',
	*     		'version_code'=> '版本号',
	*          'version_name'=>'版本名称',
	*          'file_size' => '文件大小'
	*     )
	* @create: 2014-9-28
	* @author: xiongjianbang
	*/
	public function apk_process(){
		$arr_apk =  $this->apk_analyse();
		//google play抓取其他信息
// 		$arr = $this->get_info_from_gp($arr_apk['package_name']);
// 		if(!is_empty($arr)){
// 			$arr_apk = array_merge($arr_apk,$arr);
// 		}
		return $arr_apk;
	}
	
	/**
	* @name:apk_analyse
	* @description:APK文件分析
	* @param:无
	* @return:apk数组
	* array(
	*      "package_name" => "包名",
	*      "version_code"    => "版本号",
	*      "version_name"   => "版本名称",
	*      "sign"                    => "SIGN签名",
	*      "game_name"      => "APK名称,即游戏包名",
	*      "save_path"          => "保存地址",
	*      "file_size"             => "文件大小",
	*      "ico_img"              => "ICO图片",
	*      "zip_kyx_kdg"       => "手柄配置的内容",
	*      "zip_kyx_kdr"        => "遥控器配置内容",
	*      "is_mzwsdk"         => "是否打入拇指玩的sdk",
	*      "moga"                  => "是否支持游戏手柄"
	* );
	* @create: 2014-10-13
	* @author: xiongjianbang
	*/
	public function apk_analyse(){
		if($this->verify_not_apk_file()){
			$this->delete_uploaded_apk_file();
			exit($this->arr_errors['apk_not_verify'].$this->apk_file);
			return FALSE;
		}
		//使用aapt工具分析
		exec($GLOBALS['CLI_AAPT_HOME']."  dump badging  {$this->apk_file}" , $badging); //$badging是保存返回数据之用
		if(empty($badging) || !is_array($badging)){
			$this->delete_uploaded_apk_file();
			exit($this->arr_errors['badging_bad']);
			return FALSE;
		}
		$str = implode(',', $badging);
		$arr_apk = array();
		// 取到包名
		preg_match('/package: name=\'(.*?)\'/', $str,$match);
		$arr_apk['package_name'] = trim($match[1]);
		unset($match);
		if(empty($arr_apk['package_name'])){
			$this->delete_uploaded_apk_file();
			exit($this->arr_errors['empty_package_name']);
			return FALSE;
		}
		//取到版本号
		preg_match('/versionCode=\'(.*?)\'/', $str,$match);
		$arr_apk['version_code'] = trim($match[1]);
		unset($match);
		if(empty($arr_apk['version_code'])){
			$this->delete_uploaded_apk_file();
			exit($this->arr_errors['empty_version_code']);
			return FALSE;
		}
		//取到版本名称
		preg_match('/versionName=\'(.*?)\'/', $str,$match);
		$arr_apk['version_name'] = trim($match[1]);
		unset($match);
		if(empty($arr_apk['version_name'])){
			$this->delete_uploaded_apk_file();
			exit($this->arr_errors['empty_version_name']);
			return FALSE;
		}
		//获取SIGN签名
		$arr_apk['sign']=$this->get_sign($this->apk_file);
		//获取APK名称
		preg_match('/application: label=\'(.*?)\'/', $str,$match);
		$arr_apk['game_name'] = trim($match[1]);
		unset($match);
		//APK的保存地址
		$save_path = str_replace($GLOBALS['APK_UPLOAD_DIR'], '', $this->apk_file);
		$arr_apk['save_path'] = $save_path;   //类似于/game/2014/09/25/com.teyonjp.drawslasher_5423fea49cc81.apk
		//文件大小
		$arr_apk['file_size'] = filesize($this->apk_file);
		//获取ico图片
		preg_match('/icon=\'(.*?)\'/', $str,$match);
		$res_ico = trim($match[1]);
		unset($match);
		$zip = new ZipArchive();
		$rs = $zip->open($this->apk_file);
		if($res_ico){
    		if($rs == TRUE){
    		    $ext = get_extension($res_ico);
    		    $zipstring=$zip->getFromName($res_ico);
    		    $save_path = $this->get_date_save_path();
    		    $filename = $save_path . md5(uniqid().rand()) .'.'.$ext;
    		    file_put_contents($filename,$zipstring);
    		    $arr_apk['ico_img'] = str_replace($GLOBALS['APK_GP_ICO_DIR'], '', $filename);
    		}
		}
		//=======begin 获取游戏手柄键位文件位置
		if($rs == TRUE){
			//kyx_data:SDK文件的内容
			//$zip_kyx_data=$zip->getFromName('assets/kyx_data');
			//$arr_apk['zip_kyx_data'] = $zip_kyx_data;
			
			////kyx_kdg:手柄配置的内容(见com.deadmage.shadowblade.json文件)
			
			$zip_kyx_kdg=$zip->getFromName('assets/kyx_ad.png');
			$arr_apk['zip_kyx_kdg'] = $this->decrypt($zip_kyx_kdg);
			//如果新版本的没有数据，则读旧版的
			if(is_empty($arr_apk['zip_kyx_kdg'])&& strlen($arr_apk['zip_kyx_kdg'])<10){
				//旧版的文件
				$zip_kyx_kdg=$zip->getFromName('assets/kyx_kdg');
				$arr_apk['zip_kyx_kdg'] = $this->decrypt($zip_kyx_kdg,'starystarynight1');
			}
			
			//kyx_kdr:遥控器配置内容
			$zipkyx_kdr=$zip->getFromName('assets/kyx_img.jpg');
			$arr_apk['zip_kyx_kdr'] = $this->decrypt($zipkyx_kdr);
			//如果新版本的没有数据，则读旧版的
			if(is_empty($arr_apk['zip_kyx_kdr'])&& strlen($arr_apk['zip_kyx_kdr'])<10){
				//旧版的文件
				$zipkyx_kdr=$zip->getFromName('assets/kyx_kdr');
				$arr_apk['zip_kyx_kdr'] = $this->decrypt($zipkyx_kdr,'starystarynight1');
			}
			
			//kyx_md5:SDK包的MD5
			$zip_kyx_md5=$zip->getFromName('assets/kyx_md5');
			$arr_apk['zip_kyx_md5'] = $zip_kyx_md5;

			//kyx_version:SDK的版本 
			$zip_kyx_version=$zip->getFromName('assets/kyx_version');
			if(!is_empty($zip_kyx_version)){
				//把数据的最后4位二进制的数字转回十进制的数字
				$zip_kyx_version_tmp = unpack('C*',$zip_kyx_version);
				$zip_kyx_version = array_pop($zip_kyx_version_tmp);
			}
			$arr_apk['zip_kyx_version'] = $zip_kyx_version;
			
			//kyx_mode:游戏操控模式
			/*
			 * 	1、mouse：代表鼠标游戏
				2、virtual：代表虚拟按键游戏
				3、无值或gamepad：代表手柄游戏
				4、mobilecontroller：代表支持模拟手柄 
			 */
			$zip_kyx_mode=$zip->getFromName('assets/kyx_mode');
			if($zip_kyx_mode=='mouse'){
				$zip_kyx_mode = 1;
			}elseif($zip_kyx_mode=='virtual'){
				$zip_kyx_mode = 2;
			}elseif($zip_kyx_mode=='mobilecontroller'){
				$zip_kyx_mode = 4;
			}else{
				$zip_kyx_mode = 3;
			}
			$arr_apk['zip_kyx_mode'] = $zip_kyx_mode;
			
			//emulator_type:PSP模拟器游戏的标记
			/*
			 * 直接读取字符串，目前有三种情况
				psp：代表为PSP游戏
				nes：代表为NES游戏
				如果没有文件，代表非模拟器游戏
			*/
			$zip_kyx_emulator_type=$zip->getFromName('assets/emulator_type');
			$arr_apk['zip_kyx_emulator_type'] = $zip_kyx_emulator_type;
			
		
		}
		$zip->close();
		
		//=======end 获取游戏手柄键位文件位置
		//获取是否打入拇指玩的sdk
		$arr_apk['is_mzwsdk'] = $this->get_is_mzwsdk($this->apk_file);
		if($this->get_is_moga()){
		    $arr_apk['moga'] = TRUE;
		}
		return $arr_apk;
	}
	/**
	 * @name:decrypt
	 * @description:解密数据(快游戏特别加密算法（针对AES)）
	 * @param:要解密的数据内容
	 * @param:解密KEY(为了与旧版的加密同时用)
	 * @return:解密后的数据
	 * @create: 2014-12-1
	 * @author: chengdongcai
	 */
	public function decrypt($sStr,$key='') {
		if(is_empty($key)){//如果为空则是设定的KEY
			$key = AES_KEY_CBC;
		}
		
		$decrypted= mcrypt_decrypt(
				MCRYPT_RIJNDAEL_128,
				$key,//加密KEY（要与客户端一致）
				$sStr,
				MCRYPT_MODE_CBC,
				AES_KEY_CBC_IV//设定IV值（要与客户端一致）
		);
		$dec_s = strlen($decrypted);
		$padding = ord($decrypted[$dec_s-1]);
		$decrypted = substr($decrypted, 0, -$padding);
		return $decrypted;
	}
	
	/**
	 * @name:get_is_mzwsdk
	 * @description: 是否打入拇指玩sdk包
	 * @param:apk文件
	 * @author: Xiong Jianbang
	 * @create: 2014-10-23 下午4:09:39
	 **/
	public function get_is_mzwsdk($apk_file=NULL){
	    if(is_empty($apk_file)){
	    	return 0;
	    }
	    require APPPATH.'/libraries/apk_parser.php';
	    $p = new Apk_parser();
	    $res = $p->open($apk_file);
	    $xml = $p->getXML();
	    preg_match('/android:name=\"com.muzhiwan.inject\"/', $xml,$output);
	    if($output){
	       return 1;
	    }else{
	       return 0;
	    }
	}
	
	/**
	 * @name:get_is_moga
	 * @description: 是否支持游戏手柄
	 * @param: 无
	 * @return: 无
	 * @author: Xiong Jianbang
	 * @create: 2014-10-28 下午4:05:16
	 **/
	public function get_is_moga(){
	    $zip = new ZipArchive;
	    $res = $zip->open($this->apk_file);
	    if ($res === TRUE) {
	        $temp_dir = md5(uniqid().rand());
	        $dex_path =  $GLOBALS['APK_UPLOAD_DIR'] .'/game/'.$temp_dir.'/';
	        create_my_file_path($dex_path);
	        $zip->extractTo($dex_path, array('classes.dex'));
	        $zip->close();
	        $dex_file = $dex_path .'classes.dex';
	        $out_path = $dex_path . 'out/';
	        create_my_file_path($out_path);
	        $commend=$GLOBALS['CLI_JAVA_HOME'].'java -jar  '.$GLOBALS['BAKSMAILI_JAR_HOME'].' '.$dex_file.' -o '.$out_path;
	        exec($commend);
	        $mogo_file = $out_path. 'com/bda/controller/Constants.smali';    
	        if(file_exists($mogo_file)){
	            del_dir($dex_path);
	        	return TRUE;
	        }else{
	            del_dir($dex_path);
	        	return FALSE;
	        }
	    } 
	    else {
	        return FALSE;
	    }
	}
	
	
	
	/**
	* @name:gpk_analyse
	* @description:分析GPK文件
	* @param:无
	* @return:
	*     array(
	*              'package_name' => 包名
	*              'version_code'   =>版本号
	*              'version_name'  => 版本名称
	*              'cpu' => CPU信息
	*              'sign'=> 签名信息
	*     )
	* @create: 2014-10-11
	* @author: xiongjianbang
	*/
	public function gpk_process(){
		//UNZIP解压缩配置文件分析出相应的数据
		$zip = new ZipArchive();
		$gpk_arr = array();
		$rs = $zip->open($this->apk_file);
		if($rs == TRUE){
			$zipstring=$zip->getFromName("mainifest.dat");
			//解密
			$de			= 		new Encryption();
			$destr		=		$de->Decrypt(base64_decode($zipstring));
			//数组
			$destr		=		mb_convert_encoding($destr, "utf-8", "gbk");
			$arr			=		json_decode($destr, true);
			$gpk_arr['package_name']	= $arr['apkBaseInfo']['packageName'];
			$gpk_arr['version_code']		=	$arr['apkBaseInfo']['versionCode'];
			$gpk_arr['version_name']		=	$arr['apkBaseInfo']['versionName'];
			$gpk_arr['cpu']                       =  $arr["gpkBaseInfo"]["cpuType"];
			$gpk_arr['sign']                      =  $this->get_gpk_sign();
			
			$save_path =  $this->get_app_save_path();			
			
			//解压目录
			$extract_dir = $GLOBALS['APK_UPLOAD_DIR'] . '/gpkunzip';
			create_my_file_path($extract_dir);
			$zip->extractTo($extract_dir);
			
			$files = array();
			$unzip_filesize = 0;
			//获取gpk内的文件列表
			for ($i = 0; $i < $zip->numFiles; $i++) {
			    $exta_file = $zip->getNameIndex($i);
			    $unzip_filesize += filesize($extract_dir . '/' . $zip->getNameIndex($i));
			    $files[] = $exta_file;
			}
			//删除解压目录 
			@del_dir($extract_dir);
			//GPK文件解压后的大小
			$gpk_arr['unzip_filesize'] = $unzip_filesize;
			
			$copypath = $arr['dataBaseInfo']['copyPath'];
			//mainifest.dat配置文件中，copyPath参数的值包含“Android/obb”
			if(strstr($copypath,'Android/obb') && !is_empty($files)){
// 				print_r($files);
			    foreach ($files as $value) {
			        //if(strstr($value,'patch')){
					if( substr($value,0,1)!='.' && substr($value,-4)=='.obb' ){//如果是以OBB结尾的则要提取
				        //解压出来的文件类似 ： mobi.byss.gun3\patch.133.mobi.byss.gun3.obb
			            $arr_obb =  explode('\\', $value);
			            $str_obb = end($arr_obb);
			            unset($arr_obb);
			            $arr_obb =  explode('.', $str_obb);
			            $from_version_code = intval($arr_obb[1]);//获取最低版本号
			            $zip->extractTo($save_path,$value);
			            $save_old_obb_file = $save_path  .  $value; 
			            $save_new_obb_file = $save_path  .  str_replace($gpk_arr['package_name']."\\", '', $value); //去掉前面的包名目录
			            rename($save_old_obb_file, $save_new_obb_file);//将文件重新命名
			            $patch_obb_file = str_replace($GLOBALS['APK_UPLOAD_DIR'], '', $save_new_obb_file);//OBB文件的相对路径
			            $gpk_arr['obb'][] = array(
			            	'from_version_code' => $from_version_code,
			                'patch_obb_file' =>  $patch_obb_file,
							'patch_file_type'=> 2//是OBB文件
			            );
			        //如果是application.apk文件，则也要保留
			        }else if( substr($value,0,1)!='.' && substr($value,-15)=='application.apk'){
			        	$save_new_obb_file =  $this->get_app_save_path(time()."apk");//APK文件存放路径
						$zip->extractTo($save_new_obb_file,$value);
			        	$save_new_obb_file .= $value;
						//拿签名
						//$gpk_arr['sign']   =  $this->get_sign($save_new_obb_file);
			        	$patch_obb_file = str_replace($GLOBALS['APK_UPLOAD_DIR'], '', $save_new_obb_file);//OBB文件的相对路径
			        	$gpk_arr['obb'][] = array(
			        			'from_version_code' => 0,
			        			'patch_obb_file' =>  $patch_obb_file,
								'patch_file_type'=> 1//是APK文件
			        	);
			        }
			    }
			}
// 			print_r($gpk_arr);
// 			exit;
			unset($arr);
			$zip->close();
		}
		return $gpk_arr;
	}
	
	
	/**
	 * @name:get_gpk_sign
	 * @description: 获取GPK的sign值
	 * @param: 无
	 * @return: 返回GPK的sign值
	 * @author: Xiong Jianbang
	 * @create: 2014-11-3 下午7:57:42
	 **/
	private function get_gpk_sign(){
	    $uniqid="";
	    //解压
	    $flag=true;
	    $zip=new ZipArchive();
	    $res=$zip->open($this->apk_file);
	    if($res===true){
	        if(!$zip->extractTo($GLOBALS['APK_UPLOAD_DIR'].'/','application.apk')){
	            throw new Exception('提取application.apk失败');
	            $flag=false;
	        }
	    }else{
	        throw new Exception('解压失败');
	        $flag=false;
	    }
	    if($flag){
	        $apk_path=$GLOBALS['APK_UPLOAD_DIR'].'/application.apk';
	        //先解密
// 	        $this->apk_dec($apk_path);
	        //算签名
	        $sign = $this->get_sign($apk_path);
	        //删除提取的apk文件
	        unlink($GLOBALS['APK_UPLOAD_DIR'].'/application.apk');
	    }
	    return $sign;
	}
	
	/**
	 * @name:create_diff_apk_file
	 * @description: 对APK生成差异文件
	 * @param: $old_file=上一个版本的APK路径，$diff_file=差异文件名称
	 * @author: Xiong Jianbang
	 * @create: 2014-11-4 下午3:17:05
	 **/
	public function create_diff_apk_file($old_file,$new_file,$diff_file){
	    if(is_empty($old_file) || !is_file($old_file)){
	        return FALSE;
	    }
		$save_path =  $this->get_app_save_path();
		$diff_file = $save_path . $diff_file;
		exec($GLOBALS['CLI_BSDIFF_HOME']."  $old_file $new_file $diff_file");
		return str_replace($GLOBALS['APK_UPLOAD_DIR'], '', $diff_file);
	}
	
	/**
	* @name:get_info_from_gp
	* @description:抓取google play的详细信息
	* @param:
	*      $package_name=包名,
	* @return:
	*  			$arr  =  array(
	*  					'ico_img'  => '',
	*  					'zh_desc'  => '',
	*  					'en_desc'  => '',
	*  					'en_title'  => '',
	*  			)
	* @create: 2014-9-29
	* @author: xiongjianbang
	*/
	public function get_info_from_gp($package_name=NULL){
		if(empty($package_name)){
			return FALSE;
		}
		set_time_limit(0);
		$arr = array();
		//先抓取中文页面
		$zh_url = "https://play.google.com/store/apps/details?id=$package_name&hl=zh-CN";
		if(is_url_exists($zh_url)){
    		$zh_html = $this->curl_use_get($zh_url, 300);
    		$save_path =  $this->get_date_save_path();
    		//抓取图片
    		if($zh_html){
    			//匹配手机截图
    		    $arr['screenshot_img'] = $this->get_screenshot_from_gp($zh_html);
    			//匹配中文描述
        	   $arr['zh_desc'] = $this->get_zh_desc_from_gp($zh_html);
    		}
		}
		//再抓取英文页面
		$en_url = "https://play.google.com/store/apps/details?id=$package_name&hl=en";
		if(is_url_exists($en_url)){
    		$en_html = $this->curl_use_get($en_url, 300);
    		if($en_html){
    			//英文标题
    		    $arr['en_title'] = $this->get_en_title_from_gp($en_html);
    			//英文描述
        		$arr['en_desc'] = $this->get_en_desc_from_gp($en_html);
    		}
		}
		if(!isset($zh_html) && !isset($en_html)){
			return FALSE;
		}
		return $arr;
	}
	
	/**
	 * @name:get_zh_tilte_from_gp
	 * @description: 获取中文描述
	 * @param: 包含中文的HTML文本
	 * @return: 中文描述
	 * @author: Xiong Jianbang
	 * @create: 2014-10-25 上午10:40:41
	 **/
	public function get_zh_desc_from_gp($zh_html){
	    $str = '';
	    if(preg_match('/<div class=\"id-app-orig-desc\">(.*?)<\/div>/', $zh_html,$match)){
	        $str = addslashes($match[1]);
	        unset($match);
	    }
// 	    $arr['zh_desc'] = addslashes(preg_replace('/[\[\]\{\}]/', '', $match[1]));
	    return $str;
	}
	
	/**
	 * @name:get_screenshot_from_gp
	 * @description: 抓取APP截图
	 * @param: 包含中文的HTML文本
	 * @return: 获取截图
	 * @author: Xiong Jianbang
	 * @create: 2014-10-25 上午10:29:44
	 **/
	public function get_screenshot_from_gp($zh_html){
	    $save_path =  $this->get_date_save_path();
	    $arr = array();
	    if(preg_match('/<div class=\"thumbnails-wrapper\">([\s\S]*?)<\/div>/', $zh_html,$match)){
	        $str_img_screens = $match[1];
	        unset($match);
	        preg_match_all('/src=\"(.*?)\"/', $str_img_screens,$match);
	        if(!is_empty($match[1])){
	            $arr_img_temp = $match[1];
	            $arr_img_temp = array_unique($arr_img_temp);
	            $regex = '/^((http|https):\/\/)+[\s\S]*?/'; //检查图片地址是否合法
	            $arr_img_screens = array();
	            //初步处理图片
	            foreach ($arr_img_temp as $value) {
	            	if(preg_match($regex,$value)){
	            	    $value = str_replace('h310', 'h900', $value);
	            		$arr_img_screens[] = $value;
	            	}
	            }
				//限定上传的时候只采5张图
	            $arr_img_screens = array_slice($arr_img_screens, 0, 5);
	            $arr_image = array();
	            $arr = array();
	            foreach ($arr_img_screens as $img_url) {
	                if(!is_empty($img_url)){
	                    $arr_image[] = save_remote_image($img_url,$save_path);
	                }
	            }
	            if(!is_empty($arr_image)){
	                foreach ($arr_image as $value) {
	                    if(!empty($value)){
	                        $arr[] =  str_replace($GLOBALS['APK_GP_ICO_DIR'], '', $value);
	                    }
	                }
	                unset($arr_image);
	            }
	        }
	        unset($match);
	    }
	    return $arr;
	}
	
	/**
	 * @name:get_en_title_from_gp
	 * @description: 获取英文标题
	 * @param: 包含英文的HTML文本
	 * @return: 英文标题
	 * @author: Xiong Jianbang
	 * @create: 2014-10-25 上午10:22:21
	 **/
	public function get_en_title_from_gp($en_html){
	    $str = '';
	    if(preg_match('/<div class="document-title" itemprop="name">(.*?)<\/div>/', $en_html,$match)){
	        $str = strip_tags($match[1]);
	        unset($match);
	    }
// 	    $str = addslashes(preg_replace('/[\[\]\{\}]/', '', strip_tags($match[1])));
	    return $str;
	}
	
	/**
	 * @name:get_en_desc_from_gp
	 * @description: 获取英文描述
	 * @param: 包含英文的HTML文本
	 * @return: 英文描述
	 * @author: Xiong Jianbang
	 * @create: 2014-10-25 上午10:23:53
	 **/
	public function get_en_desc_from_gp($en_html){
	    $str = '';
	     if(preg_match('/<div class=\"id-app-orig-desc\">(.*?)<\/div>/', $en_html,$match)){
    			$str = addslashes($match[1]);
    			unset($match);
    	}
//     	addslashes(preg_replace('/[\[\]\{\}]/', '', $match[1]));
	    return $str;
	}
	
	/**
	 * @name:get_zh_title_from_gp
	 * @description: 获取中文标题
	 * @param: 包含中文的HTML文本
	 * @return: 中文标题
	 * @author: Xiong Jianbang
	 * @create: 2014-10-25 上午10:55:08
	 **/
	public function get_zh_title_from_gp($zh_html){
	    $str = '';
	    preg_match('/<div class=\"document-title\" itemprop=\"name\">([\s\S]*?)<\/div>/', $zh_html,$match);
	    $str = strip_tags($match[1]);
// 	    $str = addslashes(preg_replace('/[\[\]\{\}]/', '', strip_tags($match[1])));
	    unset($match);
	    return $str;
	}
	
	/**
	 * @name:get_game_ico_from_gp
	 * @description: 获取游戏图标ICO
	 * @param: 包含中文的HTML文本
	 * @return: 游戏图标ICO
	 * @author: Xiong Jianbang
	 * @create: 2014-10-25 上午11:07:19
	 **/
	public function get_game_ico_from_gp($zh_html){
	    $str = '';
	    $save_path =  $this->get_date_save_path();
	    preg_match('/<img class=\"cover-image\" src=\"(.*?)\"/', $zh_html,$match);
	    $gp_ico_url = $match[1];
	    if(!empty($gp_ico_url)){
	        $str = save_remote_image($gp_ico_url,$save_path);
	        $str= str_replace($GLOBALS['APK_GP_ICO_DIR'], '', $str);
	    }
	    unset($match);
	    return $str;
	}
	
	
	/**
	 * @name:get_file_size_from_gp
	 * @description: 获取文件大小
	 * @param: 包含中文的HTML文本
	 * @return: 文件大小的值
	 * @author: Xiong Jianbang
	 * @create: 2014-10-25 上午11:07:04
	 **/
	public function get_file_size_from_gp($zh_html){
	    preg_match('/<div class=\"content\" itemprop=\"fileSize\">([\s\S]*?)<\/div>/', $zh_html,$match);
	    $str = strip_tags($match[1]);
	    unset($match);
	    return $str;
	}
	
	/**
	 * @name:get_current_version_from_gp
	 * @description: 获取当前版本
	 * @param: 包含英文的HTML文本
	 * @return: 当前版本
	 * @author: Xiong Jianbang
	 * @create: 2015-2-13 下午2:17:37
	 **/
	public function get_current_version_from_gp($en_html){
	    $str = '';
	    preg_match('/<div class=\"content\" itemprop=\"softwareVersion\">([\s\S]*?)<\/div>/', $en_html,$match);
	    $str = strip_tags($match[1]);
	    unset($match);
	    return $str;
	}
	
	/**
	 * @name:get_date_save_path
	 * @description: 获取保存路径目录名称
	 * @param: 无
	 * @return: $save_path = 目录名称
	 * @author: Xiong Jianbang
	 * @create: 2014-10-22 上午11:31:34
	 **/
	private function get_date_save_path(){
	    $date = date('Y/m/d');
	    $save_path =  $GLOBALS['APK_GP_ICO_DIR'] .'/game/' .  $date  .'/' ;
	    create_my_file_path($save_path);
	    return $save_path;
	}
	
	
	/**
	 * @name:get_app_save_path
	 * @description: 保存apk,gpk,obb的路径
	 * @param: $mydir 自定义的文件夹名
	 * @return: apk,gpk,obb的路径名称
	 * @author: Xiong Jianbang
	 * @create: 2014-11-4 上午10:41:46
	 **/
	private function get_app_save_path($mydir=""){
	    $date = date('Y/m/d');
	    if($mydir!=""){
	    	$save_path =  $GLOBALS['APK_UPLOAD_DIR'] .'/game/' . $date .'/' . $mydir .'/';
	    }else{
	    	$save_path =  $GLOBALS['APK_UPLOAD_DIR'] .'/game/' .  $date  .'/' ;
	    }
	    
	    create_my_file_path($save_path);
	    return $save_path;
	}
	
	/**
	 * @name:get_all_info_from_gp一般在CLI下使用
	 * @description: 从google play获取APK包的所有信息，包括名称，版本，ICO图片等等,一般在CLI下使用
	 * @param: $package_name=包名
	 * @author: Xiong Jianbang
	 * @create: 2014-10-22 上午11:28:05
	 **/
	public function get_all_info_from_gp($package_name=NULL){
	   if(empty($package_name)){
			return FALSE;
		}
		set_time_limit(0);
		$arr = array();
		//先抓取中文页面
		$zh_html = '';
		$zh_url = "https://play.google.com/store/apps/details?id=$package_name&hl=zh-CN";
		if(is_url_exists($zh_url)){
    		$zh_html = $this->curl_use_get($zh_url, 500);
    		//抓取图片
    		$save_path =  $this->get_date_save_path();
    		if($zh_html){
    			//匹配手机截图
    			$arr['screenshot_img'] = $this->get_screenshot_from_gp($zh_html);
    			unset($match);
    			//匹配中文描述
    			$arr['zh_desc'] = $this->get_zh_desc_from_gp($zh_html);
			    //匹配中文标题
			    $arr['zh_title'] = $this->get_zh_title_from_gp($zh_html);
			    //匹配ico图标
			   $arr['ico_img'] = $this->get_game_ico_from_gp($zh_html);
			    //匹配文件大小
			    $arr['file_size'] =$this->get_file_size_from_gp($zh_html);
    		}
		}
		//再抓取英文页面
		$en_html = '';
		$en_url = "https://play.google.com/store/apps/details?id=$package_name&hl=en";
		if(is_url_exists($en_url)){
    		$en_html = $this->curl_use_get($en_url, 500);
    		if($en_html){
    		    //当前版本
    		    $arr['current_version'] = $this->get_current_version_from_gp($en_html);
    			//英文标题
    		    $arr['en_title'] = $this->get_en_title_from_gp($en_html);
    			//英文描述
    			$arr['en_desc'] = $this->get_en_desc_from_gp($en_html);
    			//是否支持手柄
    			if(strpos($arr['en_desc'],'MOGA')>=0){
    			    $arr['moga'] = TRUE;
    			}
    		}
		}
		if(is_empty($zh_html) && is_empty($en_html)){
			return FALSE;
		}
		return $arr;
	}
	
	
	/**
	 * @name:get_screen_img_from_gp
	 * @description: 只做抓取截图操作
	 * @param: $package_name=包名
	 * @return: $arr_screen_img=保存到本地服务器的截图相对路径数组 
	 * @author: Xiong Jianbang
	 * @create: 2014-10-17 下午4:06:03
	 **/
	public function  get_screen_img_from_gp($package_name){
	    if(empty($package_name)){
	        return FALSE;
	    }
	    $url = "https://play.google.com/store/apps/details?id=$package_name&hl=zh-CN";
	    $arr_header = get_headers($url,TRUE);
	    $str_http = $arr_header[0];
	    if(strstr($str_http, '404')){
	        return FALSE;
	    }
		//采图片的html页面
	    $html = $this->curl_use_get($url,300);
		//采图片
	    $arr =  $this->get_screenshot_from_gp($html);
	    return $arr;
	}
	
	
	/**
	* @name:curl_use_get
	* @description:get方式的curl
	* @param:URL地址
	* @param:执行时间
	* @return:获取根据访问URL地址返回的数据
	* @create: 2014-9-28
	* @author: xiongjianbang
	* @demo:
	* 			$data = array('first_name' => 'John', 'email' => 'john@example.com', 'phone' => '1234567890',  );
	* 			echo curl_use_get('http://www.muzhiwan.com/', $data);
	*/
	private  function curl_use_get($url, $second){
		if(empty($url)){
			return '数据不能为空';
		}
		$ch = curl_init();
	    curl_setopt($ch,CURLOPT_TIMEOUT,$second);
	    curl_setopt($ch, CURLOPT_URL,$url);
	    curl_setopt($ch, CURLOPT_HEADER, 0);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
// 	    curl_setopt($ch,CURLOPT_USERAGENT,"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.1.4322; .NET CLR 2.0.50727)");
	    $data = curl_exec($ch);
	    curl_close($ch);
	    if ($data){
	    	return $data;
	    }else{
	    	return false;
		}
	}
	
	

	/**
	 * @name:delete_uploaded_apk_file
	 * @description:删除上传的apk文件
	 * @param:无
	 * @return:无
	 * @author: Xiong Jianbang
	 * @create: 2015-2-13 下午2:22:29
	 **/
	public function delete_uploaded_apk_file(){
	    if(is_file($this->apk_file)){
		  return  unlink($this->apk_file);
	    }
	    return FALSE;
	}
	
	/**
	 * @name:verify_not_apk_file
	 * @description: 验证apk文件
	 * @param: 无
	 * @return: boolean
	 * @author: Xiong Jianbang
	 * @create: 2014-10-22 下午3:51:03
	 **/
	private function verify_not_apk_file(){
		if(empty($this->apk_file)){
			return TRUE;
		}
		$ext = $this->get_file_ext($this->apk_file);
		if($ext<>'apk'){
			return TRUE;
		}
		if(!is_file($this->apk_file)){
			return TRUE;
		}
	}
	
	/**
	 * @name:muti_remote_image
	 * @description: 并发采集多张图片
	 * @param: $urls=远程图片地址数组,$save_path=保存位置
	 * @author: Xiong Jianbang
	 * @create: 2014-10-22 下午3:51:03
	 **/
	private function  muti_remote_image($urls=array(),$save_path=NULL){
        if(empty($urls) || empty($save_path)){
        	return FALSE;
        }
        $mh = curl_multi_init(); //1.初始化
        $ch = [];
        foreach ( $urls as $i => $url ) {
            $ch[$i] = curl_init($url);
            curl_setopt($ch[$i], CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch[$i], CURLOPT_TIMEOUT, 5);
            curl_setopt($ch[$i], CURLOPT_HTTPHEADER, ['Cookie:CurAreaCode=muzhiwan']);
            curl_multi_add_handle($mh, $ch[$i]); //2.循环增加ch句柄到批处理会话mh
        }
        do {
            $mrc = curl_multi_exec($mh, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);
        while ($active && $mrc == CURLM_OK) {
            if (curl_multi_select($mh) != -1) {
                do {
                    $mrc = curl_multi_exec($mh, $active); //3.运行当前cURL句柄的子连接
                } while ($mrc == CURLM_CALL_MULTI_PERFORM);
            }
        }
        // mime 和 扩展名 的映射
        $mimes=array(
                'image/bmp'=>'bmp',
                'image/gif'=>'gif',
                'image/jpeg'=>'jpg',
                'image/png'=>'png',
        );
        foreach ( $urls as $i => $url ) {
            $headers=get_headers($url, TRUE);
            // 获取响应的类型
            $type=$headers['Content-Type'];
            $ext=$mimes[$type];
            $res = curl_multi_getcontent($ch[$i]); //4.采集数据
             $filename = $save_path.'/'.md5(uniqid().rand()).'.'.$ext;
            file_put_contents($filename, $res);
            curl_multi_remove_handle($mh, $ch[$i]); //5.移除句柄资源
            curl_close($ch[$i]); //6.关闭cURL会话
            $data[] = $filename;
        }
        curl_multi_close($mh); //7.关闭一组cURL句柄
        return $data;
    }
	
    /**
     * @name:get_file_ext
     * @description: 获取文件扩展名
     * @author: Xiong Jianbang
     * @create: 2015-2-13 下午2:25:24
     **/
	private function get_file_ext($filename) {
		return substr($filename, strrpos($filename, '.') + 1);
	}
	
	
	 public function __destruct(){
	 	
	 }
	
}