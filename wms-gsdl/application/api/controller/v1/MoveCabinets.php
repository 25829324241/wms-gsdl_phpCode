<?php
//优小儿密集柜
namespace app\api\controller\v1;

use iot\MoveCabinets as MoveCabinetsApi;
use think\Cache;

class MoveCabinets extends Base
{

    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';

    protected $server_url;
    protected $FAreaId =2;
    protected $url='http://192.168.1.107:8090/';

    public function _initialize()
    {
        parent::_initialize();


        if(!empty($_SERVER['SERVER_NAME'])){
            $this->server_url = $_SERVER['SERVER_NAME']?"https://".$_SERVER['SERVER_NAME']:"http://".$_SERVER['HTTP_HOST'];
        }else{
            //计划任务兼容
            $this->server_url ='http://127.0.0.1/';
        }

    }

    //密集柜移动
    public function to_move($row_arr){

        $mov = new MoveCabinetsApi($this->url);
        $ret = $mov->m_c_query('operate',$row_arr);

        Cache::set('move_status',[
            'status'=>1,//1打开 2关闭
            'time'=>time(),
        ]);

        write_log(var_export($ret,true),'to_move_');

    }

    //密集柜关闭
    public function to_off($row_arr=''){

        $mov = new MoveCabinetsApi($this->url);
        if(!empty($row_arr) && is_array($row_arr)){
            $ret = $mov->m_c_query('operate',$row_arr);
            write_log(var_export($ret,true),'to_off_');
        }else{

            Cache::set('move_status',[
                'status'=>2,//1打开 2关闭
                'time'=>time(),
            ]);

            $data =[
                'Cmd'=>'LeftClose',
                'FAreaId'=>'2',
            ];
            $ret = $mov->m_c_query('operate',$data);
            //write_log(var_export($ret,true),'to_off_');
        }
        $this->success('密集架开始关闭');
    }

    //获取密集柜状态
    public function get_status_data(){

        $mov = new MoveCabinetsApi($this->url);
        $data =[
            'Cmd'=>'GetColPos',
        ];
        $ret = $mov->m_c_query('operate',$data);

        //$this->success('ok',$ret['data']);
        write_log('A1'.var_export($ret,true),"get_status_data_");
        if($ret['data'][0]<85 || $ret['data'][1]<85 || $ret['data'][1]<85 || $ret['data'][3]<85){
            if(empty(Cache::get('move_status'))) {
                Cache::set('move_status',[
                    'status'=>1,//1打开 2关闭
                    'time'=>time(),
                ]);
            }
            $move_status = Cache::get('move_status');
            if($move_status['status']==1){
                Cache::set('move_status',[
                    'status'=>1,//1打开 2关闭
                    'time'=>$move_status['time'],
                ]);
            }else{
                Cache::set('move_status',[
                    'status'=>1,//1打开 2关闭
                    'time'=>time(),
                ]);
            }
            write_log('A2'.var_export($move_status,true),"get_status_data_");
        }


        return $ret['data'];
    }

