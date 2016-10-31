<?php
class Ali_youku_server extends MZW_Controller{
    
    private $serv;
    
    public function __construct(){
        parent::__construct();
//         error_reporting(0);
        set_time_limit(0);
        $this->serv = new swoole_server("127.0.0.1", 9505);
        $this->serv->set(array(
                'worker_num' => 8,
                'daemonize' => false,
                'max_request' => 10000,
                'dispatch_mode' => 2,
                'debug_mode'=> 1
        ));
        $this->load->database();//打开数据库连接
    }
    
    /**
     * @name:run
     * @description: 使用方法
     *               /usr/local/php/bin/php  /mnt/hgfs/admin.kuaiyouxi.com/index.php  api/ali_youku_server  run &
     * @author: Xiong Jianbang
     * @create: 2015-9-25 下午7:44:35
     **/
    public function run(){
        $this->serv->on('Start', array($this, 'onStart'));
        $this->serv->on('Connect', array($this, 'onConnect'));
        $this->serv->on('Receive', array($this, 'onReceive'));
        $this->serv->on('Close', array($this, 'onClose'));
        $this->serv->start();
    }
    
    
    public function works(){
    	echo  "当前Worker进程号是:",$this->serv->worker_id;
    	echo  "当前Worker进程的操作系统进程ID:",$this->serv->worker_pid;
    }
    
    public function onStart( $serv ) {
        echo "开始进入视频抓取守护进程,","当前服务器主进程的PID:",$this->serv->master_pid,"\n";
        echo "当前服务器管理进程的PID:",$this->serv->manager_pid,"\n";
        echo "\n所有任务重置为开始运行状态\n";
        
    }
    
    public function onConnect( $serv, $fd, $from_id ) {
        $serv->send( $fd, " {$fd}" );
    }
    
    
    /**
     * @name:onReceive
     * @description: 接收客户端发来的数据，并处理
     * @author: Xiong Jianbang
     * @create: 2014-11-6 上午11:15:02
     **/
    public function onReceive( swoole_server $serv, $fd, $from_id, $recive_data ) {
        echo  "当前Worker进程号是:",$this->serv->worker_id;
        echo  "\n当前Worker进程的操作系统进程ID:",$this->serv->worker_pid;
        echo "\n收到数据: {$fd}:{$recive_data}\n";
        $arr_recive = unserialize($recive_data);
        $message = isset($_POST['message'])?$_POST['message']:NULL;
//         $message = file_get_contents("php://input");
        if(empty($message)){
            echo json_encode(array('msg'=>'数据不能为空','status'=>400));
            return false;
        }
        $arr = json_decode($message,true);
        if(empty($arr)){
            echo json_encode(array('msg'=>'数据解析错误','status'=>400));
            return false;
        }
        //任务ID号
        $taskid = !empty($arr['id'])?$arr['id']:NULL;
        if(empty($taskid)){
            echo json_encode(array('msg'=>'任务ID不能为空','status'=>400));
            return false;
        }
        $return_status =  !empty($arr['tasks'][0]['status'])?$arr['tasks'][0]['status']:NULL;
        $sql = "SELECT `id`,`vvl_server_url`,`vvl_sourcetype`,`vvl_title` FROM `video_video_list` WHERE `vvl_water_task_id`='{$taskid}'";
        $query = $this->db->query( $sql);
        $res = $query->row_array();
        if(empty($res)){
            echo json_encode(array('msg'=>'该任务不存在','status'=>400));
            return false;
        }else{
            $vvl_server_url = trim($res['vvl_server_url']);
            if(empty($vvl_server_url)){
                echo json_encode(array('msg'=>'视频URL地址不能为空','status'=>400));
                return false;
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
            $tmp_video = curl_get_video($vvl_server_url,$to_save);
            if(is_file($tmp_video)){
                include APPPATH.'/libraries/youku_video/include/YoukuUploader.class.php'; //
                $client_id = "e2c75bd2b5d6f95a"; // Youku OpenAPI client_id
                $client_secret = "9c704bf259d59c8ad1e84c24f3e99ccb"; //Youku OpenAPI client_secret
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
                $params['username'] = "18601045600"; //Youku username or email
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
                    echo   json_encode($arr);
                    return FALSE;
                }
            }
            $arr['status'] = 1;
            $arr['message'] = '同步错误';
            echo   json_encode($arr);
        }
    }
    
    
    public function onClose( $serv, $fd, $from_id ) {
        echo '全部执行完毕';
    }
    
}