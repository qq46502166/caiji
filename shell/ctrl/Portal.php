<?php
/**
 * 网站专业定制：网站、微信公众号、小程序等一站式开发
 * QQ 46502166
 * @author: LaoYang
 * @email: 46502166@qq.com
 * @link:  http://dahulu.cc
 * ======================================
 * portal常用shell工具
 * ======================================*/

namespace shell\ctrl;
use core\Conf;
use shell\BaseCommon;
class Portal extends BaseCommon
{
    protected $fileBodyName='crontab';
    protected $path='cache/shell/ctrl/';
    public $taskName='';
    public $appName='';
    protected function _init(){
        $this->_setCommandOptions(['-t'=>['taskName'],'-n'=>['appName']],$this->param);
    }

    public function crontab(){
        switch ($this->taskName){
            case 'zhihu':
                $this->zhihu();
                break;
            case 'qilinyue':
                $this->qilinyue();
                break;
            case 'bilibili':
                $this->bilibili();
                break;
            default:
                $this->outPut('请输入正确的任务名，格式: -t taskName'.PHP_EOL,true);
        }
    }
    //知乎答案定时发布
    public function zhihu(){
        $table='caiji_zhihu_answer';
        $where=[['isfabu','eq',0]];
        $total=$this->model->count([
            'from'=>$table,
            'where'=>$where
        ]);
        //$num=mt_rand(2,4);
        $num=1;
        $i=0;
        $this->doLoop($total,function ($perPage)use ($table,$where){
            return $this->model->from($table)->_where($where)->limit($perPage)->findAll(true);
        },function ($item)use ($table,$num,&$i){
            $this->outPut('开始处理：id=>'.$item['id'].',from_id=>'.$item['from_id'].'-------------'.PHP_EOL);
            if($this->model->from('portal_post')->select('id')->eq('from_id',$item['from_id'])->find(null,true)){
                $this->outPut('  已经存在相同的from_id'.PHP_EOL);
                $this->update($item['id'],['isfabu'=>1],$table);
                return 1;
            }
            $data=[
                'title'=>mb_substr(strip_tags($item['content']),0,18),
                'content'=>$item['content'],
                'type'=>'group',
            ];
            $data['category_id']=mt_rand(3,574);
            $data['uid']=$this->getUserId($item['username']);
            $data['from_id']=$item['from_id'];
            $item['comment']=explode('{%|||%}',$item['comment']);
            $data['comments_num']=count($item['comment']);
            $data['create_time']=(int)strstr($item['comment'][$data['comments_num']-1],'{',true);
            if(!$data['create_time']) $data['create_time']=time();
            else $data['create_time'] -= mt_rand(20,40)*60;
            $data['update_time']=$data['create_time'];
            if($id=$this->model->from('portal_post')->insert($data)){
                $this->outPut(' 成功添加到portal_post表'.PHP_EOL);
                $this->addComment($item['comment'],$id,'portal_post');
                $this->addTag($id,$item['tag'],'portal_group',0);
                $this->update($item['id'],['isfabu'=>1],$table);
                $this->updateCateNum($data['category_id'],'portal_post');
            }
            $i++;
            if($i>=$num)
                return 'break all';
            return 0;
        });
    }

   public function qilinyue(){
       $table='caiji_boxcom';
       $where=[['isfabu','eq',0]];
       $total=$this->model->count([
           'from'=>$table,
           'where'=>$where
       ]);
       $num=mt_rand(1,3);
       //$num=1;
       $i=0;
       $this->doLoop($total,function ($perPage)use ($table,$where){
           return $this->model->from($table)->_where($where)->limit($perPage)->findAll(true);
       },function ($item)use ($table,$num,&$i){
           $this->outPut('开始处理：id=>'.$item['id'].',-------------'.PHP_EOL);
           $data=[
               'title'=>$item['title'],
               'type'=>'group',
           ];
           $data['category_id']=mt_rand(2,16);
           $item['username']='';
           $data['uid']=$this->getUserId($item['username']);
           $this->keywordLink2($item['content']);
           $item['comment']=explode('{%|||%}',$item['content']);
           $data['content']=array_shift($item['comment']);
           $data['comments_num']=count($item['comment']);
           $data['create_time']=time()-mt_rand(3600*2,3600*4);
           $data['update_time']=$data['create_time'];
           if($id=$this->model->from('portal_post')->insert($data)){
               $this->outPut(' 成功添加到portal_post表'.PHP_EOL);
               $this->addComment2($item['comment'],$id,$data['create_time'],'portal_post');
               //$this->addTag($id,$item['tag'],'portal_group',0);
               $this->update($item['id'],['isfabu'=>1],$table);
               $this->updateCateNum($data['category_id'],'portal_post');
           }
           $i++;
           if($i>=$num)
               return 'break all';
           return 0;
       });
   }

