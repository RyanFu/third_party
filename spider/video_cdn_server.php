<?php
/**
 * @description: 将视频下载到本地，再上传到乐视CDN
 *               使用方法
 *               /usr/local/php/bin/php  /mnt/hgfs/admin.kuaiyouxi.com/index.php  api/video_cdn_server   run &
 *               杀死所有进程：ps -ef | grep api/video_cdn_server | grep -v grep | cut -c 9-15 | xargs kill -9
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
        $this->serv = new swoole_server("127.0.0.1", 9502);
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
        $params['status']=100; //正式开始
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
            $tmp_video = str_replace($GLOBALS['UPLOAD_DIR'], '', $tmp_video);
            $params['server_url']= $tmp_video;//服务器的相对地址
            $vvl_server_url = CDN_LOCATION_URL_DOWN .$tmp_video;
            $vvl_server_url = urlencode($vvl_server_url);
            $cdn_url = "http://a.cdn.gugeanzhuangqi.com/cli_php/cdn_load/i_cdn_leshi.php?id={$cdn_id}&download_path={$vvl_server_url}&md5_value={$md5file}";
            error_log($cdn_url, 3, $this->error_log);
            $status = curl_get($cdn_url, 1000000);
            $params['status']=300; //同步成功
            $params['md5file']= $md5file;
            $params['cdn_url']= CDN_LESHI_URL_DOWN . $tmp_video;
            $this->update_video_cdn_status($params, $id);
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