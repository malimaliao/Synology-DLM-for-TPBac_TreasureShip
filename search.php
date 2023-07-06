<?php

/**
 * TPBac_TreasureShip
 * https://cndl.synology.cn/download/Document/Software/DeveloperGuide/Package/DownloadStation/All/enu/DLM_Guide.pdf
 * Developer Notes:
 *  $tpb_default_b64: tpb default host ( use base64 encode)
 *  $tpb_get_b64: If no username mapping host is defined, the program obtains it from this source and parses the output in base64.
 */

class TPBac_TreasureShip{
    private $tpb_default_b64 = "aHR0cHM6Ly9taXJyb3JiYXkub3Jn";
    private $tpb_get_b64 = "https://raw.githubusercontent.com/malimaliao/Synology-DLM-for-TPBac_TreasureShip/main/host.txt";

    private $tpb_host = '';
    private $tpb_cs = '/get-data-for/%s';

    private $opts = ["ssl" => ["verify_peer"=>false, "verify_peer_name"=>false,]];
    private $debug = false;

    public function __construct(){
    }

    function GetPage($url){
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, 1); //设置头文件的信息作为数据流输出
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);//设置获取的信息以文件流的形式返回，而不是直接输出
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($curl, CURLOPT_TIMEOUT, 20);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); //跳过SSL证书检查
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false); //跳过SSL证书检查
        curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36 Edg/114.0.1823.37');
        curl_setopt($curl,CURLOPT_REFERER,$url);
        $res = curl_exec($curl);
        if (curl_errno($curl)) {
            $res =  curl_error($curl);
        }
        curl_close($curl);
        return $res;
    }

    private function DebugLog($str){
        if ($this->debug == true) {
            file_put_contents('TreasureShip.debug.log', $str, FILE_APPEND);
        }
    }

    // Synology DownloadStation 预设函数
    public function VerifyAccount($username, $password){
        $ret = FALSE;
        $this->DebugLog("TPB网址(由用户名代替): ".$username.PHP_EOL);
        if ($username == "") {
            $username = file_get_contents($this->tpb_get_b64,false, stream_context_create($this->opts));
            $username = base64_decode($username); // b64 decode
        }
        $result = file_get_contents($username,false, stream_context_create($this->opts));
        $this->DebugLog("TPB响应内容: ".PHP_EOL.PHP_EOL.$result.PHP_EOL.PHP_EOL);
        if (strpos($result, 'The Pirate Bay') !== false) {
            $ret = TRUE;
            $rem = 'work';
            $this->tpb_host = $username;
        }else{
            $rem = 'none';
        }
        $this->DebugLog("TPB检测结果:" .$rem.PHP_EOL);
        return $ret;
    }

    // Synology DownloadStation 预设函数
    public function prepare($curl, $query, $username, $password)
    {
        if ($username == "") { // get cloud_b64
            $this->DebugLog("TPB(get start): ".$this->tpb_get_b64.PHP_EOL);
            $tmp = file_get_contents($this->tpb_get_b64,false, stream_context_create($this->opts));
            $this->DebugLog('@base64:'.$tmp);
            $tmp = base64_decode($tmp);
            $this->DebugLog('@decode:'.$tmp.PHP_EOL);

            $regex = '@(?i)\b((?:[a-z][\w-]+:(?:/{1,3}|[a-z0-9%])|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:\'".,<>?«»“”‘’]))@';
            if(preg_match($regex,$tmp)){
                $this->DebugLog("TPB(get success): ".$tmp.PHP_EOL);
            }else{
                // get cloud bad , use default
                $tmp = base64_decode($this->tpb_default_b64);
                $this->DebugLog("TPB(get bad and use default) ".$tmp.PHP_EOL);
            }
            $site = $tmp;
        }else{ // with username replace tpb_host
            $this->DebugLog("TPB(replace with username): ".$username.PHP_EOL);
            $site = $username;
        }
        $this->tpb_host = $site; // update
        $url = $site . $this->tpb_cs;
        $this->DebugLog(sprintf($url, urlencode($query)).PHP_EOL);
        curl_setopt($curl, CURLOPT_URL, sprintf($url, urlencode($query)));
        curl_setopt($curl, CURLOPT_HEADER, 1); //设置头文件的信息作为数据流输出
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);//设置获取的信息以文件流的形式返回，而不是直接输出
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($curl, CURLOPT_TIMEOUT, 20);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); //跳过SSL证书检查
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false); //跳过SSL证书检查
        curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36 Edg/114.0.1823.37');
    }

    // Synology DownloadStation 预设函数
    public function parse($plugin, $response)
    {
        $response = str_replace(array("\r\n", "\r", "\n"), "", $response); //抛弃所有换行便于正则匹配
        $this->DebugLog(PHP_EOL.$response.PHP_EOL.PHP_EOL);

        $regexp_list = '<tr>(.+?)<\/tr>';
        $regexp_category = 'category:\d+?">(.+?)<\/a>.+?category:\d+?">(.+?)<\/a>';
        $regexp_title_url = 'item-title"><..href="(.+?)">(.+?)<\/a>';
        $regexp_magnet = 'href="(magnet:.+?)">';
        $regexp_size = 'item-size">(.+?).(GB|MB|KB|B|.?)<';
        $regexp_datetime = 'item-uploaded">(.+?)<';
        $regexp_se = '';
        $regexp_le = '';

        $res = 0;
        if (preg_match_all("/$regexp_list/i", $response, $matches2, PREG_SET_ORDER)) {
            foreach ($matches2 as $match2) {
                $title = "Unknown title";
                $download = "Unknown download";
                $size = 0;
                $datetime = "1900-12-31 23:59:10";
                $page = "Default page";
                $hash = "Hash unknown";
                $seeds = 0;
                $leechs = 0;
                $category = "Unknown category";
                # category
                if (preg_match_all("/$regexp_category/i", $match2[0], $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $category = $match[1] . " " . $match[2];
                    }
                }
                # title & url &hash
                if (preg_match_all("/$regexp_title_url/i", $match2[0], $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $page = $this->tpb_host . $match[1];
                        $title = $match[2];
                        $hash = md5($title);
                    }
                }
                # magnet
                if (preg_match_all("/$regexp_magnet/i", $match2[0], $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $download = str_replace('&amp;','&',$match[1]);
                    }
                }
                # size
                if (preg_match_all("/$regexp_size/i", $match2[0], $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $size = (float)$match[1];
                        $size_unit = $match[2];
                        switch ($size_unit) {
                            case 'KB':
                                $size = $size * 1024;
                                break;
                            case 'MB':
                                $size = $size * 1024 * 1024;
                                break;
                            case 'GB':
                                $size = $size * 1024 * 1024 * 1024;
                                break;
                            case 'TB':
                                $size = $size * 1024 * 1024 * 1024 * 1024;
                                break;
                            default:
                                $size = $size * 1;
                        }
                        $size = floor($size);
                    }
                }
                # datetime
                if (preg_match_all("/$regexp_datetime/i", $match2[0], $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        # $datetime = $match[1];
                        $datetime = $this->format_tpb_time($match[1]);
                    }
                }
                # seeds: 已完成数 / leechs: 下载中数
                /**
                if (preg_match_all("/$regexp_seeds_leechs/i", $match2[0], $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $seeds = $match[1];
                        $leechs = $match[2];
                    }
                }
                 */
                # 输出并对接给Plugin
                if ($title != "Unknown title") {
                    if ($this->debug == true) {
                        // out debug
                        echo $title.'|'.$download.'|'.$size.'|'.$datetime.'|'.$page.'|'.$hash.'|'.$seeds.'|'.$leechs.'|'.$category;
                        $this->DebugLog($title.'|'.$download.'|'.$size.'|'.$datetime.'|'.$page.'|'.$hash.'|'.$seeds.'|'.$leechs.'|'.$category.PHP_EOL);
                    }else{ // out plugin
                        $plugin->addResult($title, $download, $size, $datetime, $page, $hash, $seeds, $leechs, $category);
                    }
                    $res++;
                }
            }
        }

        return $res;
    }

    function format_tpb_time($str){
        /**
         * 换算特定文本为时间格式
         *  1 minutes
            1 hour
            1 days
            1 weeks
            1 months
            1 years
            1 hour, 2 minutes
            1 hours, 2 minutes
            1 day, 2 hours
            1 week, 2 days
            1 weeks, 2 days
            1 months, 2 weeks
            1 years, 2 months
         */
        $str = trim($str);
        $bm = mb_detect_encoding($str,array('ASCII','UTF-8','BIG5','GB2312'));
        if($bm !== 'ASCII'){
            $str = iconv($bm,'ASCII//TRANSLIT',$str);
        }
        if (preg_match('/^(\d{1,2}.minutes?$)/i', $str, $date_parts) == 1) {
            $a = '-'.$date_parts[1];
            $date_time = date("Y-m-d H:i:s", strtotime($a)); // date_sub()
        }elseif (preg_match('/^(\d{1,2}.hours?$)/i', $str, $date_parts) == 1) {
            $a = '-'.$date_parts[1];
            $date_time = date("Y-m-d H:i:s", strtotime($a));
        }elseif (preg_match('/^(\d{1,2}.days?$)/i', $str, $date_parts) == 1) {
            $a = '-'.$date_parts[1];
            $date_time = date("Y-m-d H:i:s", strtotime($a));
        }elseif (preg_match('/^(\d{1,2}.weeks?$)/i', $str, $date_parts) == 1) {
            $a = '-'.$date_parts[1];
            $date_time = date("Y-m-d H:i:s", strtotime($a));
        }elseif (preg_match('/^(\d{1,2}.months?$)/i', $str, $date_parts) == 1) {
            $a = '-'.$date_parts[1];
            $date_time = date("Y-m-d H:i:s", strtotime($a));
        }elseif (preg_match('/^(\d{1,2}.years?$)/i', $str, $date_parts) == 1) {
            $a = '-'.$date_parts[1];
            $date_time = date("Y-m-d H:i:s", strtotime($a));
        } elseif (preg_match('/^(\d{1,2}.hours?),.(\d{1,2}.minutes?$)/i', $str, $date_parts) == 1) {
            $a = '-'.$date_parts[1];
            $b = '-'.$date_parts[2];
            $date_time = date("Y-m-d H:i:s", strtotime($a));
            $date_time = date("Y-m-d H:i:s", strtotime($b,strtotime($date_time)));
        } elseif (preg_match('/^(\d{1,2}.days?),.(\d{1,2}.hours?$)/i', $str, $date_parts) == 1) {
            $a = '-'.$date_parts[1];
            $b = '-'.$date_parts[2];
            $date_time = date("Y-m-d H:i:s", strtotime($a));
            $date_time = date("Y-m-d H:i:s", strtotime($b,strtotime($date_time)));
        } elseif (preg_match('/^(\d{1,2}.weeks?),.(\d{1,2}.days?$)/i', $str, $date_parts) == 1) {
            $a = '-'.$date_parts[1];
            $b = '-'.$date_parts[2];
            $date_time = date("Y-m-d H:i:s", strtotime($a));
            $date_time = date("Y-m-d H:i:s", strtotime($b,strtotime($date_time)));
        }elseif (preg_match('/^(\d{1,2}.months?),.(\d{1,2}.weeks?$)/i', $str, $date_parts) == 1) {
            $a = '-'.$date_parts[1];
            $b = '-'.$date_parts[2];
            $date_time = date("Y-m-d H:i:s", strtotime($a));
            $date_time = date("Y-m-d H:i:s", strtotime($b,strtotime($date_time)));
        }
        elseif (preg_match('/^(\d{1,2}.years?),.(\d{1,2}.months?$)/i', $str, $date_parts) == 1) {
            $a = '-'.$date_parts[1];
            $b = '-'.$date_parts[2];
            $date_time = date("Y-m-d H:i:s", strtotime($a));
            $date_time = date("Y-m-d H:i:s", strtotime($b,strtotime($date_time)));
        } else{ // use show
            $a = '1900-11-11 11:11:11';
            $date_time = $a;
        }
        if($this->debug==true){
            echo $str.'@'.$bm.'#'.$date_time.'<hr>';
        }
        return $date_time;
    }

}

