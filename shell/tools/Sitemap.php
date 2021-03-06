<?php
/**
 * 网站专业定制：网站、微信公众号、小程序等一站式开发
 * QQ 46502166
 * @author: LaoYang
 * @email: 46502166@qq.com
 * @link:  http://dahulu.cc
 * ======================================
 * 网站地图生成工具
 * ======================================*/

namespace shell\tools;
use core\Conf;
use core\lib\cache\File;
use shell\BaseCommon;

class Sitemap extends BaseCommon
{
    static public $api='http://data.zz.baidu.com/urls?site=www.iweixinqun.cn&token=hfKnq1AHJXdqst0o';
    static public $logFile;
    public $path='cache/shell/tools/';
    public $fileBodyName='sitemap';
    public $perPage=20000;  //每个sitemap文件储存的链接条数
    public $isAutoSubmit=false;//是否自动sitemap提交
    public $isAutoPing=false; //是否进行自动ping提交
    protected $urls=[];


    public function __construct($param=[])
    {
        parent::__construct($param);
        $this->_setCommandOptions(['-a'=>['isAutoSubmit',true],'-p'=>['isAutoPing',true]],$this->param);
        self::$logFile=ROOT.'/'.$this->path.$this->fileBodyName.'.log';
    }

    /** ------------------------------------------------------------------
     * sitemap自动生成
     *--------------------------------------------------------------------*/
    public function create(){
        $where=[['last_time','lt',time()]];
        $total=$this->model->count([
            'from'=>'sitemap',
            'where'=>$where
        ]);
        if($total<1){
            $this->outPut('	没有新增加内容，所以无需生成sitemap'.PHP_EOL,true);
            return;
        }
        $this->doLoop($total,function ($perPage) use ($where){
            return $this->model->from('sitemap')->_where($where)->limit($perPage)->findAll(true);
        },function ($item){
            echo 'Generating table:'.$item['table_name'].'-------------------'.PHP_EOL;
            $last_id=$item['last_id'];
            $where=$item['condition'] ? json_decode($item['condition'],true):[];
            $total=$this->model->count([
                'from'=>$item['table_name'],
                'where'=>$this->getWhere($where,$last_id)
            ]);
            $this->outPut(' '.$item['table_name'].'=>本次需要生成sitemap：'.$total.'条'.PHP_EOL,true);
            if($total<1)
                return;
            $perPage=50;
            $page=(int)ceil($total/$perPage);
            $counter=$item['counter'];
            for ($i=0;$i<$page;$i++){
                $data=$this->model->from($item['table_name'])->_where($this->getWhere($where,$last_id))->limit($perPage)->order('id')->findAll(true);
                if(!$data)
                    break;
                $last_id=$this->sitemap($data,$item['table_name'],$counter);
                if($last_id ===false)
                    return;
            }
            $this->model->from('sitemap')->eq('id',$item['id'])->update([
                'last_id'=>$last_id,
                'counter'=>$counter,
                'last_time'=>time()
            ]);
            if($this->isAutoSubmit){
                self::submitMulti($this->urls,false);
            }
            if($this->isAutoPing){
                $count=self::ping($this->urls,$msg);
                $this->outPut(' 进行了ping提交,成功'.$count.'条!'.PHP_EOL.$msg,true);
            }
        });
    }


    /** ------------------------------------------------------------------
     * 生成查询条件
     * @param array $where
     * @param int $last_id
     * @return array
     *---------------------------------------------------------------------*/
    private function getWhere($where,$last_id){
        if($where)
            $where[]=['id','gt',$last_id];
        else
            $where=[['id','gt',$last_id]];
        return $where;
    }

    /** ------------------------------------------------------------------
     * 生成sitemap
     * @param array $data 数据
     * @param string $table 表名
     * @param int $counter 计数器
     * @return int|bool 成功返回最后一条记录的id，否则返回false
     *--------------------------------------------------------------------*/
    protected function sitemap($data,$table,&$counter){
        $siteUrl=Conf::get('site_url','site');
        $sitemap='';
        foreach ($data as $item){
            switch ($table){
                case 'portal_post':
                    switch ($item['type']){
                        case 'article':
                            $url_true=$siteUrl.'/article/'.$item['id'].'.html';
                            break;
                        case 'group':
                            $url_true=$siteUrl.'/group/'.$item['id'].'.html';
                            break;
                        case 'goods':
                            $url_true=$siteUrl.'/goods/'.$item['id'].'.html';
                            break;
                        case 'soft':
                            $url_true=$siteUrl.'/soft/'.$item['id'].'.html';
                            break;
                        default:
                            $this->outPut('	'.$table.'=>portal_post表中的switch里不存此type'.PHP_EOL,true);
                            continue;
                    }
                    $saveName='sitemap-portal';
                    break;
                case 'xuexiao':
                    $url_true=$siteUrl.'/xuexiao/'.$item['id'].'.html';
                    $saveName='sitemap-xuexiao';
                    break;
                case 'weixinqun':
                    $url_true=$siteUrl.'/weixinqun/'.$item['id'].'.html';
                    $saveName='sitemap-weixinqun';
                    break;
                default:
                    $this->outPut('	'.$table.'=>sitemap()方法中的switch里不存此table'.PHP_EOL,true);
                    return false;
            }
            $sitemap.=$url_true."\n";
            if($this->isAutoSubmit || $this->isAutoPing){
                $this->urls[]=$url_true;
            }
        }
        $page=ceil($counter/$this->perPage);
        $sitemapName=$saveName.($page>1 ? ('-'.($page-1)):'').'.txt';
        if(File::write(ROOT.'/public/'.$sitemapName,$sitemap,true))
            $this->outPut('    成功写入'.$sitemapName.PHP_EOL,true);
        else
            $this->outPut('    失败写入'.$sitemapName.PHP_EOL,true);
        $count=count($data);
        $counter+=$count;
        return $data[$count-1]['id'];
    }