   public function bilibili(){
       $this->outPut('开始进行bilibili数据定时发布'.PHP_EOL,true);
       $table='caiji_bilibili';
       $where=[['isfabu','eq',0]];
       $config=Conf::all($this->appName,false,'config/bilibili/');
       //$num=mt_rand(1,2);
       $num=mt_rand($config['crontab_num_min'],$config['crontab_num_max']);
       if($num<1){
           $this->outPut('  本次定时发布个数为0，不进行发布'.PHP_EOL,true);
           $dataArr=[];
       }else{
           $dataArr=$this->model->from($table)->_where($where)->order('id')->limit($num)->findAll(true);
       }
       if($dataArr){
           foreach ($dataArr as $item){
               $this->outPut('开始处理：id=>'.$item['id'].',-------------'.PHP_EOL);
               $where=['from_id'=>$item['from_id'],['pfid',($item['pfid']>0 ? 'gt' : 'eq'),0]];
               if($this->model->from('portal_post')->_where($where)->find(null,true)){
                   $this->outPut(' 此from_id已入库'.PHP_EOL);
                   $this->update($item['id'],['isfabu'=>1],$table);
                   continue;
               }
               $data=[
                   'title'=>$item['title'],
                   'seo_title'=>$item['seo_title'],
                   'type'=>'group',
                   'from_id'=>$item['from_id'],
                   //'content'=>$item['content'],
                   'videos'=>$item['videos'],
               ];
               //确定pid
               if($item['pfid']>0){
                   $parent=$this->model->from('portal_post')->eq('from_id',$item['pfid'])->eq('pid',0)->find(null,true);
                   if(!$parent){
                       $this->outPut('  id=>'.$item['id'].'没有找到对应的父级'.PHP_EOL,true);
                       continue;
                   }
                   $data['pid']=$parent['id'];
               }
               $data['category_id']=mt_rand($config['crontab_category_id_min'],$config['crontab_category_id_max']);
               $item['username']='';
               $data['uid']=$this->getUserId($item['username']);
               $this->keywordLink2($data['content']);
               $item['comment']=$item['comment']?explode('{%@@@%}',$item['comment']):[];
               $data['content']=$this->getContentFromComment($item['comment']);
               $data['comments_num']=count($item['comment']);
               $data['create_time']=time()-mt_rand(3600*2,3600*4);
               $data['update_time']=$data['create_time'];
               if($id=$this->model->from('portal_post')->insert($data)){
                   $this->outPut(' 成功添加到portal_post表'.PHP_EOL);
                   $this->addComment3($item['comment'],$id,$data['create_time'],'portal_post');
                   $this->addTag($id,$item['tag'],'portal_group',0);
                   $this->update($item['id'],['isfabu'=>1],$table);
                   $this->updateCateNum($data['category_id'],'portal_post');
                   $this->updateCommentIssue($data['from_id']);
               }
           }
       }
       $this->fabu_comment($config['crontab_comment']??5);
       Conf::clear($this->appName,null,'config/bilibili/');
   }
    protected function getContentFromComment($comments){
        if(is_string($comments))
            $comments=explode('{%@@@%}',$comments);
        $result=[];
        $i=0;
        foreach ($comments as $comment){
            if(!$comment)
                continue;
            if($i>7)
                break;
            if(($pos=strpos($comment,'{%##%}'))!==false){
                $comment=substr($comment,0,$pos);
            }
            list(,,$result[])=explode('{%@@%}',$comment);
            $i++;
        }
        return implode("\n",$result);
    }

