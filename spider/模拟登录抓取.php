<?php
	function zhihu(){
		$referer = 'Referer: https://www.zhihu.com/';
		$url = "https://www.zhihu.com/people/pandabajie/activities";
		$cookie = "z_c0=Mi4wQUFCQTZTd2RBQUFBUUFEdlg1dUlDaGNBQUFCaEFsVk5sSWg0V0FEenFzUGMzVlMxNVotb3ZUOE1yU2lNZFZZNm1R|1481702292|ffc245873e8ef21514a0be2c2256d6ec2c8f93a3; Domain=zhihu.com; expires=Fri, 13 Jan 2017 07:58:12 GMT; httponly; Path=/";
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		// 把post的变量加上
		curl_setopt($ch, CURLOPT_COOKIE, $cookie);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);    //SSL 报错时使用
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);    //SSL 报错时使用
		curl_setopt($ch, CURLOPT_REFERER, $referer);    //来路模拟
		$output = curl_exec($ch);
		curl_close($ch);
		echo $output;
	}


	public function sipo(){
        $referer = 'Referer: http://cpquery.sipo.gov.cn';
        $url = "http://cpquery.sipo.gov.cn//txnQueryOrdinaryPatents.do?select-key%3Ashenqingh=&select-key%3Azhuanlimc=&select-key%3Ashenqingrxm=&select-key%3Azhuanlilx=&select-key%3Ashenqingr_from=&select-key%3Ashenqingr_to=&select-key%3Adailirxm=&very-code=&captchaNo=&fanyeflag=1&verycode=fanye&attribute-node:record_start-row=41&attribute-node:record_page-row=20&#";
        $cookie = "JSESSIONID=f90de18be921306cd054823ea8df; path=/; domain=cpquery.sipo.gov.cn";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        // 把post的变量加上
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);    //SSL 报错时使用
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);    //SSL 报错时使用
        curl_setopt($ch, CURLOPT_REFERER, $referer);    //来路模拟
        $output = curl_exec($ch);
        curl_close($ch);
        echo strip_tags($output);
    }