    /** -----------------------------------------------------------------
     * 自动提交链接给搜索引擎
     * @param string|array $urls
     *      字符串时,多条用换行分隔
     *      数组时，格式 ['http://www.xxx.com/1.html','http://www.xxx.com/2.html','http://www.xxx.com/3.html']
     * @param string $api 提交的入口，百度请到百度站长工具获得入口
     * @param bool $status
     * @return string
     *--------------------------------------------------------------------*/
    static public function submit($urls,$api='',&$status=true){
        if(!$api)
            $api=self::$api;
        if(is_array($urls)){
            $urls=implode("\n",$urls);
        }
        $options =  array(
            CURLOPT_URL => $api,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER=> 1,
            CURLOPT_FOLLOWLOCATION =>1,
            CURLOPT_TIMEOUT=>15,
            CURLOPT_CONNECTTIMEOUT=>7,
            CURLOPT_POSTFIELDS => $urls,
            CURLOPT_HTTPHEADER => array('Content-Type: text/plain'),
            CURLOPT_HEADER=>false,
        );
        if(substr($api,0,5)=='https'){
            $options[CURLOPT_SSL_VERIFYPEER]=false;
            $options[CURLOPT_SSL_VERIFYHOST]=0;
        }
        $ch = curl_init();
        curl_setopt_array($ch, $options);
        $i=0;
        do{//失败重试三次
            $result = curl_exec($ch);
            $i++;
        }while ($result === false && $i <= 3 && msleep(1000,3000,false)==0);
        if ($result===false) {
            $status=false;
            $result= curl_error($ch);
        }else{
            $status=true;
        }
        curl_close($ch);
        //判断结果是否正确,暂时省略
        return $result;
    }

    /** ------------------------------------------------------------------
     * 自动提交链接到多个搜索引擎
     * @param array|string $urls 要提交的链接
     * @param bool $isReturn 是否要捕捉返回的结果
     * @param array $apis 搜索引擎提交入口集合 格式 ['baidu'=>'https://baidu.com/xxx..','so'=>'https://so/xxx..']
     * @return array 不捕捉结果或没有设置搜索引擎提交入口时，返回空数组，否则返回对应每个搜索引擎提交后的结果集，每个搜索引擎的结果集包含下面的信息:
     * [
     *          'code'=>0  //int ,0表示成功，其他数字表示出错
     *          'msg'=>''   //sting, 成功输出'success'，错误时返回错误信息
     * ]
     *--------------------------------------------------------------------*/
    static public function submitMulti($urls,$isReturn=false,$apis=[]){
        //echo ' 开始进行submitMulti'.PHP_EOL;
        //$apis=['baidu'=>'xxx.com','soso'=>'xx.ssoso.com'];
        $apis || $apis=Conf::get('sitemap_api','site');
        if(!$apis)
            return [];
        $ret=[];
        $count=is_array($urls) ? count($urls) : (preg_match_all('/\n/',$urls)+1);
        if($apis){
            foreach ($apis as $key => $api){
                $res=self::submit($urls,$api,$status);
                if($status){
					if(self::$logFile)
						File::write(self::$logFile,'    成功提交到'.$key.',提交数为'.$count.PHP_EOL,true);
                    if($isReturn)
                        $ret[$key]=['code'=>0,'msg'=>'success'];
                }else{
					if(self::$logFile)
						File::write(self::$logFile,'    成功提交到'.$key.',出错'.$res.PHP_EOL,true);
                    if($isReturn)
                        $ret[$key]=['code'=>1,'msg'=>$res];
                }
            }
        }else{
            File::write(self::$logFile,'    没有设置搜索引擎提交入口'.PHP_EOL,true);
        }
        return $ret;
    }

