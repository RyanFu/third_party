<?php
/**
 * @description: 获取视频的json文件
 * @author: xiongjianbang
 * @file:vedio_parser.php
 * @charset: UTF-8
 * @time: 2015-04-25 18:22
 * @version 1.0
 **/
    

class Video_parser {
    
    private $url;
    
    private $key_word;
    
    private $arr_aipai_used_user;
    
    private $arr_aiapi_album;
    
    public function __construct($url=''){
        if(!empty($url)){
           $this->set_url($url);
        }
        set_time_limit(0);
        $this->arr_aipai_used_user = array();
        $this->arr_aiapi_album = array();
        require_once( KYX_ROOT_DIR. '/application/libraries/simple_html_dom.php');
    }
    
    /**
     * @name:set_url
     * @description: 设置url
     * @param: $url=待抓取的url地址
     * @return: url地址
     * @author: Xiong Jianbang
     * @create: 2015-4-28 下午4:55:43
     **/
    public function set_url($url){
    	if(empty($url)){
    		return json_encode(array('msg'=>'Page is empty','status'=>400));
    	}
    	$this->url = $url;
    }
    
    /**
     * @name:get_url
     * @description: 获取网址
     * @return: 获取网址
     * @author: Xiong Jianbang
     * @create: 2015-4-28 下午4:52:27
     **/
    public function get_url(){
    	return $this->url;
    }
    
    /**
     * @name:map_source_type
     * @description: 视频来源类型和数字的映射关系
     * @param:来源字符串
     * @return: 来源类型ID
     * @author: Xiong Jianbang
     * @create: 2015-4-28 下午5:11:04
     **/
    public function map_source_type($type=''){
    	$arr = array(
    		'youku' => 1,
    		'duowan_letv' => 2,
	        'sohu' => 3,
	        'qq' => 4,
	        'tudou' => 5,
    	    'ku6'=>6,
    	    'aipai'=>7,
    	    'leshiyun'=>8,
    	    '17173'=>9,
    	    '4399'=>10,
    	   'kamcord'=>11,
    	   'everyplay'=>12,
    	   'youshixiu'=>13,
            'ali' => 14,
    	    'xiaolu'=>15,
    	    'youtube'=>16,
            'diaobao' => 17
    	);
    	return isset($arr[$type])?$arr[$type]:0;
    }
    
    /**
     * @name:get_source_arr
     * @description: 获取来源类型的数组
     * @return: array
     * @author: Xiong Jianbang
     * @create: 2015-5-6 下午7:10:38
     **/
    public function get_source_arr(){
       return$arr = array(
                1 => 'youku',
                2 => 'duowan_letv',
                3 => 'sohu',
                4 =>  'qq',
                5 => 'tudou',
                6=>'ku6',
                7=>'aipai',
                8=>'leshiyun',
                9=> '17173',
               10=>'4399',
               11=>'kamcord',
               12=>'everyplay',
               13=>'youshixiu',
               14 => 'ali',
               15=>'xiaolu',
               16=>'youtube',
               17=>'diaobao'
        );
    }
    /**
     * @name:remap_source_type
     * @description: 视频来源类型和数字的反映射关系
     * @param $type_id=来源类型ID
     * @return: 来源字符串
     * @author: Xiong Jianbang
     * @create: 2015-4-28 下午5:11:04
     **/
    public function remap_source_type($type_id=''){
        $arr = $this->get_source_arr();
        return isset($arr[$type_id])?$arr[$type_id]:'其他';
    }
    

    /**
     * @name:parse 主要是提取视频真实源地址！！！！！！
     * @description: 对指定的不同网址提取video视频源地址
     * @return: video视频源地址
     * @author: Xiong Jianbang
     * @create: 2015-4-25 下午5:10:29
     **/
    public function parse(){
    	$sub_host = $this->get_sub_host();
    	switch ($sub_host) {
    	    //优酷网站
    	    case '.youku.com':
    	        return $this->get_youku_vedio();
	        break;
	        //pcgames.com.cn 太平洋游戏网手游频道
	        case '.pcgames.com.cn':
	        	return $this->get_pcgames_vedio();
	        	break;
        	//hs.tuwan.com 兔玩戏网手游频道
        	case '.tuwan.com':
        		return $this->get_tuwan_vedio();
            break;
            //5353网站
            case '.5253.com':
                return $this->get_5253_vedio();
            break;
            case '.tgbus.com':
                return $this->get_tgbus_vedio();
            break;
            case '.40407.com':
                return $this->get_40407_vedio();
            break;
            case '.yxzoo.com':
                return $this->get_yxzoo_vedio();
            break;
             case '.mofang.com':
                 return $this->get_mofang_vedio();
            break;
            case '.gao7.com':
                return $this->get_gao7_vedio();
             break;
            //手游网站
            case '.shouyou.com':
                return $this->get_shouyou_vedio();
            break;
	        //多玩网站，分多种视频来源，现发现的有乐视云，优酷，土豆
	        case '.duowan.com':
	            return $this->get_duowan_vedio();
	        break;
	        //腾讯视频
	        case '.qq.com':
	            return $this->get_qq_vedio();
	        break;
	        case '.tudou.com':
	            return $this->get_tudou_vedio();
	        break;
	        //178网站
	        case '.178.com':
	            return $this->get_178_vedio();
	        break;
	        //66游手机
	        case '.66u.com':
	            return $this->get_66u_vedio();
	        break;
	        //4399
	        case '.4399.com':
	            return $this->get_4399_vedio();
	        break;
	        //4399
	        case '.4399pk.com':
	            return $this->get_4399pk_vedio();
	        break;
    	    //德玛西亚
    		case '.demaxiya.com':
    		    return $this->get_demaxiya_video();
    		break;
    		//撸撸趣
    		case '.lolqu.com':
    		    return $this->get_lolqu_video();
    		break;
    		//安游
    		case '.ahgame.com':
    		    return $this->get_ahgame_vedio();
    		break;
    		//全球电竟网
    		case '.ooqiu.com':
    		    return $this->get_ooqiu_vedio();
		    break;
		    //爱拍游戏网
		    case '.aipai.com':
		        return $this->get_aipai_video();
		    break;
		    case '.kamcord.com':
		        return $this->get_kamcord_m3u8_video();
		    break;
		    case '.huya.com':
		        return $this->get_huya_video();
		    break;
		    case '.everyplay.com':
		        return $this->get_everyplay_video();
		     break;
		     case '.youshixiu.com':
		         return $this->get_youshixiu_video();
		    break;
		     case '.google.com':
		         return $this->get_google_video();
		     break;
    		default:
    			return json_encode(array('msg'=>'Error URL','status'=>400));
    		break;
    	}
    }
    
    
    /**
     * @name:spider
     * @description: 专辑爬虫
     * @return: 
     * @author: Xiong Jianbang
     * @create: 2015-11-25 下午2:30:55
     **/
    public function spider($key_word=''){
        $sub_host = $this->get_sub_host();
        $this->key_word = trim($key_word);
        switch ($sub_host) {
            //优酷网站入口：http://i.youku.com/u/UMTQwNzg5NDU3Mg/playlists
        	case '.youku.com':
        	    return $this->get_youku_album();
        	break;
        	//虎牙网站入口：http://v.huya.com/mc/jieshuo.html
        	case '.huya.com':
        	    return $this->get_huya_jieshuo();
        	break;
        }
    }
    
    /**
     * @name:simple_page_spider
     * @description: 单页视频的抓取
     * @author: Xiong Jianbang
     * @create: 2015-12-7 下午3:46:21
     **/
    public function simple_page_spider(){
        $sub_host = $this->get_sub_host();
        switch ($sub_host) {
            //优酷网站入口：
        	case '.youku.com':
        	    return $this->get_youku_simple_page();
            	break;
        }
    }
    
    public function get_google_app_spider(){
        $simple_html = file_get_html($this->url);
        if(!is_object($simple_html)){
            return FALSE;
        }
        $data['game_name'] = isset($simple_html->find('h1.document-title div',0)->innertext)? strip_tags($simple_html->find('h1.document-title div',0)->innertext):'';
        $data['firm_name'] = isset($simple_html->find('a.primary span',0)->innertext)?$simple_html->find('a.primary span',0)->innertext:'';
        $data['type_name'] =  isset($simple_html->find('a.category span',0)->innertext)?$simple_html->find('a.category span',0)->innertext:'';
        $data['logo_pic'] =  isset($simple_html->find('img.cover-image',0)->src)?str_replace('//', 'http://', $simple_html->find('img.cover-image',0)->src):'';
        return $data;
    }
    
    public function get_huya_jieshuo(){
        $huya_video_url = $this->url;
        $dir = pathinfo($huya_video_url,PATHINFO_DIRNAME);
        $filename = pathinfo($huya_video_url,PATHINFO_FILENAME);
        $ext = pathinfo($huya_video_url,PATHINFO_EXTENSION);
        $next_page =1;
        do{
            $huya_video_url = $dir.'/'.$filename.'_'.$next_page.'.'.$ext;
            $all_video_html = file_get_html($huya_video_url);
            echo $next_page = $all_video_html->find('span#pageNow',0)->next_sibling()->find('a',0)->innertext;
        }while (is_object($all_video_html));
    }
    
    /**
     * @name:get_youku_videos_list_data
     * @description: 找到所有优酷的视频数据
     * @param: 
     * @return: 
     * @author: Xiong Jianbang
     * @create: 2015-12-25 上午10:31:30
     **/
    public function get_youku_videos_list_data($youku_video_url){
        //找出该页所有的视频地址+上传时间
        $all_video_html = file_get_html($youku_video_url);
        if(!is_object($all_video_html)){
            return FALSE;
        }
        //获取视频总数量
        $video_str_count = isset($all_video_html->find('div.YK-box span.append',0)->innertext)?$all_video_html->find('div.YK-box span.append',0)->innertext:'';
        $video_total_count = 0;
        if(preg_match('/\d+,?\d+/', $video_str_count,$match)){
            $video_total_count = $match[0];
            $video_total_count = str_replace(',', '', $video_total_count);
        }
        //没有视频的话，不跑了
        //         if(empty($video_total_count)){
        //         	return FALSE;
        //         }
        //视频页每页的视频数量
        $video_per_count = 120;
        //获取视频页数
        $video_page = floor($video_total_count / $video_per_count);
        if($video_total_count % $video_per_count <> 0){
            $video_page++;
        }
        $arr_video_list = array();
        for ($page=1;$page<=$video_page+1;$page++){
            if(!is_object($all_video_html)){
                continue;
            }
            //如果超过视频，则不抓了，以免占用太多内存
            $videos_count_total = count($arr_video_list);
            if($videos_count_total>=10000){
            	continue;
            }
            $ajax_params = isset($all_video_html->find('div#params',0)->name)?$all_video_html->find('div#params',0)->name:'';
            $page_num = isset($all_video_html->find('div#page_num',0)->name)?$all_video_html->find('div#page_num',0)->name:'';
            if(empty($ajax_params)){
                continue;
            }
            $ajax_params = '{'.str_replace("'", '"', $ajax_params).'}';
            $arr_params = json_decode($ajax_params,TRUE);
            $ajax_url = isset($arr_params['ajax_url'])?$arr_params['ajax_url']:'';
            $last_str = isset($arr_params['last_str'])?$arr_params['last_str']:'';
            $v_page = isset($arr_params['v_page'])?$arr_params['v_page']:'';
            for($small_i = $page_num;$small_i<$page_num+3;$small_i++){
                if(empty($last_str)){
                    continue;
                }
                 $video_ajax_url =  'http://i.youku.com'.$ajax_url."fun_ajaxload/?__rt=1&__ro=&v_page={$v_page}&page_num={$small_i}&page_order=1&q=&last_str={$last_str}";
//                 echo "\n";
                $ajax_video_html = file_get_html($video_ajax_url);
                if(empty($ajax_video_html) ||  !is_object($ajax_video_html)){
                    continue;
                }
                foreach($ajax_video_html->find('div.yk-col4') as $v){
                    $href = isset($v->find('div.v-meta-title a',0)->href)?$v->find('div.v-meta-title a',0)->href:'';
                    $href = substr($href ,0,strrpos($href ,'?'));
                    $href = str_replace('=', '', $href);
                    if(empty($href)){
                        return false;
                    }
                    $img =  isset($v->find('div.v-thumb img',0)->src)?$v->find('div.v-thumb img',0)->src:'';
                    $title =  isset($v->find('div.v-meta-title a',0)->title)?$v->find('div.v-meta-title a',0)->title:'';
                    $c_time =  isset($v->c_time)?$v->c_time:'';
                    if(preg_match('/id_(.*?)\.html/', $href,$match)){
                        $video_id = $match[1];
                        $arr_video_list[$video_id] = array('title'=>$title,'c_time'=>$c_time,'href'=>$href,'img'=>$img);
                    }
                }
//                 print_r($arr_video_list);
            }
            //分页链接，类似于/u/UNTk2MTA0MjUy/videos/order_1_view_1_page_2_spg_1_stt_2155_sid_346643572_sst_1447526704
            $video_page_href = isset($all_video_html->find('ul.YK-pages li.next a',0)->href)?$all_video_html->find('ul.YK-pages li.next a',0)->href:'';
            $video_page_href = 'http://i.youku.com'.$video_page_href;
            $video_page_href = preg_replace('/page_\d{1,}_spg/', "page_{$page}_spg", $video_page_href);
            $all_video_html = @file_get_html($video_page_href);
            if(!is_object($all_video_html)){
                continue;
            }
        }
        return $arr_video_list;
        /**
         * 获得结果类似于下面：
         *  [http://v.youku.com/v_show/id_XNDk4NjAxMTUy.html] => Array
         (
                 [title] => ★我的世界★Minecraft《籽岷的极限生存实况 第一集中 月光光 心慌慌》
                 [c_time] => 2013-01-07 12:39:19
         )
         */
    }
    
