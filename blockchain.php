<?php
//ini_set('display_errors', 1);
$debug = false;
class Block
{
    private $index;
    private $previousHash;
    private $timestamp;
    private $data;
    private $nonce;
    private $hash;

    function __construct($index, $previousHash, $timestamp, $data, $nonce, $hash)
    {
        $this->index = $index;
        $this->previousHash = $previousHash;
        $this->timestamp = $timestamp;
        $this->data = $data;
        $this->nonce = $nonce;
        $this->hash = $hash;
        /*
        $this->index = substr($index, -6, 6);
        $this->previousHash = substr($previousHash, -64, 64);
        $this->timestamp = substr($timestamp, -22, 22);
        $this->data = substr($data, -100, 100);
        $this->nonce = substr($nonce, -15, 15);
        $this->hash = substr($hash, -64, 64);
        */
    }

    function getIndex()
    {
        return $this->index;
    }

    function getPreviousHash()
    {
        return $this->previousHash;
    }

    function getTimestamp()
    {
        return $this->timestamp;
    }

    function getData()
    {
        return $this->data;
    }

    function getNonce()
    {
        return $this->nonce;
    }

    function getHash()
    {
        return $this->hash;
    }
}

//カウント可能にするには「class Blockchain implements Countable」に
class Blockchain
{
    private $blockchain = [];

    function __construct()
    {
        $this->blockchain[] = $this->getGenesisBlock();
    }

    function count(Blockchain $newBlockchain = NULL)
    {
        if(isset($newBlockchain)){
            return count($newBlockchain->getBlockchain());
        }
        else{
            return count($this -> blockchain);
        }
    }
    function getBlockchain()
    {
        return $this->blockchain;
    }

    /**
     * ジェネシスブロック生成・取得
     */
    function getGenesisBlock(): Block
    {
        return new Block(
            "0",
            "0",
            1465154705,
            'my genesis block!!',
            "0",
            '816534932c2b7154836da6afc367695e6337db8a921823784c14378abed4f7d7'
        );
    }

    /**
     * ハッシュ生成
     */
    function calculateHash($index, $previousHash, $timestamp, $data, $nonce)
    {
        global $debug;
        if($debug == true){
            echo '</br></br><textarea name="article1" cols="60" rows="5" maxlength="" wrap="hard">';
            echo "チェーン: " . $index . " 前ハッシュ: " . $previousHash . " Time: " . $timestamp . " nonce: " . $nonce . "トラザク: " . $data . " </textarea></br></br>";
            echo gettype($index) . " " . gettype($previousHash) . " " . gettype($timestamp) . " " . gettype($data) . " " . gettype($nonce) . " ";
        }
        
        //村山、文字列結合
        $prevhashandnewtrans = "{". $index . "}" . "{" . $previousHash . "}" . "{" . $timestamp . "}" . "{" . $data . "}" . "{" . $nonce . "}";
        $newhash2 = hash('sha256', $prevhashandnewtrans);

        if($debug == true){
            echo '</br><textarea name="article1" cols="60" rows="5" maxlength="" wrap="hard">' . $prevhashandnewtrans . "\n\n" . $newhash2 . "\n\n</textarea>";
        }
        return $newhash2;
        //return hash('sha256', $index + $previousHash + $timestamp + $data);
    }

    /**
     * ブロックからハッシュを生成
     */
    function calculateHashForBlock(Block $block): string
    {
        return $this->calculateHash(
            $block->getIndex(),
            $block->getPreviousHash(),
            $block->getTimestamp(),
            $block->getData(),
            $block->getNonce()
        );
    }

    /**
     * マイニングを定義
     */
    //村山(ナンス値を0から)
    function mining($index, $previousHash, $timestamp, $data)
    {
        global $debug;

        $newNonce = "0";
        $newHash = "11";
        if($debug == true){echo('</br><textarea name="article" cols="60" rows="20" maxlength="30" wrap="hard">');}

        while(1)
        {
            $subHash = substr($newHash, -3);
            if($debug == true){echo $subHash . "\n";}
            if($subHash === "000")
            {
                break;
            }
            else
            {
                //$newNonce = $newNonce + 1;
                $newNonce = rand(); //ランダム化
                $hensu = "{". $index . "}" . "{" . $previousHash . "}" . "{" . $timestamp . "}" . "{" . $data . "}" . "{" . $newNonce . "}";
                $newHash = hash('sha256', $hensu);
                $newNonce = intval($newNonce);
                if($debug == true){echo $hensu . "\n\n";}
            }
        }
        if($debug == true){echo "success</textarea>";}
        return $newNonce;
    }
    
