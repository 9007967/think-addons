<?php

return [
    'autoload' => false,
    'database' => [ //数据表获取钩子
        'expire'  => 0 //查询缓存时间（单位秒，0为不缓存）
        , 'cache' => '__hooks_data_cache__' // 钩子数据缓存标识
        , 'table' => 'hooks' //钩子数据存放表名称
        , 'field' => [ 'mark', 'list' ] //钩子数据读取字段 （mark = 钩子标识，list = 使用钩子的插件列表
    ],
    'hooks'    => [],
    'route'    => [],
    'service'  => [],
    'dir'      => 'addons' //自定义插件文件夹
];