    /**
     * @name:youku_search
     * @description: 优酷搜索页爬虫
     * @author: Xiong Jianbang
     * @create: 2016-01-13 下午2:30:55
     **/
    public function youku_search($key_word=''){
    	$game_title = '';
    	if(preg_match('/q_(.*?)$/',$this->url,$match)){
    		$game_title = $match[1];
    		//去掉中文的标点符号
    		$game_title = preg_replace("/[[:punct:]]/i", '', $game_title);
    		$game_title=  urlencode($game_title);
    		//去掉中文的标点符号
    		$game_title=preg_replace("/(%7E|%60|%21|%40|%23|%24|%25|%5E|%26|%27|%2A|%28|%29|%2B|%7C|%5C|%3D|\-|_|%5B|%5D|%7D|%7B|%3B|%22|%3A|%3F|%3E|%3C|%2C|\.|%2F|%A3%BF|%A1%B7|%A1%B6|%A1%A2|%A1%A3|%A3%AC|%7D|%A1%B0|%A3%BA|%A3%BB|%A1%AE|%A1%AF|%A1%B1|%A3%FC|%A3%BD|%A1%AA|%A3%A9|%A3%A8|%A1%AD|%A3%A4|%A1%A4|%A3%A1|%E3%80%82|%EF%BC%81|%EF%BC%8C|%EF%BC%9B|%EF%BC%9F|%EF%BC%9A|%E3%80%81|%E2%80%A6%E2%80%A6|%E2%80%9D|%E2%80%9C|%E2%80%98|%E2%80%99|%EF%BD%9E|%EF%BC%8E|%EF%BC%88)+/",' ',$game_title);
    		$game_title=urldecode($game_title);
    		$a_key = urlencode($game_title);
    		$this->url = preg_replace('/q_.*?$/', 'q_'.$a_key, $this->url);
    	}
        $search_video_url = $this->url;
        $dir = pathinfo($search_video_url,PATHINFO_DIRNAME);
        $filename = pathinfo($search_video_url,PATHINFO_FILENAME);
        $ext = pathinfo($search_video_url,PATHINFO_EXTENSION);
        $next_page =1;
        $arr_get_urls = array();
        $sleep_time = rand(1000, 2000000);
        usleep($sleep_time);
        $all_search_html = file_get_html($search_video_url);
        
        if( !is_object($all_search_html->find('div.sk_toolbar',0))  &&  !is_object($all_search_html->find('div.album_box',0))){
        	//使用IP代理
        	do{
        		$proxy_html = file_get_html("http://www.xicidaili.com/nt");
        		$arr_proxy_ip = array();
        		foreach($proxy_html->find('#ip_list tr.odd') as $p){
        			if(!is_object($p)){
        				continue;
        			}
        			$proxy_ip = is_object($p->find("td",2)) ? $p->find("td",2)->innertext : '';
        			$proxy_port = is_object($p->find("td",3)) ? $p->find("td",3)->innertext : '';
        			if(empty($proxy_ip) || empty($proxy_port)){
        				continue;
        			}
        			$arr_proxy_ip[] = $proxy_ip .':'.$proxy_port;
        		}
        		$proxy_key = rand(0,count($arr_proxy_ip)-1);
        		$arr_ch_opt = array(
        				'proxy'=> $arr_proxy_ip[$proxy_key],
        		);
        		print_r($arr_ch_opt);
        		$all_search_html = file_get_html($search_video_url,$arr_ch_opt);
        	}while ( is_object($all_search_html->find('div.sk_toolbar',0))  && is_object($all_search_html->find('div.album_box',0)));
        }
        
        do{
        	//http://www.soku.com/search_playlist/q_%E6%9E%81%E5%93%81%E9%A3%9E%E8%BD%A613_orderby_1_limitdate_0?site=14&page=6
            //查找内容
            foreach($all_search_html->find('div.album_box') as $e){
            	if(!is_object($e)){
            		continue;
            	}
            	//搜索到的标题
            	$playlist_title = is_object($e->find("div.album_tit a",0)) ? $e->find("div.album_tit a",0)->innertext : '';
            	$playlist_title = strip_tags($playlist_title);
            	//标题需要与游戏名称匹配
            	if(strpos($playlist_title, $game_title)!==FALSE){
            		$href = is_object($e->find("a.user_face",0)) ? $e->find("a.user_face",0)->href : '';
            		if(empty($href)){
            			continue;
            		}
            		$pos = strpos($href, '?');
            		if($pos!==FALSE){
            			$pos = strpos($href, '?');
            			$href = substr($href, 0,$pos);
            			$arr_get_urls[] = $href .  '/playlists';
            		}
            	}
            }
            echo "第{$next_page}页\r\n";
            $li_object = $all_search_html->find('div.sk_pager li.current',0);
            if(!is_object($li_object)){
            	break;
            } 
            $next_object = $li_object->next_sibling();
            if(!is_object($next_object)){
            	break;
            }
           $a_object = $next_object ->find('a',0);
            if(!is_object($a_object)){
            	break;
            }
            $next_page = $a_object->innertext;
            $search_video_url = $dir.'/'.$filename.'_orderby_1_limitdate_0?site=14&page='.$next_page;
            $sleep_time = rand(1000, 2000000);
            usleep($sleep_time);
            $all_search_html = file_get_html($search_video_url);
        }while (is_object($all_search_html));
    	
    	if(!empty($arr_get_urls)){
    		$arr_get_urls = array_unique($arr_get_urls);
    		return $arr_get_urls;
    	}
    	return FALSE;
    }
    
    /**
     * @name:aipai_game_spider
     * @description: 爱拍网站所有游戏页面的爬虫
     * @author: Xiong Jianbang
     * @create: 2016-01-20 下午11:37:55
     **/
    public function aipai_game_spider($game_id,$game_title){
    	    if(empty($game_id)){
    	    	return FALSE;
    	    }
    	    $arr_videos = array();
    		$first_game_url = "http://www.aipai.com/app/www/apps/gameAreaInfo.php?data={%22gameid%22:{$game_id},%22sort%22:%22id%22,%22page%22:1,%22pageSize%22:10,%22searchKey%22:%22%22,%22totalPage%22:1}&action=getWork";
    		echo $first_game_url = urldecode($first_game_url);
    		echo "\n";
    		$curl_opt = array('referer'=>'http://www.aipai.com');
    		$json = $this->curl_get($first_game_url, 30,$curl_opt);
    		$arr_temp = json_decode($json,TRUE);
    		if(empty($arr_temp) || empty($arr_temp['data'])){
    			return FALSE;
    		}
    		$arr_user = $this->get_aipai_user_data($arr_temp);
    		if(empty($arr_user) || !is_array($arr_user)){
    			return FALSE;
    		}
    		//用户去重
    		$arr_user = array_unique($arr_user);
            $arr_album = $this->get_aipai_user_album($arr_user);
    		//视频数组合并处理
    		$arr_videos = array_merge($arr_videos,$this->get_aipai_videos($arr_temp,$game_title));
    		$total = $arr_temp['total'];
    		$page = intval($total/10) +1;
    		unset($arr_temp);
    		for ($i=2;$i<=$page;$i++){
    			   if($i>50 ||  count($arr_videos)>=100){
    			   	   break;
    			   }
    			   $game_url = "http://www.aipai.com/app/www/apps/gameAreaInfo.php?data={%22gameid%22:{$game_id},%22sort%22:%22id%22,%22page%22:{$i},%22pageSize%22:10,%22searchKey%22:%22%22,%22totalPage%22:{$page}}&action=getWork";
    			   echo $game_url = urldecode($game_url);
    			   echo "\n";
    			   $json = $this->curl_get($game_url, 30,$curl_opt);
    			    $arr_temp = json_decode($json,TRUE);
    			    if(empty($arr_temp) || empty($arr_temp['data'])){
    			    	continue;
    			    }
    			    $arr_user = $this->get_aipai_user_data($arr_temp);
    			     if(empty($arr_user) || !is_array($arr_user)){
    			    	continue;
    			    }
    			    $arr_user = array_unique($arr_user);
    			    $arr_album = $this->get_aipai_user_album($arr_user);
    			    //视频数组合并处理
    			    $arr_videos = array_merge($arr_videos,$this->get_aipai_videos($arr_temp,$game_title));
    		}
    		return $arr_videos;
    }
    
    /**
     * @name:aipai_spider
     * @description: 爱拍爬虫
     * @return: Array
     * @author: Xiong Jianbang
     * @create: 2016-01-21 下午5:31:23
     **/
    public function aipai_spider($game_id,$game_title){
    	$arr_videos = $this->aipai_game_spider($game_id,$game_title);
    	if(!empty($arr_videos)){
	    	foreach ($arr_videos as $kd=>$vd) {
	    		$videoId = $vd['videoId'];
	    		foreach ($this->arr_aiapi_album as $va) {
	    			foreach ($va['album_video_list'] as $ka1=>$va1) {
	    				if($videoId==$ka1){
	    					$arr_videos[$kd]['albumTitle'] = $va['album_title'];
	//     					$arr_videos[$kd]['albumPic'] = $va['album_pic'];
	    					$arr_videos[$kd]['albumVideoCount'] = $va['album_video_count'];
	    					break;
	    				}
	    			}
	    		}
	    	}
    	}
//     	unset($this->arr_aiapi_album);
    	return $arr_videos;
    }
    
    
    /**
     * @name:get_aipai_user_album
     * @description: 爱拍用户的专辑汇总
     * @return: Array
     * @author: Xiong Jianbang
     * @create: 2016-01-21 下午5:31:23
     **/
    private function get_aipai_user_album($arr_user=array()){
    	if(empty($arr_user)){
    		return FALSE;
    	}
    	foreach ($arr_user as $value) {
    		//检查是否跑过该用户的专辑页
    		if(in_array($value,$this->arr_aipai_used_user)){
    			continue;
    		}
    		//作者所有专辑
    		$album_url = "http://home.aipai.com/{$value}".'?action=album&catagory=albumList';
    		$all_album_html = file_get_html($album_url);
    		if(empty($all_album_html) ||   !is_object($all_album_html->find('.zhuanji_list',0))){
    			continue;
    		}
    		//专辑列表
    		foreach($all_album_html->find('.zhuanji_list li') as $obj_album){
    			$arr_tmp_album = array();
    			$zp_count = is_object($obj_album->find('.zp',0))?$obj_album->find('.zp',0)->innertext:'';
    			if(empty($zp_count)){
    				continue;
    			}
    			$album_title = is_object($obj_album->find('h5 a',0))?$obj_album->find('h5 a',0)->innertext:'';
    			$album_pic = is_object($obj_album->find('a.pic img',0))?$obj_album->find('a.pic img',0)->src:'';
    			$album_video_url = is_object($obj_album->find('h5 a',0))?$obj_album->find('h5 a',0)->href:'';
    			$album_video_url = str_replace('&amp;', '&', $album_video_url);
    			if(empty($album_video_url) || empty($album_title)){
    				continue;
    			}
    			$ablum_video_html = file_get_html($album_video_url);
    			$arr_tmp_video = array();
    			//专辑下的视频列表
    			foreach($ablum_video_html->find('.video_list li') as $obj_video){
    				$video_title = is_object($obj_video->find('h5 a',0))?$obj_video->find('h5 a',0)->innertext:'';
    				$video_url = is_object($obj_video->find('h5 a',0))?$obj_video->find('h5 a',0)->href:'';
    				$video_id = pathinfo($video_url,PATHINFO_FILENAME);
    				$video_url = trim($video_url);
    				$arr_tmp_video[$video_id] = $video_title;
    			}
    			$arr_tmp_album['album_title'] = $album_title;
//     			$arr_tmp_album['album_pic'] = $album_pic;
//     			$arr_tmp_album['album_video_url'] = $album_url;
    			$arr_tmp_album['album_video_list'] = $arr_tmp_video;
    			$arr_tmp_album['album_video_count'] = $zp_count;
    			$this->arr_aiapi_album[] = $arr_tmp_album;
    			unset($arr_tmp_album);
    		}
    		$this->arr_aipai_used_user[] = $value;
    	}
    	return $this->arr_aiapi_album;
    }
    
    
    /**
     * @name:get_aipai_videos
     * @description: 爱拍视频汇总
     * @return: Array
     * @author: Xiong Jianbang
     * @create: 2016-01-21 下午5:31:23
     **/
    private function  get_aipai_videos($arr_temp=array(),$game_title=''){
    	if(empty($arr_temp) || empty($game_title)){
    		return FALSE;
    	}
    	$arr_return  = array();
    	foreach ($arr_temp['data'] as $value) {
    		foreach ($value as $v2) {
    			$uid = isset($v2['assetBid'])?$v2['assetBid']:'';
    			if(empty($uid)){
    				continue;
    			}
    			$flv = isset($v2['work']['flvFileName'])?preg_replace('/\?l=[a-z]?/', '', $v2['work']['flvFileName']):'';
    			$author_url = "http://home.aipai.com/{$uid}";
    			$all_author_html = file_get_html($author_url);
    			if(!is_object($all_author_html)){
    				continue;
    			}
    			//作者头像
    			$user_pic = isset($all_author_html->find('.wta_pic img',0)->src)?$all_author_html->find('.wta_pic img',0)->src:'';
    			$url = trim($v2['work']['url']);
    			$vid = pathinfo($url,PATHINFO_FILENAME);
    			$arr_return[] = array(
    					'uid'=>$uid,
    					'userName'=>trim($v2['assetName']),
    					'userPic'=>trim($user_pic),
    					'videoTitle'=>trim($v2['work']['title']),
    					'videoMp4'=>$v2['work']['baseURL'] . $flv,
    					'videoParseUrl'=>$v2['work']['baseURL'] . $flv,
    					'gameTitle'=> isset($v2['work']['game'])?trim($v2['work']['game']):$game_title,
    					'videoPic' =>isset($v2['work']['800fix'])?$v2['work']['800fix']:$v2['work']['big'],
    					'videoUrl' => $url,//播放地址
    					'videoTime' => $v2['work']['totalTime'], //播放时长
    					'videoClick' => $v2['work']['click'], //点击数
    					'videoCreateDate' => $v2['work']['saveTime'],//保存时间
    					'videoSource' => 'aipai',
    					'videoId' => $vid,
    			);
    		}
    	}
    	return $arr_return;
    }
    
