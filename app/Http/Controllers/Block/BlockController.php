<?php

namespace App\Http\Controllers\Block;
use App\Http\Commons\UniversalTools;
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
use App\Http\Commons\CurlOperate;
use App\Http\Commons\RedisOperate;
use Jenssegers\Agent\Agent;

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


        $curlOperate = new CurlOperate();

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
        $redis = new RedisOperate();
        $redisExist = $redis->RedisExist($redisParam);


        //判断redis里是否存在

        if($redisExist){
            return $redis->RedisGet($redisParam);
        }else{
            $param = intval($param);

            $curlOperate = new CurlOperate();

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
        $redis = new RedisOperate();
        $redisExist = $redis->RedisExist($redisParam);
        $zRes = array();

        if($redisExist){
            return $redis->RedisGet($redisParam);
        }else{
            //通过block哈希获取block数据信息
            $curlOperate = new CurlOperate();

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
        $redis = new RedisOperate();
        $redisExist = $redis->RedisExist($redisParam);


        if($redisExist){
            return $redis->RedisGet($redisParam);
        }else{

            //发送请求获取交易详情
            $curlOperate = new CurlOperate();

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
            $redis = new RedisOperate();
            $redisExist = $redis->RedisExist($redisParam);


            //判断redis里是否存在

            if($redisExist){
                return $redis->RedisGet($redisParam);
            }else{
                $param = intval($param);

                $curlOperate = new CurlOperate();

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
            $redis = new RedisOperate();
            $redisExist = $redis->RedisExist($redisParam);


            if ($redisExist) {
                return $redis->RedisGet($redisParam);
            } else {

                //发送请求获取交易详情
                $curlOperate = new CurlOperate();

                $txInfo = $curlOperate->SearchHash($redisParam);

                $txInfoArray = json_decode($txInfo, 1);


                //判断请求是否发生错误
                if (array_key_exists('msg', $txInfoArray)) {
                    return json_encode(array('error' => 1, 'msg' => $txInfoArray['msg']));
                }

                $backType = $txInfoArray['type'];


                if($backType == 'TXHash'){
                    //判断confirmations是否存在
                    $txResultTemp = $txInfoArray['data'];
                    $txResultArray = json_decode($txResultTemp,1);
                    $txResult = $txResultArray['result'];

                    if (array_key_exists('confirmations', $txResult)) {
                        $confirmations = $txResult['confirmations'];
                        //判断确认数，分别进行处理
                        $flagSum = 50;
                        $expire = 30;

                        //如果大于50确认，正常设置；
                        if ($confirmations >= $flagSum) {
                            //获取所有的vin交易，目的是通过他获取vout包含的地址和金额
                            $txTemp = $txResultArray['result']['vin'];
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
                                            $txResultArray['result']['vin'][$txkey]['value'] = $value;
                                            $txResultArray['result']['vin'][$txkey]['addr'] = $addr;
                                        }
                                    }
                                }

                            }

                            $txInfo = json_encode($txResultArray);
                            $redis->RedisSet($redisParam, $txInfo);
                            return $txInfo;
                        }
                        //小于50确认，设置redis有效期30秒
                        //获取所有的vin交易，目的是通过他获取vout包含的地址和金额
                        $txTemp = $txResultArray['result']['vin'];
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
                                        $txResultArray['result']['vin'][$txkey]['value'] = $value;
                                        $txResultArray['result']['vin'][$txkey]['addr'] = $addr;
                                    }
                                }
                            }

                        }

                        $txInfo = json_encode($txResultArray);
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
                                if(array_key_exists('msg',$txInfoArray2)){
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
            $curlOperate = new CurlOperate();

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
        $redis = new RedisOperate();
        $lbtcname = 'lbtc-'.$param;
        $address = $redis->RedisGet($lbtcname);



        //如果名字对应的地址存在，查询地址信息
        if($address){
            $curlOperate = new CurlOperate();
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

        $redis = new RedisOperate();
        $indexInfo = $redis->RedisGet('index');
        return $indexInfo;


    }



    //定时添加首页信息 定时任务
    public function SetIndex()
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        $curlOperate = new CurlOperate();

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
            $redis = new RedisOperate();
            $redis->RedisSet('index',$indexStr);
        }

    }



    //listdelegates 定时任务
    public function listwitnesses()
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        $curlOperate = new CurlOperate();

        $listDelegates = $curlOperate->ListDelegates();

        $listDelegatesArray = json_decode($listDelegates, 1);

        //判断请求是否发生错误
        if(array_key_exists('msg',$listDelegatesArray)){
            return json_encode(array('error'=>1,'msg'=>$listDelegatesArray['msg']));
        }

        $redis = new RedisOperate();


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
            $redis = new RedisOperate();
            $resParam = $redis->RedisGet($param);
            if(empty($resParam)){
                return json_encode(array('error'=>1,'msg'=>'Input information Error!'));
            }
        }


        $curlOperate = new CurlOperate();

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
        //特殊处理输入的不是地址，而是比较变态的名字
        if($addrLen < 26 || $addrLen > 34){
            $redis = new RedisOperate();
            $resParam = $redis->RedisGet('lbtc-'.$param);
            if(empty($resParam)){
                return json_encode(array('error'=>1,'msg'=>'Input information Error!'));
            }
        }else{
            $resParam = $param;
        }

        $curlOperate = new CurlOperate();

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

        $curlOperate = new CurlOperate();

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

        $curlOperate = new CurlOperate();

        $response = $curlOperate->ListDelegates();

        $responseArray = json_decode($response,1);

        //判断请求是否发生错误
        if(array_key_exists('msg',$responseArray)){
            return json_encode(array('error'=>1,'msg'=>$responseArray['msg']));
        }


        $delegatesArray = $responseArray['result'];


        //redis
        $redis = new RedisOperate();

        foreach ($delegatesArray as $key => $val){

            $response = $curlOperate->GetDelegateVotes($val['name']);

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
        $redis = new RedisOperate();
        $redisExist = $redis->RedisExist('nodesort');

        if($redisExist){
            $nodeSort = $redis->RedisGet('nodesort');
            $nodeSortArray = json_decode($nodeSort,1);

            foreach ($nodeSortArray as $val){
                $result[] = $val;
            }

            array_multisort(array_column($result,'count'),SORT_DESC,$result);

            return json_encode($result);
        }

    }



    //获取所有代理人的地址和名字
    public function Getlistdelegates()
    {
        //获取listdelegates
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");


        //redis
        $redis = new RedisOperate();

        $redisExist = $redis->RedisExist('nodesort');

        if($redisExist){
            $redisJson = $redis->RedisGet('nodesort');
            return $redisJson;
        }
        return json_encode(array('error'=>1,'msg'=>'Key NoExist'));
    }


    //setnodestatus
    public function SetNodeStatus()
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        //重新启动后，先删除redis中的nodedelegates,nodeinfo

        //最终获取coinbase的产生地址
        $curlOperate = new CurlOperate();
        $redis = new RedisOperate();

//        $response = $curlOperate->GetBlockCount();
//
//        $blockCount = json_decode($response,1);
//
//        //判断请求是否发生错误
//        if(array_key_exists('msg',$blockCount)){
//            return json_encode(array('error'=>1,'msg'=>$blockCount['msg']));
//        }

        //块高
//        $blockInt = $blockCount['result'];
//        $blockInt = 2330040;

        $blockInt = $redis->RedisGet('blockcount2');

        $blockInt = intval($blockInt);

        $blockHash = $curlOperate->GetBlockHash($blockInt);

        $blockHashArray = json_decode($blockHash, 1);


        //判断请求是否发生错误
        if (array_key_exists('msg', $blockHashArray)) {
            return json_encode(array('error' => 1, 'msg' => $blockHashArray['msg']));
        }

        $blockInt = $blockInt + 1;

        $redis->RedisSet('blockcount2', $blockInt);

        //块hash
        $blcokHash = $blockHashArray['result'];

        //块里具体信息
        $blockInfo = $curlOperate->GetBlock($blcokHash);

        $blockInfoArray = json_decode($blockInfo, 1);

        //判断请求是否发生错误
        if (array_key_exists('msg', $blockInfoArray)) {
            return json_encode(array('error' => 1, 'msg' => $blockInfoArray['msg']));
        }

        $blockInfo = $blockInfoArray['result'];

        $tx = $blockInfo['tx'][0];

        //获取最终信息
        $txs = $curlOperate->GetTransactionNew($tx);

        $txArray = json_decode($txs, 1);

        //判断请求是否发生错误
        if (array_key_exists('msg', $txArray)) {
            return json_encode(array('error' => 1, 'msg' => $txArray['msg']));
        }

        //测试用0，上线用1
        $nodeDelegates = $txArray['result']['vout'][1];

        $coinbaseAddr = $txArray['result']['vout'][0]["scriptPubKey"]["addresses"][0];

        $redis->RedisSet('nodenow', $coinbaseAddr);


        if (array_key_exists('type', $nodeDelegates) && $nodeDelegates['type'] == 'CoinbaseDelegateInfo') {
            $nodeDelegatesArray = $nodeDelegates['delegates'];

            //判断数据中是否存在数据
            $dbExist = $res1 = DB::table('nodeinfosave')
                ->first(['s_id']);


            if($dbExist){
                //先跟新排序
                DB::table('nodeinfosave')
                    ->update(['s_sort' => 0]);

                //更新没有没有挖矿节点状态
                $nodeError = $redis->RedisGet('nodedelegetsnew');
                $nodeError = json_decode($nodeError,1);

                if($nodeError){
                    foreach ($nodeError as $val){
                        $res1 = DB::table('nodeinfosave')
                            ->select('s_sum')
                            ->where('s_addr', $val)
                            ->get();

                        if ($res1){
                            DB::table('nodeinfosave')
                                ->where('s_addr', $val)
                                ->update(['s_sum' => $res1[0]->s_sum + 1,'s_status' => '-1']);
                        }else{
                            DB::table('nodeinfosave')
                                ->insert(['s_addr' => $val,'s_sort' => '0','s_count' =>'0','s_sum'=>'1','s_status' => '-1']);
                        }
                    }
                }


                //插入或更新数据库
                $coinbaseKey = array_keys($nodeDelegatesArray,$coinbaseAddr)[0];

                $res = DB::table('nodeinfosave')
                    ->select('s_id', 's_count', 's_sum')
                    ->where('s_addr', $coinbaseAddr)
                    ->get();

                if ($res) {
                    $res = $res[0];
                    $count = $res->s_count;
                    $sum = $res->s_sum;
                    DB::table('nodeinfosave')
                        ->where('s_addr', $coinbaseAddr)
                        ->update(['s_count' => $count + 1, 's_sum' => $sum + 1, 's_status' => '1', 's_sort' => 1]);
                }else{
                    DB::table('nodeinfosave')
                        ->insert([
                            's_addr' => $coinbaseAddr,
                            's_sort' => 1,
                            's_count' => 1,
                            's_sum' => 1,
                            's_status' => 1
                        ]);
                }

                unset($nodeDelegatesArray[$coinbaseKey]);

                //删除nodedelegetsnew，重新组合
                $redis->RedisDel('nodedelegetsnew');
                $tempArray = array();

                foreach ($nodeDelegatesArray as $val){
                    $tempArray[$val] = $val;
                }
                $redis->RedisSet('nodedelegetsnew',json_encode($tempArray));

                $flag1 = 1;
                foreach ($nodeDelegatesArray as $nAddr) {
                    $flag1 ++;
                    $res2 = DB::table('nodeinfosave')
                        ->select('s_id')
                        ->where('s_addr', $nAddr)
                        ->get();

                    if ($res2) {
                        DB::table('nodeinfosave')
                            ->where('s_id', $res2[0]->s_id)
                            ->update(['s_sort' => $flag1]);
                    } else {
                        DB::table('nodeinfosave')
                            ->insert([
                                's_addr' => $nAddr,
                                's_sort' => $flag1,
                                's_count' => 0,
                                's_sum' => 0,
                                's_status' => -1
                            ]);
                    }
                }

            }else {
                //初始化新数据
                $nodeDelegatesArray = $nodeDelegates['delegates'];

                $tArray = array();

                array_push($tArray,array(
                    's_addr' => $coinbaseAddr,
                    's_sort' => 1,
                    's_count' => '1',
                    's_sum' => '1',
                    's_status' => '1'
                ));

                $dkey = array_keys($nodeDelegatesArray,$coinbaseAddr)[0];

                unset($nodeDelegatesArray[$dkey]);

                $flag = 1;
                foreach ($nodeDelegatesArray as $val){
                    $flag ++;
                    array_push($tArray,array(
                        's_addr' => $val,
                        's_sort' => $flag,
                        's_count' => '0',
                        's_sum' => '0',
                        's_status' => '-1'
                    ));
                }
                DB::table('nodeinfosave')
                    ->insert($tArray);
            }
        }
        else {
            $nodeInfoSave = DB::table('nodeinfosave')
                ->select('s_count', 's_sum')
                ->where('s_addr', $coinbaseAddr)
                ->get();

            if (empty($nodeInfoSave)) {
                return json_encode(array('error' => 1, 'msg' => 'Find nodeDelegates...'));
            }

            $nodeInfoSave = $nodeInfoSave[0];
            $count = $nodeInfoSave->s_count;
            $sum = $nodeInfoSave->s_sum;

            $resRedis = $redis->RedisGet('nodedelegetsnew');
            $resRedis = json_decode($resRedis,1);

            unset($resRedis[$coinbaseAddr]);
            $redis->RedisSet('nodedelegetsnew',json_encode($resRedis));

            DB::table('nodeinfosave')
                ->where('s_addr', $coinbaseAddr)
                ->update(['s_count' => $count + 1, 's_sum' => $sum + 1, 's_status' => '1']);
        }
    }


    //setblockcount
    public function SetBlockCount()
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        $curlOperate = new CurlOperate();
        $redis = new RedisOperate();

        while(1){
            $response = $curlOperate->GetBlockCount();

            $blockCount = json_decode($response,1);

            //判断请求是否发生错误
            if(array_key_exists('msg',$blockCount)){
                return json_encode(array('error'=>1,'msg'=>$blockCount['msg']));
            }

            //块高
            $blockInt = $blockCount['result'];


            $blockHash = $curlOperate->GetBlockHash($blockInt);

            $blockHashArray = json_decode($blockHash, 1);

            //判断请求是否发生错误
            if (array_key_exists('msg', $blockHashArray)) {
                return json_encode(array('error' => 1, 'msg' => $blockHashArray['msg']));
            }

            //块hash
            $blcokHash = $blockHashArray['result'];

            //块里具体信息
            $blockInfo = $curlOperate->GetBlock($blcokHash);

            $blockInfoArray = json_decode($blockInfo, 1);

            //判断请求是否发生错误
            if (array_key_exists('msg', $blockInfoArray)) {
                return json_encode(array('error' => 1, 'msg' => $blockInfoArray['msg']));
            }

            $blockInfo = $blockInfoArray['result'];

            $tx = $blockInfo['tx'][0];

            //获取最终信息
            $txs = $curlOperate->GetTransactionNew($tx);

            $txArray = json_decode($txs, 1);

            //判断请求是否发生错误
            if (array_key_exists('msg', $txArray)) {
                return json_encode(array('error' => 1, 'msg' => $txArray['msg']));
            }

            //测试用0，上线用1
            $nodeDelegates = $txArray['result']['vout'][1];


            if(array_key_exists('type', $nodeDelegates) && $nodeDelegates['type'] == 'CoinbaseDelegateInfo'){
                $redis->RedisSet('blockcount2',$blockInt);
                $redis->RedisDel('nodedelegetsnew');
                break;
            }
            sleep(3);
        }


