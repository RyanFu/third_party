<?php

/**
 * @name:
 * @description: 视频采集功能
 * @param: 
 * @return: 
 * @author: Xiong Jianbang
 * @create: 2014-10-22 上午11:23:03
 **/
class Spider extends MZW_Controller{
    
    private $videos_filename;
    
    public function __construct(){
        parent::__construct();
//         error_reporting(0);
        set_time_limit(0);
        if(!$this->input->is_cli_request()){
            exit('请以命令行方式运行');
        }
        $this->load->model("admin/video_model");
        $this->load->model("admin/member_model");
        $this->load->library('video_parser');
        $this->videos_filename = $GLOBALS['APK_UPLOAD_DIR'].'/videos.txt';
    }
    
    public function test($a=111){
    	echo 111 . $a;
    }
    
    /**
     * @name:aipai_allgame_spider_cron
     * @description:
     * /usr/local/php/bin/php  /mnt/hgfs/admin.kuaiyouxi.com/index.php  api/spider   aipai_allgame_spider_cron
     * /usr/local/php/bin/php  /mnt/hgfs/admin.kuaiyouxi.com/index.php  api/spider   aipai_allgame_spider_cron  > /home/www/aipai_allgame_spider_cron_20160118.txt
     * /usr/local/php/bin/php  /data/web/admin.kuaiyouxi.com/index.php  api/spider   aipai_allgame_spider_cron  > /home/www/aipai_allgame_spider_cron_20160118.txt
     * @author: Xiong Jianbang
     * @create: 2015-11-25 下午5:48:43
     **/
    public function aipai_allgame_spider_cron(){
    	$v = new video_parser();
    	$data = $v->aipai_allgame_spider();
    	
    }
    
    /**
     * @name:game_video_spider_cron
     * @description:
     * /usr/local/php/bin/php  /mnt/hgfs/admin.kuaiyouxi.com/index.php  api/spider   game_video_spider_cron
     * /usr/local/php/bin/php  /mnt/hgfs/admin.kuaiyouxi.com/index.php  api/spider   game_video_spider_cron  > /home/www/search_videos_20160118.txt
     * /usr/local/php/bin/php  /data/web/admin.kuaiyouxi.com/index.php  api/spider   game_video_spider_cron  > /home/www/search_videos_20160118.txt
     * @author: Xiong Jianbang
     * @create: 2015-11-25 下午5:48:43
     **/
    public function game_video_spider_cron(){
    	ini_set('memory_limit', '512M');
    	$res = file($GLOBALS['APK_UPLOAD_DIR'].'/game_list/game.txt');
    	if(empty($res)){
    		exit('没有记录');
    	}
    	$v = new video_parser();
    	//测试代码
//     	$url = "http://www.soku.com/search_playlist/q_极品飞车13";
//     	$v->set_url($url);
//     	$arr_return = $v->youku_search();
//     	print_r($arr_return);
//     	exit;
    	
    	foreach ($res as $so_game) {
    		$so_game = trim($so_game);
    		if(empty($so_game)){
    			continue;
    		}
    		
    		
    		
    		
    		$url = "http://www.soku.com/search_playlist/q_{$so_game}";
    		echo "开始抓取{$url}  优酷的搜索页面\n";
    		$v->set_url($url);
	    	$arr_urls = $v->youku_search();
	    	if(empty($arr_urls)){
	    		echo "400：{$so_game}游戏没有获取到专辑\n\n";
	    		continue;
	    	}
	    	
	    	//查找游戏
	    	$this->load->database("spider");//打开数据库连接
	    	$game_id = 0;
	    	$sql = "SELECT `id`  FROM `video_game_info` WHERE `gi_name`='{$so_game}'  ";
	    	$query = $this->db->query( $sql);
	    	$res_game = $query->row_array();
	    	if(!empty($res_game)){
	    		$game_id = $res_game['id'];
	    	}else{
	    		$params['gi_name'] = $so_game;
	    		$this->db->insert('video_game_info',$params);
	    		$game_id = $this->db->insert_id();
	    		unset($params);
	    	}
	    	unset($res_game);
	    	echo $game_id;

	    	
    		//一个访问入口就是一个作者
    		$author_suss_count = 0; //成功返回数据的作者数量
    		$author_fail_count = 0; //失败返回数据的作者数量
	    	foreach ($arr_urls as $author_page) {
	    		echo "开始抓取{$author_page}作者的专区页面\n";
	    		$v->set_url($author_page);
	    		$data = $v->spider($so_game); //将游戏名做成过滤关键字
	    		if(empty($data)){
	    			$author_fail_count++;
	    			continue;
	    		}
	    		//添加入库
	    		if(empty($data)){
	    			continue;
	    		}
	    		$author_suss_count++;
	    		$album_count = isset($data['listSeries']) ?count($data['listSeries']):0; //专辑数量
	    		if(empty($album_count)){
	    			continue;
	    		}
	    		$video_sum = 0; //视频数量
	    		foreach ($data['listSeries'] as $videos) {
	    			$video_count = isset($videos['listVideos'])?count($videos['listVideos']):0;
	    			$video_sum +=$video_count;
	    			if(empty($video_count)){
	    				continue;
	    			}
	    			if(!isset($videos['listVideos'])){
	    				continue;
	    			}
	    			foreach ($videos['listVideos'] as $vd) {
	    				$message = "600：{$so_game}游戏的{$author_page}页面视频链接地址：{$vd['vRealPalyUrl']}\n";
	    			}
	    		}
	    		echo "300：{$so_game}游戏的{$author_page}页面有{$album_count}个专辑，{$video_sum}个视频\n"; 
	    	}
    	    echo "200：{$so_game}游戏有{$author_suss_count}成功解说者页面,有{$author_fail_count}失败解说者页面\n\n";
    	    $type_id = 6;
    	    $is_hidden = TRUE;
    	    $this->save_author_commentary($data, $v,$game_id,$type_id,$is_hidden);
    	}
    }
    
    
    
