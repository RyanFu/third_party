<?php
/**
 * @copyright: @快游戏广州 2015
 * @description: 视频管理控制器
 * @file: video.php
 * @author: chengdongcai
 * @charset: UTF-8
 * @time: 2015-04-28  10:19
 * @version 1.0
 **/
 
class Video extends MZW_Controller {
	private $mod = NULL;
	private $tmp_type_arr = NULL;
	private $arr_url_status = NULL;
	public function __construct(){
		parent::__construct();
		$this->chect_login_exit();//判断是否登陆
		$this->load->model("admin/video_model");
		$this->load->model("admin/game_model");
        $this->load->model("admin/ad_model");
        $this->load->model("admin/member_model");
		$this->mod = &$this->video_model;
		//加载视频解析类
		$this->load->library('video_parser');
		//视频导入类别
		$this->tmp_type_arr = array(
				'-1'=>'未知',
				'1'=>'人物',
				'2'=>'解说',
				'3'=>'赛事战况',
				'4'=>'集锦',
				'5'=>'职业',
				'6'=>'作者解说',
		        '7'=>'阵型',
		        '100'=>'其他'
		);
		$this->arr_url_status = array(
		        999=>'待命',
		        1000=>'已经处理过',
		        1001=>'解析为空',
		        1002=>'解析不成功',
		        1003=>'正式开始',
		        1004=>'下载成功',
		        1005=>'下载失败',
		        1006=>'同步成功',
		        1007=>'同步失败',
		        1008=>'入库成功',
		        1009=>'视频图像推送成功',
		        1010=>'不抓取非指定网址',
		);
		$this->arr_cdn_url_status= array(
		        -99=>'待命',
		        100=>'开始CDN任务',
		        101=>'开始水印任务',
		        200=>'下载成功',
		        300=>'同步成功',
		        400=>'下载失败',
		        500=>'添加水印成功',
		        600=>'准备水印+片头任务',
		        601=>'开始添加水印',
		        602=>'打上水印成功',
		        603=>'开始合并任务',
		        604=>'片头合并成功',
		);
		$this->arr_spider_url_status= array(
		        -99=>'待命',
		        100=>'开始任务',
		        200=>'采集完毕',
		        400=>'类别错误',
		);
	}
	
	/**
	 * @name:video_file_upload
	 * @description: 视频采集内容上传的页面
	 * @author: chengdongcai
	 * @create: 2015-04-28 11:14:43
	 **/
	public function video_file_upload(){
		if( !$this->check_right( '140001' ) ){//如果没有权限
			$this->url_msg_goto( get_referer(), '您没有操作权限！' );
		}

		//引入上传类
		require APPPATH.'/libraries/simple_ajax_uploader.php';
		//上传图片后缀名限制
		$valid_extensions = array('gif', 'png', 'jpeg', 'jpg','webp','txt');
		$Upload = new FileUpload('uploadfile');
		//上传大小限制
		$Upload->sizeLimit = 2*10485760;  //上限20M
		//创建图片存放目录
		$date =  '/video_file' .date('/Y/m/d/');  //添加模块名作目录一部分
		$upload_dir = $GLOBALS['APK_UPLOAD_DIR']  . $date;
		create_my_file_path($upload_dir,0755);
		//生成新图片名称
		$Upload->newFileName = md5(uniqid().$Upload->getFileName()).'.'.$Upload->getExtension();
		$result = $Upload->handleUpload($upload_dir, $valid_extensions);
		if (!$result) {
			echo json_encode(array('success' => false, 'msg' => $Upload->getErrorMsg()));
			exit;
		}
		//上传成功
		else {
			$img_path = $date . $Upload->getFileName();
			echo json_encode(array('success' => true, 'file' => $img_path));
		}
	}
	
	/**
	 * @name:video_file_list
	 * @description: 视频采集内容上传列表页面
	 * @author: chengdongcai
	 * @create: 2015-04-28 11:14:43
	 **/
	public function video_file_list(){
		if( !$this->check_right( '140002' ) ){//如果没有权限
			$this->url_msg_goto( get_referer(), '您没有操作权限！' );
		}
		$data = array(
				'js' => array(
						'App'=>'admin/scripts/app.js', //需要引入app.js
						'Index'=>'admin/scripts/index.js', //需要引入index.js
						'FormValidation'=>'admin/scripts/muzhiwan.js/video_file.js'
				)
		);
		$this->display( $data, 'video_file_list' );
	}

	/**
	 * @name: ajax_get_video_file_data
	 * @description: 视频资料导入列表数据获取
	 * @param: NULL
	 * @return: NULL
	 * @author: chengdongcai
	 * @create: 2015-02-28  11:47
	 **/
	public function ajax_get_video_file_data() {
	
		if( !$this->check_right( '140001' ) ){//如果没有权限
			$this->url_msg_goto( get_referer(), '您没有操作权限！' );
		}
	
		$start_record	= get_var_value( 'iDisplayStart' ); 	//从多少条开始查询
		$page_size		= get_var_value( 'iDisplayLength' ); 	//每页显示多少条记录
		$s_echo			= get_var_value( 'sEcho' );
		
		$conditions = '';
	
		//获取视频资料导入列表数据
		$res = $this->mod->ajax_get_video_file_data( $start_record, $page_size, $conditions );
	
		//参数转换
		if(!empty($res[0])){

			foreach($res[0] as $key => $val){
				$res[0][$key]['vf_isok'] = ($val['vf_isok'] == 1) ? '成功' : '失败';
				$tmp_game_arr = $this->mod->get_game_list($val['vf_game_id']);
				$res[0][$key]['vf_game_id'] = isset($tmp_game_arr[$val['vf_game_id']])?$tmp_game_arr[$val['vf_game_id']]:'未知游戏';
				//1任务，2解说，3赛事战况，4集锦
				$res[0][$key]['vf_type_id'] = isset($this->tmp_type_arr[$val['vf_type_id']])?$this->tmp_type_arr[$val['vf_type_id']]:'未知';
				$res[0][$key]['vf_in_time'] = empty($val['vf_in_time']) ? 0 : date("Y-m-d H:i:s",$val['vf_in_time']);
			}
		}
	
		echo $this->get_normol_json( $res[0],$s_echo,$res[1] );
	}

	/**
	 * @name: video_file_update
	 * @description: 视频资料导入添加编辑显示
	 * @param: 无
	 * @return: 无
	 * @author: chengdongcai
	 * @create: 2015-04-28 14:42:50
	 **/
	public function video_file_update(){
		if( !$this->check_right( '140001' ) ){//如果没有权限
			$this->url_msg_goto( get_referer(), '您没有操作权限！' );
		}
		$data = array(
				'js' => array(
						'App'=>'admin/scripts/app.js', //需要引入app.js
						'Index'=>'admin/scripts/index.js', //需要引入index.js
						'admin/scripts/muzhiwan.js/SimpleAjaxUploader.js', //图片上传
						'FormValidation'=>'admin/scripts/muzhiwan.js/video_file.js'
				)
		);
		//查找要导入的游戏
		$data['game_id_arr'] = $this->mod->get_game_list();
		$data['video_type'] = $this->tmp_type_arr;
		$this->display( $data, 'video_file_update' );
	}
	
	/**
	 * @name: video_file_save
	 * @description: 视频资料添加编辑保存
	 * @param:
	 * @return: json
	 * @author: chengdongcai
	 * @create: 2015-04-28 13:44:50
	 **/
	public function video_file_save(){
		//定义AJAX返回的数组
		$arr = array(
				'status'=>200,//执行状态(例如：200成功，301失败...),
				'message'=>'友情链接添加成功',//返回信息,
				'data'=>'',//返回数据,
				'tourl'=>''//要跳转的地址
		);
		if( !$this->check_right( '140001' ) ){//如果没有权限
			$arr['status'] = 1;
			$arr['message'] = '您没有操作权限！';
			$this->callback_ajax( $arr );
		}
		
		$vf_type_id = intval(get_var_post("vf_type_id")); //导入类别
		$game_id = intval(get_var_post("game_id")); //导入的游戏
		
		$vf_intro = get_var_post("vf_intro"); //备注信息
		if(is_empty($vf_intro)){
			$vf_intro = isset($this->tmp_type_arr[$vf_type_id])?$this->tmp_type_arr[$vf_type_id]:'';
		}
		$uploadfile = get_var_post("uploadfile"); //要导入的数据
		
		if(is_empty($vf_type_id)){
			$arr['status'] = 2;
			$arr['message'] = '您没有选择导入类别！';
			$this->callback_ajax( $arr );
		}
		if(is_empty($uploadfile)){
			$arr['status'] = 3;
			$arr['message'] = '您没有上传文件！';
			$this->callback_ajax( $arr );
		}
		if(is_empty($game_id)){
			$arr['status'] = 21;
			$arr['message'] = '您没有选择游戏！';
			$this->callback_ajax( $arr );
		}
		$data = array(
		  'vf_type_id'=>$vf_type_id,//导入类别标记(1任务，2解说，3赛事战况，4集锦,5职业)',
		  'vf_intro'=>$vf_intro,//导入说明',
		  'vf_path'=>$uploadfile,//导入文件路径',
		  'vf_in_uid'=>$_SESSION["sys_admin_id"],//导入人ID',
		  'vf_in_name'=>$_SESSION["sys_admin_name"],//导入人',
		  'vf_in_time'=>time(),//导入时间',
		  'vf_isok'=>2,//导入是否成功(1成功,2失败)',
		  'vf_game_id'=>$game_id //导入的游戏
		);
		$id = $this->mod->save_info( $data, 'video_file_info' );
		if($id){//如果添加数据成功，则
			$id = $this->mod->last_insert_id();
			//读取要上传的视频资料
			$video_file = @file_get_contents($GLOBALS['APK_UPLOAD_DIR'].$uploadfile);
			if(!is_empty($video_file)){
				//把json的内容转变成数据组的内容
				$video_info =json_decode($video_file,true);
				if(is_empty($video_info)){//如果内容为空
					$arr['status'] = 4;
					$arr['message'] = '导入文件为空！';
					unset($video_file);
					$this->callback_ajax( $arr );
				}
			}else{//如果打开文件出错
				$arr['status'] = 4;
				$arr['message'] = '导入文件不正确！';
				$this->callback_ajax( $arr );
			}
			//进行数据的导入
			$tmp_load = FALSE;//保存导入状态
			switch ($vf_type_id){
				case 1://1任务
					foreach ($video_info as $info){
						$tmp_load = $this->save_info_1($info,1,$game_id);
						if($tmp_load==FALSE){
							break;
						}
					}
					break;
				case 2://2解说
					foreach ($video_info as $info){
						$tmp_load = $this->save_info_2($info,2,$game_id);
						if($tmp_load==FALSE){
							break;
						}
					}
					break;
				case 3://3赛事战况
					foreach ($video_info as $info){
						$tmp_load = $this->save_info_3($info,3,$game_id);
						if($tmp_load==FALSE){
							break;
						}
					}
					break;
				case 4://4集锦
					foreach ($video_info as $info){
						$tmp_load = $this->save_info_4($info,4,$game_id);
						if($tmp_load==FALSE){
							break;
						}
					}
					break;
				case 5://5职业
					foreach ($video_info as $info){
						$tmp_load = $this->save_info_5($info,5,$game_id);
						if($tmp_load==FALSE){
							break;
						}
					}
					break;
				case 6://6作者解说
					foreach ($video_info as $info){
						$tmp_load = $this->save_info_6($info,6,$game_id);
						if($tmp_load==FALSE){
							continue;
						}
					}
					break;
					case 7://7阵型
					    foreach ($video_info as $info){
					        $tmp_load = $this->save_info_3($info,7,$game_id);
					        if($tmp_load==FALSE){
					            break;
					        }
					    }
				    break;
				    case 8:
				    case 9:
				    case 10:
				    case 11:
				    case 100://100其他，按解说的格式来导
				        foreach ($video_info as $info){
				            $tmp_load = $this->save_info_2($info,2,$game_id);
				            if($tmp_load==FALSE){
				                break;
				            }
				        }
				        break;
				default:
					$arr['status'] = 5;
					$arr['message'] = '导入类型不正确！';
					$this->callback_ajax( $arr );
					break;
			}
			//更新上传文件的导入状态
			if($tmp_load==TRUE){
				//如果导入成功，则更新上传文件的状态为成功
				$data = array('vf_isok'=>1);
				$where = array('id'=>$id);
				$this->mod->update_info( 'video_file_info', $data, $where);
			}
			//1添加，2修改，3删除，4数据导入，5数据导出，6其他
			$tmp_log_msg = "添加视频资料成功,id号为：{$id}";
			$this->mod->log_db_admin( $tmp_log_msg, 1, __CLASS__ );
			$arr['status'] = 200;
			$arr['message'] = '添加视频资料成功！';
			$arr['url'] = '/admin/video/video_file_list';
			$this->callback_ajax( $arr );
		}

	}
	
	
	/**
	 * @name: save_info_1
	 * @description: 保存 1任务  资料的内容
	 * @param:$data =array(
    'hBigIcon'=>"",
    'hEngName'=>"xinzhao",
    'hIcon'=>"http://pic5.duowan.com/lol/1205/200657192402/200657210853.png",
    'hIndexUrl'=>"http://lol.duowan.com/xinzhao",
    'hIntro'=>"这是一个残忍而扭曲的角斗赛事：当一位斗士赢得比赛时，他所要同时面对的对手数目会随之增加。这就意味着每个参赛者最终都必死无疑，只是会带着无上的荣耀死去。赵信，当时被称为"维斯塞罗"，所面对的是300名士兵，这个数目是之前记录的将近六倍。显然，这也意味着是他的最终赛事了。",
    'hIntroVideo'=>"http://player.youku.com/player.php/partnerid/XMTAwNA==/sid/XMjc1OTQxMjIw/v.swf",
    'hName'=>"赵信",
    'hSearchText'=>"赵信 德邦总管",
    'hSkillUrls'=>array(
     	0=>"http://lol.15w.com/db/xinzhao.html",
     	1=>"http://lol.15w.com/db/Xinzhao.html"
    ),
    'hTag'=>"英雄标签：近战，爆发，控制",
    'hVideoList'=>array(
    	0=>array(
        'sourceType'=>2
        'vAuthorImgUrl'=>""
        'vAuthorName'=>""
        'vImgUrl'=>"http://img1.dwstatic.com/lol/1504/293843776829/1429889086200.jpg"
        'vPlayCount'=>""
        'vRealPalyUrl'=>"http://lol.duowan.com/1504/293843776829.html"
        'vRealPlayUrlBack'=>""
        'vTime'=>""
        'vTitle'=>"夜魔解说：钻石菊花信 逆风翻盘详解！"
        'videoType'=>1,
        'number'=>1,
      	),
      	1=>array(
        'sourceType'=>2
        'vAuthorImgUrl'=>""
        'vAuthorName'=>""
        'vImgUrl'=>"http://img.dwstatic.com/lol/1504/292067869678/1428113024733.jpg"
        'vPlayCount'=>""
        'vRealPalyUrl'=>"http://lol.duowan.com/1504/292067869678.html"
        'vRealPlayUrlBack'=>""
        'vTime'=>""
        'vTitle'=>"萌妹冬阳：长枪依在 基友最爱上单菊花信"
        'videoType'=>1,
        'number'=>2,
      	),
      ),
      'heroData'=>array(
      'akt'=>"攻击力：52 (+3.1/每级)"
      'armor'=>"护甲：16 (+3.7/每级)"
      'armorStrike'=>""
      'armorStrikePercent'=>""
      'attackSpeed'=>"攻击速度：0.65(+2.6%/每级)"
      'chnPrice'=>"国服售价：3150金币 / 2500点卷"
      'critHurtAdd'=>"暴击伤害加成：200"
      'critProbability'=>""
      'enemyUseInfo'=>""
      'heroAttrList'=>array(
        0=>array(
          'haAssess'=>"高"
          'haName'=>"生命值"
          'percent'=>80
        ),
        1=>array(
          'haAssess'=>"高"
          'haName'=>"物理攻击"
          'percent'=>89
        )
      ),
      'magicHurt'=>""
      'magicHurtReduce'=>"魔法伤害减免：23%"
      'magicRecover'=>"魔法抗性：30(+1.25/每级)"
      'magicResist'=>""
      'magicStrikePercent'=>""
      'movementSpeed'=>"移动速度：345"
      'mp'=>"魔法值：213 (+31/每级)"
      'phyHurtReduce'=>""
      'range'=>""
      'selfUseInfo'=>""
      'vitality'=>"生命值：445 (+87/每级)"
      'vitalityRecover'=>"生命回复：7.0(+0.7/每级)"
    ),
    'heroSkillList'=>array(
      0=>array(
        'hsImg'=>"http://files.15w.com/lol/2012/1219/135589776986.gif"
        'hsIntro'=>"卡西奥佩娅在一片区域施放带短暂延迟的剧毒。区域内的敌人会中毒，在3秒的时间里持续受到共75/115/155/195/235(+0.45)魔法伤害。 如果瘟毒爆炸命中了一名敌方英雄，那么卡西奥佩娅就会获得10/15/20/25/30%移动速度加成，持续3秒。 伤害：75/115/155/195/235 额外移动速度：10/15/20/25/30 法力消耗：40/45/50/55/60"
        'hsName'=>"瘟毒爆炸"
        'hsUse'=>"35 / 45 / 55 / 65 / 75"
        'hsWait'=>"3"
        'shortKey'=>"Q"
      )
      1=>array(
        'hsImg'=>"http://files.15w.com/lol/2012/1219/135589776991.gif"
        'hsIntro'=>"卡西奥佩娅施放一团逐渐扩散的毒云，持续7秒。毒云中的敌人将中毒2秒，每秒受到10/15/20/25/30(+0.1)魔法伤害，并被减速25/30/35/40/45%。 只要敌人在毒云中，毒云就会持续重置敌人身上的这个中毒效果。 每秒伤害：10/15/20/25/30 减速幅度：25/30/35/40/45%"
        'hsName'=>"剧毒迷雾"
        'hsUse'=>"70 / 80 / 90 / 100 / 110"
        'hsWait'=>"9 / 9 / 9 / 9"
        'shortKey'=>"W"
      )
 	)
    'jobs'=>array(
      0=>"上单半肉"
      1=>"打野半肉"
    )
    );
	 * @param:$type 默认等于 1人物
	 * @param:$game_id 默认等于 0
	 * @return: TRUE/FALSE
	 * @author: chengdongcai
	 * @create: 2015-04-28 15:33:50
	 **/
	private function save_info_1($data,$type=1,$game_id=0){
		//创建视频地址解析对像
		$video = new video_parser();
		
		//创建图片存放目录
		$date =  '/video_img' .date('/Y/m/d/');  //添加模块名作目录一部分
		$to_save = $this->config->item('image_root_path') . $date;
		create_my_file_path($to_save,0755);
		
		//判断英雄是否存在
		$tmp_where = array(
			'hi_game_id'=>$game_id,//'游戏ID',
			'hi_name_cn'=>$data['hName'],//'英雄名称',
			'hi_name'=>$data['hEngName']//'英雄英文名称',
		);
		$hero_id = $this->mod->check_hero_by_name($tmp_where);
		if($hero_id==FALSE){//如果英雄不存在，则需要插入英雄
			//采集英雄的icon,解析英雄的视频地址,英雄技能列表对应图片
			$tmp_icon_get = '';//英雄头像(本地上传)',
			$tmp_bicon_get = '';//英雄大头像(本地上传)
			$tmp_introvideo_get = '';//'英雄介绍视频地址(解析出来的地址)'
			
			if(!is_empty($data['hIcon'])){
				$tmp_icon_get = save_remote_image($data['hIcon'],$to_save);
				$tmp_icon_get = str_replace($this->config->item('image_root_path'), '', $tmp_icon_get);
			}
			if(!is_empty($data['hBigIcon'])){
				$tmp_bicon_get = save_remote_image($data['hBigIcon'],$to_save);
				$tmp_bicon_get = str_replace($this->config->item('image_root_path'), '', $tmp_icon_get);
			}
			if(!is_empty($data['hIntroVideo'])){
				$video->set_url($data['hIntroVideo']);
				$tmp_introvideo_get = $video->parse();
				$tmp_introvideo_get = json_decode($tmp_introvideo_get,true);
				if($tmp_introvideo_get['status']!=200){
					$tmp_introvideo_get['msg']='';
					$tmp_introvideo_get['type']='';
				}else{
					$tmp_introvideo_get['type']= $video->map_source_type($tmp_introvideo_get['type']);
				}
			}else{
				$tmp_introvideo_get['msg']='';
				$tmp_introvideo_get['type']='';
			}
			//英雄保存需要的信息
			$arr = array(
					'in_date'=>time(),//'采集日期',
					'hi_game_id'=>$game_id,//'游戏ID',
					'hi_class'=>0,//'战场职责(1近战,2远程,3物理,4法术,5坦克,6辅助.7打野,8突进,9男性,10女性)',
					'hi_jobs'=> is_empty($data['jobs'])?'':json_encode($data['jobs']),//战场职责(对应于hi_class的ID记录,从采集来)
					'hi_name_cn'=>$data['hName'],//'英雄名称',
					'hi_name'=>$data['hEngName'],//'英雄英文名称',
					'hi_searchtext'=>$data['hSearchText'],//'（暂时没用）',
					'hi_icon'=>$data['hIcon'],//'英雄头像',
					'hi_icon_get'=>$tmp_icon_get,//'英雄头像(本地上传)',
					'hi_bicon'=>$data['hBigIcon'],//'英雄大头像',
					'hi_bicon_get'=>$tmp_bicon_get,//'英雄大头像(本地上传)',
					'hi_indexurl'=>$data['hIndexUrl'],//'没用(先直接保存)',
					'hi_skillurls'=>is_empty($data['hSkillUrls'])?'':json_encode($data['hSkillUrls']),//'没用(先直接保存)',
					'hi_tag'=>$data['hTag'],//'英雄标签',
					'hi_introvideo'=>$data['hIntroVideo'],//'英雄介绍视频地址(需要解析)',
					'hi_introvideo_get'=>$tmp_introvideo_get['msg'],//'英雄介绍视频地址(解析出来的地址)',
					'hi_intro'=>$data['hIntro'],//'英雄简介',
					'hi_herodata'=>is_empty($data['heroData'])?'':json_encode($data['heroData']),//'Json对象，英雄数据',
					'hi_isshow'=>1,//'是否显示(1显示,2隐藏)',
					'hi_order'=>0,//'排序号',
					'hi_heroskilllist'=>is_empty($data['heroSkillList'])?'':json_encode($data['heroSkillList']),//'英雄技能列表Json对像',
				 );
			$hero_id = $this->mod->save_hero_data($arr,false);
		}
		//如果英雄ID为空，则出错
		if( is_empty($hero_id) ){
			return FALSE;
		}
		
		//保存英雄技能列表
		if(!is_empty($data['heroSkillList'])){
			foreach ($data['heroSkillList'] as $val){
				//判断是否已经存在英雄技能
				$tmp_arr = array(
					'hs_game_id'=>$game_id,//'游戏ID',
					'hs_hi_id'=>$hero_id,//英雄ID(来自video_hero_info表)
					'hs_name'=>$val['hsName']//英雄技能名称
				 );
				//如果技能已经存在，则不插入当次的数据
				if( $this->mod->check_hero_skilllist_by_name( $tmp_arr )!=FALSE ){
					continue;
				}
				
				$tmp_skill_img = '';
				if( !is_empty($val['hsImg']) ){
					$tmp_skill_img = save_remote_image($val['hsImg'],$to_save);
					$tmp_skill_img = str_replace($this->config->item('image_root_path'), '', $tmp_skill_img);
				}
				$tmp_arr = array(
				  'in_date'=>time(),//'采集日期',
				  'hs_game_id'=>$game_id,//'游戏ID',
				  'hs_hi_id'=>$hero_id,//'英雄ID(来自video_hero_info表)',
				  'hs_name'=>$val['hsName'],//'技能名称',
				  'hs_img'=>$val['hsImg'],//'技能图片',
				  'hs_img_get'=>$tmp_skill_img,//'上传的技能图片',
				  'hs_intro'=>$val['hsIntro'],//'技能简介',
				  'hs_use'=>$val['hsUse'],//'技能值',
				  'hs_wait'=>$val['hsWait'],//'技能冷却时间',
				  'hs_shortkey'=>$val['shortKey'],//'技能快捷键',
				  'hs_isshow'=>1,//'是否显示(1显示,2隐藏)',
				  'hs_order'=>0//'排序号',
			 	);
				$this->mod->save_hero_skilllist($tmp_arr);
			}
		}
		//保存英雄视频
		if(!is_empty($data['hVideoList'])){
			foreach ($data['hVideoList'] as $val2){
				//判断是否已经存在英雄视频内容
				$tmp_arr = array(
					 'hi_id'=>$hero_id,//'英雄ID',
					 'game_id'=>$game_id,//'游戏ID',
					 'category_id'=>'',//视频联赛ID(来自video_category_info表)
					 'type_id'=>$type,//视频类型（1任务，2解说，3赛事战况，4集锦）',
					 'author_id'=>'',//'解说作者ID(来自video_author_info表)',
					 'title'=>$val2['vTitle'],//'视频标题',
					 'number'=>intval($val2['number']),//系统默认排序
				 );
				//如果视频已经存在，则不插入当次的数据
				if( $this->mod->check_video_by_name( $tmp_arr )!=FALSE ){
					continue;
				}
				
				$tmp_img = '';//播放图片
				$tmp_playurl_get = '';//优酷播放地址(解析出来的)',
				$tmp_playurl_back_get = '';//视频备用地址(解析出来的)'
				//下载播放图片
				if( !is_empty($val2['vImgUrl']) ){
					$tmp_img = save_remote_image($val2['vImgUrl'],$to_save);
					$tmp_img = str_replace($this->config->item('image_root_path'), '', $tmp_img);
				}
				if(!is_empty($val2['vRealPalyUrl'])){
					$video->set_url($val2['vRealPalyUrl']);
					$tmp_playurl_get = $video->parse();//播放地址(解析出来的)',
					if( !is_empty($tmp_playurl_get) ){
						$tmp_playurl_get = json_decode($tmp_playurl_get,true);
						if($tmp_playurl_get['status']!=200){
							$tmp_playurl_get['msg']='';
							$tmp_playurl_get['type']=intval($val2['sourceType']);
						}else{
							$tmp_playurl_get['type']= $video->map_source_type($tmp_playurl_get['type']);
						}
					}else{
						$tmp_playurl_get['msg']='';
						$tmp_playurl_get['type']=intval($val2['sourceType']);
					}
				}else{
					$tmp_playurl_get['msg']='';
					$tmp_playurl_get['type']=intval($val2['sourceType']);
				}
				if(!is_empty($val2['vRealPlayUrlBack'])){
					$video->set_url($val2['vRealPlayUrlBack']);
					$tmp_playurl_back_get = $video->parse();//视频备用地址(解析出来的)'
					if( !is_empty($tmp_playurl_back_get) ){
						$tmp_playurl_back_get = json_decode($tmp_playurl_back_get,true);
						if($tmp_playurl_back_get['status']!=200){
							$tmp_playurl_back_get['msg']='';
							$tmp_playurl_back_get['type'] = intval($val2['sourceType']);
						}else{
							$tmp_playurl_back_get['type']= $video->map_source_type($tmp_playurl_back_get['type']);
						}
					}else{
						$tmp_playurl_back_get['msg']='';
						$tmp_playurl_back_get['type'] = intval($val2['sourceType']);
					}
				}else{
					$tmp_playurl_back_get['msg']='';
					$tmp_playurl_back_get['type'] = intval($val2['sourceType']);
				}
				$tmp_arr = array(
					  'in_date'=>time(),//'采集日期',
					  'vvl_hi_id'=>$hero_id,//'英雄ID(来自video_hero_info表)',
					  'vvl_game_id'=>$game_id,//'游戏ID',
					  'vvl_category_id'=>0,//'视频联赛ID(来自video_category_info表)',
					  'vvl_type_id'=>$type,//'视频类型（1任务，2解说，3赛事战况，4集锦）',
					  'vvl_sourcetype'=>$tmp_playurl_get['type'],//'视频来源（1优酷，2多玩）',
					  'vvl_imgurl'=>$val2['vImgUrl'],//'视频图片URL地址',
					  'vvl_imgurl_get'=>$tmp_img,//'视频图片URL地址',
					  'vvl_time'=>intval($val2['vTime']),//'视频时长',
					  'vvl_playurl'=>$val2['vRealPalyUrl'],//'优酷播放地址，需要解析',
					  'vvl_playurl_get'=>$tmp_playurl_get['msg'],//'优酷播放地址(解析出来的)',
					  'vvl_author_id'=>0,//'解说作者ID(来自video_author_info表)',
					  'vvl_title'=>$val2['vTitle'],//'视频标题',
					  'vvl_playurlback'=>$val2['vRealPlayUrlBack'],//'视频备用地址',
					  'vvl_playurlback_get'=>$tmp_playurl_back_get['msg'],//'视频备用地址(解析出来的)',
					  'vvl_playcount'=>intval($val2['vPlayCount']),//'视频播放次数(采集)',
					  'vvl_count'=>0,//'视频本地播放次数(本地记录)',
					  'vvl_sort_sys'=>intval($val2['number']),//系统默认排序
				       'vvl_video_id' => $tmp_playurl_get['vid'],
				       'vvl_upload_time'=> isset($val2['createDate'])?$val2['createDate']:'',//源网站上给予的该视频的上传时间，视频的上传时间
				 );
				$this->mod->save_hero_video($tmp_arr);
			}
		}
		
		return TRUE;
	}
	
	/**
	 * @name: save_info_2
	 * @description: 保存 2解说  资料的内容
	 * @param: $data = array(
		  0=>array(
		    'sourceType'=>1
		    'vAuthorImgUrl'=>"http://g2.ykimg.com/0130391F4854EE77F9B6B50028450D14AE808A-E186-5B5F-470F-B9CD113549F8"
		    'vAuthorName'=>"徐老师来巡山"
		    'vImgUrl'=>"http://g2.ykimg.com/1100641F46553B230D981B0028450D06FCC0C5-AE58-EDCC-218D-048C940D1681"
		    'vPlayCount'=>"13.2万"
		    'vRealPalyUrl'=>"http://v.youku.com/v_show/id_XOTQxMzk5NDA4.html"
		    'vRealPlayUrlBack'=>"http://v.youku.com/v_show/id_XOTQxMzk5NDA4.html"
		    'vTime'=>"10:41"
		    'vTitle'=>"徐老师来巡山 第12期："
		    'videoType'=>2,
		    'number'=>1,
		  ),
		 1=>array(
		    'sourceType'=>1
		    'vAuthorImgUrl'=>"http://g2.ykimg.com/0130391F4554A2CA0DD9E000E166AD8E4AF7F8-B7CC-C39C-18CC-4EDAB6307EBB"
		    'vAuthorName'=>"lol峰峰侠"
		    'vImgUrl'=>"http://g4.ykimg.com/1100641F465539CC57B76E00E166AD15F2BE9F-3B27-D639-0BBF-5F3CD9625F4C"
		    'vPlayCount'=>"2.1万"
		    'vRealPalyUrl'=>"http://v.youku.com/v_show/id_XOTQwNTU5MTA0.html"
		    'vRealPlayUrlBack'=>"http://v.youku.com/v_show/id_XOTQwNTU5MTA0.html"
		    'vTime'=>"05:14"
		    'vTitle'=>"全球十佳劫 极限TOP10"
		    'videoType'=>2,
		    'number'=>1,
		  )
	 * );
	 * @param:$game_id 默认等于 0
	 * @return: TRUE/FALSE
	 * @author: chengdongcai
	 * @create: 2015-04-28  18:47
	 **/
	private function save_info_2($data,$type=2,$game_id=0){
		//创建视频地址解析对像
		$video = new video_parser();
		
		//创建图片存放目录
		$date =  '/video_img' .date('/Y/m/d/');  //添加模块名作目录一部分
		$to_save = $this->config->item('image_root_path') . $date;
		create_my_file_path($to_save,0755);
		
		//判断作者是否存在
		$tmp_where = array(
				'va_name'=>$data['vAuthorName'],//'作者名称',
				'va_game_id'=>$game_id//游戏ID
		);
		$author_id = $this->mod->check_author_by_name($tmp_where);
		if($author_id==FALSE){//如果作者不存在，则添加
			//采集作者的icon
			$tmp_icon_get = '';//作者头像(本地上传)',
			if(!is_empty($data['vAuthorImgUrl'])){
				$tmp_icon_get = save_remote_image($data['vAuthorImgUrl'],$to_save);
				$tmp_icon_get = str_replace($this->config->item('image_root_path'), '', $tmp_icon_get);
			}
			$arr = array(
					'in_date'=>time(),//'采集日期',
					'va_name'=>$data['vAuthorName'],//'作者名称',
					'va_game_id'=>$game_id,//游戏ID
					'va_icon'=>$data['vAuthorImgUrl'],//'作者头像(采集)',
					'va_icon_get'=>$tmp_icon_get,//'作者头像(编辑)',
					'va_isshow'=>1,//'是否显示(1显示,2隐藏)',
					'va_intro'=>'',//'作者简介',
					'va_email'=>'',//'作者E-Mail',
					'va_order'=>0//'排序号',
				 );
			$author_id = $this->mod->save_author_info($arr);
		}
		//如果作者ID为空，则出错
		if( is_empty($author_id) ){
			return FALSE;
		}
		//判断是否已经存在英雄视频内容
		$tmp_arr = array(
				'hi_id'=>'',//'英雄ID',
				'game_id'=>$game_id,//游戏ID
				'category_id'=>'',//视频联赛ID(来自video_category_info表)
				'type_id'=>$type,//视频类型（1任务，2解说，3赛事战况，4集锦）',
				'author_id'=>$author_id,//'解说作者ID(来自video_author_info表)',
				'title'=>$data['vTitle'],//'视频标题',
				'number'=>intval($data['number']),//系统默认排序
		        'play_url'=>isset($data['vRealPalyUrl'])?$data['vRealPalyUrl']:'',//'视频标题',
				
		);
		//如果视频已经存在，则不插入当次的数据
		if( $this->mod->check_video_by_name( $tmp_arr )!=FALSE ){
			return TRUE;
		}
		
		$tmp_img = '';//播放图片
		$tmp_playurl_get = '';//优酷播放地址(解析出来的)',
		$tmp_playurl_back_get = '';//视频备用地址(解析出来的)'
		//下载播放图片
		if( !is_empty($data['vImgUrl']) ){
			$tmp_img = save_remote_image($data['vImgUrl'],$to_save);
			$tmp_img = str_replace($this->config->item('image_root_path'), '', $tmp_img);
		}
		if(!is_empty($data['vRealPalyUrl'])){
			$video->set_url($data['vRealPalyUrl']);
			$tmp_playurl_get = $video->parse();//优酷播放地址(解析出来的)',
			if( !is_empty($tmp_playurl_get) ){
				$tmp_playurl_get = json_decode($tmp_playurl_get,true);
				if($tmp_playurl_get['status']!=200){
					$tmp_playurl_get['msg']='';
					$tmp_playurl_get['type'] = intval($data['sourceType']);
				}else{
					$tmp_playurl_get['type']= $video->map_source_type($tmp_playurl_get['type']);
				}
			}else{
				$tmp_playurl_get['msg']='';
				$tmp_playurl_get['type'] = intval($data['sourceType']);
			}
		}else{
			$tmp_playurl_get['msg']='';
			$tmp_playurl_get['type'] = intval($data['sourceType']);
		}
		if(!is_empty($data['vRealPlayUrlBack'])){
			$video->set_url($data['vRealPlayUrlBack']);
			$tmp_playurl_back_get = $video->parse();;//视频备用地址(解析出来的)'
			if( !is_empty($tmp_playurl_back_get) ){
				$tmp_playurl_back_get = json_decode($tmp_playurl_back_get,true);
				if($tmp_playurl_back_get['status']!=200){
					$tmp_playurl_back_get['msg']='';
					$tmp_playurl_back_get['type'] = intval($data['sourceType']);
				}else{
					$tmp_playurl_back_get['type']= $video->map_source_type($tmp_playurl_back_get['type']);
				}
			}else{
				$tmp_playurl_back_get['msg']='';
				$tmp_playurl_back_get['type'] = intval($data['sourceType']);
			}
		}else{
			$tmp_playurl_back_get['msg']='';
			$tmp_playurl_back_get['type'] = intval($data['sourceType']);
		}
		$tmp_arr = array(
				'in_date'=>time(),//'采集日期',
				'vvl_hi_id'=>0,//'英雄ID(来自video_hero_info表)',
				'vvl_game_id'=>$game_id,//'游戏ID',
				'vvl_category_id'=>0,//'视频联赛ID(来自video_category_info表)',
				'vvl_type_id'=>$type,//'视频类型（1任务，2解说，3赛事战况，4集锦）',
				'vvl_sourcetype'=>$tmp_playurl_get['type'],//'视频来源（1优酷，2多玩）',
				'vvl_imgurl'=>$data['vImgUrl'],//'视频图片URL地址',
				'vvl_imgurl_get'=>$tmp_img,//'视频图片URL地址',
				'vvl_time'=>intval($data['vTime']),//'视频时长',
				'vvl_playurl'=>$data['vRealPalyUrl'],//'优酷播放地址，需要解析',
				'vvl_playurl_get'=>$tmp_playurl_get['msg'],//'优酷播放地址(解析出来的)',
				'vvl_author_id'=>$author_id,//'解说作者ID(来自video_author_info表)',
				'vvl_title'=>$data['vTitle'],//'视频标题',
				'vvl_playurlback'=>$data['vRealPlayUrlBack'],//'视频备用地址',
				'vvl_playurlback_get'=>$tmp_playurl_back_get['msg'],//'视频备用地址(解析出来的)',
				'vvl_playcount'=>intval($data['vPlayCount']),//'视频播放次数(采集)',
				'vvl_count'=>0,//'视频本地播放次数(本地记录)',
				'vvl_sort_sys'=>intval($data['number']),//系统默认排序
		        'vvl_video_id' => $tmp_playurl_get['vid'],
		        'vvl_upload_time'=> isset($data['createDate'])?$data['createDate']:'',//源网站上给予的该视频的上传时间，视频的上传时间
		);
		$this->mod->save_hero_video($tmp_arr,false);
		
		return TRUE;
	}
	