//        $curlOperate = new CurlOperate();
//        $redis = new RedisOperate();
//
//        $response = $curlOperate->GetBlockCount();
//        $blockCount = json_decode($response,1);
//
//        //判断请求是否发生错误
//        if(array_key_exists('msg',$blockCount)){
//            return json_encode(array('error'=>1,'msg'=>$blockCount['msg']));
//        }
//
//        //块高
//        $blockInt = $blockCount['result'];
//        $blockCount2 = $redis->RedisGet('blockcount2');
//
//        if($blockInt - $blockCount2 >5){
//            $redis->RedisSet('blockcount2',$blockInt);
//        }

    }


    //GetNodeStatus
    public function GetNodeStatus()
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        $alloAddr = 'my5ioJEbbhMjRzgyQpcnq6fmbfUMQgTqMZ';

        $redis = new RedisOperate();
        $nodeNow = $redis->RedisGet('nodenow');

        if(empty($nodeNow)){
            return json_encode(array('error'=>1,'msg'=>'Find nodeDelegates...'));
        }

        //接口节点信息，name和count
        $voteInfo = file_get_contents('http://127.0.0.1:8333/getactive');
        $voteInfoArray = json_decode($voteInfo,1);


        $temp1 = array();
        foreach ($voteInfoArray as $tKey => $tVal){
            if($nodeNow == $tVal['address']){
                $temp1[$tVal['address']]['address'] =  $tVal['address'];
                $temp1[$tVal['address']]['name'] =  $tVal['name'];
                $temp1[$tVal['address']]['count'] =  $tVal['count'];
                $temp1[$tVal['address']]['now'] =  1;
            }else{
                $temp1[$tVal['address']]['address'] =  $tVal['address'];
                $temp1[$tVal['address']]['name'] =  $tVal['name'];
                $temp1[$tVal['address']]['count'] =  $tVal['count'];
                $temp1[$tVal['address']]['now'] =  0;
            }
        }


        //数据库中查找节点比例
        $nodeDelegatesArray = array();
        $temp2 = array();
        $dataInfo1 = DB::table('nodeinfosave')
            ->select('s_addr', 's_count', 's_sum','s_status')
            ->where('s_sort', '>',0)
            ->orderby('s_sort')
            ->get();

        $dataInfo2 = DB::table('nodeinfosave')
            ->select('s_addr', 's_count', 's_sum','s_status')
            ->where('s_sort',0)
            ->get();

        foreach ($dataInfo1 as $dKey => $dVal){
            if($dVal->s_sum){
                $bili = round($dVal->s_count/$dVal->s_sum,4);
            }else{
                $bili = 0;
            }

            $temp2[$dVal->s_addr]['ratio'] = $bili;
            $temp2[$dVal->s_addr]['status'] = $dVal->s_status;
            array_push($nodeDelegatesArray,$dVal->s_addr);
        }


        foreach ($dataInfo2 as $dKey => $dVal){
            if($dVal->s_sum){
                $bili = round($dVal->s_count/$dVal->s_sum,4);
            }else{
                $bili = 0;
            }

            $temp2[$dVal->s_addr]['ratio'] = $bili;
            $temp2[$dVal->s_addr]['status'] = $dVal->s_status;
            array_push($nodeDelegatesArray,$dVal->s_addr);
        }


        $resArray = array();
        $key = array_keys($nodeDelegatesArray,$alloAddr);
        unset($nodeDelegatesArray[$key[0]]);
        foreach ($nodeDelegatesArray as $nVal){
            array_push($resArray,array_merge($temp1[$nVal],$temp2[$nVal]));
        }

        return json_encode(array('error' => 0,'msg' => $resArray));
    }


    //GetTxByAddr
    public function GetTxByAddr(Request $request)
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        //查询100条tx，每页显示20条
        if (empty($request->input('param'))){
            return json_encode(array('error'=>1,'msg'=>'Input Addr Empty!'));
        }

        $param = trim($request->input('param'));
        $addrLen = strlen($param);
        if($addrLen < 26 || $addrLen > 34){
            return json_encode(array('error'=>1,'msg'=>'Addr Format Error!'));
        }


        $curlOperate = new CurlOperate();
        $redis = new RedisOperate();

        $timeOut = 15;

        $redisParam = 'tx-'.$param;

        $redisTx = $redis->RedisGet($redisParam);

        //先判断redis中是否存在tx-addrs
        if($redisTx){
            $txArray = json_decode($redisTx,1);
            return json_encode(array('error'=>0,'msg'=>$txArray));
        }else{
            $response = $curlOperate->GetTxByAddr($param);

            $txRes = json_decode($response,1);

            //判断请求是否发生错误
            if(array_key_exists('msg',$txRes)){
                return json_encode(array('error'=>1,'msg'=>$txRes['msg']));
            }

            $txArray = $txRes['result'];

            if(empty($txArray)){
                return json_encode(array('error'=>1,'msg'=>'Transaction information can not be found!'));
            }

            $txJson = json_encode($txArray);

            $redis->RedisSet($redisParam,$txJson,$timeOut);

            return json_encode(array('error'=>0,'msg'=>$txArray));
        }
    }


    //查看为花费的utxo
    public function ListUnSpent(Request $request)
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        if (empty($request->input('addr'))){
            return json_encode(array('error'=>1,'msg'=>'Input Addr Empty!'));
        }

        $param = trim($request->input('addr'));
        $addrLen = strlen($param);
        if($addrLen < 26 || $addrLen > 34){
            return json_encode(array('error'=>1,'msg'=>'Addr Format Error!'));
        }

        $checkRes = $this->MVerify($request);
        $checkArray = json_decode($checkRes,1);
        if($checkArray['error']){
            return $checkRes;
        }else {
            $curlOperate = new CurlOperate();

            $response = $curlOperate->ListUnSpent($param);

            return $response;
        }

    }


    //test
    public function test(Request $request)
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        $curlOperate = new CurlOperate();
        $redis = new RedisOperate();

        //注意:重新启动删除redis:del blockcountnew

        $response = $curlOperate->GetBlockCount();

        $blockCount = json_decode($response,1);

        //判断请求是否发生错误
        if(array_key_exists('msg',$blockCount)){
            return json_encode(array('error'=>1,'msg'=>$blockCount['msg']));
        }

        $blockInt = $blockCount['result'];


        $blockRedis = $redis->RedisGet('blockcountnew');

        echo date('Y-m-d H:i:s',time()+3600*8).'=>';
        echo $blockInt.'=>'.$blockRedis;

        //确认块高累计增加
        if($blockRedis){
            if($blockInt > $blockRedis){

                $redis->RedisSet('blockcountnew',$blockInt);
                //进行后续操作
                $blockInt = intval($blockInt);

                $blockHash = $curlOperate->GetBlockHash($blockInt);

                $blockHashArray = json_decode($blockHash, 1);


                //判断请求是否发生错误
                if (array_key_exists('msg', $blockHashArray)) {
                    return json_encode(array('error' => 1, 'msg' => $blockHashArray['msg']));
                }

//                $blockInt = $blockInt + 1;

//                $redis->RedisSet('blockcount2', $blockInt);

                //块hash
                $blcokHash = $blockHashArray['result'];

                //块里具体信息
                $blockInfo = $curlOperate->GetBlock($blcokHash);

                $blockInfoArray = json_decode($blockInfo, 1);

                //判断请求是否发生错误
                if (array_key_exists('msg', $blockInfoArray)) {
                    return json_encode(array('error' => 1, 'msg' => $blockInfoArray['msg']));
                }

                $blockInfo = $blockInfoArray['result'];

                $tx = $blockInfo['tx'][0];

                //获取最终信息
                $txs = $curlOperate->GetTransactionNew($tx);

                $txArray = json_decode($txs, 1);

                //判断请求是否发生错误
                if (array_key_exists('msg', $txArray)) {
                    return json_encode(array('error' => 1, 'msg' => $txArray['msg']));
                }

                //测试用0，上线用1
                $nodeDelegates = $txArray['result']['vout'][1];

                $coinbaseAddr = $txArray['result']['vout'][0]["scriptPubKey"]["addresses"][0];

                if (array_key_exists('type', $nodeDelegates) && $nodeDelegates['type'] == 'CoinbaseDelegateInfo') {
                    $nodeDelegatesArray = $nodeDelegates['delegates'];

                    echo $redis->RedisGet('nodenow').'<br>';
                    echo $coinbaseAddr.'<br>';

                    print_r($nodeDelegatesArray);
//                    file_put_contents("/home/wwwroot/explorer_elbtc/app/Http/Controllers/Block/delegates.log",json_encode($nodeDelegatesArray."=>".date("Y-m-d H:i:s").PHP_EOL),FILE_APPEND);

                    //coinbase地址在代理中的序号
                    $coinbaseKey = array_keys($nodeDelegatesArray,$coinbaseAddr)[0];

                    //redis中是否有数据
                    $res = $redis->RedisHGetAll('1');

                    if($res){
                        //处理锻造节点最后几个出问题状态
                        $tempArray = unserialize($redis->RedisGet('nodetemp'));
                        $addr = $redis->RedisGet('nodenow');
                        $number = $tempArray[$addr];
                        $arrayCount = count($nodeDelegatesArray);

                        if($number < $arrayCount){
                            for($i = $number+1;$i<=$arrayCount;$i++){
                                $iSum = $redis->RedisHGet($i,'sum');
                                $redis->RedisHSet($i,'status',-1);
                                $redis->RedisHSet($i,'sum',$iSum+1);
                            }
                        }


                        $redisArray = array();
                        //把redis状态存入mysql
                        foreach ($nodeDelegatesArray as $nKey => $nVal){
                            $redisKey = $nKey + 1;
                            $redisArray[$nVal] = $redisKey;
                            $resTemp = $redis->RedisHVals($redisKey);
                            $addrTemp = $resTemp[0];


                            //判断节点地址是否存在数据库中
                            $res1 = DB::table('nodeinfosave')
                                ->select('s_id')
                                ->where('s_addr', $addrTemp)
                                ->get();

                            echo 'redis-addr=>'.$resTemp[0].'<br>';
                            echo 'redis-count=>'.$resTemp[3].'<br>';
                            echo 'redis-sum=>'.$resTemp[4].'<br>';

                            if($res1){
                                $res1 = $res1[0];
                                $s_id = $res1->s_id;
                                DB::table('nodeinfosave')
                                    ->where('s_id', $s_id)
                                    ->update(['s_status' => $resTemp[1],'s_count' => $resTemp[3],'s_sum' => $resTemp[4]]);
                            }else{
                                DB::table('nodeinfosave')
                                    ->insert(['s_addr' => $addrTemp,'s_status' => $resTemp[1],'s_count' => $resTemp[3],'s_sum' => $resTemp[4]]);
                            }

                            //删除redis中的内容
                            $redis->RedisDel($redisKey);

                            //重新组合redis
                            $res2 = DB::table('nodeinfosave')
                                ->select('s_addr','s_status','s_count','s_sum')
                                ->where('s_addr', $nVal)
                                ->get();


                            if($res2){
                                $res2 = $res2[0];
                                echo 'mysql-addr=>'.$res2->s_addr.'<br>';
                                echo 'mysql-count=>'.$res2->s_count.'<br>';
                                echo 'mysql-sum=>'.$res2->s_sum.'<br>';
                                if($nKey < $coinbaseKey){
                                    $redis->RedisHSet($redisKey,'addr',$nVal);
                                    $redis->RedisHSet($redisKey,'status',-1);
                                    $redis->RedisHSet($redisKey,'now',0);
                                    $redis->RedisHSet($redisKey,'count',$res2->s_count);
                                    $redis->RedisHSet($redisKey,'sum',$res2->s_sum+1);
                                }elseif ($nKey == $coinbaseKey){
                                    $redis->RedisHSet($redisKey,'addr',$nVal);
                                    $redis->RedisHSet($redisKey,'status',1);
                                    $redis->RedisHSet($redisKey,'now',1);
                                    $redis->RedisHSet($redisKey,'count',$res2->s_count+1);
                                    $redis->RedisHSet($redisKey,'sum',$res2->s_sum+1);
                                }else{
                                    $redis->RedisHSet($redisKey,'addr',$nVal);
                                    $redis->RedisHSet($redisKey,'status',$res2->s_status);
                                    $redis->RedisHSet($redisKey,'now',0);
                                    $redis->RedisHSet($redisKey,'count',$res2->s_count);
                                    $redis->RedisHSet($redisKey,'sum',$res2->s_sum);
                                }
                            }else{
                                if($nKey < $coinbaseKey){
                                    $redis->RedisHSet($redisKey,'addr',$nVal);
                                    $redis->RedisHSet($redisKey,'status',-1);
                                    $redis->RedisHSet($redisKey,'now',0);
                                    $redis->RedisHSet($redisKey,'count',0);
                                    $redis->RedisHSet($redisKey,'sum',1);
                                }elseif ($nKey == $coinbaseKey){
                                    $redis->RedisHSet($redisKey,'addr',$nVal);
                                    $redis->RedisHSet($redisKey,'status',1);
                                    $redis->RedisHSet($redisKey,'now',1);
                                    $redis->RedisHSet($redisKey,'count',1);
                                    $redis->RedisHSet($redisKey,'sum',1);
                                }else{
                                    $redis->RedisHSet($redisKey,'addr',$nVal);
                                    $redis->RedisHSet($redisKey,'status',0);
                                    $redis->RedisHSet($redisKey,'now',0);
                                    $redis->RedisHSet($redisKey,'count',0);
                                    $redis->RedisHSet($redisKey,'sum',0);
                                }
                            }
                        }
                        $redis->RedisSet('nodetemp',serialize($redisArray));
                        $redis->RedisSet('nodenow', $coinbaseAddr);
                    }else{
                        //初始化操作
                        $redisArray = array();

                        //coinbase地址在代理中的序号
                        $coinbaseKey = array_keys($nodeDelegatesArray,$coinbaseAddr)[0];

                        //把redis状态存入mysql
                        foreach ($nodeDelegatesArray as $nKey => $nVal){
                            $redisKey = $nKey + 1;
                            $redisArray[$nVal] = $redisKey;

                            //重新组合redis
                            $res2 = DB::table('nodeinfosave')
                                ->select('s_addr','s_status','s_count','s_sum')
                                ->where('s_addr', $nVal)
                                ->get();

                            if($res2){
                                $res2 = $res2[0];
                                if($nKey < $coinbaseKey){
                                    $redis->RedisHSet($redisKey,'addr',$nVal);
                                    $redis->RedisHSet($redisKey,'status',-1);
                                    $redis->RedisHSet($redisKey,'now',0);
                                    $redis->RedisHSet($redisKey,'count',$res2->s_count);
                                    $redis->RedisHSet($redisKey,'sum',$res2->s_sum+1);
                                }elseif ($nKey == $coinbaseKey){
                                    $redis->RedisHSet($redisKey,'addr',$nVal);
                                    $redis->RedisHSet($redisKey,'status',1);
                                    $redis->RedisHSet($redisKey,'now',1);
                                    $redis->RedisHSet($redisKey,'count',$res2->s_count+1);
                                    $redis->RedisHSet($redisKey,'sum',$res2->s_sum+1);
                                }else{
                                    $redis->RedisHSet($redisKey,'addr',$nVal);
                                    $redis->RedisHSet($redisKey,'status',$res2->s_status);
                                    $redis->RedisHSet($redisKey,'now',0);
                                    $redis->RedisHSet($redisKey,'count',$res2->s_count);
                                    $redis->RedisHSet($redisKey,'sum',$res2->s_sum);
                                }
                            }else{
                                if($nKey < $coinbaseKey){
                                    $redis->RedisHSet($redisKey,'addr',$nVal);
                                    $redis->RedisHSet($redisKey,'status',-1);
                                    $redis->RedisHSet($redisKey,'now',0);
                                    $redis->RedisHSet($redisKey,'count',0);
                                    $redis->RedisHSet($redisKey,'sum',1);
                                }elseif ($nKey == $coinbaseKey){
                                    $redis->RedisHSet($redisKey,'addr',$nVal);
                                    $redis->RedisHSet($redisKey,'status',1);
                                    $redis->RedisHSet($redisKey,'now',1);
                                    $redis->RedisHSet($redisKey,'count',1);
                                    $redis->RedisHSet($redisKey,'sum',1);
                                }else{
                                    $redis->RedisHSet($redisKey,'addr',$nVal);
                                    $redis->RedisHSet($redisKey,'status',0);
                                    $redis->RedisHSet($redisKey,'now',0);
                                    $redis->RedisHSet($redisKey,'count',0);
                                    $redis->RedisHSet($redisKey,'sum',0);
                                }
                            }
                        }
                        $redis->RedisSet('nodetemp',serialize($redisArray));
                        $redis->RedisSet('nodenow', $coinbaseAddr);
                    }

                }
                else {
                    $tempArray = unserialize($redis->RedisGet('nodetemp'));
                    $addr1 = $redis->RedisGet('nodenow');

                    $redis->RedisSet('nodenow',$coinbaseAddr);

                    $first = $tempArray[$addr1];
                    $second = $tempArray[$coinbaseAddr];
                    $flag = $second - $first;

                    echo '<br>';
                    echo 'first=>'.$first.'<br>';
                    echo $addr1.'<br>';
                    var_dump($redis->RedisHVals($first));
                    echo 'second=>'.$second.'<br>';
                    echo $coinbaseAddr.'<br>';
                    var_dump($redis->RedisHVals($second));
                    echo '<br>';


                    //处理redis中内容
                    $count = $redis->RedisHGet($second,'count');
                    $sum = $redis->RedisHGet($second,'sum');

                    echo $count.'<br>';
                    echo $sum.'<br>';

                    if($flag == 1){
                        $redis->RedisHSet($first,'now',0);
                        $redis->RedisHSet($second,'now',1);
                        $redis->RedisHSet($second,'status',1);
                        $redis->RedisHSet($second,'count',$count+1);
                        $redis->RedisHSet($second,'sum',$sum+1);
                    }else{
                        for($i=$first+1;$i<$second;$i++){
                            $iSum = $redis->RedisHGet($i,'sum');
                            $redis->RedisHSet($i,'status',-1);
                            $redis->RedisHSet($i,'sum',$iSum+1);
                        }
                        //单独处理second
                        $redis->RedisHSet($first,'now',0);
                        $redis->RedisHSet($second,'now',1);
                        $redis->RedisHSet($second,'status',1);
                        $redis->RedisHSet($second,'count',$count+1);
                        $redis->RedisHSet($second,'sum',$sum+1);
                    }

                }


            }else{
                return json_encode(array('error'=>1,'msg'=>'block waiting index...'));
            }
        }else{
            $redis->RedisSet('blockcountnew',$blockInt);
            return json_encode(array('error'=>1,'msg'=>'redis adding...'));
        }


    }


    //gettest
    public function gettest()
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        $superNode = 'my5ioJEbbhMjRzgyQpcnq6fmbfUMQgTqMZ';
        $fakeNode  = 'mfWxJ45yp2SFn7UciZyNpvDKrzbhyfKrY8';
        $nodeCount = 10;

        $redis = new RedisOperate();

        //接口节点信息，name和count
        $voteInfo = file_get_contents('http://127.0.0.1:8333/getactive');
        $voteInfoArray = json_decode($voteInfo,1);

        $temp1 = array();
        foreach ($voteInfoArray as $tKey => $tVal){
            $temp1[$tVal['address']]['address'] =  $tVal['address'];
            $temp1[$tVal['address']]['name'] =  $tVal['name'];
            $temp1[$tVal['address']]['count'] =  $tVal['count'];
        }


        //从数据库中查询节点信息
        $mysqlRes = DB::table('nodeinfosave')
            ->select('s_addr','s_count','s_sum')
            ->get();



        foreach ($mysqlRes as $mVal){
            $mAddr = $mVal->s_addr;
            $temp1[$mAddr]['ratio'] = round($mVal->s_count/$mVal->s_sum,4);
        }

        //把supernode添加addr,name,count
        $temp1[$superNode]['address'] = $superNode;
        $temp1[$superNode]['name'] = 'SuperNode';
        $temp1[$superNode]['count'] = 2100000000000000;


        //锻造节点资格信息
        $tempArray = unserialize($redis->RedisGet('nodetemp'));

        $temp2 = array();
        $addrArray = array();
        foreach ($tempArray as $val){
            $res = $redis->RedisHVals($val);
            $addr = $res[0];
            $temp2[$addr]['address'] = $addr;
            $temp2[$addr]['status'] = $res[1];
            $temp2[$addr]['now'] = $res[2];
            $temp2[$addr]['ratio'] = round($res[3]/$res[4],4);
            $lastAddr = $addr;
            array_push($addrArray,$addr);
        }

        //temp2有资格锻造的节点
        //temp1 listlegeate返回数据

        $nodeFlag = 0;
        foreach ($addrArray as $aKey => $aVal){
            $nodeFlag = $nodeFlag + 1;
            if($temp2[$aVal]['now'] === '1' && $temp2[$aVal]['address'] != $lastAddr){
                //当前状态修改为0
                $temp2[$aVal]['now'] = 0;
                //把下一地址修改为1
                $temp2[$addrArray[$aKey+1]]['now'] = 1;
            }
            //处理后备节点
            if($aVal == $fakeNode){
                $fakeNodeNew = $fakeNode.'0';
                $temp2[$fakeNodeNew]['address'] = $fakeNodeNew;
                $temp2[$fakeNodeNew]['status'] = '-1';
                $temp2[$fakeNodeNew]['now'] = '0';
                $temp2[$fakeNodeNew]['name'] = 'Empty Node';
                $temp2[$fakeNodeNew]['count'] = 0;
            }else{
                $temp2[$aVal]['name'] = $temp1[$aVal]['name'];
                $temp2[$aVal]['count'] = $temp1[$aVal]['count'];
            }

            //筛选出没有资格锻造的地址
            unset($temp1[$aVal]);
        }

        //删除后备节点
        unset($temp2[$fakeNode]);

        $tempNum = $nodeCount - $nodeFlag;
        if($tempNum){
            for($i = 1;$i <= $tempNum;$i ++){
                $tempAddr = $fakeNode.$i;
                $temp2[$tempAddr]['address'] = $tempAddr;
                $temp2[$tempAddr]['status'] = '-1';
                $temp2[$tempAddr]['now'] = '0';
                $temp2[$tempAddr]['name'] = 'Empty Node';
                $temp2[$tempAddr]['count'] = 0;
            }
        }


        $resArray = array_merge($temp2,$temp1);

        return json_encode(array('error' => 0,'msg' => array_values($resArray)));

    }




    //testpan
    public function Test1(Request $request)
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        //处理地址
        $addr = $request->input('addr');
        if(empty($addr)){
            return json_encode(array('error' => 1,'msg' => 'Addr is empty!'));
        }

        $addr = trim($addr);
        $addrLen = strlen($addr);
        if($addrLen < 26 || $addrLen > 34){
            return json_encode(array('error'=>1,'msg'=>'Addr Format Error!'));
        }


        //处理token
        $pToken = $request->input('token');
        $pToken = trim($pToken);

        if(empty($pToken)){
            //token不存在
            return json_encode(array('error'=>2,'msg'=>'Token does not exist!'));
        }


        /*添加判断，地址是否已经注册*/
        //数据库中地址是否存在
        $a_id = DB::table('l_addrs')
            ->select('a_id')
            ->where('a_addr',$addr)
            ->get();


        if(empty($a_id)){
            return json_encode(array('error'=>2,'msg'=>'Register first!'));
        }


        $redisOperate = new RedisOperate();
        //地址是否存在redis
        if($redisOperate->RedisExist('token-'.$addr)){
            $redisToekn = $redisOperate->RedisGet('token-'.$addr);
            if($redisToekn != $pToken){
                return json_encode(array('error'=>1,'msg'=>'Token Error!'));
            }
            return json_encode(array('error'=>0,'msg'=>'Token OK!'));
        }else{
            return json_encode(array('error'=>3,'msg'=>'Token Expired!Re-login!'));
        }

    }


    //testget
    public function Test2(Request $request)
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        //处理地址
        $addr = $request->input('addr');
        if(empty($addr)){
            return json_encode(array('error' => 1,'msg' => 'Addr is empty!'));
        }

        $addr = trim($addr);
        $addrLen = strlen($addr);
        if($addrLen < 26 || $addrLen > 34){
            return json_encode(array('error'=>1,'msg'=>'Addr Format Error!'));
        }


        //处理密码
        $pwd = $request->input('pwd');
        if(empty($pwd)){
            return json_encode(array('error' => 1,'msg' => 'Password is empty!'));
        }

        $pwd = trim($pwd);
        $pwdLen = strlen($pwd);
        if($pwdLen < 8 || $pwdLen > 16){
            return json_encode(array('error' => 1,'msg' => 'Password between 8 and 16!'));
        }


        //数据库中地址是否存在
        $a_id = DB::table('l_addrs')
            ->select('a_id')
            ->where('a_addr',$addr)
            ->get();

        if(empty($a_id)){
            return json_encode(array('error'=>2,'msg'=>'Register first!'));
        }


        //设置相关参数
        $expire = '';
        $salt = 'insensible';
        $param = uniqid().time();
        $token = crypt($param,$salt);


        //密码是否正确
        $a_pwd = DB::table('l_addrs')
            ->select('a_pwd')
            ->where('a_addr',$addr)
            ->get();
        $a_pwd = $a_pwd[0]->a_pwd;

        $pwd = md5($salt.'-'.$pwd);

        if($a_pwd != $pwd){
            return json_encode(array('error'=>1,'msg'=>'Password Error!'));
        }


        //在redis中设置token
        $redisOperate = new RedisOperate();

        if($redisOperate->RedisExist('token-'.$addr)){
            return json_encode(array('error'=>0,'msg'=>'Token has exist!'));
        }else{
            $redisOperate->RedisSet('token-'.$addr,$token,$expire);
            return json_encode(array('error'=>0,'msg'=>'Token Generate OK!','data' =>array('token'=>$token)));
        }

    }


    //testreg
    public function Test3(Request $request)
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        //处理地址
        $addr = $request->input('addr');
        if(empty($addr)){
            return json_encode(array('error' => 1,'msg' => 'Addr is empty!'));
        }

        $addr = trim($addr);
        $addrLen = strlen($addr);
        if($addrLen < 26 || $addrLen > 34){
            return json_encode(array('error'=>1,'msg'=>'Addr Format Error!'));
        }


        //处理密码
        $pwd = $request->input('pwd');
        if(empty($pwd)){
            return json_encode(array('error' => 1,'msg' => 'Password is empty!'));
        }

        $pwd = trim($pwd);
        $pwdLen = strlen($pwd);
        if($pwdLen < 8 || $pwdLen > 16){
            return json_encode(array('error' => 1,'msg' => 'Password between 8 and 16!'));
        }

        //处理密码2
        $pwd2 = $request->input('repwd');
        if(empty($pwd2)){
            return json_encode(array('error' => 1,'msg' => 'Second password is empty!'));
        }

        $pwd2 = trim($pwd2);
        $pwdLen2 = strlen($pwd2);
        if($pwdLen2 < 8 || $pwdLen2 > 16){
            return json_encode(array('error' => 1,'msg' => 'Password between 8 and 16!'));
        }

        if($pwd != $pwd2){
            return json_encode(array('error' => 1,'msg' => 'Inconsistent password entered twice!'));
        }


        //数据库中地址是否存在
        $a_id = DB::table('l_addrs')
            ->select('a_id')
            ->where('a_addr',$addr)
            ->get();


        if($a_id){
            return json_encode(array('error'=> 4,'msg'=>'Address is already registered!'));
        }

        //设置相关参数
        $expire = '';
        $salt = 'insensible';
        $param = uniqid().time();
        $token = crypt($param,$salt);

        $resPwd = md5($salt.'-'.$pwd);

        //存入数据库
        $resInsert = DB::table('l_addrs')
            ->insert(['a_addr'=>$addr,'a_pwd'=>$resPwd,'a_reg_time'=>date('Y-m-d H:i:s',time())]);


        if($resInsert){
            //在redis中设置token
            $redisOperate = new RedisOperate();
            $redisOperate->RedisSet('token-'.$addr,$token,$expire);
            return json_encode(array('error'=>0,'msg'=>'Token Generate OK!','data' =>array('token'=>$token)));

        }

    }


    /**
     * @param Request $request
     * @return string 生产环境添加对是否为手机客户端判断
     */
    //MVerify,对手机客户端进行必要验证
    public function MVerify(Request $request)
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        //判断是否是手机
//        $agent = new Agent();
//        if(!$agent->isMobile()){
//            return json_encode(array('error' => 1,'msg' => 'Client is not a mobile!'));
//        }



        //判断addr，token
        if (empty($request->input('addr'))){
            return json_encode(array('error'=>1,'msg'=>'Addr Empty!'));
        }

        $addr = trim($request->input('addr'));
        $addrLen = strlen($addr);
        if($addrLen < 26 || $addrLen > 34){
            return json_encode(array('error'=>1,'msg'=>'Addr Format Error!'));
        }

        if (empty($request->input('token'))){
            return json_encode(array('error'=>1,'msg'=>'Token Empty!'));
        }

        $token = trim($request->input('token'));
        $tokenLen = strlen($token);
        if($tokenLen != 32){
            return json_encode(array('error'=>1,'msg'=>'Token Format Error!'));
        }


        //限制地址和IP访问次数
        $uTool = new UniversalTools();

        //ip
        $ip = $request->getClientIp();
        $ipRes = $uTool->IPLimit($ip,60,60);
        if(!$ipRes){
            return json_encode(array('error'=>1,'msg'=>'IP Access Limit!'));
        }

        //addr
        $addrRes = $uTool->AddrLimit($addr,60,60);
        if(!$addrRes){
            return json_encode(array('error'=>1,'msg'=>'Addr Access Limit!'));
        }


        //判断token是否正确
        $tokenRes = $uTool->VerToken($addr,$token);

        if(!$tokenRes){
            return json_encode(array('error'=>1,'msg'=>'Token Error!'));
        }

        return json_encode(['error'=>0,'msg'=>['addr'=>$addr]]);
    }





    //lbtc rich list redis缓存处理600s
    public function LbtcRichList()
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        $param = '300';

        $curlOperate = new CurlOperate();
        $redis = new RedisOperate();