    /**
     * @name:get_aipai_user_data
     * @description: 爱拍用户数据
     * @return: Array
     * @author: Xiong Jianbang
     * @create: 2016-01-21 下午5:31:23
     **/
    private function  get_aipai_user_data($arr_temp=array()){
    	if(empty($arr_temp)){
    		return FALSE;
    	}
    	$arr_return  = array();
    	foreach ($arr_temp['data'] as $value) {
    		foreach ($value as $v2) {
    			$uid = isset($v2['assetBid'])?$v2['assetBid']:'';
    			if(empty($uid)){
    				continue;
    			}
    			$arr_return[] = $uid;
    		}
    	}
    	return $arr_return;
    }
    
    
    public function huya_spider($game_name='',$game_title=''){
    	if(empty($game_name)){
    		return FALSE;;
    	}
    	$all_news_url = "http://v.huya.com/{$game_name}/new.html";
    	$all_news_html = file_get_html($all_news_url);
    	$next_page = 1;
    	$arr_videos = array();
    	do{
	    	echo "正处理第{$next_page}页";
	    	foreach($all_news_html->find('.uiVideo__item') as $obj_video){
	    		$arr_tmp = array();
	    		$video_title = is_object($obj_video->find(' .uiVideo__subtitle',0))?$obj_video->find(' .uiVideo__subtitle',0)->innertext:'';
	    		$video_url = is_object($obj_video->find(' .uiVideo__subtitle',0))?$obj_video->find(' .uiVideo__subtitle',0)->href:'';
	    		if(empty($video_url)){
	    			continue;
	    		}
	    		$this->set_url($video_url);
	    		$json = $this->parse();
	    		if(empty($json)){
	    			continue;
	    		}
	    		$arr_return = json_decode($json,TRUE);
	    		$parse_url = isset($arr_return['msg'])?$arr_return['msg']:'';
	    		if(empty($parse_url)){
	    			continue;
	    		}
	    		unset($json,$arr_return);
	    		$parse_url = urldecode($parse_url);
	    		$json = $this->curl_get($parse_url);
	    		$arr_return = json_decode($json,TRUE);
	    		$arr_tmp['gameTitle'] = $game_title;
	    		$arr_tmp['videoPic'] = $arr_return['result']['cover'];
	    		$arr_tmp['videoTitle'] = $video_title;
	    		$arr_tmp['videoMp4'] = $arr_return['result']['items'][0]['transcode']['urls'][0];
	    		$arr_tmp['videoUrl'] = $parse_url;
	    		$arr_tmp['videoTime'] = $arr_return['result']['items'][0]['transcode']['duration'];
	    		$arr_tmp['videoCreateDate'] = time();
	    		$arr_tmp['videoSource'] = 'duowan_letv';
	    		$arr_tmp['videoId'] = $arr_return['result']['items'][0]['vid'];
	    		
	    		$arr_videos[] = $arr_tmp;
	    	}
	    	print_r($arr_videos);
	    	$current_object = $all_news_html->find('#pageNow',0);
	    	if(!is_object($current_object)){
	    		return FALSE;;
	    	}
	    	$next_object = $current_object->next_sibling();
	    	if(!is_object($next_object)){
	    		return FALSE;;
	    	}
	    	$a_object = $next_object ->find('a',0);
	    	if(!is_object($a_object)){
	    		return FALSE;;
	    	}
	    	$next_page = $a_object->innertext;
	    	echo $all_news_url = "http://v.huya.com/{$game_name}/new_{$next_page}.html";
	    	echo "\n";
	    	$all_news_html = file_get_html($all_news_url);
    	}while(is_object($all_news_html));
    }
    
    
    /**
     * @name:get_youku_album
     * @description: 优酷专辑页爬虫 作者解说
     * @return: Array
     * @author: Xiong Jianbang
     * @create: 2015-11-25 下午5:31:23
     **/
    public function get_youku_album(){


        /**
         * @description: 接着开始抓取专辑页面
         **/
        $album_html = file_get_html($this->url);
        if(!is_object($album_html)){
            return FALSE;
        }
        //专辑数量
        $album_str_count = isset($album_html->find('div.YK-box span.append',0)->innertext)?$album_html->find('div.YK-box span.append',0)->innertext:'';
        $album_total_count = 0;
        if(preg_match('/\d+/', $album_str_count,$match)){
            $album_total_count = $match[0];
        }
        //没有专辑的话，不跑了
        if(empty($album_total_count)){
            echo $this->url . "该页面没有专辑\n";
            return FALSE;
        }
        $arr_video_list = array();
        //专辑数据的判断
        if($album_total_count<=100){
        	/**
        	 * @description: 先抓取视频页面，拿到视频的上传时间
        	 **/
        	//类似于个人主页http://i.youku.com/u/UNTU1Mzg3Mzk2
        	$sub_youku_home = substr($this->url, 0,strrpos($this->url, '/'));
        	$youku_video_url = $sub_youku_home .'/videos';
        	$arr_video_list = $this->get_youku_videos_list_data($youku_video_url);
        }
   
        
        
        
        //专辑每页数量
        $album_per_count = 60;
        //专辑页数
        $album_page = floor($album_total_count / $album_per_count);
        if($album_total_count % $album_per_count <> 0){
             $album_page++;
        }
        $arr_return = array();
        $arr_return['aIcon'] = $album_html->find('div.avatar img',0)->src;//作者头像地址
        $arr_return['aName'] = $album_html->find('div.avatar img',0)->title;//作者名称
        //从专辑页面进来
        for ($page=1;$page<=$album_page+1;$page++){
        	if(!is_object($album_html->find('div#params',0))){
        		continue;
        	}
        	if(!is_object($album_html->find('div#page_num',0))){
        		continue;
        	}
            $album_ajax_params = $album_html->find('div#params',0)->name;
            $page_num = $album_html->find('div#page_num',0)->name;
            $v_page = $page - 1;
            if(empty($v_page)){
            	continue;
            }
            $album_ajax_params = '{'.str_replace("'", '"', $album_ajax_params).'}';
            $arr_params = json_decode($album_ajax_params,TRUE);
            //ajax页面读取专辑
            for($small_i = $page_num;$small_i<$page_num+3;$small_i++){
                 $album_ajax_url = $this->url . "/fun_ajaxload/?__rt=1&__ro=&v_page={$v_page}&page_num={$small_i}&page_order=1&q=&last_str";
//                 echo "\n";
                $album_ajax_html =  file_get_html($album_ajax_url);
                if(!is_object($album_ajax_html)){
                    continue;
                }
                //专辑列表
                foreach($album_ajax_html->find('div.entry') as $e){
                         //专辑名称
                	    $album_name = isset($e->find('div.playlist-col h6',0)->title)?$e->find('div.playlist-col h6',0)->title:'';
                	    $album_name = str_replace('}', '', $album_name);
                	    //过滤关键字
                	    if(!empty($this->key_word)){
                	        $arr_keyword = explode(',', $this->key_word);
                	        $is_key_found = FALSE;
                	        foreach ($arr_keyword as $value) {
                	            if(strpos($album_name, $value)!==FALSE){
                	                $is_key_found = TRUE;
                	                break;
                	            }
                	        }
                	        if(!$is_key_found){
                	        	continue;
                	        }
                	    }
                	    
                	    
//                 	    echo $album_name;
//                 	    echo "\n";
                	    
                	    
                	    //专辑地址
                	    $detail_url = isset($e->find('div.playlist-info a',0)->href)?$e->find('div.playlist-info a',0)->href:'';
                	    //该专辑下的视频播放量
                	    $detail_play_count = 0;
                	    $detail_play_count_str = isset($e->find('div.vinfo li',1)->innertext)?$e->find('div.vinfo li',1)->innertext:'';
                	    if(preg_match('/\d+/', $detail_play_count_str,$match)){
                	        $detail_play_count = $match[0];
                	        $detail_play_count = str_replace(',', '', $detail_play_count);
                	        $detail_play_count = str_replace('.', '', $detail_play_count);
                	        $detail_play_count = str_replace('万', '0000', $detail_play_count);
                	    }
                	    //该专辑下的视频总数量
                	    $video_str_count = isset($e->find('div.vinfo li',0)->innertext)?$e->find('div.vinfo li',0)->innertext:'';
                	    $video_all_count = 0;
                	    if(preg_match('/\d+/', $video_str_count,$match)){
                	         $video_all_count = $match[0];
                	         $video_all_count = str_replace(',', '', $video_all_count);
                	    }
                	    if(empty($video_all_count)){
                	        echo "{$album_name}专辑下没有视频";
                	        continue;
                	    }
                	    //每个专辑下每页显示的视频数量
                	    $video_per_count = 20;
                	    //视频的页码
                	    $video_page = floor($video_all_count / $video_per_count);
                	    if($video_all_count % $video_per_count <> 0){
                	        $video_page++;
                	    }
                	    if(empty($album_name) && empty($detail_url)){
                	        continue;
                	    }
                	    $video_detail_html = file_get_html($detail_url);
                	    if(!is_object($video_detail_html)){
                	        continue;
                	    }
                	    //真正的专辑列表页
                	    $album_url = isset($video_detail_html->find('div.nBox div.extend a',0)->href)?$video_detail_html->find('div.nBox div.extend a',0)->href:'';
                	    if(empty($album_url)){
                	        continue;
                	    }
                	    $arr_video = array();
                	    //视频页列表，有分页的
                	    for ($album_big_page=1;$album_big_page<=$video_page;$album_big_page++){
                	        $dir = pathinfo($album_url,PATHINFO_DIRNAME);
                	        $filename = pathinfo($album_url,PATHINFO_FILENAME);
                	        $ext = pathinfo($album_url,PATHINFO_EXTENSION);
                	        $album_video_page_url = $dir .'/' .$filename ."_ascending_1_page_{$album_big_page}" .'.'.$ext;
                	        //2016-02-24优酷将这类页面做了重定向处理-------xiongjianbang
                	        $album_video_page_url = get_redirect_url($album_video_page_url);
                    	    $video_album_html = file_get_html($album_video_page_url);
                            if(!is_object($video_album_html)){
                    	        continue;
                    	    }
                    	    //专辑名称
                    	    $arr_album['sName'] = $album_name;
                    	    //专辑地址
                    	    $arr_album['sUrl'] = $album_url;
                    	    //该专辑下的视频总数量
                    	    $arr_album['sCount'] = $video_all_count;
                    	    $arr_album['sPlayCount'] = $detail_play_count;
                    	    //专辑图片
                    	    $arr_album['sImg'] = isset($e->find('div.v-thumb img',0)->src)?$e->find('div.v-thumb img',0)->src:'';
                    	    //视频列表，单页爬虫
                    	    foreach($video_album_html->find('ul.v') as $k=>$v){
                    	        $arr_tmp_video = array();
                    	        $arr_tmp_video['number'] = $k*$album_big_page+1;
                    	        //视频标题
                    	        $arr_tmp_video['vTitle'] = isset($v->find('li.v_title a',0)->title)?$v->find('li.v_title a',0)->title:'';
                    	        $arr_tmp_video['vRealPalyUrl'] = '';
                    	        //需要解析的播放地址
                    	        $playurl = isset($v->find('li.v_title a',0)->href)?$v->find('li.v_title a',0)->href:'';
                    	        if(!empty( $playurl)){
                    	            $arr_tmp_video['vRealPalyUrl']  = substr($playurl ,0,strrpos($playurl ,'?'));
                    	            $playurl = str_replace('=', '', $playurl);
                    	        }
                    	        //视频截图
                    	        $arr_tmp_video['vImgUrl'] = isset($v->find('li.v_thumb img',0)->src)?$v->find('li.v_thumb img',0)->src:'';
                    	        //播放时长
                    	        $arr_tmp_video['vTime'] = isset($v->find('li.v_time span.num',0)->innertext)?$v->find('li.v_time span.num',0)->innertext:'';
                    	        //播放次数
                    	        $arr_tmp_video['vPlayCount'] = isset($v->find('li.v_stat span.num',0)->innertext)?$v->find('li.v_stat span.num',0)->innertext:'';
                    	        $arr_tmp_video['vPlayCount'] = str_replace('万', '0000', $arr_tmp_video['vPlayCount']);
                    	        $arr_tmp_video['vPlayCount'] = str_replace(',', '', $arr_tmp_video['vPlayCount']);
                    	        //作者头像地址
                    	        $arr_tmp_video['vAuthorImgUrl'] = $arr_return['aIcon'];
                    	        //作者名称
                    	        $arr_tmp_video['vAuthorName'] = $arr_return['aName'];
                    	        //视频上传的时间
                    	        $arr_tmp_video['createDate'] = '';
                    	        if(preg_match('/id_(.*?)\.html/', $playurl,$match)){
                    	            $video_id = $match[1];
                    	            $arr_tmp_video['createDate'] =   isset($arr_video_list[$video_id]['c_time']) ? $arr_video_list[$video_id]['c_time']:date('Y-m-d H:i:s');
                    	            $arr_video[$video_id] = $arr_tmp_video;
                    	        }
                    	    }
                    }
                    $arr_album['listVideos'] = $arr_video;
//                     print_r($arr_album);
                    $arr_return['listSeries'][] = $arr_album;
                 }
            }
        	$url = $this->url . "/order_1_view_1_page_{$page}";
        	$album_html = file_get_html($url);
             if(!is_object($album_html)){
                continue;
            }
        }
        unset($arr_video_list);
//         print_r($arr_return);
        return $arr_return;
    }
    
