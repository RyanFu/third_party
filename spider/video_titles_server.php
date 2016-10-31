<?php
/**
 * @description: 将视频下载到本地，再打上片头
 *               使用方法
 *               /usr/local/php/bin/php  /mnt/hgfs/admin.kuaiyouxi.com/index.php  api/video_titles_server   run &
 *               杀死所有进程：ps -ef | grep api/video_titles_server | grep -v grep | cut -c 9-15 | xargs kill -9
 * @author: Xiong Jianbang
 * @create: 2015-11-10 上午11:52:35
 **/

class Video_cdn_server extends MZW_Controller{
    private $serv;
    private $error_log;
   
    public function __construct(){
        parent::__construct();
        error_reporting(0);
        set_time_limit(0);
        $this->serv = new swoole_server("127.0.0.1", 9503);
        $this->serv->set(array(
                'worker_num' => 50,
                'task_worker_num' => 50, 
        ));
        $this->error_log = '/var/tmp/my-errors.log';
    }

    public function my_onReceive($serv, $fd, $from_id, $recive_data){
        //taskwait就是投递一条任务，
        //然后阻塞等待抓取任务完成
        $result = $serv->taskwait($recive_data);
        if ($result !== false) {
            list($status, $str_return) = explode(':', $result, 2);
            if ($status == 'OK') {
                $serv->send($fd, unserialize($str_return) . "\n");
            } else {
                $serv->send($fd, $str_return);
            }
            return;
            } else {
                $serv->send($fd, "Error. Task timeout\n");
            }
    }
   
    public  function my_onTask($serv, $task_id, $from_id, $recive_data){
        $arr = unserialize($recive_data);
        print_r($arr);
        $id = isset($arr['id'])?trim($arr['id']):NULL;
        $vvl_server_url = isset($arr['server_url'])?trim($arr['server_url']):NULL;
        if(empty($vvl_server_url)){
            echo '视频地址不能为空';
            return;
        }
        $this->load->database();//打开数据库连接
        $head = @get_headers($vvl_server_url);
        if(!check_url_exists($vvl_server_url)){
            echo '该视频不存在';
            return ;
        }
        $params['status']=600; //正式开始
        $this->update_video_cdn_status($params, $id);
        $dir_name =  parse_url($vvl_server_url, PHP_URL_PATH);
        $dir_name = dirname($dir_name);
        $server_url = $dir_name . '/';
        $to_save = $GLOBALS['UPLOAD_DIR']. $server_url; //本地服务器地址
        if(!is_dir($to_save)){
            create_my_file_path($to_save,0755);
        }
        $filename = basename($vvl_server_url);
        if(is_file($to_save)){
            //不用做文件存在检查，因为也有可能是不完整的视频，所以需要覆盖下载
        }
        $tmp_video = curl_get_video($vvl_server_url,$to_save,TRUE);
        if(is_file($tmp_video)){
            $params['status']=200; //下载成功
            $this->update_video_cdn_status($params, $id);
            $md5file = md5_file($tmp_video);
            $cdn_id = md5($tmp_video);
            $server_filename = pathinfo($tmp_video,PATHINFO_FILENAME); //下载后得到的文件名
            $server_ext = pathinfo($tmp_video,PATHINFO_EXTENSION);//下载后得到的扩展名
            //将视频打上水印
            $params['status']=601; //开始添加水印
            $this->update_video_cdn_status($params, $id);
            $logo_png = $GLOBALS['UPLOAD_DIR'] . '/logo.png';
            $server_new_filename = 'kyx_'.$server_filename.'_water'; //打上水印后的文件名
            if(!empty($server_ext)){
                $server_new_filename .= '.'.$server_ext;
            }
            $server_water_path = $GLOBALS['UPLOAD_DIR']. $server_url .$server_new_filename;//打上水印后的绝对地址
            echo $command = '/usr/local/ffmpeg/bin/ffmpeg -i '.$tmp_video.'  -i '.$logo_png.' -filter_complex "overlay=10:10"  -strict -2 -y ' .$server_water_path  . '  2>&1 ';
            shell_exec($command);
            unset($command,$server_new_filename,$server_new_path);
            $params['status']=602;//成功打上水印
            $this->update_video_cdn_status($params, $id);
            
            
            //将打上水印的mp4文件转换成ts流，这样的处理是合并的时候速度会快一点
            $tmp_ts = $GLOBALS['UPLOAD_DIR'] .'/tmp_'. $md5file.'.ts';
            if(is_file($tmp_ts)){//删除临时文件
            	unlink($tmp_ts);
            }
            $command = "/usr/local/ffmpeg/bin/ffmpeg  -i {$server_water_path} -vcodec copy -acodec copy -vbsf h264_mp4toannexb -y {$tmp_ts}  2>&1";
            shell_exec($command);
            unset($command);
            
            $params['status']=603; //开始合并任务
            $this->update_video_cdn_status($params, $id);
            $logo_ts = $GLOBALS['UPLOAD_DIR'] . '/logo.ts';//logo的ts流文件
            $server_new_filename = 'kyx_'.$server_filename.'_titles';
            if(!empty($server_ext)){
                $server_new_filename .= '.'.$server_ext;
            }
            $server_titles_path = $GLOBALS['UPLOAD_DIR']. $server_url .$server_new_filename;
            //将两个ts文件合并成mp4
            $command = '/usr/local/ffmpeg/bin/ffmpeg  -i "concat:'.$logo_ts.'|'.$tmp_ts.'"  -acodec copy  -vcodec libx264   -bsf:a  aac_adtstoasc   -strict -2   -y  -s 1280x720  '.$server_titles_path  .'  2>&1';
            shell_exec($command);
            unset($command);
            if(is_file($tmp_ts)){//删除临时文件
                unlink($tmp_ts);
            }
            unset($server_new_filename,$server_new_path);
            
            $params['status']=604; //片头合并成功
            $params['water_md5file']= md5_file($server_water_path);//最终保存加上水印后文件的md5值
            $params['titles_md5file']= md5_file($server_titles_path);//最终保存合并后文件的md5值
            $params['water_url']  = str_replace($GLOBALS['UPLOAD_DIR'], '', $server_water_path);//打上水印的服务器的相对地址
            $params['titles_url']  = str_replace($GLOBALS['UPLOAD_DIR'], '', $server_titles_path);//加了片头的服务器的相对地址
            $params['server_url'] = str_replace($GLOBALS['UPLOAD_DIR'], '', $tmp_video);//服务器的相对地址
            $this->update_video_cdn_status($params, $id);
            
//             $params['cdn_url']= CDN_LESHI_URL_DOWN . $tmp_video;
        }else{
            $params['status']=400; //下载失败
            $this->update_video_cdn_status($params, $id);
        }
        $serv->finish("OK:" . serialize('执行完毕'));
    }
   
    public function my_onFinish($serv, $data){
        echo "AsyncTask Finish:Connect.PID=" . posix_getpid() . PHP_EOL;
    }
    
    public function update_video_cdn_status($params,$id){
        if(empty($id) || empty($params)){
            return FALSE;
        }
        $this->db->where('id', $id );
        $params['updated'] = time();
        $this->db->update('video_cdn_url', $params);
        return $id;
    }
   
    
    
   
    public function start(){
        $this->serv->on('Receive', array($this, 'my_onReceive'));
        $this->serv->on('Task', array($this, 'my_onTask'));
        $this->serv->on('Finish', array($this, 'my_onFinish'));
        $this->serv->start();
    }
    
    
}

$obj_server = new Video_cdn_server();
$obj_server->start();