//        $timeOut = 600;
        $redisParam = 'rich-list';

        $response = $curlOperate->GetRichList($param);
        $richRes = json_decode($response,1);

        //判断请求是否发生错误
        if(array_key_exists('msg',$richRes)){
            return json_encode(array('error'=>1,'msg'=>$richRes['msg']));
        }

        $richArray = $richRes['result'];

        if(empty($richArray)){
            return json_encode(array('error'=>1,'msg'=>'Transaction information can not be found!'));
        }

        $richJson = json_encode($richArray);

        $redis->RedisSet($redisParam,$richJson);

        return json_encode(array('error'=>0,'msg'=>'redis rich-list save OK!'));

    }


    //get rich list
    public function GetLbtcRichList(Request $request)
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        $redis = new RedisOperate();

        $redisRich = $redis->RedisGet('rich-list');

        if($redisRich){
            return json_encode(array('error'=>0,'msg'=>json_decode($redisRich,1),'timestamp'=>$redis->RedisGet('timestamp'),'allcoins'=>$redis->RedisGet('all-coins')));
        }else{
            return json_encode(array('error'=>1,'msg'=>'rich-list is empty!'));
//            //默认查询50条记录
//            $param = $request->input('param','300');
//
//            if(!is_numeric($param) || $param > 300){
//                return json_encode(array('error'=>1,'msg'=>'Param is not Num!'));
//            }
//
//            $param = trim($param);
//
//            $curlOperate = new CurlOperate();
//            $response = $curlOperate->GetRichList($param);
//
//            $richRes = json_decode($response,1);
//
//            //判断请求是否发生错误
//            if(array_key_exists('msg',$richRes)){
//                return json_encode(array('error'=>1,'msg'=>$richRes['msg']));
//            }
//
//            $richArray = $richRes['result'];
//
//            if(empty($richArray)){
//                return json_encode(array('error'=>1,'msg'=>'Data can not be found!'));
//            }
//
//            return json_encode(array('error'=>0,'msg'=>$richArray,'timestamp'=>time(),'allcoins'=>$redis->RedisGet('all-coins')));
        }
    }


    //lbtc rich pre redis缓存处理600s
    public function LbtcRichPre()
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        $flag = 6;

        $curlOperate = new CurlOperate();
        $redis = new RedisOperate();

//        $timeOut = 600;
        $big = 100000000;

        $redisParam = 'rich-pre';
        $resArray = [];

//        if(!$redis->RedisExist($redisParam)){
            //特殊处理有0-1，1-10个lbtc地址数量
            $response = $curlOperate->GetRichPer(strval(1 * $big), strval(10 * $big));
            $richRes = json_decode($response, 1);
            //判断请求是否发生错误
            if (array_key_exists('msg', $richRes)) {
                return json_encode(array('error' => 1, 'msg' => $richRes['msg']));
            }

            $richArray = $richRes['result'];

            if (empty($richArray)) {
                return json_encode(array('error' => 1, 'msg' => 'RichPre information can not be found!'));
            }

            $resArray[] = $richArray[0];
            $resArray[] = $richArray[1];

            $allCoins = $richArray[0]['coins'] + $richArray[1]['coins'];
            $allAddr = $richArray[0]['addresses'] + $richArray[1]['addresses'];

            //其他比例数量的lbtc $i = 2
            for($i = 2;$i <= $flag;$i ++) {
                $limit = pow(10, $i) * $big;
                $response = $curlOperate->GetRichPer(strval(pow(10, $i - 1) * $big), strval($limit));

                $richRes = json_decode($response, 1);

                //判断请求是否发生错误
                if (array_key_exists('msg', $richRes)) {
                    return json_encode(array('error' => 1, 'msg' => $richRes['msg']));
                }

                $richArray = $richRes['result'];

                if (empty($richArray)) {
                    return json_encode(array('error' => 1, 'msg' => 'RichPre information can not be found!'));
                }

                $resArray[] = $richArray[1];
                $allCoins = $allCoins + $richArray[1]['coins'];
                $allAddr = $allAddr + $richArray[1]['addresses'];
            }

            //存入redis
            $richJson = json_encode($resArray);
            //暂时去掉$timeOut
            $redis->RedisSet($redisParam,$richJson);
            $redis->RedisSet('all-coins',$allCoins);
            $redis->RedisSet('all-addrs',$allAddr);
            $redis->RedisSet('timestamp',time());
            return json_encode(array('error'=>0,'msg'=>'redis rich-pre save OK!'));
//        }
    }


    //get rich pre
    public function GetLbtcRichPre(Request $request)
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        $redis = new RedisOperate();

        $redisRich = $redis->RedisGet('rich-pre');

        if($redisRich){
            return json_encode(array('error'=>0,'msg'=>json_decode($redisRich,1),'timestamp'=>$redis->RedisGet('timestamp'),'allcoins'=>$redis->RedisGet('all-coins'),'alladdrs'=>$redis->RedisGet('all-addrs')));
        }else{
            return json_encode(array('error'=>1,'msg'=>'rich-pre is empty!'));
        }
    }


    /**
     *委员会相关接口
     */
    //set committees redis 12s
    public function ListCommittees()
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        $curlOperate = new CurlOperate();
        $redis = new RedisOperate();

        $redisParam = 'com-list';

        $response = $curlOperate->GetCommitteesList();
        $comRes = json_decode($response,1);


        //判断请求是否发生错误
        if(array_key_exists('msg',$comRes)){
            return json_encode(array('error'=>1,'msg'=>$comRes['msg']));
        }

        $comArray = $comRes['result'];

        //判断接口数据知否为空
        if(empty($comArray)){
            return json_encode(array('error'=>1,'msg'=>'Committees Data Empty!'));
        }

        //接口拼接数组
        $comTempArr = array();
        foreach ($comArray as $cVal){
            $cAddr = $cVal['address'];
            $comTempArr[$cAddr] = $cVal;
        }

        //redis中数据拼接数组
        $redisCom = $redis->RedisGet('com-list');
        $redisArr = array();
        if($redisCom){
            $redisTempArr = json_decode($redisCom,1);
            foreach ($redisTempArr as $rVal){
                $raddr = $rVal['address'];
                $redisArr[$raddr] = $rVal;
            }
            $arrayDif = array_diff_key($comTempArr, $redisArr);
            if(empty($arrayDif)){
                return json_encode(array('error'=>1,'msg'=>'No new members registered!'));
            }
        }


        $uTool = new UniversalTools();
        $newComArray = array();
        if($redisArr){
            $flag = 0;
            foreach ($arrayDif as $dKey => $dVal){
                $dRes = $curlOperate->GetCommitteeVotes($dKey);
                //为了速度没有异常处理
                $votes = json_decode($dRes,1)['result']['votes'];
                $newComArray[$flag]['address'] = $dKey;
                $newComArray[$flag]['name'] = $uTool->FilterBadWords($dVal['name']);
                $newComArray[$flag]['url'] = $uTool->FilterBadWords($dVal['url']);
                $newComArray[$flag]['votes'] = $votes;
                $flag ++;

            }
            $resArray = array_merge($redisTempArr,$newComArray);
        }else{
            foreach ($comArray as $cKey => $cVal){
                $addr = $cVal['address'];
                $aRes = $curlOperate->GetCommitteeVotes($addr);
                //为了速度没有异常处理
                $votes = json_decode($aRes,1)['result']['votes'];
                $newComArray[$cKey]['address'] = $addr;
                $newComArray[$cKey]['name'] = $uTool->FilterBadWords($cVal['name']);
                $newComArray[$cKey]['url'] = $uTool->FilterBadWords($cVal['url']);
                $newComArray[$cKey]['votes'] = $votes;
            }
            $resArray = $newComArray;
        }


        $comJson = json_encode($resArray);

        //设置超时时间略大于，定时任务时间，便于查错
        $timeOut = '';
        $redis->RedisSet($redisParam,$comJson,$timeOut);

        return json_encode(array('error'=>0,'msg'=>'redis com-list save OK!'));
    }


    //get committees redis
    public function GetListCommittees()
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        $redis = new RedisOperate();

        $redisCom = $redis->RedisGet('com-list');

        if($redisCom){
            return json_encode(array('error'=>0,'msg'=>json_decode($redisCom,1)));
        }else{
            return json_encode(array('error'=>1,'msg'=>'com-list is empty!'));
        }
    }


    //get listcommitteevotes参数用(名字)，给这个理事会成员投票的地址(人)
    public function GetListCommitteeVotes(Request $request)
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        if (empty($request->input('param'))){
            return json_encode(array('error'=>1,'msg'=>'Input Empty!'));
        }

        $param = trim($request->input('param'));

        if(strlen($param) > 32){
            return json_encode(array('error'=>1,'msg'=>'Name Too Long!'));
        }

        //先从redis中查找，如果不存在，在调用接口
        $redis = new RedisOperate();
        $resRedis = $redis->RedisGet('cv-'.$param);

        if($resRedis){
            $redisArray = json_decode($resRedis,1);

            $res = $redisArray['result'];

            if($res){
                return json_encode(array('error'=>0,'msg'=>$res));
            }else{
                return json_encode(array('error'=>1,'msg'=>'No one voted for this council member!'));
            }
        }else{
            $curlOperate = new CurlOperate();

            $response = $curlOperate->GetCommitteeVotesList($param);

            $responseArray = json_decode($response,1);

            //判断请求是否发生错误
            if(array_key_exists('msg',$responseArray)){
                return json_encode(array('error'=>1,'msg'=>$responseArray['msg']));
            }

            $res = $responseArray['result'];

            $timeOut = 6;
            if($res){
                $redis->RedisSet('cv-'.$param,json_encode($responseArray),$timeOut);
                return json_encode(array('error'=>0,'msg'=>$res));
            }else{
                $redis->RedisSet('cv-'.$param,json_encode($responseArray),$timeOut);
                return json_encode(array('error'=>1,'msg'=>'No one voted for this council member!'));
            }
        }
    }


    //get listvotedcommittee参数用(地址)，这个地址投给了哪个理事会成员
    public function GetListVotedCommittee(Request $request)
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        if (empty($request->input('param'))){
            return json_encode(array('error'=>1,'msg'=>'Input Empty!'));
        }

        $param = trim($request->input('param'));

        $paramLen = strlen($param);

        if($paramLen < 26 || $paramLen > 34){
            return json_encode(array('error'=>1,'msg'=>'Addr Format Error!'));
        }

        //先从redis中查找，如果不存在，在调用接口
        $redis = new RedisOperate();
        $resRedis = $redis->RedisGet('vc-'.$param);

        if($resRedis){
            $redisArray = json_decode($resRedis,1);

            $res = $redisArray['result'];

            if($res){
                return json_encode(array('error'=>0,'msg'=>$res));
            }else{
                return json_encode(array('error'=>1,'msg'=>'This address did not vote for any committee members!'));
            }
        }else{
            $curlOperate = new CurlOperate();

            $response = $curlOperate->GetVotedCommitteeList($param);

            $responseArray = json_decode($response,1);

            //判断请求是否发生错误
            if(array_key_exists('msg',$responseArray)){
                return json_encode(array('error'=>1,'msg'=>$responseArray['msg']));
            }

            $res = $responseArray['result'];
            $timeOut = 6;
            if($res){
                $redis->RedisSet('vc-'.$param,json_encode($responseArray),$timeOut);
                return json_encode(array('error'=>0,'msg'=>$res));
            }else{
                $redis->RedisSet('vc-'.$param,json_encode($responseArray),$timeOut);
                return json_encode(array('error'=>1,'msg'=>'This address did not vote for any committee members!'));
            }
        }
    }


    /**
     * 议案相关接口
     */
    //所有议案存入redis 做定时任务 9s
    public function SetBillsInfo()
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");




        $curlOperate = new CurlOperate();
        $redis = new RedisOperate();

        //获取议案ids
        $response = $curlOperate->GetListBills();

        $responseArray = json_decode($response,1);

        //判断请求是否发生错误
        if(array_key_exists('msg',$responseArray)){
            return json_encode(array('error'=>1,'msg'=>$responseArray['msg']));
        }

        $res = $responseArray['result'];

        //没有任何议案
        if(!$res){
            return json_encode(array('error'=>1,'msg'=>'No bills!'));
        }

        //billsid存入redis
        $timeOut = 9;
        $redis->RedisSet('billsid',$response,$timeOut);

        //用于过滤敏感词
        $uTool = new UniversalTools();

        //遍历议案id，获取议案的具体信息
        foreach ($res as $bid){
            $billId = $bid['id'];
            $billInfo = $curlOperate->GetBill($billId);
            $billArray = json_decode($billInfo,1);

            if(array_key_exists('msg',$billArray)){
                return json_encode(array('error'=>1,'msg'=>$billArray['msg']));
            }

            $billRes = $billArray['result'];

            //过滤敏感词
            $billRes["title"] = $uTool->FilterBadWords($billRes["title"]);
            $billRes["detail"] = $uTool->FilterBadWords($billRes["detail"]);
            $billRes["url"] = $uTool->FilterBadWords($billRes["url"]);

            //投给此议题不同解决方案的地址listbillvoters
            $bVoters = $curlOperate->GetListBillVoters($billId);
            $bVotersArray = json_decode($bVoters,1);

            if(array_key_exists('msg',$bVotersArray)){
                return json_encode(array('error'=>1,'msg'=>$bVotersArray['msg']));
            }

            $bVotersRes = $bVotersArray['result'];

            //添加案例投票地址
            $bOptions = $billRes["options"];
            foreach ($bOptions as $bKey => $bVal){
                $billRes["options"][$bKey]['address'] = $bVotersRes[$bKey]['addresses'];
                //过滤敏感词
                $billRes["options"][$bKey]['option'] = $uTool->FilterBadWords($billRes["options"][$bKey]['option']);
            }

            //bill详情存入redis
            $redis->RedisSet('billid-'.$billId,json_encode($billRes),$timeOut);
        }
        return json_encode(array('error'=>0,'msg'=>'redis bill-info save OK!'));




//        $curlOperate = new CurlOperate();
//        $redis = new RedisOperate();
//
//        //获取议案ids
//        $response = $curlOperate->GetListBills();
//
//        $responseArray = json_decode($response,1);
//
//        //判断请求是否发生错误
//        if(array_key_exists('msg',$responseArray)){
//            return json_encode(array('error'=>1,'msg'=>$responseArray['msg']));
//        }
//
//        $res = $responseArray['result'];
//
//
//        //没有任何议案
//        if(!$res){
//            return json_encode(array('error'=>1,'msg'=>'No bills!'));
//        }
//
//
//        //拼接接口数组
//        $bArray = array();
//        foreach ($res as $rVal){
//            $bId = $rVal["id"];
//            $bArray[$bId] = $rVal;
//        }
//
//
//        $timeOut = '';
//        //redis中数据拼接
//        $bRedis = $redis->RedisGet('billsid');
//
//        //redis中存在数据，计算和接口直接的差集
//        if($bRedis){
//            //拼接redis数组
//            $rTempArray = json_decode($bRedis,1);
//            $rArray = array();
//            foreach ($rTempArray as $rtVal){
//                $rId = $rtVal['id'];
//                $rArray[$rId] = $rtVal;
//            }
//
//            $difArray = array_diff_key($bArray, $rArray);
//
//            if($difArray){
//                $difTempArray = array();
//                $flag = 0;
//                foreach ($difArray as $dVal){
//                    $difTempArray[$flag] = $dVal;
//                    $flag++;
//                }
//
//                //合并数组存入reids
//                $merArray = array_merge($rTempArray,$difTempArray);
//                $redis->RedisSet('billsid',json_encode($merArray),$timeOut);
//
//                //用于过滤敏感词
//                $uTool = new UniversalTools();
//                //遍历议案id，获取议案的具体信息
//                foreach ($difTempArray as $bid){
//                    $billId = $bid['id'];
//                    $billInfo = $curlOperate->GetBill($billId);
//                    $billArray = json_decode($billInfo,1);
//
//                    if(array_key_exists('msg',$billArray)){
//                        return json_encode(array('error'=>1,'msg'=>$billArray['msg']));
//                    }
//
//                    $billRes = $billArray['result'];
//
//                    //过滤敏感词
//                    $billRes["title"] = $uTool->FilterBadWords($billRes["title"]);
//                    $billRes["detail"] = $uTool->FilterBadWords($billRes["detail"]);
//                    $billRes["url"] = $uTool->FilterBadWords($billRes["url"]);
//
//                    //投给此议题不同解决方案的地址listbillvoters
//                    $bVoters = $curlOperate->GetListBillVoters($billId);
//                    $bVotersArray = json_decode($bVoters,1);
//
//                    if(array_key_exists('msg',$bVotersArray)){
//                        return json_encode(array('error'=>1,'msg'=>$bVotersArray['msg']));
//                    }
//
//                    $bVotersRes = $bVotersArray['result'];
//
//                    //添加案例投票地址
//                    $bOptions = $billRes["options"];
//                    foreach ($bOptions as $bKey => $bVal){
//                        $billRes["options"][$bKey]['address'] = $bVotersRes[$bKey]['addresses'];
//                        //过滤敏感词
//                        $billRes["options"][$bKey]['option'] = $uTool->FilterBadWords($billRes["options"][$bKey]['option']);
//                    }
//
//                    //bill详情存入redis
//                    $redis->RedisSet('billid-'.$billId,json_encode($billRes),$timeOut);
//                }
//
//            }else{
//                //No new bills
//                return json_encode(array('error'=>1,'msg'=>'No new bills!'));
//            }
//        }else{
//            $redis->RedisSet('billsid',json_encode($res),$timeOut);
//            //第一次请求接口，redis中没有数据
//            //遍历议案id，获取议案的具体信息
//
//            //用于过滤敏感词
//            $uTool = new UniversalTools();
//            foreach ($res as $bid){
//                $billId = $bid['id'];
//                $billInfo = $curlOperate->GetBill($billId);
//                $billArray = json_decode($billInfo,1);
//
//                if(array_key_exists('msg',$billArray)){
//                    return json_encode(array('error'=>1,'msg'=>$billArray['msg']));
//                }
//
//                $billRes = $billArray['result'];
//
//                //过滤敏感词
//                $billRes["title"] = $uTool->FilterBadWords($billRes["title"]);
//                $billRes["detail"] = $uTool->FilterBadWords($billRes["detail"]);
//                $billRes["url"] = $uTool->FilterBadWords($billRes["url"]);
//
//                //投给此议题不同解决方案的地址listbillvoters
//                $bVoters = $curlOperate->GetListBillVoters($billId);
//                $bVotersArray = json_decode($bVoters,1);
//
//                if(array_key_exists('msg',$bVotersArray)){
//                    return json_encode(array('error'=>1,'msg'=>$bVotersArray['msg']));
//                }
//
//                $bVotersRes = $bVotersArray['result'];
//
//                //添加案例投票地址
//                $bOptions = $billRes["options"];
//                foreach ($bOptions as $bKey => $bVal){
//                    $billRes["options"][$bKey]['address'] = $bVotersRes[$bKey]['addresses'];
//                    //过滤敏感词
//                    $billRes["options"][$bKey]['option'] = $uTool->FilterBadWords($billRes["options"][$bKey]['option']);
//                }
//
//                //bill详情存入redis
//                $redis->RedisSet('billid-'.$billId,json_encode($billRes),$timeOut);
//            }
//        }
//
//        return json_encode(array('error'=>0,'msg'=>'redis bill-info save OK!'));
    }


    //获取议案详情
    public function GetBillsInfo()
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");


        $redis = new RedisOperate();
        $billIds = $redis->RedisGet('billsid');

        if(empty($billIds)){
            return json_encode(array('error'=>1,'msg'=>'redis billids no exist!'));
        }

        $billIdsArray = json_decode($billIds,1);
        $billIdsArray = $billIdsArray['result'];

        $resArray = array();
        foreach ($billIdsArray as $bVal){
            $bId = $bVal['id'];
            $billInfo = $redis->RedisGet('billid-'.$bId);

            if(empty($billInfo)){
                echo 'billid-'.$bId.'isEmpty';
            }

            $billInfoArray = json_decode($billInfo,1);
            $billInfoArray['id'] = $bId;
            $resArray[] = $billInfoArray;
        }
        return json_encode(array('error'=>0,'msg'=>$resArray));



