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
		$aite[$i] = file_get_contents($url[$i] . "get_prev_cnt.php", false);
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

if(isset($_POST["action"])){
	if($_POST["action"] == "add" || $_POST["action"] == "edit"){
		$item = trim(htmlspecialchars($_POST['article'], ENT_QUOTES)); //エスケープ処理
		
		//アップロード処理
		$post_tmpfile = $_FILES["attachmant_file"]["tmp_name"];
		if(is_uploaded_file($post_tmpfile)){
			$post_filename = "./data/" . $cnt . "/" . $_FILES["attachmant_file"]["name"];
			mkdir("./data/" . $cnt . "/");
			if(move_uploaded_file($post_tmpfile, $post_filename)){
				$attachmant_file_hash = hash('sha256', file_get_contents($post_filename));
				$attatchment_file_true = true;
			}
			else{
				$post_filename = null;
				$attachmant_file_hash = null;
				$attatchment_file_true = false;
			}
		}
		else{
			$post_filename = null;
			$attachmant_file_hash = null;
			$attatchment_file_true = false;
		}
	}
	else if($_POST["action"] == "del"){
		$item = "";
		$post_filename = null;
		$attachmant_file_hash = null;
		$attatchment_file_true = false;
	}

	if($_POST["action"] == "edit"){
		$message = "No." . $_POST["id"] . "修正しました";
	}
	if($_POST["action"] == "del"){
		$item = "";
		$message = "No." . $_POST["id"] . "削除しました";
	}
	if($_POST["action"] == "add"){
		$message = "書込みしました";
	}
	

	//ブロックチェーン処理
	$blogValue_temp = array();
	$blogValue_temp["id"] = $_POST["id"]; $blogValue_temp["id"] = $cnt;
	if($_POST["action"] == "del"){$blogValue_temp["id"] = $_POST["id"];}
	$blogValue_temp["action"] = $_POST["action"];
	$blogValue_temp["article"] = (string)$item;
	$blogValue_temp["attatchment_file"] = $attatchment_file_true;
	$blogValue_temp["attatchment_file_name"] = $post_filename;
	$blogValue_temp["attatchment_file_hash"] = $attachmant_file_hash;
	$blogValue = serialize($blogValue_temp);

	$newBlock = $masterBlockchain->generateNextBlock($blogValue);
	$masterBlockchain->addBlock($newBlock);
	$newBlockData = $newBlock->getData();
	rewind($handle_masterchain);
	fputs($handle_masterchain, serialize($masterBlockchain));
	flock($handle_masterchain, LOCK_UN); //ファイルロック解除
	fclose($handle_masterchain);

	if($debug_time == true){$time_block_end = microtime(true);}

	//相手のサーバーを読んであげる
	$sync = "0";
	$sync_on = rand(0,1);
	$synced = "0";
	if($realtime_sync == true){
		for ($i = 0; $i < count($iplist); $i++){
			//$sync = rand(0,1);
			if($up[$i] == 1 && $sync == $sync_on || $synced == "0"){
				file_get_contents($url[$i] . "realtime_sync.php");
				$synced++;
				$sync = "1";
			}
			else if($sync == "1"){
				$sync = "0";
			}
			else if($sync == "0"){
				$sync = "1";
			}
		}
	}


	$cnt = $masterBlockchain->count(); //自サーバーのブロックチェーン要素数を確認

	$maxid = 0;
	for ($i = 0; $i < count($iplist); $i++){
		$ip = $iplist[$i];
		$url[$i] = "http://" . $ip . "/block/";
		$up[$i] = ping($ip); //$ipに死活監視したいIPを指定する

		if($up[$i] == 1){
			$aite[$i] = file_get_contents($url[$i] . "get_prev_cnt.php", false);
		}
		else{
			$aite[$i]=0; //相手サーバーに接続できなかった場合、相手の要素数0とする
		}
		if($aite[$maxid] <= $aite[$i]){
			$max = $aite[$i];
			$maxid = $i;
		}
	}

	//header("Location: ./");
}

$isvalid = $masterBlockchain->isValidChain($masterBlockchain);
if (isset($_POST['force_import'])){
	$temp1 = file_get_contents($url[$maxid] . "memory_chain.txt", false, stream_context_create($context)); //相手サーバーのmemory_chain.txtを$temp2に代入
	file_put_contents("memory_chain.txt",print_r($temp1,true)); //自身のサーバーに保存

	header("Location: ./");
	$_POST['force_import'] = "";
}