    protected function addTag($oid,$tags,$type,$isPeople){
        return app('\app\admin\model\Tag')->addFromOid($oid,$tags,$type,1,$isPeople);
    }

    /** ------------------------------------------------------------------
     * update
     * @param int $id
     * @param array $data
     *---------------------------------------------------------------------*/
    protected function update($id,$data,$table){
        if($this->model->from($table)->eq('id',$id)->update($data))
            $this->outPut('成功：更新'.$table.'！'.PHP_EOL);
        else
            $this->outPut('失败：更新'.$table.'！'.PHP_EOL);
    }

    /** ------------------------------------------------------------------
     * 获取分类id
     * @param string $name
     * @return int
     *--------------------------------------------------------------------*/
    protected function getCategoryId($name){
        $data=$this->model->from('category')->select('id')->eq('name',$name)->eq('type','portal_group')->find(null,true);
        if($data)
            return (int)$data['id'];
        $arr=[3,4,5,25,22,29];
        return $arr[array_rand($arr,1)];
    }

    /** ------------------------------------------------------------------
     * 获取用户id : 用户名为空时取随机用户，用户名不存时自动添加用户
     * @param $username
     * @return int
     *---------------------------------------------------------------------*/
    protected function getUserId(&$username){
        $model=app('\app\portal\model\User');
        //用户名为空时 取随机用户
        if(!$username){
            return $model->randomUserEx($username);
            //$data=$model->getRandomUser(1,'id,username');
           /* if($data){
                $username=$data['0']['username'];
                return $data[0]['id'];
            }
            return 0;*/
        }
        return $model->addFromName($username);
    }

    protected function updateCateNum($id,$table){
        $num=$this->model->count([
            'from'=>$table,
            'where'=>[['category_id','eq',$id]]
        ]);
        if($this->model->from('category')->eq('id',$id)->update(['counts'=>$num]))
            $this->outPut(' 成功更新分类下post个数:'.$num.PHP_EOL);
        else
            $this->outPut(' 失败更新分类下post个数时:'.$num.PHP_EOL);
    }

    protected function updateCommentIssue($fid){
        if($this->model->from('caiji_bilibili_comment')->eq('fid',$fid)->update([
            'issue'=>1,
        ]))
            $this->outPut(' 成功更新评论issue为1'.PHP_EOL);
    }
    /** ------------------------------------------------------------------
     * 添加评论
     * @param array $data
     * @param int $id
     * @param string $table
     * @param array $separator {%||%}
     *---------------------------------------------------------------------*/
    protected function addComment($data,$oid,$table,$separator=['{%|||%}','{%||%}']){
        if(is_string($data))
            $data=explode($separator[0],$data);
        foreach ($data as $item){
            if(!$item){
                $this->outPut(' 本项评论为空跳过!'.PHP_EOL);
                continue;
            }
            $item=explode($separator[1],$item);
            $count=count($item);
            if(count($item) !==3){
                $this->outPut(' 本项评论项目不对：'.$count.PHP_EOL);
                continue;
            }
            $in=[];
            $in['create_time']=$item[0];
            $in['username']=$item[1];
            $in['content']=$item[2];
            unset($item);
            $in['uid']=$this->getUserId($in['username']);
            $in['table_name']=$table;
            $in['oid']=$oid;
            if(!$this->model->from('comment')->insert($in)){
                $this->outPut(' 插入comment表失败!'.PHP_EOL);
                $this->outPut(' 最后的sql:'.$this->model->getSql().PHP_EOL);
                dump($data);
                exit();
            }
        }
    }
    /** ------------------------------------------------------------------
     * 添加评论
     * @param array $data
     * @param int $id
     * @param int $create_time
     * @param string $table
     * @param array $separator {%||%}
     *---------------------------------------------------------------------*/
    protected function addComment2($data,$oid,$create_time,$table,$separator='{%|||%}'){
        if(is_string($data))
            $data=explode($separator,$data);
        foreach ($data as $item){
            if(!$item){
                $this->outPut(' 本项评论为空跳过!'.PHP_EOL);
                continue;
            }
            $create_time+=mt_rand(700,31*60);
            $in=[];
            $in['create_time']=$create_time;
            $in['username']='';
            $in['content']=$item;
            $in['uid']=$this->getUserId($in['username']);
            $in['table_name']=$table;
            $in['oid']=$oid;
            if(!$this->model->from('comment')->insert($in)){
                $this->outPut(' 插入comment表失败!'.PHP_EOL);
                $this->outPut(' 最后的sql:'.$this->model->getSql().PHP_EOL);
                dump($data);
                exit();
            }
        }
    }
    /** ------------------------------------------------------------------
     * 添加评论
     * @param string|array $data
     * @param int $id
     * @param int $create_time
     * @param string $table
     * @param array $separator {%||%}
     *---------------------------------------------------------------------*/
    protected function addComment3($data,$oid,$create_time,$table,$separator=['{%@@@%}','{%@@%}'],$pid=0){
        if(is_string($data))
            $data=explode($separator[0],$data);
        $isFalse=false;
        foreach ($data as $item){
            if(!$item){
                $this->outPut(' 本项评论为空跳过!'.PHP_EOL);
                continue;
            }
            if(!$this->addCommentOne3($item,$oid,$create_time,$table,$separator[1],$pid))
                $isFalse=true;
        }
        return ($isFalse !== true);
    }

