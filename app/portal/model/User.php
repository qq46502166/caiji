<?php
/**
 * 网站专业定制：网站、微信公众号、小程序等一站式开发
 *
 * QQ 46502166
 *
 * @author: LaoYang
 * @email: 46502166@qq.com
 * @link:  http://dahulu.cc
 * ======================================
 *
 * ======================================*/


namespace app\portal\model;

use core\Conf;
use extend\Helper;

class User extends \app\admin\model\User
{
    public $table='user';
    public $primaryKey = 'id';
    public $msg='';

    /** ------------------------------------------------------------------
     * 获取随机用户
     * @param int $limit
     * @param string $select
     * @return array|bool
     *---------------------------------------------------------------------*/
    public function getRandomUser($limit,$select='*'){
        return  $this->_random($limit,[['status','eq',1]],$select);
    }

    public function randomUserEx(&$username){
        //读取配置
        $maxUser=Conf::get('max_user','portal');
        if(!$maxUser){//不设置或为0时，从系统中获取
            return $this->getOneRandomUser($username);
        }else{
            $total=$this->count();
            if($total<$maxUser){
                $url=Conf::get('get_user_url','portal').'&_t='.((int)microtime(true));
                $res=Helper::curl_request($url,$code);
                if($code===200){
                    $res=json_decode($res,true);
                    if(isset($res['data']) && $res['data']){
                        $username=$res['data']['username'];
                        $id=$this->getIdByName($username);
                        if($id >0){
                            $this->eq('id',$id)->update([
                                'nickname'=>$res['data']['name'],
                                'more'=>$res['data']['text'],
                                'signature'=>$res['data']['signature'],
                                'birthday'=>$res['data']['birthday'],
                                'city'=>$res['data']['city'],
                            ]);
                            return $id;
                        }
                        return $this->addUserEx([
                            'username'=>$username,
                            'nickname'=>$res['data']['name'],
                            'more'=>$res['data']['text'],
                            'signature'=>$res['data']['signature'],
                            'birthday'=>$res['data']['birthday'],
                            'city'=>$res['data']['city'],
                            'email'=>time().'@163.com',
                            'avatar'=>'/uploads/user/'.mt_rand(0,500).'.jpg',
                            'password'=>'isi123Z&$pp456#',
                            'sex'=>mt_rand(0,2),
                            'coin'=>mt_rand(187,6888),
                        ]);
                    }
                }
            }
            return $this->getOneRandomUser($username);
        }
    }

    public function getOneRandomUser(&$username){
        $data=$this->getRandomUser(1,'id,username');
        if($data){
            $username=$data['0']['username'];
            return $data[0]['id'];
        }
        return 0;
    }

    /** ------------------------------------------------------------------
     * 从用户名添加用户，用户名已经存在时直接返回用户id
     * @param $username
     * @return int 用户名已经存在时直接返回用户id，否则会返回新插件的id
     *--------------------------------------------------------------------*/
    public function addFromName($username){
        $id=$this->getIdByName($username);
        if($id >0)
            return $id;
        return $this->addUser([
            'username'=>$username,
            'email'=>$username.'@163.com',
            'avatar'=>'/uploads/user/'.mt_rand(0,500).'.jpg',
            'password'=>'xue123Zpp456'
        ]);
    }

    /** ------------------------------------------------------------------
     * 检测验证码是否重复提交
     * @param string $type
     * @param string $name
     * @return bool  数据库中存在不过期的，就返回false 否则返回true
     *--------------------------------------------------------------------*/
    public function checkVirefyCode($type,$name){
        $data=$this->from('code')->_where([
            ['type','eq',$type],
            ['name','eq',$name],
            ['expired','lt',time()]
        ])->find(null,true);
        if($data){
            $this->msg='上次发送的验证码还没有过期，请在'.($data['expired']-time()).'秒后才能重新发送';
            return false;
        }
        return true;
    }

    /** ------------------------------------------------------------------
     * 检测接收到的验证码是否正确
     * @param string $name
     * @param string $code
     *  @param string $type
     * @return bool
     *--------------------------------------------------------------------*/
    public function checkReceiptCode($name,$code,$type=''){
        $this->from('code')->eq('name',$name);
        if($type){
            $this->eq('type',$type);
        }
        $data=$this->find(null,true);
        if(!$data){
            return false;
        }
        $code=(string)$code;
        return ($data['content']===$code);
    }

    //发送验证码
    public function sendCode($type,$name){
        if($type==='email'){
            //发邮件
            echo '发邮件';
        }elseif ($type==='phone'){
            //发短信
            echo '发短信';
        }
        //保存验证码
    }

    public function search(){

    }
    public function getOne($id,$select='*'){
        return $this->select($select)->eq('id',$id)->find(null,true);
    }
}