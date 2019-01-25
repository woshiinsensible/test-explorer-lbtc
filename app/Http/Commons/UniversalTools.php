<?php
/**
 * Created by PhpStorm.
 * User: insen
 * Date: 2018/3/16
 * Time: 16:18
 */

namespace App\Http\Commons;

use Illuminate\Http\Request;

class UniversalTools
{
    //地址访问限制
    public function AddrLimit($addr,$limit,$time)
    {
        $redis = new RedisOperate();

        $limitAddr = 'limit-'.$addr;

        $check = $redis->RedisExist($limitAddr);
        if($check){
            $redis->RedisIncr($limitAddr);  //键值递增
            $count = $redis->RedisGet($limitAddr);
            if($count > $limit){
                return 0;
            }else{
                return 1;
            }
        }else{
            $redis->RedisIncr($limitAddr);
            //限制时间为time秒
            $redis->RedisExpire($limitAddr,$time);
            return 1;
        }
    }


    //ip访问限制
    public function IPLimit($IP,$limit,$time)
    {
        $redis = new RedisOperate();

        $limitIP = 'limit-'.$IP;

        $check = $redis->RedisExist($limitIP);
        if($check){
            $redis->RedisIncr($limitIP);  //键值递增
            $count = $redis->RedisGet($limitIP);
            if($count > $limit){
                return 0;
            }else{
                return 1;
            }
        }else{
            $redis->RedisIncr($limitIP);
            //限制时间为time秒
            $redis->RedisExpire($limitIP,$time);
            return 1;
        }
    }


    //验证request的token
    public function VerToken($addr,$token)
    {
        $redis = new RedisOperate();
        $expire = 60;
        $addrToken = $addr.'-'.$token;
        $check = $redis->RedisExist($addrToken);

        if($check){
            $redisToken = $redis->RedisGet($addrToken);
            if($redisToken == $token){
                return 1;
            }else{
                return 1; //0
            }
        }else{
            $tokenArray = array();
            $tokenCount = 5;
            $leftCount  = -4;
            $timeFlag = time();
            $tokenFlag = 'lbtcniubi-'.$addr.'-';
            for($i=$leftCount;$i<$tokenCount;$i++){
                $tokenArray[] = md5($tokenFlag.($timeFlag + $i));
            }


            //token是否存在数组中
            if(in_array($token,$tokenArray)){
                $redis->RedisSet($addrToken,$token,$expire);
                return 1;
            }else{
                return 1; //0
            }
        }
    }


    //访问过滤
    public function AccessFilter()
    {

    }



    //加解密
    //函数encrypt($string,$operation,$key)
    //$string：需要加密解密的字符串；
    //$operation：判断是加密还是解密，
    //E表示加密，D表示解密；$key：密匙。
    public function Encrypt($string,$operation,$key=''){
        $key=md5($key);
        $key_length=strlen($key);
        $string=$operation=='D'?base64_decode($string):substr(md5($string.$key),0,8).$string;
        $string_length=strlen($string);
        $rndkey=$box=array();
        $result='';
        for($i=0;$i<=255;$i++){
            $rndkey[$i]=ord($key[$i%$key_length]);
            $box[$i]=$i;
        }
        for($j=$i=0;$i<256;$i++){
            $j=($j+$box[$i]+$rndkey[$i])%256;
            $tmp=$box[$i];
            $box[$i]=$box[$j];
            $box[$j]=$tmp;
        }
        for($a=$j=$i=0;$i<$string_length;$i++){
            $a=($a+1)%256;
            $j=($j+$box[$a])%256;
            $tmp=$box[$a];
            $box[$a]=$box[$j];
            $box[$j]=$tmp;
            $result.=chr(ord($string[$i])^($box[($box[$a]+$box[$j])%256]));
        }
        if($operation=='D'){
            if(substr($result,0,8)==substr(md5(substr($result,8).$key),0,8)){
                return substr($result,8);
            }else{
                return'';
            }
        }else{
            return str_replace('=','',base64_encode($result));
        }
    }


    //过滤敏感词，废弃
    public function FilterBadWord($content = '', $verify = false)
    {
        if(!$content) return false;
        // 引入敏感字词库
        $word = require '/home/wwwroot/elbtc/app/Http/Commons/badwords.php';
        // 换字符
        $lexicon = array_combine($word,array_fill(0,count($word),':-)'));
        // 匹配替换
        $str = strtr($content, $lexicon);
        if($verify){
            if($str != $content){
                return 'Content sensitive words!';
            }
        }
        return $str;
    }