    protected function addCommentOne3($item,$oid,&$create_time,$table,$separator='{%@@%}',$pid=0){
        if(is_string($item))
            $item=explode($separator,$item);
        $count=count($item);
        if(count($item) !==3){
            $this->outPut(' 本项评论项目不对：'.$count.PHP_EOL);
            return false;
        }
        $create_time+=mt_rand(700,31*60);
        $in=[];
        $in['create_time']=$create_time;
        $in['username']='';
        $in['pid']=$pid;
        $children='';
        if(strpos($item[2],'{%##%}')){
            list($in['content'],$children)=explode('{%##%}',$item[2]);
        }else
            $in['content']=$item[2];
        $this->keywordLink2($in['content']);
        $in['uid']=$this->getUserId($in['username']);
        $in['table_name']=$table;
        $in['oid']=$oid;
        //子评论数，和is_content的确定
        if($children){
            $in['children']=substr_count($children,'{%|||%}')+1;
            $length=mb_strlen(strip_tags($in['content']));
            if(($in['children']>1 &&$length>30) || $length>120)
                $in['is_content']=1;
        }
        $id=$this->model->from('comment')->insert($in);
        if(!$id){
            $this->outPut(' 插入comment表失败!'.PHP_EOL,true);
            $this->outPut(' 最后的sql:'.$this->model->getSql().PHP_EOL,true);
            dump($in);
            return false;
        }
        if($children){
            $this->outPut(' 开始处理子评论!'.PHP_EOL);
            $this->addComment3($children,$oid,$create_time,$table,['{%|||%}','{%||%}'],$id);
        }
        return $id;
    }

    /** ------------------------------------------------------------------
     * 站内描文本添加
     * @param string $content
     * @return int 返回发生替换的次数
     *--------------------------------------------------------------------*/
    protected function keywordLink(&$content){
        //去掉用户名和发布时间  时间{%||%}用户名{%||%}内容{%|||%}时间{%||%}用户名{%||%}内容{%|||%}
        $i=-1;
        $user_filter=[];
        $content=preg_replace_callback('#\{%\|\|%\}.*?\{%\|\|%\}#' ,function ($mat) use (&$i,&$user_replace,&$user_filter){
            $i++;
            $user_replace[$i]=$mat[0];
            $user_filter[$i]='{%user_filter_'.$i.'%}';
            return $user_filter[$i];
        },$content);
        $model=app('\app\admin\model\KeywordLink');
        $count=$model->doLoop($content);
        if($i>-1){
            $content=str_replace($user_filter,$user_replace,$content);
        }
        return $count;
    }

