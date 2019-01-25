<?php
/**
 * Created by PhpStorm.
 * User: insen
 * Date: 2018/3/16
 * Time: 16:18
 */

namespace App\Http\Commons;


class CurlOperate
{
    const rpc_url = 'http://47.75.152.186:19332';
    const rpc_user = 'whh';
    const rpc_pwd  = 'whh';

    public function GetAddressBalance($params)
   {
       $url  = self::rpc_url;
       $user = self::rpc_user;
       $pwd  = self::rpc_pwd;
       $Author = $user.':'.$pwd;

       $backStatus = 888;

       //记录请求次数
       $flag = 1;

       while($backStatus != 200){
           $jsonArr = ["method"=>"getaddressbalance","params"=>[$params],"id"=>1];
           $jsonStr = json_encode($jsonArr);
           $Authorization = base64_encode($Author);

           $ch = curl_init();
           curl_setopt($ch, CURLOPT_POST, 1);
           curl_setopt($ch, CURLOPT_URL, $url);
           curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
           curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
           curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                   'Content-Type: application/json; charset=utf-8',
                   'Content-Length: ' . strlen($jsonStr),
                   'Authorization:Basic '.$Authorization
               )
           );

           $response = curl_exec($ch);

           $responseArray = json_decode($response,1);

