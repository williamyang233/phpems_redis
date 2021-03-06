<?php

class session
{
	public $G;
	public $sessionname = 'currentuser';
	public $sessionuser = false;
	public $sessionid;

    public function __construct(&$G)
    {
    	$this->G = $G;
        $this->redisdo = $this->G->make("redisdo");
        $this->db = $this->G->make("pepdo");
    	$this->ev = $this->G->make("ev");
    	$this->pdosql = $this->G->make("pdosql");
    	$this->sql = $this->G->make("sql");
    	$this->strings = $this->G->make("strings");
    	$this->sessionid = $this->getSessionId();
    }

    //获取会话ID
    public function getSessionId()
    {
    	if(!$this->sessionid)
    	{
    		if($_SESSION['currentuser'])
			{
                $this->sessionid = $_SESSION['currentuser']['sessionid'];
                $this->ev->setCookie('psid',$this->sessionid,3600*24);
			}
    		else
    		{
    			$cookie = $this->strings->decode($this->ev->getCookie($this->sessionname));
    			if($cookie)
    			{
    				$this->sessionid = $cookie['sessionid'];
                    $this->ev->setCookie('psid',$this->sessionid,3600*24);
    			}
    			else
    			$this->sessionid = $this->ev->getCookie('psid');
    		}
    	}
    	if(!$this->sessionid)
    	{
    		$this->sessionid = session_id();
    		$this->ev->setCookie('psid',$this->sessionid,3600*24);
    	}
    	if(!$this->sessionid)
    	{
    		$this->sessionid = md5(TIME.rand(1000,9999));
    		$this->ev->setCookie('psid',$this->sessionid,3600*24);
    	}
    	if(!$this->getSessionValue($this->sessionid))
		{
			$data = array('sessionid'=>$this->sessionid,'sessionuserid'=>0,'sessionip'=>$this->ev->getClientIp());
            $result=$this->redisdo->hmset('session:'.$this->sessionid,$data);
		}
    	return $this->sessionid;
    }

    //设置随机参数
    public function setRandCode($randCode)
    {
    	if(!$randCode)
    	{
	    	$array = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','0','1','2','3','4','5','6','7','8','9');
    		$randCode = '';
    		for($i=0;$i<4;$i++)
    		{
    			$randCode .= $array[intval(rand(0,35))];
	    	}
    	}
    	if(!$this->sessionid)$this->getSessionId();
    	$data = array('sessionrandcode'=>$randCode);
        $r=$this->redisdo->hmset('session:'.$this->sessionid,$data);
	    if($r)return $randCode;
    	else
    	{
    		$data = array('sessionid'=>$this->sessionid,'sessionuserid'=>0,'sessionip'=>$this->ev->getClientIp());
            $this->redisdo->hmset('session:'.$this->sessionid,$data);
            return $this->setRandCode($randCode);
    	}
    }

    //获取随机参数
    public function getRandCode()
    {
    	if(!$this->sessionid)$this->getSessionId();
    	//$data = array('sessionrandcode','session',array(array('AND',"sessionid = :sessionid",'sessionid',$this->sessionid)));
    	$r = $this->redisdo->hget('session:'.$this->sessionid);
    	return $r['sessionrandcode'];
    }

    //获取会话内容
    public function getSessionValue($sessionid = NULL)
    {
    	if(!$sessionid)
    	{
    		if(!$this->sessionid)$this->getSessionId();
    		$sessionid = $this->sessionid;
    	}
    	//$data = array(false,'session',array(array('AND',"sessionid = :sessionid",'sessionid',$this->sessionid)));
        return $this->redisdo->hget('session:'.$this->sessionid);

    }

    //设置会话用户信息
    public function setSessionUser($args = NULL)
    {
    	if(!$args)return false;
    	else
    	{
            if(!$args['sessiontimelimit'])$args['sessiontimelimit'] = TIME;
	    	if(!$this->sessionid)$this->getSessionId();
	    	$args['sessionid'] = $this->sessionid;
	    	$args['sessiontimelimit'] = TIME;
            $this->redisdo->remove('session:'.$this->sessionid);
            $this->redisdo->hmset('session:'.$this->sessionid,$args);
            $this->redisdo->set('session2user:'.$args['sessionuserid'],$this->sessionid);
	    	$this->ev->setCookie($this->sessionname,$this->strings->encode($args),3600*24);
	    	$_SESSION['currentuser'] = $args;
	    	return true;
    	}
    }

    //设置会话中其他信息
    public function setSessionValue($args = NULL)
    {
		if(!$args)return false;
    	else
    	{
	    	if(!$this->sessionid)$this->getSessionId();
            $this->redisdo->hmset('session:'.$this->sessionid,$args);
            //$data = array('session',$args,array(array('AND',"sessionid = :sessionid",'sessionid',$this->sessionid)));
	    	//$sql = $this->pdosql->makeUpdate($data);
	    	//$this->db->exec($sql);
	    	return true;
    	}
    }