    //密集柜状态判断
    public function get_status($row){

        $status =0;
        $mov = new MoveCabinetsApi($this->url);
        $data =[
            'Cmd'=>'GetColPos',
        ];
        $ret = $mov->m_c_query('operate',$data);
        write_log(var_export($ret,true),'get_status_');
       // $ret = json_decode($ret,true);
        if(!empty($ret) && is_array($ret)){
            $mov_status = $ret['data'];
            switch ($row){
                case '4':
                    if($mov_status[0]>85 && $mov_status[1]>85 && $mov_status[1]>85 && $mov_status[3]>85){
                        $row_arr =[
                            'Cmd'=>'LeftMove',
                            'FAreaId'=>$this->FAreaId,
                            'FColNo'=>'1',
                        ];
                        $this->to_move($row_arr);

                    }


                    //可取 04
                    if($mov_status[0]<30 && $mov_status[1]>85 && $mov_status[2]>85){
                       // if($row==4){
                            $status =1;
                       // }
                    }

                    //可取 03
                    if($mov_status[0]<30 && $mov_status[1]<30 && $mov_status[2]>85){
                        $row_arr =[
                            'Cmd'=>'RightMove',
                            'FAreaId'=>$this->FAreaId,
                            'FColNo'=>'2',
                        ];
                        $this->to_move($row_arr);
                    }

                    //可取01 02
                    if($mov_status[0]<30 && $mov_status[1]<30 && $mov_status[2]<30){
                       /* $row_arr =[
                            'Cmd'=>'RightMove',
                            'FAreaId'=>$this->FAreaId,
                            'FColNo'=>'3',
                        ];
                        $this->to_move($row_arr);*/

                        $row_arr =[
                            'Cmd'=>'RightMove',
                            'FAreaId'=>$this->FAreaId,
                            'FColNo'=>'2',
                        ];
                        $this->to_move($row_arr);
                    }

                    break;
                case '3':

                    if($mov_status[0]>85 && $mov_status[1]>85 && $mov_status[1]>85 && $mov_status[3]>85){
                       /* $row_arr =[
                            'Cmd'=>'LeftMove',
                            'FAreaId'=>$this->FAreaId,
                            'FColNo'=>'1',
                        ];
                        $this->to_move($row_arr);*/

                        $row_arr =[
                            'Cmd'=>'LeftMove',
                            'FAreaId'=>$this->FAreaId,
                            'FColNo'=>'2',
                        ];
                        $this->to_move($row_arr);

                    }

                    //可取 03
                    if($mov_status[0]<30 && $mov_status[1]<30 && $mov_status[2]>85){
                      //  if($row==3){
                            $status =1;
                      //  }
                    }

                    //可取 04
                    if($mov_status[0]<30 && $mov_status[1]>85 && $mov_status[2]>85){
                        $row_arr =[
                            'Cmd'=>'LeftMove',
                            'FAreaId'=>$this->FAreaId,
                            'FColNo'=>'2',
                        ];
                        $this->to_move($row_arr);
                    }

                    if($mov_status[0]<30 && $mov_status[1]<30 && $mov_status[2]<30){

                        /*$row_arr =[
                            'Cmd'=>'LeftMove',
                            'FAreaId'=>$this->FAreaId,
                            'FColNo'=>'2',
                        ];
                        $this->to_move($row_arr);*/

                        $row_arr =[
                            'Cmd'=>'RightMove',
                            'FAreaId'=>$this->FAreaId,
                            'FColNo'=>'3',
                        ];
                        $this->to_move($row_arr);
                    }

                    break;
                case '2':

                    if($mov_status[0]>85 && $mov_status[1]>85 && $mov_status[1]>85 && $mov_status[3]>85){
                        /*$row_arr =[
                            'Cmd'=>'LeftMove',
                            'FAreaId'=>$this->FAreaId,
                            'FColNo'=>'4',
                        ];
                        $this->to_move($row_arr);*/

                        $row_arr =[
                            'Cmd'=>'LeftMove',
                            'FAreaId'=>$this->FAreaId,
                            'FColNo'=>'3',
                        ];
                        $this->to_move($row_arr);

                      /*  $row_arr =[
                            'Cmd'=>'LeftMove',
                            'FAreaId'=>$this->FAreaId,
                            'FColNo'=>'2',
                        ];
                        $this->to_move($row_arr);*/
                    }

                    //可取02
                    if($mov_status[0]<30 && $mov_status[1]<30 && $mov_status[2]<30){
                        //if($row<2){
                            $status =1;
                       // }
                    }

                    //可取 04
                    if($mov_status[0]<30 && $mov_status[1]>85 && $mov_status[2]>85){
                        $row_arr =[
                            'Cmd'=>'LeftMove',
                            'FAreaId'=>$this->FAreaId,
                            'FColNo'=>'3',
                        ];
                        $this->to_move($row_arr);
                        /*$row_arr =[
                            'Cmd'=>'LeftMove',
                            'FAreaId'=>$this->FAreaId,
                            'FColNo'=>'2',
                        ];
                        $this->to_move($row_arr);*/
                    }

                    //可取 03
                    if($mov_status[0]<30 && $mov_status[1]<30 && $mov_status[2]>85){
                        $row_arr =[
                            'Cmd'=>'LeftMove',
                            'FAreaId'=>$this->FAreaId,
                            'FColNo'=>'3',
                        ];
                        $this->to_move($row_arr);
                    }

                    break;

                case '1':

                    if($mov_status[0]>85 && $mov_status[1]>85 && $mov_status[1]>85 && $mov_status[3]>85){
                        /*$row_arr =[
                            'Cmd'=>'LeftMove',
                            'FAreaId'=>$this->FAreaId,
                            'FColNo'=>'4',
                        ];
                        $this->to_move($row_arr);*/

                        $row_arr =[
                            'Cmd'=>'LeftMove',
                            'FAreaId'=>$this->FAreaId,
                            'FColNo'=>'3',
                        ];
                        $this->to_move($row_arr);

                       /* $row_arr =[
                            'Cmd'=>'LeftMove',
                            'FAreaId'=>$this->FAreaId,
                            'FColNo'=>'2',
                        ];
                        $this->to_move($row_arr);*/
                    }
                    

                    //可取01
                    if($mov_status[0]<30 && $mov_status[1]<30 && $mov_status[2]<30){
                       // if($row<1){
                            $status =1;
                       // }
                    }

                    //可取 04-1
                    if($mov_status[0]<30 && $mov_status[1]>85 && $mov_status[2]>85){
                        $row_arr =[
                            'Cmd'=>'LeftMove',
                            'FAreaId'=>$this->FAreaId,
                            'FColNo'=>'3',
                        ];
                        $this->to_move($row_arr);
                       /* $row_arr =[
                            'Cmd'=>'LeftMove',
                            'FAreaId'=>$this->FAreaId,
                            'FColNo'=>'2',
                        ];
                        $this->to_move($row_arr);*/
                    }

                    //可取 03
                    if($mov_status[0]<30 && $mov_status[1]<30 && $mov_status[2]>85){
                        $row_arr =[
                            'Cmd'=>'LeftMove',
                            'FAreaId'=>$this->FAreaId,
                            'FColNo'=>'3',
                        ];
                        $this->to_move($row_arr);
                    }
                    break;
            }







        }

        if($status==0){

            /*if(empty(Cache::get('move_status'))) {
                Cache::set('move_status',[
                    'status'=>1,//1打开 2关闭
                    'time'=>time(),
                ]);
            }
            $move_status = Cache::get('move_status');
            if($move_status['status']==1){
                Cache::set('move_status',[
                    'status'=>1,//1打开 2关闭
                    'time'=>$move_status['time'],
                ]);
            }else{
                Cache::set('move_status',[
                    'status'=>2,//1打开 2关闭
                    'time'=>time(),
                ]);
            }*/

            $this->error('密集柜移动中，请稍后重试');
        }

    }

}