<?php
$debug = true;
$debug_time = true;
$realtime_sync = true;
$ver = "5";

if($debug_time == true){$time_start = microtime(true);}
require "blockchain.php"; //blockchain処理プログラムをインポート
$masterBlockchain = new Blockchain(); //オブジェクト定義

$handle_masterchain = fopen("memory_chain.txt", "c+");

if(filesize("memory_chain.txt") > 0){
	flock($handle_masterchain,LOCK_EX);
	$file_masterchain = "";
	while($line = fgets($handle_masterchain)){
		$file_masterchain .= $line . "\n";
	}
	$masterBlockchain = unserialize($file_masterchain);
	flock($handle_masterchain,LOCK_UN);
}
else{
	flock($handle_masterchain,LOCK_EX);
	rewind($handle_masterchain);
	fputs($handle_masterchain, serialize($masterBlockchain));
	flock($handle_masterchain,LOCK_UN);
}

if (isset($_POST['reset'])){
	file_put_contents("memory_temp.txt", "");
	file_put_contents("memory_chain.txt", "");
	header("Location: ./");
}

$cnt = $masterBlockchain->count(); //自サーバーのブロックチェーン要素数を確認

$iplist = explode (" ", file_get_contents("IP_List.txt"));
function ping_native($host){ //pingを送信し相手の稼働を確認する自作関数
	$r = exec(sprintf('ping -n 1 -w 1 %s', escapeshellarg($host)), $res, $rval); //Win用
	$r = exec(sprintf('ping -c 1 -W 1 %s', escapeshellarg($host)), $res, $rval); //RasPi用
	return $rval === 0;
}
function ping($host,$port=80,$timeout=0.04){
	@$fsock = fsockopen($host, $port, $errno, $errstr, $timeout);
	if( ! $fsock ){
		return 0;
	}
	else{
		return 1;
	}
}

for ($i = 0; $i < count($iplist); $i++){
	if($iplist[$i] == $_SERVER["SERVER_ADDR"]){
		array_splice($iplist, $i, 1);
	}
}
$maxid = 0;
for ($i = 0; $i < count($iplist); $i++){
	$ip = $iplist[$i];
	$url[$i] = "http://" . $ip . "/block/";
	$up[$i] = ping($ip); //$ipに死活監視したいIPを指定する

	if($up[$i] == 1){
		$aite[$i] = file_get_contents($url[$i] . "get_prev_cnt.php", true);
	}
	else{
		$aite[$i]=0; //相手サーバーに接続できなかった場合、相手の要素数0とする
	}
	if($aite[$maxid] <= $aite[$i]){
		$max = $aite[$i];
		$maxid = $i;
	}
}
if($cnt < $max){
	flock($handle_masterchain,LOCK_EX);
	rewind($handle_masterchain);
	if(filesize("memory_chain.txt") > 0){
		$file_masterchain = "";
		while($line = fgets($handle_masterchain)){
			$file_masterchain .= $line . "\n";
		}
		$masterBlockchain = unserialize($file_masterchain);
	}

	//全コピ方式
	//file_put_contents("memory_temp.txt", file_get_contents($url[$maxid] . "memory_temp.txt"));
	//file_put_contents("memory_chain.txt", file_get_contents($url[$maxid] . "memory_chain.txt"));

	//差分方式
	$temp = file_get_contents($url[$maxid] . "get_chain.php?action=get_chain_diff&cnt=" . $cnt);
	if($debug == true){echo "取得: " . $temp;}
	$temp = unserialize($temp);

	if(count($temp) != $max - $cnt){
		$message_diff_sync = "差分同期エラー発生";
	}

	for($i = 0; $i < count($temp); $i++){
		$add_success = $masterBlockchain -> addBlock($temp[$i]);
	}

	rewind($handle_masterchain);
	fputs($handle_masterchain, serialize($masterBlockchain));
	rewind($handle_masterchain);
	$file_masterchain = "";
	while($line = fgets($handle_masterchain)){
		$file_masterchain .= $line . "\n";
	}
	$masterBlockchain = unserialize($file_masterchain);
	$cnt = $masterBlockchain->count();
	flock($handle_masterchain,LOCK_UN);

	$message_diff_sync = (count($temp) . "個差分同期しました");
	//header("Location: ./");
}

$isvalid = $masterBlockchain->isValidChain($masterBlockchain);
if (isset($_POST['force_import'])){
	//$temp1 = file_get_contents($url[$maxid] . "memory_temp.txt", false, stream_context_create($context)); //相手サーバーのmemory_temp.txtを$temp1に代入
	//file_put_contents("memory_temp.txt",print_r($temp1,true)); //自身のサーバーに保存

	$temp2 = file_get_contents($url[$maxid] . "memory_chain.txt", false, stream_context_create($context)); //相手サーバーのmemory_chain.txtを$temp2に代入
	file_put_contents("memory_chain.txt",print_r($temp2,true)); //自身のサーバーに保存

	header("Location: ./");
	$_POST['force_import'] = "";
}

if($debug_time == true){$time_end = microtime(true);}
?>

<?php
if($debug_time == true){
	function csv_read($file){
		if (($handle = fopen($file, "r")) !== FALSE) {
			$i = 0;
			while ($data = fgetcsv($handle)) {
				$csv[$i] = $data;
				$i++;
			}
			fclose($handle);
			return $csv;
		}
		else{
			$csv = array(0, 0, 0, 0);
		}
	}

	function csv_write($array, $file){
		$fp = fopen($file, 'w');
		foreach ($array as $fields) {
			fputcsv($fp, $fields);
		}
		fclose($fp);
		return 0;
	}

	$time_page_end = microtime(true);
	$time = array((string)$cnt, (string)$time_start, @(string)$time_block_end, (string)$time_end, (string)$time_page_end);
	var_export($time);
	$csv = csv_read("./time.csv");
	array_push($csv, $time);
	csv_write($csv, "./time.csv");
}
?>
</body>
</html>