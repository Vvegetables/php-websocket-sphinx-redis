<?php
namespace GatewayWorker\Lib;

/*
	机器人智能回复实现：
	1.分词
	2.相似词库查询
	3.sphinx查询
*/

class RobotResponse{
	private static $sphinx = null;
	private static $mysqli = null;
	private static $flag = 0;
	private static $client_product = array();
	private static $qrcode = false;

	private static $product = array();

	public static function start(){
		
		if(self::$sphinx == null){
			self::$sphinx = new \SphinxClient();
			self::$sphinx->SetServer('127.0.0.1',9312);
			self::$sphinx->SetMatchMode(SPH_MATCH_ANY);

			self::$sphinx->SetConnectTimeout(3);
			self::$sphinx->SetArrayResult(true);
			self::$sphinx->SetMaxQueryTime(10);

		}
		if(self::$mysqli == null){

			self::$mysqli = new \mysqli('10.21.30.204','root','123456','smartservice');
			self::$mysqli->set_charset("utf8");
			if(self::$mysqli->errno){
				printf($mysqli->error);
				//exit();
				return;
			}			
		}

	}


	/*
		得到用户咨询的产品类型
	*/
	public static function getProduct(){

		$sql = " SELECT DISTINCT `productname` FROM `products`; ";
		self::start();
		$res = self::$mysqli->query($sql);
		$response = array();
		while($row = $res->fetch_array(MYSQLI_NUM)){
			$response[] = $row[0];
		}
		return $response;
	}


	/*
		获得热门问题
	*/

	public static function getHotProblem($product){
		$connect = new \mysqli('10.21.30.204','root','123456','smartservice');
		$connect->set_charset("utf8");
		if($connect->errno){
			printf($connect->error);
			//exit();
			return;
		}

		//匹配表需要改正一下
		// $sql = " SELECT third FROM `products` WHERE productname = '$product'; "
		// $res = $connect->query($sql);
		// $nums = $res->num_rows;
		$sql = " SELECT third FROM `products` WHERE productname = '$product' ORDER BY count DESC LIMIT 15; ";

		$res = $connect->query($sql);
		while($row = $res->fetch_array(MYSQLI_NUM)){
			$arr[] = $row[0];
		}
		

		$start = mt_rand(0,15)%15;
		$step = mt_rand(1,5);
		$num = 1;
		$que[] = $start;
		while($num < 4){
			$flag = false;
			$mt = mt_rand(2,10);
			$jj = ($start + $step +$mt) % 15;
			for($j = 0; $j < count($que); $j++){
				if($que[$j] == $jj){
					$flag = true;
					break;
				}
			}
			if(!$flag){
				$que[] = $jj;
				$num++;

			}
		}
		for($i = 0; $i < 4; $i++){

			$response[] = $arr[$que[$i]];
		}

		$res->close();
		$connect->close();
		return $response;
	}