if($debug_time == true){$time_end = microtime(true);}
?>

<!DOCTYPE html>
<head>
	<meta charset = "utf-8"  lang ="ja">
	<title>掲示板<?=$ver ?> on <?php echo($_SERVER["SERVER_ADDR"]);?></title>
</head>
<body>
<h1>掲示板<?=$ver ?> on <?php echo($_SERVER["SERVER_ADDR"]);?></h1>
<?php if(isset($message_diff_sync)){echo $message_diff_sync . "<br><br>";}?>
<?php if(isset($message)){echo $message . "<br>";}?>
<?php
for ($i = 0; $i < count($iplist); $i++){
	if($i == $maxid){
		echo "*";
	}
	echo $up[$i] ? $i." ".$iplist[$i].':正常稼働中,チェーンの長さ: '.$aite[$i]."<br>":$i." ".$iplist[$i].':応答なし<br>';
}
	if($aite[$maxid] < $cnt){
		echo "*";
	}
	echo "自分のチェーンの長さ: " . $cnt . "<br>";
	echo "一番多い相手: *" . $maxid . " のチェーンの長さ: " . $aite[$maxid] . "<br>";
?>
<! 以下、チェーンの妥当性ハッシュチェック>
<?php
if($isvalid == true){
	echo('<font color="green">Success! Chain is Valid!!!!!</font>');
}
else if($isvalid == false){
	echo('<font color="red">Warning Chain is InValid(´;ω;｀)</font>');
	echo ('
	<form method="POST" acvtion=""　id="form_force-import">
	<input type="submit" name="force_import" value="強制インポート" onClick="">
	</form>
	');
	echo('<font color="red">表示を中止します</font>');
	exit;
}
?>

<! 以下、リセットボタン>
<form method="POST" action=""　id="form_reset">
	<input type="submit" name="reset" value="リセットして再読み込み" onClick="">
</form>

<! 以下、書き込みフォーム>
<form method="POST" action="" enctype="multipart/form-data">
	<input type="hidden" name="id" value="-1">
	<input type="text" name="article" autofocus>
	<input type="file" name="attachmant_file">
	<button type="submit" name="action" value="add">新規書込</button><br>
</form>

<! 以下、書き込まれているデータ表示兼編集フォーム(チェーン直接読出方式)>
※最新200チェーンのみ読み出しています<br>
<?php
$a = $masterBlockchain -> getBlockchain();

for($i = count($a)-200; $i < count($a); $i++){
	if($i < 1){$i = 1;}
	$b_id = $a[$i] -> getIndex();
	$b = $a[$i] -> getData();
	$c = unserialize($b);
	$article = $c["article"];
	$temp_id[$c["id"]] = $c["id"];
	$attatch[$c["id"]] = $c["attatchment_file"];
	$attatch_link[$c["id"]] = $c["attatchment_file_name"];
	$temp[$c["id"]] = $article;
}
?>

<?php foreach((array)$temp as $key => $value){?>
	<hr>
	<form method="POST" action="">
		<?=$key . ": "?>
		<input type="hidden" name="id" value="<?=$key?>">
		<!--<textarea name="article" cols="60" rows="1" maxlength="" wrap="hard"><?php echo $value;?></textarea>--><?php echo $value;?>
		<?php if($attatch[$key] == true){echo '<a href="' . $attatch_link[$key] . '">画像ファイルあり</a>';};?>
		<button type="submit" name="action" value="edit">修正</button>
		<button type="submit" name="action" value="del">中身を削除</button>
	</form>
<?php }?>

<?php
if($debug_time == true){
	function csv_read($file){
		if (($handle = fopen($file, "c+")) !== FALSE) {
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
		$fp = fopen($file, 'c+');
		foreach ($array as $fields) {
			fputcsv($fp, $fields);
		}
		fclose($fp);
		return 0;
	}

	
	$time_page_end = microtime(true);
	$time = array((string)$cnt, (string)$time_start, @(string)$time_block_end, (string)$time_end, (string)$time_page_end);
	var_export($time);
	if(file_exists("./time.csv") == true){
		$csv = csv_read("./time.csv");
	}
	else{
		$csv = array(array(0,0,0,0));
	}
	
	array_push($csv, $time);
	csv_write($csv, "./time.csv");
}
?>
</body>
</html>