    /** ------------------------------------------------------------------
     * ping百度的博客提交入口，参考资料 http://help.baidu.com/question?prod_id=99&class=0&id=3046
     * @param string $url
     * @param string $msg 结果信息提示
     * @param string $siteName 网站名称
     * @param string $siteUrl 网站连接
     * @return bool 成功返回true,失败返回false
     *---------------------------------------------------------------------*/
    static public function pingBaidu($url,&$msg='',$siteName='',$siteUrl=''){
        if(!$siteName)
            $siteName=Conf::get('site_name','site');
        if(!$siteUrl)
            $siteUrl=Conf::get('site_url','site');
        $xml = <<<EOT
<?xml version="1.0" encode="UTF-8"?>
<methodCall>
    <methodName>weblogUpdates.extendedPing</methodName>
    <params>
        <param><value><string>{$siteName}</string></value></param>
        <param><value><string>{$siteUrl}/</string></value></param>
        <param><value><string>{$url}</string></value></param>
        <param><value><string>{$siteUrl}/feed/portal</string></value></param>
    </params>
</methodCall>
EOT;
        return self::pingHttp('http://ping.baidu.com/ping/RPC2',$xml,[
            'Content-Length: '.strlen($xml),
            'Content-Type: text/xml',
            'Host: ping.baidu.com',
            'Referer: http://ping.baidu.com/ping.html',
            //'User-Agent:Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.87 Safari/537.36',
            'User-Agent: request',
        ],$msg,'baidu');
    }

    /** ------------------------------------------------------------------
     * pingHttp
     * @param string $pingUrl 要ping的服务器地址
     * @param string $xml 提交的xml内容
     * @param array $header curl提交时的header信息
     * @param string $msg 结果提示信息
     * @param string $type 'baidu' or 'google'
     * @return bool 成功返回 true 失败返回 false
     *---------------------------------------------------------------------*/
    static protected function pingHttp($pingUrl,$xml,$header,&$msg,$type){
        if(!in_array($type,['google','baidu'],true)){
            $msg='type种类不正确';
            return false;
        }
        $options =  array(
            CURLOPT_URL => $pingUrl,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER=> 1,
            CURLOPT_FOLLOWLOCATION =>1,
            CURLOPT_HEADER=>$header,
            CURLOPT_TIMEOUT=>15,
            CURLOPT_CONNECTTIMEOUT=>7,
            CURLOPT_POSTFIELDS => $xml,
            CURLOPT_HEADER=>false,
        );
        if(substr($pingUrl,0,5)=='https'){
            $options[CURLOPT_SSL_VERIFYPEER]=false;
            $options[CURLOPT_SSL_VERIFYHOST]=0;
        }
        $ch = curl_init();
        curl_setopt_array($ch, $options);
        $i=0;
        do{//失败重试三次
            $result = curl_exec($ch);
            $i++;
        }while ($result === false && $i <= 3 && msleep(1000,3000,false)==0);
        //dump($result);
        if ($result===false) {
            $msg='连接超时：'.curl_error($ch);
            curl_close($ch);
        }else{
            curl_close($ch);
            $need=['google'=>'<boolean>0</boolean>','baidu'=>'<int>0</int>'];
            if(strpos($result,$need[$type])!==false){
                $result=true;
                $msg='success';
            }else{
                $result=false;
                $msg='提交失败，返回信息如下：'.$result;
            }
        }
        return $result;
    }

    /** ------------------------------------------------------------------
     * pingGoogle
     * @param string $url
     * @param string $msg 结果信息提示
     * @param string $siteName 网站名称
     * @param string $siteUrl 网站连接
     * @return bool 成功返回true,失败返回false
     *---------------------------------------------------------------------*/
    static public function pingGoogle($url,&$msg='',$siteName='',$siteUrl=''){
        if(!$siteName)
            $siteName=Conf::get('site_name','site');
        if(!$siteUrl)
            $siteUrl=Conf::get('site_url','site');
        $xml = <<<EOT
<?xml version="1.0" encode="UTF-8"?>
<methodCall>
  <methodName>weblogUpdates.extendedPing</methodName>
  <params>
    <param><value>{$siteName}</value></param>
    <param><value>{$siteUrl}</value></param>
    <param><value>{$url}</value></param>
    <param><value>{$siteUrl}/feed/portal</value></param>
  </params>
</methodCall>
EOT;
        return self::pingHttp('http://blogsearch.google.com/ping/RPC2',$xml,[
            'Content-Length: '.strlen($xml),
            'Content-Type: text/xml',
            'Host: blogsearch.google.com',
            'User-Agent: request',
        ],$msg,'google');
    }

    /** ------------------------------------------------------------------
     * 多条链接的ping提交
     * @param array $urls
     * @return int 返回成功提交的条数
     *---------------------------------------------------------------------*/
    static public function ping(array $urls,&$msg){
        if(!$urls){
            $msg = '     ping提交出错：urls不能为空'.PHP_EOL;
            return 0;
        }
        $count=0;
        $msg='';
        foreach ($urls as $url){
            $ret=self::pingBaidu($url,$msg_ret);
            if($ret)
                $count++;
             else
                $msg.='     baiduPing提交出错：url=>'.$url.',msg=>'.$msg_ret.PHP_EOL;
           /* $ret=self::pingGoogle($url,$msg_ret);
            if($ret)
                $count++;
            else
                $msg.='     GooglePing提交出错：url=>'.$url.',msg=>'.$msg_ret.PHP_EOL;*/
        }
        return $count;
    }

}