    //获取会话用户
    public function getSessionUser()
    {
    	if($this->sessionuser)return $this->sessionuser;
    	$cookie = $this->strings->decode($this->ev->getCookie($this->sessionname));
    	if(!$cookie && $this->ev->get(CH.'currentuser') && $this->ev->get(CH.'psid'))
    	{
			$this->sessionid = $this->ev->get(CH.'psid');
			$cookie = $this->strings->decode($this->ev->get(CH.'currentuser'));
    	}
    	if(!$cookie)
    	{
    		$cookie = $_SESSION['currentuser'];
    		if($cookie)
    		$this->ev->setCookie($this->sessionname,$this->strings->encode($cookie),3600*24);
    	}
    	if($cookie['sessionuserid'])
    	{
    		$user = $this->getSessionValue();
    		if($cookie['sessionuserid'] == $user['sessionuserid'] && $cookie['sessionpassword'] == $user['sessionpassword'])
    		{
    			$this->sessionuser = $user;
    			return $user;
    		}
    	}
		return false;
    }

    //清除会话用户
    public function clearSessionUser()
    {
    	if(!$this->sessionid)$this->getSessionId();
    	$this->ev->setCookie($this->sessionname,NULL);
    	//$data = array('session',array(array('AND',"sessionid = :sessionid",'sessionid',$this->sessionid)));
		//$sql = $this->pdosql->makeDelete($data);
		//$this->db->exec($sql);
        $userid = $this->redisdo->hget('session:'.$this->sessionid,'sessionuserid');
        $this->redisdo->remove('session2user:'.$userid);
        $this->redisdo->remove('session:'.$this->sessionid);
	    return true;
    }

    public function offOnlineUser($userid)
    {
        $sessionid=$this->redisdo->get('session2user:'.$userid);
        $this->redisdo->hdel('session:'.$sessionid);
        $this->redisdo->remove('session2user:'.$userid);
	    return true;
    }

    //清除所有会话
    public function clearSession()
    {
        $this->redisdo->multidel('session');
        $this->redisdo->multidel('session2user');
    	return true;
    }

    //清除超时用户
    public function clearOutTimeUser($time)
    {
    	if($time)
    	$date = $time;
    	else
    	$date = TIME-24*3600;
        $sessionlist = $this->redisdo->getlist('session');
        foreach ($sessionlist as $value) {
            $sessionlogintime = $this->redisdo->hget($value,'sessionlogintime');
            if($sessionlogintime < $date){
                if($this->redisdo->hget($value,'sessionuserid')) $this->redisdo->remove('session2user:'.$value);
                $this->redisdo->hdel($value);
            }
        }
        //$data = array('session',array(array('AND',"sessionlogintime < :sessionlogintime",'sessionlogintime',$date)));
    	//$sql = $this->pdosql->makeDelete($data);
	    //$this->db->exec($sql);
    	return true;
    }

    //获取所有会话用户列表
    public function getSessionUserList($page,$number = 20)
    {
    	/*$data = array(
			'select' => false,
			'table' => 'session',
			'index' => false,
			'serial' => false,
			'query' => array(array('AND',"sessionuserid > 0")),
			'orderby' => 'sessionlogintime DESC',
			'groupby' => false
		);
		return $this->db->listElements($page,$number,$data);*/
        $list = $this->redisdo->getlist('session2user');
        foreach ($list as $value) {
            if ($value=='session2user:0') {
                continue;
            }
            $sessionid = $this->redisdo->get($value);
            $data[] = $this->redisdo->hmet($sessionid);
        }

        $fieldArr = array();
        foreach ($data as $k => $v) {
            $fieldArr[$k] = $v[$sessionlogintime];
        }
        array_multisort($fieldArr, SORT_DESC, $data);
        return $data;
    }

    public function __destruct()
    {
        $this->redisdo->hmset('session:'.$this->sessionid,array('sessionlasttime' => TIME));
    	/*$data = array('session',array('sessionlasttime' => TIME),array(array('AND',"sessionid = :sessionid",'sessionid',$this->sessionid)));
    	$sql = $this->pdosql->makeUpdate($data);
    	$this->db->exec($sql);
    	if(rand(0,5) > 4)
    	{
    		$data = array('session',array(array('AND',"sessionlasttime <= :sessionlasttime","sessionlasttime",intval((TIME - 3600*24*3)))));
	    	$sql = $this->pdosql->makeDelete($data);
	    	$this->db->exec($sql);
    	}*/
    }
}
?>
