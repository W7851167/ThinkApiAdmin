<?php

// +----------------------------------------------------------------------
// | ThinkApiAdmin
// +----------------------------------------------------------------------

namespace app\admin\controller;

use controller\BasicAdmin;
use service\LogService;
use service\NodeService;
use think\Db;

/**
 * 系统登录控制器
 * class Login
 * @package app\admin\controller
 */
class Login extends BasicAdmin
{

    /**
     * 控制器基础方法
     */
    public function _initialize()
    {
        if (session('user') && $this->request->action() !== 'out') {
            $this->redirect('@admin');
        }
    }

    /**
     * 用户登录
     * @return mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function index()
    {
        if ($this->request->isGet()) {
            return $this->fetch('', ['title' => '用户登录']);
        }
        // 输入数据效验
        $username = $this->request->post('username', '', 'trim');
        $password = $this->request->post('password', '', 'trim');
        strlen($username) < 4 && $this->error('登录账号长度不能少于4位有效字符!');
        strlen($password) < 4 && $this->error('登录密码长度不能少于4位有效字符!');
        // 极验验证
        $challenge = $this->request->post('geetest_challenge');
        $validate = $this->request->post('geetest_validate');
        if (!$challenge || md5($challenge) != $validate) {
            $this->error('请先通过验证!');
        }
        // 用户信息验证
        $user = Db::name('SystemUser')->where('username', $username)->find();
        empty($user) && $this->error('登录账号不存在，请重新输入!');
        ($user['password'] !== md5($password)) && $this->error('登录密码与账号不匹配，请重新输入!');
        empty($user['status']) && $this->error('账号已经被禁用，请联系管理!');
        // 更新登录信息
        Db::name('SystemUser')->where(['id' => $user['id']])->setField('login_at',date('Y-m-d H:i:s'));
        Db::name('SystemUser')->where(['id' => $user['id']])->setInc('login_num');
        session('user', $user);
        !empty($user['authorize']) && NodeService::applyAuthNode();
        LogService::write('系统管理', '用户登录系统成功');
        $this->success('登录成功，正在进入系统...', '@admin');
    }

    /**
     * 退出登录
     */
    public function out()
    {
        if (session('user')) {
            LogService::write('系统管理', '用户退出系统成功');
        }
        session('user', null);
        session_destroy();
        $this->success('退出登录成功！', '@admin/login');
    }

    /**
     * 极验验证
     */
    public function gt()
    {
        $rnd1 = md5(rand(0, 100));
        $rnd2 = md5(rand(0, 100));
        $challenge = $rnd1 . substr($rnd2, 0, 2);
        $result = array(
            'success' => 0,
            'gt' => 'YourID',
            'challenge' => $challenge,
            'new_captcha' => 1
        );
        header('Content-Type:application/json; charset=utf-8');
        exit(json_encode($result));
    }

}
