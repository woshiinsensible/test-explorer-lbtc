<?php

namespace App\Http\Controllers\Block;
use Illuminate\Queue\Connectors\RedisConnector;
use Illuminate\Support\Facades\Redis;
use Laravel\Lumen\Routing\Controller as BaseController;
use App\Model\Block;
use App\Model\Transaction;
use App\Model\Pubkey;
use App\Model\Autosend;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Sunra\PhpSimple\HtmlDomParser;
use App\Http\Commons;

class BlockController extends BaseController
{

    //****api v3 ***//

    //通过地址得到余额
    public function GetAddressBalance(Request $request)
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");


        if (empty($request->input('param'))){
            return json_encode(array('error'=>1,'msg'=>'Input Addr Empty!'));
        }

        $param = trim($request->input('param'));
        $addrLen = strlen($param);
        if($addrLen < 26 || $addrLen > 34){
            return json_encode(array('error'=>1,'msg'=>'Addr Format Error!'));
        }


        $curlOperate = new Commons\CurlOperate();

        $curl = $curlOperate->GetAddressBalance($param);

        $arrRes = json_decode($curl,1);

        //判断请求是否发生错误
        if(array_key_exists('msg',$arrRes)){
            return json_encode(array('error'=>1,'msg'=>$arrRes['msg']));
        }


