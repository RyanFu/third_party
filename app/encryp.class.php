<?php
header("Content-Type: text/html; charset=utf-8"); 
/**
   last modified: 2011-05-27
   Usage:
   Encryption some text;
*/	
class Encryption{
	public $ORD_x = NULL;
	public $ORD_z = NULL;
	function __construct() {
		$this->ORD_x = ord('x');
		$this->ORD_z = ord('z');
		//define ( "ORD_x", ord ( 'x' ) ); //'x'��ASCII   120
		//define ( "ORD_z", ord ( 'z' ) ); //'z'��ASCII   122	
	}
	
	/*
	 * ����
	 */
	public function Encrypt($source) {
		$c = '';
		$i = 0;
		$h = 0;
		$l = 0;
		$j = 0;
		$cSrc = str_split ( $source );
		$len = count ( $cSrc );
		$arr = array ();
		for($i = 0; $i < $len; $i ++) {
			$c = ord ( $cSrc [$i] );
			//echo "c:".$c,'<br/>';
			$h = ($c >> 4) & 0xf;
			//echo "h:".$h,'<br/>';
			$l = $c & 0xf;
			//echo "l:".$l ,'<br/>';
			//echo $h + ORD_x ;
			//echo '<br/>';
			//echo $l + ORD_z ;
			//echo '<br/>';
			$arr [$j] = '\0';
			$arr [$j] = chr ( $h + $this->ORD_x );
			$arr [$j + 1] = chr ( $l + $this->ORD_z );
			$j += 2;
		}
		
		$result = '';
		foreach ( $arr as $k => $v ) {
			$result .= $v;
		}
		
		return $result;
	}

	/*
	 * ����
	 */
	public function Decrypt($source) {
		$cSrc = str_split ( $source );
		/*
	cSrc[66]:123
	cSrc[661]:122
	h:3
	l:0
	m:48
	n:0
	m + n:48
	*/
		$i = 0;
		$h = 0;
		$l = 0;
		$m = 0;
		$n = 0;
		$j = 0;
		
		//$i = 66;
		//$cSrc [$i] = 123;
		//$cSrc [$i + 1] = 122;
		

		//echo 126 - ORD_x;
		//echo 122 - ORD_z;
		

		//echo "ORD_x:".ORD_x;
		//echo "<br/>";
		//echo "ORD_z:".ORD_z;
		//echo "<br/>";
		$len = count ( $cSrc );
		$arr = array ();
		for($i = 0; $i < $len; $i = $i + 2) {
			//echo $cSrc[$i] ;
			//echo "<br/>";
			//echo $cSrc[$i+1] ;
			//echo "<br/>";
			

			$h = (ord ( $cSrc [$i] ) - $this->ORD_x);
			$l = (ord ( $cSrc [$i + 1] ) - $this->ORD_z);
			$m = $h << 4;
			$n = $l & 0xf;
			$r = $m + $n;
			$arr [$j] = '\0';
			$arr [$j] = chr ( $r );
			$j ++;
		}
		
		$result = '';
		foreach ( $arr as $k => $v ) {
			$result .= $v;
		}
		
		return $result;
	}

	/*
	 * ���� �ⲿ����ʱ�����һ�����ʵ�����
     * ���磺  $en = new Encryption(); $en->Encrypt($str);
	 */	
	public static function Test(){
		echo $str = "com.anquanxiadddddddd.aqxservice 10086 1000";
		echo "<br/>";
		//echo strlen($str);	//35
		$str_en = Encrypt($str);
		echo "<br/>";
		echo $str_en = base64_encode($str_en);
		echo "<br/>";
		$str_en = base64_decode($str_en);
		echo "<br/>";
		echo Decrypt($str_en);	
	}
}
?>