           //请求数据是否为空
           if($responseArray){
               if(array_key_exists('error',$responseArray) && $responseArray['error'] != null){
                   curl_close($ch);
                   return json_encode(array('error'=>1,'msg'=>$responseArray['error']['message']));
               }

               $backStatusTemp= curl_getinfo($ch,CURLINFO_HTTP_CODE);

               $flag++;

               if($flag > 3){
                   $errorArray = [
                       'msg'   => 'Request data does not exist!'
                   ];
                   curl_close($ch);
                   return json_encode($errorArray);
               }

               if($backStatusTemp == 200){
                   curl_close($ch);
                   return $response;
               }
           }else{
               $flag++;

               if($flag > 3){
                   $errorArray = [
                       'msg'   => 'Request data does not exist!'
                   ];
                   curl_close($ch);
                   return json_encode($errorArray);
               }
           }
       }

   }

    public function GetBlockHash($params)
   {
       $url  = self::rpc_url;
       $user = self::rpc_user;
       $pwd  = self::rpc_pwd;
       $Author = $user.':'.$pwd;

       $backStatus = 888;

       //记录请求次数
       $flag = 1;

       while($backStatus != 200){
           $jsonArr = ["method"=>"getblockhash","params"=>[$params],"id"=>1];
           $jsonStr = json_encode($jsonArr);
           $Authorization = base64_encode($Author);

           $ch = curl_init();
           curl_setopt($ch, CURLOPT_POST, 1);
           curl_setopt($ch, CURLOPT_URL, $url);
           curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
           curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
           curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                   'Content-Type: application/json; charset=utf-8',
                   'Content-Length: ' . strlen($jsonStr),
                   'Authorization:Basic '.$Authorization
               )
           );

           $response = curl_exec($ch);

           $responseArray = json_decode($response,1);

           //请求数据是否为空
           if($responseArray){
               if(array_key_exists('error',$responseArray) && $responseArray['error'] != null){
                   curl_close($ch);
                   return json_encode(array('error'=>1,'msg'=>$responseArray['error']['message']));
               }

               $backStatusTemp= curl_getinfo($ch,CURLINFO_HTTP_CODE);

               $flag++;

               if($flag > 3){
                   $errorArray = [
                       'msg'   => 'Request data does not exist!'
                   ];
                   curl_close($ch);
                   return json_encode($errorArray);
               }

               if($backStatusTemp == 200){
                   curl_close($ch);
                   return $response;
               }
           }else{
               $flag++;

               if($flag > 3){
                   $errorArray = [
                       'msg'   => 'Request data does not exist!'
                   ];
                   curl_close($ch);
                   return json_encode($errorArray);
               }
           }
       }
   }

    public function GetBlock($params)
   {
       $url  = self::rpc_url;
       $user = self::rpc_user;
       $pwd  = self::rpc_pwd;
       $Author = $user.':'.$pwd;

       $backStatus = 888;

       //记录请求次数
       $flag = 1;

       while($backStatus != 200){
           $jsonArr = ["method"=>"getblock","params"=>[$params],"id"=>1];
           $jsonStr = json_encode($jsonArr);
           $Authorization = base64_encode($Author);

           $ch = curl_init();
           curl_setopt($ch, CURLOPT_POST, 1);
           curl_setopt($ch, CURLOPT_URL, $url);
           curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
           curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
           curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                   'Content-Type: application/json; charset=utf-8',
                   'Content-Length: ' . strlen($jsonStr),
                   'Authorization:Basic '.$Authorization
               )
           );

           $response = curl_exec($ch);

           $responseArray = json_decode($response,1);

           //请求数据是否为空
           if($responseArray){
               if(array_key_exists('error',$responseArray) && $responseArray['error'] != null){
                   curl_close($ch);
                   return json_encode(array('error'=>1,'msg'=>$responseArray['error']['message']));
               }

               $backStatusTemp= curl_getinfo($ch,CURLINFO_HTTP_CODE);

               $flag++;

               if($flag > 3){
                   $errorArray = [
                       'msg'   => 'Request data does not exist!'
                   ];
                   curl_close($ch);
                   return json_encode($errorArray);
               }

               if($backStatusTemp == 200){
                   curl_close($ch);
                   return $response;
               }
           }else{
               $flag++;

               if($flag > 3){
                   $errorArray = [
                       'msg'   => 'Request data does not exist!'
                   ];
                   curl_close($ch);
                   return json_encode($errorArray);
               }
           }
       }
   }

    public function GetTransactionNew($params)
   {
       $url  = self::rpc_url;
       $user = self::rpc_user;
       $pwd  = self::rpc_pwd;
       $Author = $user.':'.$pwd;

       $backStatus = 888;

       //记录请求次数
       $flag = 1;

       while($backStatus != 200){
           $jsonArr = ["method"=>"gettransactionnew","params"=>[$params],"id"=>1];
           $jsonStr = json_encode($jsonArr);
           $Authorization = base64_encode($Author);

           $ch = curl_init();
           curl_setopt($ch, CURLOPT_POST, 1);
           curl_setopt($ch, CURLOPT_URL, $url);
           curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
           curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
           curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                   'Content-Type: application/json; charset=utf-8',
                   'Content-Length: ' . strlen($jsonStr),
                   'Authorization:Basic '.$Authorization
               )
           );

           $response = curl_exec($ch);

           $responseArray = json_decode($response,1);

           //请求数据是否为空
           if($responseArray){
               if(array_key_exists('error',$responseArray) && $responseArray['error'] != null){
                   curl_close($ch);
                   return json_encode(array('error'=>1,'msg'=>$responseArray['error']['message']));
               }

               $backStatusTemp= curl_getinfo($ch,CURLINFO_HTTP_CODE);

               $flag++;

               if($flag > 3){
                   $errorArray = [
                       'msg'   => 'Request data does not exist!'
                   ];
                   curl_close($ch);
                   return json_encode($errorArray);
               }

               if($backStatusTemp == 200){
                   curl_close($ch);
                   return $response;
               }
           }else{
               $flag++;

               if($flag > 3){
                   $errorArray = [
                       'msg'   => 'Request data does not exist!'
                   ];
                   curl_close($ch);
                   return json_encode($errorArray);
               }
           }
       }
   }

    public function GetBlockCount()
   {
       $url  = self::rpc_url;
       $user = self::rpc_user;
       $pwd  = self::rpc_pwd;
       $Author = $user.':'.$pwd;

       $backStatus = 888;

       //记录请求次数
       $flag = 1;

       while($backStatus != 200){
           $jsonArr = ["method"=>"getblockcount","params"=>[],"id"=>1];
           $jsonStr = json_encode($jsonArr);
           $Authorization = base64_encode($Author);

           $ch = curl_init();
           curl_setopt($ch, CURLOPT_POST, 1);
           curl_setopt($ch, CURLOPT_URL, $url);
           curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
           curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
           curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                   'Content-Type: application/json; charset=utf-8',
                   'Content-Length: ' . strlen($jsonStr),
                   'Authorization:Basic '.$Authorization
               )
           );

           $response = curl_exec($ch);

           $responseArray = json_decode($response,1);

           //请求数据是否为空
           if($responseArray){
               if(array_key_exists('error',$responseArray) && $responseArray['error'] != null){
                   curl_close($ch);
                   return json_encode(array('error'=>1,'msg'=>$responseArray['error']['message']));
               }

               $backStatusTemp= curl_getinfo($ch,CURLINFO_HTTP_CODE);

               $flag++;

               if($flag > 3){
                   $errorArray = [
                       'msg'   => 'Request data does not exist!'
                   ];
                   curl_close($ch);
                   return json_encode($errorArray);
               }

               if($backStatusTemp == 200){
                   curl_close($ch);
                   return $response;
               }
           }else{
               $flag++;

               if($flag > 3){
                   $errorArray = [
                       'msg'   => 'Request data does not exist!'
                   ];
                   curl_close($ch);
                   return json_encode($errorArray);
               }
           }
       }
   }

    public function ListDelegates()
   {
       $url  = self::rpc_url;
       $user = self::rpc_user;
       $pwd  = self::rpc_pwd;
       $Author = $user.':'.$pwd;

       $backStatus = 888;

       //记录请求次数
       $flag = 1;

       while($backStatus != 200){
           $jsonArr = ["method"=>"listdelegates","params"=>[],"id"=>1];
           $jsonStr = json_encode($jsonArr);
           $Authorization = base64_encode($Author);

           $ch = curl_init();
           curl_setopt($ch, CURLOPT_POST, 1);
           curl_setopt($ch, CURLOPT_URL, $url);
           curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
           curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
           curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                   'Content-Type: application/json; charset=utf-8',
                   'Content-Length: ' . strlen($jsonStr),
                   'Authorization:Basic '.$Authorization
               )
           );

           $response = curl_exec($ch);

           $responseArray = json_decode($response,1);

           //请求数据是否为空
           if($responseArray){
               if(array_key_exists('error',$responseArray) && $responseArray['error'] != null){
                   curl_close($ch);
                   return json_encode(array('error'=>1,'msg'=>$responseArray['error']['message']));
               }

               $backStatusTemp= curl_getinfo($ch,CURLINFO_HTTP_CODE);

               $flag++;

               if($flag > 3){
                   $errorArray = [
                       'msg'   => 'Request data does not exist!'
                   ];
                   curl_close($ch);
                   return json_encode($errorArray);
               }

               if($backStatusTemp == 200){
                   curl_close($ch);
                   return $response;
               }
           }else{
               $flag++;

               if($flag > 3){
                   $errorArray = [
                       'msg'   => 'Request data does not exist!'
                   ];
                   curl_close($ch);
                   return json_encode($errorArray);
               }
           }
       }
   }

    public function ListReceivedVotes($params)
   {
       $url  = self::rpc_url;
       $user = self::rpc_user;
       $pwd  = self::rpc_pwd;
       $Author = $user.':'.$pwd;

       $backStatus = 888;

       //记录请求次数
       $flag = 1;

       while($backStatus != 200){
           $jsonArr = ["method"=>"listreceivedvotes","params"=>[$params],"id"=>1];
           $jsonStr = json_encode($jsonArr);
           $Authorization = base64_encode($Author);

           $ch = curl_init();
           curl_setopt($ch, CURLOPT_POST, 1);
           curl_setopt($ch, CURLOPT_URL, $url);
           curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
           curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
           curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                   'Content-Type: application/json; charset=utf-8',
                   'Content-Length: ' . strlen($jsonStr),
                   'Authorization:Basic '.$Authorization
               )
           );

           $response = curl_exec($ch);

           $responseArray = json_decode($response,1);

           //请求数据是否为空
           if($responseArray){
               if(array_key_exists('error',$responseArray) && $responseArray['error'] != null){
                   curl_close($ch);
                   return json_encode(array('error'=>1,'msg'=>$responseArray['error']['message']));
               }

               $backStatusTemp= curl_getinfo($ch,CURLINFO_HTTP_CODE);

               $flag++;

               if($flag > 3){
                   $errorArray = [
                       'msg'   => 'Request data does not exist!'
                   ];
                   curl_close($ch);
                   return json_encode($errorArray);
               }

               if($backStatusTemp == 200){
                   curl_close($ch);
                   return $response;
               }
           }else{
               $flag++;

               if($flag > 3){
                   $errorArray = [
                       'msg'   => 'Request data does not exist!'
                   ];
                   curl_close($ch);
                   return json_encode($errorArray);
               }
           }
       }
   }

    public function ListVotedDelegates($params)
   {
       $url  = self::rpc_url;
       $user = self::rpc_user;
       $pwd  = self::rpc_pwd;
       $Author = $user.':'.$pwd;

       $backStatus = 888;

       //记录请求次数
       $flag = 1;

       while($backStatus != 200){
           $jsonArr = ["method"=>"listvoteddelegates","params"=>[$params],"id"=>1];
           $jsonStr = json_encode($jsonArr);
           $Authorization = base64_encode($Author);

           $ch = curl_init();
           curl_setopt($ch, CURLOPT_POST, 1);
           curl_setopt($ch, CURLOPT_URL, $url);
           curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
           curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
           curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                   'Content-Type: application/json; charset=utf-8',
                   'Content-Length: ' . strlen($jsonStr),
                   'Authorization:Basic '.$Authorization
               )
           );

           $response = curl_exec($ch);

           $responseArray = json_decode($response,1);

           //请求数据是否为空
           if($responseArray){
               if(array_key_exists('error',$responseArray) && $responseArray['error'] != null){
                   curl_close($ch);
                   return json_encode(array('error'=>1,'msg'=>$responseArray['error']['message']));
               }

               $backStatusTemp= curl_getinfo($ch,CURLINFO_HTTP_CODE);

               $flag++;

               if($flag > 3){
                   $errorArray = [
                       'msg'   => 'Request data does not exist!'
                   ];
                   curl_close($ch);
                   return json_encode($errorArray);
               }

               if($backStatusTemp == 200){
                   curl_close($ch);
                   return $response;
               }
           }else{
               $flag++;

               if($flag > 3){
                   $errorArray = [
                       'msg'   => 'Request data does not exist!'
                   ];
                   curl_close($ch);
                   return json_encode($errorArray);
               }
           }
       }
   }

    public function GetDelegateVotes($params)
   {
       $url  = self::rpc_url;
       $user = self::rpc_user;
       $pwd  = self::rpc_pwd;
       $Author = $user.':'.$pwd;

       $backStatus = 888;

       //记录请求次数
       $flag = 1;

       while($backStatus != 200){
           $jsonArr = ["method"=>"getdelegatevotes","params"=>[$params],"id"=>1];
           $jsonStr = json_encode($jsonArr);
           $Authorization = base64_encode($Author);

           $ch = curl_init();
           curl_setopt($ch, CURLOPT_POST, 1);
           curl_setopt($ch, CURLOPT_URL, $url);
           curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
           curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
           curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                   'Content-Type: application/json; charset=utf-8',
                   'Content-Length: ' . strlen($jsonStr),
                   'Authorization:Basic '.$Authorization
               )
           );

           $response = curl_exec($ch);

           $responseArray = json_decode($response,1);

           //请求数据是否为空
           if($responseArray){
               if(array_key_exists('error',$responseArray) && $responseArray['error'] != null){
                   curl_close($ch);
                   return json_encode(array('error'=>1,'msg'=>$responseArray['error']['message']));
               }

               $backStatusTemp= curl_getinfo($ch,CURLINFO_HTTP_CODE);

               $flag++;

               if($flag > 3){
                   $errorArray = [
                       'msg'   => 'Request data does not exist!'
                   ];
                   curl_close($ch);
                   return json_encode($errorArray);
               }

               if($backStatusTemp == 200){
                   curl_close($ch);
                   return $response;
               }
           }else{
               $flag++;

               if($flag > 3){
                   $errorArray = [
                       'msg'   => 'Request data does not exist!'
                   ];
                   curl_close($ch);
                   return json_encode($errorArray);
               }
           }
       }
   }

    public function SearchHash($params)
   {
       $url  = self::rpc_url;
       $user = self::rpc_user;
       $pwd  = self::rpc_pwd;
       $Author = $user.':'.$pwd;

       $backStatus = 888;

       //记录请求次数
       $flag = 1;

       while($backStatus != 200){
           $jsonArr = ["method"=>"gettransactionnew","params"=>[$params],"id"=>1];
           $jsonStr = json_encode($jsonArr);
           $Authorization = base64_encode($Author);

           $ch = curl_init();
           curl_setopt($ch, CURLOPT_POST, 1);
           curl_setopt($ch, CURLOPT_URL, $url);
           curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
           curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
           curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                   'Content-Type: application/json; charset=utf-8',
                   'Content-Length: ' . strlen($jsonStr),
                   'Authorization:Basic '.$Authorization
               )
           );

           $response = curl_exec($ch);

           $backStatusTemp= curl_getinfo($ch,CURLINFO_HTTP_CODE);



           //如果查询交易hash为空，查询块hash
           if($backStatusTemp != 200 && $flag >3){

               $backStatus1 = 888;

               //记录请求次数
               $flag1 = 1;

               while($backStatus1 != 200){
                   $jsonArr1 = ["method"=>"getblock","params"=>[$params],"id"=>1];
                   $jsonStr1 = json_encode($jsonArr1);
                   $Authorization = base64_encode($Author);

                   $ch1 = curl_init();
                   curl_setopt($ch1, CURLOPT_POST, 1);
                   curl_setopt($ch1, CURLOPT_URL, $url);
                   curl_setopt($ch1, CURLOPT_POSTFIELDS, $jsonStr1);
                   curl_setopt($ch1, CURLOPT_RETURNTRANSFER, 1);
                   curl_setopt($ch1, CURLOPT_HTTPHEADER, array(
                           'Content-Type: application/json; charset=utf-8',
                           'Content-Length: ' . strlen($jsonStr1),
                           'Authorization:Basic '.$Authorization
                       )
                   );

                   $response1 = curl_exec($ch1);

                   $responseArray1 = json_decode($response1,1);

                   if(array_key_exists('error',$responseArray1) && $responseArray1['error'] != null){
                       curl_close($ch);
                       return json_encode(array('error'=>1,'msg'=>$responseArray1['error']['message']));
                   }

                   $backStatusTemp1= curl_getinfo($ch1,CURLINFO_HTTP_CODE);

                   $flag1++;


                   if($flag1 > 3){
                       $errorArray1 = [
                           'msg'   => 'Request data does not exist!'
                       ];
                       curl_close($ch1);
                       return json_encode($errorArray1);
                   }

                   if($backStatusTemp1 == 200){
                       curl_close($ch1);
                       $resArray1 = array(
                           'type' => 'BlockHash',
                           'data' => $response1
                       );
                       return json_encode($resArray1);
                   }
               }
           }

           $flag++;

           if($backStatusTemp == 200){
               curl_close($ch);
               $resArray = array(
                   'type' => 'TXHash',
                   'data' => $response
               );
               return json_encode($resArray);
           }
       }
   }

    public function GetTxByAddr($params)
   {
       $url  = self::rpc_url;
       $user = self::rpc_user;
       $pwd  = self::rpc_pwd;
       $Author = $user.':'.$pwd;

       $backStatus = 888;

       //记录请求次数
       $flag = 1;

       while($backStatus != 200){
           $jsonArr = ["method"=>"getaddresstxids","params"=>[$params],"id"=>1];
           $jsonStr = json_encode($jsonArr);
           $Authorization = base64_encode($Author);

           $ch = curl_init();
           curl_setopt($ch, CURLOPT_POST, 1);
           curl_setopt($ch, CURLOPT_URL, $url);
           curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
           curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
           curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                   'Content-Type: application/json; charset=utf-8',
                   'Content-Length: ' . strlen($jsonStr),
                   'Authorization:Basic '.$Authorization
               )
           );

           $response = curl_exec($ch);

           $responseArray = json_decode($response,1);

           //请求数据是否为空
           if($responseArray){
               if(array_key_exists('error',$responseArray) && $responseArray['error'] != null){
                   curl_close($ch);
                   return json_encode(array('error'=>1,'msg'=>$responseArray['error']['message']));
               }

               $backStatusTemp= curl_getinfo($ch,CURLINFO_HTTP_CODE);

               $flag++;

               if($flag > 3){
                   $errorArray = [
                       'msg'   => 'Request data does not exist!'
                   ];
                   curl_close($ch);
                   return json_encode($errorArray);
               }

               if($backStatusTemp == 200){
                   curl_close($ch);
                   return $response;
               }
           }else{
               $flag++;

               if($flag > 3){
                   $errorArray = [
                       'msg'   => 'Request data does not exist!'
                   ];
                   curl_close($ch);
                   return json_encode($errorArray);
               }
           }
       }
   }

    public function ListUnSpent($params)
   {
       $url  = self::rpc_url;
       $user = self::rpc_user;
       $pwd  = self::rpc_pwd;
       $Author = $user.':'.$pwd;

       $backStatus = 888;

       //记录请求次数
       $flag = 1;

       while($backStatus != 200){
           $jsonArr = ["method"=>"listunspent","params"=>[0,1000000,[$params]],"id"=>1];
           $jsonStr = json_encode($jsonArr);
           $Authorization = base64_encode($Author);

           $ch = curl_init();
           curl_setopt($ch, CURLOPT_POST, 1);
           curl_setopt($ch, CURLOPT_URL, $url);
           curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
           curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
           curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                   'Content-Type: application/json; charset=utf-8',
                   'Content-Length: ' . strlen($jsonStr),
                   'Authorization:Basic '.$Authorization
               )
           );

           $response = curl_exec($ch);

           $responseArray = json_decode($response,1);

           //请求数据是否为空
           if($responseArray){
               if(array_key_exists('error',$responseArray) && $responseArray['error'] != null){
                   curl_close($ch);
                   return json_encode(array('error'=>1,'msg'=>$responseArray['error']['message']));
               }

               $backStatusTemp= curl_getinfo($ch,CURLINFO_HTTP_CODE);

               $flag++;

               if($flag > 3){
                   $errorArray = [
                       'msg'   => 'Request data does not exist!'
                   ];
                   curl_close($ch);
                   return json_encode($errorArray);
               }

               if($backStatusTemp == 200){
                   curl_close($ch);
                   return $response;
               }
           }else{
               $flag++;

               if($flag > 3){
                   $errorArray = [
                       'msg'   => 'Request data does not exist!'
                   ];
                   curl_close($ch);
                   return json_encode($errorArray);
               }
           }
       }
   }

    public function SendRawTransaction($params)
   {
       $url  = self::rpc_url;
       $user = self::rpc_user;
       $pwd  = self::rpc_pwd;
       $Author = $user.':'.$pwd;

       $backStatus = 888;

       //记录请求次数
       $flag = 1;

       while($backStatus != 200){
           $jsonArr = ["method"=>"sendrawtransaction","params"=>[$params],"id"=>1];
           $jsonStr = json_encode($jsonArr);
           $Authorization = base64_encode($Author);

           $ch = curl_init();
           curl_setopt($ch, CURLOPT_POST, 1);
           curl_setopt($ch, CURLOPT_URL, $url);
           curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
           curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
           curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                   'Content-Type: application/json; charset=utf-8',
                   'Content-Length: ' . strlen($jsonStr),
                   'Authorization:Basic '.$Authorization
               )
           );

           $response = curl_exec($ch);

           $responseArray = json_decode($response,1);

           //请求数据是否为空
           if($responseArray){
               if(array_key_exists('error',$responseArray) && $responseArray['error'] != null){
                   curl_close($ch);
                   return json_encode(array('error'=>1,'msg'=>$responseArray['error']['message']));
               }

               $backStatusTemp= curl_getinfo($ch,CURLINFO_HTTP_CODE);

               $flag++;

               if($flag > 3){
                   $errorArray = [
                       'msg'   => 'Request data does not exist!'
                   ];
                   curl_close($ch);
                   return json_encode($errorArray);
               }

               if($backStatusTemp == 200){
                   curl_close($ch);
                   return $response;
               }
           }else{
               $flag++;

               if($flag > 3){
                   $errorArray = [
                       'msg'   => 'Request data does not exist!'
                   ];
                   curl_close($ch);
                   return json_encode($errorArray);
               }
           }
       }
   }

    public function GetRichList($params)
   {
       $url  = self::rpc_url;
//       $url = "http://47.96.169.139:19332";
       $user = self::rpc_user;
       $pwd  = self::rpc_pwd;
       $Author = $user.':'.$pwd;

       $backStatus = 888;

       //记录请求次数
       $flag = 1;

       while($backStatus != 200){
           $jsonArr = ["method"=>"getcoinrank","params"=>[$params],"id"=>1];
           $jsonStr = json_encode($jsonArr);
           $Authorization = base64_encode($Author);

           $ch = curl_init();
           curl_setopt($ch, CURLOPT_POST, 1);
           curl_setopt($ch, CURLOPT_URL, $url);
           curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
           curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
           curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                   'Content-Type: application/json; charset=utf-8',
                   'Content-Length: ' . strlen($jsonStr),
                   'Authorization:Basic '.$Authorization
               )
           );

           $response = curl_exec($ch);

           $responseArray = json_decode($response,1);

           //请求数据是否为空
           if($responseArray){
               if(array_key_exists('error',$responseArray) && $responseArray['error'] != null){
                   curl_close($ch);
                   return json_encode(array('error'=>1,'msg'=>$responseArray['error']['message']));
               }

               $backStatusTemp= curl_getinfo($ch,CURLINFO_HTTP_CODE);

               $flag++;

               if($flag > 3){
                   $errorArray = [
                       'msg'   => 'Request data does not exist!'
                   ];
                   curl_close($ch);
                   return json_encode($errorArray);
               }

               if($backStatusTemp == 200){
                   curl_close($ch);
                   return $response;
               }
           }else{
               $flag++;

               if($flag > 3){
                   $errorArray = [
                       'msg'   => 'Request data does not exist!'
                   ];
                   curl_close($ch);
                   return json_encode($errorArray);
               }
           }
       }
   }

    public function GetRichPer($p1,$p2)
   {
       $url  = self::rpc_url;
//       $url = "http://47.96.169.139:19332";
       $user = self::rpc_user;
       $pwd  = self::rpc_pwd;
       $Author = $user.':'.$pwd;

       $backStatus = 888;

       //记录请求次数
       $flag = 1;

       while($backStatus != 200){
           $jsonArr = ["method"=>"getcoindistribution","params"=>[$p1,$p2],"id"=>1];
           $jsonStr = json_encode($jsonArr);
           $Authorization = base64_encode($Author);

           $ch = curl_init();
           curl_setopt($ch, CURLOPT_POST, 1);
           curl_setopt($ch, CURLOPT_URL, $url);
           curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
           curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
           curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                   'Content-Type: application/json; charset=utf-8',
                   'Content-Length: ' . strlen($jsonStr),
                   'Authorization:Basic '.$Authorization
               )
           );

           $response = curl_exec($ch);

           $responseArray = json_decode($response,1);

           //请求数据是否为空
           if($responseArray){
               if(array_key_exists('error',$responseArray) && $responseArray['error'] != null){
                   curl_close($ch);
                   return json_encode(array('error'=>1,'msg'=>$responseArray['error']['message']));
               }

               $backStatusTemp= curl_getinfo($ch,CURLINFO_HTTP_CODE);

               $flag++;

               if($flag > 3){
                   $errorArray = [
                       'msg'   => 'Request data does not exist!'
                   ];
                   curl_close($ch);
                   return json_encode($errorArray);
               }

               if($backStatusTemp == 200){
                   curl_close($ch);
                   return $response;
               }
           }else{
               $flag++;

               if($flag > 3){
                   $errorArray = [
                       'msg'   => 'Request data does not exist!'
                   ];
                   curl_close($ch);
                   return json_encode($errorArray);
               }
           }
       }
   }

    public function GetCommitteesList()
   {
       $url  = self::rpc_url;
//       $url = "http://47.96.169.139:19332";
       $user = self::rpc_user;
       $pwd  = self::rpc_pwd;
       $Author = $user.':'.$pwd;

       $backStatus = 888;

       //记录请求次数
       $flag = 1;

       while($backStatus != 200){
           $jsonArr = ["method"=>"listcommittees","params"=>[],"id"=>1];
           $jsonStr = json_encode($jsonArr);
           $Authorization = base64_encode($Author);

           $ch = curl_init();
           curl_setopt($ch, CURLOPT_POST, 1);
           curl_setopt($ch, CURLOPT_URL, $url);
           curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
           curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
           curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                   'Content-Type: application/json; charset=utf-8',
                   'Content-Length: ' . strlen($jsonStr),
                   'Authorization:Basic '.$Authorization
               )
           );

           $response = curl_exec($ch);

           $responseArray = json_decode($response,1);

           //请求数据是否为空
           if($responseArray){
               if(array_key_exists('error',$responseArray) && $responseArray['error'] != null){
                   curl_close($ch);
                   return json_encode(array('error'=>1,'msg'=>$responseArray['error']['message']));
               }

               $backStatusTemp= curl_getinfo($ch,CURLINFO_HTTP_CODE);

               $flag++;

               if($flag > 3){
                   $errorArray = [
                       'msg'   => 'Request data does not exist!'
                   ];
                   curl_close($ch);
                   return json_encode($errorArray);
               }

               if($backStatusTemp == 200){
                   curl_close($ch);
                   return $response;
               }
           }else{
               $flag++;

               if($flag > 3){
                   $errorArray = [
                       'msg'   => 'Request data does not exist!'
                   ];
                   curl_close($ch);
                   return json_encode($errorArray);
               }
           }
       }
   }

    public function GetCommitteeVotesList($params)
   {
       $url  = self::rpc_url;
//       $url = "http://47.96.169.139:19332";
       $user = self::rpc_user;
       $pwd  = self::rpc_pwd;
       $Author = $user.':'.$pwd;

       $backStatus = 888;

       //记录请求次数
       $flag = 1;

       while($backStatus != 200){
           $jsonArr = ["method"=>"listcommitteevoters","params"=>[$params],"id"=>1];
           $jsonStr = json_encode($jsonArr);
           $Authorization = base64_encode($Author);

           $ch = curl_init();
           curl_setopt($ch, CURLOPT_POST, 1);
           curl_setopt($ch, CURLOPT_URL, $url);
           curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
           curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
           curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                   'Content-Type: application/json; charset=utf-8',
                   'Content-Length: ' . strlen($jsonStr),
                   'Authorization:Basic '.$Authorization
               )
           );

           $response = curl_exec($ch);

           $responseArray = json_decode($response,1);

           //请求数据是否为空
           if($responseArray){
               if(array_key_exists('error',$responseArray) && $responseArray['error'] != null){
                   curl_close($ch);
                   return json_encode(array('error'=>1,'msg'=>$responseArray['error']['message']));
               }

               $backStatusTemp= curl_getinfo($ch,CURLINFO_HTTP_CODE);

               $flag++;

               if($flag > 3){
                   $errorArray = [
                       'msg'   => 'Request data does not exist!'
                   ];
                   curl_close($ch);
                   return json_encode($errorArray);
               }

               if($backStatusTemp == 200){
                   curl_close($ch);
                   return $response;
               }
           }else{
               $flag++;

               if($flag > 3){
                   $errorArray = [
                       'msg'   => 'Request data does not exist!'
                   ];
                   curl_close($ch);
                   return json_encode($errorArray);
               }
           }
       }
   }

    public function GetVotedCommitteeList($params)
    {
       $url  = self::rpc_url;
//        $url = "http://47.96.169.139:19332";
        $user = self::rpc_user;
        $pwd  = self::rpc_pwd;
        $Author = $user.':'.$pwd;

        $backStatus = 888;

        //记录请求次数
        $flag = 1;

        while($backStatus != 200){
            $jsonArr = ["method"=>"listvotercommittees","params"=>[$params],"id"=>1];
            $jsonStr = json_encode($jsonArr);
            $Authorization = base64_encode($Author);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json; charset=utf-8',
                    'Content-Length: ' . strlen($jsonStr),
                    'Authorization:Basic '.$Authorization
                )
            );

            $response = curl_exec($ch);

            $responseArray = json_decode($response,1);

            //请求数据是否为空
            if($responseArray){
                if(array_key_exists('error',$responseArray) && $responseArray['error'] != null){
                    curl_close($ch);
                    return json_encode(array('error'=>1,'msg'=>$responseArray['error']['message']));
                }

                $backStatusTemp= curl_getinfo($ch,CURLINFO_HTTP_CODE);

                $flag++;

                if($flag > 3){
                    $errorArray = [
                        'msg'   => 'Request data does not exist!'
                    ];
                    curl_close($ch);
                    return json_encode($errorArray);
                }

                if($backStatusTemp == 200){
                    curl_close($ch);
                    return $response;
                }
            }else{
                $flag++;

                if($flag > 3){
                    $errorArray = [
                        'msg'   => 'Request data does not exist!'
                    ];
                    curl_close($ch);
                    return json_encode($errorArray);
                }
            }
        }
    }

    public function GetListBills()
    {
        $url  = self::rpc_url;
//        $url = "http://47.96.169.139:19332";
        $user = self::rpc_user;
        $pwd  = self::rpc_pwd;
        $Author = $user.':'.$pwd;

        $backStatus = 888;

        //记录请求次数
        $flag = 1;

        while($backStatus != 200){
            $jsonArr = ["method"=>"listbills","params"=>[],"id"=>1];
            $jsonStr = json_encode($jsonArr);
            $Authorization = base64_encode($Author);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json; charset=utf-8',
                    'Content-Length: ' . strlen($jsonStr),
                    'Authorization:Basic '.$Authorization
                )
            );

            $response = curl_exec($ch);

            $responseArray = json_decode($response,1);

            //请求数据是否为空
            if($responseArray){
                if(array_key_exists('error',$responseArray) && $responseArray['error'] != null){
                    curl_close($ch);
                    return json_encode(array('error'=>1,'msg'=>$responseArray['error']['message']));
                }

                $backStatusTemp= curl_getinfo($ch,CURLINFO_HTTP_CODE);

                $flag++;

                if($flag > 3){
                    $errorArray = [
                        'msg'   => 'Request data does not exist!'
                    ];
                    curl_close($ch);
                    return json_encode($errorArray);
                }

                if($backStatusTemp == 200){
                    curl_close($ch);
                    return $response;
                }
            }else{
                $flag++;

                if($flag > 3){
                    $errorArray = [
                        'msg'   => 'Request data does not exist!'
                    ];
                    curl_close($ch);
                    return json_encode($errorArray);
                }
            }
        }
    }

    public function GetBill($param)
    {
        $url  = self::rpc_url;
//        $url = "http://47.96.169.139:19332";
        $user = self::rpc_user;
        $pwd  = self::rpc_pwd;
        $Author = $user.':'.$pwd;

        $backStatus = 888;

        //记录请求次数
        $flag = 1;

        while($backStatus != 200){
            $jsonArr = ["method"=>"getbill","params"=>[$param],"id"=>1];
            $jsonStr = json_encode($jsonArr);
            $Authorization = base64_encode($Author);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json; charset=utf-8',
                    'Content-Length: ' . strlen($jsonStr),
                    'Authorization:Basic '.$Authorization
                )
            );

            $response = curl_exec($ch);

            $responseArray = json_decode($response,1);

            //请求数据是否为空
            if($responseArray){
                if(array_key_exists('error',$responseArray) && $responseArray['error'] != null){
                    curl_close($ch);
                    return json_encode(array('error'=>1,'msg'=>$responseArray['error']['message']));
                }

                $backStatusTemp= curl_getinfo($ch,CURLINFO_HTTP_CODE);

                $flag++;

                if($flag > 3){
                    $errorArray = [
                        'msg'   => 'Request data does not exist!'
                    ];
                    curl_close($ch);
                    return json_encode($errorArray);
                }

                if($backStatusTemp == 200){
                    curl_close($ch);
                    return $response;
                }
            }else{
                $flag++;

                if($flag > 3){
                    $errorArray = [
                        'msg'   => 'Request data does not exist!'
                    ];
                    curl_close($ch);
                    return json_encode($errorArray);
                }
            }
        }
    }

    public function GetListBillVoters($param)
    {
        $url  = self::rpc_url;
//        $url = "http://47.96.169.139:19332";
        $user = self::rpc_user;
        $pwd  = self::rpc_pwd;
        $Author = $user.':'.$pwd;

        $backStatus = 888;

        //记录请求次数
        $flag = 1;

        while($backStatus != 200){
            $jsonArr = ["method"=>"listbillvoters","params"=>[$param],"id"=>1];
            $jsonStr = json_encode($jsonArr);
            $Authorization = base64_encode($Author);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json; charset=utf-8',
                    'Content-Length: ' . strlen($jsonStr),
                    'Authorization:Basic '.$Authorization
                )
            );

            $response = curl_exec($ch);

            $responseArray = json_decode($response,1);

            //请求数据是否为空
            if($responseArray){
                if(array_key_exists('error',$responseArray) && $responseArray['error'] != null){
                    curl_close($ch);
                    return json_encode(array('error'=>1,'msg'=>$responseArray['error']['message']));
                }

                $backStatusTemp= curl_getinfo($ch,CURLINFO_HTTP_CODE);

                $flag++;

                if($flag > 3){
                    $errorArray = [
                        'msg'   => 'Request data does not exist!'
                    ];
                    curl_close($ch);
                    return json_encode($errorArray);
                }

                if($backStatusTemp == 200){
                    curl_close($ch);
                    return $response;
                }
            }else{
                $flag++;

                if($flag > 3){
                    $errorArray = [
                        'msg'   => 'Request data does not exist!'
                    ];
                    curl_close($ch);
                    return json_encode($errorArray);
                }
            }
        }
    }

    public function GetListVoterBills($param)
    {
        $url  = self::rpc_url;
//        $url = "http://47.96.169.139:19332";
        $user = self::rpc_user;
        $pwd  = self::rpc_pwd;
        $Author = $user.':'.$pwd;

        $backStatus = 888;

        //记录请求次数
        $flag = 1;

        while($backStatus != 200){
            $jsonArr = ["method"=>"listvoterbills","params"=>[$param],"id"=>1];
            $jsonStr = json_encode($jsonArr);
            $Authorization = base64_encode($Author);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json; charset=utf-8',
                    'Content-Length: ' . strlen($jsonStr),
                    'Authorization:Basic '.$Authorization
                )
            );

            $response = curl_exec($ch);

            $responseArray = json_decode($response,1);

            //请求数据是否为空
            if($responseArray){
                if(array_key_exists('error',$responseArray) && $responseArray['error'] != null){
                    curl_close($ch);
                    return json_encode(array('error'=>1,'msg'=>$responseArray['error']['message']));
                }

                $backStatusTemp= curl_getinfo($ch,CURLINFO_HTTP_CODE);

                $flag++;

                if($flag > 3){
                    $errorArray = [
                        'msg'   => 'Request data does not exist!'
                    ];
                    curl_close($ch);
                    return json_encode($errorArray);
                }

                if($backStatusTemp == 200){
                    curl_close($ch);
                    return $response;
                }
            }else{
                $flag++;

                if($flag > 3){
                    $errorArray = [
                        'msg'   => 'Request data does not exist!'
                    ];
                    curl_close($ch);
                    return json_encode($errorArray);
                }
            }
        }
    }

    public function GetCommitteeVotes($param)
    {
        $url  = self::rpc_url;
//        $url = "http://47.96.169.139:19332";
        $user = self::rpc_user;
        $pwd  = self::rpc_pwd;
        $Author = $user.':'.$pwd;

        $backStatus = 888;

        //记录请求次数
        $flag = 1;

        while($backStatus != 200){
            $jsonArr = ["method"=>"getcommittee","params"=>[$param],"id"=>1];
            $jsonStr = json_encode($jsonArr);
            $Authorization = base64_encode($Author);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json; charset=utf-8',
                    'Content-Length: ' . strlen($jsonStr),
                    'Authorization:Basic '.$Authorization
                )
            );

            $response = curl_exec($ch);

            $responseArray = json_decode($response,1);

            //请求数据是否为空
            if($responseArray){
                if(array_key_exists('error',$responseArray) && $responseArray['error'] != null){
                    curl_close($ch);
                    return json_encode(array('error'=>1,'msg'=>$responseArray['error']['message']));
                }

                $backStatusTemp= curl_getinfo($ch,CURLINFO_HTTP_CODE);

                $flag++;

                if($flag > 3){
                    $errorArray = [
                        'msg'   => 'Request data does not exist!'
                    ];
                    curl_close($ch);
                    return json_encode($errorArray);
                }

                if($backStatusTemp == 200){
                    curl_close($ch);
                    return $response;
                }
            }else{
                $flag++;

                if($flag > 3){
                    $errorArray = [
                        'msg'   => 'Request data does not exist!'
                    ];
                    curl_close($ch);
                    return json_encode($errorArray);
                }
            }
        }
    }

    public function GetListCommitteeBills($param)
    {
        $url  = self::rpc_url;
//        $url = "http://47.96.169.139:19332";
        $user = self::rpc_user;
        $pwd  = self::rpc_pwd;
        $Author = $user.':'.$pwd;

        $backStatus = 888;

        //记录请求次数
        $flag = 1;

        while($backStatus != 200){
            $jsonArr = ["method"=>"listcommitteebills","params"=>[$param],"id"=>1];
            $jsonStr = json_encode($jsonArr);
            $Authorization = base64_encode($Author);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json; charset=utf-8',
                    'Content-Length: ' . strlen($jsonStr),
                    'Authorization:Basic '.$Authorization
                )
            );

            $response = curl_exec($ch);

            $responseArray = json_decode($response,1);

            //请求数据是否为空
            if($responseArray){
                if(array_key_exists('error',$responseArray) && $responseArray['error'] != null){
                    curl_close($ch);
                    return json_encode(array('error'=>1,'msg'=>$responseArray['error']['message']));
                }

                $backStatusTemp= curl_getinfo($ch,CURLINFO_HTTP_CODE);

                $flag++;

                if($flag > 3){
                    $errorArray = [
                        'msg'   => 'Request data does not exist!'
                    ];
                    curl_close($ch);
                    return json_encode($errorArray);
                }

                if($backStatusTemp == 200){
                    curl_close($ch);
                    return $response;
                }
            }else{
                $flag++;

                if($flag > 3){
                    $errorArray = [
                        'msg'   => 'Request data does not exist!'
                    ];
                    curl_close($ch);
                    return json_encode($errorArray);
                }
            }
        }
    }

    public function GetTokenInfo()
    {
//        $url  = self::rpc_url;
        $url = "http://47.75.152.186:19332";
        $user = self::rpc_user;
        $pwd  = self::rpc_pwd;
        $Author = $user.':'.$pwd;

        $backStatus = 888;

        //记录请求次数
        $flag = 1;

        $params = func_num_args();

        if ($params){
            $param = func_get_arg(0);
            while($backStatus != 200){
                $jsonArr = ["method"=>"gettokeninfo","params"=>[$param],"id"=>1];
                $jsonStr = json_encode($jsonArr);
                $Authorization = base64_encode($Author);

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                        'Content-Type: application/json; charset=utf-8',
                        'Content-Length: ' . strlen($jsonStr),
                        'Authorization:Basic '.$Authorization
                    )
                );

                $response = curl_exec($ch);

                $responseArray = json_decode($response,1);

                //请求数据是否为空
                if($responseArray){
                    if(array_key_exists('error',$responseArray) && $responseArray['error'] != null){
                        curl_close($ch);
                        return json_encode(array('error'=>1,'msg'=>$responseArray['error']['message']));
                    }

                    $backStatusTemp= curl_getinfo($ch,CURLINFO_HTTP_CODE);

                    $flag++;

                    if($flag > 3){
//                    $errorArray = [
//                        'msg'   => 'Request data does not exist!'
//                    ];
                        curl_close($ch);
                        $uTool = new UniversalTools();
                        $uTool->HttpStatus(503);
//                    return json_encode($errorArray);
                    }

                    if($backStatusTemp == 200){
                        curl_close($ch);
                        return $response;
                    }
                }else{
                    $flag++;

                    if($flag > 3){
//                    $errorArray = [
//                        'msg'   => 'Request data does not exist!'
//                    ];
                        curl_close($ch);
                        $uTool = new UniversalTools();
                        $uTool->HttpStatus(503);
//                    return json_encode($errorArray);
                    }
                }
            }
        }else{
            while($backStatus != 200){
                $jsonArr = ["method"=>"gettokeninfo","params"=>[],"id"=>1];
                $jsonStr = json_encode($jsonArr);
                $Authorization = base64_encode($Author);

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                        'Content-Type: application/json; charset=utf-8',
                        'Content-Length: ' . strlen($jsonStr),
                        'Authorization:Basic '.$Authorization
                    )
                );

                $response = curl_exec($ch);

                $responseArray = json_decode($response,1);


                //请求数据是否为空
                if($responseArray){
                    if(array_key_exists('error',$responseArray) && $responseArray['error'] != null){
                        curl_close($ch);
                        return json_encode(array('error'=>1,'msg'=>$responseArray['error']['message']));
                    }

                    $backStatusTemp= curl_getinfo($ch,CURLINFO_HTTP_CODE);

                    $flag++;

                    if($flag > 3){
//                    $errorArray = [
//                        'msg'   => 'Request data does not exist!'
//                    ];
                        curl_close($ch);
                        $uTool = new UniversalTools();
                        $uTool->HttpStatus(503);
//                    return json_encode($errorArray);
                    }

                    if($backStatusTemp == 200){
                        curl_close($ch);
                        return $response;
                    }
                }else{
                    $flag++;

                    if($flag > 3){
//                    $errorArray = [
//                        'msg'   => 'Request data does not exist!'
//                    ];
                        curl_close($ch);
                        $uTool = new UniversalTools();
                        $uTool->HttpStatus(503);
//                    return json_encode($errorArray);
                    }
                }
            }
        }
    }

    public function GetTokenBalance()
    {
        $url = "http://47.75.152.186:19332";
        $user = self::rpc_user;
        $pwd  = self::rpc_pwd;
        $Author = $user.':'.$pwd;

        $backStatus = 888;

        //记录请求次数
        $flag = 1;

        $params = func_num_args();

        if ($params == 1){
            $param = func_get_arg(0);
            while($backStatus != 200){
                $jsonArr = ["method"=>"gettokenbalance","params"=>[$param],"id"=>1];
                $jsonStr = json_encode($jsonArr);
                $Authorization = base64_encode($Author);

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                        'Content-Type: application/json; charset=utf-8',
                        'Content-Length: ' . strlen($jsonStr),
                        'Authorization:Basic '.$Authorization
                    )
                );

                $response = curl_exec($ch);

                $responseArray = json_decode($response,1);

                //请求数据是否为空
                if($responseArray){
                    if(array_key_exists('error',$responseArray) && $responseArray['error'] != null){
                        curl_close($ch);
                        return json_encode(array('error'=>1,'msg'=>$responseArray['error']['message']));
                    }

                    $backStatusTemp= curl_getinfo($ch,CURLINFO_HTTP_CODE);

                    $flag++;

                    if($flag > 3){
//                    $errorArray = [
//                        'msg'   => 'Request data does not exist!'
//                    ];
                        curl_close($ch);
                        $uTool = new UniversalTools();
                        $uTool->HttpStatus(503);
//                    return json_encode($errorArray);
                    }

                    if($backStatusTemp == 200){
                        curl_close($ch);
                        return $response;
                    }
                }else{
                    $flag++;

                    if($flag > 3){
//                    $errorArray = [
//                        'msg'   => 'Request data does not exist!'
//                    ];
                        curl_close($ch);
                        $uTool = new UniversalTools();
                        $uTool->HttpStatus(503);
//                    return json_encode($errorArray);
                    }
                }
            }
        }elseif ($params == 2){
            $param1 = func_get_arg(0);
            $param2 = func_get_arg(1);
            while($backStatus != 200){
                $jsonArr = ["method"=>"gettokenbalance","params"=>[$param1,$param2],"id"=>1];
                $jsonStr = json_encode($jsonArr);
                $Authorization = base64_encode($Author);

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                        'Content-Type: application/json; charset=utf-8',
                        'Content-Length: ' . strlen($jsonStr),
                        'Authorization:Basic '.$Authorization
                    )
                );

                $response = curl_exec($ch);

                $responseArray = json_decode($response,1);


                //请求数据是否为空
                if($responseArray){
                    if(array_key_exists('error',$responseArray) && $responseArray['error'] != null){
                        curl_close($ch);
                        return json_encode(array('error'=>1,'msg'=>$responseArray['error']['message']));
                    }

                    $backStatusTemp= curl_getinfo($ch,CURLINFO_HTTP_CODE);

                    $flag++;

                    if($flag > 3){
//                    $errorArray = [
//                        'msg'   => 'Request data does not exist!'
//                    ];
                        curl_close($ch);
                        $uTool = new UniversalTools();
                        $uTool->HttpStatus(503);
//                    return json_encode($errorArray);
                    }

                    if($backStatusTemp == 200){
                        curl_close($ch);
                        return $response;
                    }
                }else{
                    $flag++;

                    if($flag > 3){
//                    $errorArray = [
//                        'msg'   => 'Request data does not exist!'
//                    ];
                        curl_close($ch);
                        $uTool = new UniversalTools();
                        $uTool->HttpStatus(503);
//                    return json_encode($errorArray);
                    }
                }
            }
        }
    }

    public function GetAddressName($param)
    {
        $url = "http://47.75.152.186:19332";
        $user = self::rpc_user;
        $pwd  = self::rpc_pwd;
        $Author = $user.':'.$pwd;

        $backStatus = 888;

        //记录请求次数
        $flag = 1;

        while($backStatus != 200){
            $jsonArr = ["method"=>"getaddressname","params"=>[$param],"id"=>1];
            $jsonStr = json_encode($jsonArr);
            $Authorization = base64_encode($Author);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json; charset=utf-8',
                    'Content-Length: ' . strlen($jsonStr),
                    'Authorization:Basic '.$Authorization
                )
            );

            $response = curl_exec($ch);

            $responseArray = json_decode($response,1);

            //请求数据是否为空
            if($responseArray){
                if(array_key_exists('error',$responseArray) && $responseArray['error'] != null){
                    curl_close($ch);
                    return json_encode(array('error'=>1,'msg'=>$responseArray['error']['message']));
                }

                $backStatusTemp= curl_getinfo($ch,CURLINFO_HTTP_CODE);

                $flag++;

                if($flag > 3){
                    $errorArray = [
                        'msg'   => 'Request data does not exist!'
                    ];
                    curl_close($ch);
                    return json_encode($errorArray);
                }

                if($backStatusTemp == 200){
                    curl_close($ch);
                    return $response;
                }
            }else{
                $flag++;

                if($flag > 3){
                    $errorArray = [
                        'msg'   => 'Request data does not exist!'
                    ];
                    curl_close($ch);
                    return json_encode($errorArray);
                }
            }
        }
    }

    public function GetNameAddress($param)
    {
        $url = "http://47.75.152.186:19332";
        $user = self::rpc_user;
        $pwd  = self::rpc_pwd;
        $Author = $user.':'.$pwd;

        $backStatus = 888;

        //记录请求次数
        $flag = 1;

        while($backStatus != 200){
            $jsonArr = ["method"=>"getnameaddress","params"=>[$param],"id"=>1];
            $jsonStr = json_encode($jsonArr);
            $Authorization = base64_encode($Author);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json; charset=utf-8',
                    'Content-Length: ' . strlen($jsonStr),
                    'Authorization:Basic '.$Authorization
                )
            );

            $response = curl_exec($ch);

            $responseArray = json_decode($response,1);

            //请求数据是否为空
            if($responseArray){
                if(array_key_exists('error',$responseArray) && $responseArray['error'] != null){
                    curl_close($ch);
                    return json_encode(array('error'=>1,'msg'=>$responseArray['error']['message']));
                }

                $backStatusTemp= curl_getinfo($ch,CURLINFO_HTTP_CODE);

                $flag++;

                if($flag > 3){
                    $errorArray = [
                        'msg'   => 'Request data does not exist!'
                    ];
                    curl_close($ch);
                    return json_encode($errorArray);
                }

                if($backStatusTemp == 200){
                    curl_close($ch);
                    return $response;
                }
            }else{
                $flag++;

                if($flag > 3){
                    $errorArray = [
                        'msg'   => 'Request data does not exist!'
                    ];
                    curl_close($ch);
                    return json_encode($errorArray);
                }
            }
        }
    }

    public function Getaddresstokentxids($addr,$startBlock="0",$addrToken="")
    {
        $url = "http://47.75.152.186:19332";
        $user = self::rpc_user;
        $pwd  = self::rpc_pwd;
        $Author = $user.':'.$pwd;

        $backStatus = 888;

        //记录请求次数
        $flag = 1;

        if ($addrToken){
            $jsonArr = ["method"=>"getaddresstokentxids","params"=>[$addr,$startBlock,$addrToken],"id"=>1];
        }else{
            $jsonArr = ["method"=>"getaddresstokentxids","params"=>[$addr,$startBlock],"id"=>1];
        }

        while($backStatus != 200){
            $jsonStr = json_encode($jsonArr);
            $Authorization = base64_encode($Author);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json; charset=utf-8',
                    'Content-Length: ' . strlen($jsonStr),
                    'Authorization:Basic '.$Authorization
                )
            );

            $response = curl_exec($ch);

            $responseArray = json_decode($response,1);

            //请求数据是否为空
            if($responseArray){
                if(array_key_exists('error',$responseArray) && $responseArray['error'] != null){
                    curl_close($ch);
                    return json_encode(array('error'=>1,'msg'=>$responseArray['error']['message']));
                }

                $backStatusTemp= curl_getinfo($ch,CURLINFO_HTTP_CODE);

                $flag++;

                if($flag > 3){
                    $errorArray = [
                        'msg'   => 'Request data does not exist!'
                    ];
                    curl_close($ch);
                    return json_encode($errorArray);
                }

                if($backStatusTemp == 200){
                    curl_close($ch);
                    return $response;
                }
            }else{
                $flag++;

                if($flag > 3){
                    $errorArray = [
                        'msg'   => 'Request data does not exist!'
                    ];
                    curl_close($ch);
                    return json_encode($errorArray);
                }
            }
        }
    }

}