    /**
     * @name:regular_cron
     * @description:
     * /usr/local/php/bin/php  /mnt/hgfs/admin.kuaiyouxi.com/index.php  api/spider   regular_cron 
     * @author: Xiong Jianbang
     * @create: 2015-11-25 下午5:48:43
     **/
    public function regular_cron(){
    	$this->load->database();//打开数据库连接
//         $sql = "SELECT *  FROM `video_spider_url`    ORDER BY `id` DESC";
        $sql = "SELECT *  FROM `video_spider_url`  ";
//         $sql = "SELECT *  FROM `video_spider_url` WHERE id IN(1068,1067,1066,1065) ";
        $query = $this->db->query( $sql);
        $arr_urls = $query->result_array();
        if(empty($arr_urls)){
        	exit('没有记录');
        }
        foreach ($arr_urls as $value) {
            $id =  intval($value['id']);
            $type_id = intval($value['type_id']);
            $game_id = intval($value['game_id']);
            $url = trim($value['url']);
            $title = trim($value['title']);
            $key_word = trim($value['key_word']);
            $status =  intval($value['status']);
            $page_type = intval($value['page_type']);
            if(empty($url) || empty($game_id) || empty($type_id)){
            	continue;
            }
            $str_return = '';
            $params['status']=100;//正式开始
            $params['updated']=time();
            $this->video_model->update_video_spider_url($id,$params);
            $return = FALSE;
            switch ($type_id) {
            	case 6://作者解说
            	    if($page_type==1){
            	         $return  = $this->simple_page($url,$game_id,$type_id);
            	    }elseif($page_type==2){
            	        $return  = $this->author_album($url,$game_id,$type_id,$key_word);
            	    }elseif($page_type==3){
            	        $return  = $this->search_album($url,$game_id,$type_id,$key_word);
            	    }
        	        //完成
        	        $params['status']=200;
        	        $params['updated']=time();
        	        $this->video_model->update_video_spider_url($id,$params);
            	break;
            	default:
            	    $params['status']=400; 
            	    $this->video_model->update_video_spider_url($id,$params);
            	    continue;
            	break;
            }
        }
    }
    
