<?php
class Apkdiff_server extends MZW_Controller{
    
    private $serv;
    
    public function __construct(){
        parent::__construct();
        error_reporting(0);
        set_time_limit(0);
    }
    
    /**
     * @name:run
     * @description: 使用方法
     *                /usr/bin/php  /mnt/hgfs/chonggou/index.php  api/apkdiff_server  run &
     * @author: Xiong Jianbang
     * @create: 2014-11-5 下午7:44:35
     **/
    public function run(){
        if(!$this->input->is_cli_request()){
        	exit('请以命令行方式运行');
        }
        $this->load->database();//打开数据库连接
        
        $this->serv = new swoole_server("127.0.0.1", 9501);
        $this->serv->set(array(
                'worker_num' => 8,
                'daemonize' => false,
                'max_request' => 10000,
                'dispatch_mode' => 2,
                'debug_mode'=> 1
        ));
        
        $this->serv->on('Start', array($this, 'onStart'));
        $this->serv->on('Connect', array($this, 'onConnect'));
        $this->serv->on('Receive', array($this, 'onReceive'));
        $this->serv->on('Close', array($this, 'onClose'));
        $this->serv->start();
    }
    
    public function onStart( $serv ) {
        echo "开始进入APK生成差异文件守护进程\n";
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
    public function onReceive( swoole_server $serv, $fd, $from_id, $data ) {
        echo "收到数据: {$fd}:{$data}\n";
        $arr = unserialize($data);
        if(!is_array($arr) || empty($arr['new_gv_id'])){
        	exit;
        }
        $arr_apk = $arr['apk'];
        $new_gv_id = $arr['new_gv_id'];
        
        $version_code = isset($arr_apk['version_code'])?intval($arr_apk['version_code']):'';//新版版本号
	    $package_name = isset($arr_apk['package_name'])?$arr_apk['package_name']:'';//包名
	    $save_path = isset($arr_apk['save_path'])?$arr_apk['save_path']:'';//新版的保存地址
	    $sign = isset($arr_apk['sign'])?$arr_apk['sign']:'';//新版的安装包签名
	    $g_id = isset($arr_apk['g_id'])?intval($arr_apk['g_id']):'';//game_id
	    
	    if(is_empty($package_name) || is_empty($version_code)){
	    	exit;
	    }
	    
	    //查找是否已经生成过差异文件
	    $sql = 'SELECT `versioncode` FROM `mzw_game_unzip` WHERE `gid`=?';
	    $query = $this->db->query( $sql, array($g_id) );
	    $rs_upzip = $query->row();
	    if(empty($rs_upzip)){
	        $params['vid'] = $new_gv_id;  //注意：这里是新添加的gv_id
	        $params['gid'] = $g_id;
	        $params['versioncode'] = $version_code;
	        $params['sign'] = $sign;
	        $params['add_time'] = time();
	        $this->db->insert('mzw_game_unzip',$params);
	        unset($params);
	    }else{
	    	$versioncode = $rs_upzip->versioncode;
	    	//已经生成过
	    	if($versioncode>=$version_code){
	    		exit('已经生成过');
	    	}else{
	    	    $params['vid'] = $new_gv_id;
	    	    $params['versioncode'] = $version_code;
	    	    $params['add_time'] = time();
	    	    $this->db->where('gid', $g_id);
	    	    $this->db->update('mzw_game_unzip', $params);
	    	    unset($params);
	    	}
	    }
 
	    //获取所有历史版本的apk记录录
	    $sql = 'SELECT `gv_id`,`gv_version_no`,`gv_version_name`,`gv_md5_value` FROM `mzw_game_version` WHERE `gv_package_name` = ?';
	    $query = $this->db->query( $sql, array($package_name) );
	    $list   = $query->result_array();
	    if(!is_empty($list)){
	        $CI =&get_instance();
	        $CI->load->library('app_analyse');
 
	    	foreach ($list as $value) {
	    		if($value['gv_id']==$new_gv_id)continue;//排除掉最新版本号的gv_id
	    		//如果历史版本的gv_id,并满足版本对比的条件，则可以生成 差异文件
	    		$last_gv_id = $value['gv_id'];
	    		//获取历史版本的版本号，文件路径
	    		$sql = 'SELECT `mgd_apk_agsin`,`mgd_package_up_v_code`,`mgd_mzw_server_url` FROM `mzw_game_downlist` WHERE `gv_id` = ? AND `mgd_package_type`=0   LIMIT 1';
	    		$query = $this->db->query( $sql, array($last_gv_id) );
	    		$rs   = $query->row();
	    		if(!is_empty($rs)){
	    		    $old_package_up_v_code = intval($rs->mgd_package_up_v_code);
	    		    $old_sign = $rs->mgd_apk_agsin;
	                
	    		    if($old_package_up_v_code==0)continue;
	    		    //上传的APK版本号小于当前版本，就忽略
	    		    if($version_code<$old_package_up_v_code)continue;
	
	    		    //上传的APK版本号大于当前版本  或者 版本相等，但签名不一样，就生成差异文件
	    		    if( ($version_code>$old_package_up_v_code)  || ($version_code==$old_package_up_v_code && $sign<>$old_sign)){
	    		        $old_server_url = $GLOBALS['APK_UPLOAD_DIR'] . $rs->mgd_mzw_server_url;
	    		        //差异文件名称
	    		        $diff_file = 'patch.'.$old_package_up_v_code.'-'.$version_code .'.'.$package_name.'.diff';

	    		        //将差异文件信息保存到game_downlist表
	    		        $params['gv_id'] = $new_gv_id;  //注意：这里是新添加的gv_id
	    		        $params['mgd_package_type'] = 2;
	    		        $params['mgd_package_folder'] = $GLOBALS['APK_UPLOAD_DIR'];
	    		        $this->db->insert('mzw_game_downlist',$params);
	    		        $mgd_id = $this->db->insert_id();
	    		        unset($params);

	    		        //将生成的差异文件记录插入mzw_game_patch表
	    		        $params['mgd_id'] = $mgd_id;
	    		        $params['gv_id'] = $new_gv_id;//新gv_id
	    		        $params['to_versioncode'] =  $version_code;
	    		        $params['sign'] =  $sign;
	    		        $params['from_versioncode'] =  $old_package_up_v_code;
	    		        $params['add_time'] =  time();
	    		        $params['status'] = 1;
	    		        $params['g_id'] = $g_id;
	    		        $this->db->insert('mzw_game_patch',$params);
	    		        $patch_id = $this->db->insert_id();
	    		        unset($params);
	    		        
	    		        //生成差异文件，并获取差异文件的相对路径
	    		        $new_server_url = $GLOBALS['APK_UPLOAD_DIR'] .$save_path;
	    		        $new_diff_file = $this->app_analyse->create_diff_apk_file($old_server_url,$new_server_url,$diff_file);
	    		        //没有生成差异文件，则忽略
	    		        if(!$new_diff_file)continue;
	    		        
	    		        $arr_diff = array(
	    		        	'mgd_id' =>$mgd_id,
	    		            'patch_id'=>$patch_id,
	    		            'diff_file_path' =>$new_diff_file
	    		        );
	    		        $this->handle_update($arr_diff);
	    		    }
	    	  }
	       }
	    }
    }
    
    /**
     * @name:handle_update
     * @description: 处理更新操作
     * @author: Xiong Jianbang
     * @create: 2014-11-6 上午10:32:35
     **/
    private function handle_update($arr_diff){
        $params['mgd_mzw_server_url'] = $arr_diff['diff_file_path'];
        $diff_all_path = $GLOBALS['APK_UPLOAD_DIR']  .$arr_diff['diff_file_path'];
        $params['mgd_md5'] = md5_file($diff_all_path);
        $params['mgd_package_file_size'] = filesize($diff_all_path);
        $this->db->where('mgd_id', $arr_diff['mgd_id'] );
        $this->db->update('mzw_game_downlist', $params);
        unset($params);
        $params['apk_patch_file'] =  $arr_diff['diff_file_path'];
        $params['apk_patch_size'] = filesize($diff_all_path);
        $params['patch_md5'] =  md5_file($diff_all_path);
        $params['update_time'] =  time();
        $params['status'] = 2;//已完成
        $this->db->where('id', $arr_diff['patch_id'] );
        $this->db->update('mzw_game_patch', $params);
        unset($params);
    }
    
    public function onClose( $serv, $fd, $from_id ) {
        echo '执行完毕';
    }
}