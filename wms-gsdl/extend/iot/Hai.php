<?php
//海柔
namespace iot;

use think\Log;
use fast\Random;

class Hai
{

    protected $hai_haiq_url ='';
    protected $hai_ess_url ='';
    protected $header ='';

    public function __construct($hai_q_url='',$hai_ess_url='')
    {

        if(!empty($hai_q_url)){
            $this->hai_haiq_url=$hai_q_url;
        }

        if(!empty($hai_ess_url)){
            $this->hai_ess_url=$hai_ess_url;
        }

        $this->header = [
            "Content-type: application/json"
        ];

    }


    //HAIQ-IWMS 接口请求
    public function hai_q_query($q_url='',$data=[]){

        //$url = $this->hai_haiq_url.'/robot/query';
        $url = $this->hai_haiq_url.$q_url;
        if(!empty($data) && is_array($data)){
            $data =json_encode($data,true);
        }else{
            $data = "{}";
        }
        write_log(var_export($data,true),'hai_q_query_data_');
        write_log(var_export($url,true),'hai_q_query_url_');
        //$ret = $this->data_post($url,$this->header,$data);
        $ret = $this->http_post($url,$data);
       // write_log(var_export($ret,true),'hai_q_query_ret_');
        $ret = json_decode($ret,true);
        return $ret;
    }


    //ess-api接口请求
    public function hai_ess_query($q_url='',$data=[]){

        $url = $this->hai_ess_url.$q_url;
        if(!empty($data) && is_array($data)){
            $data =json_encode($data,true);
        }else{
            $data = "{}";
        }
        //$ret = $this->data_post($url,$this->header,$data);
        $ret = $this->http_post($url,$data);
        $ret = json_decode($ret,true);
        return $ret;
    }


    private function data_post($postUrl, $postHeader, $postDate)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $postUrl);
        //curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postDate);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $postHeader);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    //raw post
    private function http_post($url, $data_string) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST , "POST");

        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json; charset=utf-8',
                'Content-Length: ' . strlen($data_string))
        );
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }


}