<?php
/**
 * 网站专业定制：网站、微信公众号、小程序等一站式开发
 * QQ 46502166
 * @author: LaoYang
 * @email: 46502166@qq.com
 * @link:  http://dahulu.cc
 * ==========================================
 * 后台基础控制器，它继承了core\Ctrl，被后台所有其他控制器继承（api的除外）
 * =========================================*/

namespace app\common\ctrl;
use core\Ctrl;
class AdminCtrl extends Ctrl
{
    //默认初始化，覆盖了父类的同名方法
    protected function _config()
    {
        //设置后台模板路径
        $this->view->config(Func::_setAdminTplPath());
    }

    //初始化
    protected function _init(){
        $this->_must_login();
        if (! $this->_checkIsAdmin()){
            //$this->_logout();
            $this->_redirect('/','你没有权限访问后台管理面板');
        }
    }

    //必须登陆才可以访问后台
    protected function _must_login(){
        if($this->_is_login()){
            return true;
        }
        $this->_logout();
        $this->_redirect('admin/login/login','你还没登陆，或登陆超时');
    }
}