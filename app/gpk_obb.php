<?php
class Gpk_obb extends MZW_Controller{
    
    
    public function __construct(){
        parent::__construct();
        error_reporting(0);
        set_time_limit(0);
    }
    
    
    /**
     * @name:run
     * @description: 使用方法
     *                /usr/bin/php  /mnt/hgfs/admin.kuaiyouxi.com/index.php  api/gpk_obb  run&
     * @author: Xiong Jianbang
     * @create: 2014-11-5 下午7:44:35
     **/
    public function run(){
        $this->load->database();//打开数据库连接
        $sql = 'SELECT `mgd_id`,`gv_id`,`mgd_mzw_server_url` FROM `mzw_game_downlist` WHERE `mgd_package_type` = 1';
        $query = $this->db->query( $sql );
        $list   = $query->result_array();
        if(empty($list)){
        	return FALSE;
        }
        $CI =&get_instance();
        foreach ($list as $value) {
            $file_path = trim($value['mgd_mzw_server_url']);
            $full_path = $GLOBALS['APK_UPLOAD_DIR'].$file_path;
            if(!is_file($full_path)){
            	continue;
            }
            $config = array (
                    'file_path' => $file_path,
            );
            $CI->load->library('app_analyse',$config);
            $arr_gpk = $this->app_analyse->gpk_process();
            $gv_id = intval($value['gv_id']);
            $mgd_id = intval($value['mgd_id']);
            if(!is_empty($arr_gpk)){
                $params['mgd_package_up_v_name'] 	= 		$arr_gpk['version_name'];
                $params['mgd_package_up_v_code'] 	= 		$arr_gpk['version_code'];
//                 $this->load->model('admin/mobile_model', 'Mobile');
//                 $params['mgd_gpu_id'] 	                        = 		$this->Mobile->translate_cpu_to_gpu($arr_gpk['cpu']);
            }
            $params['mgd_mzw_server_url']  	= 		$file_path;
            $params['mgd_package_folder']	 	= 		$GLOBALS['APK_UPLOAD_DIR'];//存放数据包的文件夹名字
            $filename = $GLOBALS['APK_UPLOAD_DIR'] .  $file_path;
            $params['mgd_package_file_size']	= 		filesize($filename);
            $params['mgd_md5']							= 		md5_file($filename);
            $params['mgd_create_time']			=		time();
            if($this->db->where( 'mgd_id', $mgd_id )->update('mzw_game_downlist', $params)){
                 
                $arr_obb =  isset($arr_gpk['obb'])?$arr_gpk['obb']:NULL;//差异文件
                if(!is_empty($arr_obb)){
                    //删除原来的OBB文件
                    $sql = 'SELECT `apk_patch_file`  FROM `mzw_game_patch`  WHERE `gv_id`=? AND `mgd_id` = ?';
                    $query = $this->db->query( $sql, array($gv_id,$mgd_id) );
                    $rs   	= $query->result_array();
                     
                    //删除原来的OBB记录
                    $conditon = array(
                            'gv_id' => $gv_id,
                            'mgd_id' => $mgd_id,
                    );
                    $this->db->delete('mzw_game_patch', $conditon);
                     
                     
                    if(!empty($rs)){
                        foreach ($rs as $v) {
                            $file_server_path = $GLOBALS['APK_UPLOAD_DIR'] . $v['apk_patch_file'];
                            if(is_file($file_server_path)){
                                unlink($file_server_path);
                            }
                        }
                    }
                    unset($rs);
                     
                    $tmp_game_size = 0;//游戏总大小
                    foreach ($arr_obb as $v) {
                        $data['mgd_id'] = $mgd_id;
                        $data['gv_id'] = $gv_id;
                        $data['to_versioncode'] =  $arr_gpk['version_code'];
                        $data['sign'] =  $arr_gpk['sign'];
                        $data['from_versioncode'] =  $v['from_version_code'];
                        $data['apk_patch_file'] =  $v['patch_obb_file'];
                        $patch_obb_file = $GLOBALS['APK_UPLOAD_DIR'] . $v['patch_obb_file'];
                        $data['apk_patch_size'] =  filesize($patch_obb_file);
                        $tmp_game_size += intval($data['apk_patch_size']);//计算游戏总大小
                        $data['patch_md5'] =  md5_file($patch_obb_file);
                        $data['add_time'] =  time();
                        $data['status'] =  2;
                        $data['file_type']=intval($v['patch_file_type']);
                        $this->db->insert('mzw_game_patch',$data);
                    }
                    //如果有解压到东西出来，则要更新游戏的总大小
                    if($tmp_game_size!=0){
                        $this->db->where( 'mgd_id', $mgd_id )->update('mzw_game_downlist', array('mgd_game_size'=>$tmp_game_size));
                    }
                }
          }
       }
    }
}