    //过滤敏感词新方法
    public function FilterBadWords($content)
    {
        //从redis中获取敏感词
        $words = ["大头","datou","张银海","zhangyinhai","zyh","银","海","yin","hai","da","tou","头","点付大头"];

        $content_filter = mb_strtolower($content);

        //先对用户数据去除所有的标点符号和一些特殊字符，然后再进行敏感词判断。
        $flag_arr=array('？','！','￥','（','）','：','‘','’','“','”','《','》','，','…','。','、','nbsp','】','【','～','—');

        $content_filter=preg_replace('/\s/','',preg_replace("/[[:punct:]]/",'',strip_tags(html_entity_decode(str_replace($flag_arr,'',$content_filter),ENT_QUOTES,'UTF-8'))));


        foreach ($words as $word)
        {
            $res = strpos($content_filter, $word);

            if($res !== false){
                return "-_-";
            }

            $preg_letter = '/^[A-Za-z]+$/';
            if (preg_match($preg_letter, $content_filter))
            {//匹配中文
                $content_filter = strtolower($content_filter);
                $pattern_1 = '/([^A-Za-z]+' . $word . '[^A-Za-z]+)|([^A-Za-z]+' . $word . '\s+)|(\s+' . $word . '[^A-Za-z]+)|(^' . $word . '[^A-Za-z]+)|([^A-Za-z]+' . $word.'$)/';
                //敏感词两边不为空
                if (preg_match($pattern_1, $content_filter))
                {
                    return "-_-";
                }
                $pattern_2 = '/(^' . $word . '\s+)|(\s+' . $word . '\s+)|(\s+' . $word . '$)|(^' . $word . '$)/';
                //敏感词两边可以为空格
                if (preg_match($pattern_2, $content_filter))
                {
                    return "-_-";
                }
            }else{
                //匹配英文字符串，大小写不敏感
                $pattern = '/\s*' . $word . '\s*/';
                if (preg_match($pattern, $content_filter))
                {
                    return "-_-";
                }
            }
        }

        //二段处理
        // 引入敏感字词库
        $redis = new RedisOperate();
        $key = "badwords";
        $wordRedis = $redis->RedisLRange($key);
        // 换字符
        $lexicon = array_combine($wordRedis,array_fill(0,count($wordRedis),':-)'));
        // 匹配替换
        $str = strtr($content, $lexicon);
        return $str;
    }

    //返回请求状态码
    public function HttpStatus($num)
    {
        static $http = array (
            100 => "HTTP/1.1 100 Continue",
            101 => "HTTP/1.1 101 Switching Protocols",
            200 => "HTTP/1.1 200 OK",
            201 => "HTTP/1.1 201 Created",
            202 => "HTTP/1.1 202 Accepted",
            203 => "HTTP/1.1 203 Non-Authoritative Information",
            204 => "HTTP/1.1 204 No Content",
            205 => "HTTP/1.1 205 Reset Content",
            206 => "HTTP/1.1 206 Partial Content",
            300 => "HTTP/1.1 300 Multiple Choices",
            301 => "HTTP/1.1 301 Moved Permanently",
            302 => "HTTP/1.1 302 Found",
            303 => "HTTP/1.1 303 See Other",
            304 => "HTTP/1.1 304 Not Modified",
            305 => "HTTP/1.1 305 Use Proxy",
            307 => "HTTP/1.1 307 Temporary Redirect",
            400 => "HTTP/1.1 400 Bad Request",
            401 => "HTTP/1.1 401 Unauthorized",
            402 => "HTTP/1.1 402 Payment Required",
            403 => "HTTP/1.1 403 Forbidden",
            404 => "HTTP/1.1 404 Not Found",
            405 => "HTTP/1.1 405 Method Not Allowed",
            406 => "HTTP/1.1 406 Not Acceptable",
            407 => "HTTP/1.1 407 Proxy Authentication Required",
            408 => "HTTP/1.1 408 Request Time-out",
            409 => "HTTP/1.1 409 Conflict",
            410 => "HTTP/1.1 410 Gone",
            411 => "HTTP/1.1 411 Length Required",
            412 => "HTTP/1.1 412 Precondition Failed",
            413 => "HTTP/1.1 413 Request Entity Too Large",
            414 => "HTTP/1.1 414 Request-URI Too Large",
            415 => "HTTP/1.1 415 Unsupported Media Type",
            416 => "HTTP/1.1 416 Requested range not satisfiable",
            417 => "HTTP/1.1 417 Expectation Failed",
            500 => "HTTP/1.1 500 Internal Server Error",
            501 => "HTTP/1.1 501 Not Implemented",
            502 => "HTTP/1.1 502 Bad Gateway",
            503 => "HTTP/1.1 503 Service Unavailable",
            504 => "HTTP/1.1 504 Gateway Time-out"
        );
        header($http[$num]);
        exit();
    }

}