	/*
		分词 得到关键词
	*/
	public static function getKey($key){
		
		if(isset($key)) $content = $key;
		else $content = "这个键盘的使用方法";

		$data = array(
    		'data' => $content,
    		'respond' => 'json',
    		'ignore' => 'yes',
    		//'multi' => 5,
		);

/**
	return data
	{
		public status => string 'ok'
		public word => array(
			0 => object{
				public word => string(result)
				public off => int 0 (offset)
				public len => int 6	(length)
				public attr => string (attribute)
			}
			1 =>
			...
		)
	}
**/

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'http://www.xunsearch.com/scws/api.php');
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$response = curl_exec($ch);
		curl_close($ch);
		$res = json_decode($response);
		$key = $res->words;
		//var_dump($key);
		return $key;
	}


	/*
		知识库无法回答的问题，机器人进入闲聊模式
	*/
	public static function getGossip($content){

		$data = array(
    		'token' => '6946E73A4A48AA0E85F238109DBB427C',
    		'query' => $content,
    		'session_id' => '6946E73A4A48AA0E85F238109DBB427C',
		);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'http://www.yige.ai/v1/query');
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$response = curl_exec($ch);
		curl_close($ch);
		$res = json_decode($response);
		//var_dump($res);
		// $answer = $res->answer;
		//var_dump($key);
		return $res;
	}
	public static function flushQueue(){
		self::$client_product = array();
	}

	public static function delClient($client_name){
		
		if(isset(self::$client_product[$client_name])){
			unset(self::$client_product[$client_name]);
			self::$client_product = array_values(self::$client_product);
		}
		
	}

	public static function getUniqueProduct($client_name){
		if(isset(self::$client_product[$client_name])){
			return self::$client_product[$client_name];
		}else{
			return false;
		}
		
	}

	public static function setQrcode($value){
		self::$qrcode = $value;
	}

	public static function setUniqueProduct($client_name,$product){
		self::$client_product[$client_name] = $product;
		self::$qrcode = true;
	}

	/*
		根据用户的问句，获得回复的语句
	*/
	public static function response($key,$table){

		// if(!self::$qrcode){
		// 	$res = self::getGossip($key);
		// 	if("200" == $res->status->code){
		// 		if("产品库" == $res->parameter_recognize[0]->type){
		// 			self::$flag++;
		// 		}else if("肯定" == $res->parameter_recognize[0]->type){
		// 			self::$flag++;
		// 		}
		// 		if(2 == self::$flag){
		// 			self::$client_product[$client_name] = $res->$res->parameter_recognize[0]->text;
		// 			self::$flag = 0;
		// 		}
		// 		return $res->answer;
		// 	}
		// }
		
		self::start();
		$words = self::getKey($key);
		foreach ($words as $k => $value) {
			if($value->attr == 'n' || $value->attr == 't'){
				$res[] = $value->word;
			}
		}
		//将名词合并
		if(empty($res)){
			$res[0]="";
		}
		$str = join(" ", $res);
		//var_dump($str);

		$result = self::$sphinx->Query($str,'simlib');
		if(!empty($result['matches'])){
			//拿到id
			$id = $result['matches'][0]['id'];
			$sql = " SELECT stand FROM `simlib` WHERE id = ?; ";

			$stmt = self::$mysqli->stmt_init();
			$stmt->prepare($sql);
			$stmt->bind_param('i',$id);
			$stmt->execute();
			$stmt->bind_result($word);
			$stmt->fetch();
			$stmt->close();
		}

		if(isset($word)){
			$result = self::$sphinx->Query($word,'test1 testrt');	//sphinx全文检索
		}else{
			$result = self::$sphinx->Query($str,'test1 testrt');	//sphinx全文检索
		}

			
		//var_dump($result);

		if(!empty($result['matches'])){
			//$response = join("|",$result);

			//拿到id
			$id = $result['matches'][0]['id'];
			$sql = " SELECT content FROM `products` WHERE id = ? AND productname = ?; ";

			$stmt = self::$mysqli->stmt_init();
			$stmt->prepare($sql);
			$stmt->bind_param('is',$id,$table);
			$stmt->execute();
			$stmt->bind_result($content);
			$stmt->fetch();
			$stmt->close();

			$stmt = self::$mysqli->stmt_init();
			$update = " UPDATE `products` SET count = count + 1 WHERE id = ?; ";
			$stmt->prepare($update);
			$stmt->bind_param('i',$id);
			$stmt->execute();						
			$stmt->close();

			self::$mysqli->close();
			self::$mysqli = null;
			return $content;

		}else{	//如果为空的话 表示没有匹配项，是不能解决的新问题
			//知识库有缺陷，将用户的问题保存下来
			$mytime = time()+8*3600;
			$sql = " INSERT INTO questions(key,description,mytime) VALUES('$str','$key','$mytime'); ";
			// $stmt = self::$mysqli->stmt_init();
			// $stmt->prepare($sql);
			// $time = time()+8*3600;
			// $stmt->bind_param('sss',$str,$key,$time);
			// $stmt->execute();
			// $stmt->close();
			self::$mysqli->query($sql);
			self::$mysqli->close();
			self::$mysqli = null;

			$gossip = self::getGossip($key);

			$unkown = "对不起，您的问题我暂时无法回答，您可以转接人工客服";
			return $gossip->answer;
		}
	}

	public static function updateStatus($kefuname,$status){
		
		self::start();
		$sql = " SELECT nickname FROM user WHERE nickname = '$kefuname';";
		$res = self::$mysqli->query($sql);
		if($res->num_rows){
			$sql = " UPDATE user SET status = '$status' WHERE nickname = '$kefuname';";
			self::$mysqli->query($sql);			
		}
		// else{
		// 	$sql = " INSERT INTO user(servername,nickname,status) VALUES('$kefuname','$kefuname','$status');";
		// 	self::$mysqli->query($sql);
		// }

	}
}

/*
	测试
*/

// $return = RobotResponse::response("愚人节的相关活动");
// var_dump($return);

// $return = RobotResponse::getHotProblem();
// var_dump($return);

// $return = RobotResponse::getProduct();
// var_dump($return);