    /**
     * @name:search_album
     * @description:搜索页入口
     * @author: Xiong Jianbang
     * @create: 2016-01-13 下午2:48:43
     **/
    public function search_album($url,$game_id,$type_id,$key_word){
    	$url = urldecode($url);
    	if(empty($url)){
    		return FALSE;
    	}
    	if(empty($game_id)){
    		return FALSE;
    	}
    	$this->load->library('video_parser');
    	$v = new video_parser();
    	$v->set_url($url);
    	$arr_urls = $v->youku_search($key_word);
    	if(empty($arr_urls)){
    		return FALSE;
    	}
    	foreach ($arr_urls as $url) {
    		$return  = $this->author_album($url,$game_id,$type_id,$key_word);
    	}
    }
    
    
    /**
     * @name:author_album
     * @description: 
     * /usr/local/php/bin/php  /mnt/hgfs/admin.kuaiyouxi.com/index.php  api/spider   author_album   'http%3a%2f%2fi.youku.com%2fu%2fUNTk2MTA0MjUy%2fplaylists'  12
     * @param: $url=采集入口URL地址，需要urlencode编码
     * @param:$game_id=自定义的游戏ID号
     * @author: Xiong Jianbang
     * @create: 2015-11-25 下午5:48:43
     **/
    public function author_album($url='',$game_id=0,$type_id=0,$key_word=''){
//         $url = 'http%3a%2f%2fi.youku.com%2fu%2fUNTU1Mzg3Mzk2%2fplaylists';
//         http%3a%2f%2fi.youku.com%2fu%2fUNTk2MTA0MjUy%2fplaylists                   马里奥红叔
//         http%3a%2f%2fi.youku.com%2fu%2fUMTQwNzg5NDU3Mg%2fplaylists    五之歌
//         http%3a%2f%2fv.huya.com%2fmc%2fjieshuo.html
        $url = urldecode($url);
        if(empty($url)){
            return FALSE;
        }
        if(empty($game_id)){
            return FALSE;
        }
        $this->load->library('video_parser');
        $v = new video_parser();
        $v->set_url($url);
        $data = $v->spider($key_word);
        if(empty($data)){
            return FALSE;
        }
        if($this->save_author_commentary($data, $v,$game_id,$type_id)){
            return TRUE;
        }
        return FALSE;
    }
    
    /**
     * @name:simple_page
     * @description: 
     *  /usr/local/php/bin/php  /mnt/hgfs/admin.kuaiyouxi.com/index.php  api/spider   simple_page   'http%3a%2f%2fv.youku.com%2fv_show%2fid_XMTQwMjg1MzkzNg%3d%3d.html%3ff%3d26322574%26from%3dy1.2-3.2'  12
     * @param: 
     * @return: 
     * @author: Xiong Jianbang
     * @create: 2015-12-7 下午6:14:42
     **/
    public function simple_page($url='',$game_id=0,$type_id=0){
        //http%3a%2f%2fv.youku.com%2fv_show%2fid_XMTQwMjg1MzkzNg%3d%3d.html%3ff%3d26322574%26from%3dy1.2-3.2
        $url = urldecode($url);
        if(empty($url)){
            return FALSE;
        }
        if(empty($game_id) || empty($type_id)){
            return FALSE;
        }
        $v = new video_parser();
        $v->set_url($url);
        $data = $v->get_youku_simple_page_with_screenshot();
        if($this->save_simple_author_commentary($data,$v,$game_id,$type_id)){
        	return TRUE;
        }
        return FALSE;
    }
    
