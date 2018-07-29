<?php
/*
 * Created on 2014-12-10
 *
 * To change the template for this generated file go to
 * Window - Preferences - PHPeclipse - PHP - Code Templates
 */
 class redisdo
 {
 	public $G;
	private $log = 0;
    // 是否使用 M/S 的读写集群方案
    private $_isUseCluster = false;
    // Slave 句柄标记
    private $_sn = 0;
       
    // 服务器连接句柄
    private $_linkHandle = array(
        'master'=>null,// 只支持一台 Master
        'slave'=>array(),// 可以有多台 Slave
    );
       
    /**
     * 构造函数
     *
     * @param boolean $isUseCluster 是否采用 M/S 方案
     */
    public function __construct(&$G,$isUseCluster=false)
    {
    	$this->G = $G;
    	$this->_isUseCluster = $isUseCluster;
    }

    private function _log($sql)
    {
    	if($this->log)
    	{
    		$fp = fopen('data/rediserror.log','a');
			fputs($fp,print_r($sql,true));
			fclose($fp);
    	}
    }
       
    /**
     * 连接服务器,注意：这里使用长连接，提高效率，但不会自动关闭
     *
     * @param array $config Redis服务器配置
     * @param boolean $isMaster 当前添加的服务器是否为 Master 服务器
     * @return boolean
     */
    public function connect($host = RDH,$port = RPORT,$password = RPASS, $isMaster=true){
        // 设置 Master 连接
        if($isMaster){
            $this->_linkHandle['master'] = new Redis();
            $this->_linkHandle['master']->pconnect($host,$port);
            $ret = $this->_linkHandle['master']->auth($password);
        }else{
            // 多个 Slave 连接
            $this->_linkHandle['slave'][$this->_sn] = new Redis();
            $ret = $this->_linkHandle['slave'][$this->_sn]->pconnect($host,$port);
            $ret = $this->_linkHandle['slave'][$this->_sn]->auth($password);
            ++$this->_sn;
        }

        return $ret;
    }
       
    /**
     * 关闭连接
     *
     * @param int $flag 关闭选择 0:关闭 Master 1:关闭 Slave 2:关闭所有
     * @return boolean
     */
    public function close($flag=2){
        switch($flag){
            // 关闭 Master
            case 0:
                $this->getRedis()->close();
            break;
            // 关闭 Slave
            case 1:
                for($i=0; $i<$this->_sn; ++$i){
                    $this->_linkHandle['slave'][$i]->close();
                }
            break;
            // 关闭所有
            case 1:
                $this->getRedis()->close();
                for($i=0; $i<$this->_sn; ++$i){
                    $this->_linkHandle['slave'][$i]->close();
                }
            break;
        }
        return true;
    }
       
    /**
     * 得到 Redis 原始对象可以有更多的操作
     *
     * @param boolean $isMaster 返回服务器的类型 true:返回Master false:返回Slave
     * @param boolean $slaveOne 返回的Slave选择 true:负载均衡随机返回一个Slave选择 false:返回所有的Slave选择
     * @return redis object
     */
    public function getRedis($isMaster=true,$slaveOne=true){
        // 只返回 Master
        if($isMaster){
        	if(!$this->_linkHandle['master']){
        		$this->connect();
        	}
            return $this->_linkHandle['master'];
        }else{
        	if ($slaveOne) {
        		return $this->_getSlaveRedis();
        	}else{
        		if(!$this->_linkHandle['slave']){
        			$this->connect();
        		}
        		return $this->_linkHandle['slave'];
        	}
        }
    }
       
    /**
     * 写缓存
     *
     * @param string $key 组存KEY
     * @param string $value 缓存值
     * @param int $expire 过期时间， 0:表示无过期时间
     */
    public function set($key, $value, $expire=0){
        // 永不超时
        if($expire == 0){
            $ret = $this->getRedis()->set($key, $value);
        }else{
            $ret = $this->getRedis()->setex($key, $expire, $value);
        }
        return $ret;
    }
       
    /**
     * 读缓存
     *
     * @param string $key 缓存KEY,支持一次取多个 $key = array('key1','key2')
     * @return string || boolean  失败返回 false, 成功返回字符串
     */
    public function get($key){
        // 是否一次取多个值
        $func = is_array($key) ? 'mGet' : 'get';
        // 没有使用M/S
        if(! $this->_isUseCluster){
            return $this->getRedis()->{$func}($key);
        }
        // 使用了 M/S
        return $this->_getSlaveRedis()->{$func}($key);
    }
    
 
/*
    // magic function 
    public function __call($name,$arguments){
        return call_user_func($name,$arguments);    
    }
*/
    /**
     * 条件形式设置缓存，如果 key 不存时就设置，存在时设置失败
     *
     * @param string $key 缓存KEY
     * @param string $value 缓存值
     * @return boolean
     */
    public function setnx($key, $value){
        return $this->getRedis()->setnx($key, $value);
    }
       
    /**
     * 删除缓存
     *
     * @param string || array $key 缓存KEY，支持单个健:"key1" 或多个健:array('key1','key2')
     * @return int 删除的健的数量
     */
    public function remove($key){
        // $key => "key1" || array('key1','key2')
        return $this->getRedis()->del($key);
    }
       
    /**
     * 值加加操作,类似 ++$i ,如果 key 不存在时自动设置为 0 后进行加加操作
     *
     * @param string $key 缓存KEY
     * @param int $default 操作时的默认值
     * @return int　操作后的值
     */
    public function incr($key,$default=1){
        if($default == 1){
            return $this->getRedis()->incr($key);
        }else{
            return $this->getRedis()->incrBy($key, $default);
        }
    }
       
    /**
     * 值减减操作,类似 --$i ,如果 key 不存在时自动设置为 0 后进行减减操作
     *
     * @param string $key 缓存KEY
     * @param int $default 操作时的默认值
     * @return int　操作后的值
     */
    public function decr($key,$default=1){
        if($default == 1){
            return $this->getRedis()->decr($key);
        }else{
            return $this->getRedis()->decrBy($key, $default);
        }
    }
       
    /**
     * 添空当前数据库
     *
     * @return boolean
     */
    public function clear(){
        return $this->getRedis()->flushDB();
    }
    /**
     * 批量删除以key*开头的数据
     *
     * @return boolean
     */
    public function multidel($key){
        $lists = $this->getRedis()->delete($this->getRedis()->keys($key.':*')); 
		/*foreach ( $lists as $value) { 
		  $this->getRedis()->del($value);
		}*/
		return true;
    }
	/**
     * 获取以key*开头的数据
     *
     * @return boolean
     */
    public function getlist($key){
        return $this->getRedis()->keys($key.':*'); 
    }
       
    /* =================== 以下私有方法 =================== */
       
    /**
     * 随机 HASH 得到 Redis Slave 服务器句柄
     *
     * @return redis object
     */
    private function _getSlaveRedis(){
        // 就一台 Slave 机直接返回
        if($this->_sn <= 1){
            return $this->_linkHandle['slave'][0];
        }
        // 随机 Hash 得到 Slave 的句柄
        $hash = $this->_hashId(mt_rand(), $this->_sn);
        return $this->_linkHandle['slave'][$hash];
    }
       
    /**
     * 根据ID得到 hash 后 0～m-1 之间的值
     *
     * @param string $id
     * @param int $m
     * @return int
     */
    private function _hashId($id,$m=10)
    {
        //把字符串K转换为 0～m-1 之间的一个值作为对应记录的散列地址
        $k = md5($id);
        $l = strlen($k);
        $b = bin2hex($k);
        $h = 0;
        for($i=0;$i<$l;$i++)
        {
            //相加模式HASH
            $h += substr($b,$i*2,2);
        }
        $hash = ($h*1)%$m;
        return $hash;
    }

    /**
     *    lpush 
     */
    public function lpush($key,$value){
        return $this->getRedis()->lpush($key,$value);
    }

    /**
     *    add lpop
     */
    public function lpop($key){
        return $this->getRedis()->lpop($key);
    }
    /**
     * lrange 
     */
    public function lrange($key,$start,$end){
        return $this->getRedis()->lrange($key,$start,$end);    
    }
	/**
     *    mset hash opeation
     */
    public function hmset($name,$value){
        foreach ($value as $k => $v) {
            if (is_array($v)) {
                $value[$k]=serialize($v);
            }
        }
        return $this->getRedis()->hmset($name,$value);
    }
    /**
     *    set hash opeation
     */
    public function hset($name,$key,$value){
        if(is_array($value)){
            return $this->getRedis()->hset($name,$key,serialize($value));    
        }
        return $this->getRedis()->hset($name,$key,$value);
    }
    /**
     *    get hash opeation
     */
    public function hget($name,$key = null,$serialize=true){
    	//if(!$this->getRedis())$this->connect();
        if($key){
            $row = $this->getRedis()->hget($name,$key);
            if($row && $serialize){
                unserialize($row);
            }
            return $row;
        }

        $result=$this->getRedis()->hgetAll($name);
        foreach ($result as $k => $v) {
            if($v && $this->is_serialized($v)){
                $result[$k]=unserialize($v);
            }
        }
        return $result;
    }

    /**
     *    delete hash opeation
     */
    public function hdel($name,$key = null){
        if($key){
            return $this->getRedis()->hdel($name,$key);
        }
        return $this->getRedis()->del($name);
    }
     /**
     * 集合添加
     *
     * @param string $key 组存KEY
     * @param string $value 缓存值
     */
    public function sadd($name, $value){
        return $this->getRedis()->sAdd($name,$value);
    }
     /**
     * 集合删除
     *
     * @param string $key 组存KEY
     * @param string $value 缓存值
     */
    public function srem($name, $value){
        return $this->getRedis()->sRem($name,$value);
    }
    /**
     * 集合求交集
     *
     * @param string $key 组存KEY
     * @param string $value 缓存值
     */
    public function sinter($name){
        return $this->getRedis()->sInter($name);
    }
    /**
     * 返回集合内所有元素
     *
     * @param string $key 组存KEY
     * @param string $value 缓存值
     */
    public function smembers($name){
        return $this->getRedis()->sMembers($name);
    }
    /**
     * Transaction start
     */
    public function multi(){
        return $this->getRedis()->multi();    
    }
    /**
     * Transaction send
     */

    public function exec(){
        return $this->getRedis()->exec();    
    }
    /**
     * 判断数据是否被序列化
     */
    public function is_serialized( $data ) {
     $data = trim( $data );
     if ( 'N;' == $data )
         return true;
     if ( !preg_match( '/^([adObis]):/', $data, $badions ) )
         return false;
     switch ( $badions[1] ) {
         case 'a' :
         case 'O' :
         case 's' :
             if ( preg_match( "/^{$badions[1]}:[0-9]+:.*[;}]\$/s", $data ) )
                 return true;
             break;
         case 'b' :
         case 'i' :
         case 'd' :
             if ( preg_match( "/^{$badions[1]}:[0-9.E-]+;\$/", $data ) )
                 return true;
             break;
     }
     return false;
 }
 }
?>
