<?php

/**
 * @name:
 * @description: google play抓取功能
 * @param: 
 * @return: 
 * @author: Xiong Jianbang
 * @create: 2014-10-22 上午11:23:03
 **/
class Gp extends MZW_Controller{
    
    public function __construct(){
        parent::__construct();
        error_reporting(0);
        set_time_limit(0);
        $this->load->database();//打开数据库连接
        $this->load->model("admin/video_model");
        require APPPATH.'/libraries/umeng_video/manage_client.class.php'; //
    }
    
    public function test($a=111){
    	echo 111 . $a;
    }
    
    /**
     * @name:get_game_all_info 
     * @description: 使用命令行的方式从google play抓取游戏的所有数据
     *      使用方法
     *      在CLI下环境命令： /usr/bin/php  index.php  api/gp   get_game_all_info   de.mobilebits.soulcraft2    
     *      快速测试：/usr/bin/php  index.php  api/gp   get_game_all_info  com.evozi.deviceid
     *      在WEB访问下：http://www.muzhiwan.test/api/gp/get_game_all_info/de.mobilebits.soulcraft2
     * @param: $gv_package_name=包名
     * @return: 返回JSON格式
     *      zh_title：中文标题 string
     *      en_title：英语标题 string
     *      current_version：当前版本 string
     *      file_size：文件大小 string
     *      ico_img：ICO绝对地址 string  类似 /Data/image/game/2014/10/22/566b1299ae73137512d40536bc8246b1.png
     *      zh_desc：中文描述 string
     *      en_desc：英文描述 string
     *      moga：是否支持手柄 string
     *      screenshot_img：游戏截图绝对地址数组  array
     * @author: Xiong Jianbang
     * @create: 2014-10-22 下午12:25:14
     **/
    public function  get_game_all_info($gv_package_name=NULL){
        if(is_empty($gv_package_name)){
            echo json_encode(array('msg'=>'Parameter cannot be empty','status'=>"401"));
            exit;
        }
      
        $gv_package_name = trim($gv_package_name);
        $sql = 'SELECT `g_id` FROM `mzw_game` WHERE `g_package_name`=? LIMIT 1';
        $query = $this->db->query( $sql, array($gv_package_name) );
        $result   = $query->row();
        if(is_empty($result)){
           echo json_encode(array('msg'=>'The package name does not exist','status'=>"402"));
        	exit;
        }
        $this->load->library('app_analyse');
        $arr_apk_from_gp 	=		 $this->app_analyse->get_all_info_from_gp($gv_package_name);
        if($arr_apk_from_gp){
            $arr_apk_from_gp['package_name'] = $gv_package_name;
            echo json_encode(array('msg'=>$arr_apk_from_gp,'status'=>"200"));
//             echo stripslashes( json_encode( array('msg'=>$arr_apk_from_gp,'status'=>200), JSON_UNESCAPED_UNICODE) );
            exit;
        }else{
        	echo json_encode(array('msg'=>'Returns a failure','status'=>"403"));
        	exit;
        }
    }
    
    
    /**
     * @name:handle_game_info_from_gp
     * @description: 接收数据
     * @param:  POST_KEY= update_json
     * @return:JSON格式 $json = 
     * @author: Xiong Jianbang
     * @create: 2014-10-24 上午10:47:29
     * http://www.muzhiwan.test/api/gp/handle_game_info_from_gp/quasar.bistrocook/game_update_cf/1
     **/
    public function handle_game_info_from_gp($gv_package_name=NULL,$cf=NULL,$g_id=0){
        if(is_empty($gv_package_name) || is_empty($g_id) || is_empty($cf) ||  $cf<>'game_update_cf'){
            echo json_encode(array('msg'=>'Parameter cannot be empty','status'=>"400"));
            exit;
        }
        $json = isset($_POST['update_json'])?trim($_POST['update_json']):NULL;
        $obj = json_decode($json);
        if(!is_empty($obj) && $obj->status==200){
           	$arr = $obj->msg;
        		$arr_apk['g_id'] = intval($g_id);
        		$arr_apk['flag'] = $cf;
        		$arr_apk['game_name'] = isset($arr->zh_title)?$arr->zh_title:'';
        		$arr_apk['en_title'] = isset($arr->en_title)?$arr->en_title:'';
        		$arr_apk['zh_desc'] = isset($arr->zh_desc)?$arr->zh_desc:'';
        		$arr_apk['en_desc'] = isset($arr->en_desc)?$arr->en_desc:'';
        		$arr_apk['package_name'] = isset($arr->package_name)?$arr->package_name:'';
        		$arr_apk['version_name'] = isset($arr->current_version)?$arr->current_version:'';
        		$arr_apk['ico_img'] = isset($arr->ico_img)?$arr->ico_img:'';
        		$arr_apk['screenshot_img'] = isset($arr->screenshot_img)?$arr->screenshot_img:'';
        		$arr_apk['file_size'] = isset($arr->file_size)?size_to_bytes($arr->file_size):'';   //20M  注意：这里要单位换算成字节数!!
        		$arr_apk['moga'] = isset($arr->moga)?$arr->moga:'';
        		$this->load->model( 'admin/game_model','gameModel');
        		$return_json = $this->gameModel->handle_game_app_from_gp($arr_apk);
        		//获取处理版本更新后返回的数据
        		//格式一般为 {"msg":{"is_gpk":"0","down_nums":"0","mgd_id":"42746","gv_id":"104624","g_id":"13626"},"status":"200"}
        		$data = json_decode($return_json,TRUE);
                //进一步处理
            if($data['status']==200){
            	echo json_encode(array('msg'=>$data['msg'],'status'=>"200"));
            	exit;
            }else{
                //这里返回的都是模型里的报错信息，状态码统一为5XX
                echo json_encode(array('msg'=>$data['msg'],'status'=>$data['status']));
                exit;
            }
        }else{
            echo json_encode(array('msg'=>$obj->msg,'status'=>$obj->status));
            exit;
        }
    }
    
