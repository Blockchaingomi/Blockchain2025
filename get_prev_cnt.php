<?php
require "blockchain.php"; //blockchain処理プログラムをインポート
$masterBlockchain = new Blockchain(); //オブジェクト定義

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

$cnt = $masterBlockchain->count();
echo $cnt; //$cntを返す
?>