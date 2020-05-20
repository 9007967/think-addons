<?php

declare(strict_types = 1);

namespace think;

use think\App;
use think\helper\Str;
use think\facade\Config;
use think\facade\View;

abstract class Addons
{
    use \app\common\service\TraitBase;

    // app 容器
    protected $app;
    // 请求对象
    protected $request;
    // 当前插件标识
    protected $name;
    // 插件路径
    protected $addon_path;
    // 视图模型
    protected $view;
    // 插件配置
    protected $addon_config;
    // 插件信息
    protected $addon_info;

    protected $user;

    protected $AuthRule = [];


    /**
     * 插件构造函数
     * Addons constructor.
     * @param \think\App $app
     */
    public function __construct(App $app)
    {
        $this->app          = $app;
        $this->request      = $app->request;
        $this->name         = $this->getName();
        $this->addon_path   = $app->addons->getAddonsPath() . $this->name . DIRECTORY_SEPARATOR;
        $this->addon_config = "addon_{$this->name}_config";
        $this->addon_info   = "addon_{$this->name}_info";
        $this->view         = clone View::engine('Think');
        $this->user         = $this->request->session('__user_data__');
        $this->assign();
        $this->initialize();
        $this->authorize();
    }

    /**
     * 插件鉴权
     * @return bool
     */
    protected function authorize()
    {
        $checkAuth       = Config::get('addons.check_auth');
        $dir             = Config::get('addons.dir');
        $request         = $this->request;
        $request->isAuth = false;
        $app             = $request->plugin();
        $controller      = $request->controller(true);
        $action          = $request->action();
        $rule            = $dir . '::' . $app . '::' . $controller . '::' . $action;

        if (!empty($app) and $request->controller(true) and isset($this->AuthRule[ $checkAuth ]['except'])) {
            $except = $this->AuthRule[ $checkAuth ]['except'];
            if (in_array($action, $except)) {
                return true;
            } else if ($app and $request->controller(true)) {
                if (in_array($request->controller(true), [ 'admin' ])) {
                    $user = $request->session('__admin_data__');
                    $data = (new \app\common\middleware\CheckAdmin)->check_user_auth($rule, $user['id'] ?? 0, $user['groupid'] ?? 0);
                } else {
                    $user = $request->session('__user_data__');
                    $data = model('Plugins')->where('name', $this->name)->whereFindInSet('authorize', $user['groupid'] ?? 0)->findOrEmpty();
                }

                if ($data->isEmpty()) {
                    if ($user and $controller !== 'interface') {
                        $request->isAuth = '你无权进行此操作';
                    } else if ($user and $controller === 'interface') {
                        $this->json(10001, '你无权进行此操作');
                    } else {
                        $loginUrl = (string) \think\facade\Route::buildUrl('/auth/login/', [ 'callback' => $request->baseUrl(true) ])->domain('home');
                        $this->error('账号未登录', $loginUrl);
                    }
                }
            }
        }
    }

    // 初始化
    protected function initialize()
    {
    }

    /**
     * 获取插件标识
     * @return mixed|null
     */
    final protected function getName()
    {
        $class = get_class($this);
        [ , $name, ] = explode('\\', $class);
        $this->request->addon = $name;

        return $name;
    }

    /**
     * 加载模板输出
     * @param string $template
     * @param array  $vars 模板文件名
     * @return false|mixed|string   模板输出变量
     * @throws \think\Exception
     * @throws \Exception
     */
    protected function fetch($template = '', $vars = [])
    {
        $this->view->config([
            'view_path' => $this->addon_path . 'view' . DIRECTORY_SEPARATOR
        ]);

        if (!empty($vars['title'])) {
            $vars['title'] = $vars['title'] . ' - 扩展应用 - ' . get_setting('Site_Name');
        } else {
            $vars['title'] = !empty(get_setting('Site_Info')) ? '插件中心 - ' . get_setting('Site_Name') : get_setting('Site_Name');
        }
        $vars['keywords']    = (isset($vars['keywords']) and !empty($vars['keywords'])) ? $vars['keywords'] : get_setting('Site_Keywords');
        $vars['description'] = (isset($vars['description']) and !empty($vars['description'])) ? $vars['description'] : get_setting('Site_Description');

        return $this->view->fetch($template, $vars);
    }

    /**
     * 渲染内容输出
     * @access protected
     * @param string $content 模板内容
     * @param array  $vars    模板输出变量
     * @return mixed
     */
    protected function display($content = '', $vars = [])
    {
        $this->view->config([
            'view_path' => $this->addon_path . 'view' . DIRECTORY_SEPARATOR
        ]);
        return $this->view->display($content, $vars);
    }

    /**
     * 模板变量赋值
     * @access protected
     * @param array $array
     * @return View
     */
    protected function assign(array $array = [])
    {
        $this->view->config([
            'view_path' => $this->addon_path . 'view' . DIRECTORY_SEPARATOR
        ]);


        $default = [
            'BaseUrl'     => $this->request->baseUrl(true)
            , 'ViewData'  => new \app\common\service\ViewData
            , 'loginUser' => $this->request->session('__user_data__')
            , 'nav'       => []
        ];
        return $this->view->assign(array_merge($default, $array));
    }

    /**
     * 初始化模板引擎
     * @access protected
     * @param array|string $engine 引擎参数
     * @return View
     */
    protected function engine($engine)
    {

        return $this->view->engine($engine);
    }

    /**
     * 插件基础信息
     * @return array
     */
    final public function getInfo()
    {
        $info = Config::get($this->addon_info, []);
        if ($info) {
            return $info;
        }

        // 文件属性
        $info = $this->info ?? [];
        // 文件配置
        $info_file = $this->addon_path . 'info.ini';
        if (is_file($info_file)) {
            $_info        = parse_ini_file($info_file, true, INI_SCANNER_TYPED) ?: [];
            $_info['url'] = addons_url();
            $info         = array_merge($_info, $info);
        }

        if ($db = \think\facade\Db::name('plugins')->where('name', $this->name)->find()) {
            $info = array_merge($info, $db);
        }

        Config::set($info, $this->addon_info);
        return isset($info) ? $info : [];
    }

    /**
     * 获取配置信息
     * @param bool $type 是否获取完整配置
     * @return array|mixed
     */
    final public function getConfig($type = false)
    {
        $config = Config::get($this->addon_config, []);
        if ($config) {
            return $config;
        }
        $config_file = $this->addon_path . 'config.php';
        if (is_file($config_file)) {
            $temp_arr = (array) include $config_file;
            if ($type) {
                return $temp_arr;
            }
            foreach ($temp_arr as $key => $value) {
                $config[ $key ] = $value['value'];
            }
            unset($temp_arr);
        }
        Config::set($config, $this->addon_config);

        return $config;
    }

    //必须实现扩展中心显示方法
    abstract public function member_extended();

    //必须实现后台菜单显示方法
    abstract public function admin_menu();

    //必须实现安装
    abstract public function install();

    //必须卸载插件方法
    abstract public function uninstall();
}