    /**
     * @name:save_simple_author_commentary
     * @description: 
     * @param: 
     * @return: 
     * @author: Xiong Jianbang
     * @create: 2015-12-8 下午12:05:19
     **/
    private function save_simple_author_commentary($data,$obj_video,$game_id=0,$type_id=0){
        if(empty($data)){
            return FALSE;
        }
        //创建图片存放目录
        $date =  '/video_img' .date('/Y/m/d/');  //添加模块名作目录一部分
        $to_save = $this->config->item('image_root_path') . $date;
        if(!is_dir($to_save)){
            create_my_file_path($to_save,0777);
        }
        //判断作者是否存在
        $author_id = 0;
        if( isset($data['vAuthorName']) && !empty($data['vAuthorName'])){
            $tmp_where = array(
                    'va_name'=>$data['vAuthorName'],//'作者名称',
                    'va_game_id'=>$game_id//'游戏ID',
            );
            $author_id = $this->video_model->check_author_by_name($tmp_where);
            if($author_id==FALSE){//如果作者不存在，则添加
                //采集作者的icon
                $tmp_icon_get = '';//作者头像(本地上传)',
                if(!empty($data['aIcon'])){
                    $tmp_icon_get = save_remote_image($data['vAuthorImgUrl'],$to_save);
                    $tmp_icon_get = str_replace($this->config->item('image_root_path'), '', $tmp_icon_get);
                }
                $arr = array(
                        'in_date'=>time(),//'采集日期',
                        'va_name'=>$data['vAuthorName'],//'作者名称',
                        'va_game_id'=>$game_id,//'游戏ID',
                        'va_icon'=>$data['vAuthorImgUrl'],//'作者头像(采集)',
                        'va_icon_get'=>$tmp_icon_get,//'作者头像(编辑)',
                        'va_isshow'=>1,//'是否显示(1显示,2隐藏)',
                        'va_intro'=>'',//'作者简介',
                        'va_email'=>'',//'作者E-Mail',
                        'va_order'=>0,//'排序号',
                );
                $author_id = $this->video_model->save_author_info($arr);
            }
        }
        //判断视频专辑类别是否存在
        $tmp_where = array(
                'vc_name'=>$data['sName'],//'视频专辑类别名称',
                'vc_type_id'=>$type_id,//类别标记(1任务，2解说，3赛事战况，4集锦，5职业)
//                 'vc_game_id'=>$game_id,//'游戏ID',
                'vc_author_id'=>$author_id//解说作者ID
        );
        $category_id = $this->video_model->check_category_by_name($tmp_where);
        if($category_id==FALSE){//如果 视频类别 不存在，则添加
            //采集视频专辑类别的icon
            $tmp_icon_get = '';//视频类别(本地上传)',
            if(!empty($data['sImg'])){
                $tmp_icon_get = save_remote_image($data['sImg'],$to_save);
                $tmp_icon_get = str_replace($this->config->item('image_root_path'), '', $tmp_icon_get);
            }
            $arr = array(
                    'in_date'=>time(),//'采集日期',
                    'vc_type_id'=>$type_id,//'类别标记(1任务，2解说，3赛事战况，4集锦，5职业)',
                    'vc_game_id'=>$game_id,//'游戏ID',
                    'vc_author_id'=>$author_id,//解说作者ID(来自video_author_info表)'
                    'vc_name'=>$data['sName'],//'类别名称',
                    'vc_intro'=>'',//'类别简介',
                    'vc_isshow'=>1,//'是否显示(1显示,2隐藏)',
                    'vc_order'=>isset($data['number'])?intval($data['number']):'',//'排序号',
                    'vc_icon'=> isset($data['sImg'])?$data['sImg']:'',//分类图标(采集)
                    'vc_icon_get'=>$tmp_icon_get,//分类图标(编辑)
                    'vc_scount'=> isset($data['sCount'])?intval($data['sCount']):0,//专辑视频数量
                    'vc_splaycount'=>isset($data['sPlayCount'])?intval($data['sPlayCount']):0,//专辑播放次数
                    'vc_playcount'=>0//本地播放次数
    
            );
            $category_id = $this->video_model->save_category_info($arr);
        }
        $realplayurl = $data['vRealPalyUrl'];
        $tmp_playurl_get = '';//优酷播放地址(解析出来的)',
        $tmp_playurl_get = $obj_video->parse();//播放地址(解析出来的)',
        $tmp_playurl_get = json_decode($tmp_playurl_get,true);
        $video_id = $tmp_playurl_get['vid'];
    
        $tmp_arr = array(
                'video_id'=>$video_id
        );
        //如果视频已经存在，则不插入当次的数据
        if( $this->video_model->check_video_by_name( $tmp_arr )!=FALSE ){
            return FALSE;
        }
        $tmp_img = '';//播放图片
        //下载播放图片
        if( isset($data['vImgUrl']) && !is_empty($data['vImgUrl']) ){
            $tmp_img = save_remote_image($data['vImgUrl'],$to_save);
            $tmp_img = str_replace($this->config->item('image_root_path'), '', $tmp_img);
        }
        $tmp_arr = array(
                'in_date'=>time(),//'采集日期',
                'vvl_hi_id'=>0,//'英雄ID(来自video_hero_info表)',
                'vvl_game_id'=>$game_id,//'游戏ID',
                'vvl_category_id'=>$category_id,//'视频联赛ID(来自video_category_info表)',
                'vvl_type_id'=>$type_id,//'视频类型（1任务，2解说，3赛事战况，4集锦）',
                'vvl_sourcetype'=>$obj_video->map_source_type($tmp_playurl_get['type']),//'视频来源（1优酷，2多玩）',
                'vvl_imgurl'=>isset($data['vImgUrl'])?$data['vImgUrl']:'default',//'视频图片URL地址',
                'vvl_imgurl_get'=>$tmp_img,//'视频图片URL地址',
                'vvl_time'=>isset($data['vTime'])?trim($data['vTime']):'',//'视频时长',
                'vvl_playurl'=>isset($data['vRealPalyUrl'])?$data['vRealPalyUrl']:'',//'优酷播放地址，需要解析',
                'vvl_author_id'=>$author_id,//'解说作者ID(来自video_author_info表)',
                'vvl_title'=>$data['vTitle'],//'视频标题',
                'vvl_playurlback'=>isset($data['vRealPlayUrlBack'])?$data['vRealPlayUrlBack']:'',//'视频备用地址',
                'vvl_playurlback_get'=>'',//'视频备用地址(解析出来的)',
                'vvl_playcount'=>intval($data['vPlayCount']),//'视频播放次数(采集)',
                'vvl_count'=>0,//'视频本地播放次数(本地记录)',
                'vvl_sort_sys'=>isset($data['number'])?intval($data['number']):0,//系统默认排序
                'vvl_video_id' => $tmp_playurl_get['vid'],
                'vvl_upload_time'=> isset($data['createDate'])?strtotime($data['createDate']):'',//源网站上给予的该视频的上传时间，视频的上传时间
//                 'vvl_playurl_get'=>$tmp_playurl_get['msg'],//'优酷播放地址(解析出来的)',
                'vvl_playurl_get' => isset($data['vRealPalyUrl'])?$data['vRealPalyUrl']:'',
        );
        $video_id = $this->video_model->save_hero_video($tmp_arr,false);
        if($video_id){
          return TRUE;
        }
        return FALSE;
    }
    