    /**
     * @name:get_youku_simple_page
     * @description: 优酷单页的抓取
     * @author: Xiong Jianbang
     * @create: 2015-12-7 下午3:47:18
     **/
    public function get_youku_simple_page(){
        $video_simple_html = file_get_html($this->url);
        if(!is_object($video_simple_html)){
            continue;
        }
        //需要解析的播放地址
         $playurl = $this->url;
         if(strrpos($playurl ,'?')!==FALSE){
             $playurl  = substr($playurl ,0,strrpos($playurl ,'?'));
         }
         $arr_tmp_video['vRealPalyUrl'] = str_replace('=', '',  $playurl);
         $arr_tmp_video['vRealPalyUrl'] = $playurl;
         //专辑标题
         $arr_tmp_video['sName'] = isset($video_simple_html->find('a.singer',0)->title)?$video_simple_html->find('a.singer',0)->title:'';
         $arr_tmp_video['sUrl'] = isset($video_simple_html->find('a.singer',0)->href)?$video_simple_html->find('a.singer',0)->href:'';
         //视频标题
         $arr_tmp_video['vTitle'] = isset($video_simple_html->find('h1.title',0)->innertext)?$video_simple_html->find('h1.title',0)->innertext:'';
         $arr_tmp_video['vTitle'] = strip_tags($arr_tmp_video['vTitle']);
         //作者头像地址
         $arr_tmp_video['vAuthorImgUrl'] = isset($video_simple_html->find('div#subimg img',0)->src)?$video_simple_html->find('div#subimg img',0)->src:'';
         //作者名称
         $arr_tmp_video['vAuthorName'] = isset($video_simple_html->find('div#subname a',0)->innertext)?$video_simple_html->find('div#subname a',0)->innertext:'';
         $arr_tmp_video['createDate'] = date('Y-m-d H:i:s');
         //作者链接
         $arr_tmp_video['vAuthorUrl'] =  isset($video_simple_html->find('div#subname a',0)->href)?$video_simple_html->find('div#subname a',0)->href:'';
         //播放数
         $arr_tmp_video['vPlayCount'] = !empty($video_simple_html->find('em.num',0)->innertext)?$video_simple_html->find('em.num',0)->innertext:rand(10,2000);
         $arr_tmp_video['vPlayCount'] = str_replace('万', '0000', $arr_tmp_video['vPlayCount']);
         $arr_tmp_video['vPlayCount'] = str_replace(',', '', $arr_tmp_video['vPlayCount']);
         return $arr_tmp_video;
    }
    
    /**
     * @name:get_youku_simple_page_with_screenshot
     * @description: 获取带有视频截图的视频数组
     * @return: Array
     * @author: Xiong Jianbang
     * @create: 2015-12-7 下午6:38:40
     **/
    public function  get_youku_simple_page_with_screenshot(){
    	$arr_info = pathinfo($this->url);
    	$filename = $arr_info['filename'];
    	$pos = strpos($filename, '?');
    	if($pos==FALSE){
    	    $vid =  str_replace('id_','',basename($filename,'.html'));
    	}else{
    	    $vid =  str_replace('id_','',basename(strstr($filename, '?',TRUE),'.html'));
    	}
    	$vid = str_replace('=', '', $vid);
    	$arr_tmp_video = $this->get_youku_simple_page();
    	if(!empty($arr_tmp_video['vAuthorUrl'])){
            $youku_video_url = $arr_tmp_video['vAuthorUrl'] .'/videos';
        	$arr_video_list = $this->get_youku_videos_list_data($youku_video_url);
        	//获取视频截图
        	$arr_tmp_video['vImgUrl'] = isset($arr_video_list[$vid]['img'])?$arr_video_list[$vid]['img']:'';
    	}
    	return $arr_tmp_video;
    }

    /**
     * @name:handle_video_detail
     * @description: 处理视频数据
     * @return: array
     * @author: Xiong Jianbang
     * @create: 2015-11-27 上午10:57:19
     **/
    public function handle_video_detail($v){
        $href = isset($v->find('div.v-link a',0)->href)?$v->find('div.v-link a',0)->href:'';
        $href = substr($href ,0,strrpos($href ,'?'));
        if(empty($href)){
            return false;
        }
        $title =  isset($v->find('div.v-link a',0)->title)?$v->find('div.v-link a',0)->title:'';
        $c_time =  isset($v->c_time)?$v->c_time:'';
        $arr = array('href'=>$href,'title'=>$title,'c_time'=>$c_time);
        return $arr;
    }
    
    /**
     * @name:get_gao7_vedio
     * @description: 分析gao7网站的真实视频地址
     * @return: 视频源json文件
     * @author: Xiong Jianbang
     * @create: 2015-5-22 上午11:20:27
     **/
    public function get_gao7_vedio(){
        $html = $this->curl_get($this->url);
        if(preg_match('/embed\/(.*?)\'/', $html,$match)){
            $vid = isset($match[1])?$match[1]:0;
            if(!empty($vid)){
                return json_encode(array('msg'=>$this->get_youku_video_json($vid),'status'=>200,'type'=>'youku','vid'=>$vid));
            }
        }
    }
    
    /**
     * @name:get_mofang_vedio
     * @description: 分析mofang网站的真实视频地址
     * @return: 视频源json文件
     * @author: Xiong Jianbang
     * @create: 2015-5-22 上午11:20:27
     **/
    public function get_mofang_vedio(){
        $html = $this->curl_get($this->url);
        if(preg_match('/sid\/(.*?)\/v\.swf/', $html,$match)){
            $vid = isset($match[1])?$match[1]:0;
            if(!empty($vid)){
                return json_encode(array('msg'=>$this->get_youku_video_json($vid),'status'=>200,'type'=>'youku','vid'=>$vid));
            }
        }
        return FALSE;
    }
    
    /**
     * @name:get_google_video
     * @description: 抓取google app
     * @return: 视频源json文件
     * @author: Xiong Jianbang
     * @create: 2015-12-18 下午5:02:32
     **/
    public function get_google_video(){
        $code = check_url_exists($this->url);
        if(!$code){
            return FALSE;
        }
        $html = $this->curl_get($this->url);
//         file_put_contents('/data/web/admin.kuaiyouxi.com/uploads/google.txt', $html);
        if(preg_match('/www\.youtube\.com\/embed\/(.*?)\?/', $html,$match)){
            $vid = isset($match[1])?$match[1]:0;
            if(!empty($vid)){
                return  json_encode(array('msg'=>$this->get_youtube_video_json($vid),'status'=>200,'type'=>'youtube','vid'=>$vid));
            }
        }else{
            return  json_encode(array('msg'=>'','status'=>400,'type'=>'youtube','vid'=>$vid));
        }
        return FALSE;
    }
    
    
    /**
     * @name:get_yxzoo_vedio
     * @description: 分析yxzoo网站的真实视频地址
     * @return: 视频源json文件
     * @author: Xiong Jianbang
     * @create: 2015-5-22 上午11:20:27
     **/
    public function get_yxzoo_vedio(){
        $html = $this->curl_get($this->url);
        if(preg_match('/sid\/(.*?)==\/v\.swf/', $html,$match)){
            $vid = isset($match[1])?$match[1]:0;
            if(!empty($vid)){
                return json_encode(array('msg'=>$this->get_youku_video_json($vid),'status'=>200,'type'=>'youku','vid'=>$vid));
            }
        }
        elseif(preg_match('/sid\/(.*?)\/v\.swf/', $html,$match)){
            $vid = isset($match[1])?$match[1]:0;
            if(!empty($vid)){
                return json_encode(array('msg'=>$this->get_youku_video_json($vid),'status'=>200,'type'=>'youku','vid'=>$vid));
            }
        }
        return FALSE;
    }
    
    /**
     * @name:get_40407_vedio
     * @description: 分析40407网站的真实视频地址
     * @return: 视频源json文件
     * @author: Xiong Jianbang
     * @create: 2015-5-22 上午11:20:27
     **/
    public function get_40407_vedio(){
        $html = $this->curl_get($this->url);
        //优酷
        if(preg_match('/player\.php\/sid\/(.*?)==\/v\.swf/', $html,$match)){
            $vid = isset($match[1])?$match[1]:0;
            if(!empty($vid)){
                return json_encode(array('msg'=>$this->get_youku_video_json($vid),'status'=>200,'type'=>'youku','vid'=>$vid));
            }
        }
        elseif(preg_match('/sid\/(.*?)\/v\.swf/', $html,$match)){
            $vid = isset($match[1])?$match[1]:0;
            if(!empty($vid)){
                return json_encode(array('msg'=>$this->get_youku_video_json($vid),'status'=>200,'type'=>'youku','vid'=>$vid));
            }
        }
        //多玩
        elseif(preg_match('/vu=(.*?)&/', $html,$match)){
            $vu = isset($match[1])?$match[1]:'';
            return $this->get_duowan_video_by_vu($vu);
        }
        ///土豆
        elseif(preg_match('/v\/(.*?)\/&/', $html,$match)){
            $page_id = isset($match[1])?$match[1]:0;
            if(!empty($page_id)){
                $tudou_url = "http://www.tudou.com/programs/view/$page_id";
                $html = $this->curl_get($tudou_url);
                if(preg_match('/iid: (\d{1,})/', $html,$match)){
                    $vid = isset($match[1])?$match[1]:0;
                    return json_encode(array('msg'=>$this->get_tudou_video_json($vid),'status'=>200,'type'=>'tudou','vid'=>$vid));
                }
            }
        }
    }
    
