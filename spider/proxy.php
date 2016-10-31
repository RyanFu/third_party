<?php
class Proxy extends CI_Controller{
    
    private $videos_filename;
    
    public function __construct(){
        parent::__construct();
        //         error_reporting(0);
        set_time_limit(0);
        if(!$this->input->is_cli_request()){
            exit('请以命令行方式运行');
        }
        $this->load->library('video_parser');
        $this->videos_filename = $GLOBALS['APK_UPLOAD_DIR'].'/videos.txt';
        $this->videos_output_filename = $GLOBALS['APK_UPLOAD_DIR'].'/videos_output.txt';
    }
    
    public function test(){
    	echo 111;
    }
    
    
    /**
     * @name:get_youtube_videos
     * @description:
     *  kyxproxy  /usr/local/php/bin/php  /mnt/hgfs/admin.kuaiyouxi.com/index.php  api/proxy   get_youtube_videos
     * @param:
     * @return:
     * @author: Xiong Jianbang
     * @create: 2015-12-18 下午5:43:06
     **/
    public function get_youtube_videos(){
        ini_set('memory_limit', '512M');
        if(!is_file($this->videos_filename)){
            exit('文件不存在');
        }
        $content = file_get_contents($this->videos_filename);
        $res = json_decode($content,TRUE);
        $v = new video_parser();
        $arr = array();
        foreach ($res as $value) {
            $id = intval($value['id']);
            $package_name = !empty($value['gi_packname'])?trim($value['gi_packname']):'';
            if(empty($package_name)){
                continue;
            }
            if(!empty($value['gi_sp_url'])){
                continue;
            }
           echo $google_url = "https://play.google.com/store/apps/details?id={$package_name}";
            $v->set_url($google_url);
            $json = $v->parse();
            if(empty($json)){
                continue;
            }
            $arr_return = json_decode($json,TRUE);
            print_r($arr_return);exit;
            $data = $v->get_google_app_spider();
            $params = $value;
            $params['gi_sp_url '] = $google_url;
            $params['gi_name '] = $data['game_name'];
            $params['gi_sp_firm '] = $data['firm_name'];
            $params['gi_sp_type '] = $data['type_name'];
            $params['gi_sp_source '] = $v->map_source_type($arr_return['type']);
            $params['gi_sp_vid '] = $arr_return['type'];
            $arr[] =  $params;
             
//             $this->db->where('id', $id);
//             $this->db->update('temp_video_game_info', $params);
            echo "\n";
        }
    }
}