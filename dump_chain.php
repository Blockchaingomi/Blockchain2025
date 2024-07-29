<HTML>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="content-type" content="text/html; charset=utf-8">
<style type="text/css">
.table1 {
	border-collapse: collapse;
	table-layout: fixed;
}
</style>

<body>

<?php
if(!isset($_GET["server"]) || $_GET["server"] == ""){
	$_GET["server"] = "own";
}
?>

<form method="GET" action="" enctype="multipart/form-data">
	他サーバーIP: 
	<input type="text" name="server" autofocus <?php if(isset($_GET["server"])){echo 'value="' . $_GET["server"] . '"';}?>>
	<button type="submit">送信</button><br>
</form>
<form method="GET" action="" enctype="multipart/form-data">
	<button type="submit" name="server" <?php echo 'value="' . $_SERVER["SERVER_ADDR"] . '"';?>>自サーバーIP(<?php echo $_SERVER["SERVER_ADDR"];?>)を送信</button><br>
</form>
<form method="GET" action="" enctype="multipart/form-data">
	<button type="submit" name="server" value="own_ip">自サーバーIPを開く</button><br>
</form>
<form method="GET" action="" enctype="multipart/form-data">
	<button type="submit" name="server" value="own">同一ディレクトリ内のmemory_chain.txtを開く</button><br>
</form>

<?php
require "blockchain.php"; //blockchain処理プログラムをインポート
$masterBlockchain = new Blockchain(); //オブジェクト定義

function show_table($table){
    echo '<table border=1 class="table1" style="table-layout:fixed" width="100%">';
    foreach($table as $row){
        echo "<tr>";
        foreach($row as $cel){
			if(is_array($cel)){
				echo '<td style="word-wrap:break-word;"><table border=1><tr>';
				$keys = array_keys($cel);
				for($i = 0; $i < count($keys); $i++){
					echo '<td>' . $keys[$i] . "</td>";
				}
				echo "</tr>"; echo "<tr>";
				for($i = 0; $i < count($keys); $i++){
					$key = $keys[$i];
					echo '<td>' . $cel[$key] . "</td>";
				}
				echo "</tr></table></td>";
			}
			else{
				echo '<td style="word-wrap:break-word;">' . $cel . "</td>";
			}
        }
        echo "<tr>";
    }
    echo "</table>";
}

if(isset($_GET["server"]) && $_GET["server"] != "own"){
	if($_GET["server"] == "own_ip"){
		$_GET["server"] = $_SERVER["SERVER_ADDR"];
	}

	$link = "http://" . $_GET["server"] . "/block/memory_chain.txt";
	$masterBlockchain = unserialize(file_get_contents($link));

	echo "read from: " . $link;
}
else{
	$handle_masterchain = fopen("memory_chain.txt", "c+");
	flock($handle_masterchain,LOCK_EX); //ファイルロック

	if(filesize("memory_chain.txt") > 0){
		$file_masterchain = "";
		while($line = fgets($handle_masterchain)){
			$file_masterchain .= $line . "\n";
		}
		$masterBlockchain = unserialize($file_masterchain);
	}
	else{
		rewind($handle_masterchain);
		fputs($handle_masterchain, serialize($masterBlockchain));
		rewind($handle_masterchain);
	}
	flock($handle_masterchain, LOCK_UN); //ファイルロック解除
	fclose($handle_masterchain);

	echo "read from: 同一ディレクトリ内のmemory_chain.txt";
}

$masterchain = $masterBlockchain -> getBlockchain();
$blocks = array();
$block_keys["0"] = array("index", "previousHash", "timestamp", "data(raw)", "nonce", "hash", "ext...mining用", "ext...data(unserialize)");

for($i = 0; $i < count($masterchain); $i++){
	$blocks[$i]["0"] = $masterchain[$i] -> getIndex();
	$blocks[$i]["1"] = $masterchain[$i] -> getPreviousHash();
	$blocks[$i]["2"] = $masterchain[$i] -> getTimestamp();
	$blocks[$i]["3"] = $masterchain[$i] -> getData();
	$blocks[$i]["4"] = $masterchain[$i] -> getNonce();
	$blocks[$i]{"5"} = $masterchain[$i] -> getHash();
	$blocks[$i]{"6"} = "{". $blocks[$i]["0"] . "}" . "{" . $blocks[$i]["1"] . "}" . "{" . $blocks[$i]["2"] . "}" . "{" . $blocks[$i]["3"] . "}" . "{" . $blocks[$i]["4"] . "}";
	$blocks[$i]["7"] = unserialize($blocks[$i]["3"]);
}

$blocks = array_merge($block_keys, $blocks);

show_table($blocks);
?>
</body>
</html>