    /**
     * @name:get_tgbus_vedio
     * @description: 分析tgbus网站的真实视频地址
     * @return: 视频源json文件
     * @author: Xiong Jianbang
     * @create: 2015-5-22 上午11:20:27
     **/
    public function get_tgbus_vedio(){
        $html = $this->curl_get($this->url);
        if(preg_match('/player\.php\/sid\/(.*?)\/v\.swf"/', $html,$match)){
            $vid = isset($match[1])?$match[1]:0;
            if(!empty($vid)){
                 return json_encode(array('msg'=>$this->get_youku_video_json($vid),'status'=>200,'type'=>'youku','vid'=>$vid));
            }
        }
    }
    
    /**
     * @name:get_4399_vedio
     * @description: 分析4399pk网站的真实视频地址
     * @return: 视频源json文件
     * @author: Xiong Jianbang
     * @create: 2015-5-22 上午11:20:27
     **/
    public function get_4399_vedio(){
        $html = $this->curl_get($this->url);
        if(preg_match('/flvid = (\d*?);/', $html,$match)){
            $vid = isset($match[1])?$match[1]:0;
            if(!empty($vid)){
                return json_encode(array('msg'=>$this->get_4399_video_url($vid),'status'=>200,'type'=>'4399','vid'=>$vid));
            }
        }
    }
    
    /**
     * @name:get_4399pk_vedio
     * @description: 分析4399pk网站的真实视频地址
     * @return: 视频源json文件
     * @author: Xiong Jianbang
     * @create: 2015-5-22 上午11:20:27
     **/
    public function get_4399pk_vedio(){
        $html = $this->curl_get($this->url);
         if(preg_match('/F_ID = "(\d*?)"/', $html,$match)){
            $vid = isset($match[1])?$match[1]:0;
            if(!empty($vid)){
                return json_encode(array('msg'=>$this->get_4399_video_url($vid),'status'=>200,'type'=>'4399','vid'=>$vid));
            }
        }
    }
    
    /**
     * @name:get_66u_vedio
     * @description: 分析66u网站的真实视频地址
     * @return: 视频源json文件
     * @author: Xiong Jianbang
     * @create: 2015-5-22 上午11:20:27
     **/
    public function get_66u_vedio(){
        $html = $this->curl_get($this->url);
        //优酷
       if(preg_match('/player\.youku\.com\/embed\/(.*?)"/', $html,$match)){
            $vid = isset($match[1])?$match[1]:0;
            if(!empty($vid)){
                return json_encode(array('msg'=>$this->get_youku_video_json($vid),'status'=>200,'type'=>'youku','vid'=>$vid));
            }
        }
    }
    
    /**
     * @name:get_tudou_vedio
     * @description: 获取土豆本站的视频地址
     * @return: 视频的json格式
     * @author: Xiong Jianbang
     * @create: 2015-5-22 下午3:14:32
     **/
    public function get_tudou_vedio(){
        $html = $this->curl_get($this->url);
        if(preg_match('/iid: (\d{1,})/', $html,$match)){
            $vid = isset($match[1])?$match[1]:0;
            return json_encode(array('msg'=>$this->get_tudou_video_json($vid),'status'=>200,'type'=>'tudou','vid'=>$vid));
        }
    }
    
    /**
     * @name:get_shouyou_vedio
     * @description: 分析手游游戏网站的真实视频地址
     * @return: 视频源json文件
     * @author: Xiong Jianbang
     * @create: 2015-5-22 上午11:20:27
     **/
    public function get_shouyou_vedio(){
        $html = $this->curl_get($this->url);
        if(preg_match('/17173cdn\.com\/player_f2\/(.*?)\.swf/', $html,$match)){
            $vid = isset($match[1])?$match[1]:0;
            if(!empty($vid)){
                $url_new = "http://v.17173.com/v_1_11113/{$vid}.html";
                $html = $this->curl_get($url_new);
                return $this->get_17173_video_json($html);
            }
        }
        //优酷
        elseif(preg_match('/sid\/(.*?)\/v\.swf/', $html,$match)){
            $vid = isset($match[1])?$match[1]:0;
            if(!empty($vid)){
                return json_encode(array('msg'=>$this->get_youku_video_json($vid),'status'=>200,'type'=>'youku','vid'=>$vid));
            }
        }
    }
    
    /**
     * @name:get_5253_vedio
     * @description: 分析5253游戏网站的真实视频地址
     * @return: 视频源json文件
     * @author: Xiong Jianbang
     * @create: 2015-5-22 上午10:49:50
     **/
    public function get_5253_vedio(){
        $html = $this->curl_get($this->url);
        //多玩乐视
        if(preg_match('/vid=(\d*?)&/', $html,$match)){
            $vid = isset($match[1])?$match[1]:0;
            if(!empty($vid)){
                return json_encode(array('msg'=>$this->get_duowan_video_json($vid),'status'=>200,'type'=>'duowan_letv','vid'=>$vid));
            }
        }
        elseif(preg_match('/vu=(.*?)&/', $html,$match)){
            $vu = isset($match[1])?$match[1]:'';
            return $this->get_duowan_video_by_vu($vu);
        }
        //优酷
        elseif(preg_match('/player\.youku\.com\/player\.php\/sid\/(.*?)\/partnerid/', $html,$match)){
            $vid = isset($match[1])?$match[1]:0;
            if(!empty($vid)){
                return json_encode(array('msg'=>$this->get_youku_video_json($vid),'status'=>200,'type'=>'youku','vid'=>$vid));
            }
        }
        elseif(preg_match('/sid\/(.*?)\/v\.swf/', $html,$match)){
            $vid = isset($match[1])?$match[1]:0;
            if(!empty($vid)){
                return json_encode(array('msg'=>$this->get_youku_video_json($vid),'status'=>200,'type'=>'youku','vid'=>$vid));
            }
        }
        elseif(preg_match('/sid\/(.*?)\.html\/v\.swf/', $html,$match)){
            $vid = isset($match[1])?$match[1]:0;
            if(!empty($vid)){
                return json_encode(array('msg'=>$this->get_youku_video_json($vid),'status'=>200,'type'=>'youku','vid'=>$vid));
            }
        }
        //爱拍
        elseif(preg_match('/www\.aipai\.com\/c28\/(.*?)\/playerOut\.swf/', $html,$match)){
            $vid = isset($match[1])?$match[1]:0;
            if(!empty($vid)){
                $url_new = "http://www.aipai.com/c24/{$vid}.html";
                $file = fopen($url_new, "rb");
                //只读2字节  如果为(16进制)1f 8b (10进制)31 139则开启了gzip ;
                $bin = fread($file, 2);
                fclose($file);
                $str_info = @unpack("C2chars", $bin);
                $is_gzip = intval($str_info['chars1'].$str_info['chars2']);
                $html = $this->curl_get($url_new);
                return $this->get_aipai_video_json($html,$is_gzip);
            }
        }
    }
    
    /**
     * @name:get_17173_vedio
     * @description: 分析17173本站游戏网站的真实视频地址
     * @return: 视频源json文件
     * @author: Xiong Jianbang
     * @create: 2015-5-20 下午6:08:35
     **/
    public function get_17173_vedio(){
        $html = $this->curl_get($this->url);
        return $this->get_17173_video_json($html);
    }
    
    

    
    /**
     * @name:get_178_vedio
     * @description: 分析178游戏网站的真实视频地址
     * @return: 视频源json文件
     * @author: Xiong Jianbang
     * @create: 2015-5-20 下午4:34:45
     **/
    public function get_178_vedio(){
        $html = $this->curl_get($this->url);
        //优酷视频
        if(preg_match('/player\.youku\.com\/embed\/(.*?)"/', $html,$match)){
            $vid = isset($match[1])?$match[1]:0;
            if(!empty($vid)){
                return json_encode(array('msg'=>$this->get_youku_video_json($vid),'status'=>200,'type'=>'youku','vid'=>$vid));
            }
        }
        if(preg_match('/\/sid\/(.*?)\/v\.swf/', $html,$match)){
            $vid = isset($match[1])?$match[1]:0;
            if(!empty($vid)){
                return json_encode(array('msg'=>$this->get_youku_video_json($vid),'status'=>200,'type'=>'youku','vid'=>$vid));
            }
        }
        //乐视云
        elseif(preg_match('/uu=(.*?)&amp;vu=(.*?)&/', $html,$match)){
            $uu = isset($match[1])?$match[1]:'';
            $vu = isset($match[2])?$match[2]:'';
            if(!empty($uu) && !empty($vu)){
                return json_encode(array('msg'=>$this->get_leshiyun_video_json($uu,$vu),'status'=>200,'type'=>'leshiyun','vid'=>$vu));
            }
        }
       
    }
    
    /**
     * @name:get_youshixiu_video
     * @description: 分析youshixiu网站的真实视频地址
     * @return:视频文件
     * @author: Xiong Jianbang
     * @create: 2015-9-19 下午2:29:59
     **/
    public function get_youshixiu_video(){
        $vid= 0;
        //客户端直接给的是视频播放地址：类似于：http://source.youshixiu.com/8eff651730f05eb312a2c8a314a15206
        if(preg_match('/source\.youshixiu\.com\/(.*?)$/', $this->url,$match)){
            $vid = isset($match[1])?$match[1]:0;
        }
        return json_encode(array('msg'=>$this->url,'status'=>200,'type'=>'youshixiu','vid'=>$vid));
    }
    
    /**
     * @name:get_everyplay_video
     * @description: 分析everyplay网站的真实视频地址
     * @return:视频源json文件
     * @author: Xiong Jianbang
     * @create: 2015-9-19 下午2:29:59
     **/
    public function  get_everyplay_video(){
        $vid= 0;
        if(preg_match('/\d+/', $this->url,$match)){
            $vid = isset($match[0])?$match[0]:0;
            $mp4_url = "https://everyplay.com/api/videos/{$vid}";
            return json_encode(array('msg'=>$mp4_url,'status'=>200,'type'=>'everyplay','vid'=>$vid));
        }
        return FALSE;
    }
    
    
    /**
     * @name:get_kamcord_m3u8_video
     * @description: 分析kamcord网站的真实视频地址
     * @return: m3u8地址
     * @author: Xiong Jianbang
     * @create: 2015-7-22 下午3:27:03
     **/
    public function get_kamcord_m3u8_video(){
        $vid= 0;
        if(preg_match('/\/v\/(.*?)$/', $this->url,$match)){
            $vid = isset($match[1])?$match[1]:0;
        }
        $html = $this->curl_get($this->url);
        if(preg_match('/data-video-url="(.*?)"/', $html,$match)){
            $mp4 = isset($match[1])?$match[1]:0;
            if(!empty($mp4)){
                return json_encode(array('msg'=>$mp4,'status'=>200,'type'=>'kamcord','vid'=>$vid));
            }
        }
    }
    
    
    /**
     * @name:get_kamcord_video
     * @description: 分析kamcord网站的真实视频地址
     * @return: mp4地址
     * @author: Xiong Jianbang
     * @create: 2015-7-22 下午3:27:03
     **/
    public function get_kamcord_video(){
        $vid= 0;
        if(preg_match('/\/v\/(.*?)$/', $this->url,$match)){
            $vid = isset($match[1])?$match[1]:0;
        }
        $html = $this->curl_get($this->url);
        if(preg_match('/name\="twitter:player:stream" content="(.*?)"/', $html,$match)){
            $mp4 = isset($match[1])?$match[1]:0;
            if(!empty($mp4)){
                return json_encode(array('msg'=>$mp4,'status'=>200,'type'=>'kamcord','vid'=>$vid));
            }
        }
    }
    
    /**
     * @name:get_aipai_video
     * @description: 分析爱拍游戏网站的真实视频地址
     * @return: 视频源json文件
     * @author: Xiong Jianbang
     * @create: 2015-5-19 下午2:08:42
     **/
    public function get_aipai_video(){
        $file = fopen($this->url, "rb");
        //只读2字节  如果为(16进制)1f 8b (10进制)31 139则开启了gzip ;
        $bin = fread($file, 2);
        fclose($file);
        $str_info = @unpack("C2chars", $bin);
        $is_gzip = intval($str_info['chars1'].$str_info['chars2']);
        $html = $this->curl_get($this->url);
        return $this->get_aipai_video_json($html,$is_gzip);
    }
    
