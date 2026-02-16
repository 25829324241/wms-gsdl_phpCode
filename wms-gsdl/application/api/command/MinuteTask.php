<?php
//分钟任务

namespace app\api\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use think\Log;
use think\Config;

class MinuteTask extends command
{


    protected function configure(){

        $this->setName('IotMinuteTask')->setDescription("计划任务 MinuteTask");
    }


    //调用SendMessage 这个类时,会自动运行execute方法
    protected function execute(Input $input, Output $output){

        $output->writeln('Date Crontab job start...');
        /*** 这里写计划任务列表集 START ***/



        //重试执行失败任务
        $this->retry_task();
        //重试推送任务
        $this->retry_return_task();




        /*** 这里写计划任务列表集 END ***/
        $output->writeln('Date Crontab job end...');

    }

    //重试执行失败任务
    public function retry_task(){

    }

    //重试推送任务
    public function retry_return_task(){

    }

}