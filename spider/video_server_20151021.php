<?php
class Video_server extends MZW_Controller{
    
    private $serv;
    
    public function __construct(){
        parent::__construct();
        error_reporting(0);
        set_time_limit(0);
        $this->serv = new swoole_server("127.0.0.1", 9501);
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
     *               /usr/local/php/bin/php  /mnt/hgfs/admin.kuaiyouxi.com/index.php  api/video_server  run &
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
        $this->db->where('vf_status', 1 );
        $this->db->update('video_file', array('vf_status'=>0));
        
    }
    
    public function onConnect( $serv, $fd, $from_id ) {
        $serv->send( $fd, " {$fd}" );
    }
    
    public function update_video_url_status($params,$id){
    	if(empty($id) || empty($params)){
    		return FALSE;
    	}
    	$this->db->where('id', $id );
    	$params['updated'] = time();
    	$this->db->update('video_url', $params);
    	return $id;
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
        $arr = unserialize($recive_data);
        $file_id = isset($arr['file_id'])?intval($arr['file_id']):NULL;
        if(empty($file_id)){
            exit('文件ID号不能为空');
        }
        
        
        $sql = "SELECT *  FROM `video_url` WHERE `vf_id`=? ORDER BY `id` DESC";
        $query = $this->db->query( $sql, array($file_id) );
        $arr_urls = $query->result_array();
        if(empty($arr_urls)){
            exit('文件ID号不存在url列表');
        }
        
        //配置包名与自定义游戏ID，需要在外网生产环境的后台预先配置好，见http://ksadmin.youxilaile.com/admin/video/game_list页
        $arr_game_id = array(
                'com.rayark.Cytus.full' => 11,
                'com.rayark.cytus.full' => 11,
                'com.candyrufusgames.survivalcraft.kyx'=>9,
                'com.supercell.clashofclans'=>4,
                'com.blizzard.wtcg.hearthstone'=>3,
                'com.mojang.minecraftpe'=>2
        );
        
        $CI =&get_instance();
        $CI->load->library('video_parser');
        require_once( KYX_ROOT_DIR. '/application/libraries/umeng_video/upload_media.class.php'); //快游戏上传类
        //生产环境抓取图片的接口
        $get_img_url = 'http://ksadmin.youxilaile.com/api/gp/get_video_img';;
        $video = $this->video_parser;
        $upload_obj = new upload_media();
        //批量处理指定目录下的所有文件
        foreach ($arr_urls as $value) {
            $suss_count = 0;//处理成功数
            $fail_count=0;//处理失败数
            $down_count = 0;//下载成功数
            $down_fail_count = 0;//下载失败数
            $cdn_suss_count=0;//同步到CDN成功数
            $cdn_fail_count = 0;//同步到CDN失败数
            $package_name = isset($value['package'])?$value['package']:NULL;
            $play_url = isset($value['url'])?trim($value['url']):NULL;
            $url_id = intval($value['id']); //video_url的id值
            if(empty($play_url)){
                $fail_count++;
                continue;
            }
            //检测以前是否跑过
            $sql = "SELECT `id` FROM `video_video_list` WHERE `vvl_playurl`=?";
            $query = $this->db->query( $sql, array($play_url) );
            $res = $query->row_array();
            if(!empty($res)){
                $arr_url_status['status'] =1000;//不必处理，忽略
                $this->update_video_url_status($arr_url_status, $url_id);
                $fail_count++;
                continue;
            }
            unset($res);
            if(empty($package_name)){
//                 $obj_log->log('notice', "URL={$play_url}的包名为空");
            }
            $video->set_url($play_url);
            $tmp_introvideo_get = $video->parse();
            if(empty($tmp_introvideo_get)){
                $arr_url_status['status'] = 10001;//解析为空
                $this->update_video_url_status($arr_url_status, $url_id);
                $fail_count++;
                continue;
            }
            $arr_video = json_decode($tmp_introvideo_get,TRUE);
            print_r($arr_video);
            if(empty($arr_video) || $arr_video['status']<>200 || empty($arr_video['msg'])){
                $arr_url_status['status'] = 1002;//解析不成功
                $this->update_video_url_status($arr_url_status, $url_id);
                $fail_count++;
                continue;
            }
            $arr_url_status['status'] = 1003;//已经开始
            $this->update_video_url_status($arr_url_status, $url_id);
            $data['in_date'] = time();
            $data['vvl_game_id'] = 0;
            $data['vvl_hi_id'] = 0;
            $data['vvl_category_id'] = 0;
            $data['vvl_type_id'] = 0;
            $data['vvl_sourcetype'] = $video->map_source_type($arr_video['type']);
            $data['vvl_imgurl'] = isset($value['img_url'])?$value['img_url']:'';
            $data['vvl_imgurl_get'] = '';
        
            $video_url = $arr_video['msg']; //获取的是视频源地址，比如m3u8或者具有json格式的地址
            if(empty($video_url)){
                continue;
            }
            $arr_url = parse_url($video_url);
            if(empty($arr_url)){
                continue;
            }
            $host = $arr_url['host'];
            if(empty($host)){
                continue;
            }
            $char_count = substr_count($host, '.');
            $sub_host = $host;
            if($char_count>1){
                $sub_host = substr($host,strpos($host,'.'));//类似http://coc.5253.com/1502/287512292747.html
            }else{
                $sub_host = '.'.$host;//类似https://everyplay.com/videos/10147379
            }
            $arr_video_url = array();
            switch ($sub_host) {
            	case '.kamcord.com':
            	    $arr_video_url['high'] = $video_url;
                break;
            	case '.everyplay.com':
            	    $json = curl_get($video_url);
            	    if(empty($json)){
            	        continue;
            	    }
            	    $arr_return = json_decode($json,TRUE);
            	    if(isset($arr_return['base_url']) && isset($arr_return['video_files']['high'])){
            	        $arr_video_url['high'] = $arr_return['base_url'] .  $arr_return['video_files']['high'];
            	    }
            	    if(isset($arr_return['base_url']) && isset($arr_return['video_files']['medium'])){
            	        $arr_video_url['medium'] = $arr_return['base_url'] .  $arr_return['video_files']['medium'];
            	    }
            	    if(isset($arr_return['base_url']) && isset($arr_return['video_files']['low'])){
            	        $arr_video_url['low'] = $arr_return['base_url'] .  $arr_return['video_files']['low'];
            	    }else{
            	        continue;
            	    }
                	break;
            	case '.youshixiu.com':
            	    $arr_video_url['high'] = $video_url;
                break;
                case '.aipai.com':
                    $video_url =  get_redirect_url($video_url);
                    $arr_video_url['high'] = $video_url;
                    
                break;
            	default:
            	    $arr_url_status['status'] = 1010;//不取指定网址
            	    $this->update_video_url_status($arr_url_status, $url_id);
            	    continue;
                	break;
            }
            unset($video_url);
            if(!empty($arr_video_url)){
                //将高，中，低三种品质的视频批量一起抓取
                foreach ($arr_video_url as $key=>$video_url) {
                    //创建视频存放目录
                    $dir =  '/video_mp4' .date('/Y/m/d/');  //添加模块名作目录一部分
                    $to_save = $GLOBALS['UPLOAD_DIR']. $dir;
                    if(!is_dir($to_save)){
                        create_my_file_path($to_save,0755);
                    }
                    if(in_array($sub_host, array('.kamcord.com'))){//kamcord站的源需要边下载边转码
                        //视频的新文件名
                        $filename = md5($video_url);
                        $tmp_video = $to_save.$filename.'.mp4';
                        $command = "/usr/local/ffmpeg/bin/ffmpeg -i {$video_url}   -acodec copy -vcodec libx264  -y -loglevel info -f mp4  -bsf:a aac_adtstoasc   {$tmp_video}";
                        exec($command);
                    }else{
                        $tmp_video = curl_get_video($video_url,$to_save);
                    }
                    
                    $arr_url_status['status'] = 1004;//下载视频成功
                    $this->update_video_url_status($arr_url_status, $url_id);
                    
                    if(!empty($tmp_video)){
                        $down_count++;
                        $this->update_file_stat($file_id);//成功下载视频数
                        echo  "URL={$play_url},视频地址={$video_url}下载成功".chr(10).chr(13);
                    }else{
                        $down_fail_count++;
                        $arr_url_status['status'] = 1005;//下载视频失败
                        $this->update_video_url_status($arr_url_status, $url_id);
                    }
                    
                    //同步到阿里百川
                    $result = $upload_obj->upload_video($tmp_video,'video_mp4');
                    $json = json_encode($result);
                    var_dump($result);
                    $json_tmp = json_encode($result);
                    if(!empty($result) && $result['isSuccess']){
                        $cdn_suss_count++;
                        echo  "URL={$play_url},视频地址={$result['url']}已经同步成功".chr(10).chr(13);
                        $arr_url_status['status'] = 1006;//同步视频成功
                        $this->update_video_url_status($arr_url_status, $url_id);
                    }else{
                        $arr_url_status['status'] = 1007;//同步视频失败
                        $this->update_video_url_status($arr_url_status, $url_id);
                        continue;
                    }
                    echo  "URL={$play_url}记录,上传到阿里百川的返回值是{$json_tmp}";
                    //保存到数据表里
                    $tmp_video = str_replace($GLOBALS['UPLOAD_DIR'], '', $tmp_video);
                    switch ($key) {
                        case 'high':
                            $data['vvl_server_url'] = $tmp_video;
                        break;
                        case 'medium':
                            $data['vvl_medium_server_url'] = $tmp_video;
                        break;
                        case 'low':
                            $data['vvl_low_server_url'] = $tmp_video;
                        break;
                        default:
                            continue;
                        break;
                    }
                }
                //获取作者信息
                $author =  isset($value['author'])?trim($value['author']):'';
                $author_id = 0;
                $arr_author = array();
                if(!empty($author)){
                    //创建作者头像图片存放目录
                    $dir =  '/video_img' .date('/Y/m/d/');  //添加模块名作目录一部分
                    $to_save = $GLOBALS['IMG_UPLOAD_DIR'] . $dir;
                    if(!is_dir($to_save)){
                        create_my_file_path($to_save,0755);
                    }
                    $sql = "SELECT `id`  FROM `video_author_info` WHERE `va_name`=?";
                    $query = $this->db->query( $sql, array($author) );
                    $res = $query->row_array();
                    if(!empty($res)){
                            $author_id = $res['id'];
                    }else{
                            $arr_author['in_date'] = time();
                            $arr_author['va_name'] = $author;
                            $author_img =  isset($value['author_img'])?$value['author_img']:'';
                            $arr_author['va_icon'] = '';
                            if(!empty($author_img)){
                                $arr_author['va_icon'] =$author_img;
                                //抓取图片
                                $tmp_icon_get = curl_get_img($author_img,$to_save);
                                $tmp_icon_get = str_replace($GLOBALS['IMG_UPLOAD_DIR'], '', $tmp_icon_get);
                                $arr_img = array('local_img'=>KYX_COMPANY_IP .'/img'. $tmp_icon_get);
                                $json = curl_post($get_img_url,$arr_img);
                                $arr = json_decode($json,TRUE);
                                if(!empty($arr) && $arr['status']==200){
                                         echo  "URL={$play_url},作者头像{$arr['msg']}推送成功".chr(10).chr(13);
                                        $arr_author['va_icon_get'] = str_replace('/data/web/admin.kuaiyouxi.com/uploads/img', '', trim($arr['msg']));
                                }
                            }
                            $arr_author['va_isshow'] = 1;
                            $author_id =  $this->db->insert('video_author_info',$arr_author);
                     }
                     unset($res);
              }
             if(!empty($data['vvl_imgurl'])){
                    //创建图片存放目录
//                     $dir =  '/video_img' .date('/Y/m/d/');  //添加模块名作目录一部分
//                     $to_save = $GLOBALS['IMG_UPLOAD_DIR'] . $dir;
//                     if(!is_dir($to_save)){
//                         create_my_file_path($to_save,0755);
//                     }
//                     //抓取图片
//                     $tmp_img = curl_get_img($data['vvl_imgurl'],$to_save);
//                     $tmp_img = str_replace($GLOBALS['IMG_UPLOAD_DIR'], '', $tmp_img);
//                     $arr_img = array('local_img'=>KYX_COMPANY_IP .'/img'. $tmp_img);
//                     $json = curl_post($get_img_url,$arr_img);
//                     $arr = json_decode($json,TRUE);
//                     if(!empty($arr) && $arr['status']==200){
//                                 echo  "URL={$play_url},视频图像{$arr['msg']}推送成功".chr(10).chr(13);
//                                 $data['vvl_imgurl_get'] = str_replace('/data/web/admin.kuaiyouxi.com/uploads/img', '', trim($arr['msg']));
//                                 $arr_url_status['status'] = 1009;//图像推送成功
//                                 $this->update_video_url_status($arr_url_status, $url_id);
//                    }
//                    unset($arr);
             }
            $data['vvl_time'] = isset($value['duration'])?$value['duration']:'';
            $data['vvl_playurl'] = $play_url;
            $data['vvl_playurl_get'] = $video_url;
            $data['vvl_author_id'] = $author_id;
            $data['vvl_title'] = isset($value['title'])?$value['title']:'';
            $data['vvl_playcount'] = isset($value['play_count'])?$value['play_count']:0;
            $data['vvl_count'] = 0;
            $data['va_isshow'] = 1;
            $data['vvl_video_id'] = isset($arr_video['vid'])?$arr_video['vid']:'';
            $data['vvl_gv_id'] = 0;
            $data['vvl_package_name'] = $package_name;
            $data['vvl_game_id'] =  isset($arr_game_id[$package_name])?$arr_game_id[$package_name]:0;
            $last_id =  $this->db->insert('video_video_list',$data);
            if($last_id){
                    $suss_count++;
                    $arr_url_status['status'] = 1008;//成功入库
                    $this->update_video_url_status($arr_url_status, $url_id);
                    echo  "URL={$play_url},vvl_id={$last_id}记录入库成功".chr(10).chr(13);
            }
          }
        }
        //更新运行状态
        echo $file_id;
        $this->update_file_status($file_id);
    }
    
    
    public function onClose( $serv, $fd, $from_id ) {
        echo '视频抓取全部执行完毕';
    }
    
    /**
     * @name:update_file_stat
     * @description: 更改下载完毕后的成功数
     * @author: Xiong Jianbang
     * @create: 2015-9-28 上午10:22:06
     **/
    public function update_file_stat($file_id){
    	$sql = "UPDATE  `video_file`  SET vf_suss=vf_suss+1 WHERE vf_id={$file_id}";
    	return $this->db->query($sql);
    }
    
    /**
     * @name:update_file_status
     * @description: 改变运行的完成状态
     * @author: Xiong Jianbang
     * @create: 2015-9-28 上午10:19:06
     **/
    public function update_file_status($file_id){
        $this->db->where('vf_id', $file_id );
        $this->db->update('video_file', array("vf_status"=>2));
    }
}