    /**
     * @name:get_huya_video
     * @description: 分析虎牙站的视频源json文件
     * @return: 视频源json文件
     * @author: Xiong Jianbang
     * @create: 2015-7-28 上午11:42:23
     **/
    public function get_huya_video(){
        $html = $this->curl_get($this->url);
        if(preg_match('/\&vid=(\d*?)\&/', $html,$match) ){
            $vid = isset($match[1])?$match[1]:'';
            if(!empty($vid)){
                return json_encode(array('msg'=>$this->get_duowan_video_json($vid),'status'=>200,'type'=>'duowan_letv','vid'=>$vid));
            }
        }
    }
    
    
    /**
     * @name:get_duowan_vedio
     * @description: 分析多玩lol站的视频源json文件
     * 该网址的视频分乐视云和搜狐视频
     * @return: 视频源json文件
     * @author: Xiong Jianbang
     * @create: 2015-4-27 下午6:03:39
     **/
    public function get_duowan_vedio(){
        $html = $this->curl_get($this->url);
        //页面存在乐视云视频vid的情况
//         if(preg_match('/\"vid\":\"(\d*)\"/', $html,$match)){
//             $vid = isset($match[1])?$match[1]:0;
//             if(!empty($vid)){
//                 return json_encode(array('msg'=>$this->get_duowan_video_json($vid),'status'=>200,'type'=>'duowan'));
//             }else{
//                 return json_encode(array('msg'=>'vid is empty','status'=>400));
//             }
//         }
        //根据乐视云视频letvVideoUnique值到另一URL获取vid
        $vu = '';
        if(preg_match('/vu=(.*?)&/',$html,$match)){
            $vu = isset($match[1])?$match[1]:'';
            if(!empty($vu)){
                return $this->get_duowan_video_by_vu($vu);
            }
        }
        if(preg_match('/\"letvVideoUnique\":\"(.*?)\"/', $html,$match)){
            $vu = isset($match[1])?$match[1]:'';
            if(!empty($vu)){
                return $this->get_duowan_video_by_vu($vu);
            }
        }
        if(preg_match('/\"vid\"\:\"(\d*?)\"/', $html,$match) ){
            $vid = isset($match[1])?$match[1]:'';
            if(!empty($vid)){
                return json_encode(array('msg'=>$this->get_duowan_video_json($vid),'status'=>200,'type'=>'duowan_letv','vid'=>$vid));
            }
        }
        if(preg_match('/vid=(\d*?)&/', $html,$match) ){
            $vid = isset($match[1])?$match[1]:'';
            if(!empty($vid)){
                return json_encode(array('msg'=>$this->get_duowan_video_json($vid),'status'=>200,'type'=>'duowan_letv','vid'=>$vid));
            }
        }
        //嵌入的是搜狐视频
        elseif(preg_match('/share.vrs.sohu.com\/my\/v\.swf\&amp;id=(\d*?)&amp;skinNum/', $html,$match)){
            $vid =  isset($match[1])?$match[1]:'';
            if(!empty($vid)){
                return json_encode(array('msg'=>$this->get_sohu_video_json($vid),'status'=>200,'type'=>'sohu','vid'=>$vid));
            }
        }
        elseif(preg_match('/share.vrs.sohu.com\/my\/v\.swf\&amp;topBar=1\&amp;id=(\d*?)&/', $html,$match)){
            $vid =  isset($match[1])?$match[1]:'';
            if(!empty($vid)){
                return json_encode(array('msg'=>$this->get_sohu_video_json($vid),'status'=>200,'type'=>'sohu','vid'=>$vid));
            }
        }
        elseif(preg_match('/share\.vrs\.sohu\.com\/my\/v\.swf&id=(\d*?)&/', $html,$match)){
            $vid =  isset($match[1])?$match[1]:'';
            if(!empty($vid)){
                return json_encode(array('msg'=>$this->get_sohu_video_json($vid),'status'=>200,'type'=>'sohu','vid'=>$vid));
            }
        }
        elseif(preg_match('/share\.vrs\.sohu\.com\/my\/v\.swf&amp;id=(\d*?)&/', $html,$match)){
            $vid =  isset($match[1])?$match[1]:'';
            if(!empty($vid)){
                return json_encode(array('msg'=>$this->get_sohu_video_json($vid),'status'=>200,'type'=>'sohu','vid'=>$vid));
            }
        }
        //嵌入的是优酷视频
        elseif(preg_match('/player\.youku\.com\/player\.php\/sid\/(.*?)\/v\.swf/', $html,$match)){
            $vid = isset($match[1])?$match[1]:0;
            if(!empty($vid)){
                return json_encode(array('msg'=>$this->get_youku_video_json($vid),'status'=>200,'type'=>'youku','vid'=>$vid));
            }
        }
        elseif(preg_match('/sid\/(.*?)\/v\.swf/', $html,$match)){
            $vid = isset($match[1])?$match[1]:0;
            if(!empty($vid)){
                return json_encode(array('msg'=>$this->get_youku_video_json($vid),'status'=>200,'type'=>'youku','vid'=>$vid));
            }
        }
        elseif(preg_match('/player\.php\/sid\/(.*?)\.swf/', $html,$match)){
            $vid = isset($match[1])?$match[1]:0;
            if(!empty($vid)){
                return json_encode(array('msg'=>$this->get_youku_video_json($vid),'status'=>200,'type'=>'youku','vid'=>$vid));
            }
        }
        elseif(preg_match('/==\/sid\/(.*?)\/v\.swf/', $html,$match)){
            $vid = isset($match[1])?$match[1]:0;
            if(!empty($vid)){
                return json_encode(array('msg'=>$this->get_youku_video_json($vid),'status'=>200,'type'=>'youku','vid'=>$vid));
            }
        }
        elseif(preg_match('/v\/swf\/qplayer\.swf\?VideoIDS=(.*?)&/', $html,$match)){
            $vid = isset($match[1])?$match[1]:0;
            if(!empty($vid)){
                return json_encode(array('msg'=>$this->get_youku_video_json($vid),'status'=>200,'type'=>'youku','vid'=>$vid));
            }
        }
        elseif(preg_match('/v\/swf\/loader\.swf\?VideoIDS=(.*?)&/', $html,$match)){
            $vid = isset($match[1])?$match[1]:0;
            if(!empty($vid)){
                return json_encode(array('msg'=>$this->get_youku_video_json($vid),'status'=>200,'type'=>'youku','vid'=>$vid));
            }
        }
        //嵌入的是土豆视频
        elseif(preg_match('/www\.tudou\.com\/v\/(.*?)\//', $html,$match)){
             $page_id = isset($match[1])?$match[1]:0;
             if(!empty($page_id)){
                 $tudou_url = "http://www.tudou.com/programs/view/$page_id";
                 $html = $this->curl_get($tudou_url);
                 if(preg_match('/iid: (\d{1,})/', $html,$match)){
                    $vid = isset($match[1])?$match[1]:0;
                    return json_encode(array('msg'=>$this->get_tudou_video_json($vid),'status'=>200,'type'=>'tudou','vid'=>$vid));
                 }
             }
        }
        //嵌入的是酷六视频
        elseif(preg_match('/refer\/(.*?\.\.)\/v\.swf/', $html,$match)){
            $vid = isset($match[1])?$match[1]:0;
            if(!empty($vid)){
                return json_encode(array('msg'=>$this->get_ku6_video_json($vid),'status'=>200,'type'=>'ku6','vid'=>$vid));
            }
        }
    }
    
    
    
    /**
     * @name:handle_tudou_video
     * @description: 
     * 土豆视频做了一系列处理，我们需要遵守下面的算法，获取真实的视频地址
     * 1，先拿到页面的ID值，类似如：bhDZE0BdPHk
     * 2，再跳到对应的土豆页面，获取iid值，获取数字，类似于22053269
     * 3，访问http://www.tudou.com/outplay/goto/getItemSegs.action?iid=22053269 返回json字符串
     * 4，获取其中的k值，再访问http://v2.tudou.com/f?id={k}
     * 5，获取XML格式的土豆视频真实地址
     * @param: $vid=视频ID号
     * @return: Array
     * @author: Xiong Jianbang
     * @create: 2015-4-28 下午5:04:24
     **/
    public function handle_tudou_video($vid=0){
        $json = $this->curl_get("http://www.tudou.com/outplay/goto/getItemSegs.action?iid=$vid");
        if(empty($json)){
            return FALSE;
        }
        $arr = json_decode($json,TRUE);
        foreach ($arr as $key=>$value) { //$key = 3,2,5分别表示高清，标清，超清
            foreach ($value as $k=>$v) {
                $key_hash = $v['k']."<br>";
                $xml = $this->curl_get("http://v2.tudou.com/f?id=$key_hash");
                if(preg_match('/<f[^>]*?>(.*?)<\/f>/', $xml,$match)){
                	$arr[$key][$k]['real_flv_url'] = isset($match[1])?$match[1]:'';
                }
            }
        }
        return $arr;
    }
    
    
    /**
     * @name:handle_sohu_video
     * @description: 
     * 搜狐视频做了地址伪装，我们需要遵守下面的算法，获取真实的视频地址
     *  
     *  1，打开上面url(http://my.tv.sohu.com/videinfo.jhtml?m=viewtv&vid=xxxxx)之后是个json格式，但还无法找到下载地址
        http://allot/?prot=prot&file=clipsURL[i]&new=su[i]
         2， 因为视频有多个切片所以写成了 [i]  这种形式,在json中找到上面的字段 allot、 prot、 clipsURL、su
           例如：
           http://220.181.61.213/?prot=2&file=220.181.89.24/148188491b1c61e718f43082e880f898486a7f6c4ef3f1fe9e476443a3f942d684b3b9c5045314bf7aba2ca44012fefc.mp4
           &new=/67/66/Az9cxRoLnpe2McInJOmN17.mp4
          3， 打开后是这样子：
            http://101.226.200.16/sohu/6/|324|114.80.133.7|ywAYHUJiiFObDbpaJEIE9iCgYQ5iVim1PKiuhA..|1|0
         4，我们需要处理一些字段下载地址的组合为：
          http://101.226.200.16/sohu/6/+su[i]+?key= ywAYHUJiiFObDbpaJEIE9iCgYQ5iVim1PKiuhA..
          5， 主要上面的下载地址还用到了之前json页面上的 su[i]   另外添加上了?key=   这几个字符， 最后组合成下载地址，如：
         http://101.226.200.16/sohu/6//67/66/Az9cxRoLnpe2McInJOmN17.mp4?key=ywAYHUJiiFObDbpaJEIE9iCgYQ5iVim1PKiuhA..
     * @param: $vid=视频ID号
     * @return: Array
     * @author: Xiong Jianbang
     * @create: 2015-4-28 上午11:19:32
     **/
    public  function handle_sohu_video($vid=0){
        $json = $this->curl_get("http://my.tv.sohu.com/videinfo.jhtml?m=viewtv&vid=$vid");
        if(!empty($json)){
            $arr = json_decode($json,TRUE);
            $allot = trim($arr['allot']);
            $prot = trim($arr['prot']);
            $arr_clipsURL = $arr['data']['clipsURL'];
            $arr_su = $arr['data']['su'];
            if(empty($arr_clipsURL)){
                 return FALSE;
            }
            foreach ($arr_clipsURL as $key=>$value) {
                $join_url = "http://$allot/?prot=$prot&file=$value&new=$arr_su[$key]";
                $fetch_str = $this->curl_get($join_url);
                $new_url = preg_replace('/\/\|\d*?\|.*?\|/', "{$arr_su[$key]}?key=", $fetch_str);
                $new_url = preg_replace('/\|\d{1,}\|\d{1,}\|\d{1,}\|\d{1,}\|\d{1}/','',$new_url);
                $arr['real_mp4_url'][] = $new_url;
            }
            return $arr;
        }
        return FALSE;
    }
    
    
    /**
     * @name:get_duowan_video_by_vu
     * @description: 根据vu处理乐视云视频
     * @param: $vu=乐视云的vu参数
     * @return: json
     * @author: Xiong Jianbang
     * @create: 2015-4-29 下午3:09:22
     **/
    private function get_duowan_video_by_vu($vu=0){
        if(!empty($vu)){
            $json = $this->curl_get("http://playapi.v.duowan.com/index.php?r=play/baseinfo&vid=&letv_video_unique=$vu");
            if(!empty($json)){
                $arr = json_decode($json,TRUE);
                $vid = isset($arr['vid'])?$arr['vid']:0;
                unset($arr);
                if(!empty($vid)){
                    return json_encode(array('msg'=>$this->get_duowan_video_json($vid),'status'=>200,'type'=>'duowan_letv','vid'=>$vid));
                }else{
                    return json_encode(array('msg'=>'vid is empty','status'=>400));
                }
            }else{
                return json_encode(array('msg'=>'duowan json is empty','status'=>400));
            }
        }else{
            return json_encode(array('msg'=>'letvVideoUnique is empty','status'=>400));
        }
    }
    