    /**
     * @name:save_author_commentary
     * @description: 保存作者解说的数据
     * @param: $data=采集来的数据集合
     * @param:$obj_video=视频对象
     * @param:$game_id=自定义的游戏ID
     * @param:$type=采集类型
     * @return: 
     * @author: Xiong Jianbang
     * @create: 2015-11-30 下午2:07:02
     **/
    private function save_author_commentary($data,$obj_video,$game_id=0,$type=0,$is_hidden=FALSE){
        //创建图片存放目录
        $date =  '/video_img' .date('/Y/m/d/');  //添加模块名作目录一部分
        $to_save = $this->config->item('image_root_path') . $date;
        if(!is_dir($to_save)){
            create_my_file_path($to_save,0777);
        }
        //判断作者是否存在
        $author_id = 0;
        $vvl_uid = 0;
        if( isset($data['aName']) && !empty($data['aName'])){
            $tmp_where = array(
                    'va_name'=>$data['aName'],//'作者名称',
                    'va_game_id'=>$game_id//'游戏ID',
            );
            $author_id = $this->video_model->check_author_by_name($tmp_where);
            if($author_id==FALSE){//如果作者不存在，则添加
                //采集作者的icon
                $tmp_icon_get = '';//作者头像(本地上传)',
                if(!empty($data['aIcon'])){
                    $tmp_icon_get = save_remote_image($data['aIcon'],$to_save);
                    $tmp_icon_get = str_replace($this->config->item('image_root_path'), '', $tmp_icon_get);
                }
                $arr = array(
                        'in_date'=>time(),//'采集日期',
                        'va_name'=>$data['aName'],//'作者名称',
                        'va_game_id'=>$game_id,//'游戏ID',
                        'va_icon'=>$data['aIcon'],//'作者头像(采集)',
                        'va_icon_get'=>$tmp_icon_get,//'作者头像(编辑)',
                        'va_isshow'=>1,//'是否显示(1显示,2隐藏)',
                        'va_intro'=>'',//'作者简介',
                        'va_email'=>'',//'作者E-Mail',
                        'va_order'=>0,//'排序号',
                );
                $author_id = $this->video_model->save_author_info($arr);
            }
            unset($arr);
            //添加到用户中心
            $vvl_uid = $this->member_model->check_nickname($data['aName']);
            if($vvl_uid==FALSE){//如果用户中心不存在该用户，则添加
                $arr = array(
                        'regdate'=>time(),//'注册日期',
                        'nickname'=>$data['aName'],//'作者名称',
                        'username'=>'k_'.uniqid(),//'用户名',
                        'source'=>2,//'注册来源',
                );
                $vvl_uid = $this->member_model->memer_info_add($arr);
                //采集作者的icon
                if(!empty($data['aIcon'])){
                    //生产环境抓取图片的接口
                    $get_img_url = UC_API . '/api/get_avatar_img.php';
                    $arr_img = array('local_img'=>$data['aIcon'],'uid'=>$vvl_uid);
                    //调用ucenter的头像处理接口
                    $json = curl_post($get_img_url,$arr_img);
                }
            }
        }
        unset($arr);
        //如果解说专辑不存在，则跳出当次执行
        if(!isset($data['listSeries']) || empty($data['listSeries']) || empty($author_id) ){
            return FALSE;
        }
        //循环插入视频
        foreach ($data['listSeries'] as $data2){
            //判断视频专辑类别是否存在
            $tmp_where = array(
                    'vc_name'=>$data2['sName'],//'视频专辑类别名称',
                    'vc_type_id'=>$type,//类别标记(1任务，2解说，3赛事战况，4集锦，5职业)
//                     'vc_game_id'=>$game_id,//'游戏ID',
                    'vc_author_id'=>$author_id//解说作者ID
            );
            $category_id = $this->video_model->check_category_by_name($tmp_where);
            if($category_id==FALSE){//如果 视频类别 不存在，则添加
                //采集视频专辑类别的icon
                $tmp_icon_get = '';//视频类别(本地上传)',
                if(!empty($data2['sImg'])){
                    $tmp_icon_get = save_remote_image($data2['sImg'],$to_save);
                    $tmp_icon_get = str_replace($this->config->item('image_root_path'), '', $tmp_icon_get);
                }
                if(empty($data2['sName'])){
                	continue;
                }
                $arr = array(
                        'in_date'=>time(),//'采集日期',
                        'vc_type_id'=>$type,//'类别标记(1任务，2解说，3赛事战况，4集锦，5职业)',
                        'vc_game_id'=>$game_id,//'游戏ID',
                        'vc_author_id'=>$author_id,//解说作者ID(来自video_author_info表)'
                        'vc_name'=>$data2['sName'],//'类别名称',
                        'vc_intro'=>'',//'类别简介',
                        'vc_isshow'=>1,//'是否显示(1显示,2隐藏)',
                        'vc_order'=>isset($data2['number'])?intval($data2['number']):'',//'排序号',
                        'vc_icon'=> isset($data2['sImg'])?$data2['sImg']:'',//分类图标(采集)
                        'vc_icon_get'=>$tmp_icon_get,//分类图标(编辑)
                        'vc_scount'=> isset($data2['sCount'])?intval($data2['sCount']):0,//专辑视频数量
                        'vc_splaycount'=>isset($data2['sPlayCount'])?intval($data2['sPlayCount']):0,//专辑播放次数
                        'vc_playcount'=>0,//本地播放次数
                        'vc_uid' =>$vvl_uid //用户中心ID
        
                );
                $category_id = $this->video_model->save_category_info($arr);
            }
            //如果 视频类别ID 为空，则跳过当次执行
            if( empty($category_id) ){
                continue;
            }
            	
            //添加游戏视频
            if( !empty($data2['listVideos']) ){
                foreach ($data2['listVideos'] as $val2){
                    $realplayurl = $val2['vRealPalyUrl'];
                    if(empty($realplayurl)){
                    	continue;
                    }
                    $obj_video->set_url($realplayurl);
                    $tmp_playurl_get = '';//优酷播放地址(解析出来的)',
                    $tmp_playurl_get = $obj_video->parse();//播放地址(解析出来的)',
                    $tmp_playurl_get = json_decode($tmp_playurl_get,true);
                    $video_id = $tmp_playurl_get['vid'];
                    
                    $tmp_arr = array(
                            'video_id'=>$video_id
                    );
                    //如果视频已经存在，则不插入当次的数据
                    if( $this->video_model->check_video_by_name( $tmp_arr )!=FALSE ){
                        continue;
                    }
                    $tmp_img = '';//播放图片
                    //下载播放图片
                    if( !is_empty($val2['vImgUrl']) ){
                        $tmp_img = save_remote_image($val2['vImgUrl'],$to_save);
                        $tmp_img = str_replace($this->config->item('image_root_path'), '', $tmp_img);
                    }
                    $tmp_arr = array(
                    		'in_date'=> time(),//'采集日期',
//                             'in_date'=> isset($val2['createDate'])?strtotime($val2['createDate']):'',//'采集日期',
                            'vvl_hi_id'=>0,//'英雄ID(来自video_hero_info表)',
                            'vvl_game_id'=>$game_id,//'游戏ID',
                            'vvl_category_id'=>$category_id,//'视频联赛ID(来自video_category_info表)',
                            'vvl_type_id'=>$type,//'视频类型（1任务，2解说，3赛事战况，4集锦）',
                            'vvl_sourcetype'=>$obj_video->map_source_type($tmp_playurl_get['type']),//'视频来源（1优酷，2多玩）',
                            'vvl_imgurl'=>$val2['vImgUrl'],//'视频图片URL地址',
                            'vvl_imgurl_get'=>$tmp_img,//'视频图片URL地址',
                            'vvl_time'=>isset($val2['vTime'])?trim($val2['vTime']):'',//'视频时长',
                            'vvl_playurl'=>isset($val2['vRealPalyUrl'])?$val2['vRealPalyUrl']:'',//'优酷播放地址，需要解析',
                            'vvl_playurl_get'=>$tmp_playurl_get['msg'],//'优酷播放地址(解析出来的)',
                            'vvl_author_id'=>$author_id,//'解说作者ID(来自video_author_info表)',
                            'vvl_title'=>$val2['vTitle'],//'视频标题',
                            'vvl_playurlback'=>isset($val2['vRealPlayUrlBack'])?$val2['vRealPlayUrlBack']:'',//'视频备用地址',
                            'vvl_playurlback_get'=>'',//'视频备用地址(解析出来的)',
                            'vvl_playcount'=>intval($val2['vPlayCount']),//'视频播放次数(采集)',
                            'vvl_count'=>0,//'视频本地播放次数(本地记录)',
                            'vvl_sort_sys'=>intval($val2['number']),//系统默认排序
                            'vvl_video_id' => $tmp_playurl_get['vid'],
                            'vvl_uid'=>$vvl_uid, //用户中心的UID
                            'vvl_upload_time'=> isset($val2['createDate'])?strtotime($val2['createDate']):'',//源网站上给予的该视频的上传时间，视频的上传时间
                    );
                    if($is_hidden){
                    	$tmp_arr['va_isshow'] =  2; //隐藏
                    }
                    $this->video_model->save_hero_video($tmp_arr,false);
                }
            }
        }
        return TRUE;
    }
    
