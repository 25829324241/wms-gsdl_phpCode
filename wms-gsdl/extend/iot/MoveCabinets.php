<?php
//密集柜
namespace iot;

use think\Log;
use fast\Random;



class MoveCabinets
{


    protected $api_url ='';
    protected $header ='';

    public function __construct($api_url='')
    {

        if(!empty($api_url)){
            $this->api_url=$api_url;
        }


        $this->header = [
            "Content-type: application/json"
        ];

    }

    //密集柜请求
    public function m_c_query($url='',$data=[]){

        //$url = $this->hai_haiq_url.'/robot/query';
        $url = $this->api_url.$url;
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


    private function http_post($url, $data_string)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");

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