//        $redis = new RedisOperate();
//        $billIds = $redis->RedisGet('billsid');
//
//        if(empty($billIds)){
//            return json_encode(array('error'=>1,'msg'=>'redis billids no exist!'));
//        }
//
//        $billIdsArray = json_decode($billIds,1);
//
//        $resArray = array();
//        $curlOperate = new CurlOperate();
//        $uTool = new UniversalTools();
//
//        foreach ($billIdsArray as $bVal){
//            $bId = $bVal['id'];
//            $billInfo = $redis->RedisGet('billid-'.$bId);
//
//            //会漏掉议案id，没有处理
//            if(empty($billInfo)){
////                echo 'billid-'.$bId.'isEmpty'."<br>";
//
//                $billInfo = $curlOperate->GetBill($bId);
//                $billArray = json_decode($billInfo,1);
//
//                if(array_key_exists('msg',$billArray)){
//                    return json_encode(array('error'=>1,'msg'=>$billArray['msg']));
//                }
//
//                $billRes = $billArray['result'];
//
//                //过滤敏感词
//                $billRes["title"] = $uTool->FilterBadWords($billRes["title"]);
//                $billRes["detail"] = $uTool->FilterBadWords($billRes["detail"]);
//                $billRes["url"] = $uTool->FilterBadWords($billRes["url"]);
//
//                //投给此议题不同解决方案的地址listbillvoters
//                $bVoters = $curlOperate->GetListBillVoters($bId);
//                $bVotersArray = json_decode($bVoters,1);
//
//                if(array_key_exists('msg',$bVotersArray)){
//                    return json_encode(array('error'=>1,'msg'=>$bVotersArray['msg']));
//                }
//
//                $bVotersRes = $bVotersArray['result'];
//
//                //添加案例投票地址
//                $bOptions = $billRes["options"];
//                foreach ($bOptions as $bKey => $bVal){
//                    $billRes["options"][$bKey]['address'] = $bVotersRes[$bKey]['addresses'];
//                    //过滤敏感词
//                    $billRes["options"][$bKey]['option'] = $uTool->FilterBadWords($billRes["options"][$bKey]['option']);
//                }
//
//                //bill详情存入redis
//                $redis->RedisSet('billid-'.$bId,json_encode($billRes),"");
//            }
//
//            $billInfoArray = json_decode($billInfo,1);
//            $billInfoArray['id'] = $bId;
//            $resArray[] = $billInfoArray;
//        }
//        return json_encode(array('error'=>0,'msg'=>$resArray));
    }


    //查看此地址投给了哪些议案
    public function VoterBillsByAddr(Request $request)
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        if (empty($request->input('param'))){
            return json_encode(array('error'=>1,'msg'=>'Input Empty!'));
        }

        $param = trim($request->input('param'));

        $paramLen = strlen($param);

        if($paramLen < 26 || $paramLen > 34){
            return json_encode(array('error'=>1,'msg'=>'Addr Format Error!'));
        }

        //先从redis中查找，如果不存在，在调用接口
        $redis = new RedisOperate();
        $resRedis = $redis->RedisGet('vb-'.$param);

        if($resRedis){
            $redisArray = json_decode($resRedis,1);

            $res = $redisArray['result'];

            if($res){
                return json_encode(array('error'=>0,'msg'=>$res));
            }else{
                return json_encode(array('error'=>1,'msg'=>'This address did not vote for any bills!'));
            }
        }else{
            $curlOperate = new CurlOperate();

            $response = $curlOperate->GetListVoterBills($param);

            $responseArray = json_decode($response,1);

            //判断请求是否发生错误
            if(array_key_exists('msg',$responseArray)){
                return json_encode(array('error'=>1,'msg'=>$responseArray['msg']));
            }

            $res = $responseArray['result'];
            $timeOut = 6;
            if($res){
                $redis->RedisSet('vb-'.$param,json_encode($responseArray),$timeOut);
                return json_encode(array('error'=>0,'msg'=>$res));
            }else{
                $redis->RedisSet('vb-'.$param,json_encode($responseArray),$timeOut);
                return json_encode(array('error'=>1,'msg'=>'This address did not vote for any bills!'));
            }
        }
    }


    //查看此地址提出了哪些议案 参数用名字
    public function GetListCommitteeBills(Request $request)
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        if (empty($request->input('param'))){
            return json_encode(array('error'=>1,'msg'=>'Input Empty!'));
        }

        $param = trim($request->input('param'));

        $paramLen = strlen($param);

        if($paramLen > 32){
            return json_encode(array('error'=>1,'msg'=>'Name Format Error!'));
        }

        //先从redis中查找，如果不存在，在调用接口
        $redis = new RedisOperate();
        $resRedis = $redis->RedisGet('lcb-'.$param);

        if($resRedis){
            $redisArray = json_decode($resRedis,1);

            $res = $redisArray['result'];

            if($res){
                return json_encode(array('error'=>0,'msg'=>$res));
            }else{
                return json_encode(array('error'=>1,'msg'=>'This council member did not propose any motion!'));
            }
        }else{
            $curlOperate = new CurlOperate();

            $response = $curlOperate->GetListCommitteeBills($param);

            $responseArray = json_decode($response,1);

            //判断请求是否发生错误
            if(array_key_exists('msg',$responseArray)){
                return json_encode(array('error'=>1,'msg'=>$responseArray['msg']));
            }

            $res = $responseArray['result'];
            $timeOut = 6;
            if($res){
                $redis->RedisSet('lcb-'.$param,json_encode($responseArray),$timeOut);
                return json_encode(array('error'=>0,'msg'=>$res));
            }else{
                $redis->RedisSet('lcb-'.$param,json_encode($responseArray),$timeOut);
                return json_encode(array('error'=>1,'msg'=>'This council member did not propose any motion!'));
            }
        }
    }


    //判断到期时间，更新议案状态 定时任务1hour
    public function UpdateBillStatus()
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        $redis = new RedisOperate();
        $curlOperate = new CurlOperate();

        $billIds = $redis->RedisGet('billsid');

        if ($billIds){
            $timestamp = time();
            $billArr = json_decode($billIds,1)["result"];
            foreach ($billArr as $bill){
                $bId = $bill['id'];
                $billInfo = $redis->RedisGet('billid-'.$bId);
                $billCon = json_decode($billInfo,1);
                $billEndTime = $billCon['endtime'];
                if(!array_key_exists('flag',$billCon)){
                    //更新议案详情
                    if($timestamp >= $billEndTime){
                        $billInfo = $curlOperate->GetBill($bId);
                        $state = json_decode($billInfo,1)['result']['state'];

                        $billCon['state'] = $state;
                        $billCon['flag'] = '1';
                        $redis->RedisSet('billid-'.$bId,json_encode($billCon));
                    }
                }
            }

            //更新ids
            $response = $curlOperate->GetListBills();

            $responseArray = json_decode($response,1);

            //判断请求是否发生错误
            if(array_key_exists('msg',$responseArray)){
                return json_encode(array('error'=>1,'msg'=>$responseArray['msg']));
            }

//            $res = $responseArray['result'];

            $redis->RedisSet('billsid',$response);
        }
    }


    //跟新议案选项的票数 定时任务30s
    public function BillOptionsVotes()
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        $redis = new RedisOperate();
        $curlOperate = new CurlOperate();

        $billIds = $redis->RedisGet('billsid');

        if($billIds){
            $timestamp = time();
            $billArr = json_decode($billIds,1)["result"];

            foreach ($billArr as $bill){
                $bId = $bill['id'];
                $billInfo = $redis->RedisGet('billid-'.$bId);
                $billCon = json_decode($billInfo,1);
                $billEndTime = $billCon['endtime'];
                //更新议案详情
                if($timestamp <= $billEndTime){
                    //votes
                    $bVoters = $curlOperate->GetListBillVoters($bId);
                    $bVotersArray = json_decode($bVoters,1);

                    if(array_key_exists('msg',$bVotersArray)){
                        return json_encode(array('error'=>1,'msg'=>$bVotersArray['msg']));
                    }

                    $bVotersRes = $bVotersArray['result'];

                    //添加案例投票地址
                    $bOptions = $billCon["options"];

                    foreach ($bOptions as $bKey => $bVal){
                        $billCon["options"][$bKey]['address'] = $bVotersRes[$bKey]['addresses'];
                    }

                    $redis->RedisSet('billid-'.$bId,json_encode($billCon));
                }
            }

        }
    }


    /**token相关
     * @param Request $request
     * @return false|string
     */
    //查询token信息
    public function GetTokenInfo(Request $request)
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        $param = trim($request->input('param'));

        if ($param){
            $paramLen = strlen($param);
            if($paramLen < 26 || $paramLen > 34){
                return json_encode(array('error'=>1,'msg'=>'Addr Format Error!'));
            }

            $curlOperate = new CurlOperate();

            $response = $curlOperate->GetTokenInfo($param);

            $responseArray = json_decode($response,1);

            //判断请求是否发生错误
            if(array_key_exists('msg',$responseArray)){
                return json_encode(array('error'=>1,'msg'=>$responseArray['msg']));
            }

            $res = $responseArray['result'];

            return json_encode(array('error'=>0,'msg'=>$res));

        }else{
            $curlOperate = new CurlOperate();

            $response = $curlOperate->GetTokenInfo();

            $responseArray = json_decode($response,1);

            //判断请求是否发生错误
            if(array_key_exists('msg',$responseArray)){
                return json_encode(array('error'=>1,'msg'=>$responseArray['msg']));
            }

            $res = $responseArray['result'];

            return json_encode(array('error'=>0,'msg'=>$res));
        }

    }


    //查询token余额
    public function GetTokenBalance(Request $request)
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        $userAddr = trim($request->input('uAddr'));
        $tokenAddr = trim($request->input('tAddr'));

        if (empty($userAddr)){
            return json_encode(array('error'=>1,'msg'=>'userAddress is empty!'));
        }

        $paramLen = strlen($userAddr);
        if($paramLen < 26 || $paramLen > 34){
            return json_encode(array('error'=>1,'msg'=>'userAddress Format Error!'));
        }

        //如果tokenaddr存在
        if ($tokenAddr){
            $paramLen = strlen($tokenAddr);
            if($paramLen < 26 || $paramLen > 34){
                return json_encode(array('error'=>1,'msg'=>'tokenAddress Format Error!'));
            }

            $curlOperate = new CurlOperate();
            $response = $curlOperate->GetTokenBalance($userAddr,$tokenAddr);

            $responseArray = json_decode($response,1);

            //判断请求是否发生错误
            if(array_key_exists('msg',$responseArray)){
                return json_encode(array('error'=>1,'msg'=>$responseArray['msg']));
            }

            $res = $responseArray['result'];

            return json_encode(array('error'=>0,'msg'=>$res));
        }else{
            $curlOperate = new CurlOperate();
            $response = $curlOperate->GetTokenBalance($userAddr);

            $responseArray = json_decode($response,1);

            //判断请求是否发生错误
            if(array_key_exists('msg',$responseArray)){
                return json_encode(array('error'=>1,'msg'=>$responseArray['msg']));
            }

            $res = $responseArray['result'];

            return json_encode(array('error'=>0,'msg'=>$res));
        }
    }


    //token与地址互查
    //定时任务存入 3s RedisRPush
    public function SetOwenToToken(Request $request)
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        $resTokenInfo = $this->GetTokenInfo($request);

        $resArray = json_decode($resTokenInfo,1);

        if($resArray["error"] != 0){
            $msg =$resArray["msg"];
            return json_encode(["error"=>1,"msg"=>$msg]);
        }

        $resMsg = $resArray["msg"];

        $redis = new RedisOperate();

        foreach ($resMsg as $v){
            $ownerAddress = $v["ownerAddress"];
            $tokenSymbol = $v["tokenSymbol"];

            $redis->RedisRPush('ownerAddr-'.$ownerAddress,$tokenSymbol);
        }

    }


    //定时任务存入 3s RedisSet
    public function SetTokenToOwen(Request $request)
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        $resTokenInfo = $this->GetTokenInfo($request);

        $resArray = json_decode($resTokenInfo,1);

        if($resArray["error"] != 0){
            $msg =$resArray["msg"];
            return json_encode(["error"=>1,"msg"=>$msg]);
        }

        $resMsg = $resArray["msg"];

        $redis = new RedisOperate();

        foreach ($resMsg as $v){
            $ownerAddress = $v["ownerAddress"];
            $tokenSymbol = $v["tokenSymbol"];

            $redis->RedisSet('token-'.$tokenSymbol,$ownerAddress);
        }
    }

    //互查
    public function GetTokenOrOwner(Request $request)
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        $redis = new RedisOperate();

        $ownPreFix = 'ownerAddr-';
        $tokenPreFix = 'token-';

        $param = $request->input('param');

        if (empty($param)){
            return json_encode(["error"=>1,"msg" => "param is empty!"]);
        }

        $ownExpire = $redis->RedisExist($ownPreFix.$param);
        $tokenExpire = $redis->RedisExist($tokenPreFix.$param);

        if ($ownExpire){
            $res = $redis->RedisLRange($ownPreFix.$param);
            return json_encode(["error"=>0,"msg" => array_unique($res)]);
        }

        if ($tokenExpire){
            $res = $redis->RedisGet($tokenPreFix.$param);
            return json_encode(["error"=>0,"msg" => $res]);
        }

        return json_encode(["error"=>1,"msg" => "ownerAddr and token is expire!"]);
    }


    //通过地址查询注册名
    public function GetAddressName(Request $request)
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        $param = trim($request->input('param'));

        $paramLen = strlen($param);
        if($paramLen < 26 || $paramLen > 34){
            return json_encode(array('error'=>1,'msg'=>'Addr Format Error!'));
        }

        $curlOperate = new CurlOperate();

        $response = $curlOperate->GetAddressName($param);

        $responseArray = json_decode($response,1);

        //判断请求是否发生错误
        if(array_key_exists('msg',$responseArray)){
            return json_encode(array('error'=>1,'msg'=>$responseArray['msg']));
        }

        $res = $responseArray['result'];

        if(empty($res)){
            return json_encode(array('error'=>1,'msg'=>'Address has no registered name'));
        }

        return json_encode(array('error'=>0,'msg'=>$res));
    }


    //通过注册名查询地址
    public function GetNameAddress(Request $request)
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        $param = trim($request->input('param'));

        $curlOperate = new CurlOperate();

        $response = $curlOperate->GetNameAddress($param);

        $responseArray = json_decode($response,1);

        //判断请求是否发生错误
        if(array_key_exists('msg',$responseArray)){
            return json_encode(array('error'=>1,'msg'=>$responseArray['msg']));
        }

        $res = $responseArray['result'];

        if(empty($res)){
            return json_encode(array('error'=>1,'msg'=>'This name does not exist'));
        }

        return json_encode(array('error'=>0,'msg'=>$res));
    }


    //根据地址查询token交易信息
    public function GetAddressTokenTxids(Request $request)
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        $addr = trim($request->input('addr'));
        if (empty($addr)){
            return json_encode(array('error'=>1,'msg'=>'Addr is empty!'));
        }

        $addrLen = strlen($addr);
        if($addrLen < 26 || $addrLen > 34){
            return json_encode(array('error'=>1,'msg'=>'Addr Format Error!'));
        }


        $startBlock = trim($request->input('start'));
        if (empty($startBlock)){
            $startBlock = '0';
        }

        if (!is_numeric($startBlock)){
            return json_encode(array('error'=>1,'msg'=>'StartBlock is not Num!'));
        }

        if ($startBlock < 0){
            return json_encode(array('error'=>1,'msg'=>'StartBlock must >= 0!'));
        }

        $addrToken = trim($request->input('addrtoken'));
        
        if ($addrToken){
            $addrTokenLen = strlen($addrToken);
            if($addrTokenLen < 26 || $addrTokenLen > 34){
                return json_encode(array('error'=>1,'msg'=>'AddrTokenLen Format Error!'));
            }
        }

        $curlOperate = new CurlOperate();

        $response = $curlOperate->Getaddresstokentxids($addr,$startBlock,$addrToken);

        $responseArray = json_decode($response,1);

        //判断请求是否发生错误
        if(array_key_exists('msg',$responseArray)){
            return json_encode(array('error'=>1,'msg'=>$responseArray['msg']));
        }

        $res = $responseArray['result'];

        if(empty($res)){
            return json_encode(array('error'=>1,'msg'=>'This name does not exist'));
        }

        return json_encode(array('error'=>0,'msg'=>$res));
    }


    /**
     * mobile
     */
    //MGetTxInfo
    public function MGetTxInfo(Request $request)
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");


        $checkRes = $this->MVerify($request,250,1);
        $checkArray = json_decode($checkRes,1);
        if($checkArray['error']){
            return $checkRes;
        }else {
            $param = $request->input('param');

            //redis
            $redisParam = $param;
            $redis = new RedisOperate();
            $redisExist = $redis->RedisExist($redisParam);

            if ($redisExist) {
                return json_encode(['error'=>0,'msg'=>json_decode($redis->RedisGet($redisParam),1)['result']]);
            } else {
                //request GetTransactionNew
                $curlOperate = new CurlOperate();
                $txInfo = $curlOperate->GetTransactionNew($redisParam);
                $txInfoArray = json_decode($txInfo, 1);

                if (array_key_exists('msg', $txInfoArray)) {
                    return json_encode(array('error' => 1, 'msg' => $txInfoArray['msg']));
                }

                //confirmations count
                $txResult = $txInfoArray['result'];
                if (array_key_exists('confirmations', $txResult)) {
                    $confirmations = $txResult['confirmations'];

                    $flagSum = 50;
                    $expire = 30;

                    if ($confirmations >= $flagSum) {
                        $txTemp = $txInfoArray['result']['vin'];
                        foreach ($txTemp as $txkey => $txval) {
                            if (!array_key_exists('coinbase', $txval)) {
                                $vinHash = $txval['txid'];
                                $voutN = $txval['vout'];
                                $txVout = $curlOperate->GetTransactionNew($vinHash);
                                $txVoutArray = json_decode($txVout, 1);

                                if (array_key_exists('msg', $txVoutArray)) {
                                    return json_encode(array('error' => 1, 'msg' => $txVoutArray['msg']));
                                }

                                $txVoutResArray = $txVoutArray['result']['vout'];

                                foreach ($txVoutResArray as $k => $txOutVal) {
                                    if ($txOutVal['n'] === $voutN) {
                                        $value = $txOutVal['value'];
                                        $addr = $txOutVal["scriptPubKey"]["addresses"][0];
                                        $txInfoArray['result']['vin'][$txkey]['value'] = $value;
                                        $txInfoArray['result']['vin'][$txkey]['addr'] = $addr;
                                    }
                                }
                            }

                        }

                        $txInfo = json_encode($txInfoArray);
                        $redis->RedisSet($redisParam, $txInfo);
                        return json_encode(['error'=>0,'msg'=>$txInfoArray['result']]);
                    }
                    $txTemp = $txInfoArray['result']['vin'];
                    foreach ($txTemp as $txkey => $txval) {
                        if (!array_key_exists('coinbase', $txval)) {
                            $vinHash = $txval['txid'];
                            $voutN = $txval['vout'];
                            $txVout = $curlOperate->GetTransactionNew($vinHash);
                            $txVoutArray = json_decode($txVout, 1);

                            if (array_key_exists('msg', $txVoutArray)) {
                                return json_encode(array('error' => 1, 'msg' => $txVoutArray['msg']));
                            }

                            $txVoutResArray = $txVoutArray['result']['vout'];

                            foreach ($txVoutResArray as $k => $txOutVal) {
                                if ($txOutVal['n'] === $voutN) {
                                    $value = $txOutVal['value'];
                                    $addr = $txOutVal["scriptPubKey"]["addresses"][0];
                                    $txInfoArray['result']['vin'][$txkey]['value'] = $value;
                                    $txInfoArray['result']['vin'][$txkey]['addr'] = $addr;
                                }
                            }
                        }

                    }

                    $txInfo = json_encode($txInfoArray);
                    $redis->RedisSet($redisParam, $txInfo, $expire);
                    return json_encode(['error'=>0,'msg'=>$txInfoArray['result']]);
                } else {
                    $txTemp = $txInfoArray['result']['vin'];
                    foreach ($txTemp as $txkey => $txval) {
                        if (!array_key_exists('coinbase', $txval)) {
                            $vinHash = $txval['txid'];
                            $voutN = $txval['vout'];
                            $txVout = $curlOperate->GetTransactionNew($vinHash);
                            $txVoutArray = json_decode($txVout, 1);

                            if (array_key_exists('msg', $txVoutArray)) {
                                return json_encode(array('error' => 1, 'msg' => $txVoutArray['msg']));
                            }

                            $txVoutResArray = $txVoutArray['result']['vout'];

                            foreach ($txVoutResArray as $k => $txOutVal) {
                                if ($txOutVal['n'] === $voutN) {
                                    $value = $txOutVal['value'];
                                    $addr = $txOutVal["scriptPubKey"]["addresses"][0];
                                    $txInfoArray['result']['vin'][$txkey]['value'] = $value;
                                    $txInfoArray['result']['vin'][$txkey]['addr'] = $addr;
                                }
                            }
                        }

                    }
                    return json_encode(['error'=>0,'msg'=>$txInfoArray['result']]);
                }
            }
        }
    }


    //获取所有代理人的地址和名字
    public function MGetlistdelegates(Request $request)
    {
        //获取listdelegates
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        $checkRes = $this->MVerify($request);
        $checkArray = json_decode($checkRes,1);
        if($checkArray['error']){
            return $checkRes;
        }else {
            //获取浏览器接口数据
            $apiRes = file_get_contents("http://172.31.239.84/getactive");

            $apiResArr = json_decode($apiRes,1);

            return json_encode(["error"=>0,"msg"=>$apiResArr]);
        }

    }


    //查看为花费的utxo
    public function MListUnSpent(Request $request)
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");


        $checkRes = $this->MVerify($request);
        $checkArray = json_decode($checkRes,1);
        if($checkArray['error']){
            return $checkRes;
        }else {
            $param = $request->input('addr');

            $curlOperate = new CurlOperate();
            $response = $curlOperate->ListUnSpent($param);

            $res = json_decode($response,1)['result'];
            if(empty($res)){
                return json_encode(['error'=>1,'msg'=>'ListUnSpent is empty']);
            }

            return json_encode(['error'=>0,'msg'=>$res]);
        }

    }


    //getbalance
    public function MGetBalance(Request $request)
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        $checkRes = $this->MVerify($request);
        $checkArray = json_decode($checkRes,1);

        if($checkArray['error']){
            return $checkRes;
        }else{
            $param = $checkArray['msg']['addr'];

            $curlOperate = new CurlOperate();
            $curl = $curlOperate->GetAddressBalance($param);

            $arrRes = json_decode($curl,1);
            //判断请求是否发生错误
            if(array_key_exists('msg',$arrRes)){
                return json_encode(array('error'=>1,'msg'=>$arrRes['msg']));
            }

            $response = json_encode(['error'=>0,'msg'=>$arrRes['result']]);
            return $response;
        }

    }


    //getvotebyaddress
    public function MGetVoteByAddress(Request $request)
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        $checkRes = $this->MVerify($request);
        $checkArray = json_decode($checkRes,1);
        if($checkArray['error']){
            return $checkRes;
        }else{
            $param = $checkArray['msg']['addr'];


            $curlOperate = new CurlOperate();

            $curl = $curlOperate->ListVotedDelegates($param);

            $arrRes = json_decode($curl,1);

            //判断请求是否发生错误
            if(array_key_exists('msg',$arrRes)){
                return json_encode(array('error'=>1,'msg'=>$arrRes['msg']));
            }

            $response = json_encode(['error'=>0,"msg"=>$arrRes['result']]);

            return $response;
        }
    }


    //getvotersbywitness
    public function MGetVotersByWitness(Request $request)
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        $checkRes = $this->MVerify($request);
        $checkArray = json_decode($checkRes,1);
        if($checkArray['error']){
            return $checkRes;
        }else{

            $name = $request->input('name');

            $param = $checkArray['msg']['addr'];

            $curlOperate = new CurlOperate();

            $curl = $curlOperate->ListReceivedVotes($name);

            $arrRes = json_decode($curl,1);

            //判断请求是否发生错误
            if(array_key_exists('msg',$arrRes)){
                return json_encode(array('error'=>1,'msg'=>$arrRes['msg']));
            }


            $response = json_encode(['error'=>0,'msg'=>$arrRes['result']]);


            return $response;
        }
    }


    //tx
    public function MGetTxByAddr(Request $request)
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        $checkRes = $this->MVerify($request,50,1);
        $checkArray = json_decode($checkRes,1);
        if($checkArray['error']){
            return $checkRes;
        }else{
            $param = $checkArray['msg']['addr'];

            $start = $request->input('start');
            $end = $request->input('end');

            $curlOperate = new CurlOperate();

            $curl = $curlOperate->GetTxByAddr($param,$start,$end);

            $arrRes = json_decode($curl,1);

            //判断请求是否发生错误
            if(array_key_exists('msg',$arrRes)){
                return json_encode(array('error'=>1,'msg'=>$arrRes['msg']));
            }


            $response = json_encode(['error'=>0,'msg'=>$arrRes['result']]);


            return $response;
        }
    }


    //blockcount
    public function MGetBlockCount(Request $request)
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        $checkRes = $this->MVerify($request,50,1);
        $checkArray = json_decode($checkRes,1);
        if($checkArray['error']){
            return $checkRes;
        }else{
            $curlOperate = new CurlOperate();

            $curl = $curlOperate->GetBlockCount();

            return json_encode(['error'=>0,'msg'=>json_decode($curl,1)['result']]);
        }

    }


    //sendrawtransaction
    public function MSendRawTransaction(Request $request)
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        if (empty($request->input('param'))){
            return json_encode(array('error'=>1,'msg'=>'Input Param Empty!'));
        }

        $checkRes = $this->MVerify($request);
        $checkArray = json_decode($checkRes,1);
        if($checkArray['error']){
            return $checkRes;
        }else {
            $param = trim($request->input('param'));

            $curlOperate = new CurlOperate();

            $response = $curlOperate->SendRawTransaction($param);

            return json_encode(['error'=>0,'msg'=>json_decode($response,1)['result']]);
        }
    }


    //获取app版本号
    public function MGetVersion()
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        $redis = new RedisOperate();

        $ver = $redis->RedisGet('app-ver');
        $flag = $redis->RedisGet('app-flag');

        $res = ['version'=>$ver,"flag" => $flag];
        return json_encode($res);
    }


    //设置app版本号
    public function MSetVersion(Request $request)
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        $ver = $request->input('v',"1.0");
        $flag = $request->input('f',"0");
        $token = $request->input('t');

        if($token == 'lbtctozhemoon'){
            $redis = new RedisOperate();
            $redis->RedisSet("app-ver",$ver);
            $redis->RedisSet("app-flag",$flag);
        }else{
            return json_encode(['error'=>1,'msg'=>'token is error']);
        }

    }


    //获取新闻
    public function MGetNews()
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        $path = dirname(dirname(__DIR__))."/Commons/news.ini";
        $res = parse_ini_file($path,1);

        if($res){
            $resArray = array();
            foreach ($res as $val){
                $resArray[] = $val;
            }
            return json_encode(array('error'=>0,'msg'=>$resArray));
        }else{
            return json_encode(array('error'=>1,'msg'=>'no content'));
        }
    }

    //获取新闻新街口MGetNews2
    public function MGetNews2(Request $request)
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        $page = $request->input('page',1);
        $count = $request->input('count',10);
        $tag = $request->input('tag');
        $cate = $request->input('cate','xwzx');


        //http://47.75.150.5:8082/api/get_posts?category_name=xwzx
        $url = "http://172.31.239.243:8082/api/get_posts?";

        if($tag){
            try{
                $res = file_get_contents($url.'category_name='.$cate.'&tag='.$tag.'&count='.$count.'&page='.$page);
            }catch (\Exception $e){
                return json_encode(array('error'=>1,'msg'=>'tag no exits'));
            }
        }else{
            try{
                $res = file_get_contents($url.'category_name='.$cate.'&count='.$count.'&page='.$page);
            }catch (\Exception $e){
                return json_encode(array('error'=>1,'msg'=>'tag no exits'));
            }
        }


        $resTemp = json_decode($res,1)["posts"];
        if($resTemp){
            $resArray = array();
            foreach ($resTemp as $val){
                $resArray[] = $val;
            }
            return json_encode(array('error'=>0,'msg'=>$resArray));
        }else{
            return json_encode(array('error'=>1,'msg'=>'no content'));
        }
    }


    //生成token用于测试
    public function GenerateToken(Request $request)
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");

        $addr = $request->input('addr');
        $timeFlag = time();
        $tokenFlag = 'lbtctozhemoon-'.$addr.'-';
        $token = md5($tokenFlag.$timeFlag);
        return json_encode(['token' => $token]);
    }





    /**
     * 用于测试接口
     */
    //添加敏感词
    public function AddWords(Request $request)
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");
        $param = $request->input('param');

        if(empty($param)){
            return json_encode(array('error'=>1,'msg'=>'Badword Empty!'));
        }

        $pwd = $request->input('pwd');

        if(empty($pwd)){
            return json_encode(array('error'=>1,'msg'=>'PassWord Empty!'));
        }

        $pwd = trim($pwd);

        $redis = new RedisOperate();

        $redisPwd = $redis->RedisGet('badwords-pwd');
        if($redisPwd){
            if(md5($pwd.'-facai') == $redisPwd){
                $param = trim($param);

                $redis = new RedisOperate();

                $key = 'badwords';

                $badArray = $redis->RedisLRange($key);
                if(in_array($param,$badArray)){
                    return json_encode(array('error'=>1,'msg'=>'Add badword already exists!'));
                }else{
                    $res = $redis->RedisLPush($key,$param);
                    if($res){
                        return json_encode(array('error'=>0,'msg'=>'Add badword OK!'));
                    }else{
                        return json_encode(array('error'=>1,'msg'=>'Add badword Fail!'));
                    }
                }
            }else{
                return json_encode(array('error'=>1,'msg'=>'Password is error!'));
            }
        }else{
            //Password is not set
            return json_encode(array('error'=>1,'msg'=>'Password is not set!'));
        }
    }

    public function TestApi(Request $request)
    {
        $bads = array(
            "诈骗","DAF","融资","私募","闪电信仰群","市值前五","比例","三位数","预挖","bitifinex","日本房产","ICO","合规","GJ","交易所","跑路","退币","喊单","点付猪头","割韭菜","虚假宣传","小密圈","知识星球","YouLive","旅行币","UC","张银海","zhangyinhai","zyh","维权","洗黑钱","割肉","砸盘","套住","亏成翔","敌敌畏","嫩模","骗子","逍遥法外","传销","腰斩","庄家","接盘","装死","发泄","山寨","抄袭",'考前答案','万科','家宝','辛灏年','陈胜','紧掏','紧淘','锦淘','锦掏','紧套','藏独','soufun','搜房','139116797372','学生静坐','操你','傻逼','人体炸弹','温家保','炸药','代考','温家堡','造反','共产党','温总','恽小华','黄疽','胡进套','温家饱','黄JU','HUANG菊','HUANGJU','huang菊','黄ju','huangju','绝食','静坐','声援','请愿','八九六','八九','观音法门','升达','郭玉闪','成杰','余辉','车殿光','秦高潮','王克勤','张振刚','董昕','王学永','李宇静','褚玉光','刘志华','宗顺留','庄公惠','朱振中','朱兆良','朱增泉','朱永新','朱相远','朱文泉','朱启','朱佩玲','朱培康','朱铭','朱丽兰','周子玉','周正庆','周玉清','周永康','周宜兴','周小川','周铁农','周绍熹','周坤仁','周伯华','周　济','仲兆隆','钟起煌','征鹏','赵展岳','赵勇','赵燕','赵喜明','赵龙','赵乐际','赵金铎','赵地','章祥荪','张左己','张中伟','张志坚','张芝庭','张云川','张毓茂','张佑才','张永珍','张学忠','张学东','张绪武','张新时','张肖','张吾乐','张文岳','张维庆','张廷翰','张涛','张思卿','张圣坤','张榕明','张庆黎','张洽','张平','张美兰','张梅颖','张龙俊','张立昌','张克辉','张俊九','张继禹','张怀西','张宏伟','张国祥','张工','张耕','张高丽','张帆','张发强','张定发','张德邻','张德江','张大宁','张春贤','张承芬','张宝文','张宝顺','张宝明','张柏林','翟泰丰','扎汗·俄马尔','曾荫权','曾宪梓','曾培炎','曾华','袁行霈','袁驷','袁汉民','喻林祥','俞正声','俞正','俞泽猷','俞云波','余国春','于珍','于幼军','于均波','尤仁','一诚','叶小文','叶少兰','叶如棠','叶青','叶连松','叶朗','叶大年','姚志彬','姚湘成','姚守拙','杨振杰','杨永良','杨兴富','杨孙西','杨岐','杨俊文','杨景宇','杨晶','杨国庆','杨国梁','杨德清','杨春兴','杨崇汇','杨长槐','杨邦杰','杨柏龄','阳安江','阎洪臣','严义埙','许仲林','许智宏','许志琴','许永跃','许克敏','许嘉璐','许柏年','徐自强','徐志纯','徐至展','徐展堂','徐永清','徐荣凯','徐麟祥','徐匡迪','徐鸿道','徐光春','徐冠华','徐更生','徐才厚','邢世忠','邢军','信春鹰','谢佑卿','谢生林','谢丽娟','谢克昌','萧灼基','向巴平措','夏赞忠','夏培度','夏家骏','习近平','西纳','武连元','伍增荣','伍淑清','伍绍祖','吴祖强','吴正德','吴贻弓','吴新涛','吴蔚然','吴双战','吴润忠','吴明熹','吴敬琏','吴基传','吴国祯','吴光正','吴光宇','吴冠中','吴德馨','吴爱英','乌云其木格','乌日图','魏复盛','卫留成','韦家能','王佐书','王祖训','王忠禹','王兆国','王占','王云龙','王云坤','王永炎','王英凡','王以铭','王耀华','王学萍','王旭东','王先琼','王维城','王涛','王太岚','王宋大','王生铁','王少阶','王忍之','王全书','王钦敏','王岐山','王宁生','王明明','王珉','王梦奎','王蒙','王梅祥','王茂润','王茂林','王良漙','王立平','王力平','王乐泉','王巨禄','王金山','王建民','王怀远','王鸿举','王恒丰','王鹤龄','王国发','王广宪','王光谦','王刚','王东明','王东江','王东','王大中','王选','汪啸风','汪恕诚','汪纪戎','汪光焘','万学远','万学文','万钢','万鄂湘','瓦哈甫·苏来曼','图道多吉','童傅','田玉科','田期玉','田岚','田成平','陶伯钧','汤洪高','索丽生','孙优贤','孙英','孙晓群','孙文盛','孙淑义','孙金龙','孙家正','孙淦','隋明太','苏荣','苏纪兰','宋秀岩','宋瑞祥','宋平顺','宋金升','宋宝瑞','舒圣佑','舒惠国','石宗源','石秀诗','石万鹏','石四箴','石广生','盛华仁','圣辉','沈辛荪','沈春耀','邵奇惠','邵华泽','邵鸿','任玉岭','任文燕','任启兴','任茂东','任克礼','曲钦岳','秦玉琴','乔晓阳','乔清晨','钱运录','钱景仁','启功','齐续春','彭钊','彭小枫','庞丽娟','潘霞','潘贵玉','潘蓓蕾','帕巴拉·格列朗杰','欧阳明高','钮茂生','倪岳峰','倪润峰','倪国熙','南振中','墨文川','莫时仁','闵智亭','闵乃本','孟建柱','毛增华','毛如柏','买买提明·阿不都热依木','马忠臣','马志伟','马永伟','马万祺','马庆生','马启智','马凯','罗清泉','罗豪才','栾恩杰','吕祖善','路甬祥','路明','陆锡蕾','陆浩','陆兵','卢展工','卢瑞华','卢荣景','卢强','卢光琇','卢登华','卢邦正','柳斌','刘仲藜','刘忠德','刘志忠','刘志军','刘政奎','刘镇武','刘振伟','刘泽民','刘云山','刘元仁','刘永好','刘迎龙','刘应明','刘亦铭','刘延东','刘廷焕','刘书田','刘胜玉','刘绍先','刘璞','刘明祖','刘民复','刘积斌','刘珩','刘鹤章','刘汉铨','刘光复','刘冬冬','刘大响','刘炳森','刘柏年','刘淇','令狐安','林兆枢','林文漪','林强','列确','廖锡龙','廖晖','梁振英','梁荣欣','梁绮萍','梁金泉','梁国扬','梁光烈','厉有为','厉以宁','厉无畏','李重庵','李至伦','李肇星','李兆焯','李泽钜','李源潮','李元正','李勇武','李雅芳','李学举','李新良','李铁映','李树文','李世济','李盛霖','李慎明','李乾元','李奇生','李其炎','李明豫','李敏宽','李良辉','李连宁','李金明','李金华','李建国','李继耐','李慧珍','李贵鲜','李赣骝','李德洙','李从军','李慈君','李春亭','李承淑','李成玉','李昌鉴','李宝祥','李蒙','黎乐民','冷溶','雷鸣球','雷蕾','孔小均','克尤木·巴吾东','靖志远','靳尚谊','金异','金日光','金人庆','金鲁贤','金烈','金开诚','金基鹏','金炳华','蒋正华','蒋以任','蒋树声','姜颖','姜笑琴','姜恩柱','贾志杰','贾军','季允石','吉佩定','霍英东','霍达','黄智权','黄小晶','黄孟复','黄丽满','黄康生','黄璜','黄华华','黄光汉','黄关从','黄格胜','胡彦林','胡贤生','胡康生','胡光宝','胡富国','胡德平','胡彪','侯义斌','洪绂曾','贺一诚','贺旻','贺铿','何柱国','何晔晖','何添发','何鲁丽','何厚铧','何鸿燊','何椿霖','郝建秀','韩忠朝','韩正','韩寓群','韩喜凯','韩生贵','韩汝琦','韩启德','韩大建','郭廷标','郭树言','郭金龙','郭凤莲','郭东坡','郭伯雄','郭炳湘','桂世镛','顾秀莲','谷建芬','苟建丽','龚学平','龚世萍','龚谷成','高占祥','高强','高洪','高国才','甘子钊','甘宇平','傅志寰','傅铁山','傅杰','傅家祥','傅惠民','符廷贵','奉恒高','冯培恩','冯健亲','方祖岐','方兆祥','方兆本','方新','范徐丽泰','范长龙','范宝俊','多吉才让','杜宜瑾','杜铁环','杜青林','窦瑞华','董建华','丁石孙','丁人林','丁光训','邓伟志','邓朴方','邓成城','邓昌友','德哇仓','刀述仁','刀美兰','戴证良','戴相龙','丛斌','储波','程誌青','程贻举','程津培','程安东','陈宗兴','陈政立','陈章良','陈永棋','陈益群','陈宜瑜','陈耀邦','陈勋儒','陈心昭','陈士能','陈绍基','陈清泰','陈清华','陈难先','陈明德','陈凌孚','陈良宇','陈奎元','陈抗甫','陈俊亮','陈军','陈建生','陈建国','陈佳贵','陈佳洱','陈辉光','陈虹','陈昊苏','陈广元','陈广文','陈高华','陈德铭','陈德敏','陈昌智','陈炳德','陈邦柱','曹圣洁','曹其真','曹刚川','曹伯纯','薄熙来','包叙定','白立忱','白克明','白恩培','巴金','安启元','艾丕善','出售答案','邵长良','阴道被捅','做爱挑逗','奶头真红','粉嫩小洞','放入春药','惊暴双乳','性交大赛','费良勇','刘西峰','李新德','浦志强','郭飞熊','郭飞雄','杨茂东','李大同','袁伟时','施亮','安魂网','杜湘成','杨文武','许万平','夏逸陶','杨斌','唐淳风','卢雪松','胡绩伟','朱成虎','退党','游行','真善忍','江棋生','王通智','孙连桂','伊济源','赵铁斌','吴镇南','王维民','邓可人','宗凤鸣','红魂网站','法会','茳澤民','回良玉','王传平','徐金芝','昝爱宗','刘晓竹','夏春荣','杜导斌','张先玲','丁家班','何家栋','焦国标','集体上访','阴茎','阴唇','肉穴','肉洞','骚穴','肉棍','鸡巴','骚逼','毛片','孟令伟','蔣彥永','同胞书','蒋彦永','吴仪','民主墙','张博涵','六四','共军','共匪','共党','河殇','贝领','暴政','高自联','反共','多维','民主潮','民运','疆独','达赖','澤民','江泽民','朱镕基','李鹏','吴官正','罗干','李长春','黄菊','曾庆红','贾庆林','吴邦国','胡锦涛','苏晓康','李旺阳','李禄','严家其','温元凯','万润南','江公子','江锦恒','洪吟','张京生','方励之','刘晓波','张祖桦','侯杰','吕加平','任畹町','张晓平','杨子立','王建军','周国强','陈子明','方觉','马强','何德普','王美茹','华惠棋','刘凤钢','刘军宁','陈小雅','谢小庆','章虹','鲍彤','鲍筒','丁子霖','肖爱玲','傅怡彬','魏京生','王丹','何清涟','华岳','李志绥','柴玲','暴乱','陶驷驹','江责民','法輪','转法轮','法轮','李大师','李宏治','李宏志','明Hui','慧网','明慧','府谷','骚乱','拉萨事件','310事件','马明哲','关六如','周鸿陵','李伙田','李汉田','李秋田','钟志根','李连玉','邳州','段桂青','段桂清','死亡笔记','梁定邦','清明桥','令计划','覃志刚','艾仰华','陈晓铭','周天勇','动乱','胡耀邦','大纪元','fuck','大法','自焚','台独','文化大革命','文革','学运','学联','学潮','天葬','一中一台','镇压','两个中国','法轮功','法轮大法好','985660286','85863252','万科即将宣布破产','多处财产已被银行查封','南京站编辑部副主编','万科即将破产','破产','010-86515990','前列腺治疗','yanjiaoshequ','交而不泄','先肾后心','69','69式','9浅1深','BJ','Blow Job','CAR SEX','G点','G点高潮','KJ','Y染色体','姦淫','屄','屄缝','師母','房中','慕男症','情欲结','上下其手','手淫危害论','after-play','性爱派对','阿姨','爱抚','爱侣','爱女','爱慰','爱液','爱欲','安抚','安全期避孕法','按揉','按柔','按压','暗红','昂奋','傲然挺立','扒开','巴氏腺','拔出','把玩','爸 爸','爸爸','白带恶臭','白里透红','白嫩','白塞氏病','白浊','摆布','摆动','摆弄','摆脱','扳开','伴侣','半裸半露','半骚半软','半遮半露','膀胱','膀胱三角','膀胱阴道瘘','膀胱肿瘤','棒','棒棒','蚌唇','胞漏疮','包覆','包茎','包皮','包皮龟头炎','包皮过长','包皮环切手术','包皮环切术','包皮嵌顿','包皮腔','包皮系带','包皮系带撕裂','包皮炎','剥开','保持','保精','保育细胞','饱胀','宝贝','抱抱','抱紧','抱着','抱坐','暴露','暴涨','暴胀','爆射','背飞凫','贝肉','逼','逼里','逼迫','闭经','壁肉','臂部','扁平湿疣','变粗','变得','变软','变硬','标准','表弟','表哥','表姐','表妹','表嫂','表现','表兄','表姊','病毒性睾丸炎','并睾','播散性淋菌感染','拨开','拨开阴毛','拨弄','勃发','勃起','勃起功能障碍ED','伯父','伯母','泊泊','不洁性交','不举','不泄','不育','不孕','不孕症','擦拭','采精','采阴补阳','苍白螺旋体','操','操逼','操弄','操起','操死','操我','操穴','侧臀','插','插爆','插进','插进插出','插奶','插弄','插入','插死你','插送','插她','插穴','叉开','叉我','缠抱','缠绵','颤动','颤抖','场合','场景','长','长驱直入','长腿','长兄','肠壁','肠梨形鞭毛虫病','肠源性紫绀','潮红','潮湿','撑爆','撑破','撑涨','撑胀感','持续','耻部','耻骨尾骨肌','耻骨直肠肌','耻毛','耻丘','赤裸','赤裸裸','炽热','充血','冲插','冲刺','抽','抽擦','抽插','抽出','抽搐','抽打','抽捣','抽动','抽离','抽了','抽弄','抽送','抽送着','抽缩','初血','初夜','出水','出血','出血性膀胱炎','矗立','触动','触摸','触碰','触淫','处男','处女','穿插','传染性软疣','传统','喘叫','床事','床戏','吹弹欲破','吹萧','垂软','春洞','春宫','春情','春心','春药','唇瓣','唇缝','唇间','唇片','唇肉','唇舌','纯熟','蠢蠢欲动','戳穿','戳入','戳穴','雌二醇','雌激素','雌性激素','慈母','刺插','刺激','次数','丛毛','粗暴','粗长','粗粗','粗大','粗大的玩意儿','粗黑','粗红','粗鲁','粗硬','粗涨','粗壮','促使','窜动','摧残','催情','搓','搓蹭','搓捏','搓弄','搓揉','搓柔','搓玩','搓著','搓着','打飞机','打炮','打泡','打手枪','大波','大抽','大哥','大姐','大妈','大妹','大奶','大奶头','大娘','大肉','大乳','大嫂','大叔','大腿','大泄','大穴','大爷','大姨','大姊','带状沟','单调','蛋','蛋蛋','蛋子','荡妇','荡叫','荡声','档部','倒骑','导致','得到','登床','低潮期','低嚎','滴虫性阴道炎','滴出','底裤','地点','第二性征','第四性病','第五性病','弟 弟','弟弟','弟妇','颠鸾倒凤','叼住','调逗','调经','调情','调戏','调整','爹爹','叠股','顶紧','顶进','顶弄','顶破','顶送','顶体蛋白酶','顶体酶','顶体膜','顶体素','顶我','顶住','丢了','动情区','动欲区','动作','洞洞','洞开','洞口','洞穴','洞眼','抖颤','逗弄','独生女','肚脐','短粗','对方','多毛','多囊卵巢综合征','多情','多肉','多少次高潮','多汁','多姿','多睾','恶露','儿媳妇','耳垂','耳磨鬓擦','二期梅毒','发颤','发春','发抖','发浪','发麻','发情','发热','发骚','发丝','发泄','发痒','发展','发涨','翻动','翻搅','翻弄','芳香','方法','方式','房事','房事昏厥症','房室伤','房中七经','房中术','房中之术','放荡','放在','非淋菌性尿道炎','飞溅','飞燕','肥大','肥美','肥奶','肥翘','肥乳','肥润','肥臀','肥穴','肥尻','分泌','分身','焚身','粉 颊','粉 嫩','粉白','粉臂','粉额','粉汗微出','粉红','粉红阴唇','粉颊','粉嫩','粉乳','粉腮','粉舌','粉头','粉腿','粉臀','粉腰','丰肥','丰隆','丰乳','丰硕','丰臀','丰腴','风流','风骚','夫妻','伏在','服囊肉膜','抚爱','抚摸','抚模','抚摩','抚捏','抚弄','抚揉','抚玩','抚慰','抚著','抚着','俯弄','副性腺炎','父亲','腹股沟管','腹股沟淋巴结肿大','腹股沟淋巴肉芽肿','腹股沟肉芽肿','腹股沟疝','富有','附 睾','附件炎','附属性腺','附性腺分泌液','附睾','附睾管','附睾结核','附睾丸','附睾小叶','附睾炎','附睾液','妇方','妇人','改善','干过炮','缸交','肛部','肛管','肛管内括约肌','肛管外括约肌','肛管直肠环','肛交','肛门','肛门交','肛肉','肛乳头炎','肛尾韧带','肛腺','肛柱','肛窦炎','高潮','高亢','高耸','高挺','高凸','高胀','膏淋','哥 哥','哥哥','革兰氏阳性细菌','根 部','根部','根插','根毛','供精','公公','宫颈','宫颈癌','宫颈管内膜刮取术','宫颈管型癌症','宫颈扩张','宫颈鳞状上皮','宫颈糜烂','宫颈外口','宫颈息肉','宫颈腺癌','宫颈炎','宫颈阴道段','宫颈粘液','宫颈粘液观察法','宫颈肿瘤','宫颈锥切术','宫口','宫内避孕器','宫内膜炎','宫旁组织','宫腔','宫腔粘连','宫外孕','共浴','沟缝','狗交','狗爬','够骚','箍住','姑姑','姑妈','姑母','姑爷','鼓胀','骨感','骨盆','骨盆腔','股沟','刮宫','刮官','乖巧','乖肉','冠状沟','观淫癖','光洁无毛','光溜溜','光裸','光脱脱','广东疮','龟 头','龟腾','龟头','龟头固定药疹','龟头冠状沟','龟头结核疹','龟头炎','龟头珍珠垢','鬼交','跪骑于','跪臀位','跪姿','滚动','滚热','滚烫','滚圆','裹住','裹着','过程','过度','含','含春','含弄','含乳','含入','含吮','含咬','含住','含着','豪乳','豪乳型','好棒','好爽','好性','耗精伤气','呵痒','和谐','合拢','合适','合体','黑洞','黑黑的阴毛','黑毛','黑色的阴毛','狠插','狠干','横冲直撞','红唇','红颊','红润','喉交','侯龙涛','后妈','后母','后入位','后庭','后庭花','后戏','呼呼','壶腹部','花瓣','花苞','花唇','花蕾','花蜜','花蕊','花芯','花心','花穴','滑出','滑到','滑动','滑抚','滑进','滑溜','滑美','滑嫩','滑入','滑润','滑湿','滑爽','滑顺','滑下','滑向','滑粘','欢爱','欢吟','欢愉','欢悦','环境','缓进速出','缓慢','唤起','黄体生成素','黄体酮','秽疮','秽物','会阴','会阴部肌肉群','会阴浅横肌','会阴浅隙','会阴深横肌','会阴深隙','会阴中心腱','魂飞魄散','浑圆','混圆','活塞','火辣','火热','火热鸡巴','火柱','获得','击打','肌肉','饥渴','激发性地区','激烈的性交','激情','激射','激素','鸡吧','鸡巴顶住','鸡把','鸡鸡','鸡奸','急抽','急喘','急性女阴溃疡','急性输卵管炎','急性外阴炎','挤捏','挤压','技巧','悸动','继父','夹紧','夹著','夹住','夹着','加长','假性湿疣','假装','坚实','坚挺','坚挺的东西','坚硬','尖叫','尖锐湿疣','尖挺','尖硬','间质部','间质细胞','间质细胞刺激素','奸插','奸弄','奸虐','奸辱','奸尸','奸我','奸淫','减少','将嘴套至','浆汁','降低','椒乳','交缠','交合','交欢','交颈','交配','交融','交媾','交媾素','骄躯','骄穴','娇喘','娇哼','娇呼','娇叫','娇媚','娇嫩','娇娘','娇躯','娇容','娇软','娇弱','娇声','娇态','娇啼','娇小','娇笑','娇艳','娇吟','搅弄','脚交','较好','叫床','叫声','接触','接吻','洁阴法','结缔组织','解带','解开','解衣','解脲脲原体','姐 姐','姐夫','姐姐','筋肉','津液','紧合','紧夹','紧靠','紧贴','紧握','紧小','紧咬','紧窄','紧抓','进程','进入','浸润','浸润癌','浸湿','浸淫','尽根','茎底','茎头','精巢','精虫','精阜','精关失固','精浆','精满自溢','精门','精门开','精母','精母细胞','精囊','精囊良性肿瘤','精囊囊肿','精囊腺','精囊炎','精失固摄','精水','精索','精索静脉曲张','精索内筋膜','精索鞘韧带','精索外筋膜','精脱','精细胞','精液','精元','精原','精原核','精原细胞','精种','精子','精子鞭毛','精子成活率','精子抗原','精子膜','经期','经期紊乱','经前痤疮','经痛','经血','经血来潮','经验','经质粘稠','静香','境界','痉挛','痉脔','久战','九浅一深','舅父','舅舅','舅妈','菊孔','咀唇','举高双腿','巨棒','巨根','巨棍','巨炮','巨枪','巨乳','巨物','剧烈','撅起','撅着','绝经','俊逸','开苞','开发','揩擦','亢奋','亢进','渴望','啃咬','控制射精','抠摸','抠弄','抠挖','口爆','口唇','口含','口活儿','口交','口中','扣弄','跨跪','跨骑','跨坐','胯股','胯下','快 感','快感','快活','快乐','快意','狂暴','狂操','狂插','狂抽','狂捣','狂干','狂热','狂吻','狂泄','狂舐','窥探','困境','括约肌','括约肌间沟','来潮','来经','来搔抚','兰香','浪逼','浪喘','浪妇','浪哼','浪货','浪叫','浪劲','浪媚','浪女','浪态','浪穴','浪样','浪吟','浪语','老爸','老二','老姐','老套','姥姥','乐趣','蕾苞','类菌质体','冷阴症','理想','丽香','连炮几炮','恋母','恋人','恋童','两情相悦','两腿','两腿之间','撩拨','撩动','撩开','撩乱','撩弄','撩起','林醉','淋巴管','淋巴结','淋病','淋菌','淋漓','淋球菌','淋证','凌 辱','凌乱','凌辱','灵肉','令女人春心荡漾','令女性春心荡漾','流','流出','流到','流溢','龙根','隆起','搂抱','露出','露阴和窥阴','露阴癖','陆玄霜','卵巢','卵巢激素','卵巢囊肿','卵巢下垂','卵巢炎','卵蛋','卵黄曩','卵裂','卵蜜蛋','卵母细胞','卵泡','卵泡刺激素','卵泡期','卵泡液','卵细胞','卵原核','卵子','乱抽','乱蹬','乱顶','乱伦','乱摸','乱揉','乱淌','乱舔','轮暴','轮奸','裸背','裸露','裸女','裸躯','裸身','裸睡','裸体','裸体男女','裸臀','裸胸','裸着','妈 咪','妈咪','麻酥','麻酥酥','麻痒','马杀鸡','马眼','马子','埋进','蛮腰','满面潮红','满胀','满足','曼妙','慢性输卵管炎','肏','肏干','肏人','肏死','肏我','毛囊','毛茸茸','茂密','茂盛','冒水','梅毒螺旋体','梅毒疹','霉疮','霉菌性阴道炎','美唇','美妇','美感','美脚','美伶','美满','美目','美人','美肉','美乳','美体','美腿','美臀','美香','美穴','妹 妹','妹夫','妹妹','妹子','媚唇','媚功','媚力','媚娘','媚肉','媚术','媚态','媚笑','媚艳','媚液','闷哼','猛操','猛插','猛颤','猛冲','猛抽','猛喘','猛刺','猛干','猛搅','猛男','猛舔','猛挺','猛撞','梦交','梦失精','梦泄精','梦遗','迷情','秘贝','秘部','秘处','秘唇','秘洞','秘缝','秘肉','秘穴','泌出','泌尿生殖系统','泌尿系感染','泌尿系统','泌乳','蜜唇','蜜洞','蜜壶','蜜肉','蜜桃','蜜穴','蜜液','蜜意','蜜汁','密处','密洞','密合','密窥','密穴','密汁','绵软','免疫性不孕','妙目','敏感','敏感带','名器','命根','命根子','摸','摸到','摸鸡巴','摸抠','摸摸','摸捏','摸弄','摸揉','摸乳','摸他','摸玩','摸我','摸向','摸着','模式','磨擦','磨搽','磨搓','磨弄','磨穴','摩擦','摩肝益肾法','摩弄','拇指','母 亲','母亲','母痔区','那话','那话儿','奶房','奶尖','奶水','奶头','奶子','奶頭','男方','男方膝立位','男根','男跪女後','男欢女爱','男茎','男精女血','男女','男上式','男性','男性不育症','男性尿道唇','男性生殖器','男性外生殖器官','男子','内壁','内裤','内生殖器','内睾提肌','嫩白','嫩红','嫩脸','嫩嫩','嫩肉','嫩乳','嫩舌','嫩爽','嫩腿','嫩臀','嫩娃','嫩穴','腻滑','逆行射精','娘亲','尿胆素','尿道','尿道附属腺体','尿道海绵体','尿道结石','尿道口','尿道括约肌','尿道旁腺','尿道旁腺炎','尿道膨出','尿道球部','尿道球腺','尿道球腺炎','尿道肉阜','尿道上裂','尿道外口','尿道外括约肌','尿道狭窄','尿道下裂','尿道腺','尿道腺液','尿道炎','尿道嵴','尿道憩室','尿毒','尿毒症','尿后流白','尿路感染','尿路结石','尿末滴白','尿囊膜','尿频','尿生殖隔','尿生殖膈','尿生殖膈上盘膜','尿生膈下筋膜','尿水','尿痛','尿味','尿血','尿液','尿意','尿浊','捏','捏挤','捏揪','捏摸','捏捏','捏弄','捏掐','捏揉','扭动','扭捏','扭臀','扭腰','脓尿','浓稠','浓黑','浓精','浓密的阴毛','浓热','浓浊','弄','弄弄','弄破','弄湿','弄穴','怒张','怒涨','怒胀','女畅男欢','女儿','女方','女方跪臀位','女器','女前男后','女人','女人的BB','女上式','女上位','女士','女童','女臀','女卧男立式','女下','女下位','女性','女性不孕症','女性外生殖器','女性性功能障碍','女性性洁症','女性性冷淡','女婿','女阴','女优','女子性冷淡','女尻','虐待','排出','排过精','排精','排卵','排卵期','排卵日','排入','排射','排泄','抛浪','泡彦','泡浴','泡疹性外阴炎','培养','配偶','喷出','喷发','喷射','喷泄','喷涌','盆腔','盆腔放线菌病','盆腔腹膜','盆腔腹膜炎','盆腔炎','盆膈下筋膜','澎胀','膨大','膨涨','膨胀','碰触','疲软','皮角','屁 股','屁道','屁股','屁门','屁穴','屁眼','频度','频繁','频率','平滑','破处','破瓜','破坏','破身','破贞','迫进','葡萄胎','漆黑的阴毛','奇痒','奇淫','骑乘位','起性','器具','气氛','气淋','千变万化','乾妈','乾姊','前 戏','前列腺','前列腺癌','前列腺静脉','前列腺素','前列腺小囊','前列腺炎','前列腺液','前列腺增生','前庭大腺','前庭大腺炎','前庭球','前戏','潜欲','浅出浅入','浅会阴筋膜','嵌顿包茎','嵌顿性包茎','腔内','腔肉','强暴','强奸','强健','强精','强硬','强壮','巧春','鞘膜腔','翘起','翘臀','俏丽','俏脸','俏眼','切除子宫','亲','亲哥','亲匿','亲亲','亲热','亲吻','亲昵','青春期','轻按','轻颤','轻触','轻喘','轻搓','轻抚','轻撩','轻揉','轻松','轻舔','轻吻','轻握','轻咬','轻盈','轻舐','情动','情侣','情色','情穴','情欲','球海绵体肌','求欢','曲细精管','曲细精管发育不全','取悦','去操','去能因子','去吮','去舔','泉涌','全根','缺乏','燃烧','让女人春心荡漾','让女性春心荡漾','热滚','热烘烘','热乎乎','热浆','热辣辣','热淋','热情','热热','热烫','热吻','热穴','热胀','人类乳突病','忍精','妊娠','妊娠期','妊娠中断','绒毛状乳头状瘤','揉','揉擦','揉搓','揉动','揉抚','揉摸','揉磨','揉捏','揉弄','揉揉','揉抓','揉转','揉着','柔唇','柔滑','柔肌','柔毛','柔嫩','柔腻','柔软','柔弱','肉 缝','肉 棍','肉 体','肉 芽','肉~棒','肉瓣','肉棒','肉蚌','肉贝','肉壁','肉臂','肉搏','肉搏战','肉帛','肉肠','肉虫','肉唇','肉袋','肉弹','肉道','肉豆','肉缝','肉感','肉根','肉沟','肉冠','肉核','肉乎乎','肉壶','肉紧','肉茎','肉具','肉孔','肉粒','肉门','肉膜','肉腔','肉丘','肉球','肉圈','肉色','肉身','肉体','肉团','肉臀','肉香','肉芽','肉芽肿','肉牙儿','肉眼','肉欲','肉柱','肉嘟嘟','肉襞','蠕动','如醉如痴','乳 房','乳 头','乳癌','乳白色','乳波臀浪','乳部','乳蒂','乳儿','乳房','乳房癌','乳房外湿疹样癌','乳房腺体','乳房叶','乳峰','乳沟','乳核','乳尖','乳交','乳浪','乳蕾','乳糜尿','乳母','乳球','乳肉','乳首','乳水','乳头','乳头坏死','乳头瘤病毒','乳腺','乳腺癌','乳腺导管','乳腺疾病','乳腺炎','乳阴核','乳晕','乳渍','乳頭','入浴','软掉','软了','软毛','软绵','软绵绵','软肉','软软','软瘫','软瘫了','软下','软下疳','软性下疳','软玉温香','润滑','润湿','弱精子症','弱入强出','弱小','塞进','塞入','搔弄','搔痒','骚B','骚动','骚货','骚劲','骚浪','骚浪叫','骚浪样子','骚媚','骚女','骚情','骚热','骚声','骚水','骚味','骚痒','骚淫','骚幽','骚状','嫂嫂','嫂子','色色','色欲','杀精','擅','伤精','上床','上翘','上上下下','上位','上下蠕动','上压下顶','少妇','少精子症','舌 头','舌尖','舌头','射','射出','射到','射精','射精刺激阈','射精反射','射精管','射精疼痛','射精延迟','射精预感','射了','射向','射液','呻吟','伸入','身体','身无寸缕','身子','深插','深喉','深会阴筋膜','深入浅出','深吻','婶婶','肾结核','肾气不固症','肾上腺','肾盂','肾肿瘤','生精','生精细胞','生精小管','生母','生殖','生殖道','生殖道分泌物','生殖道支原体感染','生殖管','生殖管导腺体','生殖管道损伤','生殖器','生殖器滴虫病','生殖器官','生殖器疾病','生殖器念珠菌病','生殖器脓疱','生殖器损伤','生殖器粘膜','生殖器疱疹','生殖系统','生殖系炎症','生殖细胞','生殖腺','生殖支原体','师弟','师姐','师妹','师母','师兄','失败','失精','湿乎乎','湿滑','湿淋淋','湿热','湿热下注证','湿软','湿透','湿漉漉','湿濡','石淋','时机','时间','时刻','蚀骨','实臀','事前','势如破竹','手','手 淫','手淫','手淫史','手指','受精','受精卵','受孕','受孕力','受孕率','瘦小','兽奸','兽交','输精','输精管','输精管道','输精管壶腹','输精管壶腹液','输精管狭窄','输卵管','输卵管狭窄','输卵管炎','输尿管','输乳管','叔母','叔嫂','舒爽','竖立','竖起','竖直','双唇','双峰','双管齐下','双胯','双奶','双乳','双腿','双腿架到','双臀','双子宫','爽','爽滑','爽劲','爽快','爽死','爽透','水多','水淋淋','水乳交融','吮','吮了','吮奶','吮吻','吮吸','吮咬','吮著','吮着','吮舐','硕大','硕乳','硕壮','撕开','撕裂','撕破','私部','私处','死精','死精症','死精子症','松弛','松软','耸动','耸起','酥到','酥淋','酥麻','酥熔','酥乳','酥软','酥爽','酥酥','酥胸','酥痒','素女经','素燕','酸软','酸痒','随心所欲','孙女','锁精术','她的波','她的花蕊','她的阴部','她的阴核','胎膜早破','胎盘','贪淫','瘫软','探入','探索','堂哥','堂妹','堂嫂','烫热','滔滔不绝','套动','套紧','套弄','套上','提高','提前排卵','提枪','提升','提睾筋膜','体壁','体毛','体内','体内的阴茎','体味','体位','体香','体验','体液','替我','剃掉','天强','甜蜜','舔','舔遍','舔触','舔到','舔动','舔及轻击','舔净','舔了','舔掠','舔摸','舔奶','舔弄','舔起','舔乾','舔去','舔拭','舔吮','舔他','舔吻','舔我','舔我的阴部','舔吸','舔穴','舔寻','舔咬','舔阴','舔著','舔着','舔舐','挑拨','挑动','挑逗','挑弄','挑起','铁硬','停经','挺进','挺立','挺立的性器','挺起','挺实','挺腰','挺直','同性恋','童男','捅','捅进','捅了','痛快','偷汉','偷欢','偷窥','偷香','透明','突刺','徒弟','推揉','推送','推油','腿儿','腿缝','腿根','腿间','褪下','吞','吞入','吞食','臀 部','臀瓣','臀部','臀洞','臀缝','臀沟','臀股','臀尖','臀孔','臀丘','臀肉','臀腿','臀下','臀眼','臀後','脱光','脱裤','脱去','脱下','挖弄','外公','外流','外婆','外生殖器','外甥','外孙','外阴','外阴癌','外阴炎','外阴瘙痒','玩六九','玩摸','玩弄','完事','晚期流产','万艾可','旺盛','微隆','萎软','萎缩','伪装','温存','温热','温热感','温软','温湿','吻','吻遍','吻摸','吻向','稳定','我的花蕊','我的乳头','我的阴核','我射了','卧式','卧式性交','握缩感','无精子症','无毛','无排卵性月经','无月经','无睾症','五淋','五征五欲','舞奴','吸','吸功','吸吭','吸弄','吸入','吸舔','吸吻','吸咬','吸允','吸啜','吸聒','戏弄','细菌性阴道病','细嫩','细软','细小','细腰','峡部','下贱','下流','下身','下体','下阴','掀开','先摸','先射','鲜嫩','鲜润','咸咸','衔住','香唇','香滑','香肌','香肩','香津','香嫩','香软','香腮','香臀','香涎','香艳','想操','想舔','享受','销魂','消精亡阴','小逼','小便','小唇','小弟第','小弟弟','小洞','小缝','小核','小鸡鸡','小咀','小脸','小美','小屁眼','小巧玲珑','小乳','小舌','小腿','小雄','小穴','小腰','小姨','小嘴','挟紧双腿','泄出','泄洪','泄精','泄了','泄射','泄身','泄欲','泻','泻出','泻了','欣喜若狂','新婚','新婚多虚','新郎','新娘','心情','心痒','心因性阳萎','兴奋','兴奋剂','兴趣','兴致勃勃','行房','行事','行淫','幸福','性 器','性 欲','性爱','性爱后','性爱技巧','性爱冷感症','性爱模式','性爱前','性爱时间','性病恐惧症','性病淋巴肉芽肿','性传播疾病','性传播疾病(STD)','性奋','性感','性感带','性感集中按摩术','性感区','性高潮','性功能障碍','性唤起障碍','性黄金','性交','性交不适(性交痛','性交方式','性交后','性交昏厥','性交恐惧症','性交前','性交时间','性交姿势','性冷感','性力','性脉博','性脉搏','性虐','性器','性器官','性侵犯','性情','性染色体','性生活','性生活方式','性生活时间','性生活障碍','性事','性妄想症','性戏','性腺','性腺激素细胞','性腺器官','性心理障碍','性信号','性行为','性欲','性招式','凶猛','胸部','胸脯','胸推','胸型','胸罩','雄壮','修长','羞态','秀媚','秀挺','虚脱','蓄精','宣泄','宣淫','旋转','学姐','学妹','穴壁','穴唇儿','穴道','穴洞','穴缝','穴门','穴肉','穴水','穴心','穴裡','穴穴','穴眼','穴痒','雪白','雪颈','雪臀','血精','血性精液','血睾','血睾屏障','压进','压入','鸭嘴器','牙印和掐痕','雅莉','延长','延迟','艳妇','艳丽','艳肉','艳臀','杨梅疮','羊膜囊','羊膜破裂','羊水','阳 具','阳峰','阳根','阳茎','阳精','阳精必薄','阳具','阳事渐衰','阳水','阳萎','阳物','阳痿','阳痿患者','仰卧','养父','养精','养母','养女','腰腹','腰际','腰臀','腰枝','腰肢','妖媚','妖艳','妖淫','咬扯','爷爷','野合','冶荡','腋尾','夜御数女','液体','一泻千里','衣原体','遗精','移向','姨妈','姨妹','姨丈','艺术','意淫','义父','益精','溢精','溢乳','异乎寻常','异位妊娠','异性','荫茎','殷红','阴□','阴壁','阴埠','阴部','阴部内动脉','阴部内静脉','阴部赘生物','阴部疱疹','阴唇系带','阴道','阴道壁','阴道病','阴道池','阴道滴虫','阴道高潮','阴道好紧','阴道毛滴虫病','阴道念珠菌病','阴道损伤','阴道粘膜','阴道纵膈','阴道穹窿','阴道穹窿部','阴蒂','阴蒂高潮','阴蒂垢','阴蒂海绵体','阴蒂脚','阴蒂体','阴蒂头','阴蒂系带','阴蒂炎','阴蒂肿瘤','阴洞','阴缝','阴阜','阴沟','阴垢','阴核','阴户','阴茎癌','阴茎勃起功能障碍','阴茎的静脉','阴茎的血液供应','阴茎冠状沟','阴茎海绵体','阴茎筋膜','阴茎颈','阴茎浅筋膜','阴茎丘疹','阴茎体','阴茎头','阴茎头包皮炎','阴茎纤维性海绵体','阴茎炎','阴茎异常勃起','阴茎硬结症','阴茎折断','阴茎珍珠样丘疹病','阴精','阴径','阴亏血燥证','阴毛','阴毛很浓','阴毛交织','阴门','阴囊','阴囊肉膜','阴囊神经性皮炎','阴囊湿疹','阴囊窦道','阴肉','阴虱病','阴水','阴庭','阴虚火亢症','阴穴','阴液','淫 荡','淫 靡','淫 液','淫~靡','淫棒','淫唇','淫荡','淫洞','淫妇','淫哥','淫根','淫棍','淫果','淫合','淫花','淫秽','淫火','淫贱','淫溅','淫叫','淫津','淫精','淫具','淫浪','淫乱','淫乱的声音','淫毛','淫媚','淫靡','淫糜','淫母','淫念','淫女','淫虐','淫腔','淫情','淫人','淫肉','淫乳','淫骚','淫色','淫舌','淫神','淫声','淫事','淫兽','淫水','淫态','淫态毕露','淫汤','淫臀','淫娃','淫物','淫香','淫笑','淫邪','淫心','淫兴','淫性','淫嗅味','淫穴','淫血','淫言','淫艳','淫宴','淫痒','淫液','淫逸','淫友','淫语','淫欲','淫汁','淫挚','淫纵','淫嘴','淫狎','淫猥','引逗','引诱','隐睾','隐睾症','樱唇','樱口','樱口之技','樱口之枝','应召','营造','迎合','盈满','硬邦邦','硬梆梆','硬绑绑','硬茎','硬立','硬热','硬挺','硬物','硬下疳','硬硬','硬涨','硬胀','拥抱','拥吻','涌出','涌泉','涌入','涌向','勇猛','用力','用力一顶','用药','幽洞','幽户','优香','油黑','游动','游移','有力','有舒有缓','右乳','右臀','诱惑','又稠又粘','又粗又短','又肥又厚','又美又嫩','又细又嫩','又咬又舔又吸','又肿又大','幼嫩','幼稚型子宫','鱼比目','鱼水','玉 腿','玉棒','玉背','玉臂','玉齿','玉洞','玉房','玉峰','玉缝','玉肤','玉棍','玉户','玉肌','玉浆','玉脚','玉茎','玉颈','玉娟','玉面','玉娘','玉卿','玉乳','玉蕊','玉体','玉腿','玉臀','玉穴','玉液','玉液般','玉指','玉柱','玉麈','愈插愈快','欲感','欲火','欲望','欲焰','浴室','圆粗','圆鼓鼓','圆滚','圆翘','圆润','圆臀','岳父','岳母','月经','月经不调','月经初潮','月经失调','月经紊乱','月经异常','月经周期','允吸','韵律','孕激素','孕卵','孕酮','脏病','早泄','造爱','增粗','增加','窄窄','粘稠','粘乎乎','粘滑','粘膜','粘液','展露','站立','站立式性交','站位性交','张合','张开红唇','张开了嘴','张开双唇','张开双腿','张开小嘴','张开樱唇','掌握','丈夫','丈母','丈母娘','胀大','胀得难受','胀红','胀破','胀疼','胀硬','胀胀','障碍','珍珠状阴茎丘疹','真琴','阵阵快感','整根','整根阴茎','肢体','直插','直肠','直肠瓣','直肠壶腹','直肠阴道瘘','直肠柱','直精小管','直挺挺','植物性神经','侄儿','侄女','侄子','指技','指头','稚嫩','质量','痔内静脉丛','痔外静脉丛','治荡','中断排尿','肿涨','肿胀的东西','重视','朱唇','主动','抓捏','抓弄','抓揉','抓住','专奸','壮大','壮神鞭','壮盛','准备','茁壮','灼热','姿势','滋润','紫红色','子宫','子宫膀胱皱襞','子宫壁','子宫病变','子宫底','子宫恶性肉瘤','子宫后倾','子宫后屈位','子宫畸形','子宫肌瘤','子宫角','子宫颈','子宫颈癌','子宫颈管内膜柱状','子宫颈内D松弛','子宫颈炎','子宫颈阴道部','子宫颈粘膜','子宫阔韧带','子宫内膜','子宫内膜癌','子宫内膜炎','子宫内膜液','子宫内膜异位','子宫平滑肌','子宫腔','子宫切除手术','子宫切除术','子宫体','子宫脱垂','子宫峡部','子宫下段','子宫下段剖宫产','子宫腺','子宫圆韧带','子宫粘膜','子宫骶骨韧带','子孙袋子','自慰','自淫','自渎','纵欲','最高','最佳','尊具','左拥右抱','做爱','做爱后','做爱节奏','做爱经验','做爱前','做爱时间','做爱之道','作爱','坐骨海绵体肌','坐式性交','坐位','坐位性交','坐姿','睾酮','睾丸','睾丸动脉','睾丸固有鞘膜','睾丸激素','睾丸间质','睾丸结核','睾丸精索鞘膜','睾丸鞘膜','睾丸生精功能障碍','睾丸素','睾丸损伤','睾丸酮','睾丸网','睾丸系带','睾丸系膜','睾丸小隔','睾丸小叶','睾丸炎','睾丸液','睾丸移植','睾丸增生','睾丸坠痛','睾丸纵隔','睾丸甾酮','睾网液','厮缠','厮磨','剌激','耷拉','撸着','攥住','啜吸','啜著','噘起','噙住','後 穴','後洞','後进','後穴','狎弄','狎玩','猬亵','汩汩','涓涓','涔涔','渲泄','溽湿','潺潺','濡湿','尻臀','姊弟','姊夫','姊姊','妞媚','陰道','陽具','牝','牝户','掰开','胴体','膣壁','膣口','膣肉','膣穴','黏稠','黏糊','黏滑','黏膜','黏腻','黏热','黏湿','黏液','癃闭','裆部','舐','舐弄','舐去','舐吮','舐吻','舐著','舐着','酡红','蹂躏','蹼状阴茎','稣胸','骶丛','髂骨尾骨肌','髋部','麈柄','黝黑','谢燕益','告同胞书','新唐人','关贵敏','白雪','张铁钧','戚晓春','姜敏','陈瑞武','苏显达','张佳慧','大小百合','JIANZEMIN','LIPENG','FALUN','江主席','LIHONGZH','jiangzemin','lihongzhi','李洪志','法抡','6。4','6.4','李瑞环','小B','赵化勇','王八','shit','牛B','周恩来','邓小平','他妈的','毛泽东','江青','林彪','变态','国民党','法伦','傻b','丁关根','毛主席','西藏','打倒','拉登','cctv','告全体网民书','新唐人电视台','全球华人联欢晚会','宝马车撞人案','孙大午','朱胜文','民进党','罗明','张长明','胡恩','PowertotheFalunGong','FreeTibet','BrothersinArms','theUndergroundResistance','nihowma','Tibet','Falun','Gong','Underground','Resistance','Arms','Brothers','TIBET','tibet','falun','平反','六·四','6·4','生者与死者','天安门母亲','曹思源','茅于轼','娄义','先审','新闻管制','预审查','舆论钳制','2.23会议','蒋彦勇','社会公器','冯骥','赵安','线人','东方红时空','分家在十月','波波娃','诺夫','上街','集会','牛羊瘟疫','老刀','64','中宣部','讨伐','正邪大决战','救度世人','赵紫阳','总书记','大参考','大纪元郑重声明','一句心语传真情','轻轻的一声祝福，片片文章表心情。','老友你好，好文章别错过。','新年祝福，请看广传海外的报导。','Happynewyear，好文章不可不看。','001工程','共和国之辉','聚集','抗议','堵截','堵路','海龙大厦','天安门广场','西单','西坝河','国美','爱国者同盟','反日','反曰','抵制','日货','曰货游行','抗日','抗曰','日本','曰本','保钓','钓鱼岛','使馆','领事馆','示威','五四','54','五月四日','5.4','天鹅绒行动','颜色革命','天鹅绒','张戎','《毛：不为人知的故事》','鞍钢','鞍山钢铁','东突','圣战','重庆特钢厂','重特钢','太石村','太*石*村','泰石村','鱼窝头镇','罢官筹委会','羊皮狼','重庆特钢','重钢','拉法叶舰','拉案','东洲','太星中学','柳树中学','罢课','人事调整','王江波','春运','神职','龙滩水电站','加薪','郑茂清','邓天生','黎国如','田青','程宝山','程宝山中将','程中将','CHENG中将','肖副团长','肖白','肖大校','二炮','石占明','羊倌','国旗','沙桐','朱环','林程','女主持','黄健翔','刘建宏','段喧','张斌','冬日那','韩乔生','佐藤琢磨','婴儿汤','婴儿煲汤','四川朱昱','占地','拆迁','封杀','流产','小胡','李咏','孙悦','伍思凯','举报','黄色网站','法院','好处费','政府','白痴','sb','缺德','赢盘','丫','你妈的','滚','郑培民','失身','陈水扁','阿扁','赵建铭','吕秀莲','苏贞昌','罢扁','巴拉圭','下课','犬','倭','倭国','CCTV-5','cctv-5','主持人','chao','TMD','tmd','下岗','李文娟','彝','穆斯林','回族','蛮','伊斯兰','猪','王红旗','天体','生命起源','物质','成吉思汗','蒙古','转世','外逃','占领','嘎玛巴','藏传佛教','陈光诚','周金伙','傅先财','傻比','SB','施丹','道歉','黄贱翔','主持','解说','护短','侮辱','霸道','斑竹','版主','耻','主义','贱翔','周稚舜','中央电视台','汉奸','CCTV','魔兽','插穿','婊子','温家宝','秦裕','祝均一','老子','IP','群','南水北调','三峡','高智晟','程翔','赵岩','六合彩','针孔','笔记本','垃圾','央视','经纬','十七大','人事变动','削权','筹备','年轻','高知','团系','接班','胡温','整肃官场','省市换班','第五代','冒起','人事小组','宋平','七种人','一句话','披露','包二奶','临终遗言','政治局会议','筹备领导组','省委书记年轻化','李晓峰','sky','投票','蔡欢','财政部','奖','密码','帐号','账号','雅昌','百度古董','百家','妈','鸡','老母','宋什么','上访','领导','柴','璐','柴璐','李','红','李红','LIHONGZHI','宝马案','FalunGong','预审','日','一党专治','一党专制','人事','多党','民主','暴动','天安门','季先','小油条','一党制','延昌雅士','卫寺狂','砖家','赝品','兰河','遗珠','南露','老夫永乐','塞北云中鹤1','XJG351','假货','卖吗','出售','贩假','卖假','嘴脸','骗子','小丑','那日贵','蚁力神','助考','枪手','台海','台湾','立委','选举','谢燕益 ','告同胞书 ','关贵敏 ','张铁钧 ','戚晓春 ','姜敏 ','陈瑞武 ','苏显达 ','张佳慧 ','大小百合 ','朱镕基 ','江泽民 ','江主席 ','LIHONGZH ','jiangzemin ','lihongzhi ','李洪志 ','法抡 ','6。4 ','6.4 ','李瑞环 ','小B ','胡锦涛 ','赵化勇 ','王八 ','shit ','fuck ','牛B ','周恩来 ','邓小平 ','李宏治 ','同性恋 ','他妈的 ','操 ','毛泽东 ','江青 ','林彪 ','变态 ','共产党 ','国民党 ','法轮 ','法伦 ','傻b ','丁关根 ','毛主席 ','西藏 ','打倒 ','拉登 ','cctv ','告全体网民书 ','新唐人电视台 ','全球华人联欢晚会 ','宝马车撞人案 ','孙大午 ','朱胜文 ','李宏志 ','民进党 ','罗明 ','张长明 ','胡恩 ','Power to the Falun Gong ','Free Tibet ','Brothers in Arms ','the Underground Resistance ','ni how ma ','Tibet ','Falun ','Gong ','Underground ','Resistance ','Arms ','Brothers ','TIBET ','tibet ','falun ','蒋彦永 ','平反 ','六四 ','丁子霖 ','六·四 ','6·4 ','生者与死者 ','天安门母亲 ','曹思源 ','茅于轼 ','娄义 ','先审 ','新闻管制 ','预审查 ','舆论钳制 ','2.23会议 ','蒋彦勇 ','社会公器 ','冯骥 ','赵安 ','线人 ','东方红时空 ','分家在十月 ','波波娃 ','诺夫 ','上街 ','游行 ','集会 ','牛羊瘟疫 ','老刀 ','焦国标 ','何家栋 ','丁家班 ','中宣部 ','讨伐 ','昝爱宗 ','正邪大决战 ','救度世人 ','天葬 ','赵紫阳 ','总书记 ','大参考 ','大纪元郑重声明 ','一句心语传真情 ','轻轻的一声祝福，片片文章表心情。 ','老友你好，好文章别错过。 ','新年祝福，请看广传海外的报导。 ','001工程 ','共和国之辉 ','聚集 ','抗议 ','堵截 ','堵路 ','海龙大厦 ','天安门广场 ','西单 ','西坝河 ','国美 ','爱国者同盟 ','反日 ','反曰 ','抵制 ','日货 ','曰货游行 ','抗日 ','抗曰 ','日本 ','曰本 ','保钓 ','钓鱼岛 ','使馆 ','领事馆 ','示威 ','五四 ','五月四日 ','5.4 ','天鹅绒行动 ','颜色革命 ','天鹅绒 ','张戎 ','《毛：不为人知的故事》 ','鞍钢 ','鞍山钢铁 ','东突 ','圣战 ','重庆特钢厂 ','重特钢 ','太石村 ','太*石*村 ','泰石村 ','鱼窝头镇 ','罢官筹委会 ','羊皮狼 ','重庆特钢 ','重钢 ','拉法叶舰 ','拉案 ','东洲 ','太星中学 ','柳树中学 ','罢课 ','许万平 ','人事调整 ','王江波 ','春运 ','神职 ','龙滩水电站 ','加薪 ','郑茂清 ','邓天生 ','黎国如 ','田青 ','程宝山 ','程宝山中将 ','程中将 ','CHENG中将 ','肖副团长 ','肖白 ','肖大校 ','二炮 ','石占明 ','羊倌 ','国旗 ','沙桐 ','朱环 ','林程 ','女主持 ','黄健翔 ','刘建宏 ','段喧 ','张斌 ','冬日那 ','韩乔生 ','佐藤琢磨 ','婴儿汤 ','婴儿煲汤 ','裸体 ','四川朱昱 ','占地 ','拆迁 ','封杀 ','处女 ','流产 ','小胡 ','李咏 ','孙悦 ','伍思凯 ','举报 ','黄色网站 ','法院 ','好处费 ','政府 ','手淫 ','做爱 ','白痴 ','sb ','缺德 ','赢盘 ','丫 ','你妈的 ','滚 ','郑培民 ','失身 ','陈水扁 ','阿扁 ','女婿 ','赵建铭 ','吕秀莲 ','苏贞昌 ','罢扁 ','巴拉圭 ','下课 ','犬 倭 ','倭国 ','CCTV-5 ','cctv-5 ','主持人 ','chao ','TMD ','tmd ','下岗 ','李文娟 ','彝 ','穆斯林 ','回族 ','蛮 ','伊斯兰 ','猪 ','王红旗 ','天体 ','生命起源 ','藏独 ','疆独 ','成吉思汗 ','蒙古 ','达赖 ','转世 ','外逃 ','占领 ','嘎玛巴 ','藏传佛教 ','陈光诚 ','周金伙 ','傅先财 ','傻比 ','SB ','鸡巴 ','施丹 ','道歉 ','黄贱翔 ','主持 ','解说 ','护短 ','侮辱 ','霸道 ','斑竹 ','版主 ','耻 ','主义 ','贱翔 ','周稚舜 ','傻逼 ','中央电视台 ','汉奸 ','CCTV ','魔兽 ','插穿 ','骚穴 ','婊子 ','温家宝 ','秦裕 ','祝均一 ','老子 ','南水北调 ','三峡 ','高智晟 ','程翔 ','赵岩 ','六合彩 ','针孔 ','笔记本 ','垃圾 ','央视 ','陈良宇 ','乱伦 ','经纬 ','十七大 ','人事变动 ','曾庆红 ','削权 ','筹备 ','高知 ','团系 ','接班 ','胡温 ','整肃官场 ','省市换班 ','第五代 ','冒起 ','人事小组 ','宋平 ','七种人 ','一句话 ','披露 ','包二奶 ','霍英东 ','临终遗言 ','政治局会议 ','筹备领导组 ','省委书记年轻化 ','李晓峰 ','sky ','投票 ','蔡欢 ','金人庆 ','财政部 ','奖 ','密码 ','帐号 ','账号 ','法轮功 ','自焚 ','雅昌 ','百度古董 ','百家 ','妈 ','鸡 ','老母 ','宋什么 ','上访 ','领导 ','柴璐 ','李 红 ','李红 ','新唐人 ','FALUN ','LIHONGZHI ','宝马案 ','Falun Gong ','预审 ','大纪元 ','年轻 ','刘少奇','朱德','彭德怀','刘伯承','陈毅','贺龙','聂荣臻','徐向前','罗荣桓','叶剑英','李大钊','陈独秀','孙中山','孙文','孙逸仙','陈云','尉健行','李岚清','唐家璇','华建敏','陈至立','贺国强','李登辉','连战','宋楚瑜','郁慕明','蒋介石','蒋中正','蒋经国','马英九','布什','布莱尔','小泉纯一郎','萨马兰奇','安南','阿拉法特','普京','默克尔','克林顿','里根','尼克松','林肯','杜鲁门','赫鲁晓夫','列宁','斯大林','马克思','恩格斯','金正日','金日成','萨达姆','胡志明','西哈努克','希拉克','撒切尔','阿罗约','曼德拉','卡斯特罗','富兰克林','华盛顿','艾森豪威尔','拿破仑','亚历山大','路易','拉姆斯菲尔德','劳拉','鲍威尔','布朗','奥巴马','梅德韦杰夫','潘基文','本拉登','奥马尔','达赖喇嘛','张春桥','姚文元','王洪文','东条英机','希特勒','墨索里尼','冈村秀树','冈村宁次','高丽朴','沃尔开西','赖昌星','马加爵','班禅','额尔德尼','山本五十六','热比娅','鲁迅','莎士比亚','爱因斯坦','牛顿','哥白尼','哥伦布','达芬奇','米开朗基罗','毕加索','梵高','海明威','斯蒂芬霍金','六四运动','美国之音','密宗','民国','摩门教','纳粹','南华早报','南蛮','明慧网','起义','亲民党','瘸腿帮','人民报','法轮大法','台独分子','台联','台湾民国','台湾独立','太子党','天安门事件','屠杀','小泉','新党','新疆独立','新疆国','西藏国','西藏独立','一党专政','一贯道','圆满','政变','政治','政治反对派','政治犯','中共','中国民主党','中国之春','苏家屯','基地组织','塔利班','东亚病夫','支那','高治联','藏青会','藏妇会','台湾岛国','台湾分裂','西藏分裂','新疆分裂','核工业基地','核武器','铀','原子弹','氢弹','导弹','核潜艇','小参考','国内动态详情','道教','佛教','佛祖','释迦牟尼','如来','阿弥陀佛','观世音','普贤','文殊','地藏','回教','穆罕默德','升天','圣母','耶和华','耶稣','真主安拉','白莲教','天主教','基督教','东正教','真理教','走向圆满','黄大仙','风水','跳大神','神汉','神婆','大卫教','阎王','黑白无常','牛头马面','谋杀','杀人','吸毒','贩毒','赌博','拐卖','走私','卖淫','监狱','抢劫','先奸后杀','下注','押大','押小','抽头','坐庄','赌马','赌球','筹码','老虎机','轮盘赌','安非他命','大麻','可卡因','海洛因','冰毒','摇头丸','杜冷丁','鸦片','罂粟','迷幻药','白粉','嗑药','AIDS','aids','Aids','DICK','dick','Dick','penis','sex','SM','屙','爱滋','梅毒','臭机八','臭鸡巴','吹喇叭','吹箫','催情药','屌','黄色','机八','机巴','鸡八','机掰','鸡叭','鸡掰','妓女','奸','茎','尻','滥交','乱交','嫖娼','强奸犯','三陪','色情','威而钢','威而柔','伟哥','穴','颜射','一夜情','淫','援交妹','换妻','Bitch','cao','FUCK','Fuck','kao','NMD','NND','ri','SHIT','SUCK','Suck','tnnd','K他命','笨蛋','操她妈','操妳妈','操你妈','操他妈','草你','册那','侧那','测拿','蠢猪','废物','干她妈','干妳','干妳娘','干你','干你妈','干你妈B','干你妈b','干你妈逼','干你娘','干他妈','狗娘养的','贱货','贱人','靠','烂人','老土','妈比','妈的','马的','妳老母的','妳娘的','你妈逼','破鞋','仆街','去她妈','去妳的','去妳妈','去你的','去你妈','去死','去他妈','日你','赛她娘','赛妳娘','赛你娘','赛他娘','傻B','傻子','上妳','上你','神经病','屎','屎妳娘','屎你娘','王八蛋','我操','我日','乡巴佬','猪猡','干','尿','掯','骑你','湿了','操他','操她','骑他','骑她','欠骑','欠人骑','来爽我','来插我','干他','干她','干死','干爆','干机','机叭','臭鸡','臭机','烂鸟','览叫','摸咪咪','PENIS','BITCH','BLOW','JOB','KISS','MY','ASS','干鸡','干入','插你','爽你','干干','干X','他干','干它','干牠','干您','干汝','干林','操林','干尼','操尼','我咧干','干勒','干我','干到','干啦','干爽','欠干','狗干','我干','来干','轮干','轮流干','干一干','援交','奸暴','再奸','我奸','奸你','奸他','奸她','奸一奸','淫湿','鸡歪','臭西','吊','烂逼','大血比','叼你妈','靠你妈','戳你','逼你老母','挨球','我日你','草拟妈','卖逼','狗操卖逼','日死','奶娘','他娘','她娘','你妈了妹','逼毛','插你妈','叼你','渣波波','嫩b','ADMIN','Admin','admin','Administrator','administrator','管理','管里','管理员','服务管理','服务器','活动管理员','官方','维护','系统','系统公告','审查','巡查','监督','监管','game','master','GAMEMASTER','GameMaster','GM','Gm','gM','gm','G.M','游戏管理员','Client','Server','CS','Cs','cs','cS','KEFU','kefu','Kefu','KeFu','助理','客户服务','客服','服务天使','测试','辅助程序','运营','运营者','运营组','运营商','运营长','运营官','运营人','sf','私服','私人服务器','wg','外挂','陈天桥','SNDA','盛大','SDG','盛大游戏','ShandaGames','shandagames','Shandagames','Shanda.Games','16大','十六大','64事件','六四学潮','64运动','89事件','中国共产党','共产','共产主义','中共中央政治局','届中央政治局委','中央政治局','反党','反人民','反动','反动派','反革命','打倒中国','打倒中国共产党','打倒中华人民共和国','党中央','国家主席','党主席','台湾帝国','台湾公投','台湾共产党','台湾共和国','台湾国','台湾总统','保卫台湾','倒台','两国论','全国人大','陈总统','阿扁万岁','东突暴动','东突独立','fa','伦','fa-lun-gong','falco','ferry','FLG','发伦','藏独会','藏独分子','藏独分裂','藏独势力','真世界','膏药旗','根正苗红','黑五类','红卫兵','皇军','共荣圈','国共合作','国军','国民党万岁','国民政府','四人帮','靖国神社','没有人权','批林批孔','政府无能','政治避难','政治打击','政治封锁','政治压迫','四清','抢夺圣火','社会主义','中国人民解放军','中国人民志愿军','中华人民共和国','中南海','国务院','革命','公安局','政治局','暴行','朝鲜族','大东亚','大东亚共荣圈','大跃进','大字报','抵制日货','独裁','法西斯','浮夸风','腐败','复辟','密传','和平演变','一边一国','戒急用忍','军国主义','垮台','民國','中华民国','中華民國','青天白日','青天白日旗','政协','人民大会堂','总理','委员','常委','西藏喇嘛','资本主义','指导员','武力统一','台湾党','走资帮','天皇','天神','上山下乡','言论自由','新华社','新闻出版总署','新义安','毛澤東','鄧小平','江泽明','江澤民','江core','蔡启芳','蔡和森','曹庆泽','阿沛?阿旺晋美','蔡英文','陈博志','蔡庆林','陈伯达','陈建铭','薄一波','陈慕华','陈定南','陈锡联','陈菊','陈唐山','成克杰','陈丕显','陈永贵','姜春云','陈希同','邓发','董必武','邓颖超','谷牧','邓力群','杜正胜','董文华','韩杼滨','洪兴','顾顺章','郝伯村','韩光','何勇','韩天石','胡启立','黄永生','胡乔木','黄克诚','瞿秋白','华国锋','纪登奎','康生','凯丰','李登柱','黄仲生','李德生','李雪峰','李俊毅','李立三','李维汉','傅作义','李先念','傅全有','廖承志','李作鹏','林佳龙','林益世','卢福坦','林信义','林祖涵','刘华清','连惠心','林伯渠','刘丽英','刘文雄','刘澜涛','陆定一','马国瑞','倪志福','彭冲','彭佩云','彭真','祁培文','钱其琛','乔冠华','乔石','宋庆龄','宋任穷','苏兆征','苏振华','王从吾','王汉斌','王鹤寿','王稼祥','王克','王金平','王震','韦国清','郑宝清','秦基伟','朱立伦','邹家华','李克强','张廷发','张万年','张闻天','张震','章孝勇','章孝严','赵洪祝','曾志郎','杨白冰','张博雅','邱会作','邱太三','杨得志','杨尚昆','杨勇','姚依林','叶菊兰','叶群','任弼时','任建新','翁金珠','习仲勋','卓伯源','谭绍文','汪东兴','田弘茂','田纪云','许世友','谭震林','耿飚','谭政','吴德','许财利','吴桂贤','伍世文','向忠发','颜庆章','于永波','余秋里','谢长廷','谢非','谢深山','袁纯清','唐骏','李红志','川岛芳子','塞福昂?','艾则孜','扎卡维','乌兰夫','av貼圖','japansweet','sm','adult','16dy-图库','獸交','爱女人','拔出来','操b','插我','吃精','调教','黄色电影','激情电影','迷奸','色猫','图库','亚情','淫亂','淫蕩','幼圖','中文搜性网','鷄巴','學生妹','１８歲淫亂','999日本妹','幹炮','摸阴蒂','金鳞岂是池中物','掰穴皮卡丘','白虎少妇','白虎阴穴','暴淫','逼痒','蕩妹','肥逼','粉穴','干穴','口活','狼友','风艳阁','激情小说','兽欲','全裸','菊花蕾','大力抽送','干的爽','anal','肉蒲团','男女交欢','极品波霸','流淫','销魂洞','操烂','成人网站','一夜欢','给你爽','偷窥图片','性奴','incest','奸幼','中年美妇','喷精','逼奸','脱内裤','菊花洞','成人自拍','自拍美穴','抠穴','淫水爱液','露B','母子奸情','人妻熟女','色界','丁香社区','爱图公园','色色五月天','鹿城娱乐','幼香阁','隐窝窝','乱伦熟女网','插阴','露阴照','美幼','97sese','嫩鲍鱼','日本AV女优','美女走光','33bbb走光','激情贴图','成人论坛','就去诱惑','浴室自拍','BlowJobs','激情裸体','丽春苑','窝窝客','银民吧','亚洲色','碧香亭','爱色cc','妹妹骚图','宾馆女郎','美腿丝足','好色cc','无码长片','淫水涟涟','放荡少妇','成人图片','黄金圣水','勾魂少妇','我要性交','SM女王','乳此丝袜','日本灌肠','集体性爱','国产骚货','操B指南','亚洲淫娃','熟女乱伦','SM舔穴','無碼H漫','大胆少女','乳此丰满','屄屄特写','熟女颜射','要色色','耻辱轮奸','巨乳素人','妩媚挑逗','骚姨妈','裸体少妇','美少妇','射奶','杨思敏','野外性交','风骚淫荡','白虎嫩B','明星淫图','淫乱熟女','高清性愛','高潮集锦','淫兽学园','俏臀摄魄','有容奶大','无套内射','毛鲍','3P炮图','性交课','激凸走光','性感妖娆','人妻交换','监禁陵辱','生徒胸触','東洋屄','翘臀嫩穴','春光外泻','淫妇自慰','本土无码','淫妻交换','日屄','近亲相奸','艳乳','白虎小穴','肛门喷水','淫荡贵妇','鬼畜轮奸','浴室乱伦','生奸内射','国产嫖娼','白液四溅','带套肛交','大乱交','精液榨取','性感乳娘','魅惑巨乳','无码炮图','群阴会','人性本色','极品波神','淫乱工作','白浆四溅','街头扒衣','口内爆射','嫩BB','肛门拳交','灌满精液','莲花逼','自慰抠穴','人妻榨乳','拔屄自拍','洗肠射尿','人妻色诱','淫浆','狂乳激揺','騷浪','射爽','蘚鮑','制服狩','無毛穴','骚浪美女','肏屄','舌头穴','人妻做爱','插逼','爆操','插穴止痒','骚乳','食精','爆乳娘','插阴茎','黑毛屄','肉便器','肉逼','淫亂潮吹','母奸','熟妇人妻','発射','幹砲','性佣','爽穴','插比','嫩鲍','骚母','吃鸡巴','金毛穴','体奸','爆草','操妻','a4u','酥穴','屄毛','厕所盗摄','艳妇淫女','掰穴打洞','盗撮','薄码','少修正','巧淫奸戏','成人片','换妻大会','穴爽','99bb','g点','tw18','asiasex','teen','sexy','欢欢娱乐时空','近親相姦','裤袜','买春','妹妹阴毛','免费成人网站','免费偷窥网','免费A片','摩洛客','骚姐姐','色区','色书库','射颜','吸精少女','下流地带','性虎','性饥渴','淫妹','淫图','幼交','嫩屄','嫩女','噴精','情色天崖','情色文学','群交亂舞','日本骚货','肉棍干骚妇','肉淫器吞精','骚妹','色狐狸网址','色狼论坛','色狼小说','湿穴','爽死我了','舔逼','舔屁眼','好嫩','亂倫','hardcore','amateur','做爱电影','色诱','秘裂','采花堂','含屌','亚洲性虐','夫妻自拍','熟女','裹本','嫩逼','欢乐性今宵','性愛圖片','学生妹','炮友之家','花花公子','淫虫','hotsex','porn','小姐打飞机','少女被插','Ｘ到噴屎尿','口淫','按摩棒','奸情','被干','露逼','美女高潮','日逼','阴缔','插暴','人妻','内射','欲仙欲浪','被插','吞精','暴乳','成人午夜场','买春堂','性之站','成人社区','群交','激情聊天','三八淫','做爱自拍','淫妻','夫妻俱乐部','激情交友','诱色uu','就去色色','熟妇','mm美图','走光偷拍','77bbb','虎骑','咪咪图片','成人导航','深爱色色','厕所偷拍','成人A片','夫妻多p','我就色','释欲','你色吗','裙内偷拍','男女蒲典','色97爱','丝诱','人妻自拍','色情工厂','色色婷婷','美体艳姿','颜射自拍','熟母','肉丝裤袜','sm调教','打野炮','赤裸天使','淫欲世家','就去日','爱幼阁','巨屌','花样性交','裸陪','夫妻3p','大奶骚女','性愛插穴','日本熟母','幼逼','淫水四溅','大胆出位','旅馆自拍','无套自拍','快乐AV','国产无码','强制浣肠','援交自拍','凸肉优','撅起大白腚','骚妹妹','插穴手淫','双龙入洞','美女吞精','处女开包','调教虐待','淫肉诱惑','激情潮喷','骚穴怒放','馒头屄','无码丝袜','写真','寂寞自摸','警奴','轮操','淫店','精液浴','淫乱诊所','极品奶妹','惹火身材','暴力虐待','巨乳俏女医','扉之阴','淫の方程式','丁字裤翘臀','轮奸内射','空姐性交','美乳斗艳','舔鸡巴','骚B熟女','淫丝荡袜','奴隷调教','阴阜高耸','翘臀嫩逼','口交放尿','媚药少年','暴奸','无修正','国产AV','淫水横流','插入内射','东热空姐','大波粉B','互舔淫穴','丝袜淫妇','乳此动人','大波骚妇','无码做爱','口爆吞精','放荡熟女','巨炮兵团','叔嫂肉欲','肉感炮友','爱妻淫穴','无码精选','超毛大鲍','熟妇骚器','内射美妇','毒龙舔脚','性爱擂台','圣泉学淫','性奴会','密室淫行','亮屄','操肿','无码淫女','玩逼','我就去色','淫痴','风骚欲女','亮穴','操穴喷水','幼男','肉箫','巨骚','骚妻','漏逼','骚屄','大奶美逼','高潮白浆','性战擂台','淫女炮图','淫水横溢','性交吞精','姦染','淫告白','乳射','操黑','朝天穴','公媳乱','女屄','慰春情','集体淫','淫B','屄屄','肛屄','小嫩鸡','舔B','嫩奶','a4y','品穴','淫水翻騰','一本道','乳尻','羞耻母','艳照','三P','露毛','紧穴','露点','18禁','g片','無碼電影','插b','荡女','露穴','迷药','无码','吸精','现代情色小说','性交图','性息','艳情小说','阴部特写','阴道图片','淫书','幼女','玉蒲团','玉女心经','援助交易','中国成人论坛','中国性爱城','自拍写真','做爱图片','掰穴','万淫堂','穴图','穴淫','艳舞淫业','咬着龟头','要射了','一夜性网','阴茎插小穴','陰穴新玩法','婬乱军团','淫逼','淫姐','淫流','淫蜜','淫魔','淫妞','淫奴','钻插','H动漫','交换夫妻','舔脚','丝袜','亚洲情色网','强奸处女','鸡巴暴胀','大众色情成人网','火辣图片','淫声浪语','疯狂抽送','淫河','多人性愛','操屄','色情论坛','性虎色网','淫欲日本','色迷城','petgirl','骚女叫春','成人百强','猖妓','天天干贴图','密穴贴图','品色堂','嫖妓指南','色窝窝','被操','巨奶','骚洞','阴屄','群魔色舞','扒穴','六月联盟','55sss偷拍区','张筱雨','xiao77','极品黑丝','丝袜写真','天天情色','成人小说','成人文学','情色艺术天空','222se图片','偷拍','淫色贴图','厕奴','美女','成人','酥胸诱惑','五月天','人体摄影','东北xx网','玛雅网','成人bt','周六性吧','爆乳','诱惑视频','裙下风光','嘻游中国','操母狗','御の二代目','丝袜足交','肮脏美学','亚洲有码','欲仙欲死','丝袜高跟','偷拍美穴','原味丝袜','裸露自拍','针孔偷拍','放荡少妇宾馆','性感肉丝','拳交','迫奸','品香堂','北京xx网','虐奴','情色导航','欧美大乳','欧美无套','骚妇露逼','炮友','淫水丝袜','母女双飞','老少乱伦','幼妓','素人娘','前凸后翘','制服誘惑','舔屄','色色成人','迷奸系列','性交无码','惹火自拍','胯下呻吟','淫驴屯','少妇偷情','护士诱惑','群奸乱交','极品白虎','曲线消魂','无码淫漫','假阳具插穴','蝴蝶逼','自插小穴','SM援交','西洋美女','爱液横流','无码无套','淫战群P','酒店援交','乳霸','湿身诱惑','火辣写真','动漫色图','熟女护士','粉红穴','经典炮图','童颜巨乳','性感诱惑','援交薄码','美乳美穴','奇淫宝鉴','美骚妇','跨下呻吟','无毛美少女','流蜜汁','日本素人','爆乳人妻','妖媚熟母','日本有码','激情打炮','制服美妇','无码彩图','放尿','入穴一游','丰唇艳姬','群奸轮射','高级逼','MM屄','美臀嫰穴','淫东方','国产偷拍','清晰内射','嫩穴肉缝','雪腿玉胯','骚妇掰B','白嫩骚妇','梅花屄','猛操狂射','潮喷','无码体验','吞精骚妹','紧缚凌辱','奸淫电车','堕淫','颜骑','互淫','胸濤乳浪','夫妻乱交','黑屄','奶大屄肥','拔屄','穴海','换妻杂交','黑逼','粉屄','口射','多人轮','奶挺臀翘','扒屄','痴乳','鬼輪姦','乳爆','浴尿','淫样','発妻','姫辱','插后庭','操爽','嫩缝','操射','骚妈','激插','暴干','母子交欢','嫐屄','足脚交','露屄','柔阴术','相奸','淫师荡母','桃园蜜洞','二穴中出','奴畜抄','连続失禁','大鸡巴','玩穴','性交自拍','叫鸡','骚浪人妻','陈总','kappa','Player','player','bitch','tianwang','cdjp','bignews','boxun','chinaliberal','chinamz','chinesenewsnet','cnd','creaders','dafa','dajiyuan','dfdz','dpp','falu','falundafa','flg','freechina','freenet','GCD','gcd','hongzhi','hrichina','huanet','hypermart','jiangdongriji','japan','making','minghui','minghuinews','nacb','naive','nmis','paper','peacehall','playboy','renminbao','renmingbao','rfa','safeweb','seqing','simple','svdc','taip','tibetalk','triangle','triangleboy','UltraSurf','unixbox','ustibet','voa','wangce','wstaiji','xinsheng','yuming','zhengjian','zhengjianwang','zhenshanren','zhuanfalun','xxx','anime','censor','hentai','[hz]','(hz)','[av]','(av)','[sm]','(sm)','multimedia','toolbar','downloader','碼','乳','陰','姦','性','裸','骚','码','嫂','阴','肛','妓','顶级','绝版','教師','教师','老师','医生','护士','自拍','大片','大陆','限制','吐血','流血','韩国','招待','中国','香港','西方','少儿不宜','咪咪','酷刑','强迫','三級','三级','A片','A级','被虐','被迫','被逼','口技','緊縛','幼幼','女優','强歼','色友','蒲团','女女','喷尿','插插','坐交','慰安妇','色狼','妹疼','妹痛','弟疼','弟痛','姐疼','姐痛','哥疼','哥痛','同房','捅你','捅我','插他','波霸','偷情','制服','亚热','走光','自摸','捆绑','潮吹','群射','卡通','臭作','薄格','調教','近親','連發','母子','更衣','無修正','尿尿','喷水','小泽玛莉亚','武腾兰','武藤兰','饭岛爱','小泽圆','長瀨愛','川島和津實','粉嫩的小沟','小澤園','飯島愛','星崎未來','及川奈央','朝河蘭','夕樹舞子','大澤惠','金澤文子','三浦愛佳','伊東','武藤蘭','叶子楣','舒淇','翁虹','麻衣','櫻井','風花','星野桃','宝来','桜田','藤井彩','小森美王','平井','桃井望','榊彩弥','桜井','中条美華','大久保玲','松下','青木友梨','深田涼子','愛内萌','姫島瑠璃香','長瀬愛','中野千夏','春菜','望月','岡崎美女','宮下杏奈','加藤','日野美沙','北島優','蜘蛛','1Pondo','夜勤病栋','三个代表','一党','专政','避孕套','弟子','洪志','红志','洪智','红智','法论','法沦','发轮','发论','发沦','轮功','轮公','轮攻','沦功','沦公','沦攻','论攻','论功','论公','伦攻','伦功','伦公','卖春','朱容基','警察','民警','公安','嫖','大盖帽','武警','黑社会','交警','消防队','刑警','夜总会','妈个','公款','首长','书记','坐台','城管','李远哲','司法警官','高干','人大','文字狱','宋祖英','骗局','猫肉','吸储','张五常','张丕林','空难','专制','三個代表','一黨','多黨','專政','大紀元','紅志','紅智','法論','法淪','法倫','發輪','發論','發淪','發倫','輪功','輪公','輪攻','淪功','淪公','淪攻','論攻','論功','論公','倫攻','倫功','倫公','民運','台獨','李鵬','天安門','朱鎔基','李長春','李瑞環','胡錦濤','臺灣獨立','藏獨','西藏獨立','疆獨','新疆獨立','大蓋帽','黑社會','消防隊','夜總會','媽個','首長','書記','腐敗','暴動','暴亂','李遠哲','高幹','李嵐清','黃麗滿','於幼軍','文字獄','騙局','貓肉','吸儲','張五常','張丕林','空難','溫家寶','吳邦國','曾慶紅','黃菊','羅幹','賈慶林','專制','八老','巴赫','白立朴','白梦','白皮书','鲍戈','北大三角地论坛','北韩','北京当局','北京之春','北美自由论坛','博讯','蔡崇国','曹长青','常劲','陈炳基','陈蒙','陈破空','陈小同','陈宣良','陈一谘','程凯','程铁军','程真','迟浩田','持不同政见','赤匪','赤化','春夏自由论坛','大纪元新闻网','大纪园','大家论坛','大史','大史记','大史纪','大中国论坛','大中华论坛','大众真人真事','弹劾','登辉','邓笑贫','迪里夏提','地下教会','地下刊物','第四代','电视流氓','丁元','东北独立','东方时空','东南西北论谈','东社','东土耳其斯坦','东西南北论坛','独夫','独立台湾会','杜智富','屙民','俄国','发愣','发正念','反封锁技术','反腐败论坛','反攻','反人类','反社会','方舟子','飞扬论坛','斐得勒','分家在','分裂','粉饰太平','风雨神州','风雨神州论坛','封从德','冯东海','冯素英','佛展千手法','付申奇','傅申奇','高官','高文谦','高薪养廉','高瞻','戈扬','鸽派','歌功颂德','蛤蟆','个人崇拜','工自联','功法','共狗','关卓中','贯通两极法','广闻','郭罗基','郭平','郭岩华','国家安全','国家机密','国贼','韩东方','韩联潮','红灯区','红色恐怖','宏法','洪传','洪哲胜','胡紧掏','胡锦滔','胡锦淘','胡景涛','胡平','胡总书记','护法','华通时事论坛','华夏文摘','华语世界论坛','华岳时事论坛','黄慈萍','黄祸','黄菊　','黄翔','回民暴动','悔过书','鸡毛信文汇','姬胜德','积克馆','基督','贾廷安','贾育台','建国党','江八点','江流氓','江罗','江绵恒','江戏子','江则民','江泽慧','江贼','江贼民','江折民','江猪','江猪媳','将则民','僵贼','僵贼民','讲法','酱猪媳','交班','教养院','揭批书','金尧如','锦涛','禁看','经文','开放杂志','看中国','邝锦文','劳动教养所','劳改','劳教','老江','老毛','黎安友','李红痔','李洪宽','李兰菊','李老师','李录','李少民','李淑娴','李文斌','李小朋','李小鹏','李月月鸟','李总理','李总统','连胜德','联总','廉政大论坛','炼功','梁擎墩','两岸关系','两岸三地论坛','两会','两会报道','两会新闻','林保华','林长盛','林樵清','林慎立','凌锋','刘宾深','刘宾雁','刘刚','刘国凯','刘俊国','刘凯中','刘千石','刘青','刘山青','刘士贤','刘文胜','刘永川','流亡','龙虎豹','陆委会','吕京花','抡功','轮大','罗礼诗','马大维','马良骏','马三家','马时敏','卖国','毛厕洞','毛贼东','美国参考','蒙独','蒙古独立','绵恒','民联','民意','民意论坛','民阵','民猪','民族矛盾','莫伟强','木犀地','木子论坛','南大自由论坛','闹事','倪育贤','你说我说论坛','潘国平','泡沫经济','迫害','祁建','齐墨','钱达','钱国梁','抢粮记','亲美','钦本立','秦晋','轻舟快讯','情妇','庆红','全国两会','热站政论网','人民内情真相','人民真实','人民之声论坛','人权','瑞士金融大学','善恶有报','上海帮','上海孤儿院','邵家健','神通加持法','沈彤','盛雪','师父','石戈','时代论坛','时事论坛','世界经济导报','事实独立','双十节','水扁','税力','司马晋','司马璐','司徒华','斯诺','四川独立','宋书元','苏绍智','台盟','台湾狗','台湾建国运动组织','台湾青年独立联盟','台湾政论区','台湾自由联盟','汤光中','唐柏桥','唐捷','滕文生','天怒','童屹','统独','统独论坛','统战','外交论坛','外交与方略','万维读者论坛','万晓东','汪岷','王宝森','王炳章','王策','王超华','王辅臣','王涵万','王沪宁','王军涛','王力雄','王瑞林','王润生','王若望','王希哲','王秀丽','王冶坪','网特','魏新生','无界浏览器','吴百益','吴方城','吴弘达','吴宏达','吴仁华','吴学灿','吴学璨','吾尔开希','五不','伍凡','洗脑','项怀诚','项小吉','肖强','邪恶','谢选骏','谢中之','新观察论坛','新华举报','新华内情','新华通论坛','新生网','新闻封锁','新语丝','信用危机','邢铮','熊炎','熊焱','修炼','徐邦秦','徐水良','许家屯','薛伟','学习班','学自联','雪山狮子','严家祺','阎明复','央视内部晚会','杨怀安','杨建利','杨巍','杨月清','杨周','姚月谦','夜话紫禁城','义解','亦凡','异见人士','异议人士','易丹轩','易志熹','尹庆民','由喜贵','幼齿','于大海','于浩成','余英时','舆论','舆论反制','宇明网','远志明','岳武','在十月','则民','择民','泽民','贼民','张伯笠','张钢','张宏堡','张健','张林','张伟国','张昭富','张志清','赵海青','赵南','赵品潞','赵晓微','哲民','真相','真象','争鸣论坛','正见网','郑义','正义党论坛','极品','真木加美','超爽','清纯','伦理','艳星','女生','绝色','床上功夫','強暴','陈冠希','铃木麻奈美','星崎未来','东京热','束缚','电影','影片','菅野亚梨沙','吉岡美穗','红音','原千寻','沙雪','H动画','hgame','H游戏','89风波','89風波','9ping','9评','9坪','9評','fa轮','fa輪','FL功','free tibet','GONG党','GONG黨','HEIDEROOSJESAND LOTEN UNITED TEBET','hyperballad-tibet','jiuping','Media Control in China','NTDTV','Raptibetan','United Tibet','大际元','大際元','邓矮子','鄧矮子','狄玉明','法0功','法lun','法X功','共独','共獨','共贼','共賊','激流china','激流cn','金菩提','金菩提上师','金菩提上師','金麒麟','九ping','九评','九凭','九評','龙应台','龍應臺','泣血的春天','全球華人聯歡晚會','全世界华人声乐大赛','全世界華人聲樂大賽','全世界中国舞舞蹈大赛','全世界中國舞舞蹈大賽','仁布其','神韵艺术团','神韻藝術團','生者和死者','生者與死者','世界通','世界之门','世界之門','四神记','四神記','透视中国','透視中國','退黨','亡党','亡黨','伪火','偽火','无产阶级专政','无界','吾尔凯希','希望之声','希望之聲','邪党','邪黨','邪教','新搪人','一九八九年春夏之交的政治风波','張宏堡','中功','周刊纪事','周刊紀事','周晓川','自由亚洲','自由亞洲','自由之门','自由之門','激流中国','激流中國','东楼','東樓');

        $redis = new RedisOperate();
//        foreach ($bads as $v){
//            $redis->RedisRPush('badwords',$v);
//        }
        $res = $redis->RedisLRange('badwords');
        dd($res);
    }
}