    /**
     * @name:get_video_img
     * @description: 抓取视频图片
     * @author: Xiong Jianbang
     * @create: 2015-7-31 上午11:13:09
     **/
    public function get_video_img(){
    	 $img_url = isset($_POST['local_img'])?trim($_POST['local_img']):NULL;
    	 if(empty($img_url)){
    	     exit(json_encode(array('msg'=>'图片不能为空','status'=>400)));
    	 }
    	 $dir =  '/video_img' .date('/Y/m/d/');  //添加模块名作目录一部分
    	 $to_save = $GLOBALS['APK_GP_ICO_DIR'] . $dir;
    	 if(!is_dir($to_save)){
    	     create_my_file_path($to_save,0755);
    	 }
    	 $tmp_img = curl_get_img($img_url,$to_save);
    	 exit(json_encode(array('msg'=>$tmp_img,'status'=>200)));
    }
    
    /**
     * @name:upload_video_file
     * @description: 上传视频的爬虫文件
     * @author: Xiong Jianbang
     * @create: 2015-10-9 上午10:29:37
     **/
    public function upload_video_file(){
        $target_path  = $GLOBALS['APK_UPLOAD_DIR']."/game/video_json/";//接收文件目录
        $full_path = $target_path . basename( $_FILES['uploadedfile']['name']);
        if(move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $full_path)) {
            if(!is_file($full_path)){
                exit(json_encode(array('msg'=>'文件上传失败，请检查','status'=>400)));
            }
            $info = pathinfo($full_path);
            $ext =  $info['extension'];
            if($ext <> 'txt'){
                unlink($full_path);
                exit(json_encode(array('msg'=>'文件格式不正确','status'=>400)));
            }
            $json = @file_get_contents($full_path);
            if(empty($json)){
                exit(json_encode(array('msg'=>'文件内容为空，请检查','status'=>400)));
            }
            $arr_url_data = json_decode($json,TRUE);
            if(empty($arr_url_data)){
                exit(json_encode(array('msg'=>'文件内容解析不成功，请检查','status'=>400)));
            }
            $file_path = str_replace($GLOBALS['APK_UPLOAD_DIR'], '', $full_path);
            $desc = trim(get_var_value('desc'));
            $data = array(
                    'vf_uid' => $_SESSION["sys_admin_id"],
                    'vf_username'=> $_SESSION['sys_admin_name'],
                    'vf_created'=>time(),
                    'vf_server_url'=>$file_path,
                    'vf_file_size' => filesize($full_path),
                    'vf_file_md5' => md5_file($full_path),
                    'vf_ext'=>get_file_ext($full_path),
                    'vf_desc'=>$desc
            );
            $data['vf_urls_count'] = count($arr_url_data);
            $data['vf_server_url'] = $file_path;
            $last_id=$this->video_model->add_simple_file($data);
            unset($data);
            if($last_id){
                foreach ($arr_url_data as $value) {
                    $play_url = isset($value['vRealPalyUrl'])?trim($value['vRealPalyUrl']):NULL;
                    if(empty($play_url)){
                        continue;
                    }
                    $tmp['vf_id'] = $last_id;
                    $tmp['url'] = $play_url;
                    $tmp['title'] = isset($value['vTitle'])?trim($value['vTitle']):0;//标题
                    $tmp['duration'] = isset($value['vTime'])?trim($value['vTime']):'';//播放时长
                    $tmp['author'] = isset($value['vAuthorName'])?trim($value['vAuthorName']):'';//作者名称
                    $tmp['author_img'] = isset($value['vAuthorImgUrl'])?trim($value['vAuthorImgUrl']):'';//作者图片
                    $tmp['img_url'] = isset($value['vImgUrl'])?trim($value['vImgUrl']):'';//视频图片
                    $tmp['play_count'] = isset($value['vPlayCount'])?floatval($value['vPlayCount']):0;//播放次数
                    $tmp['package'] = isset($value['vPackageName'])?$value['vPackageName']:'';//包名
                    $tmp['created'] = time();
                    $tmp['status'] = 999;
                    $url_id=$this->video_model->add_simple_file_url($tmp);
                } 
                if(class_exists('swoole_client')){//如果有扩展，则进行操作
                    $this->client = new swoole_client(SWOOLE_SOCK_TCP);
                    if( !$this->client->connect("127.0.0.1", 9501 , 1) ) {
                        exit(json_encode(array('msg'=>"Error: {$fp->errMsg}[{$fp->errCode}]",'status'=>400)));
                    }
                    $result = $this->video_model->get_video_url_by_file_id($last_id);
                    if(empty($result)){
                        exit(json_encode(array('msg'=>'没有满足条件的url纪录，请检查','status'=>400)));
                    }
                    unset($result);
                    //是否正在运行中
                    $result = $this->video_model->get_video_file_by_id($last_id);
                    if(!empty($result) && $result['vf_status']==1){
                        exit(json_encode(array('msg'=>'该服务已经连接，正在运行中。。。','status'=>400)));
                    }else{
                        $this->video_model->update_file_simple(array('vf_status'=>1),$last_id);
                    }
                    $arr_msg = array(
                            'file_id' =>$last_id,
                    );
                    $str_msg = serialize($arr_msg);
                    $this->client->send( $str_msg );
                    $message = $this->client->recv();
                   exit(json_encode(array('msg'=>'上传成功,生成视频抓取队列，请点击“详情”页面查看当前进度','status'=>200)));
                }else{
                    exit(json_encode(array('msg'=>'服务器未安装swoole扩展','status'=>400)));
                }
            }
        }  else{
            exit(json_encode(array('msg'=>'上传失败','status'=>400)));
        }
    }
    
    
    /**
    public function get_ali_notify(){
    	$tmp_task_txt = $GLOBALS['APK_UPLOAD_DIR']  . '/tmp_alibaichuan_video/tmp_ali_task.txt';
    	$message = file_get_contents("php://input");
    	//         $message = isset($_POST['message'])?$_POST['message']:NULL;
    	if(empty($message)){
    		exit(json_encode(array('msg'=>'数据不能为空','status'=>400)));
    	}
    	$arr = json_decode($message,true);
    	if(empty($arr)){
    		exit(json_encode(array('msg'=>'数据解析错误','status'=>400)));
    	}
    	//任务ID号
    	$taskid = !empty($arr['id'])?$arr['id']:NULL;
    	if(empty($taskid)){
    		exit(json_encode(array('msg'=>'任务ID不能为空','status'=>400)));
    	}
    
    	//保存任务ID号到临时文件，避免重复阿里百川的重复回调
    	if(is_file($tmp_task_txt)){
    		$arr_task_id = file($tmp_task_txt);
    		foreach ($arr_task_id as $key=>$value) {
    			$arr_task_id[$key] = trim($value);
    		}
    		if(in_array($taskid, $arr_task_id)){
    			exit(json_encode(array('msg'=>'该任务已存在','status'=>400)));
    		}else{
    			file_put_contents($tmp_task_txt , $taskid ."\n",FILE_APPEND);
    		}
    	}else{
    		file_put_contents($tmp_task_txt ,$taskid ."\n");
    	}
    
    	$return_status =  !empty($arr['tasks'][0]['status'])?$arr['tasks'][0]['status']:NULL;
    	$sql = "SELECT `id`,`vvl_server_url`,`vvl_sourcetype`,`vvl_title` FROM `video_video_list` WHERE `vvl_water_task_id`='{$taskid}'";
    	$query = $this->db->query( $sql);
    	$res = $query->row_array();
    	if(empty($res)){
    		exit(json_encode(array('msg'=>'该任务不存在','status'=>400)));
    	}else{
    		$vvl_server_url = trim($res['vvl_server_url']);
    		if(empty($vvl_server_url)){
    			exit(json_encode(array('msg'=>'视频URL地址不能为空','status'=>400)));
    		}
    		$sourcetype = $res['vvl_sourcetype'];
    		$title = $res['vvl_title'];
    		$video_id =  intval($res['id']);
    		$vvl_server_url = str_replace('http://kyxvideo.file.alimmdn.com', '', $vvl_server_url);
    		//打完水印后的空间是publicvideo，所以要从这里下载最新的视频
    		$vvl_server_url = 'http://publicvideo.file.alimmdn.com' . $vvl_server_url;
    		//             if(!empty($return_status) && $return_status==4){
    		//                 $data['vvl_server_url'] = $vvl_server_url;
    		//                 $this->video_model->video_info_update($video_id,$data);
    		//             }
    		//如果没有打上水印，则将原来的视频同步到优酷CDN
    		if(!check_url_exists($vvl_server_url)){
    			$vvl_server_url = str_replace('http://publicvideo.file.alimmdn.com', '', $vvl_server_url);
    			$vvl_server_url = 'http://kyxvideo.file.alimmdn.com' . $vvl_server_url;
    		}
    		//             $vvl_server_url = 'http://kyxvideo.file.alimmdn.com' . $vvl_server_url;
    		$dir_name =  parse_url($vvl_server_url, PHP_URL_PATH);
    		$dir_name = dirname($dir_name);
    		$to_save = $GLOBALS['UPLOAD_DIR']. '/tmp_alibaichuan_video'.$dir_name . '/';
    		if(!is_dir($to_save)){
    			create_my_file_path($to_save,0755);
    		}
    		$filename = basename($vvl_server_url);
    		$tmp_video = curl_get_video($vvl_server_url,$to_save,TRUE);
    		if(is_file($tmp_video)){
    			include APPPATH.'/libraries/youku_video/include/YoukuUploader.class.php'; //
    			$client_id = "1de53610657a47f1"; // Youku OpenAPI client_id
    			$client_secret = "4c3f199554dfb64bd863daeab03ded95"; //Youku OpenAPI client_secret
    			//refresh_token的获取步骤如下：
    			/**1,redirect_uri这个地址一定要填到优酷的某个表单里，
    			 *  访问$url = 'https://openapi.youku.com/v2/oauth2/authorize?client_id=1de53610657a47f1&response_type=code&redirect_uri=http://www.xiaolu123.com';
    			获取code授权码；
    			2, $url= 'https://openapi.youku.com/v2/oauth2/token';
    			$vars['client_id'] = $client_id =  '1de53610657a47f1';
    			$vars['client_secret'] = '4c3f199554dfb64bd863daeab03ded95';
    			$vars['grant_type'] ='authorization_code';
    			$vars['redirect_uri'] ='http://www.xiaolu123.com';
    			$vars['code'] ='c9b8e43e7872fcb76a0c8d79eec15f5a';
    			$arr_return = curl_post($url,$vars);
    			print_r($arr_return);
    			获取refresh_token码；
    			3,将refresh_token填入$GLOBALS['UPLOAD_DIR']  . '/token/youku_refresh_token.txt这个文件
    			**/
    
 /**
    
    			$filename = $GLOBALS['UPLOAD_DIR']  . '/token/youku_refresh_token.txt';
    			$refresh_token = file_get_contents($filename);
    			$url = 'https://openapi.youku.com/v2/oauth2/token';
    			$vars['client_id'] = $client_id;
    			$vars['client_secret'] = $client_secret;
    			$vars['refresh_token'] = $refresh_token;
    			$vars['grant_type'] = 'refresh_token';
    			$json = curl_post($url,$vars);
    			$arr_return = json_decode($json,TRUE);
    			if(isset($arr_return['access_token']) && !empty($arr_return['access_token'])){
    				$access_token = $arr_return['access_token'];
    			}
    			if(empty($access_token)){
    				return FALSE;
    			}
    
    			$new_refresh_token = $arr_return['refresh_token'];
    			if($new_refresh_token<>$refresh_token){
    				file_put_contents($filename, $new_refresh_token);
    				$refresh_token = $new_refresh_token;
    			}
    
    
    			$params['access_token'] = $access_token;
    			$params['refresh_token'] = $refresh_token;
    			$params['username'] = "18601964550"; //Youku username or email
    			$params['password'] = md5("kuaiyouxi123"); //Youku password
    
    			$youkuUploader = new YoukuUploader($client_id, $client_secret);
    			$file_name = $tmp_video; //video file
    			try {
    				$file_md5 = @md5_file($file_name);
    				if (!$file_md5) {
    					throw new Exception("Could not open the file!\n");
    				}
    			}catch (Exception $e) {
    				echo "(File: ".$e->getFile().", line ".$e->getLine()."): ".$e->getMessage();
    				return;
    			}
    			$file_size = filesize($file_name);
    			$uploadInfo = array(
    					"title" => $title, //video title
    					"tags" => "小鹿视频", //tags, split by space
    					"file_name" => $file_name, //video file name
    					"file_md5" => $file_md5, //video file's md5sum
    					"file_size" => $file_size //video file size
    			);
    			$progress = false; //if true,show the uploading progress
    			$youku_video_id = $youkuUploader->upload($progress, $params,$uploadInfo);
    			if(!empty($video_id)){
    				if(is_file($tmp_video)){
    					//                         unlink($tmp_video);
    				}
    				$data = array();
    				$tmp_video = str_replace($GLOBALS['UPLOAD_DIR'], '', $tmp_video);
    				$data['vvl_video_id'] = $youku_video_id;
    				$data['vvl_server_url'] = $vvl_server_url;
    				$data['vvl_playurl'] = "http://v.youku.com/v_show/id_{$youku_video_id}.html";
    				$this->video_model->video_info_update($video_id,$data);
    				$arr['status'] = 1;
    				$arr['message'] = $youku_video_id.'上传成功';
    
    				$this->callback_ajax( $arr );
    			}
    		}
    		$arr['status'] = 1;
    		$arr['message'] = '同步错误';
    		$this->callback_ajax( $arr );
    	}
    }
**/
    
    /**
     * @name:get_ali_notify
     * @description: 
     * @param: 
     * @return: 
     * @author: Xiong Jianbang
     * @create: 2015-11-11 下午5:06:48
     **/
    public function get_ali_notify(){
        $tmp_task_txt = $GLOBALS['APK_UPLOAD_DIR']  . '/tmp_alibaichuan_video/tmp_ali_task.txt';
        $message = file_get_contents("php://input");
//         $message = isset($_POST['message'])?$_POST['message']:NULL;
        if(empty($message)){
            exit(json_encode(array('msg'=>'数据不能为空','status'=>400)));
        }
        $arr = json_decode($message,true);
        if(empty($arr)){
            exit(json_encode(array('msg'=>'数据解析错误','status'=>400)));
        }
        //任务ID号
        $taskid = !empty($arr['id'])?$arr['id']:NULL;
        if(empty($taskid)){
            exit(json_encode(array('msg'=>'任务ID不能为空','status'=>400)));
        } 
        
        //保存任务ID号到临时文件，避免重复阿里百川的重复回调
        if(is_file($tmp_task_txt)){
            $arr_task_id = file($tmp_task_txt);
            foreach ($arr_task_id as $key=>$value) {
                $arr_task_id[$key] = trim($value);
            }
            if(in_array($taskid, $arr_task_id)){
                exit(json_encode(array('msg'=>'该任务已存在','status'=>400)));
            }else{
                file_put_contents($tmp_task_txt , $taskid ."\n",FILE_APPEND);
            }
        }else{
            file_put_contents($tmp_task_txt ,$taskid ."\n");
        }
        
        $return_status =  !empty($arr['tasks'][0]['status'])?$arr['tasks'][0]['status']:NULL;
        $sql = "SELECT `id`,`vvl_server_url`,`vvl_sourcetype`,`vvl_title` FROM `video_video_list` WHERE `vvl_water_task_id`='{$taskid}'";
        $query = $this->db->query( $sql);
        $res = $query->row_array();
        if(empty($res)){
            exit(json_encode(array('msg'=>'该任务不存在','status'=>400)));
        }else{
            $vvl_server_url = trim($res['vvl_server_url']);
            if(empty($vvl_server_url)){
                exit(json_encode(array('msg'=>'视频URL地址不能为空','status'=>400)));
            }
            $sourcetype = $res['vvl_sourcetype'];
            $title = $res['vvl_title'];
            $video_id =  intval($res['id']);
            $vvl_server_url = str_replace('http://kyxvideo.file.alimmdn.com', '', $vvl_server_url);
            //打完水印后的空间是publicvideo，所以要从这里下载最新的视频
            $vvl_server_url = 'http://publicvideo.file.alimmdn.com' . $vvl_server_url;
//             if(!empty($return_status) && $return_status==4){
//                 $data['vvl_server_url'] = $vvl_server_url;
//                 $this->video_model->video_info_update($video_id,$data);
//             }
            if(!check_url_exists($vvl_server_url)){
                $vvl_server_url = str_replace('http://publicvideo.file.alimmdn.com', '', $vvl_server_url);
                 $vvl_server_url = 'http://kyxvideo.file.alimmdn.com' . $vvl_server_url;
            }
            $title = urlencode($title);
            $vvl_server_url = urlencode($vvl_server_url);
            $xiaolu_url = "http://xiaoluupload.wx.jaeapp.com/upload?url={$vvl_server_url}&title={$title}&vid={$video_id}";
            $is_suss = curl_get($xiaolu_url, 30);
            if($is_suss){
            	$arr['status'] = 200;
            	$arr['message'] = $title.'视频上传成功';
            }else{
            	$arr['status'] = 400;
            	$arr['message'] = $title.'视频上传失败';
            }
            $this->callback_ajax( $arr );
        }
    }
    
    
    /**
     * @name:auto_upload_video_desc
     * @description: 自动上传按播放量前10的视频
     * /usr/local/php/bin/php  /mnt/hgfs/admin.kuaiyouxi.com/index.php  api/gp   auto_upload_video_desc 
     * @author: Xiong Jianbang
     * @create: 2015-12-8 下午6:50:50
     **/
    public function auto_upload_video_desc(){
        if(!$this->input->is_cli_request()){
            exit('请以命令行方式运行');
        }
        $sql = "SELECT `id`  FROM `video_video_list` WHERE `vvl_sourcetype`=14  AND  vvl_video_id='' AND `va_isshow`=1 ORDER BY `vvl_count` DESC LIMIT 10";
        $query = $this->db->query( $sql);
        $res = $query->result_array();
        if(empty($res)){
            exit(json_encode(array('msg'=>'没有记录','status'=>400)));
        }
        foreach ($res as $value) {
            $video_id = $value['id'];
            $return = $this->push_youku_cdn($video_id);
            if(empty($return)){
                continue;
            }
        }
    }
    
    /**
     * @name:push_youku_cdn
     * @description: 将视频推送到优酷CDN
     * @author: Xiong Jianbang
     * @create: 2015-12-7 下午12:12:24
     **/
    private function push_youku_cdn($video_id=0){
        if(empty($video_id)){
            return FALSE;
        }
        $res = $this->video_model->get_video_list_info($video_id);
        //这里的网址直接是http://kyxvideo.file.alimmdn.com/2015/09/20/c3808bf9-8612-4ea2-8530-f34fbccb7402.mp4这样的形式了
        $vvl_server_url = !empty($res['vvl_server_url'])?$res['vvl_server_url']:NULL;
        if(empty($vvl_server_url)){
            return FALSE;
        }
        //已经打过水印，不处理。
        if(strpos($vvl_server_url, 'publicvideo')!==FALSE){
             return FALSE;
        }
        $sourcetype = $res['vvl_sourcetype'];
        $title = isset($res['vvl_title'])?$res['vvl_title']:'';
        $arr_temp = explode('.', $vvl_server_url);
        $vvl_server_url = ($sourcetype == 14) ?$vvl_server_url : ('http://kyxservervideo.file.alimmdn.com'.reset($arr_temp));
        if(empty($vvl_server_url)){
            return FALSE;
        }
        $head = @get_headers($vvl_server_url);
        if(!check_url_exists($vvl_server_url)){
            return FALSE;
        }
        $arr = parse_url($vvl_server_url);
        //存储空间
        $namespace = substr($arr['host'],0,strpos($arr['host'], '.'));
        $files = pathinfo($arr['path']);
        $ak = '23190770';
        $sk = 'be3181612c90e7e2a031a70c586f465f';
    
        $opts['watermark'] = EncodeUtils::encodeWithURLSafeBase64("['kyxvideo','/test','小鹿视频水印.png']");//水印地址
        $video_dir = $files['dirname'];
        $video_file  =  $files['basename'];
        $opts['input'] = EncodeUtils::encodeWithURLSafeBase64("['{$namespace}','{$video_dir}','{$video_file}']");
        $new_namespace = 'publicvideo';
        $video_file = $files['filename'];
        $video_ext = isset($files['extension'])?$files['extension']:'';
        if(!empty($video_ext)){
            $video_file .= '.'.$video_ext;
        }
        $opts['output'] = EncodeUtils::encodeWithURLSafeBase64("['{$new_namespace}','{$video_dir}','{$video_file}']");
        //转码模板
        $opts['encodeTemplate'] = 'mp4-720p';
        //打水印模板
        $opts['watermarkTemplate'] = 'left';
        $opts['usePreset'] = 0;
        $opts['force'] = 1;
//         $opts['notifyUrl'] = "http://ksadmin.youxilaile.com/uploads/test.php";
        $opts['notifyUrl'] = "http://ksadmin.youxilaile.com/api/gp/get_ali_notify";
        $uri = '/' . Conf::MANAGE_API_VERSION . '/mediaEncode';
        $obj = new ManageClient($ak,$sk);
        $return = $obj->curl_rest('POST',$uri,$opts);
        if(!empty($return) && $return['isSuccess'] ){
            $data['vvl_water_task_id'] = trim($return['taskId']);
            $this->video_model->video_info_update($video_id,$data);
            return TRUE;
        }
        return FALSE;
    }
    
    
}