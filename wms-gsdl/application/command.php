<?php

// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------

return [
    'app\admin\command\Crud',
    'app\admin\command\Menu',
    'app\admin\command\Install',
    'app\admin\command\Min',
    'app\admin\command\Addon',
    'app\admin\command\Api',
    'app\api\command\IotSyncTask',//Lot同步任务
    'app\api\command\IotMinuteTask',//Lot分钟任务
    //===LOT
    'app\api\command\HaiTask',//海柔任务
    'app\api\command\MinuteTask',//分钟任务
    'app\api\command\RobotarmTask'//机械臂
];
