<?php
namespace core\lib\cache;

use extend\ImageResize;

class File
{
    /**
     * @var string:存储目录
     */
    private $path=ROOT.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'files';
    /**
     * @var int:存活时间
     */
    private $time = 3600;

    /** 构造函数
     * @param array $option：['path'=>'to/your/path','time'=>44444]
     */
    public function __construct($option=array())
    {
        if(isset($option['path']))
            $this->path=rtrim($option['path'],DIRECTORY_SEPARATOR);
        if(isset($option['time']))
            $this->time=$option['time'];
    }
    /** ------------------------------------------------------------------
     * 读取缓存：把缓存在文件中的json格式数据，全部原样读出
     * @param string $name：文件名
     * @return mixed:文件存在而且缓存时间没到期时返回原数据，否则返回false
     *---------------------------------------------------------------------*/
    public function get($name)
    {
        if (is_file($this->path . DIRECTORY_SEPARATOR . $name . '.php')) {
            $ret = json_decode(file_get_contents($this->path . DIRECTORY_SEPARATOR . $name . '.php'), true);
            if ($ret['time'] == 0 || $ret['time'] >= TIME) {
                return $ret['data'];
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /** ------------------------------------------------------------------
     * 把数据以json格式写入到缓存文件中
     * @param string $name：缓存文件名
     * @param mixed $data :数据
     * @param int|bool $time 缓存时间，0是永久，false时是现在时间+$this->time，否则是$time+time()
     * @return bool 写入成功返回true,否则抛出错误“写入权限不足”
     *---------------------------------------------------------------------*/
    public function set($name, $data, $time = false)
    {
        if ($time === false) {
            $time = time() + $this->time;
        } else if ($time === 0) {
            $time = 0;
        } else {
            $time +=  time();
        }
        $file = $this->path . DIRECTORY_SEPARATOR . $name . '.php';
        return self::write($file,json_encode([
            'data'=>$data,
            'time'=>$time
        ]));
    }

    /** ------------------------------------------------------------------
     * 删除缓存文件
     * @param string $name:缓存文件名
     * @return bool:成功删除返回true,否则返回false
     *---------------------------------------------------------------------*/
    public function del($name)
    {
        $file = $this->path . DIRECTORY_SEPARATOR . $name . '.php';
        if (is_file($file)) {
            return unlink($file);
        } else {
            return false;
        }
    }

    /** ------------------------------------------------------------------
     * 删除当前缓存目录下所有缓存文件
     *---------------------------------------------------------------------*/
    public function clear()
    {
        $dh = opendir($this->path);
        while ($file = readdir($dh)) {
            if ($file != "." && $file != "..") {
                $fullPath = $this->path . DIRECTORY_SEPARATOR . $file;
                if (!is_dir($fullPath)) {
                    unlink($fullPath);
                } else {
                    rmdir($fullPath);
                }
            }
        }
    }

    /** ------------------------------------------------------------------
     * 把字符串写入文件中
     * @param string $cacheFile
     * @param string $content
     * @param bool $add 是否是追加
     * @return bool 写入成功返回true,写入权限不足时返回false
     *---------------------------------------------------------------------
     */
    static public function write($cacheFile, $content,$add=false)
    {
        // 检测目录是否存在
        $dir = dirname($cacheFile);
        if (!is_dir($dir)) {
            if(!mkdir($dir, 0755, true))
                return false;
        }
        if($add)
            return file_put_contents($cacheFile, (string)$content,FILE_APPEND);
        else
            return file_put_contents($cacheFile, (string)$content);
    }

    /** ------------------------------------------------------------------
     * 检测一个文件是否有效
     * @param string $cacheFile
     * @param int $cacheTime
     * @return bool 存在且在有效期内返回true,否则返回false
     *---------------------------------------------------------------------*/
    static public function checkFile($cacheFile, $cacheTime){
        if (!file_exists($cacheFile)) {
            return false;
        }
        if (0 != $cacheTime && time() > (filemtime($cacheFile) + $cacheTime)) {
            return false;
        }
        return true;
    }

    /** ------------------------------------------------------------------
     * getFileInfo
     * @param string $file 文件完整路径
     * @return array|bool
     *---------------------------------------------------------------------*/
    static public function getFileInfo($file){
        if(!is_file($file))
            return false;
        $data=[];
        $data['mime']=self::getFileMime($file);
        $data['isimg']=self::checkIsImg($data['mime']) ? 1 :0;
        $data['ext']=self::getFileExt($file,$data['isimg']);
        $data['size']=filesize($file);
        $data['md5']=md5_file($file);
        if($data['md5']===false)
            $data['md5']='';
        $data['savename']=self::getFileNameBody($file);
        return $data;
    }

    /** ------------------------------------------------------------------
     * 获取文件的mime
     * @param string $file
     * @return string
     *--------------------------------------------------------------------*/
    static public function getFileMime($file){
        return (new \finfo(FILEINFO_MIME_TYPE))->file($file);
    }

    /** ------------------------------------------------------------------
     * 从mime信息判断文件是否是图片
     * @param string $mime
     * @return bool
     *--------------------------------------------------------------------*/
    static public function checkIsImg($mime){
        return  strstr($mime, 'image') !== false;
    }

    /** ------------------------------------------------------------------
     * 获取文件扩展名
     * @param string $file 文件完整路径
     * @param bool $isimg  是否是图片，直接从文件名解析不到扩展名时，图片会继续从mime中获取扩展名
     * @return string
     *--------------------------------------------------------------------*/
    static public function getFileExt($file,$isimg){
        $r_offset = strrpos($file, '.');
        $ext=$r_offset ?  substr($file, $r_offset + 1) : '';
        if($isimg && $ext===''){//图片扩展名为空时继续从mine中获取
            $ext=ImageResize :: getImagExtendName($file);
        }
        return $ext;
    }

    /** ------------------------------------------------------------------
     * 从文件路径中提取文件名（不带后缀）
     * @param string $file
     * @return string
     *--------------------------------------------------------------------*/
    static public function getFileNameBody($file){
        $name=basename($file);
        $r_offset = strrpos($name, '.');
        return  $r_offset ? substr($name, 0,$r_offset) : $name;
    }

}