<?php
require "blockchain.php"; //blockchain処理プログラムをインポート
$masterBlockchain = new Blockchain(); //オブジェクト定義
if(filesize("memory_chain.txt") > 0){
	$masterBlockchain = unserialize(file_get_contents("memory_chain.txt")); //memory_chain.txtからチェーン取込
}

$exp_prevTime = $masterBlockchain->prevTime(); //最後のチェーンの時刻を$exp_prevTimeに格納
echo $exp_prevTime; //$exp_prevTimeを返す
?>