    /**
     * @name:get_qq_vedio
     * @description: 分析优酷本站的视频源json文件
     * @return: 视频源json文件
     * @author: Xiong Jianbang
     * @create: 2015-4-27 下午6:24:04
     **/
    public function get_qq_vedio(){
    	$arr = parse_url($this->url);
    	$query = isset($arr['query'])?$arr['query']:'';
    	unset($arr);
    	if(empty($query)){
    	    return json_encode(array('msg'=>'QQ vid is empty','status'=>400));
    	}
    	$arr = explode('=', $query);
    	$vid = end($arr);
    	unset($arr);
    	return json_encode(array('msg'=>$this->get_tencent_video_json($vid),'status'=>200,'type'=>'qq','vid'=>$vid));
    }
    
    
    /**
     * @name:get_youku_vedio
     * @description: 分析优酷本站的视频源m3u8文件
     * @return: 视频源m3u8文件
     * @author: Xiong Jianbang
     * @create: 2015-4-27 上午11:11:48
     **/
    public function get_youku_vedio(){
        $arr_info = pathinfo($this->url);
        $filename = $arr_info['filename'];
        $pos = strpos($filename, '?');
        if($pos==FALSE){
            $vid =  str_replace('id_','',basename($filename,'.html'));
        }else{
            $vid =  str_replace('id_','',basename(strstr($filename, '?',TRUE),'.html'));
        }
        if(empty($vid)){
            return json_encode(array('msg'=>'Youku vid is empty','status'=>400));
        }else{
            //2015.11月底因m3u8算法改变，不能正常获取
//             return json_encode(array('msg'=>$this->get_youku_video_json($vid),'status'=>200,'type'=>'youku','vid'=>$vid));
            return json_encode(array('msg'=>'','status'=>200,'type'=>'youku','vid'=>$vid));
        }
    }
    /**
     * @name:get_pcgames_vedio
     * @description: 分析太平洋游戏网手游频道的域名是http://http://hs.pcgames.com.cn/的视频源json文件，目前找到了优酷的视频
     * @return: 视频源json文件或者m3u8文件
     * @author: chengdongcai
     * @create: 2015-5-20 16:46:32
     **/
    public function get_pcgames_vedio(){
    	$html = $this->curl_get($this->url);
    	//找出页面里的iframe 地址
     	$tmp_iframe = preg_match('/<iframe class="iframe_video" frameborder="0" height="400" src="(.*?)" width="480"><\/iframe>/', $html,$match_iframe);
        //优酷视频(找出iframe里包含的vid)
        if($tmp_iframe && preg_match('/\#(.*?)$/', $match_iframe[1],$match)){
            $vid = $match[1];
            if(empty($vid)){
                return json_encode(array('msg'=>'Youku vid is empty','status'=>400));
            }else{
                return json_encode(array('msg'=>$this->get_youku_video_json($vid),'status'=>200,'type'=>'youku','vid'=>$vid));
            }
        }else{
            return json_encode(array('msg'=>'Video json file is empty','status'=>400));
        }
    }
    /**
     * @name:get_tuwan_vedio
     * @description: 分析兔玩的域名是http://hs.tuwan.com的视频源json文件，目前找到了优酷的视频
     * @return: 视频源json文件或者m3u8文件
     * @author: chengdongcai
     * @create: 2015-5-20 16:46:32
     **/
    public function get_tuwan_vedio(){
    	$html = $this->curl_get($this->url);
    	//找出页面里的iframe 地址
    	//<iframe width="726" height="516" src="http://player.youku.com/embed/XOTU4ODM4NDg4" frameborder="0" allowfullscreen></iframe>
    	//优酷视频(找出iframe里包含的vid)
    	if(preg_match('/player\.youku\.com\/embed\/(.*?)"/', $html,$match)){
    		$vid = $match[1];
    		if(empty($vid)){
    			return json_encode(array('msg'=>'Youku vid is empty','status'=>400));
    		}else{
    			return json_encode(array('msg'=>$this->get_youku_video_json($vid),'status'=>200,'type'=>'youku','vid'=>$vid));
    		}
    	}else{
    		return json_encode(array('msg'=>'Video json file is empty','status'=>400));
    	}
    }
    /**
     * @name:get_ooqiu_vedio  
     * @description: 分析全球电竟网的域名是http://www.ooqiu.com/的视频源json文件，目前找到了腾讯和优酷的视频
     * @return: 视频源json文件或者m3u8文件
     * @author: Xiong Jianbang
     * @create: 2015-4-25 下午5:46:32
     **/
    public function get_ooqiu_vedio(){
        $html = $this->curl_get($this->url);
        //腾讯视频
        if(preg_match('/v\.qq\.com\/iframe\/player\.html\?vid=(.*?)&/', $html,$match)){
            $vid = $match[1];
            if(empty($vid)){
                return json_encode(array('msg'=>'Tencent vid is empty','status'=>400));
            }else{
                return json_encode(array('msg'=>$this->get_tencent_video_json($vid),'status'=>200,'type'=>'qq','vid'=>$vid));
            }
        }
        //优酷视频
        if(preg_match('/player\.youku\.com\/player\.php\/sid\/(.*?)\/v\.swf/', $html,$match)){
            $vid = $match[1];
            if(empty($vid)){
                return json_encode(array('msg'=>'Youku vid is empty','status'=>400));
            }else{
                return json_encode(array('msg'=>$this->get_youku_video_json($vid),'status'=>200,'type'=>'youku','vid'=>$vid));
            }
        }else{
            return json_encode(array('msg'=>'Video json file is empty','status'=>400));
        }
    }
    
    /**
     * @name:get_ahgame_vedio
     * @description: 分析安游的域名是http://lol.ahgame.com/的视频源json文件，目前找到了优酷的视频
     * @return: 视频源m3u8文件
     * @author: Xiong Jianbang
     * @create: 2015-4-25 下午5:58:12
     **/
    public function get_ahgame_vedio(){
         $html = file_get_contents($this->url);
        if(preg_match('/swf\/loader\.swf\?VideoIDS=(.*?)\&/', $html,$match)   ||  preg_match('/player\.youku\.com\/player\.php\/sid\/(.*?)\/v\.swf/', $html,$match)){
            $vid = $match[1];
                if(empty($vid)){
                    return json_encode(array('msg'=>'Youku vid is empty','status'=>400));
                }else{
                    return json_encode(array('msg'=>$this->get_youku_video_json($vid),'status'=>200,'type'=>'youku','vid'=>$vid));
                }
            }
            else{
                return json_encode(array('msg'=>'Video json file is empty','status'=>400));
            }
        }
        
        /**
         * @name:get_lolqu_video
         * @description: 分析撸撸趣的域名是www.lolqu.com的视频源json文件，目前找到了优酷的视频
         * @return: 视频源m3u8文件
         * @author: Xiong Jianbang
         * @create: 2015-4-25 下午5:29:14
         **/
        public function get_lolqu_video(){
            $html = $this->curl_get($this->url);
            //优酷视频
            if(preg_match('/player\.youku\.com\/player\.php\/sid\/(.*?)\/v\.swf/', $html,$match)){
                $vid = $match[1];
                if(empty($vid)){
                    return json_encode(array('msg'=>'Youku vid is empty','status'=>400));
                }else{
                    return json_encode(array('msg'=>$this->get_youku_video_json($vid),'status'=>200,'type'=>'youku','vid'=>$vid));
                }
            }else{
                return json_encode(array('msg'=>'Video json file is empty','status'=>400));
            }
        }
        
        /**
         * @name:get_demaxiya_video
         * @description: 分析德玛西亚的域名是www.demaxiya.com的视频源json文件，目前找到了优酷和腾讯的视频
         * @return: 视频源json文件或者m3u8文件
         * @author: Xiong Jianbang
         * @create: 2015-4-25 下午5:14:06
         **/
        public function get_demaxiya_video(){
            $arr = pathinfo($this->url);
            $file_id = $arr['filename'];
            if(empty($file_id) || !is_numeric($file_id)){//如果不是数字
                return json_encode(array('msg'=>'File ID is empty','status'=>400));
            }
            $fetch_url = "http://www.demaxiya.com/s/play.php?aid=$file_id";
            $json = $this->curl_get($fetch_url);
            if(empty($json)){
                return json_encode(array('msg'=>'javascript file is empty','status'=>400));
            }
            $arr = json_decode($json,TRUE);
            $html = $arr['playhtml'];
            //腾讯视频
            if(preg_match('/TencentPlayer.swf\?vid=(.*?)&/', $html,$match)){
                $vid = $match[1];
                if(empty($vid)){
                    return json_encode(array('msg'=>'Tencent vid is empty','status'=>400));
                }else{
                    return json_encode(array('msg'=>$this->get_tencent_video_json($vid),'status'=>200,'type'=>'qq','vid'=>$vid));
                }
            }
            //优酷视频
            elseif(preg_match('/player\.youku\.com\/player\.php\/sid\/(.*?)\/v\.swf/', $html,$match)){
                $vid = $match[1];
                if(empty($vid)){
                    return json_encode(array('msg'=>'Youku vid is empty','status'=>400));
                }else{
                    return json_encode(array('msg'=>$this->get_youku_video_json($vid),'status'=>200,'type'=>'youku','vid'=>$vid));
                }
            }else{
                return json_encode(array('msg'=>'Video json file is empty','status'=>400));
            }
        }
        
        /**
         * @name:get_4399_video_url
         * @description: 获取4399视频的播放地址
         * @param: $vid=播放id
         * @return: 视频地址
         * @author: Xiong Jianbang
         * @create: 2015-5-25 上午10:15:06
         **/
        private function get_4399_video_url($vid){
        	$xml = simplexml_load_file("http://video.5054399.com/v/v2/video_{$vid}.xml"); //创建 SimpleXML对象
        	foreach($xml->item->attributes() as $key => $value){
        	    if($key=='url'){
        	    	return strval($value);
        	    }
        	}
        }
        
        /**
         * @name:get_17173_video_json
         * @description: 获取17173的mp4地址
         * @param: $html17173本站的页面
         * @return: 17173的json格式地址
         * @author: Xiong Jianbang
         * @create: 2015-5-22 上午11:03:22
         **/
        private function get_17173_video_json($html){
            if(preg_match('/data-pnum="(\d*?)"/', $html,$match)){
                $vid = isset($match[1])?$match[1]:0;
                if(!empty($vid)){
                    return json_encode(array('msg'=>$this->get_17173_video_api($vid),'status'=>200,'type'=>'17173','vid'=>$vid));
                }
            }
        }
        
        
        /**
         * @name:get_aipai_video_json
         * @description: 获取爱拍的mp4地址
         * @param: $html爱拍本站的页面
         * @return: 爱拍的json格式地址
         * @author: Xiong Jianbang
         * @create: 2015-5-22 上午11:03:22
         **/
        private function get_aipai_video_json($html,$is_gzip=''){
            //还有一个是6033，不知道是什么东西
            if($is_gzip==31139){
                $html = $this->gzdecode($html);
            }
            $vid = 0;
            if(preg_match('/c24\/(.*?)\.html/', $this->url,$match)){
                $vid = isset($match[1])?$match[1]:'';
            }
            if(preg_match('/property="og:videosrc" content="(.*?)\?{1,}.*?"/', $html,$match)){
                $video_url = isset($match[1])?$match[1]:'';
                if(!empty($video_url)){
                    return json_encode(array('msg'=>$video_url,'status'=>200,'type'=>'aipai','vid'=>$vid));
                }
            }
            if(preg_match('/property="og:videosrc" content="(.*?)"/', $html,$match)){
                $video_url = isset($match[1])?$match[1]:'';
                if(!empty($video_url)){
                    return json_encode(array('msg'=>$video_url,'status'=>200,'type'=>'aipai','vid'=>$vid));
                }
            }
        }
        