	/**
	 * @name: save_info_3
	 * @description: 保存 3赛事战况 资料的内容
	 * @param: $arr = array(
		  0=>array(
		    'categoryName'=>"LPL联赛"
		    'id'=>2
		    'kyxVideoList'=>array(
		      0=>array(
		        'sourceType'=>2
		        'vAuthorImgUrl'=>""
		        'vAuthorName'=>""
		        'vImgUrl'=>"http://img1.dwstatic.com/lol/1504/294002492234/1430047464077.jpg"
		        'vPlayCount'=>""
		        'vRealPalyUrl'=>"http://lol.duowan.com/1504/294002492234.html"
		        'vRealPlayUrlBack'=>""
		        'vTime'=>""
		        'vTitle'=>"2015LPL春季赛决赛 EDG vs LGD 五连发"
		        'videoType'=>3,
		        'number'=>1,
		      )
		      1=>array(
		        'sourceType'=>2
		        'vAuthorImgUrl'=>""
		        'vAuthorName'=>""
		        'vImgUrl'=>"http://img.dwstatic.com/lol/1504/293996277778/1430041396009.jpg"
		        'vPlayCount'=>""
		        'vRealPalyUrl'=>"http://lol.duowan.com/1504/293996277778.html"
		        'vRealPlayUrlBack'=>""
		        'vTime'=>""
		        'vTitle'=>"lpl明星对抗表演赛：龙队 VS 虎队二连发"
		        'videoType'=>3,
		        'number'=>1,
		      )
		  )
		 )
	  );
	  
	 * @param:$game_id 默认等于 0
	 * @return: TRUE/FASL
	 * @author: chengdongcai
	 * @create: 2015-04-29 09:42:50
	 **/
	private function save_info_3($data,$type=3,$game_id=0){
		//创建视频地址解析对像
		$video = new video_parser();
		
		//创建图片存放目录
		$date =  '/video_img' .date('/Y/m/d/');  //添加模块名作目录一部分
		$to_save = $this->config->item('image_root_path') . $date;
		create_my_file_path($to_save,0755);
		
		//判断视频类别是否存在
		$tmp_where = array(
				'vc_name'=>$data['categoryName'],//'视频类别名称',
				'vc_type_id'=>$type,//类别标记(1任务，2解说，3赛事战况，4集锦)
				'vc_game_id'=>$game_id//'游戏ID',
		);
		$category_id = $this->mod->check_category_by_name($tmp_where);
		if($category_id==FALSE){//如果 视频类别 不存在，则添加
			$arr = array(
				  'in_date'=>time(),//'采集日期',
				  'vc_type_id'=>$type,//'类别标记(1任务，2解说，3赛事战况，4集锦)',
				  'vc_game_id'=>$game_id,//'游戏ID',
				  'vc_name'=>$data['categoryName'],//'类别名称',
				  'vc_intro'=>'',//'类别简介',
				  'vc_isshow'=>1,//'是否显示(1显示,2隐藏)',
				  'vc_order'=>0,//'排序号',
			 );
			$category_id = $this->mod->save_category_info($arr);	
		}
		//如果 视频类别ID 为空，则出错
		if( is_empty($category_id) ){
			return FALSE;
		}
		
		//判断作者是否存在
		$author_id = 0;
		if( isset($data['vAuthorName']) && !is_empty($data['vAuthorName']) ){
			$tmp_where = array(
					'va_name'=>$data['vAuthorName'],//'作者名称',
					'va_game_id'=>$game_id//'游戏ID',
			);
			$author_id = $this->mod->check_author_by_name($tmp_where);
			if($author_id==FALSE){//如果作者不存在，则添加
				//采集作者的icon
				$tmp_icon_get = '';//作者头像(本地上传)',
				if(!is_empty($data['vAuthorImgUrl'])){
					$tmp_icon_get = save_remote_image($data['vAuthorImgUrl'],$to_save);
					$tmp_icon_get = str_replace($this->config->item('image_root_path'), '', $tmp_icon_get);
				}
				$arr = array(
						'in_date'=>time(),//'采集日期',
						'va_game_id'=>$game_id,//'游戏ID',
						'va_name'=>$data['vAuthorName'],//'作者名称',
						'va_icon'=>$data['vAuthorImgUrl'],//'作者头像(采集)',
						'va_icon_get'=>$tmp_icon_get,//'作者头像(编辑)',
						'va_isshow'=>1,//'是否显示(1显示,2隐藏)',
						'va_intro'=>'',//'作者简介',
						'va_email'=>'',//'作者E-Mail',
						'va_order'=>0//'排序号',
				);
				$author_id = $this->mod->save_author_info($arr);
			}
		}

		//添加游戏视频
		if( !is_empty($data['kyxVideoList']) ){
			foreach ($data['kyxVideoList'] as $val2){
				//判断是否已经存在英雄视频内容
				$tmp_arr = array(
						'hi_id'=>'',//'英雄ID',
						'game_id'=>$game_id,//'游戏ID',
						'category_id'=>$category_id,//视频联赛ID(来自video_category_info表)
						'type_id'=>$type,//视频类型（1任务，2解说，3赛事战况，4集锦）',
						'author_id'=>$author_id,//'解说作者ID(来自video_author_info表)',
						'title'=>$val2['vTitle'],//'视频标题',
						'number'=>intval($val2['number']),//系统默认排序
				);
				//如果视频已经存在，则不插入当次的数据
				if( $this->mod->check_video_by_name( $tmp_arr )!=FALSE ){
					continue;
				}
			
				$tmp_img = '';//播放图片
				$tmp_playurl_get = '';//优酷播放地址(解析出来的)',
				$tmp_playurl_back_get = '';//视频备用地址(解析出来的)'
				//下载播放图片
				if( !is_empty($val2['vImgUrl']) ){
					$tmp_img = save_remote_image($val2['vImgUrl'],$to_save);
					$tmp_img = str_replace($this->config->item('image_root_path'), '', $tmp_img);
				}
				if(!is_empty($val2['vRealPalyUrl'])){
					$video->set_url($val2['vRealPalyUrl']);
					$tmp_playurl_get = $video->parse();//播放地址(解析出来的)',
					if( !is_empty($tmp_playurl_get) ){
						$tmp_playurl_get = json_decode($tmp_playurl_get,true);
						if($tmp_playurl_get['status']!=200){
							$tmp_playurl_get['msg']='';
							$tmp_playurl_get['type'] = intval($val2['sourceType']);
						}else{
							$tmp_playurl_get['type']= $video->map_source_type($tmp_playurl_get['type']);
						}
					}else{
						$tmp_playurl_get['msg']='';
						$tmp_playurl_get['type'] = intval($val2['sourceType']);
					}
				}else{
					$tmp_playurl_get['msg']='';
					$tmp_playurl_get['type'] = intval($val2['sourceType']);
				}
				if(!is_empty($val2['vRealPlayUrlBack'])){
					$video->set_url($val2['vRealPlayUrlBack']);
					$tmp_playurl_back_get = $video->parse();//视频备用地址(解析出来的)'
					if( !is_empty($tmp_playurl_back_get) ){
						$tmp_playurl_back_get = json_decode($tmp_playurl_back_get,true);
						if($tmp_playurl_back_get['status']!=200){
							$tmp_playurl_back_get['msg']='';
							$tmp_playurl_back_get['type'] = intval($val2['sourceType']);
						}else{
							$tmp_playurl_back_get['type']= $video->map_source_type($tmp_playurl_back_get['type']);
						}
					}else{
						$tmp_playurl_back_get['msg']='';
						$tmp_playurl_back_get['type'] = intval($val2['sourceType']);
					}
				}else{
					$tmp_playurl_back_get['msg']='';
					$tmp_playurl_back_get['type'] = intval($val2['sourceType']);
				}
				$tmp_arr = array(
						'in_date'=>time(),//'采集日期',
						'vvl_hi_id'=>0,//'英雄ID(来自video_hero_info表)',
						'vvl_game_id'=>$game_id,//'游戏ID',
						'vvl_category_id'=>$category_id,//'视频联赛ID(来自video_category_info表)',
						'vvl_type_id'=>$type,//'视频类型（1任务，2解说，3赛事战况，4集锦）',
						'vvl_sourcetype'=>$tmp_playurl_get['type'],//'视频来源（1优酷，2多玩）',
						'vvl_imgurl'=>$val2['vImgUrl'],//'视频图片URL地址',
						'vvl_imgurl_get'=>$tmp_img,//'视频图片URL地址',
						'vvl_time'=>intval($val2['vTime']),//'视频时长',
						'vvl_playurl'=>$val2['vRealPalyUrl'],//'优酷播放地址，需要解析',
						'vvl_playurl_get'=>$tmp_playurl_get['msg'],//'优酷播放地址(解析出来的)',
						'vvl_author_id'=>$author_id,//'解说作者ID(来自video_author_info表)',
						'vvl_title'=>$val2['vTitle'],//'视频标题',
						'vvl_playurlback'=>$val2['vRealPlayUrlBack'],//'视频备用地址',
						'vvl_playurlback_get'=>$tmp_playurl_back_get['msg'],//'视频备用地址(解析出来的)',
						'vvl_playcount'=>intval($val2['vPlayCount']),//'视频播放次数(采集)',
						'vvl_count'=>0,//'视频本地播放次数(本地记录)',
						'vvl_sort_sys'=>intval($val2['number']),//系统默认排序
				        'vvl_video_id' => $tmp_playurl_get['vid'],
				        'vvl_upload_time'=> isset($val2['createDate'])?$val2['createDate']:'',//源网站上给予的该视频的上传时间，视频的上传时间
				);
				$this->mod->save_hero_video($tmp_arr,false);
			}
		}
		return TRUE;
	}
	/**
	* @name: save_info_4
	* @description: 保存 4集锦 资料的内容
	* @param: $arr = array(
		  0=>array(
		    'categoryName'=>"囧镜头"
		    'id'=>2
		    'kyxVideoList'=>array(
		      0=>array(
		        'sourceType'=>2
		        'vAuthorImgUrl'=>""
		        'vAuthorName'=>""
		        'vImgUrl'=>"http://img.dwstatic.com/lol/1504/292607599800/1428652893900.jpg"
		        'vPlayCount'=>""
		        'vRealPalyUrl'=>"http://lol.duowan.com/1504/292607599800.html"
		        'vRealPlayUrlBack'=>""
		        'vTime'=>""
		        'vTitle'=>"青铜时刻：狼人超假演技送人头 已举报！"
		        'videoType'=>4,
		        'number'=>1,
		      )
		      1=>array(
		        'sourceType'=>2
		        'vAuthorImgUrl'=>""
		        'vAuthorName'=>""
		        'vImgUrl'=>"http://img.dwstatic.com/lol/1504/292520290619/1428565336682.jpg"
		        'vPlayCount'=>""
		        'vRealPalyUrl'=>"http://lol.duowan.com/1504/292520290619.html"
		        'vRealPlayUrlBack'=>""
		        'vTime'=>""
		        'vTitle'=>"囧镜头：“实力”魔力瞎 无人空门打飞机"
		        'videoType'=>4,
		        'number'=>1,
		      )
		  )
		 )
	  );	
	* @param:$game_id 默认等于 0
	* @return: TRUE/FASL
	* @author: chengdongcai
	* @create: 2015-04-29 10:36:50
	**/
	private function save_info_4($data,$type=4,$game_id=0){
		//创建视频地址解析对像
		$video = new video_parser();
	
		//创建图片存放目录
		$date =  '/video_img' .date('/Y/m/d/');  //添加模块名作目录一部分
		$to_save = $this->config->item('image_root_path') . $date;
		create_my_file_path($to_save,0755);
	
		//判断视频类别是否存在
		$tmp_where = array(
				'vc_name'=>$data['categoryName'],//'视频类别名称',
				'vc_type_id'=>$type,//类别标记(1任务，2解说，3赛事战况，4集锦)
				'vc_game_id'=>$game_id//'游戏ID',
		);
		$category_id = $this->mod->check_category_by_name($tmp_where);
		if($category_id==FALSE){//如果 视频类别 不存在，则添加
			$arr = array(
					'in_date'=>time(),//'采集日期',
					'vc_type_id'=>$type,//'类别标记(1任务，2解说，3赛事战况，4集锦)',
					'vc_game_id'=>$game_id,//'游戏ID',
					'vc_name'=>$data['categoryName'],//'类别名称',
					'vc_intro'=>'',//'类别简介',
					'vc_isshow'=>1,//'是否显示(1显示,2隐藏)',
					'vc_order'=>0,//'排序号',
			);
			$category_id = $this->mod->save_category_info($arr);
		}
		//如果 视频类别ID 为空，则出错
		if( is_empty($category_id) ){
			return FALSE;
		}
		
		//判断作者是否存在
		$author_id = 0;
		if( isset($data['vAuthorName']) && !is_empty($data['vAuthorName'])){
			$tmp_where = array(
					'va_name'=>$data['vAuthorName'],//'作者名称',
					'va_game_id'=>$game_id//'游戏ID',
			);
			$author_id = $this->mod->check_author_by_name($tmp_where);
			if($author_id==FALSE){//如果作者不存在，则添加
				//采集作者的icon
				$tmp_icon_get = '';//作者头像(本地上传)',
				if(!is_empty($data['vAuthorImgUrl'])){
				    $data['vAuthorImgUrl'] = get_redirect_url($data['vAuthorImgUrl']);
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
						'va_order'=>0//'排序号',
				);
				$author_id = $this->mod->save_author_info($arr);
			}
		}
		//添加游戏视频
		if( !is_empty($data['kyxVideoList']) ){
			foreach ($data['kyxVideoList'] as $val2){
				//判断是否已经存在英雄视频内容
				$tmp_arr = array(
						'hi_id'=>'',//'英雄ID',
						'game_id'=>$game_id,//'游戏ID',
						'category_id'=>$category_id,//视频联赛ID(来自video_category_info表)
						'type_id'=>$type,//视频类型（1任务，2解说，3赛事战况，4集锦）',
						'author_id'=>$author_id,//'解说作者ID(来自video_author_info表)',
						'title'=>$val2['vTitle'],//'视频标题',
						'number'=>intval($val2['number']),//系统默认排序,
						'play_url' =>$val2['vRealPalyUrl'],
				);
				//如果视频已经存在，则不插入当次的数据
				if( $this->mod->check_video_by_name( $tmp_arr )!=FALSE ){
					continue;
				}
					
				$tmp_img = '';//播放图片
				$tmp_playurl_get = '';//优酷播放地址(解析出来的)',
				$tmp_playurl_back_get = '';//视频备用地址(解析出来的)'
				//下载播放图片
				if( !is_empty($val2['vImgUrl']) ){
				    $val2['vImgUrl'] = get_redirect_url($val2['vImgUrl']);
					$tmp_img = save_remote_image($val2['vImgUrl'],$to_save);
					$tmp_img = str_replace($this->config->item('image_root_path'), '', $tmp_img);
				}
				if(!is_empty($val2['vRealPalyUrl'])){
					$video->set_url($val2['vRealPalyUrl']);
					$tmp_playurl_get = $video->parse();//播放地址(解析出来的)',
					if( !is_empty($tmp_playurl_get) ){
						$tmp_playurl_get = json_decode($tmp_playurl_get,true);
						if($tmp_playurl_get['status']!=200){
							$tmp_playurl_get['msg']='';
							$tmp_playurl_get['type'] = intval($val2['sourceType']);
						}else{
							$tmp_playurl_get['type']= $video->map_source_type($tmp_playurl_get['type']);
						}
					}else{
						$tmp_playurl_get['msg']='';
						$tmp_playurl_get['type'] = intval($val2['sourceType']);
					}
				}else{
					$tmp_playurl_get['msg']='';
					$tmp_playurl_get['type'] = intval($val2['sourceType']);
				}
				if(!is_empty($val2['vRealPlayUrlBack'])){
					$video->set_url($val2['vRealPlayUrlBack']);
					$tmp_playurl_back_get = $video->parse();//视频备用地址(解析出来的)'
					if( !is_empty($tmp_playurl_back_get) ){
						$tmp_playurl_back_get = json_decode($tmp_playurl_back_get,true);
						if($tmp_playurl_back_get['status']!=200){
							$tmp_playurl_back_get['msg']='';
							$tmp_playurl_back_get['type'] = intval($val2['sourceType']);
						}else{
							$tmp_playurl_back_get['type']= $video->map_source_type($tmp_playurl_back_get['type']);
						}
					}else{
						$tmp_playurl_back_get['msg']='';
						$tmp_playurl_back_get['type'] = intval($val2['sourceType']);
					}
				}else{
					$tmp_playurl_back_get['msg']='';
					$tmp_playurl_back_get['type'] = intval($val2['sourceType']);
				}
				$tmp_arr = array(
						'in_date'=>time(),//'采集日期',
						'vvl_hi_id'=>0,//'英雄ID(来自video_hero_info表)',
						'vvl_game_id'=>$game_id,//'游戏ID',
						'vvl_category_id'=>$category_id,//'视频联赛ID(来自video_category_info表)',
						'vvl_type_id'=>$type,//'视频类型（1任务，2解说，3赛事战况，4集锦）',
						'vvl_sourcetype'=>$tmp_playurl_get['type'],//'视频来源（1优酷，2多玩）',
						'vvl_imgurl'=>$val2['vImgUrl'],//'视频图片URL地址',
						'vvl_imgurl_get'=>$tmp_img,//'视频图片URL地址',
						'vvl_time'=>intval($val2['vTime']),//'视频时长',
						'vvl_playurl'=>$val2['vRealPalyUrl'],//'优酷播放地址，需要解析',
						'vvl_playurl_get'=>$tmp_playurl_get['msg'],//'优酷播放地址(解析出来的)',
						'vvl_author_id'=>$author_id,//'解说作者ID(来自video_author_info表)',
						'vvl_title'=>$val2['vTitle'],//'视频标题',
						'vvl_playurlback'=>$val2['vRealPlayUrlBack'],//'视频备用地址',
						'vvl_playurlback_get'=>$tmp_playurl_back_get['msg'],//'视频备用地址(解析出来的)',
						'vvl_playcount'=>intval($val2['vPlayCount']),//'视频播放次数(采集)',
						'vvl_count'=>0,//'视频本地播放次数(本地记录)',
						'vvl_sort_sys'=>intval($val2['number']),//系统默认排序
				        'vvl_video_id' => isset($tmp_playurl_get['vid'])?$tmp_playurl_get['vid']:NULL,
				        'vvl_upload_time'=> isset($val2['createDate'])?$val2['createDate']:'',//源网站上给予的该视频的上传时间，视频的上传时间
				);
				$this->mod->save_hero_video($tmp_arr,false);
			}
		}
		return TRUE;
	}
	/**
	 * @name: save_info_5
	 * @description: 保存 5职业  资料的内容
	 * @param: $arr = array(
	 0=>array(
	 'categoryName'=>"德鲁伊"
	 'id'=>2
	 'kyxVideoList'=>array(
	 0=>array(
	 'sourceType'=>2
	 'vAuthorImgUrl'=>""
	 'vAuthorName'=>""
	 'vImgUrl'=>"http://img.dwstatic.com/lol/1504/292607599800/1428652893900.jpg"
	 'vPlayCount'=>""
	 'vRealPalyUrl'=>"http://lol.duowan.com/1504/292607599800.html"
	 'vRealPlayUrlBack'=>""
	 'vTime'=>""
	 'vTitle'=>"青铜时刻：狼人超假演技送人头 已举报！"
	 'videoType'=>4,
	 'number'=>1,
	 "videoTypeSecond": "竞技场视频"
	 )
	 1=>array(
	 'sourceType'=>2
	 'vAuthorImgUrl'=>""
	 'vAuthorName'=>""
	 'vImgUrl'=>"http://img.dwstatic.com/lol/1504/292520290619/1428565336682.jpg"
	 'vPlayCount'=>""
	 'vRealPalyUrl'=>"http://lol.duowan.com/1504/292520290619.html"
	 'vRealPlayUrlBack'=>""
	 'vTime'=>""
	 'vTitle'=>"囧镜头：“实力”魔力瞎 无人空门打飞机"
	 'videoType'=>4,
	 'number'=>1,
	 "videoTypeSecond": "竞技场视频"
	 )
	 )
	 )
	 );
	 * @param:$game_id 默认等于 0
	 * @return: TRUE/FASL
	 * @author: chengdongcai
	 * @create: 2015-05-15 10:36:50
	 **/
	private function save_info_5($data,$type=5,$game_id=0){
		//创建视频地址解析对像
		$video = new video_parser();
	
		//创建图片存放目录
		$date =  '/video_img' .date('/Y/m/d/');  //添加模块名作目录一部分
		$to_save = $this->config->item('image_root_path') . $date;
		create_my_file_path($to_save,0755);
	
		//判断视频类别是否存在
		$tmp_where = array(
				'vc_name'=>$data['categoryName'],//'视频类别名称',
				'vc_type_id'=>$type,//类别标记(1任务，2解说，3赛事战况，4集锦，5职业)
				'vc_game_id'=>$game_id//'游戏ID',
		);
		$category_id = $this->mod->check_category_by_name($tmp_where);
		if($category_id==FALSE){//如果 视频类别 不存在，则添加
			$arr = array(
					'in_date'=>time(),//'采集日期',
					'vc_type_id'=>$type,//'类别标记(1任务，2解说，3赛事战况，4集锦，5职业)',
					'vc_game_id'=>$game_id,//'游戏ID',
					'vc_name'=>$data['categoryName'],//'类别名称',
					'vc_intro'=>'',//'类别简介',
					'vc_isshow'=>1,//'是否显示(1显示,2隐藏)',
					'vc_order'=>0,//'排序号',
			);
			$category_id = $this->mod->save_category_info($arr);
		}
		//如果 视频类别ID 为空，则出错
		if( is_empty($category_id) ){
			return FALSE;
		}
	
		//判断作者是否存在
		$author_id = 0;
		if( isset($data['vAuthorName']) && !is_empty($data['vAuthorName'])){
			$tmp_where = array(
					'va_name'=>$data['vAuthorName'],//'作者名称',
					'va_game_id'=>$game_id//'游戏ID',
			);
			$author_id = $this->mod->check_author_by_name($tmp_where);
			if($author_id==FALSE){//如果作者不存在，则添加
				//采集作者的icon
				$tmp_icon_get = '';//作者头像(本地上传)',
				if(!is_empty($data['vAuthorImgUrl'])){
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
						'va_order'=>0//'排序号',
				);
				$author_id = $this->mod->save_author_info($arr);
			}
		}
		//添加游戏视频
		if( !is_empty($data['kyxVideoList']) ){
			foreach ($data['kyxVideoList'] as $val2){
				//判断是否已经存在英雄视频内容
				$tmp_arr = array(
						'hi_id'=>'',//'英雄ID',
						'game_id'=>$game_id,//'游戏ID',
						'category_id'=>$category_id,//视频联赛ID(来自video_category_info表)
						'type_id'=>$type,//视频类型（1任务，2解说，3赛事战况，4集锦）',
						'author_id'=>$author_id,//'解说作者ID(来自video_author_info表)',
						'title'=>$val2['vTitle'],//'视频标题',
						'number'=>intval($val2['number']),//系统默认排序
				);
				//如果视频已经存在，则不插入当次的数据
				if( $this->mod->check_video_by_name( $tmp_arr )!=FALSE ){
					continue;
				}
					
				$tmp_img = '';//播放图片
				$tmp_playurl_get = '';//优酷播放地址(解析出来的)',
				$tmp_playurl_back_get = '';//视频备用地址(解析出来的)'
				//下载播放图片
				if( !is_empty($val2['vImgUrl']) ){
					$tmp_img = save_remote_image($val2['vImgUrl'],$to_save);
					$tmp_img = str_replace($this->config->item('image_root_path'), '', $tmp_img);
				}
				if(!is_empty($val2['vRealPalyUrl'])){
					$video->set_url($val2['vRealPalyUrl']);
					$tmp_playurl_get = $video->parse();//播放地址(解析出来的)',
					if( !is_empty($tmp_playurl_get) ){
						$tmp_playurl_get = json_decode($tmp_playurl_get,true);
						if($tmp_playurl_get['status']!=200){
							$tmp_playurl_get['msg']='';
							$tmp_playurl_get['type'] = intval($val2['sourceType']);
						}else{
							$tmp_playurl_get['type']= $video->map_source_type($tmp_playurl_get['type']);
						}
					}else{
						$tmp_playurl_get['msg']='';
						$tmp_playurl_get['type'] = intval($val2['sourceType']);
					}
				}else{
					$tmp_playurl_get['msg']='';
					$tmp_playurl_get['type'] = intval($val2['sourceType']);
				}
				$tmp_arr = array(
						'in_date'=>time(),//'采集日期',
						'vvl_hi_id'=>0,//'英雄ID(来自video_hero_info表)',
						'vvl_game_id'=>$game_id,//'游戏ID',
						'vvl_category_id'=>$category_id,//'视频联赛ID(来自video_category_info表)',
						'vvl_type_id'=>$type,//'视频类型（1任务，2解说，3赛事战况，4集锦）',
						'vvl_sourcetype'=>$tmp_playurl_get['type'],//'视频来源（1优酷，2多玩）',
						'vvl_imgurl'=>$val2['vImgUrl'],//'视频图片URL地址',
						'vvl_imgurl_get'=>$tmp_img,//'视频图片URL地址',
						'vvl_time'=>intval($val2['vTime']),//'视频时长',
						'vvl_playurl'=>$val2['vRealPalyUrl'],//'优酷播放地址，需要解析',
						'vvl_playurl_get'=>$tmp_playurl_get['msg'],//'优酷播放地址(解析出来的)',
						'vvl_author_id'=>$author_id,//'解说作者ID(来自video_author_info表)',
						'vvl_title'=>$val2['vTitle'],//'视频标题',
						'vvl_playurlback'=>$val2['videoTypeSecond'],//'视频备用地址',
						'vvl_playurlback_get'=>'',//'视频备用地址(解析出来的)',
						'vvl_playcount'=>intval($val2['vPlayCount']),//'视频播放次数(采集)',
						'vvl_count'=>0,//'视频本地播放次数(本地记录)',
						'vvl_sort_sys'=>intval($val2['number']),//系统默认排序
				        'vvl_video_id' => $tmp_playurl_get['vid'],
				        'vvl_upload_time'=> isset($val2['createDate'])?$val2['createDate']:'',//源网站上给予的该视频的上传时间，视频的上传时间
				);
				$this->mod->save_hero_video($tmp_arr,false);
			}
		}
		return TRUE;
	}
	
