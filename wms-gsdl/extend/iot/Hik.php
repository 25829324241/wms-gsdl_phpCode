<?php
//海康
namespace iot;

use think\Log;
use fast\Random;
use think\Request;

class Hik
{


    protected $h_url1 ='';
    protected $h_url2 ='';
    //protected $h_url3 ='';
    protected $h_url ='';
    protected $header ='';
    protected $api_version ='v1.0';

    public function __construct($h_url1='',$h_url2='')
    {

        //url1 https://192.168.1.42/
        //url2 rcs/rtas
        //url3 /api/robot/controller/robot/query


        if(!empty($h_url1)){
            $this->h_url1=$h_url1;
        }

        if(!empty($h_url2)){
            $this->h_url2=$h_url2;
        }

        $this->h_url=$this->h_url1.$h_url2;
    }



    //hik海康rcs 接口请求
    public function hik_rcs_query($q_url='',$data=[]){

        $url = $this->h_url.$q_url;
        if(!empty($data) && is_array($data)){
            $data =json_encode($data,true);
        }else{
            $data = "{}";
        }

       /* $request = Request::instance();
        $ip=$request->ip();*/

        $this->header = [
            "Content-type: application/json",
            // 'Authorization: Bearer abc123xyz456',
            "X-lr-request-id: ".uniqid(),
            "X-lr-version: ".$this->api_version,
            "Content-Length: " . strlen($data),
            // "Host: " . $ip.':80',

        ];

       // write_log(var_export($data,true),'hik_q_query_data_');
        //write_log(var_export($url,true),'hik_q_query_url_');
        //$ret = $this->data_post($url,$this->header,$data);
        $ret = $this->http_post($url,$data,$this->header);
        // write_log(var_export($ret,true),'hai_q_query_ret_');
        $ret = json_decode($ret,true);
        return $ret;
    }

    //raw post
    private function http_post($url, $data_string,$header) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST , "POST");

      /*  curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json; charset=utf-8',
                'Content-Length: ' . strlen($data_string))
        );*/
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 信任任何证书
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); // 检查证书中是否设置域名
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }

}