        $arrRes['addr'] = $param;
        $response = json_encode($arrRes);
        return $response;
    }



    //通过快高获取相应信息
    public function GetBlockInfo(Request $request)
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        //分页
        $page = $request->input('page',1);
        $count = 20;

        //页码小于1，重置1
        if($page < 1){
            $page = 1;
        }

        if($page > 5){
            $page = 5;
        }

        $num = 100;

        if (empty($request->input('param'))){
            return json_encode(array('error'=>1,'msg'=>'Input Addr Empty!'));
        }

        $param = trim($request->input('param'));
        if(!is_numeric($param)){
            return json_encode(array('error'=>1,'msg'=>'Block Not Num!'));
        }

        if($param < 1){
            return json_encode(array('error'=>1,'msg'=>'Block Too Small!'));
        }


        //在redis里查找，没有在请求
        $redisParam = $param.'-'.$page;
        $redis = new Commons\RedisOperate();
        $redisExist = $redis->RedisExist($redisParam);


        //判断redis里是否存在

        if($redisExist){
            return $redis->RedisGet($redisParam);
        }else{
            $param = intval($param);

            $curlOperate = new Commons\CurlOperate();

            $curl = $curlOperate->GetBlockHash($param);

            $resBlockHash = json_decode($curl,1);

            //判断请求是否发生错误
            if(array_key_exists('msg',$resBlockHash)){
                return json_encode(array('error'=>1,'msg'=>$resBlockHash['msg']));
            }

            $resHash = $resBlockHash["result"];


            //最终数据
            $zRes = [];


            //先通过block哈希获取block数据信息

            $blockInfo = $curlOperate->GetBlock($resHash);

            $blockInfoRes = json_decode($blockInfo,1);

            //判断请求是否发生错误
            if(array_key_exists('msg',$blockInfoRes)){
                return json_encode(array('error'=>1,'msg'=>$blockInfoRes['msg']));
            }


            $txArray = $blockInfoRes['result']['tx'];

            //只返回100条交易hash
            $txArray = array_slice($txArray,0,$num);

            $pageCount = ceil(count($txArray) / $count);
            $pageCount = $pageCount < 1 ? 1 : $pageCount;


            //如果页码过大，返回
            if ($page > $pageCount) {
                return json_encode(array('error' => 1, 'msg' => 'Page Too More!'));
            }

            $offset = $count * ($page - 1);

            //截取数组
            $txArray = array_slice($txArray,$offset,$count);

            $txResArray = array();
            foreach ($txArray as $tx){
                //发送请求获取交易详情

                $txInfo = $curlOperate->GetTransactionNew($tx);

                $txInfoArray = json_decode($txInfo,1);


                //判断请求是否发生错误
                if(array_key_exists('msg',$txInfoArray)){
                    return json_encode(array('error'=>1,'msg'=>$txInfoArray['msg']));
                }


                $txVin = $txInfoArray['result']['vin'];


                foreach ($txVin as $txkey => $txval){
                    if (!array_key_exists('coinbase',$txval)){
                        $vinHash = $txval['txid'];

                        //获取vout编号
                        $voutN = $txval['vout'];

                        $txVout = $curlOperate->GetTransactionNew($vinHash);

                        $txInfoArray2 = json_decode($txVout,1);

                        //判断请求是否发生错误
                        if(array_key_exists('msg',$txInfoArray2)){
                            return json_encode(array('error'=>1,'msg'=>$txInfoArray2['msg']));
                        }

                        $txVoutArray = $txInfoArray2['result']['vout'];
                        foreach ($txVoutArray as $txOutVal) {
                            if(array_key_exists("addresses", $txOutVal["scriptPubKey"])){
                                //判断编号等于voutN在进行下步操作
                                if($txOutVal['n'] === $voutN){
                                    $value = $txOutVal['value'];
                                    $addr = $txOutVal["scriptPubKey"]["addresses"][0];
                                    $txInfoArray['result']['vin'][$txkey]['value'] = $value;
                                    $txInfoArray['result']['vin'][$txkey]['addr'] = $addr;
                                }
                            }

                        }

                    }
                }
                array_push($txResArray,$txInfoArray);
            }

            array_push($zRes,$blockInfoRes,$txResArray);

            $zRes = json_encode($zRes);
            $redis->RedisSet($redisParam,$zRes);
            return $zRes;
        }

    }



    //通过块hash得到相关信息
    public function GetBlockInfoByHash(Request $request)
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        //分页
        $page = $request->input('page',1);
        $count = 20;

        //页码小于1，重置1
        if($page < 1){
            $page = 1;
        }

        if($page > 5){
            $page = 5;
        }

        $num = 100;


        if (empty($request->input('param'))){
            return json_encode(array('error'=>1,'msg'=>'Input BlockHash Empty!'));
        }

        //判断块hash是否为64位
        $param = trim($request->input('param'));
        $len = strlen($param);
        if($len != 64){
            return json_encode(array('error'=>1,'msg'=>'Block Hash Error!'));
        }


        //在redis里查找，没有在请求
        $redisParam = $param.'-'.$page;
        $redis = new Commons\RedisOperate();
        $redisExist = $redis->RedisExist($redisParam);
        $zRes = array();

        if($redisExist){
            return $redis->RedisGet($redisParam);
        }else{
            //通过block哈希获取block数据信息
            $curlOperate = new Commons\CurlOperate();

            $blockInfo = $curlOperate->GetBlock($param);

            $blockInfoRes = json_decode($blockInfo,1);

            //判断请求是否发生错误
            if(array_key_exists('msg',$blockInfoRes)){
                return json_encode(array('error'=>1,'msg'=>$blockInfoRes['msg']));
            }

            $txArray = $blockInfoRes['result']['tx'];

            //只返回100条交易hash
            $txArray = array_slice($txArray,0,$num);


            $pageCount = ceil(count($txArray) / $count);
            $pageCount = $pageCount < 1 ? 1 : $pageCount;


            //如果页码过大，返回
            if ($page > $pageCount) {
                return json_encode(array('error' => 1, 'msg' => 'Page Too More'));
            }

            $offset = $count * ($page - 1);

            //截取数组
            $txArray = array_slice($txArray,$offset,$count);


            $txResArray = array();
            foreach ($txArray as $tx){
                //发送请求获取交易详情

                $txInfo = $curlOperate->GetTransactionNew($tx);

                $txInfoArray = json_decode($txInfo,1);


                //判断请求是否发生错误
                if(array_key_exists('msg',$txInfoArray)){
                    return json_encode(array('error'=>1,'msg'=>$txInfoArray['msg']));
                }


                $txVin = $txInfoArray['result']['vin'];

                foreach ($txVin as $txkey => $txval){
                    if (!array_key_exists('coinbase',$txval)){
                        $vinHash = $txval['txid'];

                        //获取vout编号
                        $voutN = $txval['vout'];

                        $txVout = $curlOperate->GetTransactionNew($vinHash);

                        $txInfoArray2 = json_decode($txVout,1);

                        //判断请求是否发生错误
                        if(array_key_exists('msg',$txVout)){
                            return json_encode(array('error'=>1,'msg'=>$txVout['msg']));
                        }

                        $txVoutArray = $txInfoArray2['result']['vout'];
                        foreach ($txVoutArray as $txOutVal) {
                            if(array_key_exists("addresses", $txOutVal["scriptPubKey"])){
                                //判断编号等于voutN在进行下步操作
                                if($txOutVal['n'] === $voutN){
                                    $value = $txOutVal['value'];
                                    $addr = $txOutVal["scriptPubKey"]["addresses"][0];
                                    $txInfoArray['result']['vin'][$txkey]['value'] = $value;
                                    $txInfoArray['result']['vin'][$txkey]['addr'] = $addr;
                                }
                            }

                        }

                    }
                }
                array_push($txResArray,$txInfoArray);
            }

            array_push($zRes,$blockInfoRes,$txResArray);

            $zRes = json_encode($zRes);
            $redis->RedisSet($redisParam,$zRes);
            return $zRes;
        }
    }



    //通过交易tx获取相应信息
    public function GetTxInfo(Request $request)
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        if (empty($request->input('param'))) {
            return json_encode(array('error' => 1, 'msg' => 'Input TX Empty!'));
        }

        $param = trim($request->input('param'));
        $len = strlen($param);
        if ($len != 64) {
            return json_encode(array('error' => 1, 'msg' => 'TX Format Error'));
        }


        //在redis里查找，没有在请求
        $redisParam = $param;
        $redis = new Commons\RedisOperate();
        $redisExist = $redis->RedisExist($redisParam);


        if($redisExist){
            return $redis->RedisGet($redisParam);
        }else{

            //发送请求获取交易详情
            $curlOperate = new Commons\CurlOperate();

            $txInfo = $curlOperate->GetTransactionNew($redisParam);

            $txInfoArray = json_decode($txInfo,1);


            //判断请求是否发生错误
            if(array_key_exists('msg',$txInfoArray)){
                return json_encode(array('error'=>1,'msg'=>$txInfoArray['msg']));
            }


            //判断confirmations是否存在
            $txResult = $txInfoArray['result'];
            if(array_key_exists('confirmations',$txResult)){
                $confirmations = $txResult['confirmations'];
                //判断确认数，分别进行处理
                $flagSum = 50;
                $expire = 30;

                //如果大于50确认，正常设置；
                if($confirmations >= $flagSum){
                    //获取所有的vin交易，目的是通过他获取vout包含的地址和金额
                    $txTemp = $txInfoArray['result']['vin'];
                    foreach ($txTemp as $txkey => $txval) {
                        if (!array_key_exists('coinbase', $txval)) {
                            $vinHash = $txval['txid'];
                            //获取vout的编号
                            $voutN = $txval['vout'];

                            $txVout = $curlOperate->GetTransactionNew($vinHash);

                            $txVoutArray = json_decode($txVout, 1);

                            //判断请求是否发生错误
                            if(array_key_exists('msg',$txVoutArray)){
                                return json_encode(array('error'=>1,'msg'=>$txVoutArray['msg']));
                            }

                            $txVoutResArray = $txVoutArray['result']['vout'];

                            foreach ($txVoutResArray as $k=>$txOutVal) {
                                //判断编号等于voutN在进行下步操作
                                if($txOutVal['n'] === $voutN){
                                    $value = $txOutVal['value'];
                                    $addr = $txOutVal["scriptPubKey"]["addresses"][0];
                                    $txInfoArray['result']['vin'][$txkey]['value'] = $value;
                                    $txInfoArray['result']['vin'][$txkey]['addr'] = $addr;
                                }
                            }
                        }

                    }

                    $txInfo = json_encode($txInfoArray);
                    $redis->RedisSet($redisParam,$txInfo);
                    return $txInfo;
                }
                //小于50确认，设置redis有效期30秒
                //获取所有的vin交易，目的是通过他获取vout包含的地址和金额
                $txTemp = $txInfoArray['result']['vin'];
                foreach ($txTemp as $txkey => $txval) {
                    if (!array_key_exists('coinbase', $txval)) {
                        $vinHash = $txval['txid'];
                        //获取vout的编号
                        $voutN = $txval['vout'];

                        $txVout = $curlOperate->GetTransactionNew($vinHash);

                        $txVoutArray = json_decode($txVout, 1);

                        //判断请求是否发生错误
                        if(array_key_exists('msg',$txVoutArray)){
                            return json_encode(array('error'=>1,'msg'=>$txVoutArray['msg']));
                        }

                        $txVoutResArray = $txVoutArray['result']['vout'];

                        foreach ($txVoutResArray as $k=>$txOutVal) {
                            //判断编号等于voutN在进行下步操作
                            if($txOutVal['n'] === $voutN){
                                $value = $txOutVal['value'];
                                $addr = $txOutVal["scriptPubKey"]["addresses"][0];
                                $txInfoArray['result']['vin'][$txkey]['value'] = $value;
                                $txInfoArray['result']['vin'][$txkey]['addr'] = $addr;
                            }
                        }
                    }

                }

                $txInfo = json_encode($txInfoArray);
                $redis->RedisSet($redisParam,$txInfo,$expire);
                return $txInfo;
            }
        }

    }



    //查询(查找hash时，有可能时block哈希，暂时先不查找块hash)()
    public function Search3(Request $request)
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");


        //判断查询输入是否为空
        if (empty($request->input('param'))){
            return json_encode(array('error'=>1, 'msg'  =>'Input Empty!'));
        }


        $param = trim($request->input('param'));


        //如果输入为数字查询快高
        if (is_numeric($param)){
            //分页
            $page = $request->input('page',1);
            $count = 20;

            //页码小于1，重置1
            if($page < 1){
                $page = 1;
            }

            if($page > 5){
                $page = 5;
            }

            $num = 100;

            if($param < 1){
                return json_encode(array('error'=>1,'msg'=>'Block Too Small!'));
            }


            //在redis里查找，没有在请求
            $redisParam = $param.'-'.$page;
            $redis = new Commons\RedisOperate();
            $redisExist = $redis->RedisExist($redisParam);


            //判断redis里是否存在

            if($redisExist){
                return $redis->RedisGet($redisParam);
            }else{
                $param = intval($param);

                $curlOperate = new Commons\CurlOperate();

                $curl = $curlOperate->GetBlockHash($param);

                $resBlockHash = json_decode($curl,1);

                //判断请求是否发生错误
                if(array_key_exists('msg',$resBlockHash)){
                    return json_encode(array('error'=>1,'msg'=>$resBlockHash['msg']));
                }

                $resHash = $resBlockHash["result"];


                //最终数据
                $zRes = [];


                //先通过block哈希获取block数据信息

                $blockInfo = $curlOperate->GetBlock($resHash);

                $blockInfoRes = json_decode($blockInfo,1);

                //判断请求是否发生错误
                if(array_key_exists('msg',$blockInfoRes)){
                    return json_encode(array('error'=>1,'msg'=>$blockInfoRes['msg']));
                }


                $txArray = $blockInfoRes['result']['tx'];

                //只返回100条交易hash
                $txArray = array_slice($txArray,0,$num);

                $pageCount = ceil(count($txArray) / $count);
                $pageCount = $pageCount < 1 ? 1 : $pageCount;


                //如果页码过大，返回
                if ($page > $pageCount) {
                    return json_encode(array('error' => 1, 'msg' => 'Page Too More!'));
                }

                $offset = $count * ($page - 1);

                //截取数组
                $txArray = array_slice($txArray,$offset,$count);

                $txResArray = array();
                foreach ($txArray as $tx){
                    //发送请求获取交易详情

                    $txInfo = $curlOperate->GetTransactionNew($tx);

                    $txInfoArray = json_decode($txInfo,1);


                    //判断请求是否发生错误
                    if(array_key_exists('msg',$txInfoArray)){
                        return json_encode(array('error'=>1,'msg'=>$txInfoArray['msg']));
                    }


                    $txVin = $txInfoArray['result']['vin'];


                    foreach ($txVin as $txkey => $txval){
                        if (!array_key_exists('coinbase',$txval)){
                            $vinHash = $txval['txid'];

                            //获取vout编号
                            $voutN = $txval['vout'];

                            $txVout = $curlOperate->GetTransactionNew($vinHash);

                            $txInfoArray2 = json_decode($txVout,1);

                            //判断请求是否发生错误
                            if(array_key_exists('msg',$txInfoArray2)){
                                return json_encode(array('error'=>1,'msg'=>$txInfoArray2['msg']));
                            }

                            $txVoutArray = $txInfoArray2['result']['vout'];
                            foreach ($txVoutArray as $txOutVal) {
                                if(array_key_exists("addresses", $txOutVal["scriptPubKey"])){
                                    //判断编号等于voutN在进行下步操作
                                    if($txOutVal['n'] === $voutN){
                                        $value = $txOutVal['value'];
                                        $addr = $txOutVal["scriptPubKey"]["addresses"][0];
                                        $txInfoArray['result']['vin'][$txkey]['value'] = $value;
                                        $txInfoArray['result']['vin'][$txkey]['addr'] = $addr;
                                    }
                                }

                            }

                        }
                    }
                    array_push($txResArray,$txInfoArray);
                }

                array_push($zRes,$blockInfoRes,$txResArray);

                $zRes = json_encode($zRes);
                $redis->RedisSet($redisParam,$zRes);
                return $zRes;
            }
        }


        //如果输入为hash,直接查询交易hash，暂时不查块hash
        if (strlen($param) == 64 ) {
            //在redis里查找，没有在请求
            $redisParam = $param;
            $redis = new Commons\RedisOperate();
            $redisExist = $redis->RedisExist($redisParam);


            if ($redisExist) {
                return $redis->RedisGet($redisParam);
            } else {

                //发送请求获取交易详情
                $curlOperate = new Commons\CurlOperate();

                $txInfo = $curlOperate->SearchHash($redisParam);

                $txInfoArray = json_decode($txInfo, 1);


                //判断请求是否发生错误
                if (array_key_exists('msg', $txInfoArray)) {
                    return json_encode(array('error' => 1, 'msg' => $txInfoArray['msg']));
                }

                $backType = $txInfoArray['type'];

                if($backType == 'TXHash'){
                    //判断confirmations是否存在
                    $txResult = $txInfoArray['data'];
                    $txResult = json_decode($txResult,1);
                    if (array_key_exists('confirmations', $txResult)) {
                        $confirmations = $txResult['confirmations'];
                        //判断确认数，分别进行处理
                        $flagSum = 50;
                        $expire = 30;

                        //如果大于50确认，正常设置；
                        if ($confirmations >= $flagSum) {
                            //获取所有的vin交易，目的是通过他获取vout包含的地址和金额
                            $txTemp = $txResult['result']['vin'];
                            foreach ($txTemp as $txkey => $txval) {
                                if (!array_key_exists('coinbase', $txval)) {
                                    $vinHash = $txval['txid'];
                                    //获取vout的编号
                                    $voutN = $txval['vout'];

                                    $txVout = $curlOperate->GetTransactionNew($vinHash);

                                    $txVoutArray = json_decode($txVout, 1);

                                    //判断请求是否发生错误
                                    if (array_key_exists('msg', $txVoutArray)) {
                                        return json_encode(array('error' => 1, 'msg' => $txVoutArray['msg']));
                                    }

                                    $txVoutResArray = $txVoutArray['result']['vout'];

                                    foreach ($txVoutResArray as $k => $txOutVal) {
                                        //判断编号等于voutN在进行下步操作
                                        if ($txOutVal['n'] === $voutN) {
                                            $value = $txOutVal['value'];
                                            $addr = $txOutVal["scriptPubKey"]["addresses"][0];
                                            $txResult['result']['vin'][$txkey]['value'] = $value;
                                            $txResult['result']['vin'][$txkey]['addr'] = $addr;
                                        }
                                    }
                                }

                            }

                            $txInfo = json_encode($txResult);
                            $redis->RedisSet($redisParam, $txInfo);
                            return $txInfo;
                        }
                        //小于50确认，设置redis有效期30秒
                        //获取所有的vin交易，目的是通过他获取vout包含的地址和金额
                        $txTemp = $txResult['result']['vin'];
                        foreach ($txTemp as $txkey => $txval) {
                            if (!array_key_exists('coinbase', $txval)) {
                                $vinHash = $txval['txid'];
                                //获取vout的编号
                                $voutN = $txval['vout'];

                                $txVout = $curlOperate->GetTransactionNew($vinHash);

                                $txVoutArray = json_decode($txVout, 1);

                                //判断请求是否发生错误
                                if (array_key_exists('msg', $txVoutArray)) {
                                    return json_encode(array('error' => 1, 'msg' => $txVoutArray['msg']));
                                }

                                $txVoutResArray = $txVoutArray['result']['vout'];

                                foreach ($txVoutResArray as $k => $txOutVal) {
                                    //判断编号等于voutN在进行下步操作
                                    if ($txOutVal['n'] === $voutN) {
                                        $value = $txOutVal['value'];
                                        $addr = $txOutVal["scriptPubKey"]["addresses"][0];
                                        $txResult['result']['vin'][$txkey]['value'] = $value;
                                        $txResult['result']['vin'][$txkey]['addr'] = $addr;
                                    }
                                }
                            }

                        }

                        $txInfo = json_encode($txResult);
                        $redis->RedisSet($redisParam, $txInfo, $expire);
                        return $txInfo;
                    }
                }
                }


                if($backType == 'BlockHash'){
                //如果交易hash没有查到，就去查块hash
                //分页
                $page = $request->input('page',1);
                $count = 20;

                //页码小于1，重置1
                if($page < 1){
                    $page = 1;
                }

                if($page > 5){
                    $page = 5;
                }

                $num = 100;


                if (empty($request->input('param'))){
                    return json_encode(array('error'=>1,'msg'=>'Input BlockHash Empty!'));
                }

                //判断块hash是否为64位
                $param = trim($request->input('param'));
                $len = strlen($param);
                if($len != 64){
                    return json_encode(array('error'=>1,'msg'=>'Block Hash Error!'));
                }


                $param = json_decode($txInfoArray['data'],1)['result']['hash'];

                //在redis里查找，没有在请求
                $redisParam = $param.'-'.$page;
                $redisExist = $redis->RedisExist($redisParam);
                $zRes = array();

                if($redisExist){
                    return $redis->RedisGet($redisParam);
                }else{
                    //通过block哈希获取block数据信息
                    $blockInfo = $curlOperate->GetBlock($param);

                    $blockInfoRes = json_decode($blockInfo,1);

                    //判断请求是否发生错误
                    if(array_key_exists('msg',$blockInfoRes)){
                        return json_encode(array('error'=>1,'msg'=>$blockInfoRes['msg']));
                    }

                    $txArray = $blockInfoRes['result']['tx'];

                    //只返回100条交易hash
                    $txArray = array_slice($txArray,0,$num);


                    $pageCount = ceil(count($txArray) / $count);
                    $pageCount = $pageCount < 1 ? 1 : $pageCount;


                    //如果页码过大，返回
                    if ($page > $pageCount) {
                        return json_encode(array('error' => 1, 'msg' => 'Page Too More'));
                    }

                    $offset = $count * ($page - 1);

                    //截取数组
                    $txArray = array_slice($txArray,$offset,$count);


                    $txResArray = array();
                    foreach ($txArray as $tx){
                        //发送请求获取交易详情

                        $txInfo = $curlOperate->GetTransactionNew($tx);

                        $txInfoArray = json_decode($txInfo,1);


                        //判断请求是否发生错误
                        if(array_key_exists('msg',$txInfoArray)){
                            return json_encode(array('error'=>1,'msg'=>$txInfoArray['msg']));
                        }


                        $txVin = $txInfoArray['result']['vin'];

                        foreach ($txVin as $txkey => $txval){
                            if (!array_key_exists('coinbase',$txval)){
                                $vinHash = $txval['txid'];

                                //获取vout编号
                                $voutN = $txval['vout'];

                                $txVout = $curlOperate->GetTransactionNew($vinHash);

                                $txInfoArray2 = json_decode($txVout,1);

                                //判断请求是否发生错误
                                if(array_key_exists('msg',$txVout)){
                                    return json_encode(array('error'=>1,'msg'=>$txVout['msg']));
                                }

                                $txVoutArray = $txInfoArray2['result']['vout'];
                                foreach ($txVoutArray as $txOutVal) {
                                    if(array_key_exists("addresses", $txOutVal["scriptPubKey"])){
                                        //判断编号等于voutN在进行下步操作
                                        if($txOutVal['n'] === $voutN){
                                            $value = $txOutVal['value'];
                                            $addr = $txOutVal["scriptPubKey"]["addresses"][0];
                                            $txInfoArray['result']['vin'][$txkey]['value'] = $value;
                                            $txInfoArray['result']['vin'][$txkey]['addr'] = $addr;
                                        }
                                    }

                                }

                            }
                        }
                        array_push($txResArray,$txInfoArray);
                    }

                    array_push($zRes,$blockInfoRes,$txResArray);

                    $zRes = json_encode($zRes);
                    $redis->RedisSet($redisParam,$zRes);
                    return $zRes;
                }
            }

        }


        //如果输入的是地址
        if(strlen($param) >= 26 && strlen($param) <= 34){
            $curlOperate = new Commons\CurlOperate();

            $curl = $curlOperate->GetAddressBalance($param);

            $arrRes = json_decode($curl,1);

            //判断请求是否发生错误
            if(array_key_exists('msg',$arrRes)){
                return json_encode(array('error'=>1,'msg'=>$arrRes['msg']));
            }


            $arrRes['addr'] = $param;
            $response = json_encode($arrRes);
            return $response;
        }



        //先从redis查询用户名
        $redis = new Commons\RedisOperate();
        $lbtcname = 'lbtc-'.$param;
        $address = $redis->RedisGet($lbtcname);



        //如果名字对应的地址存在，查询地址信息
        if($address){
            $curlOperate = new Commons\CurlOperate();
            $curl = $curlOperate->GetAddressBalance($address);

            $arrRes = json_decode($curl,1);

            //判断请求是否发生错误
            if(array_key_exists('msg',$arrRes)){
                return json_encode(array('error'=>1,'msg'=>$arrRes['msg']));
            }


            $arrRes['addr'] = $address;
            $response = json_encode($arrRes);
            return $response;
        }

        return json_encode(array('error'=>1,'msg'=>'Input Error'));
    }



    //查询首页信息
    public function Index3()
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        $redis = new Commons\RedisOperate();
        $indexInfo = $redis->RedisGet('index');
        return $indexInfo;


    }



    //定时添加首页信息 定时任务
    public function SetIndex()
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        $curlOperate = new Commons\CurlOperate();

        $height = $curlOperate->GetBlockCount();

        $heightArray = json_decode($height, 1);

        //判断请求是否发生错误
        if(array_key_exists('msg',$heightArray)){
            return json_encode(array('error'=>1,'msg'=>$heightArray['msg']));
        }


        //最新快高
        $height = intval($heightArray['result']);


        $count = 20;
        //拼接数组保证钱20条
        $heightArray = array();
        for ($i = $height; $i > $height - $count; $i--) {

            //通过快高得到块hash
            $blockHash = $curlOperate->GetBlockHash($i);

            //得到块hash，在得到块具体信息
            $blockHashArray = json_decode($blockHash, 1);

            //判断请求是否发生错误
            if(array_key_exists('msg',$blockHashArray)){
                return json_encode(array('error'=>1,'msg'=>$blockHashArray['msg']));
            }

            $blockHash = $blockHashArray['result'];

            $blockInfo = $curlOperate->GetBlock($blockHash);

            $blockInfoArray = json_decode($blockInfo, 1);

            //判断请求是否发生错误
            if(array_key_exists('msg',$blockInfoArray)){
                return json_encode(array('error'=>1,'msg'=>$blockInfoArray['msg']));
            }

            array_push($heightArray,$blockInfoArray);

        }

        $heightArray = array_filter($heightArray);

        if(count($heightArray) == 20){
            //得到的首页数据放入redis
            $indexStr =  json_encode($heightArray);
            $redis = new Commons\RedisOperate();
            $redis->RedisSet('index',$indexStr);
        }

    }



    //listdelegates 定时任务
    public function listwitnesses()
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        $curlOperate = new Commons\CurlOperate();

        $listDelegates = $curlOperate->ListDelegates();

        $listDelegatesArray = json_decode($listDelegates, 1);

        //判断请求是否发生错误
        if(array_key_exists('msg',$listDelegatesArray)){
            return json_encode(array('error'=>1,'msg'=>$listDelegatesArray['msg']));
        }

        $redis = new Commons\RedisOperate();


        $resListDelegates = $listDelegatesArray['result'];

        foreach ($resListDelegates as $delegatesVal){
            $redis->RedisSet($delegatesVal['address'],$delegatesVal['name']);
            $redis->RedisSet('lbtc-'.$delegatesVal['name'],$delegatesVal['address']);
        }
    }



    //listreceivedvotes 参数用名字
    public function getvotersbywitness(Request $request)
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        if (empty($request->input('param'))){
            return json_encode(array('error'=>1,'msg'=>'Input Empty!'));
        }

        $param = trim($request->input('param'));
        $addrLen = strlen($param);
        if($addrLen < 26 || $addrLen > 34){
            $resParam = $param;
        }else{
            $redis = new Commons\RedisOperate();
            $resParam = $redis->RedisGet($param);
            if(empty($resParam)){
                return json_encode(array('error'=>1,'msg'=>'Input information Error!'));
            }
        }


        $curlOperate = new Commons\CurlOperate();

        $listReceivedVotes = $curlOperate->ListReceivedVotes($resParam);

        $listReceivedVotesArray = json_decode($listReceivedVotes,1);

        //判断请求是否发生错误
        if(array_key_exists('msg',$listReceivedVotesArray)){
            return json_encode(array('error'=>1,'msg'=>$listReceivedVotesArray['msg']));
        }

        return $listReceivedVotes;
    }



    //listvoteddelegates
    public function getvotebyaddress(Request $request)
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        if (empty($request->input('param'))){
            return json_encode(array('error'=>1,'msg'=>'Input Empty!'));
        }

        $param = trim($request->input('param'));
        $addrLen = strlen($param);
        if($addrLen < 26 || $addrLen > 34){
            $redis = new Commons\RedisOperate();
            $resParam = $redis->RedisGet('lbtc-'.$param);
            if(empty($resParam)){
                return json_encode(array('error'=>1,'msg'=>'Input information Error!'));
            }
        }else{
            $resParam = $param;
        }

        $curlOperate = new Commons\CurlOperate();

        $response = $curlOperate->ListVotedDelegates($resParam);

        $responseArray = json_decode($response,1);

        //判断请求是否发生错误
        if(array_key_exists('msg',$responseArray)){
            return json_encode(array('error'=>1,'msg'=>$responseArray['msg']));
        }

        return $response;

    }



    //getdelegatevotes得票数  用名字
    public function getwitnessshare(Request $request)
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        if (empty($request->input('param'))){
            return json_encode(array('error'=>1,'msg'=>'Input Empty!'));
        }

        $param = $request->input('param');

        $curlOperate = new Commons\CurlOperate();

        $response = $curlOperate->GetDelegateVotes($param);

        $responseArray = json_decode($response,1);

        //判断请求是否发生错误
        if(array_key_exists('msg',$responseArray)){
            return json_encode(array('error'=>1,'msg'=>$responseArray['msg']));
        }

        return $response;
    }



    //setredis 定时任务
    public function SetRedis()
    {
        //获取listdelegates
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        $curlOperate = new Commons\CurlOperate();

        $response = $curlOperate->ListDelegates();

        $responseArray = json_decode($response,1);


        //判断请求是否发生错误
        if(array_key_exists('msg',$responseArray)){
            return json_encode(array('error'=>1,'msg'=>$responseArray['msg']));
        }


        $delegatesArray = $responseArray['result'];


        //redis
        $redis = new Commons\RedisOperate();

        foreach ($delegatesArray as $key => $val){


            $response = $curlOperate->GetDelegateVotes("dazhuang");

            $responseArray = json_decode($response,1);


            //判断请求是否发生错误
            if(array_key_exists('msg',$responseArray)){
                return json_encode(array('error'=>1,'msg'=>$responseArray['msg']));
            }

            $delegatesArray[$key]['count'] = $responseArray['result'];
        }


        if($delegatesArray){
            $delegatesJson = json_encode($delegatesArray);
            $redis->RedisSet('nodesort',$delegatesJson);
        }

    }



    //getactive
    public function GetActive()
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        //redis
        $redis = new Commons\RedisOperate();
        $redisExist = $redis->RedisExist('nodesort');

        if($redisExist){
            $nodeSort = $redis->RedisGet('nodesort');
            $nodeSortArray = json_decode($nodeSort,1);
        }


        foreach ($nodeSortArray as $val){
            $result[] = $val;
        }

        array_multisort(array_column($result,'count'),SORT_DESC,$result);

        return json_encode($result);

    }



    //获取所有代理人的地址和名字
    public function Getlistdelegates()
    {
        //获取listdelegates
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");


        //redis
        $redis = new Commons\RedisOperate();

        $redisExist = $redis->RedisExist('nodesort');

        if($redisExist){
            $redisJson = $redis->RedisGet('nodesort');
            return $redisJson;
        }
        return json_encode(array('error'=>1,'msg'=>'Key NoExist'));
    }



    //test
    public function test(Request $request)
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        $redis = new Commons\RedisOperate();
        $redisGet = $redis->RedisGet('nodesort');
        return $redisGet;
    }

}