    /**
     * @name:get_youtube_videos
     * @description:
     *   /usr/local/php/bin/php  /mnt/hgfs/admin.kuaiyouxi.com/index.php  api/spider   dump_youtube_videos
     * @param:
     * @return:
     * @author: Xiong Jianbang
     * @create: 2015-12-18 下午5:43:06
     **/
    public function dump_youtube_videos(){
        ini_set('memory_limit', '512M');
        $sql = "SELECT *  FROM  `temp_video_game_info`     ORDER BY `id` DESC";
        $query = $this->db->query( $sql);
        $res = $query->result_array();
        if(empty($res)){
            exit('没有记录');
        }
        file_put_contents($this->videos_filename, json_encode($res));
        exit('导出成功');
    }
    
    
    
    /**
     * @name:get_youtube_videos
     * @description: 
     *  kyxproxy  /usr/local/php/bin/php  /mnt/hgfs/admin.kuaiyouxi.com/index.php  api/spider   get_youtube_videos 
     *  kyxproxy  /usr/local/php/bin/php  /data/web/admin.kuaiyouxi.com/index.php  api/spider   get_youtube_videos 
     *  UPDATE `temp_video_game_info` SET gi_sp_name='',gi_sp_logo_url='',gi_sp_logo='',gi_sp_firm ='',gi_sp_type='',gi_sp_source='',gi_sp_vid='',
     *  gi_sp_url='',gi_sp_mp4_url='',gi_sp_server='',gi_sp_md5='',gi_sp_size='',gi_sp_status='',gi_sp_date =''
     * @param: 
     * @return: 
     * @author: Xiong Jianbang
     * @create: 2015-12-18 下午5:43:06
     **/
    public function get_youtube_videos(){
        ini_set('memory_limit', '512M');
        $sql = "SELECT *  FROM  `temp_video_game_info`   ";
        $query = $this->db->query( $sql);
        $res = $query->result_array();
        if(empty($res)){
            exit('没有记录');
        }
        $v = new video_parser();
        foreach ($res as $value) {
            $params = array();
            $id = intval($value['id']);
            $package_name = !empty($value['gi_packname'])?trim($value['gi_packname']):'';
            if(empty($package_name)){
            	continue;
            }
            //成功拿到下载地址的不用跑了
           if(!empty($value['gi_sp_url']) && !empty($value['gi_sp_size']) && $value['gi_sp_status']==200){
               continue;
           }
           $params['gi_sp_date '] = time();
           echo $google_url = "https://play.google.com/store/apps/details?id={$package_name}";
           $v->set_url($google_url);
           $json = $v->parse();
           $params['gi_sp_url '] = $google_url;
           if(empty($json)){
               $params['gi_sp_status '] = 404;//打不开网页
               $this->db->where('id', $id);
               $this->db->update('temp_video_game_info', $params);
               continue;
           }
           $arr_return = json_decode($json,TRUE);
           $vvl_server_url = $arr_return['msg'];
           if(empty($vvl_server_url)){
               $params['gi_sp_status '] = 400;//没有匹配到视频
               $this->db->where('id', $id);
               $this->db->update('temp_video_game_info', $params);
               continue;
           }
           $dir_name =  '/video_mp4/' .date('Y/m/d/');
           $to_save = $GLOBALS['UPLOAD_DIR']. $dir_name ;
           if(!is_dir($to_save)){
               create_my_file_path($to_save,0755);
           }
           //抓取视频
           $tmp_video = curl_get_video($vvl_server_url,$to_save,TRUE);
           
           $data = $v->get_google_app_spider();
           //抓取LOGO图片
           $dir_name =  '/video_img' .date('/Y/m/d/');  
           $to_save = $this->config->item('image_root_path') . $dir_name;
           if(!is_dir($to_save)){
               create_my_file_path($to_save,0777);
           }
           $logo_get = '';
           if(!empty($data['logo_pic'])){
               $logo_get = save_remote_image($data['logo_pic'],$to_save);
               $logo_get = str_replace($this->config->item('image_root_path'), '', $logo_get);
           }
           
           $params['gi_name '] = $data['game_name'];
           $params['gi_sp_firm '] = $data['firm_name'];
           $params['gi_sp_type '] = $data['type_name'];
           $params['gi_sp_source '] = $v->map_source_type($arr_return['type']);
           $params['gi_sp_vid '] = $arr_return['vid'];
           $params['gi_sp_mp4_url '] = $vvl_server_url;
           $params['gi_sp_logo '] = $logo_get;
           $params['gi_sp_logo_url '] = $data['logo_pic'];
           $params['gi_sp_status '] = 200;
           if(is_file($tmp_video)){
               $params['gi_sp_md5 '] = md5_file($tmp_video);
               $params['gi_sp_size '] = filesize($tmp_video);
               $params['gi_sp_server '] = str_replace($GLOBALS['UPLOAD_DIR'], '', $tmp_video);
               $this->db->where('id', $id);
               $this->db->update('temp_video_game_info', $params);
           }
           echo "\n";
        }
    }
}