	/**
	 * @name: save_info_6
	 * @description: 保存 6作者解说  资料的内容
	@param:$data =array(
    'aName'=>"大猫",//作者名
    'aIcon'=>"www.xxxx.....jpg",//作者头像
    'aF_Url'=>"",//不会用到
    'aS_url'=>"",//不会用到
    'listSeries'=>array(//专辑列表
   	   0=>array(
    	'sName'=>"大猫系列"//专辑名
    	'sImg'=>"www......jpg"//专辑图片
    	'sCount'=>"123"//专辑视频数量
    	'sPlayCount'=>"1231,221"//专辑播放次数
    	'sUrl'=>"www.sfsdf.com"//不会用到
    	'number'=>"1"//排序序号，序号越大越新
    	'listVideos'=>array(//视频列表
    		0=>array(
    		'sourceType'=>2
	        'vAuthorImgUrl'=>""
	        'vAuthorName'=>""
	        'vImgUrl'=>"http://img1.dwstatic.com/lol/1504/293843776829/1429889086200.jpg"
	        'vPlayCount'=>""
	        'vRealPalyUrl'=>"http://lol.duowan.com/1504/293843776829.html"
	        'vRealPlayUrlBack'=>""
	        'vTime'=>""
	        'vTitle'=>"夜魔解说：钻石菊花信 逆风翻盘详解！"
	        'videoType'=>1,
	        'number'=>1,
	      	),
    	),
    );
	 * @param:$game_id 默认等于 0
	 * @return: TRUE/FASL
	 * @author: chengdongcai
	 * @create: 2015-05-20 10:36:50
	 **/
	private function save_info_6($data,$type=6,$game_id=0){
		//创建视频地址解析对像
		$video = new video_parser();
	
		//创建图片存放目录
		$date =  '/video_img' .date('/Y/m/d/');  //添加模块名作目录一部分
		$to_save = $this->config->item('image_root_path') . $date;
		create_my_file_path($to_save,0755);

		
		//判断作者是否存在
		$author_id = 0;
		if( isset($data['aName']) && !is_empty($data['aName'])){
			$tmp_where = array(
					'va_name'=>$data['aName'],//'作者名称',
					'va_game_id'=>$game_id//'游戏ID',
			);
			$author_id = $this->mod->check_author_by_name($tmp_where);
			if($author_id==FALSE){//如果作者不存在，则添加
				//采集作者的icon
				$tmp_icon_get = '';//作者头像(本地上传)',
				if(!is_empty($data['aIcon'])){
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
				$author_id = $this->mod->save_author_info($arr);
			}
		}
		//如果解说专辑不存在，则跳出当次执行
		if(!isset($data['listSeries']) || is_empty($data['listSeries']) || is_empty($author_id) ){
			return false;
		}
		//循环插入视频
		foreach ($data['listSeries'] as $data2){

			//判断视频类别是否存在
			$tmp_where = array(
					'vc_name'=>$data2['sName'],//'视频类别名称',
					'vc_type_id'=>$type,//类别标记(1任务，2解说，3赛事战况，4集锦，5职业)
					'vc_game_id'=>$game_id,//'游戏ID',
					'vc_author_id'=>$author_id//解说作者ID
			);
			$category_id = $this->mod->check_category_by_name($tmp_where);
			if($category_id==FALSE){//如果 视频类别 不存在，则添加
				//采集视频类别的icon
				$tmp_icon_get = '';//视频类别(本地上传)',
				if(!is_empty($data2['sImg'])){
					$tmp_icon_get = save_remote_image($data2['sImg'],$to_save);
					$tmp_icon_get = str_replace($this->config->item('image_root_path'), '', $tmp_icon_get);
				}
				$arr = array(
						'in_date'=>time(),//'采集日期',
						'vc_type_id'=>$type,//'类别标记(1任务，2解说，3赛事战况，4集锦，5职业)',
						'vc_game_id'=>$game_id,//'游戏ID',
						'vc_author_id'=>$author_id,//解说作者ID(来自video_author_info表)'
						'vc_name'=>$data2['sName'],//'类别名称',
						'vc_intro'=>'',//'类别简介',
						'vc_isshow'=>1,//'是否显示(1显示,2隐藏)',
						'vc_order'=>intval($data2['number']),//'排序号',
						'vc_icon'=>$data2['sImg'],//分类图标(采集)
						'vc_icon_get'=>$tmp_icon_get,//分类图标(编辑)
						'vc_scount'=>intval($data2['sCount']),//专辑视频数量
						'vc_splaycount'=>intval($data2['sPlayCount']),//专辑播放次数
						'vc_playcount'=>0//本地播放次数
						
				);
				$category_id = $this->mod->save_category_info($arr);
			}
			//如果 视频类别ID 为空，则跳过当次执行
			if( is_empty($category_id) ){
				continue;
			}
			
			//添加游戏视频
			if( !is_empty($data2['listVideos']) ){
				foreach ($data2['listVideos'] as $val2){
					//判断是否已经存在英雄视频内容
					$tmp_arr = array(
							'hi_id'=>'',//'英雄ID',
							'game_id'=>$game_id,//'游戏ID',
							'category_id'=>$category_id,//视频联赛ID(来自video_category_info表)
							'type_id'=>$type,//视频类型（1任务，2解说，3赛事战况，4集锦）',
							'author_id'=>$author_id,//'解说作者ID(来自video_author_info表)',
							'title'=>$val2['vTitle']//'视频标题'
					);
					//如果视频已经存在，则不插入当次的数据
					if( $this->mod->check_video_by_name( $tmp_arr )!=FALSE ){
						continue;
					}
						
					$tmp_img = '';//播放图片
					$tmp_playurl_get = '';//优酷播放地址(解析出来的)',
					//下载播放图片
					if( !is_empty($val2['vImgUrl']) ){
						$tmp_img = save_remote_image($val2['vImgUrl'],$to_save);
						$tmp_img = str_replace($this->config->item('image_root_path'), '', $tmp_img);
					}
					if(!is_empty($val2['vRealPalyUrl'])){
						$video->set_url($val2['vRealPalyUrl']);
						$tmp_playurl_get = $video->parse();//播放地址(解析出来的)',
						if( !is_empty($tmp_playurl_get) ){
							$tmp_playurl_get = json_decode($tmp_playurl_get,true);
							if($tmp_playurl_get['status']!=200){
								$tmp_playurl_get['msg']='';
								$tmp_playurl_get['type'] = intval($val2['sourceType']);
							}else{
								$tmp_playurl_get['type']= $video->map_source_type($tmp_playurl_get['type']);
							}
						}else{
							$tmp_playurl_get['msg']='';
							$tmp_playurl_get['type'] = intval($val2['sourceType']);
						}
					}else{
						$tmp_playurl_get['msg']='';
						$tmp_playurl_get['type'] = intval($val2['sourceType']);
					}
					$tmp_arr = array(
							'in_date'=>time(),//'采集日期',
							'vvl_hi_id'=>0,//'英雄ID(来自video_hero_info表)',
							'vvl_game_id'=>$game_id,//'游戏ID',
							'vvl_category_id'=>$category_id,//'视频联赛ID(来自video_category_info表)',
							'vvl_type_id'=>$type,//'视频类型（1任务，2解说，3赛事战况，4集锦）',
							'vvl_sourcetype'=>$tmp_playurl_get['type'],//'视频来源（1优酷，2多玩）',
							'vvl_imgurl'=>$val2['vImgUrl'],//'视频图片URL地址',
							'vvl_imgurl_get'=>$tmp_img,//'视频图片URL地址',
							'vvl_time'=>intval($val2['vTime']),//'视频时长',
							'vvl_playurl'=>$val2['vRealPalyUrl'],//'优酷播放地址，需要解析',
							'vvl_playurl_get'=>$tmp_playurl_get['msg'],//'优酷播放地址(解析出来的)',
							'vvl_author_id'=>$author_id,//'解说作者ID(来自video_author_info表)',
							'vvl_title'=>$val2['vTitle'],//'视频标题',
							'vvl_playurlback'=>$val2['vRealPlayUrlBack'],//'视频备用地址',
							'vvl_playurlback_get'=>'',//'视频备用地址(解析出来的)',
							'vvl_playcount'=>intval($val2['vPlayCount']),//'视频播放次数(采集)',
							'vvl_count'=>0,//'视频本地播放次数(本地记录)',
							'vvl_sort_sys'=>intval($val2['number']),//系统默认排序
					        'vvl_video_id' => $tmp_playurl_get['vid'],
					        'vvl_upload_time'=> isset($val2['createDate'])?$val2['createDate']:'',//源网站上给予的该视频的上传时间，视频的上传时间
					        
					);
					$this->mod->save_hero_video($tmp_arr,false);
				}
			}
		}
		return TRUE;
	}
	
	/**
	 * @name: get_normol_json
	 * @description: 根据传递参数返回json对象数据
	 * @param: 待转换的原始数组 | 前端datatables所需的sEcho参数值 | 查询总记录数
	 * @return: String json 返回的json字串
	 * @author: Chen Fei
	 * @create: 2014-09-23 21:26
	 **/
	private function get_normol_json( $data_array = null, $sEcho, $nums ) {
	
		foreach( $data_array as &$val ){ //为了兼容datatables 额外加入了这两个参数key
			$val['select_name_cf'] = '';
			$val['do_name_cf'] = '';
			$val['iTotalRecords'] = $nums;
			$val['iTotalDisplayRecords'] = $nums;
		}
	
		$tmp_arr = array(
				"sEcho"   				=> $sEcho,
				"iTotalRecords"   		=> $nums,
				"iTotalDisplayRecords"  => $nums,
				"aaData"  				=> $data_array
		);
	
		return json_encode($tmp_arr);
	}
	
	public function mytest(){
		//创建视频地址解析对像
		$video = new video_parser();
		$video->set_url('http://coc.ptbus.com/457662/');
		$tmp_playurl_back_get = $video->parse();//视频备用地址(解析出来的)'
		$tmp_playurl_back_get = json_decode($tmp_playurl_back_get,true);
		var_dump($tmp_playurl_back_get);
	}

    /**
     * @name: hero_list
     * @description: 英雄列表显示
     * @param: 无
     * @return: 无
     * @author: Chen Zhong
     * @create: 2015-04-28 11:58:50
     **/
    public function hero_list(){
        if( !$this->check_right( '140003' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }

        $class_arr = array(
            1 => '近战',
            2 => '远程',
            3 => '物理',
            4 => '法术',
            5 => '坦克',
            6 => '辅助',
            7 => '打野',
            8 => '突进',
            9 => '男性',
            10 => '女性'
        );

        //关联游戏信息数组
        $game_arr = $this->video_model->get_relev_game_arr();

        $data = array(
            'js' =>array(
                'App'=>'admin/scripts/app.js', //需要引入app.js
                'Index'=>'admin/scripts/index.js', //需要引入index.js
                'FormValidation'=>'admin/scripts/muzhiwan.js/hero-manage.js'
            )
        );
        $data['class_arr'] = $class_arr;
        $data['game_arr'] = $game_arr;
        $data['h_id'] = intval(get_var_value('h_id')); //英雄id

        $this->display( $data, 'hero_list' );
    }


    /**
     * @name: ajax_get_hero_data
     * @description: ajax获取英雄信息
     * @param:
     * @return: 无
     * @author: Chen Zhong
     * @create: 2015-04-28  12:28
     **/
    public function ajax_get_hero_data() {

        if( !$this->check_right( '140003' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }

        $start_record = get_var_value( 'iDisplayStart' ); 	//从多少条开始查询
        $page_size	  = get_var_value( 'iDisplayLength' ); 	//每页显示多少条记录
        $s_echo		  = get_var_value( 'sEcho' );
        $is_hide = intval(get_var_value('is_hide')); //显示状态
        $hi_name = get_var_value('hi_name'); //英雄名称
        $hi_class = rtrim(get_var_value('hi_class'),','); //战场职责
        $hi_tag = get_var_value('hi_tag'); //英雄标签
        $h_id = intval(get_var_value('h_id')); //英雄id
        $bel_game = intval(get_var_value('bel_game')); //所属游戏
        $img_ext = get_var_value('img_ext'); //英雄头像后缀
        $conditions   = ''; //查询条件

        if(!empty($is_hide)){
            $conditions .= " AND `hi_isshow` = ".$is_hide;
        }
        if(!empty($h_id)){
            $conditions .= " AND `id` = ".$h_id;
        }
        if(!empty($bel_game)){
            $conditions .= " AND `hi_game_id` = ".$bel_game;
        }
        if(!empty($hi_name)){
            $conditions .= " AND (`hi_name_cn` LIKE '%".$hi_name."%' OR `hi_name` LIKE '%".$hi_name."%')";
        }
        if(!empty($hi_tag)){
            $conditions .= " AND (`hi_tag` LIKE '%".$hi_tag."%')";
        }
        if(!empty($img_ext)){
            $conditions .= " AND (`hi_icon_get` LIKE '%.".$img_ext."%')";
        }

        //英雄职责属性值
        if(!empty($hi_class)){
            $class_where = '';
            $class_arr = explode(',',$hi_class);
            if(!empty($class_arr) && is_type($class_arr,'Array')){
                foreach($class_arr as $val){
                    $class_where .= "OR FIND_IN_SET(".$val.",hi_class) ";
                }
            }
            if(!empty($class_where)){
                $conditions .= ' AND ('.ltrim($class_where,'OR').') ';
            }
        }

        //获取英雄列表数据
        $res = $this->video_model->ajax_get_hero_data( $start_record, $page_size, $conditions );

        //参数转换
        if(!empty($res[0])){
            foreach($res[0] as $key => &$val){
                $val['hi_isshow_str'] = ($val['hi_isshow'] == 1) ? '显示' : '隐藏'; //显示状态
                $val['in_date'] = empty($val['in_date']) ? '' : date('Y-m-d',$val['in_date']); //添加时间
                $val['hi_class'] = $this->get_hero_class_str($val['hi_class']); //战场职责
                $val['hi_game_id'] = empty($val['hi_game_id']) ? '未关联' : $this->video_model->get_relev_game_name($val['hi_game_id']); //关联游戏
                $val['hi_name_str'] = empty($val['hi_name']) ? $val['hi_name_cn'] : ($val['hi_name_cn'].'（'.$val['hi_name'].'）'); //英雄名称
                $val['hi_icon'] = empty($val['hi_icon']) ? '暂无头像' : ('<img src="'.$val['hi_icon'].'" width=50 height=50/>');
                $val['hi_icon_get'] = empty($val['hi_icon_get']) ? '暂无头像' : ('<img src="'.$GLOBALS['IMAGE_DOMAIN'].$val['hi_icon_get'].'" width=50 height=50/>');
                $val['hi_bicon'] = empty($val['hi_bicon']) ? '暂无头像' : ('<img src="'.$val['hi_bicon'].'" width=60 height=60/>');
                $val['hi_bicon_get'] = empty($val['hi_bicon_get']) ? '暂无头像' : ('<img src="'.$GLOBALS['IMAGE_DOMAIN'].$val['hi_bicon_get'].'" width=60 height=60/>');
            }
        }

        echo $this->get_normol_json( $res[0],$s_echo,$res[1] );
    }

    /**
     * @name: get_hero_class_str
     * @description: 获取英雄职责字符串
     * @param: class_str int 英雄职责id字符串
     * @return: string
     * @author: Chen Zhong
     * @create: 2015-05-06 14:50:50
     **/
    public function get_hero_class_str($class_str = ''){
        if(empty($class_str)){
            return '';
        }

        //战场职责数组 1近战,2远程,3物理,4法术,5坦克,6辅助.7打野,8突进,9男性,10女性
        $class_arr = array(
            1 => '近战',
            2 => '远程',
            3 => '物理',
            4 => '法术',
            5 => '坦克',
            6 => '辅助',
            7 => '打野',
            8 => '突进',
            9 => '男性',
            10 => '女性'
        );

        $class_str_arr = explode(',',$class_str);
        $return_str = '';
        foreach($class_str_arr as $val){
            $return_str .= isset($class_arr[$val]) ? ($class_arr[$val].'，') : '';
        }

        return rtrim($return_str,'，');
    }

    /**
     * @name: hero_edit
     * @description: 英雄信息编辑显示
     * @param: 无
     * @return: 无
     * @author: Chen Zhong
     * @create: 2015-04-28 14:55:50
     **/
    public function hero_edit(){
        if(!$this->check_right('140004')){
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }

        //战场职责数组 1近战,2远程,3物理,4法术,5坦克,6辅助.7打野,8突进,9男性,10女性
        $class_arr = array(
            1 => '近战',
            2 => '远程',
            3 => '物理',
            4 => '法术',
            5 => '坦克',
            6 => '辅助',
            7 => '打野',
            8 => '突进',
            9 => '男性',
            10 => '女性'
        );

        //关联游戏信息数组
        $game_arr = $this->video_model->get_relev_game_arr();

        //英雄id
        $id = intval(get_var_value('id'));

        //游戏分类列表
        $data = array(
            'js' => array(
                'App'=>'admin/scripts/app.js', //需要引入app.js
                'Index'=>'admin/scripts/index.js', //需要引入index.js
                'admin/plugins/bootstrap-fileinput/js/fileinput.js',			//上传按钮和预览图
                'admin/plugins/jquery-file-upload/js/jquery.fileupload.js',		//上传组件
                'admin/plugins/bootstrap-wysihtml5/bootstrap-wysihtml5.js',
                'FormValidation'=>'admin/scripts/muzhiwan.js/hero-manage.js'
            )
        );
        $data['css'] = array(
            'admin/plugins/bootstrap-fileinput/css/fileinput.min.css',
            'admin/plugins/bootstrap-wysihtml5/bootstrap-wysihtml5.css'
        );
        $data['class_arr'] = $class_arr;
        $data['game_arr'] = $game_arr;

        //获取英雄信息
        if(!empty($id)){
            $data['data'] = $this->video_model->get_hero_info($id);
        }

        $this->display( $data, 'hero_edit' );
    }

    /**
     * @name: ajax_del_hero_info
     * @description: ajax删除英雄信息
     * @param: id int 英雄ID
     * @return: json
     * @author: Chen Zhong
     * @create: 2015-04-28 14:47:50
     **/
    public function ajax_del_hero_info(){
        if( !$this->check_right( '140004' ) ){//如果没有权限
            exit(json_encode(array('msg'=>'您没有操作权限！','status'=>3)));
        }
        $id	=  get_var_value( 'id'); //英雄id

        if(empty($id)){
            exit(json_encode(array('msg'=>'参数错误','status'=>400)));
        }
        if($this->video_model->del_hero_info($id)){
            //记录日志 1添加，2修改，3删除，4数据导入，5数据导出，6其他
            $tmp_log_msg = "删除英雄信息成功,英雄ID为：{$id}";
            $this->video_model->log_db_admin( $tmp_log_msg, 3, __CLASS__ );
            exit(json_encode(array('msg'=>'删除成功','status'=>200)));
        }else{
            //记录日志 1添加，2修改，3删除，4数据导入，5数据导出，6其他
            $tmp_log_msg = "删除英雄信息失败,英雄ID为：{$id}";
            $this->video_model->log_db_admin( $tmp_log_msg, 3, __CLASS__ );
            exit(json_encode(array('msg'=>'删除失败','status'=>400)));
        }
    }

    /**
     * @name: ajax_change_hi_hide_status
     * @description: ajax屏蔽显示英雄信息
     * @param: id int 英雄ID
     * @param: status int 是否屏蔽（1：是 2：否）
     * @return: json
     * @author: Chen Zhong
     * @create: 2015-04-28 14:49:50
     **/
    public function ajax_change_hi_hide_status(){
        if( !$this->check_right( '140004' ) ){//如果没有权限
            exit(json_encode(array('msg'=>'您没有操作权限！','status'=>3)));
        }
        $id	=  get_var_value( 'id'); //英雄id
        $status = intval(get_var_value('status')); //屏蔽状态
        if(empty($id) || empty($status)){
            exit(json_encode(array('msg'=>'参数错误','status'=>400)));
        }

        if($this->video_model->change_hi_hide_status($id,$status)){
            //记录日志 1添加，2修改，3删除，4数据导入，5数据导出，6其他
            $tmp_log_msg = ($status == 1) ? ("显示英雄信息成功,英雄ID：{$id}") : ("屏蔽英雄信息成功,英雄ID为：{$id}");
            $this->video_model->log_db_admin( $tmp_log_msg, 2, __CLASS__ );
            exit(json_encode(array('msg'=>'操作成功','status'=>200)));
        }else{
            //记录日志 1添加，2修改，3删除，4数据导入，5数据导出，6其他
            $tmp_log_msg = ($status == 1) ? "显示英雄信息失败,英雄ID：{$id}" : "屏蔽英雄信息失败,英雄ID为：{$id}";
            $this->video_model->log_db_admin( $tmp_log_msg, 2, __CLASS__ );
            exit(json_encode(array('msg'=>'操作失败','status'=>400)));
        }
    }

    /**
     * @name:upload_hi_icon
     * @description:上传英雄头像ico
     * @author: Chen Zhong
     * @create: 2015-04-28 15:30
     */
    public function upload_hi_icon(){
        if( !$this->check_right( '140004' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $return = array( 'status' => 0 );
        if( !is_empty( $_FILES['upload_hi_icon']) ){
            $res = $this->upload_image( $_FILES['upload_hi_icon'], 'hero_ico' );	//上传图片
            $return = is_empty( $res ) ? $return : array( 'status' => 200, 'file' => $res );
        }
        echo json_encode( $return );
    }

    /**
     * @name:upload_hi_bicon
     * @description:上传英雄大头像ico
     * @author: Chen Zhong
     * @create: 2015-04-28 15:30
     */
    public function upload_hi_bicon(){
        if( !$this->check_right( '140004' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $return = array( 'status' => 0 );
        if( !is_empty( $_FILES['upload_hi_bicon']) ){
            $res = $this->upload_image( $_FILES['upload_hi_bicon'], 'hero_bico' );	//上传图片
            $return = is_empty( $res ) ? $return : array( 'status' => 200, 'file' => $res );
        }
        echo json_encode( $return );
    }

    
    
    
    /**
     * @name: upload_image
     * @description: 上传图片
     * @param: array 文件数组
     * @param: string 上传的模块
     * @return: string 成功返回文件名
     * @author: Quan Zelin
     * @create: 2014-10-4 17:43:20
     **/
/*     public function upload_image( $files, $video_modelule ){
        $return = FALSE;
        if( !is_empty( $files ) ){
            $upload_path= $this->config->item( 'image_root_path' );
            $file_name	= md5( uniqid() );	//随机文件名
            $dir		= $upload_path.DS.$video_modelule.DS.date('Y').DS.date('m').DS.date('d').DS;		//以模块+年+月+日作为目录
            if( !file_exists( $dir ) ){
                @mkdir( $dir, 0777, TRUE );
            }
            $ext	= strtolower( substr( $files['name'], strrpos( $files['name'], '.' ) ) );	//.jpg
             $file 	= $dir.$file_name;	//完整文件名
           echo  $res	= up_file( $files, $file, array('jpg|jpeg|png|gif|jpeg2000|bmp'), -1, null, 'AUTO-' );
            $return	= ( $res == $ext ) ? str_replace( $upload_path, '', $file ).$ext : FALSE;	//成功返回带子目录的文件名，如/$video_modelule/2014/10/10/abc.jpg
        }
        return $return;
    }
 */
    /**
     * @name: hero_info_save
     * @description: 英雄信息更新保存
     * @param:
     * @return: json
     * @author: Chen Zhong
     * @create: 2015-04-28 15:54:50
     **/
    public function hero_info_save(){
        if( !$this->check_right( '140004' ) ){//如果没有权限
            $arr['status'] = 1;
            $arr['message'] = '您没有操作权限！';
            $this->callback_ajax( $arr );
        }

        //定义AJAX返回的数组
        $arr = array(
            'status'=>200,//执行状态(例如：200成功，301失败...),
            'message'=>'更新成功',//返回信息,
            'url'=>''//要跳转的地址
        );

        //获取操作数据
        $id = intval(get_var_post("id"));  //英雄id
        $hi_class = rtrim(get_var_post("hi_class"),','); //英雄职责
        $hi_searchtext= get_var_post("hi_searchtext"); //英雄完整名
        $hi_name_cn= get_var_post("hi_name_cn"); //英雄名称
        $hi_name= get_var_post("hi_name"); //英雄名称（英文）
        $hi_icon = get_var_value("hi_icon",False); //英雄头像（本地）
        $hi_bicon = get_var_value("hi_bicon",False); //英雄大头像（本地）
        $hi_tag= get_var_post("hi_tag"); //英雄标签
        $hi_order =  intval(get_var_post("hi_order")); //排序号
        $hi_isshow =  intval(get_var_post("hi_isshow")); //显示状态
        $bel_game =  intval(get_var_post("bel_game")); //所属游戏
        $hi_intro = $this->input->post('hi_intro',TRUE); //英雄简介

        //判断是否在不正常的空内容
        if( empty( $hi_name_cn ) ){
            $arr['status'] = 1;
            $arr['message'] = '英雄名称不能为空！';
            $this->callback_ajax( $arr );
        }

        $data = array(
            "hi_class"=>$hi_class,
            "hi_name_cn"=>$hi_name_cn,
            "hi_name"=>$hi_name,
            "hi_icon_get"=>$hi_icon,
            "hi_bicon_get"=>$hi_bicon,
            "hi_tag"=>$hi_tag,
            "hi_order"=>$hi_order,
            "hi_isshow"=>$hi_isshow,
            "hi_intro"=>$hi_intro,
            "hi_game_id" => $bel_game,
            "hi_searchtext" => $hi_searchtext
        );

        //执行更新操作
        $url = '/admin/video/hero_list';  //执行成功后返回列表页
        if($this->video_model->hero_info_update( $id, $data )){
            //1添加，2修改，3删除，4数据导入，5数据导出，6其他
            $tmp_log_msg = "英雄信息更新成功,英雄ID为：{$id}";
            $this->video_model->log_db_admin( $tmp_log_msg, 2, __CLASS__ );

            $arr['status'] = 200;
            $arr['message'] = '英雄信息更新成功！';
            $arr['url'] = $url;
            $this->callback_ajax( $arr );
        }else{
            $arr['status'] = 3;
            $arr['message'] = '英雄信息无变化';
            $this->callback_ajax( $arr );
        }
    }

    /**
     * @name: author_list
     * @description: 视频解说者列表显示
     * @param: 无
     * @return: 无
     * @author: Chen Zhong
     * @create: 2015-04-28 16:15:50
     **/
    public function author_list(){
        if( !$this->check_right( '140005' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }

        $data = array(
            'js' =>array(
                'App'=>'admin/scripts/app.js', //需要引入app.js
                'Index'=>'admin/scripts/index.js', //需要引入index.js
                'FormValidation'=>'admin/scripts/muzhiwan.js/author-manage.js'
            )
        );
        //关联游戏信息数组
        $data['game_arr'] = $this->video_model->get_relev_game_arr();

        $this->display( $data, 'author_list' );
    }


    /**
     * @name: ajax_get_author_data
     * @description: ajax获取视频解说者信息
     * @param:
     * @return: 无
     * @author: Chen Zhong
     * @create: 2015-04-28  16:20
     **/
    public function ajax_get_author_data() {

        if( !$this->check_right( '140005' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }

        $start_record = get_var_value( 'iDisplayStart' ); 	//从多少条开始查询
        $page_size	  = get_var_value( 'iDisplayLength' ); 	//每页显示多少条记录
        $s_echo		  = get_var_value( 'sEcho' );
        $is_hide = intval(get_var_value('is_hide')); //显示状态
        $va_name = get_var_value('va_name'); //作者名称
        $va_email = intval(get_var_value('va_email')); //作者邮箱
        $img_ext = get_var_value('img_ext'); //作者头像后缀
        $bel_game = intval(get_var_value('bel_game')); //所属游戏
        $is_reg = intval(get_var_value('is_reg')); //是否注册
        $conditions   = ''; //查询条件

        if(!empty($is_hide)){
            $conditions .= " AND `va_isshow` = ".$is_hide;
        }
        if(!empty($bel_game)){
            $conditions .= " AND `va_game_id` = ".$bel_game;
        }
        if(!empty($va_name)){
            $conditions .= " AND (`va_name` LIKE '%".$va_name."%')";
        }
        if(!empty($va_email)){
            $conditions .= " AND (`va_email` LIKE '%".$va_email."%')";
        }
        if(!empty($is_reg)){
            if($is_reg == 1){
                $conditions .= " AND `va_uid` != 0 ";
            }else{
                $conditions .= " AND `va_uid` = 0 ";
            }
        }
        if(!empty($img_ext)){
            $conditions .= " AND (`va_icon_get` LIKE '%.".$img_ext."%')";
        }

        //获取视频解说者列表数据
        $res = $this->video_model->ajax_get_author_data( $start_record, $page_size, $conditions );

        //参数转换
        if(!empty($res[0])){
            foreach($res[0] as $key => &$val){
                $val['va_isshow_str'] = ($val['va_isshow'] == 1) ? '显示' : '隐藏'; //显示状态
                $val['in_date'] = empty($val['in_date']) ? '' : date('Y-m-d',$val['in_date']); //添加时间
                $val['va_game_id'] = empty($val['va_game_id']) ? '未关联' : $this->video_model->get_relev_game_name($val['va_game_id']); //关联游戏
                $val['va_icon'] = empty($val['va_icon']) ? '暂无头像' : ('<img src="'.$val['va_icon'].'" width=50 height=50/>');
                $val['va_icon_get'] = empty($val['va_icon_get']) ? '暂无头像' : ('<img src="'.$GLOBALS['IMAGE_DOMAIN'].$val['va_icon_get'].'" width=50 height=50/>');
                $val['is_reg'] = empty($val['va_uid']) ? 0 : 1; //是否已注册
            }
        }

        echo $this->get_normol_json( $res[0],$s_echo,$res[1] );
    }

    /**
     * @name: author_edit
     * @description: 解说者信息编辑显示
     * @param: 无
     * @return: 无
     * @author: Chen Zhong
     * @create: 2015-04-28 16:57:50
     **/
    public function author_edit(){
        if(!$this->check_right('140006')){
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }

        //解说者id
        $id = intval(get_var_value('id'));

        //游戏分类列表
        $data = array(
            'js' => array(
                'App'=>'admin/scripts/app.js', //需要引入app.js
                'Index'=>'admin/scripts/index.js', //需要引入index.js
                'admin/plugins/bootstrap-fileinput/js/fileinput.js',			//上传按钮和预览图
                'admin/plugins/jquery-file-upload/js/jquery.fileupload.js',		//上传组件
                'admin/plugins/bootstrap-wysihtml5/bootstrap-wysihtml5.js',
                'FormValidation'=>'admin/scripts/muzhiwan.js/author-manage.js'
            )
        );
        $data['css'] = array(
            'admin/plugins/bootstrap-fileinput/css/fileinput.min.css',
            'admin/plugins/bootstrap-wysihtml5/bootstrap-wysihtml5.css'
        );

        //关联游戏信息数组
        $data['game_arr'] = $this->video_model->get_relev_game_arr();

        //获取解说者信息
        if(!empty($id)){
            $data['data'] = $this->video_model->get_author_info($id);
        }

        $this->display( $data, 'author_edit' );
    }

    /**
     * @name:upload_author_icon
     * @description:上传解说者头像ico
     * @author: Chen Zhong
     * @create: 2015-04-28 17:11
     */
    public function upload_author_icon(){
        if( !$this->check_right( '140006' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $return = array( 'status' => 0 );
        if( !is_empty( $_FILES['upload_va_icon']) ){
            $res = $this->upload_image( $_FILES['upload_va_icon'], 'author_ico' );	//上传图片
            $return = is_empty( $res ) ? $return : array( 'status' => 200, 'file' => $res );
        }
        echo json_encode( $return );
    }

    /**
     * @name:upload_video_type_icon
     * @description:上传类型图标ico
     * @author: Chen Zhong
     * @create: 2015-05-19 12:25
     */
    public function upload_video_type_icon(){
        if( !$this->check_right( '140008' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $return = array( 'status' => 0 );
        if( !is_empty( $_FILES['upload_vc_icon']) ){
            $res = $this->upload_image( $_FILES['upload_vc_icon'], 'video_type_ico' );	//上传图片
            $return = is_empty( $res ) ? $return : array( 'status' => 200, 'file' => $res );
        }
        echo json_encode( $return );
    }

    /**
     * @name:upload_video_type_bicon
     * @description:上传类型大图
     * @author: Chen Zhong
     * @create: 2015-05-19 12:25
     */
    public function upload_video_type_bicon(){
        if( !$this->check_right( '140008' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }

        //引入上传类
        require APPPATH.'/libraries/simple_ajax_uploader.php';
        //上传图片后缀名限制
        $valid_extensions = array('gif', 'png', 'jpeg', 'jpg','webp','txt');
        $Upload = new FileUpload('uploadfile');
        //上传大小限制
        $Upload->sizeLimit = 1048576;  //上限1M
        //创建图片存放目录
        $date =  '/video_type_bico' .date('/Y/m/d/');  //添加模块名作目录一部分
        $upload_dir = $this->config->item('image_root_path')  . $date;
        create_my_file_path($upload_dir,0755);
        //生成新图片名称
        $Upload->newFileName = md5(uniqid().$Upload->getFileName()).'.'.$Upload->getExtension();
        $result = $Upload->handleUpload($upload_dir, $valid_extensions);
        if (!$result) {
            echo json_encode(array('success' => false, 'msg' => $Upload->getErrorMsg()));
            exit;
        }else{ //上传成功
            $img_path = $date . $Upload->getFileName();
            echo json_encode(array('success' => true, 'file' => $img_path));
        }
    }

    /**
     * @name:upload_video_type_index_icon
     * @description:上传专辑首页推荐图
     * @author: Chen Zhong
     * @create: 2015-05-19 12:25
     */
    public function upload_video_type_index_icon(){
        if( !$this->check_right( '140008' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }

        //引入上传类
        require APPPATH.'/libraries/simple_ajax_uploader.php';
        //上传图片后缀名限制
        $valid_extensions = array('gif', 'png', 'jpeg', 'jpg','webp','txt');
        $Upload = new FileUpload('index-uploadfile');
        //上传大小限制
        $Upload->sizeLimit = 1048576;  //上限1M
        //创建图片存放目录
        $date =  '/video_type_index_ico' .date('/Y/m/d/');  //添加模块名作目录一部分
        $upload_dir = $this->config->item('image_root_path')  . $date;
        create_my_file_path($upload_dir,0755);
        //生成新图片名称
        $Upload->newFileName = md5(uniqid().$Upload->getFileName()).'.'.$Upload->getExtension();
        $result = $Upload->handleUpload($upload_dir, $valid_extensions);
        if (!$result) {
            echo json_encode(array('success' => false, 'msg' => $Upload->getErrorMsg()));
            exit;
        }else{ //上传成功
            $img_path = $date . $Upload->getFileName();
            echo json_encode(array('success' => true, 'file' => $img_path));
        }
    }

    /**
     * @name: author_info_save
     * @description: 解说者信息更新保存
     * @param:
     * @return: json
     * @author: Chen Zhong
     * @create: 2015-04-28 17:13:50
     **/
    public function author_info_save(){
        if( !$this->check_right( '140006' ) ){//如果没有权限
            $arr['status'] = 1;
            $arr['message'] = '您没有操作权限！';
            $this->callback_ajax( $arr );
        }

        //定义AJAX返回的数组
        $arr = array(
            'status'=>200,//执行状态(例如：200成功，301失败...),
            'message'=>'更新成功',//返回信息,
            'url'=>''//要跳转的地址
        );

        //获取操作数据
        $id = intval(get_var_post("id"));  //解说者id
        $va_name = get_var_post("va_name"); //解说者名称
        $va_icon = get_var_value("va_icon",False); //解说者头像
        $va_email = get_var_post("va_email"); //解说者邮箱
        $va_order =  intval(get_var_post("va_order")); //排序号
        $va_isshow =  intval(get_var_post("va_isshow")); //显示状态
        $bel_game =  intval(get_var_post("bel_game")); //所属游戏
        $va_intro = $this->input->post('va_intro',TRUE); //解说者简介

        //判断是否在不正常的空内容
        if( empty( $va_name ) ){
            $arr['status'] = 1;
            $arr['message'] = '解说者名称不能为空！';
            $this->callback_ajax( $arr );
        }

        $data = array(
            "va_name"=>$va_name,
            "va_icon_get"=>$va_icon,
            "va_email"=>$va_email,
            "va_order"=>$va_order,
            "va_isshow"=>$va_isshow,
            "va_intro"=>$va_intro,
            "va_game_id" => $bel_game
        );

        //执行更新操作
        $url = '/admin/video/author_list';  //执行成功后返回列表页
        if($this->video_model->author_info_update( $id, $data )){
            //1添加，2修改，3删除，4数据导入，5数据导出，6其他
            $tmp_log_msg = "解说者信息更新成功,解说者ID为：{$id}";
            $this->video_model->log_db_admin( $tmp_log_msg, 2, __CLASS__ );

            $arr['status'] = 200;
            $arr['message'] = '解说者信息更新成功！';
            $arr['url'] = $url;
            $this->callback_ajax( $arr );
        }else{
            $arr['status'] = 3;
            $arr['message'] = '信息无变化';
            $this->callback_ajax( $arr );
        }
    }

    /**
     * @name: ajax_del_author_info
     * @description: ajax删除视频解说者信息
     * @param: id int 解说者ID
     * @return: json
     * @author: Chen Zhong
     * @create: 2015-04-28 16:49:50
     **/
    public function ajax_del_author_info(){
        if( !$this->check_right( '140006' ) ){//如果没有权限
            exit(json_encode(array('msg'=>'您没有操作权限！','status'=>3)));
        }
        $id	=  get_var_value( 'id'); //解说者id
        $del_video = get_var_value('del_video');
        if(empty($id)){
            exit(json_encode(array('msg'=>'参数错误','status'=>400)));
        }
        if($this->video_model->del_author_info($id)){
            //删除视频解说者对应视频列表
            if($del_video == 'true'){
                $this->video_model->del_video_by_author_id($id);
            }
            //记录日志 1添加，2修改，3删除，4数据导入，5数据导出，6其他
            $tmp_log_msg = "删除视频解说者成功,解说者ID为：{$id}";
            $this->video_model->log_db_admin( $tmp_log_msg, 3, __CLASS__ );
            exit(json_encode(array('msg'=>'删除成功','status'=>200)));
        }else{
            //记录日志 1添加，2修改，3删除，4数据导入，5数据导出，6其他
            $tmp_log_msg = "删除视频解说者失败,解说者ID为：{$id}";
            $this->video_model->log_db_admin( $tmp_log_msg, 3, __CLASS__ );
            exit(json_encode(array('msg'=>'删除失败','status'=>400)));
        }
    }

    /**
     * @name: ajax_change_author_hide_status
     * @description: ajax屏蔽显示视频解说者信息
     * @param: id int 解说者ID
     * @param: status int 是否屏蔽（1：是 2：否）
     * @return: json
     * @author: Chen Zhong
     * @create: 2015-04-28 16:53:50
     **/
    public function ajax_change_author_hide_status(){
        if( !$this->check_right( '140006' ) ){//如果没有权限
            exit(json_encode(array('msg'=>'您没有操作权限！','status'=>3)));
        }
        $id	=  get_var_value( 'id'); //解说者id
        $status = intval(get_var_value('status')); //屏蔽状态
        if(empty($id) || empty($status)){
            exit(json_encode(array('msg'=>'参数错误','status'=>400)));
        }

        if($this->video_model->change_author_hide_status($id,$status)){
            //记录日志 1添加，2修改，3删除，4数据导入，5数据导出，6其他
            $tmp_log_msg = ($status == 1) ? ("显示解说者信息成功,解说者ID：{$id}") : ("屏蔽解说者信息成功,解说者ID为：{$id}");
            $this->video_model->log_db_admin( $tmp_log_msg, 2, __CLASS__ );
            exit(json_encode(array('msg'=>'操作成功','status'=>200)));
        }else{
            //记录日志 1添加，2修改，3删除，4数据导入，5数据导出，6其他
            $tmp_log_msg = ($status == 1) ? "显示解说者信息失败,解说者ID：{$id}" : "屏蔽解说者信息失败,解说者ID为：{$id}";
            $this->video_model->log_db_admin( $tmp_log_msg, 2, __CLASS__ );
            exit(json_encode(array('msg'=>'操作失败','status'=>400)));
        }
    }

    /**
     * @name: video_type_list
     * @description: 视频类别列表显示
     * @param: 无
     * @return: 无
     * @author: Chen Zhong
     * @create: 2015-04-28 17:56:50
     **/
    public function video_type_list(){
        if( !$this->check_right( '140007' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }

        $data = array(
            'js' =>array(
                'App'=>'admin/scripts/app.js', //需要引入app.js
                'Index'=>'admin/scripts/index.js', //需要引入index.js
                'FormValidation'=>'admin/scripts/muzhiwan.js/video-manage.js'
            )
        );
        $data['type_list'] = $this->tmp_type_arr;

        //关联游戏信息数组
        $data['game_arr'] = $this->video_model->get_relev_game_arr();

        //作者数组
        $data['author_arr'] = $this->video_model->get_all_author_list();

        $this->display( $data, 'video_type_list' );
    }

    /**
     * @name: ajax_get_video_type_data
     * @description: ajax获取视频类别信息
     * @param:
     * @return: 无
     * @author: Chen Zhong
     * @create: 2015-04-28  17:58
     **/
    public function ajax_get_video_type_data() {

        if( !$this->check_right( '140007' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }

        $type_arr = $this->tmp_type_arr;

        $start_record = get_var_value( 'iDisplayStart' ); 	//从多少条开始查询
        $page_size	  = get_var_value( 'iDisplayLength' ); 	//每页显示多少条记录
        $s_echo		  = get_var_value( 'sEcho' );
        $is_hide = intval(get_var_value('is_hide')); //显示状态
        $bel_game = intval(get_var_value('bel_game')); //所属游戏
        $author_id = intval(get_var_value('author_id')); //所属解说者
        $vc_name = get_var_value('vc_name'); //类别名称
        $vc_type_id = intval(get_var_value('vc_type_id')); //类别标签
        $index_recom = intval(get_var_value('index_recom')); //首页推荐状态
        $img_ext = get_var_value('img_ext'); //图标后缀
        $conditions   = ''; //查询条件

        if(!empty($is_hide)){
            $conditions .= " AND `vc_isshow` = ".$is_hide;
        }
        if(!empty($bel_game)){
            $conditions .= " AND `vc_game_id` = ".$bel_game;
        }
        if(!empty($author_id)){
            $conditions .= " AND `vc_author_id` = ".$author_id;
        }
        if(!empty($vc_type_id)){
            $conditions .= " AND `vc_type_id` = ".$vc_type_id;
        }
        if(!empty($index_recom)){
            $conditions .= " AND `vc_index_recom` = ".($index_recom-1);
        }
        if(!empty($vc_name)){
            $conditions .= " AND (`vc_name` LIKE '%".$vc_name."%')";
        }
        if(!empty($img_ext)){
            $conditions .= " AND (`vc_icon_get` LIKE '%.".$img_ext."%')";
        }

        //获取视频资料导入列表数据
        $res = $this->video_model->ajax_get_video_type_data( $start_record, $page_size, $conditions );

        //参数转换
        if(!empty($res[0])){
            foreach($res[0] as $key => &$val){
                $val['vc_isshow_str'] = ($val['vc_isshow'] == 1) ? '显示' : '隐藏'; //显示状态
                $val['in_date'] = empty($val['in_date']) ? '' : date('Y-m-d',$val['in_date']); //添加时间
                $val['vc_game_id'] = empty($val['vc_game_id']) ? '未关联' : ($this->video_model->get_relev_game_name($val['vc_game_id'])); //关联游戏
                $val['vc_type_id'] = (isset($type_arr[$val['vc_type_id']])) ? $type_arr[$val['vc_type_id']] : '未知'; //类别标记
                $val['vc_icon'] = empty($val['vc_icon']) ? '暂无图标' : ('<img src="'.$val['vc_icon'].'" width=50 height=50/>');
                $val['vc_icon_get'] = empty($val['vc_icon_get']) ? '暂无图标' : ('<img src="'.$GLOBALS['IMAGE_DOMAIN'].$val['vc_icon_get'].'" width=50 height=50/>');
                $val['vc_index_icon'] = empty($val['vc_index_icon']) ? '暂无图标' : ('<img src="'.$GLOBALS['IMAGE_DOMAIN'].$val['vc_index_icon'].'" width=50 height=50/>');
                $val['vc_bicon'] = empty($val['vc_bicon']) ? '暂无图标' : ('<img src="'.$GLOBALS['IMAGE_DOMAIN'].$val['vc_bicon'].'" width=50 height=50/>');
            }
        }

        echo $this->get_normol_json( $res[0],$s_echo,$res[1] );
    }

    /**
     * @name: video_type_edit
     * @description: 视频类别编辑显示
     * @param: 无
     * @return: 无
     * @author: Chen Zhong
     * @create: 2015-04-28 19:10:50
     **/
    public function video_type_edit(){
        if(!$this->check_right('140008')){
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }

        $type_arr = $this->tmp_type_arr;

        //类别id
        $id = intval(get_var_value('id'));

        //游戏分类列表
        $data = array(
            'js' => array(
                'App'=>'admin/scripts/app.js', //需要引入app.js
                'Index'=>'admin/scripts/index.js', //需要引入index.js
                'admin/scripts/muzhiwan.js/SimpleAjaxUploader.js', //图片上传
                'admin/plugins/bootstrap-fileinput/js/fileinput.js',			//上传按钮和预览图
                'admin/plugins/jquery-file-upload/js/jquery.fileupload.js',		//上传组件
                'admin/plugins/bootstrap-wysihtml5/bootstrap-wysihtml5.js',
                'FormValidation'=>'admin/scripts/muzhiwan.js/video-manage.js'
            )
        );
        $data['css'] = array(
            'admin/plugins/bootstrap-fileinput/css/fileinput.min.css',
            'admin/plugins/bootstrap-wysihtml5/bootstrap-wysihtml5.css'
        );
        $data['type_arr'] = $type_arr;
        $data['id'] = $id;

        //关联游戏信息数组
        $data['game_arr'] = $this->video_model->get_relev_game_arr();

        //获取解说者信息
        if(!empty($id)){
            $data['data'] = $this->video_model->get_video_type_info($id);
        }

        //获取所有分类数组
        $game_id = isset($data['data']['vc_game_id']) ? intval($data['data']['vc_game_id']) : 0;
        $data['category_arr'] = $this->video_model->get_video_category($game_id);

        //作者关联数组
        $data['author_arr'] = $this->video_model->get_author_list($game_id);

        $this->display( $data, 'video_type_edit' );
    }

    /**
     * @name: ajax_del_video_type
     * @description: ajax删除视频类别
     * @param: id int 类别ID
     * @return: json
     * @author: Chen Zhong
     * @create: 2015-04-28 19:00:50
     **/
    public function ajax_del_video_type(){
        if( !$this->check_right( '140008' ) ){//如果没有权限
            exit(json_encode(array('msg'=>'您没有操作权限！','status'=>3)));
        }
        $id	=  get_var_value( 'id'); //类别id

        if(empty($id)){
            exit(json_encode(array('msg'=>'参数错误','status'=>400)));
        }
        if($this->video_model->del_video_type($id)){
            //记录日志 1添加，2修改，3删除，4数据导入，5数据导出，6其他
            $tmp_log_msg = "删除视频类别成功,类别ID为：{$id}";
            $this->video_model->log_db_admin( $tmp_log_msg, 3, __CLASS__ );
            exit(json_encode(array('msg'=>'删除成功','status'=>200)));
        }else{
            //记录日志 1添加，2修改，3删除，4数据导入，5数据导出，6其他
            $tmp_log_msg = "删除视频类别失败,类别ID为：{$id}";
            $this->video_model->log_db_admin( $tmp_log_msg, 3, __CLASS__ );
            exit(json_encode(array('msg'=>'删除失败','status'=>400)));
        }
    }

    /**
     * @name: ajax_change_video_type_status
     * @description: ajax屏蔽显示视频类别
     * @param: id int 类别ID
     * @param: status int 是否屏蔽（1：是 2：否）
     * @return: json
     * @author: Chen Zhong
     * @create: 2015-04-28 19:06:50
     **/
    public function ajax_change_video_type_status(){
        if( !$this->check_right( '140008' ) ){//如果没有权限
            exit(json_encode(array('msg'=>'您没有操作权限！','status'=>3)));
        }
        $id	=  get_var_value( 'id'); //类别id
        $status = intval(get_var_value('status')); //屏蔽状态
        if(empty($id) || empty($status)){
            exit(json_encode(array('msg'=>'参数错误','status'=>400)));
        }

        if($this->video_model->change_video_type_status($id,$status)){
            //记录日志 1添加，2修改，3删除，4数据导入，5数据导出，6其他
            $tmp_log_msg = ($status == 1) ? ("显示视频类别成功,类别ID：{$id}") : ("屏蔽视频类别成功,类别ID为：{$id}");
            $this->video_model->log_db_admin( $tmp_log_msg, 2, __CLASS__ );
            exit(json_encode(array('msg'=>'操作成功','status'=>200)));
        }else{
            //记录日志 1添加，2修改，3删除，4数据导入，5数据导出，6其他
            $tmp_log_msg = ($status == 1) ? "显示视频类别失败,类别ID：{$id}" : "屏蔽视频类别失败,类别ID为：{$id}";
            $this->video_model->log_db_admin( $tmp_log_msg, 2, __CLASS__ );
            exit(json_encode(array('msg'=>'操作失败','status'=>400)));
        }
    }

    /**
     * @name: ajax_change_author_recom_status
     * @description: ajax更改视频解说者推荐状态
     * @param: id int 解说者ID
     * @param: status int 推荐状态（1：推荐 0：取消推荐）
     * @return: json
     * @author: Chen Zhong
     * @create: 2015-04-28 16:53:50
     **/
    public function ajax_change_author_recom_status(){
        if( !$this->check_right( '140006' ) ){//如果没有权限
            exit(json_encode(array('msg'=>'您没有操作权限！','status'=>3)));
        }
        $id	=  get_var_value( 'id'); //解说者id
        $status = intval(get_var_value('status')); //屏蔽状态
        if(empty($id) || empty($status)){
            exit(json_encode(array('msg'=>'参数错误','status'=>400)));
        }

        if($this->video_model->change_author_recom_status($id,($status-1))){
            //记录日志 1添加，2修改，3删除，4数据导入，5数据导出，6其他
            $tmp_log_msg = ($status == 2) ? ("推荐解说者成功,解说者ID：{$id}") : ("取消推荐解说者成功,解说者ID为：{$id}");
            $this->video_model->log_db_admin( $tmp_log_msg, 2, __CLASS__ );
            exit(json_encode(array('msg'=>'操作成功','status'=>200)));
        }else{
            //记录日志 1添加，2修改，3删除，4数据导入，5数据导出，6其他
            $tmp_log_msg = ($status == 2) ? "推荐解说者失败,解说者ID：{$id}" : "取消推荐解说者失败,解说者ID为：{$id}";
            $this->video_model->log_db_admin( $tmp_log_msg, 2, __CLASS__ );
            exit(json_encode(array('msg'=>'操作失败','status'=>400)));
        }
    }

    /**
     * @name: ajax_change_category_recom_status
     * @description: ajax更改视频分类的推荐状态
     * @param: id int 视频分类ID
     * @param: status int 推荐状态（1：推荐 0：取消推荐）
     * @return: json
     * @author: Chen Zhong
     * @create: 2015-11-03 09:49:50
     **/
    public function ajax_change_category_recom_status(){
        if( !$this->check_right( '140007' ) ){//如果没有权限
            exit(json_encode(array('msg'=>'您没有操作权限！','status'=>3)));
        }
        $id	=  get_var_value( 'id'); //视频分类id
        $status = intval(get_var_value('status')); //推荐状态
        if(empty($id) || empty($status)){
            exit(json_encode(array('msg'=>'参数错误','status'=>400)));
        }

        if($this->video_model->change_category_recom_status($id,($status-1))){
            //记录日志 1添加，2修改，3删除，4数据导入，5数据导出，6其他
            $tmp_log_msg = ($status == 2) ? ("via推荐视频分类成功,视频分类ID：{$id}") : ("via取消推荐视频分类成功,视频分类ID为：{$id}");
            $this->video_model->log_db_admin( $tmp_log_msg, 2, __CLASS__ );
            exit(json_encode(array('msg'=>'操作成功','status'=>200)));
        }else{
            //记录日志 1添加，2修改，3删除，4数据导入，5数据导出，6其他
            $tmp_log_msg = ($status == 2) ? "via推荐视频分类失败,视频分类ID：{$id}" : "via取消推荐视频分类失败,视频分类ID为：{$id}";
            $this->video_model->log_db_admin( $tmp_log_msg, 2, __CLASS__ );
            exit(json_encode(array('msg'=>'操作失败','status'=>400)));
        }
    }

    /**
     * @name: ajax_change_index_recom_status
     * @description: ajax更改视频分类的首页推荐状态
     * @param: id int 视频分类ID
     * @param: status int 推荐状态（1：推荐 0：取消推荐）
     * @return: json
     * @author: Chen Zhong
     * @create: 2015-11-03 09:49:50
     **/
    public function ajax_change_index_recom_status(){
        if( !$this->check_right( '140007' ) ){//如果没有权限
            exit(json_encode(array('msg'=>'您没有操作权限！','status'=>3)));
        }
        $id	=  get_var_value( 'id'); //视频分类id
        $status = intval(get_var_value('status')); //推荐状态
        if(empty($id) || empty($status)){
            exit(json_encode(array('msg'=>'参数错误','status'=>400)));
        }

        if($this->video_model->change_index_recom_status($id,($status-1))){
            //记录日志 1添加，2修改，3删除，4数据导入，5数据导出，6其他
            $tmp_log_msg = ($status == 2) ? ("首页推荐视频分类成功,视频分类ID：{$id}") : ("取消首页推荐视频分类成功,视频分类ID为：{$id}");
            $this->video_model->log_db_admin( $tmp_log_msg, 2, __CLASS__ );
            exit(json_encode(array('msg'=>'操作成功','status'=>200)));
        }else{
            //记录日志 1添加，2修改，3删除，4数据导入，5数据导出，6其他
            $tmp_log_msg = ($status == 2) ? "首页推荐视频分类失败,视频分类ID：{$id}" : "取消首页推荐视频分类失败,视频分类ID为：{$id}";
            $this->video_model->log_db_admin( $tmp_log_msg, 2, __CLASS__ );
            exit(json_encode(array('msg'=>'操作失败','status'=>400)));
        }
    }

    /**
     * @name: video_type_info_save
     * @description: 视频类别信息更新保存
     * @param:
     * @return: json
     * @author: Chen Zhong
     * @create: 2015-04-28 19:21:50
     **/
    public function video_type_info_save(){
        if( !$this->check_right( '140008' ) ){//如果没有权限
            $arr['status'] = 1;
            $arr['message'] = '您没有操作权限！';
            $this->callback_ajax( $arr );
        }

        //定义AJAX返回的数组
        $arr = array(
            'status'=>200,//执行状态(例如：200成功，301失败...),
            'message'=>'更新成功',//返回信息,
            'url'=>''//要跳转的地址
        );

        //获取操作数据
        $id = intval(get_var_post("id"));  //类别id
        $vc_name = get_var_post("vc_name"); //类别名称
        $vc_type_id = get_var_post("vc_type_id");  //类别标记
        $vc_icon = get_var_value("vc_icon",False); //类型头像
        $vc_bicon = get_var_value("vc_bicon",False); //类型大图
        $vc_index_icon = get_var_value("vc_index_icon",False); //首页推荐图
        $vc_order =  intval(get_var_post("vc_order")); //排序号
        $vc_isshow =  intval(get_var_post("vc_isshow")); //显示状态
        $bel_game =  intval(get_var_post("bel_game")); //所属游戏
        $vc_intro = $this->input->post('vc_intro',TRUE); //解说者简介
        $p_id =  intval(get_var_post("vc_p_id")); //父级id
        $vc_author_id =  intval(get_var_post("vc_author_id")); //所属作者

        //判断是否在不正常的空内容
        if( empty( $vc_name ) ){
            $arr['status'] = 1;
            $arr['message'] = '类别名称不能为空！';
            $this->callback_ajax( $arr );
        }

        //用户id
        $uid = 0;
        if(!empty($vc_author_id)){
            $author_data = $this->video_model->get_author_info($vc_author_id);
            $uid = isset($author_data['va_uid']) ? intval($author_data['va_uid']) : 0;
        }

        $data = array(
            "vc_name"=>$vc_name,
            "vc_type_id"=>$vc_type_id,
            "vc_order"=>$vc_order,
            "vc_isshow"=>$vc_isshow,
            "vc_intro"=>$vc_intro,
            "vc_game_id" => $bel_game,
            "vc_icon_get" => $vc_icon,
            "vc_bicon" => $vc_bicon,
            "vc_index_icon" => $vc_index_icon,
            "vc_p_id" => $p_id,
            "vc_author_id" => $vc_author_id,
            "vc_uid" => $uid
        );

        $url = '/admin/video/video_type_list';  //执行成功后返回列表页
        if(!empty($id)){ //更新操作
            $data['vc_update_time'] = time();
            if($this->video_model->video_type_info_update( $id, $data )){

                //uid不为空，更新专辑下视频的uid
                if(!empty($uid)){
                    $this->video_model->video_info_update(0,array('vvl_uid' => $uid,'vvl_author_id' => $vc_author_id),array('vvl_category_id' => $id));
                }

                //1添加，2修改，3删除，4数据导入，5数据导出，6其他
                $tmp_log_msg = "视频类别信息更新成功,类别ID为：{$id}";
                $this->video_model->log_db_admin( $tmp_log_msg, 2, __CLASS__ );

                $arr['status'] = 200;
                $arr['message'] = '视频类别信息更新成功！';
                $arr['url'] = $url;
                $this->callback_ajax( $arr );
            }else{
                $arr['status'] = 3;
                $arr['message'] = '信息无变化';
                $this->callback_ajax( $arr );
            }
        }else{ //添加操作
            $data['in_date'] = time();
            $data['vc_scount'] = 0;
            $data['vc_splaycount'] = 0;
            $data['vc_playcount'] = 0;
            $data['vc_author_id'] = 0;
            if($insert_id = $this->video_model->video_type_info_add( $data )){
                //1添加，2修改，3删除，4数据导入，5数据导出，6其他
                $tmp_log_msg = "视频类别信息添加成功,类别ID为：{$insert_id}";
                $this->video_model->log_db_admin( $tmp_log_msg, 1, __CLASS__ );

                $arr['status'] = 200;
                $arr['message'] = '视频类别信息添加成功！';
                $arr['url'] = $url;
                $this->callback_ajax( $arr );
            }else{
                $arr['status'] = 3;
                $arr['message'] = '信息无变化';
                $this->callback_ajax( $arr );
            }
        }
    }

    /**
     * @name: hero_skill_list
     * @description: 英雄技能列表显示
     * @param: 无
     * @return: 无
     * @author: Chen Zhong
     * @create: 2015-04-29 09:55:50
     **/
    public function hero_skill_list(){
        if( !$this->check_right( '140009' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }

        $data = array(
            'js' =>array(
                'App'=>'admin/scripts/app.js', //需要引入app.js
                'Index'=>'admin/scripts/index.js', //需要引入index.js
                'FormValidation'=>'admin/scripts/muzhiwan.js/hero-manage.js'
            )
        );
        $data['h_id'] = intval(get_var_value('h_id')); //英雄id

        //关联游戏信息数组
        $data['game_arr'] = $this->video_model->get_relev_game_arr();

        $this->display( $data, 'hero_skill_list' );
    }


    /**
     * @name: ajax_get_hero_skill_data
     * @description: ajax获取英雄技能信息
     * @param:
     * @return: 无
     * @author: Chen Zhong
     * @create: 2015-04-29  09:56
     **/
    public function ajax_get_hero_skill_data() {

        if( !$this->check_right( '140009' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }

        $start_record = get_var_value( 'iDisplayStart' ); 	//从多少条开始查询
        $page_size	  = get_var_value( 'iDisplayLength' ); 	//每页显示多少条记录
        $s_echo		  = get_var_value( 'sEcho' );
        $is_hide = intval(get_var_value('is_hide')); //显示状态
        $hi_name = get_var_value('hi_name'); //英雄名称
        $hs_name = get_var_value('hs_name'); //技能名称
        $h_id = intval(get_var_value('h_id')); //英雄id
        $bel_game = intval(get_var_value('bel_game')); //所属游戏
        $img_ext = get_var_value('img_ext'); //英雄头像后缀
        $conditions   = ''; //查询条件

        if(!empty($is_hide)){
            $conditions .= " AND A.`hs_isshow` = ".$is_hide;
        }
        if(!empty($bel_game)){
            $conditions .= " AND A.`hs_game_id` = ".$bel_game;
        }
        if(!empty($h_id)){
            $conditions .= " AND A.`hs_hi_id` = ".$h_id;
        }
        if(!empty($hi_name)){
            $conditions .= " AND (B.`hi_name_cn` LIKE '%".$hi_name."%' OR B.`hi_name` LIKE '%".$hi_name."%')";
        }
        if(!empty($hs_name)){
            $conditions .= " AND (A.`hs_name` LIKE '%".$hs_name."%')";
        }
        if(!empty($img_ext)){
            $conditions .= " AND (A.`hs_img_get` LIKE '%.".$img_ext."%')";
        }

        //获取英雄技能列表数据
        $res = $this->video_model->ajax_get_hero_skill_data( $start_record, $page_size, $conditions );

        //参数转换
        if(!empty($res[0])){
            foreach($res[0] as $key => &$val){
                $val['hs_isshow_str'] = ($val['hs_isshow'] == 1) ? '显示' : '隐藏'; //显示状态
                $hi_name_str = empty($val['hi_name']) ? $val['hi_name_cn'] : ($val['hi_name_cn'].'（'.$val['hi_name'].'）'); //英雄名称
                $val['hs_game_id'] = empty($val['hs_game_id']) ? '未关联' : $this->video_model->get_relev_game_name($val['hs_game_id']); //关联游戏
                $val['hi_name_str'] = '<a href="#" onclick="view_hero_info('.$val['hs_hi_id'].')">'.$hi_name_str.'</a>';
                $val['in_date'] = empty($val['in_date']) ? '' : date('Y-m-d',$val['in_date']); //采集日期
                $val['hs_img'] = empty($val['hs_img']) ? '暂无头像' : ('<img src="'.$val['hs_img'].'" width=50 height=50/>'); //技能图片
                $val['hs_img_get'] = empty($val['hs_img_get']) ? '暂无头像' : ('<img src="'.$GLOBALS['IMAGE_DOMAIN'].$val['hs_img_get'].'" width=50 height=50/>'); //技能图片（本地）
            }
        }

        echo $this->get_normol_json( $res[0],$s_echo,$res[1] );
    }

    /**
     * @name: ajax_del_hero_skill_info
     * @description: ajax删除英雄技能信息
     * @param: id int 英雄技能ID
     * @return: json
     * @author: Chen Zhong
     * @create: 2015-04-29 10:56:50
     **/
    public function ajax_del_hero_skill_info(){
        if( !$this->check_right( '140010' ) ){//如果没有权限
            exit(json_encode(array('msg'=>'您没有操作权限！','status'=>3)));
        }
        $id	=  get_var_value( 'id'); //英雄技能id

        if(empty($id)){
            exit(json_encode(array('msg'=>'参数错误','status'=>400)));
        }
        if($this->video_model->del_hero_skill_info($id)){
            //记录日志 1添加，2修改，3删除，4数据导入，5数据导出，6其他
            $tmp_log_msg = "删除英雄技能信息成功,技能ID为：{$id}";
            $this->video_model->log_db_admin( $tmp_log_msg, 3, __CLASS__ );
            exit(json_encode(array('msg'=>'删除成功','status'=>200)));
        }else{
            //记录日志 1添加，2修改，3删除，4数据导入，5数据导出，6其他
            $tmp_log_msg = "删除英雄技能信息失败,技能ID为：{$id}";
            $this->video_model->log_db_admin( $tmp_log_msg, 3, __CLASS__ );
            exit(json_encode(array('msg'=>'删除失败','status'=>400)));
        }
    }

    /**
     * @name: ajax_change_hs_hide_status
     * @description: ajax屏蔽显示英雄技能信息
     * @param: id int 技能ID
     * @param: status int 是否屏蔽（1：是 2：否）
     * @return: json
     * @author: Chen Zhong
     * @create: 2015-04-29 11:01:50
     **/
    public function ajax_change_hs_hide_status(){
        if( !$this->check_right( '140010' ) ){//如果没有权限
            exit(json_encode(array('msg'=>'您没有操作权限！','status'=>3)));
        }
        $id	=  get_var_value( 'id'); //技能id
        $status = intval(get_var_value('status')); //屏蔽状态
        if(empty($id) || empty($status)){
            exit(json_encode(array('msg'=>'参数错误','status'=>400)));
        }

        if($this->video_model->change_hs_hide_status($id,$status)){
            //记录日志 1添加，2修改，3删除，4数据导入，5数据导出，6其他
            $tmp_log_msg = ($status == 1) ? ("显示英雄技能成功,技能ID：{$id}") : ("屏蔽英雄技能成功,技能ID为：{$id}");
            $this->video_model->log_db_admin( $tmp_log_msg, 2, __CLASS__ );
            exit(json_encode(array('msg'=>'操作成功','status'=>200)));
        }else{
            //记录日志 1添加，2修改，3删除，4数据导入，5数据导出，6其他
            $tmp_log_msg = ($status == 1) ? "显示英雄技能失败,技能ID：{$id}" : "屏蔽英雄技能失败,技能ID为：{$id}";
            $this->video_model->log_db_admin( $tmp_log_msg, 2, __CLASS__ );
            exit(json_encode(array('msg'=>'操作失败','status'=>400)));
        }
    }

    /**
     * @name: hero_skill_edit
     * @description: 英雄技能信息编辑显示
     * @param: 无
     * @return: 无
     * @author: Chen Zhong
     * @create: 2015-04-29 11:06:50
     **/
    public function hero_skill_edit(){
        if(!$this->check_right('140010')){
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }

        //技能id
        $id = intval(get_var_value('id'));

        //游戏分类列表
        $data = array(
            'js' => array(
                'App'=>'admin/scripts/app.js', //需要引入app.js
                'Index'=>'admin/scripts/index.js', //需要引入index.js
                'admin/plugins/bootstrap-fileinput/js/fileinput.js',			//上传按钮和预览图
                'admin/plugins/jquery-file-upload/js/jquery.fileupload.js',		//上传组件
                'admin/plugins/bootstrap-wysihtml5/bootstrap-wysihtml5.js',
                'FormValidation'=>'admin/scripts/muzhiwan.js/hero-manage.js'
            )
        );
        $data['css'] = array(
            'admin/plugins/bootstrap-fileinput/css/fileinput.min.css',
            'admin/plugins/bootstrap-wysihtml5/bootstrap-wysihtml5.css'
        );

        //关联游戏信息数组
        $data['game_arr'] = $this->video_model->get_relev_game_arr();

        //获取技能信息
        if(!empty($id)){
            $res = $this->video_model->get_hero_skill_info($id);
            if(!empty($res)){
                $res['hero_str'] = empty($res['hi_name']) ? $res['hi_name_cn'] : ($res['hi_name_cn'].'（'.$res['hi_name'].'）');
            }
            $data['data'] = $res;
        }

        $this->display( $data, 'hero_skill_edit' );
    }

    /**
     * @name:upload_hero_skill_icon
     * @description:上传技能ico
     * @author: Chen Zhong
     * @create: 2015-04-29 11:08
     */
    public function upload_hero_skill_icon(){
        if( !$this->check_right( '140010' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $return = array( 'status' => 0 );
        if( !is_empty( $_FILES['upload_hs_icon']) ){
            $res = $this->upload_image( $_FILES['upload_hs_icon'], 'hero_skill_img' );	//上传图片
            $return = is_empty( $res ) ? $return : array( 'status' => 200, 'file' => $res );
        }
        echo json_encode( $return );
    }

    /**
     * @name: hero_skill_info_save
     * @description: 英雄技能信息更新保存
     * @param:
     * @return: json
     * @author: Chen Zhong
     * @create: 2015-04-29 11:09:50
     **/
    public function hero_skill_info_save(){
        if( !$this->check_right( '140010' ) ){//如果没有权限
            $arr['status'] = 1;
            $arr['message'] = '您没有操作权限！';
            $this->callback_ajax( $arr );
        }

        //定义AJAX返回的数组
        $arr = array(
            'status'=>200,//执行状态(例如：200成功，301失败...),
            'message'=>'更新成功',//返回信息,
            'url'=>''//要跳转的地址
        );

        //获取操作数据
        $id = intval(get_var_post("id"));  //技能id
        $hs_name = get_var_post("hs_name"); //技能名称
        $hs_icon = get_var_value("hs_icon",False); //技能图片
        $hs_use = get_var_post("hs_use"); //技能值
        $hs_wait = get_var_post("hs_wait"); //技能冷却时间
        $hs_shortkey = get_var_post("hs_shortkey"); //技能快捷键
        $hs_order =  intval(get_var_post("hs_order")); //排序号
        $hs_isshow =  intval(get_var_post("hs_isshow")); //显示状态
        $bel_game =  intval(get_var_post("bel_game")); //所属游戏
        $hs_intro = $this->input->post('hs_intro',TRUE); //解说者简介


        //判断是否在不正常的空内容
        if( empty( $hs_name ) ){
            $arr['status'] = 1;
            $arr['message'] = '技能名称不能为空！';
            $this->callback_ajax( $arr );
        }

        $data = array(
            "hs_name"=>$hs_name,
            "hs_img_get"=>$hs_icon,
            "hs_use"=>$hs_use,
            "hs_wait"=>$hs_wait,
            "hs_shortkey"=>$hs_shortkey,
            "hs_order"=>$hs_order,
            "hs_isshow"=>$hs_isshow,
            "hs_intro"=>$hs_intro,
            "hs_game_id" => $bel_game
        );

        //执行更新操作
        $url = '/admin/video/hero_skill_list';  //执行成功后返回列表页
        if($this->video_model->hero_skill_info_update( $id, $data )){
            //1添加，2修改，3删除，4数据导入，5数据导出，6其他
            $tmp_log_msg = "技能信息更新成功,技能ID为：{$id}";
            $this->video_model->log_db_admin( $tmp_log_msg, 2, __CLASS__ );

            $arr['status'] = 200;
            $arr['message'] = '技能信息更新成功！';
            $arr['url'] = $url;
            $this->callback_ajax( $arr );
        }else{
            $arr['status'] = 3;
            $arr['message'] = '信息无变化';
            $this->callback_ajax( $arr );
        }
    }

    /**
     * @name: collect_image
     * @description: 根据地址采集图片
     * @param: mixed 需要判断的变量
     * @return: boolean (TRUE为空, FALSE为非空)
     * @author: Chen Zhong
     * @create: 2015-03-03 14:26:50
     **/
    public function collect_image(){

        $source_path = trim(get_var_value('source_path')); //源地址
        //返回json数组
        $return_arr = array(
            'status' => 400,
            'message' => '源地址不存在，无法采集',
            'img_path' => ''
        );
        if(empty($source_path)){
            $this->callback_ajax($return_arr);
        }

        //创建图片存放目录
        $img_path = ''; //图片本地存储路径
        $date =  '/video_img' .date('/Y/m/d/');  //添加模块名作目录一部分
        $to_save = $this->config->item('image_root_path') . $date;
        create_my_file_path($to_save,0755);

        $img_all = curl_get_img($source_path,$to_save);
        $img = str_replace($this->config->item('image_root_path'), '', $img_all);

        if(!empty($img)){
            $return_arr['status'] = 200;
            $return_arr['message'] = '采集成功';
            $return_arr['img_path'] = $img;
            $return_arr['img_path_all'] = $GLOBALS['IMAGE_DOMAIN'].$img;
        }else{
            $return_arr['message'] = '采集失败';
        }

        $this->callback_ajax($return_arr);
    }
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    /**
     * @name:video_list
     * @description: 视频列表
     * @author: Xiong Jianbang
     * @create: 2015-4-29 下午6:00:08
     **/
    public function video_list(){
      if( !$this->check_right( '140011' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $data = array(
                'js' =>array(
                        'App'=>'admin/scripts/app.js', //需要引入app.js
                        'Index'=>'admin/scripts/index.js', //需要引入index.js
                        'GameDatetimePicker' => 'admin/scripts/muzhiwan.js/game-datetime-picker.js', //日历
                        'FormValidation'=>'admin/scripts/muzhiwan.js/video-list.js'
                )
        );
        $data['type_list'] = $this->tmp_type_arr;

        $data['h_id'] = intval(get_var_value('h_id')); //英雄id
        $data['v_id'] = intval(get_var_value('v_id')); //视频类型id
        $data['a_id'] = intval(get_var_value('a_id')); //作者id
        $data['game_id'] = intval(get_var_value('game_id'));//游戏ID 
        $data['package_list'] = $this->video_model->get_package_list();
        $data['hero_list'] = $this->video_model->get_hi_list();
        $video = new video_parser();
        $data['source'] = $video->get_source_arr();
        //关联游戏信息数组
        $game_arr = $this->video_model->get_relev_game_arr();
        $data['game_arr'] = $game_arr;
        $this->display( $data, 'video_list' );
    }
    
    
    /**
     * @name:video_youku_simple
     * @description: 抓取优酷的单页视频
     * @author: Xiong Jianbang
     * @create: 2015-12-7 下午2:30:51
     **/
    public function video_youku_simple(){
        if( !$this->check_right( '140011' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
    
        $data = array(
                'js' => array(
                        'App'=>'admin/scripts/app.js', //需要引入app.js
                        'Index'=>'admin/scripts/index.js', //需要引入index.js
                        'admin/scripts/muzhiwan.js/video_youku_simple.js'
                )
        );
        //关联游戏信息数组
        $game_arr = $this->video_model->get_relev_game_arr();
        $data['game_arr'] = $game_arr;
        $this->display( $data, 'video_youku_simple' );
    }
    
    /**
     * @name:ajax_get_video_list_data
     * @description: 获取视频列表数据
     * @author: Xiong Jianbang
     * @create: 2015-4-29 下午5:59:58
     **/
    public function ajax_get_video_list_data() {
        if( !$this->check_right( '140011' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
    
        $type_arr = $this->tmp_type_arr;
    
        $start_record = get_var_value( 'iDisplayStart' ); 	//从多少条开始查询
        $page_size	  = get_var_value( 'iDisplayLength' ); 	//每页显示多少条记录
        $s_echo		  = get_var_value( 'sEcho' );
        $vvl_id = intval(get_var_value('vvl_id'));
        $title = get_var_value('title'); //标题 
        $hi_id = intval(get_var_value('hi_id')); //英雄id
        $uid = intval(get_var_value('uid'));//用户ID
        $nickname = trim(get_var_value('nickname'));
        $v_id = intval(get_var_value('v_id')); //视频类型id
        $a_id = intval(get_var_value('a_id')); //作者id
        $type_id = intval(get_var_value('type_id')); //类别标签
        $source_id = intval(get_var_value('source_id')); //来源
        $is_parsed = intval(get_var_value('is_parsed'));
        $img_format = trim(get_var_value('img_format'));
        $package_name = trim(get_var_value('package_name'));
        $game_id = '';
        $game_post = trim(get_var_value('game_id'));
        $v_status = intval(get_var_value('v_status'));
        $begin_time = trim(get_var_value('begin_time'));
        $end_time = trim(get_var_value('end_time'));

        
        $i_sort_column	=  intval(get_var_value( 'iSortCol_0' ));	    //排序列索引
        $s_sort_order = empty($i_sort_column) ? 'desc' : get_var_value( 'sSortDir_0' );
        $i_sort_column  = ($i_sort_column) ? $i_sort_column : 1;		//默认使用下标为1即id排序
        $s_sort_column	= get_var_value( 'mDataProp_'.$i_sort_column ); //获取列名
        
        if(!empty($game_post)){
        	$game_id = $game_post;
        }
        $bel_game = trim(get_var_value('bel_game'));
        if(!empty($bel_game)){
            $game_id = $bel_game;
        }
        $conditions   = ''; //查询条件
    
        if(!empty($vvl_id)){
            $conditions .= " AND `id` = ".$vvl_id;
        }
        if(!empty($hi_id)){
            $conditions .= " AND `vvl_hi_id` = ".$hi_id;
        }
        if(!empty($uid)){
        	$conditions .= " AND `vvl_uid` = ".$uid;
        }
        if(!empty($nickname)){
        	$uids = $this->member_model->get_uids_by_nickname($nickname);
        	if(!empty($uids)){
        		$arr_uid = array();
        		foreach ($uids as $value) {
        			$arr_uid[] = $value['uid'];
        		}
        		$str_uid = implode(',', $arr_uid);
        		$conditions .= " AND `vvl_uid` IN ({$str_uid}) ";
        	}
        }
        if(!empty($v_id)){
            $conditions .= " AND `vvl_category_id` = ".$v_id;
        }
        if(!empty($a_id)){
            $conditions .= " AND `vvl_author_id` = ".$a_id;
        }
        if(!empty($type_id)){
            $conditions .= " AND `vvl_type_id` = ".$type_id;
        }
        if(!empty($title)){
            $conditions .= " AND (`vvl_title` LIKE '%".$title."%')";
        }
        if(!empty($source_id)){
            $conditions .= " AND `vvl_sourcetype` = ".$source_id;
        }
        if(!empty($game_id)){
            $conditions .= " AND `vvl_game_id` = ".$game_id;
        }
        if(!empty($v_status)){
            $conditions .= " AND `va_isshow` = ".$v_status;
        }
        if(!empty($is_parsed)){
            switch ($is_parsed) {
            	case 1:
            	    $conditions .= " AND `vvl_playurl_get` <> '' ";
            	break;
            	case 2:
            	    $conditions .= " AND `vvl_playurl_get` = '' ";
            	break;
            }
        }
        if(!empty($img_format)){
            $conditions .= " AND (`vvl_imgurl_get` LIKE '%".$img_format."%')";
        }
        if(!empty($package_name)){
            $conditions .= " AND (`vvl_package_name`= '{$package_name}')";
        }
        
        if(!empty($begin_time) && empty($end_time)){
            $begin_time = strtotime($begin_time);
            $conditions .= " AND `in_date` >= {$begin_time}";
        }
        if(!empty($begin_time) && !empty($end_time)){
            $begin_time = strtotime($begin_time);
            $end_time = strtotime($end_time);
            $conditions .= " AND `in_date` >= {$begin_time} AND  `in_date` <= {$end_time}";
        }
        
        
        //获取视频资料导入列表数据
        $res = $this->video_model->ajax_get_video_list_data( $start_record, $page_size, $conditions,$s_sort_column,$s_sort_order );
        $show_arr = array(1=>'显示',2=>'隐藏');
        //参数转换
        if(!empty($res[0])){
            $this->load->library('video_parser');
            $v = new video_parser();
            foreach($res[0] as $key => &$val){
                $user_info = $this->member_model->get_member_list_info($val['vvl_uid']);
                $tmp = $this->video_model->get_hero_info($val['vvl_hi_id']);
                $video_count = $this->video_model->get_video_count_by_uid($val['vvl_uid']);
                $val['user_name'] = isset($user_info['nickname']) ? $user_info['nickname'] ."({$video_count})" : '未关联';
                $val['hi_name_cn'] = isset($tmp['hi_name_cn']) ? $tmp['hi_name_cn'] : '';
                $val['vvl_type_name'] = isset($type_arr[$val['vvl_type_id']]) ? $type_arr[$val['vvl_type_id']] : '未知';
                $val['vvl_playurl'] = '<a href="'.$val['vvl_playurl'].'" target="_blank">链接</a>【' .$v->remap_source_type($val['vvl_sourcetype']).'】';
                $val['vvl_playurl_get'] = empty($val['vvl_playurl_get'])?'<input type="button"  value="重新抓取" class="btn green refetch" data-value="' .$val['id']. '" />':'<a href="'.$val['vvl_playurl_get'].'" target="_blank">点击打开</a>';
                $val['vvl_imgurl'] = empty($val['vvl_imgurl'])?'暂无':'<img width="65" height="45" src="'. $val['vvl_imgurl'].'" />';
                $val['vvl_imgurl_get'] = empty($val['vvl_imgurl_get'])?'暂无':'<img width="65" height="45" src="'.$GLOBALS['IMAGE_DOMAIN'].$val['vvl_imgurl_get'].'" />';
                $val['va_show'] = $show_arr[$val['va_isshow']];
                $val['in_date'] = empty($val['in_date']) ? '' : date('Y-m-d H:i:s',$val['in_date']);
                $val['vvl_upload_time'] = empty($val['vvl_upload_time']) ? '' : date('Y-m-d H:i:s',$val['vvl_upload_time']);
                $val['game_name'] = $this->video_model->get_relev_game_name($val['vvl_game_id']);
                $val['sys_app'] = '';
                if(!empty($val['vvl_server_url'])){
                    $str =  $val['vvl_package_name'] ;
                    $server_url = str_replace('http://kyxvideo.file.alimmdn.com', '', $val['vvl_server_url']);
                    $arr = explode('.', $val['vvl_server_url']);
                    //sourcetype=14主要是来自客户端上传
                    $val['sys_app'] .= '【<a href="'.(($val['vvl_sourcetype'] == 14) ? CDN_LESHI_URL_DOWN . $server_url : (CDN_LESHI_URL_DOWN.reset($arr))).'" target="_blank">乐视CDN下载</a>】';
                    $val['sys_app'] .= '【<a href="'.(($val['vvl_sourcetype'] == 14) ? $val['vvl_server_url'] : ('http://kyxservervideo.file.alimmdn.com'.reset($arr))).'" target="_blank">阿里百川下载</a>】';
                }else{
                    $val['vvl_server_url'] = '';
                }
                $val['vvl_tags_name'] = '';
                if(!empty($val['vvl_tags'])){
                    $arr = $this->video_model->tags_select($val['vvl_tags']);
                    $val['vvl_tags_name'] = $arr['tag_name_cn'];
                }

                //分类名称
                $category_arr = $this->video_model->get_category_name($val['vvl_category_id']);
                if(empty($category_arr)){
                    $category_name = '未分类';
                }else{
                    $category_name = empty($category_arr['p_category_name']) ? $category_arr['category_name'] : ($category_arr['p_category_name'].'【'.$category_arr['category_name'].'】');
                }
                $val['category_name'] = $category_name;
            }
        }
        echo $this->get_normol_json( $res[0],$s_echo,$res[1] );
    }
    
    /**
     * @name:upload_video_icon
     * @description: 上传视频头像
     * @author: Xiong Jianbang
     * @create: 2015-4-29 下午4:23:40
     **/
    public function upload_video_icon(){
        if( !$this->check_right( '140011' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $return = array( 'status' => 0 );
        if( !is_empty( $_FILES['upload_hi_icon']) ){
            $res = $this->upload_image( $_FILES['upload_hi_icon'], 'video_img' );	//上传图片
            $return = is_empty( $res ) ? $return : array( 'status' => 200, 'file' => $res );
        }
        echo json_encode( $return );
    }
    
    public function big_upload_video_icon(){
        if( !$this->check_right( '140011' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $return = array( 'status' => 0 );
        if( !is_empty( $_FILES['big_upload_hi_icon']) ){
            $res = $this->upload_image( $_FILES['big_upload_hi_icon'], 'video_img' );	//上传图片
            $return = is_empty( $res ) ? $return : array( 'status' => 200, 'file' => $res );
        }
        echo json_encode( $return );
    }
    
    /**
     * @name:video_list_edit
     * @description: 视频编辑页面
     * @author: Xiong Jianbang
     * @create: 2015-4-29 下午4:03:21
     **/
    public function video_list_edit(){
        if(!$this->check_right('140011')){
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $type_arr = $this->tmp_type_arr;
    
        //类别id
        $id = intval(get_var_value('id'));
        //游戏分类列表
        $data = array(
                'js' => array(
                        'App'=>'admin/scripts/app.js', //需要引入app.js
                        'Index'=>'admin/scripts/index.js', //需要引入index.js
                        'admin/plugins/bootstrap-fileinput/js/fileinput.js',			//上传按钮和预览图
                        'admin/plugins/jquery-file-upload/js/jquery.fileupload.js',		//上传组件
                        'FormValidation'=>'admin/scripts/muzhiwan.js/video-list.js'
                        
                )
        );
        $data['css'] = array(
                'admin/plugins/bootstrap-fileinput/css/fileinput.min.css',
        );
        $data['type_arr'] = $type_arr;
        if(!empty($id)){
            $data['data'] = $this->video_model->get_video_list_info($id);
        }
        $game_id = isset($data['data']['vvl_game_id']) ? intval($data['data']['vvl_game_id']) : 0;
        $data['author_arr'] = $this->video_model->get_author_list($game_id);
        $data['hi_arr'] = $this->video_model->get_hi_list($game_id);

        //关联游戏信息数组
        $game_arr = $this->video_model->get_relev_game_arr();
        $data['game_arr'] = $game_arr;
        $data['arr_tags'] = $this->video_model->tags_all();
        //视频来源
        $video = new video_parser();
        $data['arr_source'] = $video->get_source_arr();
        $this->display( $data, 'video_list_edit' );
    }
    
    
    /**
     * @name:video_info_save
     * @description: 视频信息保存
     * @author: Xiong Jianbang
     * @create: 2015-4-29 下午4:27:25
     **/
    public function video_info_save(){
        if( !$this->check_right( '140011' ) ){//如果没有权限
            $arr['status'] = 1;
            $arr['message'] = '您没有操作权限！';
            $this->callback_ajax( $arr );
        }
    
        //定义AJAX返回的数组
        $arr = array(
                'status'=>200,//执行状态(例如：200成功，301失败...),
                'message'=>'更新成功',//返回信息,
                'url'=>''//要跳转的地址
        );
    
        //获取操作数据
        $id = intval(get_var_post("id"));  //信息id
        
        $vvl_title = get_var_post("vvl_title"); //类别名称
        $vvl_type_id = intval(get_var_post("vvl_type_id"));  //类别标记
//         $vc_order =  intval(get_var_post("vc_order")); //排序号
//        $hi_icon =  trim(get_var_post("hi_icon")); //视频图片
        $hi_icon = get_var_value("hi_icon",False); //视频图片
        $big_vi_icon = get_var_value("big_vi_icon",False);
        $vvl_time = get_var_post("vvl_time");  
        $vvl_playurl = get_var_post("vvl_playurl");
        $vvl_playurl_get = get_var_post("vvl_playurl_get");
        $vvl_category_id = intval(get_var_post("vvl_category_id"));
        $vvl_sort = intval(get_var_post("vvl_sort"));
        $vvl_author_id = intval(get_var_post("vvl_author_id"));
        $vvl_count = intval(get_var_post("vvl_count"));
        $vvl_hi_id = intval(get_var_post("vvl_hi_id"));
        $bel_game = intval(get_var_post("bel_game"));
        $vvl_tags =  intval(get_var_post("vvl_tags"));
        $vvl_package_name =  trim(get_var_post("vvl_package_name"));
        $vc_p_id = intval(get_var_post("vc_p_id")); //二级分类id
        $vvl_server_url =  trim(get_var_post("vvl_server_url"));
        $vvl_letv_cdn =  trim(get_var_post("vvl_letv_cdn"));
        $vvl_video_id =  trim(get_var_post("vvl_video_id"));
        $vvl_source =  trim(get_var_post("vvl_source"));
        //在未填写包名时根据游戏id获取视频关联游戏包名 change by chenzhong 2015-11-07 添加游戏关联包名存储
        if(!empty($bel_game) && empty($vvl_package_name)){
            $vvl_package_name = $this->video_model->get_game_package_name($bel_game);
        }

        //用户id
        $uid = 0;
        if(!empty($vvl_author_id)){
            $author_data = $this->video_model->get_author_info($vvl_author_id);
            $uid = isset($author_data['va_uid']) ? intval($author_data['va_uid']) : 0;
        }

        $data = array(
            "vvl_title"=>$vvl_title,
            "vvl_type_id"=>$vvl_type_id,
            "vvl_imgurl_get"=>$hi_icon,
            "vvl_big_imgurl_get"=>$big_vi_icon,
            "vvl_time"=>$vvl_time,
            "vvl_playurl"=>$vvl_playurl,
            "vvl_playurl_get"=>$vvl_playurl_get,
            "vvl_category_id"=> !empty($vc_p_id) ? $vc_p_id : $vvl_category_id,
            'vvl_sort'=>$vvl_sort,
            "vvl_author_id"=>$vvl_author_id,
            "vvl_count"=>$vvl_count,
            "vvl_hi_id"=>$vvl_hi_id,
            "vvl_game_id" => $bel_game,
            "vvl_tags" => $vvl_tags,
            "vvl_package_name" => $vvl_package_name,
            "vvl_update_time" => time(),
            "vvl_server_url" => $vvl_server_url,
            "vvl_letv_cdn" => $vvl_letv_cdn,
            "vvl_uid" => $uid,
             "vvl_video_id" => $vvl_video_id,
             "vvl_sourcetype"=>$vvl_source,
        );

        //执行更新操作
        $url = '/admin/video/video_list';  //执行成功后返回列表页
        if(!empty($id)){
            if($this->video_model->video_info_update( $id, $data )){
                //1添加，2修改，3删除，4数据导入，5数据导出，6其他
                $tmp_log_msg = "视频信息信息更新成功,类别ID为：{$id}";
                $this->video_model->log_db_admin( $tmp_log_msg, 2, __CLASS__ );
                $arr['status'] = 200;
                $arr['message'] = '视频信息更新成功！';
                $arr['url'] = $url;
                $this->callback_ajax( $arr );
            }else{
                $arr['status'] = 3;
                $arr['message'] = '信息无变化';
                $this->callback_ajax( $arr );
            }
        }else{
            $data['vvl_sourcetype'] = 1;//kuaiyouxi
            $data['in_date'] = time();
            $data['vvl_upload_time'] = time();
        	if($this->video_model->video_info_add($data)){
        	    $arr['status'] = 200;
        	    $arr['message'] = '视频信息添加成功！';
        	    $arr['url'] = $url;
        	    $this->callback_ajax( $arr );
        	}else{
        	    $arr['status'] = 3;
        	    $arr['message'] = '视频信息添加失败';
        	    $this->callback_ajax( $arr );
        	}
        }
     
    }
    
    /**
     * @name:ajax_get_category_by_typeid
     * @description: 根据类型ID返回视频类别信息
     * @author: Xiong Jianbang
     **/
    public function ajax_get_category_by_typeid(){
        if( !$this->check_right( '140011' ) ){//如果没有权限
            $arr['status'] = 1;
            $arr['message'] = '您没有操作权限！';
            $this->callback_ajax( $arr );
        }
        $game_id = intval(get_var_post("game_id")); //游戏id
        $vvl_type_id = intval(get_var_post("vvl_type_id"));  //类别标记
        $category_id = intval(get_var_post("category_id")); //一级分类id
        $author_id = intval(get_var_post("vvl_author_id")); //作者id
        $temp = $this->video_model->get_category_arr($game_id,$vvl_type_id,$category_id,$author_id);
        if(!empty($temp)){
            $arr['status'] = 200;
            $arr['message'] =$temp;
            $this->callback_ajax( $arr );
        }
    }

    /**
     * @name:ajax_get_game_linkage_info
     * @description: 获取视频编辑游戏联动先关信息
     * @author: Chen Zhong
     * @create: 2015-10-09 14:25:25
     * @return: array
     **/
    public function ajax_get_game_linkage_info(){
        if( !$this->check_right( '140011' ) ){//如果没有权限
            $arr['status'] = 1;
            $arr['message'] = '您没有操作权限！';
            $this->callback_ajax( $arr );
        }
        $game_id = intval(get_var_post("game_id")); //游戏id
        $vvl_type_id = intval(get_var_post("vvl_type_id"));  //类别标记

        //一级分类数据
        $temp = $this->video_model->get_category_arr($game_id,$vvl_type_id);
        if(!empty($temp)){
            $arr['status'] = 200;
            $arr['category_msg'] = $temp;
        }

        //作者解说信息
        $temp = $this->video_model->get_author_list($game_id);
        $arr['author_msg'] = $temp;

        //英雄名称信息
        $temp = $this->video_model->get_hi_list($game_id);
        $arr['hero_msg'] = $temp;

        $this->callback_ajax( $arr );

    }

    /**
     * @name:ajax_get_category_info
     * @description: 获取视频分类编辑游戏联动分类
     * @author: Chen Zhong
     * @create: 2015-10-09 14:25:25
     * @return: array
     **/
    public function ajax_get_category_info(){
        if( !$this->check_right( '140008' ) ){//如果没有权限
            $arr['status'] = 1;
            $arr['message'] = '您没有操作权限！';
            $this->callback_ajax( $arr );
        }
        $game_id = intval(get_var_post("game_id")); //游戏id

        //一级分类数据
        $temp = $this->video_model->get_video_category($game_id);
        if(!empty($temp)){
            $arr['status'] = 200;
            $arr['category_msg'] = $temp;
        }

        $this->callback_ajax( $arr );

    }
    
    /**
     * @name:area_list
     * @description: 视频专区列表
     * @author: Xiong Jianbang
     * @create: 2015-4-21 上午11:58:07
     **/
    public function area_list(){
        //权限判断
        if( !$this->check_right( '140012' ) ){
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $data['js'] = array(
                'App'=>'admin/scripts/app.js', //需要引入app.js
                'Index'=>'admin/scripts/index.js', //需要引入index.js
                'admin/plugins/bootstrap-fileinput/js/fileinput.js',			//上传按钮和预览图
                'admin/plugins/jquery-file-upload/js/jquery.fileupload.js',		//上传组件
                'FormValidation'=> 'admin/scripts/muzhiwan.js/video-area-admin-table-managed.js',
        );
        $data['css'] = array(
                'admin/plugins/bootstrap-fileinput/css/fileinput.min.css',
                
        );
        $this->display($data, 'video_area_list');
    }
    
    /**
     * @name:video_exam_list
     * @description: 视频审核列表
     * @author: Xiong Jianbang
     * @create: 2015-4-29 下午6:00:08
     **/
    public function video_exam_list(){
      if( !$this->check_right( '140013' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $data = array(
                'js' =>array(
                        'App'=>'admin/scripts/app.js', //需要引入app.js
                        'Index'=>'admin/scripts/index.js', //需要引入index.js
                        'GameDatetimePicker' => 'admin/scripts/muzhiwan.js/game-datetime-picker.js', //日历
                        'FormValidation'=>'admin/scripts/muzhiwan.js/video-exam-list.js'
                )
        );
        $data['type_list'] = $this->tmp_type_arr;

        $data['h_id'] = intval(get_var_value('h_id')); //英雄id
        $data['v_id'] = intval(get_var_value('v_id')); //视频类型id
        $data['a_id'] = intval(get_var_value('a_id')); //作者id
        $data['game_id'] = intval(get_var_value('game_id'));//游戏ID 
        $data['package_list'] = $this->video_model->get_package_list();
        $data['hero_list'] = $this->video_model->get_hi_list();
        $video = new video_parser();
        $data['source'] = $video->get_source_arr();
        //关联游戏信息数组
        $game_arr = $this->video_model->get_relev_game_arr();
        $data['game_arr'] = $game_arr;
        $this->display( $data, 'video_exam_list' );
    }
    
    
    /**
     * @name:ajax_get_video_list_data
     * @description: 获取视频列表数据
     * @author: Xiong Jianbang
     * @create: 2015-4-29 下午5:59:58
     **/
    public function ajax_get_video_exam_list_data() {
    	if( !$this->check_right( '140013' ) ){//如果没有权限
    		$this->url_msg_goto( get_referer(), '您没有操作权限！' );
    	}
    
    	$type_arr = $this->tmp_type_arr;
    
    	$start_record = get_var_value( 'iDisplayStart' ); 	//从多少条开始查询
    	$page_size	  = get_var_value( 'iDisplayLength' ); 	//每页显示多少条记录
    	$s_echo		  = get_var_value( 'sEcho' );
    	$vvl_id = intval(get_var_value('vvl_id'));
    	$title = get_var_value('title'); //标题
    	$hi_id = intval(get_var_value('hi_id')); //英雄id
    	$uid = intval(get_var_value('uid'));//用户ID
    	$nickname = trim(get_var_value('nickname'));
    	$v_id = intval(get_var_value('v_id')); //视频类型id
    	$a_id = intval(get_var_value('a_id')); //作者id
    	$type_id = intval(get_var_value('type_id')); //类别标签
    	$source_id = intval(get_var_value('source_id')); //来源
    	$is_parsed = intval(get_var_value('is_parsed'));
    	$img_format = trim(get_var_value('img_format'));
    	$package_name = trim(get_var_value('package_name'));
    	$game_id = '';
    	$game_post = trim(get_var_value('game_id'));
    	$v_status = intval(get_var_value('v_status'));
    	$v_exam = intval(get_var_value('v_exam'));
    	$begin_time = trim(get_var_value('begin_time'));
    	$end_time = trim(get_var_value('end_time'));
    
    
    	$i_sort_column	=  intval(get_var_value( 'iSortCol_0' ));	    //排序列索引
    	$s_sort_order = empty($i_sort_column) ? 'desc' : get_var_value( 'sSortDir_0' );
    	$i_sort_column  = ($i_sort_column) ? $i_sort_column : 1;		//默认使用下标为1即id排序
    	$s_sort_column	= get_var_value( 'mDataProp_'.$i_sort_column ); //获取列名
    
    	if(!empty($game_post)){
    		$game_id = $game_post;
    	}
    	$bel_game = trim(get_var_value('bel_game'));
    	if(!empty($bel_game)){
    		$game_id = $bel_game;
    	}
    	//用户上传而且小于3分钟的视频
    	$conditions   = '  AND  vvl_time>="03:00"  AND  vvl_sourcetype=14 '; //查询条件
    
    	if(!empty($v_exam)){
    		$conditions .= " AND `vvl_is_exam` = ".$v_exam;
    	}else{
    		$conditions .= " AND `vvl_is_exam` = 1";
    	}
    	
    	if(!empty($vvl_id)){
    		$conditions .= " AND `id` = ".$vvl_id;
    	}
    	if(!empty($hi_id)){
    		$conditions .= " AND `vvl_hi_id` = ".$hi_id;
    	}
    	if(!empty($uid)){
    		$conditions .= " AND `vvl_uid` = ".$uid;
    	}
    	if(!empty($nickname)){
    		$uids = $this->member_model->get_uids_by_nickname($nickname);
    		if(!empty($uids)){
    			$arr_uid = array();
    			foreach ($uids as $value) {
    				$arr_uid[] = $value['uid'];
    			}
    			$str_uid = implode(',', $arr_uid);
    			$conditions .= " AND `vvl_uid` IN ({$str_uid}) ";
    		}
    	}
    	if(!empty($v_id)){
    		$conditions .= " AND `vvl_category_id` = ".$v_id;
    	}
    	if(!empty($a_id)){
    		$conditions .= " AND `vvl_author_id` = ".$a_id;
    	}
    	if(!empty($type_id)){
    		$conditions .= " AND `vvl_type_id` = ".$type_id;
    	}
    	if(!empty($title)){
    		$conditions .= " AND (`vvl_title` LIKE '%".$title."%')";
    	}
    	if(!empty($source_id)){
    		$conditions .= " AND `vvl_sourcetype` = ".$source_id;
    	}
    	if(!empty($game_id)){
    		$conditions .= " AND `vvl_game_id` = ".$game_id;
    	}
    	if(!empty($v_status)){
    		$conditions .= " AND `va_isshow` = ".$v_status;
    	}
    	if(!empty($is_parsed)){
    		switch ($is_parsed) {
    			case 1:
    				$conditions .= " AND `vvl_playurl_get` <> '' ";
    				break;
    			case 2:
    				$conditions .= " AND `vvl_playurl_get` = '' ";
    				break;
    		}
    	}
    	if(!empty($img_format)){
    		$conditions .= " AND (`vvl_imgurl_get` LIKE '%".$img_format."%')";
    	}
    	if(!empty($package_name)){
    		$conditions .= " AND (`vvl_package_name`= '{$package_name}')";
    	}
    
    	if(!empty($begin_time) && empty($end_time)){
    		$begin_time = strtotime($begin_time);
    		$conditions .= " AND `in_date` >= {$begin_time}";
    	}
    	if(!empty($begin_time) && !empty($end_time)){
    		$begin_time = strtotime($begin_time);
    		$end_time = strtotime($end_time);
    		$conditions .= " AND `in_date` >= {$begin_time} AND  `in_date` <= {$end_time}";
    	}
//     echo $conditions;
    
    	//获取视频资料导入列表数据
    	$res = $this->video_model->ajax_get_video_list_data( $start_record, $page_size, $conditions,$s_sort_column,$s_sort_order );
    	$show_arr = array(1=>'显示',2=>'隐藏');
    	$exam_arr = array(1=>'未审核',2=>'已审核');
    	//参数转换
    	if(!empty($res[0])){
    		$this->load->library('video_parser');
    		$v = new video_parser();
    		foreach($res[0] as $key => &$val){
    			$user_info = $this->member_model->get_member_list_info($val['vvl_uid']);
    			$tmp = $this->video_model->get_hero_info($val['vvl_hi_id']);
    			$video_count = $this->video_model->get_video_count_by_uid($val['vvl_uid']);
    			$val['user_name'] = isset($user_info['nickname']) ? $user_info['nickname'] ."({$video_count})" : '未关联';
    			$val['hi_name_cn'] = isset($tmp['hi_name_cn']) ? $tmp['hi_name_cn'] : '';
    			$val['vvl_type_name'] = isset($type_arr[$val['vvl_type_id']]) ? $type_arr[$val['vvl_type_id']] : '未知';
    			$val['vvl_playurl'] = '<a href="'.$val['vvl_playurl'].'" target="_blank">链接</a>【' .$v->remap_source_type($val['vvl_sourcetype']).'】';
    			$val['vvl_playurl_get'] = empty($val['vvl_playurl_get'])?'<input type="button"  value="重新抓取" class="btn green refetch" data-value="' .$val['id']. '" />':'<a href="'.$val['vvl_playurl_get'].'" target="_blank">点击打开</a>';
    			$val['vvl_imgurl'] = empty($val['vvl_imgurl'])?'暂无':'<img width="65" height="45" src="'. $val['vvl_imgurl'].'" />';
    			$val['vvl_imgurl_get'] = empty($val['vvl_imgurl_get'])?'暂无':'<img width="65" height="45" src="'.$GLOBALS['IMAGE_DOMAIN'].$val['vvl_imgurl_get'].'" />';
    			$val['va_show'] = $show_arr[$val['va_isshow']];
    			$val['va_exam'] = $exam_arr[$val['vvl_is_exam']];
    			$val['in_date'] = empty($val['in_date']) ? '' : date('Y-m-d H:i:s',$val['in_date']);
    			$val['vvl_upload_time'] = empty($val['vvl_upload_time']) ? '' : date('Y-m-d H:i:s',$val['vvl_upload_time']);
    			$val['game_name'] = $this->video_model->get_relev_game_name($val['vvl_game_id']);
    			$val['sys_app'] = '';
    			if(!empty($val['vvl_server_url'])){
    				$str =  $val['vvl_package_name'] ;
    				$server_url = str_replace('http://kyxvideo.file.alimmdn.com', '', $val['vvl_server_url']);
    				$arr = explode('.', $val['vvl_server_url']);
    				//sourcetype=14主要是来自客户端上传
    				$val['sys_app'] .= '【<a href="'.(($val['vvl_sourcetype'] == 14) ? CDN_LESHI_URL_DOWN . $server_url : (CDN_LESHI_URL_DOWN.reset($arr))).'" target="_blank">乐视CDN下载</a>】';
    				$val['sys_app'] .= '【<a href="'.(($val['vvl_sourcetype'] == 14) ? $val['vvl_server_url'] : ('http://kyxservervideo.file.alimmdn.com'.reset($arr))).'" target="_blank">阿里百川下载</a>】';
    			}else{
    				$val['vvl_server_url'] = '';
    			}
    			$val['vvl_tags_name'] = '';
    			if(!empty($val['vvl_tags'])){
    				$arr = $this->video_model->tags_select($val['vvl_tags']);
    				$val['vvl_tags_name'] = $arr['tag_name_cn'];
    			}
    
    			//分类名称
    			$category_arr = $this->video_model->get_category_name($val['vvl_category_id']);
    			if(empty($category_arr)){
    				$category_name = '未分类';
    			}else{
    				$category_name = empty($category_arr['p_category_name']) ? $category_arr['category_name'] : ($category_arr['p_category_name'].'【'.$category_arr['category_name'].'】');
    			}
    			$val['category_name'] = $category_name;
    		}
    	}
    	echo $this->get_normol_json( $res[0],$s_echo,$res[1] );
    }
    
    /**
     * @name:ajax_get_area_list
     * @description: AJAX获取文章标签列表
     * @author: Xiong Jianbang
     * @create: 2014-11-6 下午8:37:13
     **/
    public function ajax_get_area_list(){
        if( !$this->check_right( '140012' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
    
        $start_record	= get_var_value( 'iDisplayStart' ); 	//从多少条开始查询
        $page_size		= get_var_value( 'iDisplayLength' ); 	//每页显示多少条记录
        $s_echo			= get_var_value( 'sEcho' );
    
        $res = $this->video_model->ajax_get_area_data($start_record, $page_size );
        if(!empty($res[0])){
            foreach($res[0] as $key => &$val){
                $val['va_show'] = $val['va_isshow']==1? '显示':'隐藏';
                $val['va_pic'] = empty($val['va_pic'])?'暂无':'<img width="100" height="100" src="'. $GLOBALS['IMAGE_DOMAIN'].$val['va_pic'].'" />';
            }
        }
        if( !is_empty($res) ){
            echo $this->get_normol_json( $res[0], $s_echo, $res[1] );
        }
    }
    
    /**
     * @name:game_ico_up_image
     * @description:上传游戏ICO图片
     * @create: 2014-10-11
     * @author: xiongjianbang
     */
    public function game_ico_up_image(){
        if( !$this->check_right( '140012' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $return = array( 'status' => 0 );
        if( !is_empty( $_FILES['upload_area_image']) ){
            $res = $this->upload_image( $_FILES['upload_area_image'], 'video' );	//上传图片
            $return = is_empty( $res ) ? $return : array( 'status' => 200, 'file' => $res );
        }
        echo json_encode( $return );
    }
    
    /**
     * @name:area_add
     * @description: 添加或者编辑专区表单
     * @author: Xiong Jianbang
     * @create: 2014-11-6 下午9:06:15
     **/
    public function area_add(){
        if( !$this->check_right( '140012' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $va_id	=  intval(get_var_value( 'va_id'));
        $arr['va_name']	= trim(get_var_value( 'va_name' ));
        $arr['va_intro']	= trim(get_var_value( 'va_intro'));
        $arr['va_order']	= intval(trim(get_var_value( 'va_order')));
        $arr['va_isshow']	= intval(trim(get_var_value( 'va_isshow')));
        if(empty($arr['va_name'])){
            exit(json_encode(array('msg'=>'专辑名称不能为空，请检查!','status'=>200)));
        }
        $f_image_key	= trim(get_var_value( 'f_image_key'));
        if(!empty($f_image_key)){
            $arr['va_pic'] = $f_image_key;
        }
        //添加
        if(empty($va_id)){
            $arr['va_in_uid'] = $_SESSION['sys_admin_id'];
            $arr['va_in_name'] = $_SESSION['sys_admin_name'];
            if($this->video_model->add_area($arr)){
                $this->video_model->log_db_admin( '添加视频专辑:'.$arr['va_name'], 1, __CLASS__ );
                exit(json_encode(array('msg'=>'添加成功!','status'=>200)));
            }else{
                exit(json_encode(array('msg'=>'添加失败!','status'=>200)));
            }
        }else{//编辑
            $this->video_model->update_area_by_id($arr,$va_id);
            $this->video_model->log_db_admin( '修改视频专辑:'.$arr['va_name'], 2, __CLASS__ );
            exit(json_encode(array('msg'=>'修改成功!','status'=>200)));
        }
    }
    
    /**
     * @name:ajax_edit_tag_form
     * @description: AJAX生成修改表单
     * @author: Xiong Jianbang
     * @create: 2014-11-6 下午10:15:43
     **/
    public function ajax_edit_tag_form(){
        if( !$this->check_right( '140012' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $va_id	=  intval(get_var_value( 'va_id'));
        if(empty($va_id))exit;
        $arr = $this->video_model->get_area_info_by_id($va_id);
        if(!empty($arr)){
            echo json_encode($arr);
        }
    }
    
    /**
     * @name:ajax_del_tag
     * @description: 删除文章标签
     * @author: Xiong Jianbang
     * @create: 2015-3-31 下午3:59:04
     **/
    public function ajax_del_area(){
        if( !$this->check_right( '140012' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $va_id	=  intval(get_var_value( 'va_id'));
        if(empty($va_id)){
            exit(json_encode(array('msg'=>'参数错误','status'=>400)));
        }
        if($this->video_model->delete_area_by_id($va_id)){
            $this->video_model->log_db_admin( '删除专辑ID:'.$va_id, 3, __CLASS__ );
            exit(json_encode(array('msg'=>'删除成功','status'=>200)));
        }else{
            exit(json_encode(array('msg'=>'删除失败','status'=>400)));
        }
    }
    
    /**
     * @name:area_video_list
     * @description: 专辑的视频列表
     * @author: Xiong Jianbang
     * @create: 2015-4-30 上午10:52:29
     **/
    public function area_video_list($va_id=0){
        //权限判断
        if( !$this->check_right( '140012' ) ){
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $data['js'] = array(
                'App'=>'admin/scripts/app.js', //需要引入app.js
                'Index'=>'admin/scripts/index.js', //需要引入index.js
                'admin/scripts/muzhiwan.js/video-area-list-table-managed.js',
        );
        $data['va_id'] = $va_id;
        $res = $this->video_model->get_area_info($va_id);
        $data['va_name'] = isset($res['va_name'])?$res['va_name']:'';
        $this->display($data, 'video_area_video_list');
    }
    
    /**
     * @name:ajax_show_area
     * @description: 显示/隐藏视频专辑
     * @author: Xiong Jianbang
     * @create: 2015-3-31 下午3:59:04
     **/
    public function ajax_show_area(){
        if( !$this->check_right( '140012' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $va_id	=  intval(get_var_value( 'va_id'));
        $vg_type=  intval(get_var_value( 'vg_type'));
        if(empty($va_id)){
            exit(json_encode(array('msg'=>'参数错误','status'=>400)));
        }
        switch ($vg_type) {
        	case 1:
        	    $arr['va_isshow'] = 1;
            	break;
        	case 2:
        	    $arr['va_isshow'] = 2;
            	break;
        }
        if( $this->video_model->update_area_by_id($arr,$va_id)){
            $this->video_model->log_db_admin( '处理视频专辑ID:'.$va_id, 3, __CLASS__ );
            exit(json_encode(array('msg'=>'处理成功','status'=>200)));
        }else{
            exit(json_encode(array('msg'=>'处理失败','status'=>400)));
        }
    }
    
    /**
     * @name:ajax_get_area_list
     * @description: AJAX获取文章标签列表
     * @author: Xiong Jianbang
     * @create: 2014-11-6 下午8:37:13
     **/
    public function ajax_get_area_video_list(){
        if( !$this->check_right( '140012' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
    
        $start_record	= get_var_value( 'iDisplayStart' ); 	//从多少条开始查询
        $page_size		= get_var_value( 'iDisplayLength' ); 	//每页显示多少条记录
        $s_echo			= get_var_value( 'sEcho' );
        $va_id			    = intval(get_var_value( 'va_id' ));
        $conditon['va_id'] = $va_id;
    
        $res = $this->video_model->ajax_get_area_video_data($start_record, $page_size,$conditon );
        if(!empty($res[0])){
            foreach($res[0] as $key => &$val){
                $val['vvl_playurl'] = '<a href="'.$val['vvl_playurl'].'" target="_blank">'.$val['vvl_playurl'].'</a>';
                $val['vvl_playurl_get'] = empty($val['vvl_playurl_get'])?'':'<a href="'.$val['vvl_playurl_get'].'" target="_blank">点击打开</a>';
                $val['vvl_imgurl'] = empty($val['vvl_imgurl'])?'暂无':'<img width="50" height="50" src="'. $val['vvl_imgurl'].'" />';
                $val['va_isshow'] = ($val['va_isshow'] == 1) ? '显示' : '隐藏';
            }
        }
        if( !is_empty($res) ){
            echo $this->get_normol_json( $res[0], $s_echo, $res[1] );
        }
    }
    
    /**
     * @name:ajax_del_tag
     * @description: 删除文章标签
     * @author: Xiong Jianbang
     * @create: 2015-3-31 下午3:59:04
     **/
    public function ajax_del_area_video(){
        if( !$this->check_right( '140012' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $vavi_id	=  intval(get_var_value( 'vavi_id'));
        if(empty($vavi_id)){
            exit(json_encode(array('msg'=>'参数错误','status'=>400)));
        }
        if($this->video_model->delete_area_video_by_id($vavi_id)){
            $this->video_model->log_db_admin( '删除视频和专辑关系表的ID:'.$vavi_id, 3, __CLASS__ );
            exit(json_encode(array('msg'=>'删除成功','status'=>200)));
        }else{
            exit(json_encode(array('msg'=>'删除失败','status'=>400)));
        }
    }
    
    /**
     * @name:area_video_add
     * @description: 视频添加到指定专辑的展示页面
     * @author: Xiong Jianbang
     * @create: 2015-4-30 下午12:13:13
     **/
    public function area_video_add(){
        if( !$this->check_right( '140012' ) ){
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $data['js'] = array(
                'App'=>'admin/scripts/app.js', //需要引入app.js
                'Index'=>'admin/scripts/index.js', //需要引入index.js
                'admin/scripts/muzhiwan.js/video-area-video-table-managed.js',
        );
        $va_id	=  intval(get_var_value( 'va_id'));
        $data['va_id'] = $va_id;
        $data['type_list'] = $this->tmp_type_arr;
        
        $data['h_id'] = intval(get_var_value('h_id')); //英雄id
        $data['v_id'] = intval(get_var_value('v_id')); //视频类型id
        $data['a_id'] = intval(get_var_value('a_id')); //作者id
        $data['hero_list'] = $this->video_model->get_hi_list();
        $res = $this->video_model->get_area_info($va_id);
        $data['va_name'] = isset($res['va_name'])?$res['va_name']:'';

        //关联游戏信息数组
        $game_arr = $this->video_model->get_relev_game_arr();
        $data['game_arr'] = $game_arr;

        $this->display($data, 'video_area_add_video_list');
    }
    
    /**
     * @name:ajax_add_area_video
     * @description: 将视频添加到指定专辑里
     * @author: Xiong Jianbang
     * @create: 2015-4-30 下午12:30:56
     **/
    public function ajax_add_area_video(){
        if( !$this->check_right( '140012' ) ){
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $va_id	=  intval(get_var_value( 'va_id'));
        $vvl_id	=  intval(get_var_value( 'vvl_id'));
        $data = array(
        	'va_id' => $va_id,
            'vvl_id' => $vvl_id,
            'created' => time(),
        );
        if($this->video_model->check_area_video($vvl_id,$va_id)){
            exit(json_encode(array('msg'=>'该视频已存在此专辑','status'=>400)));
        }
        if($this->video_model->add_area_video($data)){
            $this->video_model->log_db_admin( "添加视频和专辑关系表的专区ID:{$va_id}，视频ID：{$vvl_id}", 3, __CLASS__ );
            exit(json_encode(array('msg'=>'添加成功','status'=>200)));
        }else{
            exit(json_encode(array('msg'=>'添加失败','status'=>400)));
        }
    }
    
    /**
     * @name:ajax_get_area_video_list_data
     * @description: 专辑待选的视频列表
     * @author: Xiong Jianbang
     * @create: 2015-4-30 下午1:21:43
     **/
    public function ajax_get_area_video_list_data() {
        if( !$this->check_right( '140012' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
    
        $type_arr = $this->tmp_type_arr;
    
        $start_record = get_var_value( 'iDisplayStart' ); 	//从多少条开始查询
        $page_size	  = get_var_value( 'iDisplayLength' ); 	//每页显示多少条记录
        $s_echo		  = get_var_value( 'sEcho' );
        $i_sort_column	=  intval(get_var_value( 'iSortCol_0' ));	    //排序列索引
        $i_sort_column  = ($i_sort_column) ? $i_sort_column : 6;		//默认使用下标为1即id排序
        $s_sort_column	= get_var_value( 'mDataProp_'.$i_sort_column ); //获取列名
        $s_sort_order	= get_var_value( 'sSortDir_0' );				//正序或倒序
        $title = get_var_value('title'); //标题
//        $hi_id = intval(get_var_value('hi_id')); //英雄id
//        $v_id = intval(get_var_value('v_id')); //视频类型id
//        $a_id = intval(get_var_value('a_id')); //作者id
        $type_id = intval(get_var_value('type_id')); //类别标签
        $va_isshow = intval(get_var_value('va_isshow')); //显示状态
        $bel_game = intval(get_var_value('bel_game')); //所属游戏
        $va_id =  intval(get_var_value('va_id'));//专辑ID号
        
     
        
        $conditions   = ''; //查询条件
    
//        if(!empty($hi_id)){
//            $conditions .= " AND `vvl_hi_id` = ".$hi_id;
//        }
//        if(!empty($v_id)){
//            $conditions .= " AND `vvl_category_id` = ".$v_id;
//        }
//        if(!empty($a_id)){
//            $conditions .= " AND `vvl_author_id` = ".$a_id;
//        }
        if(!empty($type_id)){
            $conditions .= " AND `vvl_type_id` = ".$type_id;
        }
        if(!empty($va_isshow)){
            $conditions .= " AND `va_isshow` = ".$va_isshow;
        }
        if(!empty($bel_game)){
            $conditions .= " AND `vvl_game_id` = ".$bel_game;
        }
        if(!empty($title)){
            $conditions .= " AND (`vvl_title` LIKE '%".$title."%')";
        }
        //获取视频资料导入列表数据
        $res = $this->video_model->ajax_get_video_list_data( $start_record, $page_size, $conditions,$s_sort_column,$s_sort_order);
        //参数转换
        if(!empty($res[0])){
            foreach($res[0] as $key => &$val){
                $tmp = $this->video_model->get_hero_info($val['vvl_hi_id']);
                $val['hi_name_cn'] = isset($tmp['hi_name_cn']) ? $tmp['hi_name_cn'] : '';
                $val['va_isshow'] = ($val['va_isshow'] == 1) ? '显示' : '隐藏';
                $val['vvl_type_name'] = isset($type_arr[$val['vvl_type_id']]) ? $type_arr[$val['vvl_type_id']] : '未知';
                $val['vvl_playurl'] = '<a href="'.$val['vvl_playurl'].'" target="_blank">'.$val['vvl_playurl'].'</a>';
                $val['vvl_playurl_get'] = empty($val['vvl_playurl_get'])?'':'<a href="'.$val['vvl_playurl_get'].'" target="_blank">点击打开</a>';
                $val['vvl_imgurl'] = empty($val['vvl_imgurl'])?'暂无':'<img width="65" height="45" src="'.$val['vvl_imgurl'].'" />';
                $val['vvl_is_area'] = ($this->video_model->check_area_video($val['id'],$va_id))?1:0;
                $val['in_date'] = date('Y-m-d',$val['in_date']);
                $val['game_name'] = $this->video_model->get_relev_game_name($val['vvl_game_id']);
            }
        }
    
        echo $this->get_normol_json( $res[0],$s_echo,$res[1] );
    }
    
    /**
     * @name:batch_add_video_area
     * @description: 批量给专辑添加视频
     * @author: Xiong Jianbang
     * @create: 2015-5-4 上午10:53:11
     **/
    public function batch_add_video_area(){
        if( !$this->check_right( '140012' ) ){
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $ids = get_var_value('ids'); //id列表
        $va_id	=  intval(get_var_value( 'va_id'));
        if(empty($ids)){
            exit(json_encode(array('msg'=>'ID列表不能为空','status'=>400)));
        }
        $arr_id = explode(',', $ids);
        foreach ($arr_id as $value) {
            $data = array(
                    'va_id' => $va_id,
                    'vvl_id' => $value,
            );
            if($this->video_model->check_area_video($value,$va_id)){
                continue;
            }
            $this->video_model->add_area_video($data);
            $this->video_model->log_db_admin( '批量给视频专辑ID'.$va_id.'添加视频ID:'.$value, 1, __CLASS__ );
        }
        exit(json_encode(array('msg'=>'处理成功','status'=>200)));
    }
    
    /**
     * @name:batch_cancle_video_area
     * @description: 批量取消
     * @author: Xiong Jianbang
     * @create: 2015-5-4 下午5:21:48
     **/
    public function batch_cancle_video_area(){
        if( !$this->check_right( '140012' ) ){
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $ids = get_var_value('ids'); //id列表
        $va_id	=  intval(get_var_value( 'va_id'));
        if(empty($ids)){
            exit(json_encode(array('msg'=>'ID列表不能为空','status'=>400)));
        }
        $arr_id = explode(',', $ids);
        foreach ($arr_id as $value) {
            $this->video_model->cancle_area_video($va_id,$value);
        }
        exit(json_encode(array('msg'=>'处理成功','status'=>200)));
    }
    
    /**
     * @name:batch_show_video
     * @description: 批量显示视频
     * @author: Xiong Jianbang
     * @create: 2015-5-5 上午11:51:00
     **/
    public function batch_show_video(){
        if( !$this->check_right( '140011' ) ){
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $ids = get_var_value('ids'); //id列表
        if(empty($ids)){
            exit(json_encode(array('msg'=>'ID列表不能为空','status'=>400)));
        }
        $arr_id = explode(',', $ids);
        $count = count($arr_id);
        if($count>1){
            foreach ($arr_id as $value) {
                $arr_return = $this->video_model->get_video_info_by_id($value);
                if(empty($arr_return)){
                	continue;
                }
                if(empty($arr_return['vvl_video_id'])){
                    continue;
                }
                $data = array('va_isshow'=>1);
                $this->video_model->video_info_update( $value, $data );
                $this->video_model->log_db_admin( '显示视频ID:'.$value, 2, __CLASS__ );
            }
        }else{
            $arr_return = $this->video_model->get_video_info_by_id($ids);
            if(empty($arr_return)){
                 exit(json_encode(array('msg'=>'参数错误','status'=>400)));
            }
            if(empty($arr_return['vvl_video_id'])){
                 exit(json_encode(array('msg'=>'还没同步到优酷CDN','status'=>400)));
            }
            $data = array('va_isshow'=>1);
            $this->video_model->video_info_update( $ids, $data );
            $this->video_model->log_db_admin( '显示视频ID:'.$ids, 2, __CLASS__ );
            
        }
        exit(json_encode(array('msg'=>'处理成功','status'=>200)));
    }
    
    
    /**
     * @name:batch_show_exam_video
     * @description: 批量显示视频，并通过审核
     * @author: Xiong Jianbang
     * @create: 2015-5-5 上午11:51:00
     **/
    public function batch_show_exam_video(){
    	if( !$this->check_right( '140013' ) ){
    		$this->url_msg_goto( get_referer(), '您没有操作权限！' );
    	}
    	$ids = get_var_value('ids'); //id列表
    	if(empty($ids)){
    		exit(json_encode(array('msg'=>'ID列表不能为空','status'=>400)));
    	}
    	$arr_id = explode(',', $ids);
    	$count = count($arr_id);
    	if($count>1){
    		foreach ($arr_id as $value) {
    			$arr_return = $this->video_model->get_video_info_by_id($value);
    			if(empty($arr_return)){
    				continue;
    			}
    			if(empty($arr_return['vvl_video_id'])){
    				continue;
    			}
    			$data = array('va_isshow'=>1,'vvl_is_exam'=>2);
    			$this->video_model->video_info_update( $value, $data );
    			$this->video_model->log_db_admin( '显示视频ID:'.$value, 2, __CLASS__ );
    		}
    	}else{
    		$arr_return = $this->video_model->get_video_info_by_id($ids);
    		if(empty($arr_return)){
    			exit(json_encode(array('msg'=>'参数错误','status'=>400)));
    		}
    		if(empty($arr_return['vvl_video_id'])){
    			exit(json_encode(array('msg'=>'还没同步到优酷CDN','status'=>400)));
    		}
    		$data = array('va_isshow'=>1,'vvl_is_exam'=>2);
    		$this->video_model->video_info_update( $ids, $data );
    		$this->video_model->log_db_admin( '显示视频ID:'.$ids, 2, __CLASS__ );
    
    	}
    	exit(json_encode(array('msg'=>'处理成功','status'=>200)));
    }
    
    
    
    /**
     * @name:batch_exam_video
     * @description: 通过审核
     * @author: Xiong Jianbang
     * @create: 2015-5-5 上午11:51:00
     **/
    public function batch_exam_video(){
    	if( !$this->check_right( '140013' ) ){
    		$this->url_msg_goto( get_referer(), '您没有操作权限！' );
    	}
    	$ids = get_var_value('ids'); //id列表
    	if(empty($ids)){
    		exit(json_encode(array('msg'=>'ID列表不能为空','status'=>400)));
    	}
    	$arr_id = explode(',', $ids);
    	$count = count($arr_id);
    	if($count>1){
    		foreach ($arr_id as $value) {
    			$arr_return = $this->video_model->get_video_info_by_id($value);
    			if(empty($arr_return)){
    				continue;
    			}
    			if(empty($arr_return['vvl_video_id'])){
    				continue;
    			}
    			$data = array('vvl_is_exam'=>2);
    			$this->video_model->video_info_update( $value, $data );
    			$this->video_model->log_db_admin( '显示视频ID:'.$value, 2, __CLASS__ );
    		}
    	}else{
    		$arr_return = $this->video_model->get_video_info_by_id($ids);
    		if(empty($arr_return)){
    			exit(json_encode(array('msg'=>'参数错误','status'=>400)));
    		}
    		$data = array('vvl_is_exam'=>2);
    		$this->video_model->video_info_update( $ids, $data );
    		$this->video_model->log_db_admin( '显示视频ID:'.$ids, 2, __CLASS__ );
    
    	}
    	exit(json_encode(array('msg'=>'处理成功','status'=>200)));
    }
    
    /**
     * @name:delete_video
     * @description: 删除视频信息
     * @author: Xiong Jianbang
     * @create: 2015-12-7 上午10:20:25
     **/
    public function delete_video(){
        if( !$this->check_right( '140011' ) ){
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $ids = get_var_value('ids'); //id列表
        if(empty($ids)){
            exit(json_encode(array('msg'=>'ID列表不能为空','status'=>400)));
        }
        $this->video_model->video_info_delete( $ids );
        $this->video_model->log_db_admin( '删除视频ID:'.$ids, 3, __CLASS__ );
        exit(json_encode(array('msg'=>'处理成功','status'=>200)));
    }
    
    /**
     * @name:batch_hidden_video
     * @description: 批量隐藏视频
     * @author: Xiong Jianbang
     * @create: 2015-5-5 上午11:57:31
     **/
    public function batch_hidden_video(){
        if( !$this->check_right( '140011' ) ){
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $ids = get_var_value('ids'); //id列表
        if(empty($ids)){
            exit(json_encode(array('msg'=>'ID列表不能为空','status'=>400)));
        }
        $arr_id = explode(',', $ids);
        foreach ($arr_id as $value) {
            $data = array('va_isshow'=>2);
            $this->video_model->video_info_update( $value, $data );
            $this->video_model->log_db_admin( '隐藏视频ID:'.$value, 2, __CLASS__ );
        }
        exit(json_encode(array('msg'=>'处理成功','status'=>200)));
    }
    
    /**
     * @name:batch_hidden_exam_video
     * @description: 批量隐藏视频，并通过审核
     * @author: Xiong Jianbang
     * @create: 2015-5-5 上午11:57:31
     **/
    public function batch_hidden_exam_video(){
    	if( !$this->check_right( '140013' ) ){
    		$this->url_msg_goto( get_referer(), '您没有操作权限！' );
    	}
    	$ids = get_var_value('ids'); //id列表
    	if(empty($ids)){
    		exit(json_encode(array('msg'=>'ID列表不能为空','status'=>400)));
    	}
    	$arr_id = explode(',', $ids);
    	foreach ($arr_id as $value) {
    		$data = array('va_isshow'=>2,'vvl_is_exam'=>2);
    		$this->video_model->video_info_update( $value, $data );
    		$this->video_model->log_db_admin( '隐藏视频ID:'.$value, 2, __CLASS__ );
    	}
    	exit(json_encode(array('msg'=>'处理成功','status'=>200)));
    }

    /**
     * @name:batch_recom_video
     * @description: 批量推荐视频
     * @author: Xiong Jianbang
     * @create: 2015-5-5 上午11:57:31
     **/
    public function batch_recom_video(){
        if( !$this->check_right( '140011' ) ){
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $ids = get_var_value('ids'); //id列表
        $status = get_var_value('status'); //推荐状态
        if(empty($ids)){
            exit(json_encode(array('msg'=>'ID列表不能为空','status'=>400)));
        }
        $arr_id = explode(',', $ids);
        foreach ($arr_id as $value) {
            $data = array('vvl_recommend'=>($status - 1),'vvl_update_time'=>time());
            $this->video_model->video_info_update( $value, $data );
            $msg = ($status == 2) ? '推荐视频成功' : '取消推荐视频成功';
            $this->video_model->log_db_admin( $msg.',视频ID:'.$value, 2, __CLASS__ );
        }
        exit(json_encode(array('msg'=>'处理成功','status'=>200)));
    }
    
    /**
     * @name:batch_top_video
     * @description: 批量编辑推荐
     * @author: Xiong Jianbang
     * @create: 2015-12-16 下午5:52:32
     **/
    public function batch_top_video(){
        if( !$this->check_right( '140011' ) ){
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $ids = get_var_value('ids'); //id列表
        $status = get_var_value('status'); //推荐状态
        if(empty($ids)){
            exit(json_encode(array('msg'=>'ID列表不能为空','status'=>400)));
        }
        $arr_id = explode(',', $ids);
        foreach ($arr_id as $value) {
            $data = array('vvl_top'=>($status - 1),'vvl_update_time'=>time());
            $this->video_model->video_info_update( $value, $data );
            $msg = ($status == 2) ? '编辑推荐视频成功' : '编辑取消推荐视频成功';
            $this->video_model->log_db_admin( $msg.',视频ID:'.$value, 2, __CLASS__ );
        }
        exit(json_encode(array('msg'=>'处理成功','status'=>200)));
    }
    
    
    /**
     * @name:batch_fetch_video
     * @description: 批量抓取视频
     * @author: Xiong Jianbang
     * @create: 2015-5-5 上午11:57:31
     **/
    public function batch_fetch_video(){
        if( !$this->check_right( '140011' ) ){
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $ids = get_var_value('ids'); //id列表
        if(empty($ids)){
            exit(json_encode(array('msg'=>'ID列表不能为空','status'=>400)));
        }
        $arr_id = explode(',', $ids);
        $video = new video_parser();
        foreach ($arr_id as $value) {
            $res = $this->video_model->get_video_list_info($value);
            if(empty($res)){
                $this->video_model->log_db_admin( '不存在记录，没有成功抓取视频ID:'.$value, 2, __CLASS__ );
                continue;
            }
            $vvl_playurl =  $res['vvl_playurl'];
            if(empty($vvl_playurl)){
                $this->video_model->log_db_admin( '待解析的网址，不存在，没有成功抓取视频ID:'.$value, 2, __CLASS__ );
                continue;
            }
            $video->set_url($vvl_playurl);
            $json = $video->parse();
            if(empty($json)){
                $this->video_model->log_db_admin( '没有获取解析地址，没有成功抓取视频ID:'.$value, 2, __CLASS__ );
                continue;
            }
            $arr = json_decode($json,TRUE);
            if($arr['status']==400){
                $this->video_model->log_db_admin( '没有获取解析地址，没有成功抓取视频ID:'.$value, 2, __CLASS__ );
                continue;
            }
            $url = $arr['msg'];
            if(empty($url)){
                $this->video_model->log_db_admin( '没有获取解析地址，没有成功抓取视频ID:'.$value, 2, __CLASS__ );
                continue;
            }
            $data['vvl_playurl_get'] = $url;
            $data['vvl_sourcetype'] = $video->map_source_type($arr['type']);
            $data['vvl_video_id'] = $arr['vid'];
            $this->video_model->video_info_update($value,$data);
            $this->video_model->log_db_admin( '成功抓取视频ID:'.$value, 2, __CLASS__ );
        }
        exit(json_encode(array('msg'=>'处理成功','status'=>200)));
    }
    
    /**
     * @name:batch_move_to_game
     * @description: 批量转移视频到指定游戏
     * @author: Xiong Jianbang
     * @create: 2015-5-5 上午11:57:31
     **/
    public function batch_move_to_game(){
    	if( !$this->check_right( '140011' ) ){
    		$this->url_msg_goto( get_referer(), '您没有操作权限！' );
    	}
    	$ids = get_var_value('ids'); //id列表
    	if(empty($ids)){
    		exit(json_encode(array('msg'=>'ID列表不能为空','status'=>400)));
    	}
    	$arr_id = explode(',', $ids);
    	$game_id = get_var_value('game_id');
    	if(empty($game_id)){
    		exit(json_encode(array('msg'=>'请选择游戏','status'=>400)));
    	}
    	$video = new video_parser();
    	foreach ($arr_id as $value) {
    		$data['vvl_game_id'] = $game_id;
    		$this->video_model->video_info_update($value,$data);
    		$this->video_model->log_db_admin( '成功转移视频ID:'.$value, 2, __CLASS__ );
    	}
    	exit(json_encode(array('msg'=>'处理成功','status'=>200)));
    }
    
    
    /**
     * @name:batch_youku_cdn_video
     * @description: 批量处理同步视频到优酷CDN
     * @author: Xiong Jianbang
     * @create: 2015-12-7 上午11:23:51
     **/
    public function batch_youku_cdn_video(){
        if( !$this->check_right( '140011' ) ){
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $ids = get_var_value('ids'); //id列表
        if(empty($ids)){
            exit(json_encode(array('msg'=>'ID列表不能为空','status'=>400)));
        }
        set_time_limit(0);
        ini_set('memory_limit', '128M');
        $arr_id = explode(',', $ids);
        require APPPATH.'/libraries/umeng_video/manage_client.class.php'; //
        foreach ($arr_id as $value) {
            $video_id = intval($value);
            if($this->push_youku_cdn($video_id)){
            	
            }else{
                continue;
            }
        }
        exit(json_encode(array('msg'=>'处理成功','status'=>200)));
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
            continue;
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
        $video_ext = $files['extension'];
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
	
    /**
     * @name:refetch_url
     * @description: 重新抓取视频
     * @author: Xiong Jianbang
     * @create: 2015-5-5 上午11:57:31
     **/
    public function refetch_url(){
        if( !$this->check_right( '140011' ) ){
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $id = get_var_value('id');
        if(empty($id)){
            exit(json_encode(array('msg'=>'ID不能为空','status'=>400)));
        }
        $res = $this->video_model->get_video_list_info($id);
        if(empty($res)){
            exit(json_encode(array('msg'=>'不存在记录','status'=>400)));
        }
        $vvl_playurl =  $res['vvl_playurl'];
        if(empty($vvl_playurl)){
            exit(json_encode(array('msg'=>'待解析的网址，不存在','status'=>400)));
        }
        $video = new video_parser();
        $video->set_url($vvl_playurl);
        $json = $video->parse();
         if(empty($json)){
                exit(json_encode(array('msg'=>'没有获取解析地址','status'=>400)));
          }
          $arr = json_decode($json,TRUE);
          if($arr['status']==400){
              exit(json_encode(array('msg'=>'没有获取解析地址','status'=>400)));
          }
          $url = $arr['msg'];
          if(empty($url)){
                    exit(json_encode(array('msg'=>'没有获取解析地址','status'=>400)));
           }
          $data['vvl_playurl_get'] = $url;
          $data['va_isshow'] = 1;
          $data['vvl_sourcetype'] = $video->map_source_type($arr['type']);
          $data['vvl_video_id'] = $arr['vid'];
          $this->video_model->video_info_update($id,$data);
           exit(json_encode(array('msg'=>'处理成功','status'=>200)));
    }
    
    /**
     * @name:game_list
     * @description: 视频列表
     * @author: Xiong Jianbang
     * @create: 2015-4-29 下午6:00:08
     **/
    public function game_list(){
         //权限判断
        if( !$this->check_right( '140020' ) ){
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $data['js'] = array(
                'App'=>'admin/scripts/app.js', //需要引入app.js
                'Index'=>'admin/scripts/index.js', //需要引入index.js
                'admin/scripts/muzhiwan.js/SimpleAjaxUploader.js', //图片上传
                'admin/plugins/bootstrap-fileinput/js/fileinput.js',			//上传按钮和预览图
                'admin/plugins/jquery-file-upload/js/jquery.fileupload.js',		//上传组件
                'FormValidation'=> 'admin/scripts/muzhiwan.js/video-game-admin-table-managed.js',
        );
        $data['css'] = array(
                'admin/plugins/bootstrap-fileinput/css/fileinput.min.css',
        
        );

        //获取视频分类信息
        $data['video_type_arr'] = $this->video_model->get_video_type_arr();

        $this->display($data, 'video_game_list');
    }
    
    /**
     * @name:ajax_get_game_list
     * @description: AJAX获取文章标签列表
     * @author: Xiong Jianbang
     * @create: 2014-11-6 下午8:37:13
     **/
    public function ajax_get_game_list(){
        if( !$this->check_right( '140020' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $start_record	= get_var_value( 'iDisplayStart' ); 	//从多少条开始查询
        $page_size		= get_var_value( 'iDisplayLength' ); 	//每页显示多少条记录
        $s_echo			= get_var_value( 'sEcho' );
        $game_name			= get_var_value( 'game_name' ); //游戏名称
        $sub_recom			= intval(get_var_value( 'sub_recom' )); //订阅推荐

        $where = ' WHERE 1 ';
        if(!empty($game_name)){
            $where .= " AND `gi_name` LIKE '%".$game_name."%'";
        }

        if(!empty($sub_recom)){
            $where .= " AND `gi_sub_recommend` = ".$sub_recom;
        }

        //订阅推荐数组
        $sub_arr = array(
            1 => '未推荐',
            2 => '带视频推荐',
            3 => '不带视频推荐'
        );

        $res = $this->video_model->ajax_get_game_data($start_record, $page_size,$where );
        if(!empty($res[0])){
            foreach($res[0] as $key => &$val){
                $val['gi_show'] = ($val['gi_isshow']==1)?'显示':'隐藏';
                $val['gi_sub_recommend'] = (isset($sub_arr[$val['gi_sub_recommend']])) ? $sub_arr[$val['gi_sub_recommend']] : '未推荐';
            }
        }
        if( !is_empty($res) ){
            echo $this->get_normol_json( $res[0], $s_echo, $res[1] );
        }
    }
    
    /**
     * @name:game_add
     * @description: 添加或者编辑专区表单
     * @author: Xiong Jianbang
     * @create: 2014-11-6 下午9:06:15
     **/
    public function game_add(){
        if( !$this->check_right( '140020' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $vg_id	=  intval(get_var_value( 'vg_id'));
        
        $arr['gi_gv_id']	= intval(trim(get_var_value( 'gv_id')));
        $arr['gi_order']	= intval(trim(get_var_value( 'gi_order')));
        $arr['gi_download_url'] =  trim(get_var_value( 'gi_download_url'));
        $arr['gi_simple_txt'] =  trim(get_var_value( 'gi_simple_txt'));
        $arr['gi_isshow']	= !empty($_POST['gi_isshow'])?get_var_value( 'gi_isshow'):1;
        $arr['gi_name'] =  trim(get_var_value( 'gi_name'));
        $arr['gi_type_id'] =  intval(trim(get_var_value( 'gi_type_id')));
        $arr['gi_sub_recommend'] =  intval(trim(get_var_value( 'sub_recom')));
        $arr['gi_packname'] = trim(get_var_value( 'gi_packname'));
        $f_image_key = trim(trim(get_var_value( 'f_image_key',false)));
        if(!empty($f_image_key)){
            $arr['gi_logo'] =$f_image_key;
        }
        $uploadfile = trim(trim(get_var_value( 'uploadfile',false)));
        if(!empty($uploadfile)){
            $arr['gi_bg_img'] =$uploadfile;
        }
        $arr['gi_intro'] = trim(trim(get_var_value( 'gi_intro')));

        //获取汉语拼音
        $name = $arr['gi_name'];
        if(!empty($name)){
            $pinyin_temp = '';
            if(preg_match('/[0-9A-Za-z]{1,}/', $name,$match)){
                $pinyin_temp = $match[0];
                $name = str_replace($pinyin_temp,'org',$name);
                $name = strtolower($name);
            }

            $pinyin = pinyin($name);
            $pinyin = str_replace('org',$pinyin_temp,$pinyin);

            $arr['gi_pingyin'] = $pinyin;
        }

        //添加
        if(empty($vg_id)){
            $arr['gi_in_uid'] = $_SESSION['sys_admin_id'];
            $arr['gi_in_name'] = $_SESSION['sys_admin_name'];
            $arr['gi_created'] = time();
            if($this->video_model->add_game($arr)){
                $this->video_model->log_db_admin( '添加视频游戏:'.$arr['gi_name'], 1, __CLASS__ );
                exit(json_encode(array('msg'=>'添加成功!','status'=>200)));
            }else{
                exit(json_encode(array('msg'=>'添加失败!','status'=>200)));
            }
        }else{//编辑
            $this->video_model->update_game_by_id($arr,$vg_id);
            $this->video_model->log_db_admin( '修改视频游戏:'.$arr['gi_name'], 2, __CLASS__ );
            exit(json_encode(array('msg'=>'修改成功!','status'=>200)));
        }
    }
    
    
    /**
     * @name:ajax_edit_game_form
     * @description: AJAX生成修改表单
     * @author: Xiong Jianbang
     * @create: 2014-11-6 下午10:15:43
     **/
    public function ajax_edit_game_form(){
        if( !$this->check_right( '140020' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $vg_id	=  intval(get_var_value( 'vg_id'));
        if(empty($vg_id))exit;
        $arr = $this->video_model->get_game_info_by_id($vg_id);
        if(!empty($arr)){
            echo json_encode($arr);
        }
    }
    
    /**
     * @name:ajax_del_game
     * @description: 删除游戏视频
     * @author: Xiong Jianbang
     * @create: 2015-3-31 下午3:59:04
     **/
    public function ajax_del_game(){
        if( !$this->check_right( '140020' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $vg_id	=  intval(get_var_value( 'vg_id'));
        if(empty($vg_id)){
            exit(json_encode(array('msg'=>'参数错误','status'=>400)));
        }
        if($this->video_model->delete_game_by_id($vg_id)){
            $this->video_model->log_db_admin( '删除视频游戏ID:'.$vg_id, 3, __CLASS__ );
            exit(json_encode(array('msg'=>'删除成功','status'=>200)));
        }else{
            exit(json_encode(array('msg'=>'删除失败','status'=>400)));
        }
    }
    
    /**
     * @name:ajax_show_game
     * @description: 显示/隐藏游戏视频
     * @author: Xiong Jianbang
     * @create: 2015-3-31 下午3:59:04
     **/
    public function ajax_show_game(){
        if( !$this->check_right( '140020' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $vg_id	=  intval(get_var_value( 'vg_id'));
        $vg_type=  intval(get_var_value( 'vg_type'));
        if(empty($vg_id)){
            exit(json_encode(array('msg'=>'参数错误','status'=>400)));
        }
        switch ($vg_type) {
        	case 1:
        	    $arr['gi_isshow'] = 1;
        	break;
        	case 2:
        	    $arr['gi_isshow'] = 2;
        	break;
        }
        if( $this->video_model->update_game_by_id($arr,$vg_id)){
            $this->video_model->log_db_admin( '处理视频游戏ID:'.$vg_id, 2, __CLASS__ );
            exit(json_encode(array('msg'=>'处理成功','status'=>200)));
        }else{
            exit(json_encode(array('msg'=>'处理失败','status'=>400)));
        }
    }
    
    /**
     * @name: tags_list
     * @description: 添加标签列表方法
     * @author: xiongjianbang
     * @create: 2015-09-22  17:19
     **/
    public function tags_list() {
        if(!$this->check_right('140021')){
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        //标签列表
        $data = array(
                'js' => array(
                        'App'=>'admin/scripts/app.js', //需要引入app.js
                        'Index'=>'admin/scripts/index.js', //需要引入index.js
                        'GameTagsTableManaged'=>'admin/scripts/muzhiwan.js/videotags-table-managed.js', //需要引入table-managed.js
                        'admin/plugins/bootstrap-fileinput/js/fileinput.js',			//上传按钮和预览图
                        'admin/plugins/jquery-file-upload/js/jquery.fileupload.js',		//上传组件
                ),
        );
        $this->display( $data, 'video_tags_lists' );
    }
    
    /**
     * @name: tags_add
     * @description: 添加游戏标签
     * @author: chenteng
     * @create: 2014-09-22  13:19
     **/
    public function tags_add($id=0) {
        if(!$this->check_right('140021')){
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $tag_id = isset($_POST['tag_id'])?get_var_post("tag_id"):0;
        if(empty($tag_id) && isset($_POST['tag_name_cn'])){
               $arr['tag_name_cn'] = isset($_POST['tag_name_cn'])?get_var_post('tag_name_cn'):'';
               $arr['tag_is_hl'] = isset($_POST['tag_is_hl'])?get_var_post('tag_is_hl'):'';
               $arr['tag_colour'] = isset($_POST['tag_colour'])?get_var_post('tag_colour'):'';
               $arr['create_user_id'] = $_SESSION["sys_admin_id"];
               $arr['create_user_name'] = $_SESSION["sys_admin_name"];
               $arr['create_time'] = time();
                $a=	$this->video_model->tags_add($arr);
                if($a){
                    $this->sys->log_db_admin( '添加视频标签:'.get_var_post('tag_name_cn'), 1, __CLASS__ );
                    redirect('/admin/video/tags_list');
                }else{
                    $this->sys->log_db_admin('添加视频标签失败,标签名称:'.get_var_post('tag_name_cn'), 1, __CLASS__);
                }
        }else{
            $arr['tag_name_cn'] = isset($_POST['tag_name_cn'])?get_var_post('tag_name_cn'):'';
            $arr['tag_colour'] = isset($_POST['tag_colour'])?get_var_post('tag_colour'):'';
            $arr['tag_is_hl'] = isset($_POST['tag_is_hl'])?get_var_post('tag_is_hl'):'';
            $arr['create_user_id'] = $_SESSION["sys_admin_id"];
            $arr['create_user_name'] = $_SESSION["sys_admin_name"];
            $a=	$this->video_model->tags_update($arr,$tag_id);
            if($a){
                $this->sys->log_db_admin( '修改视频标签:'.get_var_post('tag_name_cn'), 1, __CLASS__ );
                redirect('/admin/video/tags_list');
            }else{
                $this->sys->log_db_admin('修改视频标签失败,标签名称:'.get_var_post('tag_name_cn'), 1, __CLASS__);
            }
        }
        $data = array(
                'js' => array(
                        'App'=>'admin/scripts/app.js', //需要引入app.js
                        'Index'=>'admin/scripts/index.js', //需要引入index.js
                )
        );
        if(!empty($id)){
            $data['data']=	$this->video_model->tags_select($id);
        }
        $this->display( $data, 'video_tags_add' );
    }
    
    /**
     * @name:ajax_delete_tag_type
     * @description: 删除标签
     * @author: Xiong Jianbang
     * @create: 2015-9-23 下午12:00:34
     **/
    public function ajax_delete_tag_type(){
        $f_id		= get_var_post('tag_id'); //游戏ID
        if( !$this->check_right( 140021 ) ){
            echo -1; //没有权限
        } else {
            $res = $this->video_model->delete_tag_type( $f_id );
            if( $res ){
                //记录操作日志
                $tmp_log_msg = "删除一条视频标签,tag_id为：{$f_id}";
                $this->sys->log_db_admin( $tmp_log_msg, 3, __CLASS__ );
            }
            echo $res ? 1 : 0;
        }
    }
    

    /**
     * @name: tags_list
     * @description: 添加标签列表方法
     * @author: xiongjianbang
     * @create: 2015-09-22  17:19
     **/
    public function ajax_get_tags_data() {
        if(!$this->check_right('140021')){
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $start_record	= get_var_value( 'iDisplayStart' ); 	//从多少条开始查询
        $page_size		= get_var_value( 'iDisplayLength' ); 	//每页显示多少条记录
        $s_echo			= get_var_value( 'sEcho' );
        $res = $this->video_model->ajax_get_tags_data( $start_record, $page_size );

        //标签颜色数组
        $colour_arr = array(
            '#f73934' => '红色',
            '#41ba00' => '绿色',
            '#2e9fff' => '蓝色'
        );


        if( !is_empty($res) ){
            foreach($res[0] as &$val){
                //父级id转换
                $val['tag_p_str'] = 0;

                //状态转换
                if($val['tag_is_hl'] == 1){
                    $val['tag_is_hl'] = '是';
                }else{
                    $val['tag_is_hl'] = '否';
                }

                //颜色字符串
                $val['tag_colour_str'] = isset($colour_arr[$val['tag_colour']]) ? $colour_arr[$val['tag_colour']] : '无';
            }
            echo $this->get_normol_json( $res[0],$s_echo,$res[1] );
        }
    }
    
    

    /**
     * @name:get_gv_id_by_package
     * @description: 根据包名获取游戏ID
     * @param: $package=包名
     * @return: gv_id
     * @author: Xiong Jianbang
     * @create: 2015-7-28 下午3:13:50
     **/
    private function  get_gv_id($package=''){
    	if(empty($package)){
    		return FALSE;
    	}
    	$res = $this->game_model->get_gv_id_by_package($package);
    	if(empty($res)){
    	    return FALSE;
    	}
    	return $res['gv_id'];
    }
    
    /**
     * @name:file_list
     * @description: 文件列表
     * @author: Xiong Jianbang
     * @create: 2015-2-7 上午10:46:56
     **/
    public function file_list(){
        if( !$this->check_right( '140022' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
    
        $data = array(
                'js' => array(
                        'App'=>'admin/scripts/app.js', //需要引入app.js
                        'Index'=>'admin/scripts/index.js', //需要引入index.js
                        'admin/scripts/muzhiwan.js/video_json_file.js'
                )
        );
        $this->display( $data, 'video_file_json_list' );
    }
    
    
    public function file_url_list($id=0){
        if( !$this->check_right( '140022' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $data = array(
                'js' => array(
                        'App'=>'admin/scripts/app.js', //需要引入app.js
                        'Index'=>'admin/scripts/index.js', //需要引入index.js
                        'admin/scripts/muzhiwan.js/video_url_file.js'
                )
        );
        $data['id'] = $id;
        $data['arr_status'] = $this->arr_url_status;
        $this->display( $data, 'video_file_url_list' );
    }
    
    

    /**
     * @name:file_upload
     * @description: 普通文件上传的页面
     * @author: Xiong Jianbang
     * @create: 2015-2-6 下午5:14:43
     **/
    public function file_upload(){
        if( !$this->check_right( '140022' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $data = array(
                'js' => array(
                        'App'=>'admin/scripts/app.js', //需要引入app.js
                        'Index'=>'admin/scripts/index.js', //需要引入index.js
                        'admin/scripts/swfupload.js',
                        'admin/scripts/fileprogress.js',
                        'admin/scripts/handlers.js',
                        'admin/scripts/muzhiwan.js/video-upload-admin.js',//
                )
        );
        $data['css'] = array(
                'admin/css/default.css',
        );
        $data['upload_domain'] = $GLOBALS['UPLOAD_APP_DOMAIN'];
        $this->display( $data, 'video_file_upload' );
    }
    
    
    /**
     * @name:ajax_upload_simple_file
     * @description: ajax上传普通文件
     * @author: Xiong Jianbang
     * @create: 2015-2-7 上午9:56:30
     **/
    public function ajax_upload_simple_file(){
        if( !$this->check_right( '140022' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        set_time_limit(0);
        @ini_set('memory_limit', '512M');
        $file_path = "";
    
        if (isset($_POST["hidFileID"]) && $_POST["hidFileID"] != "" ) {
            $file_path = get_var_value('hidFileID');
        }
        if(empty($file_path)){
            $arr['status'] = 400;
            $arr['message'] = "文件上传失败，请检查文件是否符合上传条件,可能的原因有:\n1，是否在同一天上传过同文件名的APK包，\n2、上传格式不对。\n3、文件保存目录没有写入权限。\n4、磁盘空间已满。\n5、已经存在同名文件";
            $this->callback_ajax( $arr );
        }
        $file_path = en_de_code($file_path, $GLOBALS['MZW_UPLOAD_SWF_KEY'],2);
        $desc = '';
        if (isset($_POST["desc"]) && $_POST["desc"] != "" ) {
            $desc = get_var_value('desc');
        }
//         $is_fixed = '';
//         if (isset($_POST["is_fixed"]) && $_POST["is_fixed"] != "" ) {
//             $is_fixed = intval(get_var_value('is_fixed'));
//         }
        $is_fixed = 1; //固定目录 
        //给新上传的文件重命名，返回替换后的文件名
        $this->load->model( 'admin/game_model' );
        $file_path = $this->game_model->rename_package_file($file_path);
    
    
        $full_path = $GLOBALS['APK_UPLOAD_DIR'] . $file_path;
        //移动到新位置
        if(!empty($is_fixed)){
            $filename = basename($full_path);
            $fixed_dir = $GLOBALS['APK_UPLOAD_DIR'].'/game/video_json';
            if(!is_dir($fixed_dir)){
                create_my_file_path($fixed_dir);
            }
            $new_file = $fixed_dir . '/'.$filename;
            rename($full_path, $new_file);
            $full_path = $new_file;
        }
        $json = @file_get_contents($full_path);
        if(empty($json)){
            $arr['status'] = 400;
            $arr['message'] = "文件内容为空，请检查";
            $this->callback_ajax( $arr );
        }
        $arr_url_data = json_decode($json,TRUE);
        if(empty($arr_url_data)){
            $arr['status'] = 400;
            $arr['message'] = "文件内容解析不成功，请检查";
            $this->callback_ajax( $arr );
        }
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
        if(!empty($is_fixed)){
            $data['vf_server_url'] = str_replace($GLOBALS['APK_UPLOAD_DIR'], '', $full_path);
        }
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
            $arr['status'] = 200;
            $arr['message'] = "上传成功";
            $tmp_log_msg = "上传视频普通文件,路径为：{$full_path}";
            $this->mod->log_db_admin( $tmp_log_msg, 1, __CLASS__ );
            $this->callback_ajax( $arr );
        }else{
            $arr['status'] = 400;
            $arr['message'] = "上传失败";
            $this->callback_ajax( $arr );
        }
    }
    
    /**
     * @name:ajax_get_simple_file_list
     * @description: 普通文件列表
     * @author: Xiong Jianbang
     * @create: 2015-2-7 上午11:50:27
     **/
    public function ajax_get_simple_file_list(){
        if( !$this->check_right( '140022' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
    
        $start_record	= get_var_value( 'iDisplayStart' ); 	//从多少条开始查询
        $page_size		= get_var_value( 'iDisplayLength' ); 	//每页显示多少条记录
        $s_echo			= get_var_value( 'sEcho' );
    
        $res = $this->video_model->ajax_get_simple_file_data($start_record, $page_size );
        if( !is_empty($res) ){
            $arr_status = array(0=>'待命',1=>'正在运行中',2=>'运行完毕');
            foreach($res[0] as &$val){
                $val['status_name'] = $arr_status[$val['vf_status']];
            }
            echo $this->get_normol_json( $res[0], $s_echo, $res[1] );
        }
    }
    
    
    /**
     * @name:ajax_get_simple_file_url_list
     * @description: 普通文件列表
     * @author: Xiong Jianbang
     * @create: 2015-2-7 上午11:50:27
     **/
    public function ajax_get_simple_file_url_list($id=0){
        if( !$this->check_right( '140022' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
    
        $start_record	= get_var_value( 'iDisplayStart' ); 	//从多少条开始查询
        $page_size		= get_var_value( 'iDisplayLength' ); 	//每页显示多少条记录
        $s_echo			= get_var_value( 'sEcho' );
        $status			= get_var_value( 'status' );
        $where = ' WHERE 1 ';
        if(!empty($id)){
        	$where .= ' AND vf_id='.$id;
        }
        if(!empty($status) && $status<>0){
            $where .= ' AND status='.$status;
        }
        $res = $this->video_model->ajax_get_simple_file_url_data($start_record, $page_size,$where );
        if( !is_empty($res) ){
            foreach($res[0] as &$val){
                $val['status_name'] =  $this->arr_url_status[$val['status']];
            }
            echo $this->get_normol_json( $res[0], $s_echo, $res[1] );
        }
    }
    
    
    /**
     * @name:ajax_delete_simple_file
     * @description: 删除文件
     * @author: Xiong Jianbang
     * @create: 2015-2-9 上午10:43:11
     **/
    public function ajax_delete_simple_file(){
        if( !$this->check_right( '140022' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $mf_id = 0;
        if (isset($_POST["mf_id"]) && $_POST["mf_id"] != "" ) {
            $mf_id = get_var_value('mf_id');
        }
        if(empty($mf_id)){
            $arr['status'] = 400;
            $arr['message'] = "参数错误";
            $this->callback_ajax( $arr );
        }
        if($this->video_model->delete_simple_file($mf_id)){
            $arr['status'] = 200;
            $arr['message'] = "删除成功";
            $tmp_log_msg = "删除普通文件,mf_id为：{$mf_id}";
            $this->mod->log_db_admin( $tmp_log_msg, 3, __CLASS__ );
            $this->callback_ajax( $arr );
        }
    }
    
    /**
     * @name:ajax_delete_simple_file_url
     * @description: 删除url
     * @author: Xiong Jianbang
     * @create: 2015-2-9 上午10:43:11
     **/
    public function ajax_delete_simple_file_url(){
        if( !$this->check_right( '140022' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $mf_id = 0;
        if (isset($_POST["mf_id"]) && $_POST["mf_id"] != "" ) {
            $mf_id = get_var_value('mf_id');
        }
        if(empty($mf_id)){
            $arr['status'] = 400;
            $arr['message'] = "参数错误";
            $this->callback_ajax( $arr );
        }
        if($this->video_model->delete_simple_file_url($mf_id)){
            $arr['status'] = 200;
            $arr['message'] = "删除成功";
            $tmp_log_msg = "删除一条视频url,id为：{$mf_id}";
            $this->mod->log_db_admin( $tmp_log_msg, 3, __CLASS__ );
            $this->callback_ajax( $arr );
        }
    }
    
    /**
     * @name:ajax_run_simple_file
     * @description: 开始执行
     * @author: Xiong Jianbang
     * @create: 2015-9-24 下午5:48:46
     **/
    public function ajax_run_simple_file($vf_id=0){
        if( !$this->check_right( '140022' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        if (isset($_POST["vf_id"]) && $_POST["vf_id"] != "" ) {
            $vf_id = get_var_value('vf_id');
        }
        if(empty($vf_id)){
            $arr['status'] = 400;
            $arr['message'] = "参数错误";
            $this->callback_ajax( $arr );
        }
        if(class_exists('swoole_client')){//如果有扩展，则进行操作
            $this->client = new swoole_client(SWOOLE_SOCK_TCP);
            if( !$this->client->connect("127.0.0.1", 9501 , 1) ) {
                $arr['status'] =400;
                $arr['message'] = "Error: {$fp->errMsg}[{$fp->errCode}]\n";
                $this->callback_ajax( $arr );
            }
            
            //是否正在运行中
            $result = $this->video_model->get_video_file_by_id($vf_id);
            if(!empty($result) && $result['vf_status']==1){
                $arr['status'] = 400;
                $arr['message'] = "该服务已经连接，正在运行中。。。";
                $this->callback_ajax( $arr );
            }else{
                $this->video_model->update_file_simple(array('vf_status'=>1),$vf_id);
            }
            //获取url列表是否有执行的相关信息
            $arr_info = $this->video_model->get_video_url_info_by_file_id($vf_id);
            if(empty($arr_info) || empty($arr_info['ct'])){
                $arr['status'] = 400;
                $arr['message'] = "该服务已无数据处理";
                $this->callback_ajax( $arr );
            }
            $total_count = intval($arr_info['ct']);
            $dep_count = 10;
            $step = intval($total_count/$dep_count);
            if($total_count<=$dep_count){
                $arr_msg = array(
                        'file_id' =>$vf_id,
                );
                $str_msg = serialize($arr_msg);
                $this->client->send( $str_msg );
                $message = $this->client->recv();
            }else{
                for ($i=0;$i<=$dep_count;$i++){
                    $arr_msg = array(
                            'file_id' =>$vf_id,'start'=>$i*$step,'step'=>$step
                    );
                    $str_msg = serialize($arr_msg);
                    $this->client->send( $str_msg );
                    $message = $this->client->recv();
                    sleep(3);
                }
            }
            $arr['status'] = 200;
            $arr['message'] = "生成视频抓取队列，请点击“详情”页面查看当前进度";
            $this->callback_ajax( $arr );
        }else{
            $arr['status'] = 400;
            $arr['message'] = "服务器未安装swoole扩展";
            $this->callback_ajax( $arr );
        }
        $arr['status'] = 400;
        $arr['message'] = "未知错误";
        $this->callback_ajax( $arr );
    }
    
    /**
     * @name:ajax_reload_simple_file
     * @description: 执行shell，重启swoole
     * @author: Xiong Jianbang
     * @create: 2015-9-28 下午3:56:26
     **/
    public function ajax_reload_simple_file(){
        if( !$this->check_right( '140022' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $shell_file = KYX_ROOT_DIR . '/cli_php/kill_video.sh';
        $command =  "sudo {$shell_file}";
        $return = shell_exec($command);
        if($return){
            $arr['status'] = 200;
            $arr['message'] = "重启成功";
        }else{
            $arr['status'] = 400;
            $arr['message'] = "重启失败";
        }
        $this->callback_ajax( $arr );
    }
    
    public function ajax_view_flow(){
        if( !$this->check_right( '140022' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $command =  "sudo  vnstat -h";
        $return = shell_exec($command);
        $arr['status'] = 200;
        $arr['message'] = nl2br($return);
        $this->callback_ajax( $arr );
    }
    


    /**
     * @name:ajax_push_message
     * @description: 视频消息推送
     * @author: Chen Zhong
     * @create: 2015-11-05 11:54:23
     **/
    public function ajax_push_message(){

        $push_id = intval(get_var_value('push_id')); //推送视频id
        $push_type = intval(get_var_value('push_type')); //推送类型（1：视频详情推送 2：视频专辑推送）
        $game_id = intval(get_var_value('game_id')); //推送游戏id
        $msg_title = get_var_value('msg_title'); //推送消息标题
        $msg_content = get_var_value('msg_content'); //推送消息内容
        $msg_registration = get_var_value('msg_registration'); //推送特定用户
        $device_str = rtrim(get_var_value('device_str'),','); //推送设备字符串 android,ios,winphone

        $res = array(
            'status' => 400,
            'msg' => '参数错误'
        );

        if(empty($push_id) || empty($msg_title) || empty($msg_content) || empty($device_str)){
            $this->callback_ajax( $res );
        }

        //推送app_key
        $app_key = 'c9cf0a49cec60534b71c3e06';
        $master_secret = '12a8d1dd9746149584889b8e';

        if($game_id != 2 && $game_id != 12){
            $res['msg'] = '目前只支持我的世界跟我的世界：故事模式游戏的视频推送，暂不支持其他游戏视频推送';
            $this->callback_ajax( $res );
        }

        //推送用户过滤
        $receive = empty($msg_registration) ? 'all' : (array('registration_id'=>explode(',',$msg_registration)));
//        $receive = array('registration_id'=>array('020cba06ea1')); //推送过滤

        //推送日志记录数组初始化
        $data = array(
            'push_id' => $push_id,
            'push_title' => $msg_title,
            'push_content' => $msg_content,
            'push_device' => $device_str,
            'push_registration' => json_encode($receive),
            'push_status' => 0,
            'push_time' => time()
        );

        if($push_type == 1){
            //获取视频信息
            $video_info = $this->video_model->get_video_list_info($push_id);
            if(!empty($video_info)){

                //推送日志记录
                $data['push_type'] = 1;
                $log_id = $this->video_model->add_video_push_message_log($data);

                //参数数组
                $url = '{"id":"'.$log_id.'","title":"'.$video_info['vvl_title'].'","action":"type=1\,appid='.$push_id.'"}';
                $m_arr = array('push' => $url);

                //推送消息
                $push_res = $this->push_message($msg_title,$msg_content,$m_arr,$receive,$device_str,$app_key,$master_secret);
                $res['status'] = 200;
                $res['msg'] = $push_res['message'];

                //推送日志记录更新
                $update_data = array(
                    'push_param' => json_encode($m_arr),
                    'push_status' => ($push_res['status'] == 200) ? 1 : 2
                );
                $this->video_model->update_video_push_message_log($log_id,$update_data);
                $tmp_log_msg = "推送消息成功,消息ID：".$log_id;
                $this->mod->log_db_admin( $tmp_log_msg, 1, __CLASS__ );
            }
        }elseif($push_type == 2){
            //获取专辑信息
            $category_info = $this->video_model->get_category_info($push_id);
            if(!empty($category_info)){

                if($category_info['vc_type_id'] != 4){
                    $res['msg'] = '只能推送集锦类型的专辑，暂不支持其他专辑类型的推送';
                    $this->callback_ajax( $res );
                }

                //检查是否存在二级页面
                $is_parent = $this->video_model->check_category_parent($category_info['id']);
                $type = $is_parent ? 4 : 3;

                //推送日志记录
                $data['push_type'] = 2;
                $log_id = $this->video_model->add_video_push_message_log($data);

                //参数数组
                $url = '{"id":"'.$log_id.'","title":"'.$category_info['vc_name'].'","action":"type='.$type.'\,appid='.$push_id.'"}';
                $m_arr = array('push' => $url);

                //推送消息
                $push_res = $this->push_message($msg_title,$msg_content,$m_arr,$receive,$device_str,$app_key,$master_secret);
                $res['status'] = 200;
                $res['msg'] = $push_res['message'];

                //推送日志记录更新
                $update_data = array(
                    'push_param' => json_encode($m_arr),
                    'push_status' => ($push_res['status'] == 200) ? 1 : 2
                );
                $this->video_model->update_video_push_message_log($log_id,$update_data);
                $tmp_log_msg = "推送消息成功,消息ID：".$log_id;
                $this->mod->log_db_admin( $tmp_log_msg, 1, __CLASS__ );
            }
        }

        $this->callback_ajax( $res );
    }

    /**
     * @name:ajax_get_author_info
     * @description: 获取视频专辑游戏联动作者
     * @author: Chen Zhong
     * @create: 2015-10-09 14:25:25
     * @return: array
     **/
    public function ajax_get_author_info(){
        if( !$this->check_right( '140008' ) ){//如果没有权限
            $arr['status'] = 1;
            $arr['message'] = '您没有操作权限！';
            $this->callback_ajax( $arr );
        }
        $game_id = intval(get_var_post("game_id")); //游戏id

        //一级分类数据
        $temp = $this->video_model->get_all_author_list($game_id);
        if(!empty($temp)){
            $arr['status'] = 200;
            $arr['author_msg'] = $temp;
        }
        $this->callback_ajax( $arr );
    }
    
    /**
     * @name:batch_video_cdn
     * @description: 
     * @param: 
     * @return: 
     * @author: Xiong Jianbang
     * @create: 2015-11-7 上午10:52:00
     **/
    public function batch_video_cdn(){
        if( !$this->check_right( '140008' ) ){//如果没有权限
            $arr['status'] = 1;
            $arr['message'] = '您没有操作权限！';
            $this->callback_ajax( $arr );
        }
        $video_id = intval(get_var_post("id")); 
        $res = $this->video_model->get_video_list_info($video_id);
        //这里的网址直接是http://kyxvideo.file.alimmdn.com/2015/09/20/c3808bf9-8612-4ea2-8530-f34fbccb7402.mp4这样的形式了
        $vvl_server_url = !empty($res['vvl_server_url'])?$res['vvl_server_url']:NULL;
        $sourcetype = $res['vvl_sourcetype'];
        $arr_temp = explode('.', $vvl_server_url);
        $vvl_server_url = ($sourcetype == 14) ?$vvl_server_url : ('http://kyxservervideo.file.alimmdn.com'.reset($arr_temp));
        if(empty($vvl_server_url)){
            $arr['status'] = 1;
            $arr['message'] = '视频地址为空';
            $this->callback_ajax( $arr );
        }
        $head = @get_headers($vvl_server_url);
        if(!check_url_exists($vvl_server_url)){
            $arr['status'] = 1;
            $arr['message'] = '阿里百川不存在该视频';
            $this->callback_ajax( $arr );
        }
         $dir_name =  parse_url($vvl_server_url, PHP_URL_PATH);
         $dir_name = dirname($dir_name);
        $to_save = $GLOBALS['UPLOAD_DIR']. $dir_name . '/';
        if(!is_dir($to_save)){
            create_my_file_path($to_save,0755);
        }
        $filename = basename($vvl_server_url);
        $tmp_video = curl_get_video($vvl_server_url,$to_save,TRUE);
        if(is_file($tmp_video)){
            $tmp_video = str_replace($GLOBALS['UPLOAD_DIR'], '', $tmp_video);
            $status = $this->action_cdn_video_file($video_id,$tmp_video);
            if($status){
                $arr['status'] = 1;
                $arr['message'] = $status.'同步成功';
                $this->callback_ajax( $arr );
            }
        }
        $arr['status'] = 1;
        $arr['message'] = '同步错误';
        $this->callback_ajax( $arr );
    }
    
    
    /**
     * @name:video_youku
     * @description: 将视频同步到优酷CDN
     * @author: Xiong Jianbang
     * @create: 2015-12-9 下午2:57:12
     **/
    public function video_youku(){
        if( !$this->check_right( '140008' ) ){//如果没有权限
            $arr['status'] = 1;
            $arr['message'] = '您没有操作权限！';
            $this->callback_ajax( $arr );
        }
        $video_id = intval(get_var_post("id"));
        $res = $this->video_model->get_video_list_info($video_id);
        //这里的网址直接是http://kyxvideo.file.alimmdn.com/2015/09/20/c3808bf9-8612-4ea2-8530-f34fbccb7402.mp4这样的形式了
        $vvl_server_url = !empty($res['vvl_server_url'])?$res['vvl_server_url']:NULL;;
        if(empty($vvl_server_url)){
            $arr['status'] = 1;
            $arr['message'] = '视频地址为空';
            $this->callback_ajax( $arr );
        }
        $sourcetype = $res['vvl_sourcetype'];
        $title = $res['vvl_title'];
        $arr_temp = explode('.', $vvl_server_url);
//         $vvl_server_url = ($sourcetype == 14) ?$vvl_server_url : ('http://kyxservervideo.file.alimmdn.com'.reset($arr_temp));
        $vvl_server_url = str_replace('http://kyxvideo.file.alimmdn.com', '', $vvl_server_url);
        $vvl_server_url = str_replace('http://publicvideo.file.alimmdn.com', '', $vvl_server_url);
        $vvl_server_url = 'http://publicvideo.file.alimmdn.com' . $vvl_server_url;;
        $head = @get_headers($vvl_server_url);
        if(!check_url_exists($vvl_server_url)){
            $arr['status'] = 1;
            $arr['message'] = '阿里百川不存在该视频或者还没打上水印';
            $this->callback_ajax( $arr );
        }
        $dir_name =  parse_url($vvl_server_url, PHP_URL_PATH);
        $dir_name = dirname($dir_name);
        $to_save = $GLOBALS['UPLOAD_DIR']. $dir_name . '/';
        if(!is_dir($to_save)){
            create_my_file_path($to_save,0755);
        }
        $filename = basename($vvl_server_url);
        $tmp_video = curl_get_video($vvl_server_url,$to_save,TRUE);
        if(is_file($tmp_video)){
            include APPPATH.'/libraries/youku_video/include/YoukuUploader.class.php'; //
            $client_id = "1de53610657a47f1"; // Youku OpenAPI client_id
            $client_secret = "4c3f199554dfb64bd863daeab03ded95"; //Youku OpenAPI client_secret
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
                $data['vvl_playurl'] = "http://v.youku.com/v_show/id_{$youku_video_id}.html";
                $data['vvl_server_url'] = $vvl_server_url;
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
    
    
    
    /**
     * @name:batch_video_youku
     * @description: 上传到优酷
     * @param: 
     * @return: 
     * @author: Xiong Jianbang
     * @create: 2015-12-3 上午10:33:48
     **/
    public function batch_video_youku(){
        if( !$this->check_right( '140008' ) ){//如果没有权限
            $arr['status'] = 1;
            $arr['message'] = '您没有操作权限！';
            $this->callback_ajax( $arr );
        }
        set_time_limit(0);
        ini_set('memory_limit', '128M');
        
        
        $video_id = intval(get_var_post("id"));
        $res = $this->video_model->get_video_list_info($video_id);
        //这里的网址直接是http://kyxvideo.file.alimmdn.com/2015/09/20/c3808bf9-8612-4ea2-8530-f34fbccb7402.mp4这样的形式了
        $vvl_server_url = !empty($res['vvl_server_url'])?$res['vvl_server_url']:NULL;
        $sourcetype = $res['vvl_sourcetype'];
        $title = isset($res['vvl_title'])?$res['vvl_title']:'';
        $arr_temp = explode('.', $vvl_server_url);
        $vvl_server_url = ($sourcetype == 14) ?$vvl_server_url : ('http://kyxservervideo.file.alimmdn.com'.reset($arr_temp));
        if(empty($vvl_server_url) ){
            $arr['status'] = 1;
            $arr['message'] = '视频地址为空';
            $this->callback_ajax( $arr );
        }
        $head = @get_headers($vvl_server_url);
        if(!check_url_exists($vvl_server_url)){
            $arr['status'] = 1;
            $arr['message'] = '阿里百川不存在该视频';
            $this->callback_ajax( $arr );
        }
        
        $arr = parse_url($vvl_server_url);
        //存储空间
        $namespace = substr($arr['host'],0,strpos($arr['host'], '.'));
        $files = pathinfo($arr['path']);
        $ak = '23190770';
        $sk = 'be3181612c90e7e2a031a70c586f465f';
        
        require APPPATH.'/libraries/umeng_video/manage_client.class.php'; //
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
        $opts['notifyUrl'] = "http://ksadmin.youxilaile.com/api/gp/get_ali_notify";
//         $opts['notifyUrl'] = "http://183.6.167.82:8005/api/gp/get_ali_notify";
        $uri = '/' . Conf::MANAGE_API_VERSION . '/mediaEncode';
        $obj = new ManageClient($ak,$sk);
        $return = $obj->curl_rest('POST',$uri,$opts);
        if(!empty($return) && $return['isSuccess'] ){
            $data['vvl_water_task_id'] = trim($return['taskId']);
            $this->video_model->video_info_update($video_id,$data);
            $arr['status'] = 1;
            $arr['message'] = '水印任务提交成功';
            $this->callback_ajax( $arr );
        } else{
            $arr['status'] = 1;
            $arr['message'] = '水印任务提交失败';
            $this->callback_ajax( $arr );
        }
    }
    
    /**
     * @name:action_cdn_video_file
     * @description: 将视频文件同步到CDN
     * @author: Xiong Jianbang
     * @create: 2015-2-7 上午11:42:09
     **/
    public function action_cdn_video_file($vvl_id=0,$server_path=''){
        if(empty($vvl_id) || empty($server_path)){
            return FALSE;
        }
        $cdn_url = $GLOBALS['SITE_HTTP_DOMAIN'] .'api/cdntb/i_video_cdn';
        $params = array(
                'flag' => 'KYX_VIDEO_FILE_'.$vvl_id,
                'server_path' => $server_path,
                'type' => 2//只同步到乐视
        );
        $json[] = curl_post($cdn_url,$params);
        $this->mod->log_db_admin( '同步视频文件' . $server_path, 2, __CLASS__ );
        return TRUE;
    }
    
    
    /**
     * @name:action_cdn_video_url
     * @description: 直接将外网的资源地址上传到CDN
     * @author: Xiong Jianbang
     * @create: 2015-11-9 上午11:02:26
     **/
    public function action_cdn_video_url($server_url_path=''){
        if( empty($server_url_path)){
            return FALSE;
        }
        $cdn_url = $GLOBALS['SITE_HTTP_DOMAIN'] .'api/cdntb/i_video_cdn';
        $params = array(
                'flag' => 'KYX_VIDEO_FILE_'.md5($server_url_path),
                'server_path' => $server_url_path,
                'type' => 2//只同步到乐视
        );
        $json[] = curl_post($cdn_url,$params);
        $this->mod->log_db_admin( '同步文件' . $server_url_path, 2, __CLASS__ );
        return TRUE;
    }
    
    /**
     * @name:man_made_video
     * @description: 手工处理视频
     * @author: Xiong Jianbang
     * @create: 2015-11-7 下午5:16:03
     **/
    public function man_made_video(){
        if( !$this->check_right( '140023' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        
       $data = array(
				'js' => array(
						'App'=>'admin/scripts/app.js', //需要引入app.js
						'Index'=>'admin/scripts/index.js', //需要引入index.js
						'admin/scripts/muzhiwan.js/video_man_made.js'
				)
		);
       
		$this->display( $data, 'video_man_made' );
    }
    
    
    public function cron_video($id=0){
        if( !$this->check_right( '140025' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
    
        $data = array(
                'js' => array(
                        'App'=>'admin/scripts/app.js', //需要引入app.js
                        'Index'=>'admin/scripts/index.js', //需要引入index.js
                        'admin/scripts/muzhiwan.js/video_cron.js'
                )
        );
        //关联游戏信息数组
        $game_arr = $this->video_model->get_relev_game_arr();
        $data['game_arr'] = $game_arr;
        if(!empty($id)){
        	$data['info'] = $this->video_model->find_spider_url_data_by_id($id);
        }
        $this->display( $data, 'video_cron' );
    }

    /**
     * @name:ajax_video_cdn
     * @description: 
     * @param: 
     * @return: 
     * @author: Xiong Jianbang
     * @create: 2015-11-10 上午11:43:53
     **/
    public function ajax_video_cdn(){
        if( !$this->check_right( '140023' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $id = trim(get_var_post("id"));
        $res = $this->video_model->find_cdn_video_url($id);
        if(empty($res)){
            $arr['status'] = 400;
            $arr['message'] = '参数错误';
            $this->callback_ajax( $arr );
        }
        if($res['status']==300){
            $arr['status'] = 400;
            $arr['message'] = '已经同步成功';
            $this->callback_ajax( $arr );
        }
        $vvl_server_url = $res['url'];
        if(class_exists('swoole_client')){//如果有扩展，则进行操作
            $this->client = new swoole_client(SWOOLE_SOCK_TCP);
            if( !$this->client->connect("127.0.0.1", 9502 , 1) ) {
                $arr['status'] =400;
                $arr['message'] = "Error: {$fp->errMsg}[{$fp->errCode}]\n";
                $this->callback_ajax( $arr );
            }
            $arr_msg = array(
                    'server_url' =>$vvl_server_url,'id'=>$id
            );
            $str_msg = serialize($arr_msg);
            $this->client->send( $str_msg );
            $message = $this->client->recv();
            $arr['status'] = 200;
            $arr['message'] = "生成视频抓取队列";
            $this->callback_ajax( $arr );
        }else{
            $arr['status'] = 400;
            $arr['message'] = "服务器未安装swoole扩展";
            $this->callback_ajax( $arr );
        }
    }
    
//     public function put_resource_cdn(){
//         $down_url = 'http://kyxvideo.file.alimmdn.com/2015/09/28/d5d6ff80-3910-463c-870e-c6162194ee95.mp4';
//         $id = md5($down_url);
//         $md5_value = 'be0a553a79d9f0595334a7a64a5539b0';
//         echo $leshi_cdn_url 	 = 'http://a.cdn.gugeanzhuangqi.com/cli_php/cdn_load/i_cdn_leshi.php?id='.$id.'&download_path='.urlencode( $down_url ).'&md5_value='.$md5_value;
// 		 $return["leshi"] = file_get_contents($leshi_cdn_url);	
//     }
    /**
     * @name:ajax_get_video_info
     * @description: 获取视频详情推送消息体内容
     * @author: Chen Zhong
     * @create: 2015-11-06 10:23:25
     * @return: json
     **/
    public function ajax_get_video_info(){

        $res = array(
            'status' => 400
        );

        if( !$this->check_right( '140011' ) ){//如果没有权限
            $arr['msg'] = '您没有操作权限！';
            $this->callback_ajax( $res );
        }

        //视频id
        $myid = intval(get_var_post("myid"));

        //获取视频信息
        $video_info = $this->video_model->get_video_list_info($myid);
        $game_id = isset($video_info['vvl_game_id']) ? intval($video_info['vvl_game_id']) : 0;
        $title = $this->video_model->get_game_name($game_id);
        $title = empty($title) ? '' : ($title.'游戏视频');
        $content = isset($video_info['vvl_title']) ? $video_info['vvl_title'] : '';

        //返回数据拼装
        $res['status'] = 200;
        $res['title'] = $title;
        $res['content'] = $content;
        $res['game_id'] = $game_id;

        $this->callback_ajax( $res );

    }

    /**
     * @name:ajax_get_category_push_info
     * @description: 获取视频专辑推送消息体内容
     * @author: Chen Zhong
     * @create: 2015-11-06 10:02:25
     * @return: json
     **/
    public function ajax_get_category_push_info(){

        $res = array(
            'status' => 400
        );

        if( !$this->check_right( '140007' ) ){//如果没有权限
            $arr['msg'] = '您没有操作权限！';
            $this->callback_ajax( $res );
        }

        //专辑id
        $myid = intval(get_var_post("myid"));

        //获取专辑信息
        $category_info = $this->video_model->get_category_info($myid);
        $game_id = isset($category_info['vc_game_id']) ? intval($category_info['vc_game_id']) : 0;
        $title = $this->video_model->get_game_name($game_id);
        $title = empty($title) ? '' : ($title.'游戏视频');
        $content = isset($category_info['vc_name']) ? $category_info['vc_name'] : '';

        //返回数据拼装
        $res['status'] = 200;
        $res['title'] = $title;
        $res['content'] = $content;
        $res['game_id'] = $game_id;
        $this->callback_ajax( $res );
    }
    
    
    /**
     * @name:ajax_get_simple_file_url_list
     * @description: 普通文件列表
     * @author: Xiong Jianbang
     * @create: 2015-2-7 上午11:50:27
     **/
    public function ajax_get_cdn_url_list($id=0){
        if( !$this->check_right( '140023' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
    
        $start_record	= get_var_value( 'iDisplayStart' ); 	//从多少条开始查询
        $page_size		= get_var_value( 'iDisplayLength' ); 	//每页显示多少条记录
        $s_echo			= get_var_value( 'sEcho' );
        $status			= get_var_value( 'status' );
        $where = ' WHERE 1 ';
        if(!empty($status) && $status<>0){
            $where .= ' AND status='.$status;
        }
        $res = $this->video_model->ajax_get_cdn_url_data($start_record, $page_size,$where );
        if( !is_empty($res) ){
            foreach($res[0] as &$val){
                $val['url'] = !empty($val['url'])?'【<a href="'.$val['url'].'" target="_blank">视频源下载</a>】':'';
                $val['status_name'] =  $this->arr_cdn_url_status[$val['status']];
                $val['cdn_url'] = !empty($val['cdn_url'])?'【<a href="'.$val['cdn_url'].'" target="_blank">CDN下载</a>】':'';
                $val['water_url'] = !empty($val['water_url'])?'【<a href="/uploads'.$val['water_url'].'" target="_blank">带有水印视频下载</a>】':'';
                $val['titles_url'] = !empty($val['titles_url'])?'【<a href="/uploads'.$val['titles_url'].'" target="_blank">带有片头+水印视频下载</a>】':'';
            }
            echo $this->get_normol_json( $res[0], $s_echo, $res[1] );
        }
    }
    
    
    /**
     * @name:ajax_get_spider_url_list
     * @description: 获取采集url列表
     * @author: Xiong Jianbang
     * @create: 2015-12-3 下午6:17:24
     **/
    public function ajax_get_spider_url_list($id=0){
        if( !$this->check_right( '140025' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
    
        $start_record	= get_var_value( 'iDisplayStart' ); 	//从多少条开始查询
        $page_size		= get_var_value( 'iDisplayLength' ); 	//每页显示多少条记录
        $s_echo			= get_var_value( 'sEcho' );
        $status			= get_var_value( 'status' );
        $page_type			= get_var_value( 'page_type' );
        $game_id = intval(get_var_value( 'game_id' ));
        $type_id = intval(get_var_value( 'type_id' ));
        $ali_title			= get_var_value( 'ali_title' );
        $where = ' WHERE 1 ';
        if(!empty($status) && $status<>0){
            $where .= ' AND status='.$status;
        }
        if(!empty($game_id) && $game_id<>0){
        	$where .= ' AND game_id='.$game_id;
        }
        if(!empty($type_id) && $type_id<>0){
        	$where .= ' AND type_id='.$type_id;
        }
        if(!empty($page_type) && $page_type<>0){
            $where .= ' AND page_type='.$page_type;
        }
        if(!empty($ali_title)){
        	$where .= ' AND title LIKE "%'.$ali_title.'%"';
        }
        $arr_type = array(1=>'人物',2=>'解说',3=>'赛事战况',4=>'集锦',5=>'职业',6=>'作者解说',7=>'阵型');
        $arr_page_type = array(1=>'单页',2=>'专辑页');
        $res = $this->video_model->ajax_get_spider_url_data($start_record, $page_size,$where );
        if( !is_empty($res) ){
            $game_arr = $this->video_model->get_relev_game_arr();
            foreach($res[0] as &$val){
                $val['url'] = !empty($val['url'])?'【<a href="'.urldecode($val['url']).'" target="_blank">'.urldecode($val['url']).'</a>】':'';
                $val['status_name'] =  isset($this->arr_spider_url_status[$val['status']])?$this->arr_spider_url_status[$val['status']]:'';
                $val['type_name'] =  isset($arr_type[$val['type_id']])?$arr_type[$val['type_id']]:'未知';
                $val['page_type_name'] =  isset($arr_page_type[$val['page_type']])?$arr_page_type[$val['page_type']]:'未知';
                $val['cdn_url'] = !empty($val['cdn_url'])?'【<a href="'.$val['cdn_url'].'" target="_blank">CDN下载</a>】':'';
                $val['water_url'] = !empty($val['water_url'])?'【<a href="/uploads'.$val['water_url'].'" target="_blank">带有水印视频下载</a>】':'';
                $val['titles_url'] = !empty($val['titles_url'])?'【<a href="/uploads'.$val['titles_url'].'" target="_blank">带有片头+水印视频下载</a>】':'';
                $val['game_name'] =  isset($game_arr[$val['game_id']])?$game_arr[$val['game_id']] . "【{$val['game_id']}】":'未知';
                
            }
            echo $this->get_normol_json( $res[0], $s_echo, $res[1] );
        }
    }
    
    
    
    /**
     * @name:ajax_video_cdn_add
     * @description: 添加cdn url记录
     * @author: Xiong Jianbang
     * @create: 2015-11-10 上午11:44:14
     **/
    public function ajax_video_cdn_add(){
        if( !$this->check_right( '140023' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $vvl_server_url = trim(get_var_post("server"));
        $vvl_title = trim(get_var_post("title"));
        $head = @get_headers($vvl_server_url);
        if(!check_url_exists($vvl_server_url)){
            $arr['status'] = 400;
            $arr['message'] = '阿里百川不存在该视频';
            $this->callback_ajax( $arr );
        }
        //检查该网址是否存在
        $res = $this->video_model->find_cdn_url_data($vvl_server_url);
        if(!empty($res)){
            $arr['status'] = 400;
            $arr['message'] = '该地址已经存在';
            $this->callback_ajax( $arr );
        }
        unset($res);
        $data['url'] = $vvl_server_url;
        $data['title'] = $vvl_title;
        $data['created'] = time();
        $data['status'] = -99;
        $res = $this->video_model->ajax_add_cdn_url_data($data);
        if($res){
            $arr['status'] = 200;
            $arr['message'] = '添加成功';
            $this->callback_ajax( $arr );
        }
        $arr['status'] = 400;
        $arr['message'] = '添加错误';
        $this->callback_ajax( $arr );
    }
    
    /**
     * @name:ajax_video_spider_delete
     * @description: 删除记录
     * @author: Xiong Jianbang
     * @create: 2015-12-4 上午10:59:18
     **/
    public function ajax_video_spider_delete(){
        if( !$this->check_right( '140025' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $id	=  intval(get_var_value( 'id'));
        if(empty($id)){
            exit(json_encode(array('message'=>'参数错误','status'=>400)));
        }
        if($this->video_model->delete_video_spider_url($id)){
            exit(json_encode(array('message'=>'删除成功','status'=>200)));
        }else{
            exit(json_encode(array('message'=>'删除失败','status'=>400)));
        }
    }
    
    /**
     * @name:ajax_video_spider_restart
     * @description: 更新状态
     * @author: Xiong Jianbang
     * @create: 2015-12-4 上午10:59:12
     **/
    public function ajax_video_spider_restart(){
        if( !$this->check_right( '140025' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $id	=  intval(get_var_value( 'id'));
        $data['status']=-99;
        if($this->video_model->update_video_spider_url($id,$data)){
            exit(json_encode(array('message'=>'重置成功','status'=>200)));
        }else{
            exit(json_encode(array('message'=>'重置失败','status'=>400)));
        }
    }
    
    /**
     * @name:ajax_video_simple_spider_start
     * @description: 单页采集
     * @author: Xiong Jianbang
     * @create: 2015-12-7 下午3:19:47
     **/
    public function ajax_video_simple_spider_start(){
        if( !$this->check_right( '140025' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $id	=  intval(get_var_value( 'id'));
        
    }
    
    /**
     * @name:ajax_video_spider_add
     * @description: 添加记录
     * @author: Xiong Jianbang
     * @create: 2015-12-3 下午6:23:51
     **/
    public function ajax_video_spider_add(){
        if( !$this->check_right( '140025' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $vvl_server_url = trim(get_var_post("server"));
        $vvl_title = trim(get_var_post("title"));
        $game_id = trim(get_var_post("game_id"));
        $type_id = trim(get_var_post("type_id"));
        $key_word = trim(get_var_post("key_word"));
        $page_type = intval(get_var_post("page_type"));
        $id = intval(trim(get_var_post("id")));
        $head = @get_headers($vvl_server_url);
        if(!check_url_exists($vvl_server_url)){
            $arr['status'] = 400;
            $arr['message'] = '该网址不能访问';
            $this->callback_ajax( $arr );
        }
        $data['url'] = $vvl_server_url;
        $data['title'] = $vvl_title;
        $data['created'] = time();
        $data['game_id'] = $game_id;
        $data['type_id'] = $type_id;
        $data['key_word'] = $key_word;
        $data['page_type'] = $page_type;
        if(empty($id)){
        	//检查该网址是否存在
        	$vvl_server_url = urlencode($vvl_server_url);
        	$res = $this->video_model->find_spider_url_data($vvl_server_url,$game_id);
        	if(!empty($res)){
        		$arr['status'] = 400;
        		$arr['message'] = '该地址已经存在';
        		$this->callback_ajax( $arr );
        	}
        	unset($res);
        	$data['status'] = -99;
        	$res = $this->video_model->add_spider_url_data($data);
        }else{
        	$res = $this->video_model->update_video_spider_url($id,$data);
        }
        if($res){
            $arr['status'] = 200;
            $arr['message'] = '处理成功';
            $this->callback_ajax( $arr );
        }
        $arr['status'] = 400;
        $arr['message'] = '处理错误';
        $this->callback_ajax( $arr );
    }
    
    /**
     * @name:ajax_video_simple_spider
     * @description: 单页抓取
     * @author: Xiong Jianbang
     * @create: 2015-12-8 上午10:43:23
     **/
    public function ajax_video_simple_spider(){
        if( !$this->check_right( '140025' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $vvl_server_url = trim(get_var_post("server"));
        $vvl_title = trim(get_var_post("title"));
        $game_id = trim(get_var_post("game_id"));
        $type_id = trim(get_var_post("type_id"));
        $page_type = intval(get_var_post("page_type"));
        $head = @get_headers($vvl_server_url);
        if(!check_url_exists($vvl_server_url)){
            $arr['status'] = 400;
            $arr['message'] = '该网址不能访问';
            $this->callback_ajax( $arr );
        }
        //创建视频地址解析对像
        $video = new video_parser();
        $video->set_url($vvl_server_url);
        $data = array();
        switch ($type_id) {
        	case 6:
        	    $data = $video->simple_page_spider();
        	break;
        	
        	default:
        		;
        	break;
        }
        if(!empty($data)){
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
                    'vc_game_id'=>$game_id,//'游戏ID',
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
            $tmp_playurl_get = $video->parse();//播放地址(解析出来的)',
            $tmp_playurl_get = json_decode($tmp_playurl_get,true);
            $video_id = $tmp_playurl_get['vid'];
            
            $tmp_arr = array(
                    'video_id'=>$video_id
            );
            //如果视频已经存在，则不插入当次的数据
            if( $this->video_model->check_video_by_name( $tmp_arr )!=FALSE ){
                $arr_ret['status'] = 400;
                $arr_ret['message'] = '该视频已存在';
                $this->callback_ajax( $arr_ret );
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
                    'vvl_sourcetype'=>$video->map_source_type($tmp_playurl_get['type']),//'视频来源（1优酷，2多玩）',
                    'vvl_imgurl'=>isset($data['vImgUrl'])?$data['vImgUrl']:'default',//'视频图片URL地址',
                    'vvl_imgurl_get'=>$tmp_img,//'视频图片URL地址',
                    'vvl_time'=>isset($data['vTime'])?trim($data['vTime']):'',//'视频时长',
                    'vvl_playurl'=>isset($data['vRealPalyUrl'])?$data['vRealPalyUrl']:'',//'优酷播放地址，需要解析',
                    'vvl_playurl_get'=>$tmp_playurl_get['msg'],//'优酷播放地址(解析出来的)',
                    'vvl_author_id'=>$author_id,//'解说作者ID(来自video_author_info表)',
                    'vvl_title'=>$data['vTitle'],//'视频标题',
                    'vvl_playurlback'=>isset($data['vRealPlayUrlBack'])?$data['vRealPlayUrlBack']:'',//'视频备用地址',
                    'vvl_playurlback_get'=>'',//'视频备用地址(解析出来的)',
                    'vvl_playcount'=>intval($data['vPlayCount']),//'视频播放次数(采集)',
                    'vvl_count'=>0,//'视频本地播放次数(本地记录)',
                    'vvl_sort_sys'=>isset($data['number'])?intval($data['number']):0,//系统默认排序
                    'vvl_video_id' => $tmp_playurl_get['vid'],
                    'vvl_upload_time'=> isset($data['createDate'])?strtotime($data['createDate']):'',//源网站上给予的该视频的上传时间，视频的上传时间
            );
            $video_id = $this->video_model->save_hero_video($tmp_arr,false);
            if($video_id){
                $arr_ret['status'] = 200;
                $arr_ret['message'] = '添加成功';
                $this->callback_ajax( $arr_ret );
            }
        }
        else{
            $arr_ret['status'] = 400;
            $arr_ret['message'] = '数据不存在';
            $this->callback_ajax( $arr_ret );
        }
    }
    
    /**
     * @name:ajax_water_task
     * @description: 添加水印任务
     * @author: Xiong Jianbang
     * @create: 2015-11-11 下午6:03:11
     **/
    public function ajax_water_task(){
        if( !$this->check_right( '140023' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $id = trim(get_var_post("id"));
        $res = $this->video_model->find_cdn_video_url($id);
        if(empty($res)){
            $arr['status'] = 400;
            $arr['message'] = '参数错误';
            $this->callback_ajax( $arr );
        }
        if($res['status']==300){
            $arr['status'] = 200;
            $arr['message'] = '已经同步成功';
            $this->callback_ajax( $arr );
        }
        
        require_once( KYX_ROOT_DIR. '/application/libraries/umeng_video/manage_client.class.php');
        //阿里百川的ak+sk
        $ak = '23224196';
        $sk = '6308fa2bcd2464367249767fa7b29bb1';
        
        $vvl_server_url = $res['url'];
        $arr = parse_url($vvl_server_url);
        $namespace = substr($arr['host'],0,strpos($arr['host'], '.'));
        $files = pathinfo($arr['path']);
        
        
        $opts['watermark'] = EncodeUtils::encodeWithURLSafeBase64("['panda','/images','logo.png']");//水印地址
        $video_dir = $files['dirname'];
        $video_file  =  $files['basename'];
        $opts['input'] = EncodeUtils::encodeWithURLSafeBase64("['{$namespace}','{$video_dir}','{$video_file}']");
        $new_dir = '/dir_new/'.md5('http://www.baidu.com');
        $video_file = $files['filename'];
        $opts['output'] = EncodeUtils::encodeWithURLSafeBase64("['{$namespace}','{$new_dir}','{$video_file}']");
        $opts['encodeTemplate'] = 'mp4_m3u8';
        $opts['watermarkTemplate'] = 'logo_water';
        $opts['usePreset'] = 0;
        $opts['force'] = 1;
        $opts['notifyUrl'] = "http://ksadmin.youxilaile.com/api/gp/get_ali_notify";
        $uri = '/' . Conf::MANAGE_API_VERSION . '/mediaEncode';
        
        $obj = new ManageClient($ak,$sk);
        $return = $obj->curl_rest('POST',$uri,$opts);
        if(!empty($return) && $return['isSuccess'] ){
            $params['water_task_id'] = trim($return['taskId']);
            $params['water_created'] = time();
            $params['status'] = 101;
            $this->video_model->cdn_url_update($id,$params);
            $arr['status'] = 200;
            $arr['message'] = '开始执行添加水印任务';
            $this->callback_ajax( $arr );
        }
        $arr['status'] = 400;
        $arr['message'] = '执行水印任务失败';
        $this->callback_ajax( $arr );
    }
    
    /**
     * @name:添加 
     * @description: ajax_titles_task
     * @param: 
     * @return: 
     * @author: Xiong Jianbang
     * @create: 2015-11-24 下午2:04:01
     **/
    public function ajax_titles_task(){
        if( !$this->check_right( '140023' ) ){//如果没有权限
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $id = trim(get_var_post("id"));
        $res = $this->video_model->find_cdn_video_url($id);
        if(empty($res)){
            $arr['status'] = 400;
            $arr['message'] = '参数错误';
            $this->callback_ajax( $arr );
        }
        if($res['status']==604){
            $arr['status'] = 400;
            $arr['message'] = '水印已经打印成功';
            $this->callback_ajax( $arr );
        }
        $vvl_server_url = $res['url'];
        if(class_exists('swoole_client')){//如果有扩展，则进行操作
            $this->client = new swoole_client(SWOOLE_SOCK_TCP);
            if( !$this->client->connect("127.0.0.1", 9503 , 1) ) {
                $arr['status'] =400;
                $arr['message'] = "Error: {$fp->errMsg}[{$fp->errCode}]\n";
                $this->callback_ajax( $arr );
            }
            $arr_msg = array(
                    'server_url' =>$vvl_server_url,'id'=>$id
            );
            $str_msg = serialize($arr_msg);
            $this->client->send( $str_msg );
            $message = $this->client->recv();
            $arr['status'] = 200;
            $arr['message'] = "生成片头视频队列";
            $this->callback_ajax( $arr );
        }else{
            $arr['status'] = 400;
            $arr['message'] = "服务器未安装swoole扩展";
            $this->callback_ajax( $arr );
        }
    }
    
    

    /**
     * @name: ajax_syn_img
     * @description: ajax同步解说中头像
     * @param: id int 解说者ID
     * @return: json
     * @author: Chen Zhong
     * @create: 2015-11-17 14:24:50
     **/
    public function ajax_syn_img(){
        if( !$this->check_right( '140006' ) ){//如果没有权限
            exit(json_encode(array('msg'=>'您没有操作权限！','status'=>3)));
        }

        $id	=  intval(get_var_value( 'id')); //解说者id
        if(empty($id)){
            exit(json_encode(array('msg'=>'参数错误','status'=>400)));
        }

        if(!empty($id)){
            //获取解说者信息
            $author_data = $this->video_model->get_author_info($id);

            if(!empty($author_data)){

                if(!isset($author_data['va_uid']) || empty($author_data['va_uid'])){
                    exit(json_encode(array('msg'=>'该用户未注册，请先注册后再进行同步操作！','status'=>400)));
                }

                //上传头像
                $arr = $this->upload_author_img($author_data['va_icon_get'],$author_data['va_uid']);
                exit(json_encode($arr));
            }else{
                exit(json_encode(array('msg'=>'获取解说信息错误','status'=>400)));
            }
        }
    }

    /**
     * @name: ajax_user_reg
     * @description: ajax解说中注册
     * @param: id int 解说者ID
     * @return: json
     * @author: Chen Zhong
     * @create: 2015-11-17 14:24:50
     **/
    public function ajax_user_reg(){
        if( !$this->check_right( '140006' ) ){//如果没有权限
            exit(json_encode(array('msg'=>'您没有操作权限！','status'=>3)));
        }

        $id	=  intval(get_var_value( 'id')); //解说者id
        if(empty($id)){
            exit(json_encode(array('msg'=>'参数错误','status'=>400)));
        }

        //获取解说者信息
        $author_data = $this->video_model->get_author_info($id);
        if(!empty($author_data)){

            //检测昵称是否存在
            $has_nickname = $this->member_model->check_nickname($author_data['va_name']);
            if($has_nickname){
                exit(json_encode(array('msg'=>'该昵称已经注册，请修改昵称后再注册','status'=>400)));
            }

            //获取主播关联游戏类型
            $game_type = '';
            $game_data = $this->video_model->get_game_info_by_id($author_data['va_game_id']);
            if(isset($game_data['gi_type_id']) && !empty($game_data['gi_type_id'])){
                $game_type = $game_data['gi_type_id'];
            }

            //获取需要注册的信息
            $username = 'k_'.time(); //自动生成用户名
            $password = 'kyx66666666';   //统一生成密码
            $salt = substr(uniqid(rand()), -6);
            $param = array(
                'username' => $username,
                'password' => md5(md5($password).$salt),
                'regip' => '127.0.0.1',
                'regdate' => time(),
                'salt' => $salt,
                'nickname' => $author_data['va_name'],
                'desc' => $author_data['va_intro'],
                'source' => 3,
                'is_recommed' => 1,
                'is_show' => 1,
                'video_game' => $author_data['va_game_id'],
                'video_game_type' => $game_type
            );
            $uid = $this->member_model->memer_info_add($param);

            //注册成功
            if(!empty($uid)){

                $upload_path= $this->config->item( 'image_root_path' );
                $ico_path = empty($author_data['va_icon_get']) ? '' : ($upload_path.$author_data['va_icon_get']);
                if(!empty($ico_path) && is_file($ico_path)){
                    //上传解说头像
                    $this->upload_author_img($author_data['va_icon_get'],$uid);
                }

                //更新解说表
                $this->video_model->author_info_update(0,array('va_uid'=>$uid),array('va_name' => $author_data['va_name']));

                //更新专辑表
                $this->video_model->category_info_update(array('vc_uid'=>$uid),array('vc_author_id' => intval($author_data['id'])));

                //更新视频表
                $this->video_model->video_info_update(0,array('vvl_uid'=>$uid),array('vvl_author_id' => intval($author_data['id'])));

                //记录日志 1添加，2修改，3删除，4数据导入，5数据导出，6其他
                $tmp_log_msg = "解说注册成功,用户ID：{$uid}";
                $this->video_model->log_db_admin( $tmp_log_msg, 1, __CLASS__ );
                exit(json_encode(array('msg'=>'注册成功','status'=>200)));
            }
        }else{
            exit(json_encode(array('msg'=>'获取解说信息错误','status'=>400)));
        }
    }

    /**
     * @name: upload_author_img
     * @description: 上传解说者头像到用户中心
     * @param: id int 解说者ID
     * @return: json
     * @author: Chen Zhong
     * @create: 2015-11-17 14:24:50
     **/
    public function upload_author_img($path = '',$uid = 0,$ignore = false){

        $arr = array(
            'msg' => '',
            'status' => 400
        );

        $upload_path= $this->config->item( 'image_root_path' );
        $ico_path = empty($path) ? '' : ($upload_path.$path);
        if(empty($ico_path) || !is_file($ico_path)){
            $arr['msg'] = '解说作者头像不存在或为空';
        }else{
            $ext = pathinfo($ico_path, PATHINFO_EXTENSION);
            if(!in_array($ext,array('jpg','png','gif'))){
                $arr['msg'] = '文件必须为JPG,PNG,GIF格式';
            }

            //将非JPG图像转换为JPG
            if(in_array($ext, array('png','gif'))){
                $new_pic_full_path = str_replace($ext,'jpg',$ico_path);
                image_to_jpg($ico_path,$new_pic_full_path ,180,180);
                $ico_path = $new_pic_full_path;
            }

            if(is_file($ico_path)){
                $pic_url = $GLOBALS['IMAGE_DOMAIN'] . str_replace($upload_path, '', $ico_path);
                $data = file_get_contents($pic_url);
                $im = imagecreatefromstring($data);
                if($im == false){
                    $arr['msg'] = '不是正常的头像文件';
                }
                unset($data);
                //生产环境抓取图片的接口
                $get_img_url = UC_API . '/api/get_avatar_img.php';
                $arr_img = array('local_img'=>$pic_url,'uid'=>$uid);
                //调用ucenter的头像处理接口
                $json = curl_post($get_img_url,$arr_img);
                $arr = json_decode($json,TRUE);
                if($arr['status']==400){
                    $arr['msg'] = '头像同步失败';
                }else{
                    //记录日志 1添加，2修改，3删除，4数据导入，5数据导出，6其他
                    $tmp_log_msg = "解说者头像同步成功,用户ID：{$uid}";
                    $this->video_model->log_db_admin( $tmp_log_msg, 2, __CLASS__ );
                    $arr['msg'] = '头像同步成功';
                    $arr['status'] = 200;
                }
            }
        }

        return $arr;
    }

    /**
     * @name: ajax_syn_video
     * @description: ajax同步解说者视频数据
     * @param: id int 解说者ID
     * @return: json
     * @author: Chen Zhong
     * @create: 2015-11-17 14:24:50
     **/
    public function ajax_syn_video(){
        if( !$this->check_right( '140006' ) ){//如果没有权限
            exit(json_encode(array('msg'=>'您没有操作权限！','status'=>3)));
        }

        $id	=  intval(get_var_value( 'id')); //解说者id
        if(empty($id)){
            exit(json_encode(array('msg'=>'参数错误','status'=>400)));
        }

        if(!empty($id)){
            //获取解说者信息
            $author_data = $this->video_model->get_author_info($id);

            if(!empty($author_data)){

                if(!isset($author_data['va_uid']) || empty($author_data['va_uid'])){
                    exit(json_encode(array('msg'=>'该用户未注册，请先注册后再进行同步操作！','status'=>400)));
                }

                //更新专辑表
                $this->video_model->category_info_update(array('vc_uid'=>$author_data['va_uid']),array('vc_author_id' => $id));

                //更新视频表
                $this->video_model->video_info_update(0,array('vvl_uid'=>$author_data['va_uid']),array('vvl_author_id' => $id));

                exit(json_encode(array('msg'=>'数据同步成功','status'=>200)));
            }else{
                exit(json_encode(array('msg'=>'获取解说信息错误','status'=>400)));
            }
        }else{
            exit(json_encode(array('msg'=>'操作失败','status'=>400)));
        }
    }

    /**
     * @name: video_chal_gory_tag_rela
     * @description: 视频频道分类标签关系列表
     * @author: Chen Zhong
     * @create: 2015-11-27  17:19
     **/
    public function video_chal_gory_tag_rela() {
        if(!$this->check_right('140024')){
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }

        //标签列表
        $data = array(
            'js' => array(
                'App'=>'admin/scripts/app.js', //需要引入app.js
                'Index'=>'admin/scripts/index.js', //需要引入index.js
                'FormValidation'=>'admin/scripts/muzhiwan.js/video-chalgorytags-table-managed.js' //需要引入table-managed.js
            ),
        );

        //关联游戏信息数组
        $game_arr = $this->video_model->get_relev_game_arr();
        $data['game_arr'] = $game_arr;

        $this->display( $data, 'video_chal_gory_tag_rela' );
    }

    /**
     * @name: ajax_get_video_cgt_rela_list
     * @description: 获取频道分类标签列表
     * @author: Chen Zhong
     * @create: 2015-11-27  17:19
     **/
    public function ajax_get_video_cgt_rela_list() {
        if(!$this->check_right('140024')){
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $start_record	= get_var_value( 'iDisplayStart' ); 	//从多少条开始查询
        $page_size		= get_var_value( 'iDisplayLength' ); 	//每页显示多少条记录
        $s_echo			= get_var_value( 'sEcho' );
        $bel_game			= intval(get_var_value( 'bel_game' )); //所属游戏
        $tag_type			= intval(get_var_value( 'tag_type' )); //标签类型 1：频道 2：分类 3：标签

        $where = '';
        if(!empty($bel_game)){
            $where .= " AND `game_id` = ".$bel_game;
        }

        if(!empty($tag_type)){
            switch($tag_type){
                case 1:
                    $where .= " AND `category_id` = 0 AND `tag_id` = 0";
                    break;
                case 2:
                    $where .= " AND `category_id` != 0 AND `tag_id` = 0";
                    break;
                default:
                    $where .= " AND `cha_id` != 0 AND `category_id` != 0 AND `tag_id` != 0";
                    break;
            }
        }

        //获取列表
        $res = $this->video_model->ajax_get_video_cgt_rela_data( $start_record, $page_size, $where );

        if( !empty($res[0]) ){
            foreach($res[0] as $key => $val){
                $res[0][$key]['source'] = '频道';
                if(!empty($val['category_id']) && empty($val['tag_id'])){
                    $res[0][$key]['source'] = '分类';
                }elseif(!empty($val['category_id']) && !empty($val['tag_id'])){
                    $res[0][$key]['source'] = '标签';
                }

                $res[0][$key]['cha_id'] = empty($val['cha_id']) ? '' : ($val['cha_name'].'（'.$val['cha_id'].'）');
                $res[0][$key]['category_id'] = empty($val['category_id']) ? '' : ($val['category_name'].'（'.$val['category_id'].'）');
                $res[0][$key]['tag_id'] = empty($val['tag_id']) ? '' : ($val['tag_name'].'（'.$val['tag_id'].'）');
            }
            echo $this->get_normol_json( $res[0],$s_echo,$res[1] );
        }else{
            echo $this->get_normol_json( array(),$s_echo,0 );
        }
    }

    /**
     * @name: video_chal_gory_tag_add
     * @description: 添加视频频道分类标签
     * @author: Chen Zhong
     * @create: 2014-11-30  10:34
     **/
    public function video_chal_gory_tag_add($id=0) {
        if(!$this->check_right('140024')){
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }

        $data = array(
            'js' => array(
                'App'=>'admin/scripts/app.js', //需要引入app.js
                'Index'=>'admin/scripts/index.js', //需要引入index.js
                'FormValidation'=>'admin/scripts/muzhiwan.js/video-chalgorytags-table-managed.js' //需要引入table-managed.js
            )
        );

        //关联游戏信息数组
        $game_arr = $this->video_model->get_relev_game_arr();
        $data['game_arr'] = $game_arr;

        if(!empty($id)){
            $data['data']=	$this->video_model->tags_select($id);
        }
        $this->display( $data, 'video_chal_gory_tag_add' );
    }

    /**
     * @name:ajax_get_channel_linkage
     * @description: 根据游戏获取频道数组
     * @author: Chen Zhong
     **/
    public function ajax_get_channel_linkage(){
        if( !$this->check_right( '140024' ) ){//如果没有权限
            $arr['status'] = 1;
            $arr['message'] = '您没有操作权限！';
            $this->callback_ajax( $arr );
        }
        $bel_game = intval(get_var_post("bel_game")); //游戏id
        $temp = $this->video_model->get_channel_linkage_arr($bel_game);
        if(!empty($temp)){
            $arr['status'] = 200;
            $arr['message'] =$temp;
            $this->callback_ajax( $arr );
        }
    }

    /**
     * @name:ajax_get_chal_gory_linkage
     * @description: 根据游戏获取分类数组
     * @author: Chen Zhong
     **/
    public function ajax_get_chal_gory_linkage(){
        if( !$this->check_right( '140024' ) ){//如果没有权限
            $arr['status'] = 1;
            $arr['message'] = '您没有操作权限！';
            $this->callback_ajax( $arr );
        }
        $bel_game = intval(get_var_post("bel_game")); //游戏id
        $bel_channel = intval(get_var_post("bel_channel")); //所属频道
        $temp = $this->video_model->get_chal_gory_linkage_arr($bel_game,$bel_channel);
        if(!empty($temp)){
            $arr['status'] = 200;
            $arr['message'] =$temp;
            $this->callback_ajax( $arr );
        }
    }

    /**
     * @name:video_chal_gory_tag_save
     * @description: 视频频道分类标签修改
     * @author: Chen Zhong
     * @create: 2015-11-30 15:45:25
     **/
    public function video_chal_gory_tag_save(){
        if( !$this->check_right( '140024' ) ){//如果没有权限
            $arr['status'] = 1;
            $arr['message'] = '您没有操作权限！';
            $this->callback_ajax( $arr );
        }

        //定义AJAX返回的数组
        $arr = array(
            'status'=>400,//执行状态(例如：200成功，301失败...),
            'message'=>'参数错误',//返回信息,
            'url'=>''//要跳转的地址
        );

        //获取操作数据
        $id = intval(get_var_post("id"));  //id
        $cct_name = get_var_post("cct_name"); //频道分类标签名称
        $cct_type = intval(get_var_post("cct_type"));  //类型 1：频道 2：分类 3：标签
        $bel_game = intval(get_var_post("bel_game"));  //所属游戏
        $bel_channel = intval(get_var_post("bel_channel"));  //所属频道
        $bel_category = intval(get_var_post("bel_category"));  //所属分类

        //类型判断
        if(empty($cct_type) || empty($cct_name)){
            $this->callback_ajax( $arr );
        }

        //所属游戏判断
        if(empty($bel_game)){
            $arr['message'] = '请选择所属游戏';
            $this->callback_ajax( $arr );
        }

        //所属频道判断
        if($cct_type == 2 || $cct_type == 3){
            if(empty($bel_channel)){
                $arr['message'] = '请选择所属频道';
                $this->callback_ajax( $arr );
            }
        }

        //所属分类判断
        if($cct_type == 3){
            if(empty($bel_category)){
                $arr['message'] = '请选择所属分类';
                $this->callback_ajax( $arr );
            }
        }

        //类型数组
        $type_arr = array(
            1 => '频道',
            2 => '分类',
            3 => '标签'
        );

        if(empty($id)){ //添加

            $data = array(
                'vtc_name' => $cct_name,
                'vtc_user_name' => $_SESSION['sys_admin_name'],
                'vtc_user_id' => $_SESSION['sys_admin_id'],
                'vtc_create_time' => time(),
                'vtc_game_id' => $bel_game,
                'vtc_type' => $cct_type
            );

            //添加频道分类标签
            $id = $this->video_model->chal_gory_tag_add($data);

            //添加成功后添加频道分类标签关系表信息
            if(!empty($id)){
                $data = array(
                    'cha_id' => ($cct_type == 1) ? $id : $bel_channel,
                    'category_id' => ($cct_type == 2) ? $id : $bel_category,
                    'tag_id' => ($cct_type == 3) ? $id : 0,
                    'game_id' => $bel_game
                );

                $rel_id = $this->video_model->chal_gory_tag_relation_add($data);

                //记录日志：1添加，2修改，3删除，4数据导入，5数据导出，6其他
                $tmp_log_msg = (isset($type_arr[$cct_type]) ? $type_arr[$cct_type] : '')."添加成功，ID为：$id ，名称为：".$cct_name;
                $this->sys->log_db_admin( $tmp_log_msg, 1, __CLASS__ );

                $arr['status'] = 200;
                $arr['message'] = '添加成功';
                $arr['url'] = '/admin/video/video_chal_gory_tag_rela';
                $this->callback_ajax( $arr );
            }else{
                $arr['message'] = '添加失败';
                $this->callback_ajax( $arr );
            }
        }else{ //修改

        }
    }

    /**
     * @name: ajax_change_xiaolu_recom_status
     * @description: ajax更改视频分类的小鹿推荐状态
     * @param: id int 视频分类ID
     * @param: status int 推荐状态（2：推荐 1：取消推荐）
     * @return: json
     * @author: Chen Zhong
     * @create: 2015-11-03 09:49:50
     **/
    public function ajax_change_xiaolu_recom_status(){
        if( !$this->check_right( '140007' ) ){//如果没有权限
            exit(json_encode(array('msg'=>'您没有操作权限！','status'=>3)));
        }
        $id	=  get_var_value( 'id'); //视频分类id
        $status = intval(get_var_value('status')); //推荐状态
        if(empty($id) || empty($status)){
            exit(json_encode(array('msg'=>'参数错误','status'=>400)));
        }

        if($this->video_model->change_xiaolu_recom_status($id,$status)){
            //记录日志 1添加，2修改，3删除，4数据导入，5数据导出，6其他
            $tmp_log_msg = ($status == 2) ? ("小鹿推荐视频分类成功,视频分类ID：{$id}") : ("取消小鹿推荐视频分类成功,视频分类ID为：{$id}");
            $this->video_model->log_db_admin( $tmp_log_msg, 2, __CLASS__ );
            exit(json_encode(array('msg'=>'操作成功','status'=>200)));
        }else{
            //记录日志 1添加，2修改，3删除，4数据导入，5数据导出，6其他
            $tmp_log_msg = ($status == 2) ? "小鹿推荐视频分类失败,视频分类ID：{$id}" : "取消小鹿推荐视频分类失败,视频分类ID为：{$id}";
            $this->video_model->log_db_admin( $tmp_log_msg, 2, __CLASS__ );
            exit(json_encode(array('msg'=>'操作失败','status'=>400)));
        }
    }


    /**
     * @name: video_game_type_list
     * @description: 视频游戏分类列表
     * @author: Chen Zhong
     * @create: 2015-11-27  17:19
     **/
    public function video_game_type_list() {
        if(!$this->check_right('140026')){
            $arr['status'] = 1;
            $arr['message'] = '您没有操作权限！';
            $this->callback_ajax( $arr );
        }

        $data = array(
            'js' => array(
                'App'=>'admin/scripts/app.js', //需要引入app.js
                'Index'=>'admin/scripts/index.js', //需要引入index.js
                'FormValidation'=>'admin/scripts/muzhiwan.js/video-game-type-table-managed.js'
            ),
        );

        $this->display( $data, 'video_game_type_list' );
    }

    /**
     * @name: ajax_get_video_game_type_list
     * @description: 获取视频分类列表
     * @author: Chen Zhong
     * @create: 2015-11-27  17:19
     **/
    public function ajax_get_video_game_type_list() {
        if(!$this->check_right('140026')){
            $arr['status'] = 1;
            $arr['message'] = '您没有操作权限！';
            $this->callback_ajax( $arr );
        }
        $start_record	= get_var_value( 'iDisplayStart' ); 	//从多少条开始查询
        $page_size		= get_var_value( 'iDisplayLength' ); 	//每页显示多少条记录
        $s_echo			= get_var_value( 'sEcho' );

        $where = '';

        //获取列表
        $res = $this->video_model->ajax_get_video_game_type_data( $start_record, $page_size, $where );

        if( !empty($res[0]) ){
            foreach($res[0] as $key => $val){
                $res[0][$key]['t_logo'] = empty($val['t_logo']) ? '暂无图片' : ("<img src='" . $GLOBALS['IMAGE_DOMAIN'] . $val['t_logo'] . "' width=50 height=50 />");
                $res[0][$key]['t_img'] = empty($val['t_img']) ? '暂无图片' : ("<img src='" . $GLOBALS['IMAGE_DOMAIN'] . $val['t_img'] . "' width=50 height=50 />");
                $res[0][$key]['status_str'] = ($val['t_status'] == 1) ? '显示' : '隐藏';
            }
        }

        echo $this->get_normol_json( $res[0],$s_echo,$res[1] );
    }

    /**
     * @name: video_game_type_edit
     * @description: 视频游戏分类添加编辑
     * @author: Chen Zhong
     * @create: 2014-11-30  10:34
     **/
    public function video_game_type_edit() {
        if(!$this->check_right('140027')){
            $arr['status'] = 1;
            $arr['message'] = '您没有操作权限！';
            $this->callback_ajax( $arr );
        }

        $id = intval(get_var_value('id')); //视频游戏分类id

        $data = array(
            'js' => array(
                'App'=>'admin/scripts/app.js', //需要引入app.js
                'Index'=>'admin/scripts/index.js', //需要引入index.js
                'admin/scripts/muzhiwan.js/SimpleAjaxUploader.js', //图片上传
                'FormValidation'=>'admin/scripts/muzhiwan.js/video-game-type-table-managed.js'
            )
        );

        if(!empty($id)){
            $data['data']=	$this->video_model->get_video_game_type_info($id);
        }

        $this->display( $data, 'video_game_type_edit' );
    }

    /**
     * @name: upload_video_game_type_img
     * @description: 上传视频游戏分类图片
     * @param:
     * @return: json
     * @author: Chen Zhong
     * @create: 2015-02-13 15:10:50
     **/
    public function upload_video_game_type_img(){
        if( !$this->check_right( '140027' ) ){
            $arr['status'] = 1;
            $arr['message'] = '您没有操作权限！';
            $this->callback_ajax( $arr );
        }

        //引入上传类
        require APPPATH.'/libraries/simple_ajax_uploader.php';
        //上传图片后缀名限制
        $valid_extensions = array('gif', 'png', 'jpeg', 'jpg','webp');
        $Upload = new FileUpload('uploadfile');
        //上传大小限制
        $Upload->sizeLimit = 2*10485760;  //上限20M
        //创建图片存放目录
        $date =  '/video_game_type_bg' .date('/Y/m/d/');  //添加模块名作目录一部分
        $upload_dir = $this->config->item('image_root_path')  . $date;
        create_my_file_path($upload_dir,0755);
        //生成新图片名称
        $Upload->newFileName = md5(uniqid().$Upload->getFileName()).'.'.$Upload->getExtension();
        $result = $Upload->handleUpload($upload_dir, $valid_extensions);
        if (!$result) {
            echo json_encode(array('success' => false, 'msg' => $Upload->getErrorMsg()));
            exit;
        }else { //上传成功
            $img_path = $date . $Upload->getFileName();
            echo json_encode(array('success' => true, 'file' => $img_path));
        }
    }

    /**
     * @name: upload_video_game_type_logo
     * @description: 上传视频游戏分类logo
     * @param:
     * @return: json
     * @author: Chen Zhong
     * @create: 2015-02-13 15:10:50
     **/
    public function upload_video_game_type_logo(){
        if( !$this->check_right( '140027' ) ){
            $arr['status'] = 1;
            $arr['message'] = '您没有操作权限！';
            $this->callback_ajax( $arr );
        }

        //引入上传类
        require APPPATH.'/libraries/simple_ajax_uploader.php';
        //上传图片后缀名限制
        $valid_extensions = array('gif', 'png', 'jpeg', 'jpg','webp');
        $Upload = new FileUpload('index-uploadfile');
        //上传大小限制
        $Upload->sizeLimit = 2*10485760;  //上限20M
        //创建图片存放目录
        $date =  '/video_game_type_logo' .date('/Y/m/d/');  //添加模块名作目录一部分
        $upload_dir = $this->config->item('image_root_path')  . $date;
        create_my_file_path($upload_dir,0755);
        //生成新图片名称
        $Upload->newFileName = md5(uniqid().$Upload->getFileName()).'.'.$Upload->getExtension();
        $result = $Upload->handleUpload($upload_dir, $valid_extensions);
        if (!$result) {
            echo json_encode(array('success' => false, 'msg' => $Upload->getErrorMsg()));
            exit;
        }else { //上传成功
            $img_path = $date . $Upload->getFileName();
            echo json_encode(array('success' => true, 'file' => $img_path));
        }
    }

    /**
     * @name:video_chal_gory_tag_save
     * @description: 视频频道分类标签修改
     * @author: Chen Zhong
     * @create: 2015-11-30 15:45:25
     **/
    public function video_game_type_save(){
        if( !$this->check_right( '140027' ) ){//如果没有权限
            $arr['status'] = 1;
            $arr['message'] = '您没有操作权限！';
            $this->callback_ajax( $arr );
        }

        //定义AJAX返回的数组
        $arr = array(
            'status'=>400,//执行状态(例如：200成功，301失败...),
            'message'=>'参数错误',//返回信息,
            'url'=>''//要跳转的地址
        );

        //获取操作数据
        $t_id = intval(get_var_post("t_id"));  //游戏分类id
        $t_name_cn = get_var_post("t_name_cn"); //游戏分类名称（中文）
        $t_name_en = get_var_post("t_name_en"); //游戏分类名称（英文）
        $t_desc = get_var_post("t_desc");  //游戏分类描述
        $t_status = intval(get_var_post("t_status"));  //显示状态
        $uploadfile = get_var_post("uploadfile");  //游戏分类背景图
        $logo = get_var_post("logo");  //游戏分类背logo

        $data = array(
            't_name_cn' => $t_name_cn,
            't_name_en' => $t_name_en,
            't_desc' => $t_desc,
            't_status' => $t_status
        );

        if(!empty($uploadfile)){
            $data['t_img'] = $uploadfile;
        }

        if(!empty($logo)){
            $data['t_logo'] = $logo;
        }

        if(empty($t_id)){ //添加

        }else{ //修改
            if($this->video_model->update_info( 'video_game_type', $data, array('t_id'=>$t_id) )){
                //1添加，2修改，3删除，4数据导入，5数据导出，6其他
                $tmp_log_msg = "视频游戏分类更新成功,id号为：{$t_id}";
                $this->video_model->log_db_admin( $tmp_log_msg, 2, __CLASS__ );

                $arr['status'] = 200;
                $arr['message'] = '视频游戏分类更新成功！';
                $arr['url'] = '/admin/video/video_game_type_list';
                $this->callback_ajax( $arr );
            }
        }
    }

    /**
     * @name: ajax_change_game_type_status
     * @description: ajax更改视频游戏分类状态
     * @param: id int 视频游戏分类ID
     * @param: status int 推荐（1：显示 2：隐藏）
     * @return: json
     * @author: Chen Zhong
     * @create: 2015-11-03 09:49:50
     **/
    public function ajax_change_game_type_status(){
        if( !$this->check_right( '140027' ) ){//如果没有权限
            $arr['status'] = 1;
            $arr['message'] = '您没有操作权限！';
            $this->callback_ajax( $arr );
        }

        $id	=  get_var_value( 'id'); //视频分类id
        $status = intval(get_var_value('status')); //推荐状态
        if(empty($id) || empty($status)){
            exit(json_encode(array('msg'=>'参数错误','status'=>400)));
        }

        if($this->video_model->change_xiaolu_recom_status($id,$status)){
            //记录日志 1添加，2修改，3删除，4数据导入，5数据导出，6其他
            $tmp_log_msg = ($status == 2) ? ("小鹿推荐视频分类成功,视频分类ID：{$id}") : ("取消小鹿推荐视频分类成功,视频分类ID为：{$id}");
            $this->video_model->log_db_admin( $tmp_log_msg, 2, __CLASS__ );
            exit(json_encode(array('msg'=>'操作成功','status'=>200)));
        }else{
            //记录日志 1添加，2修改，3删除，4数据导入，5数据导出，6其他
            $tmp_log_msg = ($status == 2) ? "小鹿推荐视频分类失败,视频分类ID：{$id}" : "取消小鹿推荐视频分类失败,视频分类ID为：{$id}";
            $this->video_model->log_db_admin( $tmp_log_msg, 2, __CLASS__ );
            exit(json_encode(array('msg'=>'操作失败','status'=>400)));
        }
    }

    /**
     * @name: upload_video_game_bg_img
     * @description: 上传视频游戏背景图片
     * @param:
     * @return: json
     * @author: Chen Zhong
     * @create: 2015-02-13 15:10:50
     **/
    public function upload_video_game_bg_img(){
        if( !$this->check_right( '140027' ) ){
            $arr['status'] = 1;
            $arr['message'] = '您没有操作权限！';
            $this->callback_ajax( $arr );
        }

        //引入上传类
        require APPPATH.'/libraries/simple_ajax_uploader.php';
        //上传图片后缀名限制
        $valid_extensions = array('gif', 'png', 'jpeg', 'jpg','webp');
        $Upload = new FileUpload('uploadfile');
        //上传大小限制
        $Upload->sizeLimit = 2*10485760;  //上限20M
        //创建图片存放目录
        $date =  '/video_game_bg_img' .date('/Y/m/d/');  //添加模块名作目录一部分
        $upload_dir = $this->config->item('image_root_path')  . $date;
        create_my_file_path($upload_dir,0755);
        //生成新图片名称
        $Upload->newFileName = md5(uniqid().$Upload->getFileName()).'.'.$Upload->getExtension();
        $result = $Upload->handleUpload($upload_dir, $valid_extensions);
        if (!$result) {
            echo json_encode(array('success' => false, 'msg' => $Upload->getErrorMsg()));
            exit;
        }else { //上传成功
            $img_path = $date . $Upload->getFileName();
            echo json_encode(array('success' => true, 'file' => $img_path));
        }
    }

    /**
     * @name video_type_del
     * @description 删除视频导航
     * @author Chen Zhong
     * @time 2016-02-17 11:11
     */
    public function video_type_del(){
        $return = array(
            'code' => 0,
            'msg' => '未知错误'
        );

        //权限判断
        if( !$this->check_right( '140027' ) ){
            $return['code'] = -2;
            $return['msg'] = '您没有操作权限！';
            echo json_encode($return);
            exit();
        }

        $id = get_var_get('id');
        $flag = $this->video_model->video_type_del( $id );
        if( $flag){
            $return['code'] = 1;
            $return['msg'] = '删除成功';

            //记录日志：1添加，2修改，3删除，4数据导入，5数据导出，6其他
            $tmp_log_msg = "删除视频分类成功，分类ID为：$id";
            $this->video_model->log_db_admin( $tmp_log_msg, 3, __CLASS__);
        }else{
            $return['code'] = -1;
            $return['msg'] = '删除失败';
        }

        echo json_encode($return);
    }

    /**
     * @name: video_tag_sub_list
     * @description: 标签推荐列表
     * @author: Chen Zhong
     * @create: 2015-11-27  17:19
     **/
    public function video_tag_sub_list() {
        if(!$this->check_right('140028')){
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }

        //标签列表
        $data = array(
            'js' => array(
                'App'=>'admin/scripts/app.js', //需要引入app.js
                'Index'=>'admin/scripts/index.js', //需要引入index.js
                'FormValidation'=>'admin/scripts/muzhiwan.js/video-chalgorytags-table-managed.js' //需要引入table-managed.js
            ),
        );

        //关联游戏信息数组
        $game_arr = $this->video_model->get_relev_game_arr();
        $data['game_arr'] = $game_arr;

        $this->display( $data, 'video_tag_sub_list' );
    }

    /**
     * @name: video_tag_sub_list
     * @description: 获取标签订阅推荐列表
     * @author: Chen Zhong
     * @create: 2015-11-27  17:19
     **/
    public function ajax_get_tag_sub_list() {
        if(!$this->check_right('140028')){
            $this->url_msg_goto( get_referer(), '您没有操作权限！' );
        }
        $start_record	= get_var_value( 'iDisplayStart' ); 	//从多少条开始查询
        $page_size		= get_var_value( 'iDisplayLength' ); 	//每页显示多少条记录
        $s_echo			= get_var_value( 'sEcho' );
        $bel_game			= intval(get_var_value( 'bel_game' )); //所属游戏
        $sub_recom			= intval(get_var_value( 'sub_recom' )); //是否推荐（1：已推荐 2：未推荐）
        $tag_name			= get_var_value( 'tag_name' ); //标签名称

        $where = '';
        if(!empty($bel_game)){
            $where .= " AND `vtc_game_id` = ".$bel_game;
        }

        if(!empty($sub_recom)){
            $where .= " AND `vtc_sub_recom` = ".$sub_recom;
        }

        if(!empty($tag_name)){
            $where .= " AND `vtc_name` = '%".$tag_name."%'";
        }

        //获取列表
        $res = $this->video_model->ajax_get_video_tag_sub_data( $start_record, $page_size, $where );

        $sub_arr = array(
            1 => '已推荐',
            2 => '未推荐'
        );

        if( !empty($res[0]) ){
            foreach($res[0] as $key => $val){
                $res[0][$key]['sub_recom_str'] = isset($sub_arr[$val['vtc_sub_recom']]) ? $sub_arr[$val['vtc_sub_recom']] : '未推荐';
            }
            echo $this->get_normol_json( $res[0],$s_echo,$res[1] );
        }else{
            echo $this->get_normol_json( array(),$s_echo,0 );
        }
    }

    /**
     * @name:ajax_xl_sub_recommand_tag
     * @description: 小鹿订阅推荐标签
     * @author: Xiong Jianbang
     * @create: 2015-12-10 上午10:21:48
     **/
    public function ajax_xl_sub_recommand_tag(){
        if( !$this->check_right( '140028' ) ){//如果没有权限
            $arr['status'] = 1;
            $arr['message'] = '您没有操作权限！';
            $this->callback_ajax( $arr );
        }

        $tagid = intval(get_var_post("tagid"));  //信息id
        $is_recommand = intval(get_var_post("is_recommand"));
        if(empty($tagid) || empty($is_recommand) || !in_array($is_recommand,array(1,2))){
            $arr['status'] = 1;
            $arr['message'] = '参数错误';
            $this->callback_ajax( $arr );
        }
        $url = '/admin/video/video_tag_sub_list';  //执行成功后返回列表页
        $data = array('vtc_sub_recom'=>$is_recommand);
        if($this->video_model->tag_info_update( $tagid,$data )){
            $tmp_log_msg = "标签推荐成功,ID为：{$tagid}";
            $this->video_model->log_db_admin( $tmp_log_msg, 2, __CLASS__ );
            $arr['status'] = 200;
            $arr['message'] = '标签推荐成功！';
            if($is_recommand == 2){
                $arr['message'] = '标签取消推荐成功！';
            }
            $arr['url'] = $url;
            $this->callback_ajax( $arr );
        }else{
            $arr['status'] = 3;
            $arr['message'] = '参数错误';
            $this->callback_ajax( $arr );
        }
    }

}