        private function gzdecode($data) {
            $len = strlen($data);
            if ($len < 18 || strcmp(substr($data,0,2),"\x1f\x8b")) {
                return null;  // Not GZIP format (See RFC 1952)
            }
            $method = ord(substr($data,2,1));  // Compression method
            $flags  = ord(substr($data,3,1));  // Flags
            if ($flags & 31 != $flags) {
                // Reserved bits are set -- NOT ALLOWED by RFC 1952
                return null;
            }
            // NOTE: $mtime may be negative (PHP integer limitations)
            $mtime = unpack("V", substr($data,4,4));
            $mtime = $mtime[1];
            $xfl   = substr($data,8,1);
            $os    = substr($data,8,1);
            $headerlen = 10;
            $extralen  = 0;
            $extra     = "";
            if ($flags & 4) {
                // 2-byte length prefixed EXTRA data in header
                if ($len - $headerlen - 2 < 8) {
                    return false;    // Invalid format
                }
                $extralen = unpack("v",substr($data,8,2));
                $extralen = $extralen[1];
                if ($len - $headerlen - 2 - $extralen < 8) {
                    return false;    // Invalid format
                }
                $extra = substr($data,10,$extralen);
                $headerlen += 2 + $extralen;
            }
        
            $filenamelen = 0;
            $filename = "";
            if ($flags & 8) {
                // C-style string file NAME data in header
                if ($len - $headerlen - 1 < 8) {
                    return false;    // Invalid format
                }
                $filenamelen = strpos(substr($data,8+$extralen),chr(0));
                if ($filenamelen === false || $len - $headerlen - $filenamelen - 1 < 8) {
                    return false;    // Invalid format
                }
                $filename = substr($data,$headerlen,$filenamelen);
                $headerlen += $filenamelen + 1;
            }
        
            $commentlen = 0;
            $comment = "";
            if ($flags & 16) {
                // C-style string COMMENT data in header
                if ($len - $headerlen - 1 < 8) {
                    return false;    // Invalid format
                }
                $commentlen = strpos(substr($data,8+$extralen+$filenamelen),chr(0));
                if ($commentlen === false || $len - $headerlen - $commentlen - 1 < 8) {
                    return false;    // Invalid header format
                }
                $comment = substr($data,$headerlen,$commentlen);
                $headerlen += $commentlen + 1;
            }
        
            $headercrc = "";
            if ($flags & 1) {
                // 2-bytes (lowest order) of CRC32 on header present
                if ($len - $headerlen - 2 < 8) {
                    return false;    // Invalid format
                }
                $calccrc = crc32(substr($data,0,$headerlen)) & 0xffff;
                $headercrc = unpack("v", substr($data,$headerlen,2));
                $headercrc = $headercrc[1];
                if ($headercrc != $calccrc) {
                    return false;    // Bad header CRC
                }
                $headerlen += 2;
            }
        
            // GZIP FOOTER - These be negative due to PHP's limitations
            $datacrc = unpack("V",substr($data,-8,4));
            $datacrc = $datacrc[1];
            $isize = unpack("V",substr($data,-4));
            $isize = $isize[1];
        
            // Perform the decompression:
            $bodylen = $len-$headerlen-8;
            if ($bodylen < 1) {
                // This should never happen - IMPLEMENTATION BUG!
                return null;
            }
            $body = substr($data,$headerlen,$bodylen);
            $data = "";
            if ($bodylen > 0) {
                switch ($method) {
                	case 8:
                	    // Currently the only supported compression method:
                	    $data = gzinflate($body);
                	    break;
                	default:
                	    // Unknown compression method
                	    return false;
                }
            } else {
                // I'm not sure if zero-byte body content is allowed.
                // Allow it for now...  Do nothing...
            }
        
            // Verifiy decompressed size and CRC32:
            // NOTE: This may fail with large data sizes depending on how
            //       PHP's integer limitations affect strlen() since $isize
            //       may be negative for large sizes.
            if ($isize != strlen($data) || crc32($data) != $datacrc) {
                // Bad format!  Length or CRC doesn't match!
                return false;
            }
            return $data;
        }
        
        /**
         * @name:get_17173_video_api
         * @description: 获取17173视频的json格式的视频文件
         * @param: $vid=游戏视频id
         * @return: JSON格式的视频文件的URL地址
         * @author: Xiong Jianbang
         * @create: 2015-5-20 下午6:06:49
         **/
        private function get_17173_video_api($vid){
            return "";
//             return "http://v.17173.com/api/video/vInfo/id/{$vid}";
        }
        
        /**
         * @name:get_leshiyun_video_json
         * @description: 获取乐视云视频的json格式的视频文件
         * @param: $uu=用户唯一标识码，由乐视网统一分配并提供
         * @param:$vu=视频唯一标识码
         * @return: 视频播放地址
         * @author: Xiong Jianbang
         * @create: 2015-4-28 上午11:26:55
         **/
        private function  get_leshiyun_video_json($uu,$vu){
        	return "http://yuntv.letv.com/bcloud.html?uu={$uu}&vu={$vu}";
        }
        
        /**
         * @name:get_ku6_video_json
         * @description: 获取酷六视频的json格式的视频文件
         * @param: $vid=酷六视频的VID号
         * @return: JSON格式的视频文件的URL地址
         * @author: Xiong Jianbang
         * @create: 2015-4-28 上午11:26:55
         **/
        private  function get_ku6_video_json($vid=0){
            return "http://v.ku6.com/fetch.htm?t=getVideo4Player&vid=$vid";
        }
        
        /**
         * @name:get_duowan_video_json
         * @description: 获取多玩的json格式的视频文件
         * @param: $vid=多玩的VID号
         * @return: 视频文件的URL地址
         * @author: Xiong Jianbang
         * @create: 2015-4-25 下午5:02:28
         **/
        private function get_duowan_video_json($vid=0){
            return "http://playapi.v.duowan.com/index.php?vid=$vid&r=play%2Fvideo";
        }
        
        /**
         * @name:get_tencent_video_json
         * @description: 获取腾讯视频的json格式的视频文件
         * @param: $vid=腾讯视频的VID号
         * @return: JSON格式的视频文件的URL地址
         * @author: Xiong Jianbang
         * @create: 2015-4-25 下午5:02:28
         **/
        private function get_tencent_video_json($vid=0){
            return "http://vv.video.qq.com/geturl?vid=$vid&otype=json";
        }
        
        /**
         * @name:get_sohu_video_json
         * @description: 获取搜狐视频的json格式的视频文件
         * @param: $vid=搜狐视频的VID号
         * @return: JSON格式的视频文件的URL地址
         * @author: Xiong Jianbang
         * @create: 2015-4-28 上午11:26:55
         **/
        private  function get_sohu_video_json($vid=0){
            return "";
//             return "http://my.tv.sohu.com/videinfo.jhtml?m=viewtv&vid=$vid";
        }
        
        /**
         * @name:get_youku_video_json
         * @description: 获取优酷的json格式的视频文件
         * @param: $vid=优酷的VID号
         * @return: m3u8格式视频文件的URL地址
         * @author: Xiong Jianbang
         * @create: 2015-4-25 下午5:16:49
         **/
        private function get_youku_video_json($vid=0){
            require_once 'youku.class.php';
            $obj_yk = new Youku($vid);
            $ret = $obj_yk->get_m3u8_file();
            if(isset($ret['status']) && $ret['status']==400){
            	return '';
            }
            return $ret;
        }
        
        /**
         * @name:get_youtube_video_json
         * @description: 获取youkube的视频文件
         * @param: $vid=youkube的VID号
         * @return: youkube的视频文件地址
         * @author: Xiong Jianbang
         * @create: 2015-12-18 下午4:32:43
         **/
        private function get_youtube_video_json($vid=0){
            $url = 'http://www.youtube.com/get_video_info?video_id='.$vid;
            $str = file_get_contents($url);
            $str = urldecode($str);
            $arr = explode('&', $str);
            $videos = array();
            foreach ($arr as $value) {
                $temp = explode('=', $value);
                $k = reset($temp);
                $v = urldecode(urldecode(end($temp)));
                if($k=='url' && strpos($v, 'mime=video/mp4')){
                    $videos[] = array($k=>$v);
                }
            }
            $arr_video_url = isset($videos[0])?$videos[0]:'';
            if(empty($arr_video_url)){
                return FALSE;
            }
            return $arr_video_url['url'];
        }
        
        
        /**
         * @name:get_tudou_video_json
         * @description: 获取土豆视频的json格式的视频文件
         * @param: $vid=土豆视频的VID号
         * @return: json格式视频文件的URL地址
         * @author: Xiong Jianbang
         * @create: 2015-4-25 下午5:16:49
         **/
        private function get_tudou_video_json($vid=0){
            return "http://www.tudou.com/outplay/goto/getItemSegs.action?iid=$vid";
        }
        
        /**
         * @name:get_host
         * @description: 获取域名
         * @return: 域名字符串
         * @author: Xiong Jianbang
         * @create: 2015-4-25 下午5:10:05
         **/
        private function get_host(){
            $arr_url = parse_url($this->url);
            if(empty($arr_url)){
                return FALSE;
            }
            $host = $arr_url['host'];
            return $host;
        }
        
        
        /**
         * @name:get_sub_host
         * @description: 获取子域名，即二级域名
         * @return: $sub_host
         * @author: Xiong Jianbang
         * @create: 2015-11-25 下午2:27:20
         **/
        public function get_sub_host(){
            $host = $this->get_host();
            if(empty($host)){
                return json_encode(array('msg'=>'Page is error','status'=>400));
            }
            $char_count = substr_count($host, '.');
            $sub_host = $host;
            if($char_count>1){
                $sub_host = substr($host,strpos($host,'.'));//类似http://coc.5253.com/1502/287512292747.html
            }else{
                $sub_host = '.'.$host;//类似https://everyplay.com/videos/10147379
            }
            return $sub_host;
        }
        
        
        /**
         * @name:curl_get
         * @description: CURL GET请求
         * @param: $url=请求地址  $second=超时时间
         * @return: string or boolean
         * @author: Xiong Jianbang
         * @create: 2014-12-9 上午11:54:29
         **/
        public function curl_get($url, $second=60,$curl_opt=array()){
            if(empty($url)){
                return false;
            }
         	$arr_agent = array(
					"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; AcooBrowser; .NET CLR 1.1.4322; .NET CLR 2.0.50727)",
					"Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.0; Acoo Browser; SLCC1; .NET CLR 2.0.50727; Media Center PC 5.0; .NET CLR 3.0.04506)",
					"Mozilla/4.0 (compatible; MSIE 7.0; AOL 9.5; AOLBuild 4337.35; Windows NT 5.1; .NET CLR 1.1.4322; .NET CLR 2.0.50727)",
					"Mozilla/5.0 (Windows; U; MSIE 9.0; Windows NT 9.0; en-US)",
					"Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; Win64; x64; Trident/5.0; .NET CLR 3.5.30729; .NET CLR 3.0.30729; .NET CLR 2.0.50727; Media Center PC 6.0)",
					"Mozilla/5.0 (compatible; MSIE 8.0; Windows NT 6.0; Trident/4.0; WOW64; Trident/4.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; .NET CLR 1.0.3705; .NET CLR 1.1.4322)",
					"Mozilla/4.0 (compatible; MSIE 7.0b; Windows NT 5.2; .NET CLR 1.1.4322; .NET CLR 2.0.50727; InfoPath.2; .NET CLR 3.0.04506.30)",
					"Mozilla/5.0 (Windows; U; Windows NT 5.1; zh-CN) AppleWebKit/523.15 (KHTML, like Gecko, Safari/419.3) Arora/0.3 (Change: 287 c9dfb30)",
					"Mozilla/5.0 (X11; U; Linux; en-US) AppleWebKit/527+ (KHTML, like Gecko, Safari/419.3) Arora/0.6",
					"Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.2pre) Gecko/20070215 K-Ninja/2.1.1",
					"Mozilla/5.0 (Windows; U; Windows NT 5.1; zh-CN; rv:1.9) Gecko/20080705 Firefox/3.0 Kapiko/3.0",
					"Mozilla/5.0 (X11; Linux i686; U;) Gecko/20070322 Kazehakase/0.4.5",
					"Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.9.0.8) Gecko Fedora/1.9.0.8-1.fc10 Kazehakase/0.5.6",
					"Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/535.11 (KHTML, like Gecko) Chrome/17.0.963.56 Safari/535.11",
					"Mozilla/5.0 (Macintosh; Intel Mac OS X 10_7_3) AppleWebKit/535.20 (KHTML, like Gecko) Chrome/19.0.1036.7 Safari/535.20",
					"Opera/9.80 (Macintosh; Intel Mac OS X 10.6.8; U; fr) Presto/2.9.168 Version/11.52",
					'Mozilla/5.0 (Windows NT 6.3; WOW64; rv:38.0) Gecko/20100101 Firefox/38.0',
			);
			$rand_key = rand(0, count($arr_agent)-1);
			$user_agent = isset($arr_agent[$rand_key])?$arr_agent[$rand_key]:'Mozilla/5.0 (Windows NT 6.3; WOW64; rv:38.0) Gecko/20100101 Firefox/38.0';
            $ch = curl_init();
            curl_setopt($ch,CURLOPT_TIMEOUT,$second);
            curl_setopt($ch, CURLOPT_URL,$url);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch,CURLOPT_USERAGENT,$user_agent);
            curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
            curl_setopt($ch, CURLOPT_ENCODING, 'deflate');
            if(!empty($curl_opt)){
            	if(isset($curl_opt['referer']) && !empty($curl_opt['referer'])){
            		curl_setopt($ch, CURLOPT_REFERER, $curl_opt['referer']);
            	}
            }
//             curl_setopt($ch, CURLOPT_ENCODING, "");
            $data = curl_exec($ch);
            curl_close($ch);
            if ($data){
                return $data;
            }else{
                return false;
            }
        }
    }
    
    // $url = 'http://www.demaxiya.com/lol/22744.html';
    // $url = "http://www.lolqu.com/dashen/sktfaker/11318.html";
    // $url = "http://lol.ahgame.com/mov/2014020844805.shtml";
    //$url = "http://lol.ahgame.com/mov/2013111930885.shtml";
    // $url = "http://www.ooqiu.com/2015/0424/66934.html";
    // $url = "http://www.ooqiu.com/2014/0913/18038.html";
    //http://lol.duowan.com/1312/251115918553.html  多玩的土豆视频来源