    protected function keywordLink2(&$content){
        return app('\app\admin\model\KeywordLink')->doLoop($content);
    }

    public function fabu_comment($num=2){
        $this->outPut('从bilibili_comment表发布评论'.PHP_EOL,true);
        if($num<1)
            return;
        $data=$this->model->select('*')->from('caiji_bilibili_comment')->eq('issue',1)->eq('isfabu',0)->order('create_time,id')->limit($num)->findAll(true);
        if($data){
            foreach ($data as $item){
                $msg=' 处理id=>'.$item['id'];
                $pdata=$this->model->select('id')->from('portal_post')->eq('from_id',$item['fid'])->find(null,true);
                if(!$pdata){
                    $this->outPut($msg.',没有找到父级'.PHP_EOL,true);
                    continue;
                }
                $create_time=time()-700;
                if($item['more'])
                    $item['content'].='{%##%}'.$item['more'];
                $id=$this->addCommentOne3([$item['create_time'],$item['username'],$item['content']],$pdata['id'],$create_time,'portal_post',0);
                if($id){
                    $msg.=',成功发布到comment表';
                    //更新评论数
                    $count=$this->model->count([
                        'from'=>'comment',
                        'where'=>[['oid','eq',$pdata['id']],['table_name','eq','portal_post']],
                    ]);
                    $this->model->from('portal_post')->eq('id',$pdata['id'])->update(['comments_num'=>$count]);
                    if($this->model->from('caiji_bilibili_comment')->eq('id',$item['id'])->update(['isfabu'=>1]))
                        $msg.=',成功:更新caiji_bilibili_comment表';
                    else
                        $msg.=',失败:更新caiji_bilibili_comment表时';
                }else{
                    $msg.=',失败:发布到comment表时';
                }
                $this->outPut($msg.PHP_EOL,true);
            }
        }else
            $this->outPut(' 没有符合条件的评论'.PHP_EOL,true);
        //if()
    }

    public function dodo(){
        $table='caiji_renren_name';
        $where=[['isdone','eq',0]];
        $total=$this->model->count([
            'from'=>$table,
            'where'=>$where
        ]);
        $this->doLoop($total,function ($perPage)use ($table,$where){
            return $this->model->select('*')->from($table)->_where($where)->limit($perPage)->findAll(true);
        },function ($item)use ($table){
            echo '开始处理：id=>'.$item['id'].'---------------'.PHP_EOL;
            $update=[ 'isdone'=>1];
            if($item['text']){
                echo '  处理text=>';
                $update['text']=$this->filter($item['text']);
                if($update['text'] !==$item['text']){
                    if ($update['text']=='')
                        $update['md5']='';
                    else{
                        if(mb_strlen($update['text'])<40){
                            $update['text']='';
                            $update['md5']='';
                        }else
                            $update['md5']=md5($update['text']);
                    }

                }else
                    unset($update['text']);
                echo '---'.PHP_EOL;
            }
            if($item['signature']){
                echo '  处理signature=>';
                $update['signature']=$this->filter($item['signature']);
                if($update['signature'] !==$item['signature']){
                    if ($update['signature']=='')
                        $update['sign_md5']='';
                    else{
                        if(mb_strlen($update['signature'])<20){
                            $update['signature']='';
                            $update['sign_md5']='';
                        }else
                            $update['sign_md5']=md5($update['signature']);
                    }
                }else
                    unset($update['signature']);
                echo '---'.PHP_EOL;
            }
            if($this->model->from($table)->eq('id',$item['id'])->update($update))
                echo '  成功：更新'.PHP_EOL;
            else{
                echo '  失败：更新'.PHP_EOL;
                exit();
            }
            //msleep(20000);
        });
    }