    //Proof(ナンス値...前のハッシュから始めている...String型→Int型バグる)
    function mining_momosaki($hash)
    { 
        $newProof = "0";
        $newHash = "11111";
        while(1)
        {
            $subHash = substr($newHash, -8);
            if($subHash == "00000000")
            {
                break;
            }
            else{
                $hensu = $hash + $newProof;
                $newHash = hash('sha256', $hash + $newProof);
                $newProof = intval($newProof);
                $newProof = $newProof + 1;
                $newProof = (string)$newProof;
            }
        }
        return $newProof;
    }



    /**
     * ブロックチェーンの最後のブロックを取得
     */
    function getLatestBlock(): Block
    {
        return $this->blockchain[count($this->blockchain) - 1];
    }

    /**
     * 次のブロックを生成
     */
    function generateNextBlock($blockData, $newTime = NULL): Block
    {
        $previousBlock = $this->getLatestBlock();
        $nextIndex = $previousBlock->getIndex() + 1;
        $nextTimestamp = (new DateTime())->getTimestamp() / 1000;
        $nextTimestamp = microtime(true);
        if(isset($newTime)){$nextTimestamp = $newTime;}
        $nextNonce = $this->mining($nextIndex, $previousBlock->getHash(), $nextTimestamp, $blockData);
        $nextHash = $this->calculateHash($nextIndex, $previousBlock->getHash(), $nextTimestamp, $blockData, $nextNonce);

        return new Block($nextIndex, $previousBlock->getHash(), $nextTimestamp, $blockData, $nextNonce, $nextHash);
    }

    /**
     * 新しく作成するブロックの安全性チェック
     */
    function isValidNewBlock($newBlock, $previousBlock)
    {
        if ($previousBlock->getIndex() + 1 !== $newBlock->getIndex()) {
            echo "Warning: Invalid index.\n";
            return false;
        } else if ($previousBlock->getHash() !== $newBlock->getPreviousHash()) {
            echo "Warning: Invalid previous hash.\n";
            return false;
        } else if ($this->calculateHashForBlock($newBlock) !== $newBlock->getHash()) {
            echo 'Warning: Invalid hash: ' . $this->calculateHashForBlock($newBlock) . ' ' . $newBlock->getHash() . "\n";
            return false;
        }
        return true;
    }

    /**
     * 最長チェーンを選択
     */
    function replaceChain(Blockchain $newBlockchain)
    {
        $newBlocks = $newBlockchain->getBlockchain();

        if ($this->isValidChain($newBlockchain) && count($newBlocks) > count($this->blockchain)) {
            echo "Received blockchain is valid. Replacing current blockchain with received blockchain\n";
            $this->blockchain = $newBlocks;
        } else {
            echo "Received blockchain invalid\n";
        }
    }

    /**
     * ブロックチェーンの妥当性チェック
     */
    function isValidChain(Blockchain $blockchain): bool
    {
        $blockchainToValidate = $blockchain->getBlockchain();

        // ジェネシスブロックが一致するかをチェック
        if ($blockchainToValidate[0]->getHash() !== $this->getGenesisBlock()->getHash()) {
            return false;
        }
        // 全てのブロックの妥当性をチェック
        foreach ($blockchainToValidate as $index => $blockToVaridate) {
            if (0 === $index || $this->isValidNewBlock($blockToVaridate, $blockchainToValidate[$index - 1])) {
                continue;
            }
            return false;
        }
        return true;
    }

    /**
     * ブロックチェーンにブロックを追加
     */
    function addBlock(Block $newBlock)
    {
        if ($this->isValidNewBlock($newBlock, $this->getLatestBlock())) {
            $this->blockchain[] = $newBlock;
        }
    }

    function broadcast(Blockchain $newBlockchain, string $name)
    {
        echo "$name broadcast.\n";
        $size = count($newBlockchain->getBlockchain());
        $this->replaceChain($newBlockchain);
        $latestBlockHash = $this->getLatestBlock()->getHash();
        echo "$name new blockchain. SIZE: $size, LATEST_BLOCK: $latestBlockHash\n";
    }
    
    function prevTime(){
        $latestBlockTime = $this->getLatestBlock()->getTimestamp();
        return $latestBlockTime;
        }
    
}