    public function dodo2(){
        $table='caiji_renren_name';
        $where=[['isdone','eq',0]];
        $total=$this->model->count([
            'from'=>$table,
            'where'=>$where
        ]);
        $this->doLoop($total,function ($perPage)use ($table,$where){
            return $this->model->select('*')->from($table)->_where($where)->limit($perPage)->findAll(true);
        },function ($item)use ($table){
            echo '开始处理：id=>'.$item['id'].'---------------'.PHP_EOL;
            $update=[ 'isdone'=>1];
            if($item['text']){
                echo '  处理text=>';
                $update['text']=$this->filter2($item['text'],false);
                if($update['text'] !==$item['text']){
                    if ($update['text']=='')
                        $update['md5']='';
                    else{
                        if(mb_strlen(strip_tags($update['text']))<40){
                            $update['text']='';
                            $update['md5']='';
                        }else
                            $update['md5']=md5($update['text']);
                    }
                }else
                    unset($update['text']);
                echo '---'.PHP_EOL;
            }
            if($item['signature']){
                echo '  处理signature=>';
                $update['signature']=$this->filter2($item['signature']);
                if($update['signature'] !==$item['signature']){
                    if ($update['signature']=='')
                        $update['sign_md5']='';
                    else{
                        if(mb_strlen($update['signature'])<20){
                            $update['signature']='';
                            $update['sign_md5']='';
                        }else
                            $update['sign_md5']=md5($update['signature']);
                    }
                }else
                    unset($update['signature']);
                echo '---'.PHP_EOL;
            }
            if($this->model->from($table)->eq('id',$item['id'])->update($update))
                echo '  成功：更新'.PHP_EOL;
            else{
                echo '  失败：更新'.PHP_EOL;
                exit();
            }
            //msleep(20000);
        });
    }

    protected function filter2($str,$strip=true){
        if($strip)
            $str=strip_tags($str);
        else
            $str=strip_tags($str,'<br>');
        return trim(preg_replace(['%[\r\n\t]+%','/&nbsp;/','/\s{2,}/'],['',' ',' '],$str));
    }

    protected function filter($str){
        if(strpos($str,'老男孩')!==false){
            return '';
        }
        if(strpos($str,'{%|||%}')!==false){
            $arr=explode('{%|||%}',$str);
            echo '多条bili评论,';
            $count=count($arr);
            $str='';
            for ($i=0;$i<$count;$i++){
                if($i==0){
                    $str.=$this->filterBili($arr[$i]);
                }else
                    $str.='<br>'.$this->filterBili($arr[$i]);
            }
        }elseif(strpos($str,'{%||%}')!==false){
            $str=$this->filterBili($str);
        }
        $str=str_replace(['电影','这部片'],['视频','这个视频'],$str);
        $str=preg_replace([
            '%回复 @.+?:%',
            '/\[.*?\]/',
            '%(！|。|，|？|：|；|“|”|《|》|（|）|—|、){2,}%',
            '/[,.\\,}#@%&*!()_\-~`|$\/?]{2,}/',
        ],[
            '',
            '',
            ';',
            '.'
        ],$str);
        return $str;
    }

    protected function filterBili($str){
        if(!$str)
            return '';
        echo 'bili子评论,';
        list(,,$content)=explode('{%||%}',$str);
        return $content;
    }

    public function test(){
        $table='portal_post';
        $where=[['uid','eq',0]];
        $total=999999;
        $this->doLoop($total,function ($perPage)use ($table,$where){
            return $this->model->select('id')->from($table)->_where($where)->limit($perPage)->findAll(true);
        },function ($item)use ($table){
            echo '开始处理：id=>'.$item['id'].'-----------'.PHP_EOL;
            $username='';
            $uid=$this->getUserId($username);
            $update=[ 'uid'=>$uid];
            if($this->model->from($table)->eq('id',$item['id'])->update($update))
                echo '  成功：更新'.PHP_EOL;
            else
                echo '  失败：更新'.PHP_EOL;
            //msleep(2000);
        });
    }

    public function test2(){
        echo mb_strlen('嗨Howard，我第一次遇到问题时确实阅读了这篇文章。问题是标签现在只有在您搜索它们时才可用-这意味着您需要知道您要查找什么。我有几十个标签可以对数百个文档进行排序。在此之前，当我想按标签排序或添加标签时，我会从');
    }

}