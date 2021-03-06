<?php

if(!defined('IN_GAME')) {
	exit('Access Denied');
}

//----------------------------------------
//              底层机制函数
//----------------------------------------

function gameerrorhandler($code, $msg, $file, $line){
	global $errorinfo;
	if(!$errorinfo){return;}
	if($code == 2){$emessage = '<b style="color:#ff0">Warning</b> ';}
	elseif($code == 4){$emessage = '<b style="color:#f00">Parse</b> ';}
	elseif($code == 8){$emessage = '<b>Notice</b> ';}
	elseif($code == 256){$emessage = '<b>User Error</b> ';}
	elseif($code == 512){$emessage = '<b>User Warning</b> ';}
	elseif($code == 1024){$emessage = '<b>User Notice</b> ';}
	else{$emessage = '<b style="color:#f00>Fatal error</b> ';}
	$emessage .= "($code): $msg in $file on line $line";
	if(isset($GLOBALS['error'])){
		$GLOBALS['error'] .= '<br>'.$emessage;
	}else{
		$GLOBALS['error'] = $emessage;
	}
	return true;
}

function gexit($message = '',$file = '', $line = 0) {
	global $charset,$title,$extrahead,$allowcsscache,$errorinfo;
	include template('error');
	exit();
}

function output($content = '') {
	//if(!$content){$content = ob_get_contents();}
	//ob_end_clean();
	//$GLOBALS['gzipcompress'] ? ob_start('ob_gzhandler') : ob_start();
	//echo $content;
	ob_end_flush();
}

//----------------------------------------
//              输入输出函数
//----------------------------------------

function gstrfilter($str) {
	if(is_array($str)) {
		foreach($str as $key => $val) {
			$str[$key] = gstrfilter($val);
		}
	} else {		
		if($GLOBALS['magic_quotes_gpc']) {
			$str = stripslashes($str);
		}
		$str = str_replace("'","",$str);//屏蔽单引号'
		$str = str_replace("\\","",$str);//屏蔽反斜杠/
		$str = htmlspecialchars($str,ENT_COMPAT);//转义html特殊字符，即"<>&
	}
	return $str;
}

function language($file, $templateid = 0, $tpldir = '') {
	$tpldir = $tpldir ? $tpldir : TPLDIR;
	$templateid = $templateid ? $templateid : TEMPLATEID;

	$languagepack = GAME_ROOT.'./'.$tpldir.'/'.$file.'.lang.php';
	if(file_exists($languagepack)) {
		return $languagepack;
	} elseif($templateid != 1 && $tpldir != './templates/default') {
		return language($file, 1, './templates/default');
	} else {
		return FALSE;
	}
}

function template($file, $templateid = 0, $tpldir = '') {
	global $tplrefresh;

	$tpldir = $tpldir ? $tpldir : TPLDIR;
	$templateid = $templateid ? $templateid : TEMPLATEID;

	$tplfile = GAME_ROOT.'./'.$tpldir.'/'.$file.'.htm';
	$objfile = GAME_ROOT.'./gamedata/templates/'.$templateid.'_'.$file.'.tpl.php';
	if(TEMPLATEID != 1 && $templateid != 1 && !file_exists($tplfile)) {
		return template($file, 1, './templates/default/');
	}
	if($tplrefresh == 1) {
		if(!file_exists($objfile) || filemtime($tplfile) > filemtime($objfile)) {
			require_once GAME_ROOT.'./include/template.func.php';
			parse_template($file, $templateid, $tpldir);
		}
	}
	return $objfile;
}

function content($file = '') {
	ob_clean();
	include template($file);
	$content = ob_get_contents();
	ob_end_clean();
	$GLOBALS['gzipcompress'] ? ob_start('ob_gzhandler') : ob_start();
	return $content;
}

function gsetcookie($var, $value, $life = 0, $prefix = 1) {
	global $tablepre, $cookiedomain, $cookiepath, $now, $_SERVER;
	setcookie(($prefix ? $tablepre : '').$var, $value,
		$life ? $now + $life : 0, $cookiepath,
		$cookiedomain, $_SERVER['SERVER_PORT'] == 443 ? 1 : 0);
}

function clearcookies() {
	global $cookiepath, $cookiedomain, $game_uid, $game_user, $game_pw, $game_secques, $adminid, $groupid, $credits;
	dsetcookie('auth', '', -86400 * 365);

	$game_uid = $adminid = $credits = 0;
	$game_user = $game_pw = $game_secques = '';
}

function config($file = '', $cfg = 1) {
	$cfgfile = file_exists(GAME_ROOT."./gamedata/cache/{$file}_{$cfg}.php") ? GAME_ROOT."./gamedata/cache/{$file}_{$cfg}.php" : GAME_ROOT."./gamedata/cache/{$file}_1.php";
	return $cfgfile;
}

function dir_clear($dir) {
	$directory = dir($dir);
	while($entry = $directory->read()) {
		$filename = $dir.'/'.$entry;
		if(is_file($filename)) {
			unlink($filename);
		}
	}
	$directory->close();
}

//读取文件
function readover($filename,$method="rb"){
	strpos($filename,'..')!==false && exit('Forbidden');
	//$filedata=file_get_contents($filename);
	$handle=fopen($filename,$method);
	if(flock($handle,LOCK_SH)){
		$filedata='';
		while (!feof($handle)) {
   		$filedata .= fread($handle, 8192);
		}
		//$filedata.=fread($handle,filesize($filename));
		fclose($handle);
	} else {exit ('Read file error.');}
	return $filedata;
}

//写入文件
function writeover($filename,$data,$method="rb+",$iflock=1,$check=1,$chmod=1){
	$check && strpos($filename,'..')!==false && exit('Forbidden');
	touch($filename);
	$handle=fopen($filename,$method);
	if($iflock){
		if(flock($handle,LOCK_EX)){
			fwrite($handle,$data);
			if($method=="rb+") ftruncate($handle,strlen($data));
			fclose($handle); 
		} else {var_dump($filename);exit ('Write file error.');}
	} else {
		fwrite($handle,$data);
		if($method=="rb+") ftruncate($handle,strlen($data));
		fclose($handle); 
	}
	$chmod && chmod($filename,0777);
	return;
}

//打开文件，以数组形式返回
function openfile($filename){
	$filedata=readover($filename);
	$filedata=str_replace("\n","\n<:game:>",$filedata);
	$filedb=explode("<:game:>",$filedata);
	$count=count($filedb);
	if($filedb[$count-1]==''||$filedb[$count-1]=="\r"){unset($filedb[$count-1]);}
	if(empty($filedb)){$filedb[0]='';}
	return $filedb;
}

function compatible_json_encode($data){	//自动选择使用内置函数或者自定义函数，结合JSON.php可做到兼容低版本PHP
	if(version_compare(PHP_VERSION, '5.2.0', '<')) {
		require_once GAME_ROOT.'./include/JSON.php';
		$json = new Services_JSON();
		$jdata = $json->encode($data);
	} else{
		$jdata = json_encode($data);
	}
	return $jdata;	
}

//function addnews($t = '', $n = '', $a = '',$b = '', $c = '', $d = '') {
//	global $now,$db,$tablepre;
//	$t = $t ? $t : $now;
//	$newsfile = GAME_ROOT.'./gamedata/newsinfo.php';
//	$newsdata = readover($newsfile); //file_get_contents($newsfile);
//	if(is_array($a)) {
//		$news = "$t,$n,".implode('-',$a).",$b,$c,$d,\n";
//	} elseif(isset($n)) {
//		$news = "$t,$n,$a,$b,$c,$d,\n";
//	}
//	$newsdata = substr_replace($newsdata,$news,53,0);
//	writeover($newsfile,$newsdata,'wb');
//	
//	if(strpos($n,'death11') === 0  || strpos($n,'death32') === 0) {
//		$result = $db->query("SELECT lastword FROM {$tablepre}users WHERE username = '$a'");
//		$lastword = $db->result($result, 0);
//		//$result = $db->query("SELECT pls FROM {$tablepre}players WHERE name = '$a' AND type = '$b'");
//		//$pls = $db->result($result, 0);
//		$db->query("INSERT INTO {$tablepre}chat (type,`time`,send,recv,msg) VALUES ('3','$t','$a','$c','$lastword')");
//	}	elseif(strpos($n,'death15') === 0 || strpos($n,'death16') === 0) {
//		$result = $db->query("SELECT lastword FROM {$tablepre}users WHERE username = '$a'");
//		$lastword = $db->result($result, 0);
//		$result = $db->query("SELECT pls FROM {$tablepre}players WHERE name = '$a' AND type = '$b'");
//		$pls = $db->result($result, 0);
//		$db->query("INSERT INTO {$tablepre}chat (type,`time`,send,recv,msg) VALUES ('3','$t','$a','$pls','$lastword')");
//	}
//}

//----------------------------------------
//              重要游戏函数
//----------------------------------------

function addnews($t = 0, $n = '',$a='',$b='',$c = '', $d = '', $e = '') {
	global $now,$db,$tablepre;
	$t = $t ? $t : $now;
	$newsfile = GAME_ROOT.'./gamedata/newsinfo.php';
	touch($newsfile);
	if(is_array($a)){
		$a=implode('_',$a);
	}
//	$newsfile = GAME_ROOT.'./gamedata/newsinfo.php';
//	$newsdata = readover($newsfile); //file_get_contents($newsfile);
//	if(is_array($a)) {
//		$news = "$t,$n,".implode('-',$a).",$b,$c,$d,\n";
//	} elseif(isset($n)) {
//		$news = "$t,$n,$a,$b,$c,$d,\n";
//	}
//	$newsdata = substr_replace($newsdata,$news,53,0);
//	writeover($newsfile,$newsdata,'wb');
	if(strpos($n,'death11') === 0  || strpos($n,'death32') === 0) {
		$result = $db->query("SELECT lastword FROM {$tablepre}users WHERE username = '$a'");
		$e = $lastword = $db->result($result, 0);
		$db->query("INSERT INTO {$tablepre}chat (type,`time`,send,recv,msg) VALUES ('3','$t','$a','$c','$lastword')");
	}	elseif(strpos($n,'death15') === 0 || strpos($n,'death16') === 0) {
		$result = $db->query("SELECT lastword FROM {$tablepre}users WHERE username = '$a'");
		$e = $lastword = $db->result($result, 0);
		$result = $db->query("SELECT pls FROM {$tablepre}players WHERE name = '$a' AND type = '0'");
		$place = $db->result($result, 0);
		$db->query("INSERT INTO {$tablepre}chat (type,`time`,send,recv,msg) VALUES ('3','$t','$a','$place','$lastword')");
	}
	$db->query("INSERT INTO {$tablepre}newsinfo (`time`,`news`,`a`,`b`,`c`,`d`,`e`) VALUES ('$t','$n','$a','$b','$c','$d','$e')");
}

function logsave($pid,$time,$log = '',$type = 's'){
//	$logfile = GAME_ROOT."./gamedata/log/$pid.log";
//	$date = date("H:i:s",$time);
//	$logdata = "{$date}，{$log}<br>\n";
//	writeover($logfile,$logdata,'ab+');
	
	global $db,$tablepre;
	$ldata['toid']=$pid;
	$ldata['type']=$type;
	$ldata['prcsd']=0;
	$ldata['time']=$time;
	$ldata['log']=$log;
	//$db->query("INSERT INTO {$tablepre}log (toid,type,`time`,log) VALUES ('$pid','$type','$time','$log')");
	$db->array_insert("{$tablepre}log", $ldata);
	return;	
}

function load_gameinfo() {
	global $now,$db,$tablepre;
	global $gamenum,$gamestate,$lastupdate,$starttime,$winmode,$winner,$arealist,$areanum,$areatime,$areawarn,$validnum,$alivenum,$deathnum,$afktime,$optime,$weather,$hack,$combonum,$gamevars;
	$result = $db->query("SELECT * FROM {$tablepre}game");
	$gameinfo = $db->fetch_array($result);
	$gamenum = $gameinfo['gamenum'];
	$gamestate = $gameinfo['gamestate'];
	//$lastupdate = $gameinfo['lastupdate'];
	$starttime = $gameinfo['starttime'];
	$winmode = $gameinfo['winmode'];
	$winner = $gameinfo['winner'];
	$arealist = explode(',',$gameinfo['arealist']);
	$areanum = $gameinfo['areanum'];
	$areatime = $gameinfo['areatime'];
	$areawarn = $gameinfo['areawarn'];
	$validnum = $gameinfo['validnum'];
	$alivenum = $gameinfo['alivenum'];
	$deathnum = $gameinfo['deathnum'];
	$afktime = $gameinfo['afktime'];
	$optime = $gameinfo['optime'];
	$weather = $gameinfo['weather'];
	$hack = $gameinfo['hack'];
	$gamevars = $gameinfo['gamevars'];
	$gamevars = Array('sanmaact' => $gamevars & 1, 'sanmadead' => $gamevars & 2);
	$combonum = $gameinfo['combonum'];
	return;
}

function save_gameinfo() {
	global $now,$db,$tablepre;
	global $gamenum,$gamestate,$lastupdate,$starttime,$winmode,$winner,$arealist,$areanum,$areatime,$areawarn,$validnum,$alivenum,$deathnum,$afktime,$optime,$weather,$hack,$combonum,$gamevars;
	if(!isset($gamenum)||!isset($gamestate)){return;}
	if($alivenum < 0){$alivenum = 0;}
	if($deathnum < 0){$deathnum = 0;}
	if(empty($afktime)){$afktime = $now;}
	if(empty($optime)){$optime = $now;}
	$gameinfo = Array();
	$gameinfo['gamenum'] = $gamenum;
	$gameinfo['gamestate'] = $gamestate;
	//$gameinfo['lastupdate'] = $now;//注意此处
	$gameinfo['starttime'] = $starttime;	
	$gameinfo['winmode'] = $winmode;
	$gameinfo['winner'] = $winner;
	$gameinfo['arealist'] = implode(',',$arealist);
	$gameinfo['areanum'] = $areanum;
	$gameinfo['areatime'] = $areatime;
	$gameinfo['areawarn'] = $areawarn;
	$gameinfo['validnum'] = $validnum;
	$gameinfo['alivenum'] = $alivenum;
	$gameinfo['deathnum'] = $deathnum;
	$gameinfo['afktime'] = $afktime;
	$gameinfo['optime'] = $optime;
	$gameinfo['weather'] = $weather;
	$gamevars0 = ($gamevars['sanmaact'] ? 1 : 0) + ($gamevars['sanmadead'] ? 2 : 0);
	$gameinfo['gamevars'] = $gamevars0;
	$gameinfo['hack'] = $hack;
	$gameinfo['combonum'] = $combonum;
	$db->array_update("{$tablepre}game",$gameinfo,1);
	/*
	$gameinfo = "<?php\n\nif(!defined('IN_GAME')){exit('Access Denied');}\n\n\$gamenum = {$gamenum};\n\$gamestate = {$gamestate};\n\$starttime = {$starttime};\n\$winmode = {$winmode};\n\$winner = '{$winner}';\n\$arealist = array(".implode(',',$arealist).");\n\$areanum = {$areanum};\n\$areatime = {$areatime};\n\$weather = {$weather};\n\$hack = {$hack};\n\$validnum = {$validnum};\n\$alivenum = {$alivenum};\n\$deathnum = {$deathnum};\n\$afktime = {$afktime};\n\$optime = {$optime};\n\n?>";
	$dir = GAME_ROOT.'./gamedata/';
	if($fp = fopen("{$dir}gameinfo.php", 'w')) {
		if(flock($fp,LOCK_EX)) {
			fwrite($fp, $gameinfo);
		} else {
			exit("Couldn't save the game's info !");
		}
		fclose($fp);
	} else {
		gexit('Can not write to cache files, please check directory ./gamedata/ .', __file__, __line__);
	}*/
	return;
}



function save_combatinfo(){
	global $hdamage,$hplayer,$noisetime,$noisepls,$noiseid,$noiseid2,$noisemode;
	if(!$hdamage){$hdamage = 0;}
	if(!$noisetime){$noisetime = 0;}
	if(!$noisepls){$noisepls = 0;}
	if(!$noiseid){$noiseid = 0;}
	if(!$noiseid2){$noiseid2 = 0;}
	$combatinfo = "<?php\n\nif(!defined('IN_GAME')){exit('Access Denied');}\n\n\$hdamage = {$hdamage};\n\$hplayer = '{$hplayer}';\n\$noisetime = {$noisetime};\n\$noisepls = {$noisepls};\n\$noiseid = {$noiseid};\n\$noiseid2 = {$noiseid2};\n\$noisemode = '{$noisemode}';\n\n?>";
	//$combatinfo = "{$hdamage},{$hplayer},{$noisetime},{$noisepls},{$noiseid},{$noiseid2},{$noisemode},\n";
	$dir = GAME_ROOT.'./gamedata/';
	if($fp = fopen("{$dir}combatinfo.php", 'w')) {
		if(flock($fp,LOCK_EX)) {
			fwrite($fp, $combatinfo);
		} else {
			exit("Couldn't save combat info !");
		}
		fclose($fp);
	} else {
		gexit('Can not write to cache files, please check directory ./gamedata/ .', __file__, __line__);
	}
	return;
}

function getchat($last,$team='',$limit=0) {
	global $db,$tablepre,$chatlimit,$chatinfo,$plsinfo;
	$limit = $limit ? $limit : $chatlimit;
	$result = $db->query("SELECT * FROM {$tablepre}chat WHERE cid>'$last' AND (type!='1' OR (type='1' AND recv='$team')) ORDER BY cid desc LIMIT $limit");
	$chatdata = Array('lastcid' => $last, 'msg' => '');
	if(!$db->num_rows($result)){$chatdata = array('lastcid' => $last, 'msg' => '');return $chatdata;}
	
	while($chat = $db->fetch_array($result)) {
		//if(!$chatdata['lastcid']){$chatdata['lastcid'] = $chat['cid'];}
		if($chatdata['lastcid'] < $chat['cid']){$chatdata['lastcid'] = $chat['cid'];}
		$chat['msg'] = htmlspecialchars($chat['msg']);
		if($chat['type'] == '0') {
			$msg = "【{$chatinfo[$chat['type']]}】{$chat['send']}：{$chat['msg']}".date("\(H:i:s\)",$chat['time']).'<br>';
		} elseif($chat['type'] == '1') {
			$msg = "<span class=\"clan\">【{$chatinfo[$chat['type']]}】{$chat['send']}：{$chat['msg']}".date("\(H:i:s\)",$chat['time']).'</span><br>';
		} elseif($chat['type'] == '2') {
			$msg = "<span class=\"lime\">【{$chatinfo[$chat['type']]}】{$chat['send']}：{$chat['msg']}".date("\(H:i:s\)",$chat['time']).'</span><br>';
		} elseif($chat['type'] == '3') {
			if ($chat['msg']){
				$msg = "<span class=\"red\">【{$plsinfo[$chat['recv']]}】{$chat['send']}：{$chat['msg']} ".date("\(H:i:s\)",$chat['time']).'</span><br>';
			} else {
				$msg = "<span class=\"red\">【{$plsinfo[$chat['recv']]}】{$chat['send']} 什么都没说就死去了 ".date("\(H:i:s\)",$chat['time']).'</span><br>';
			}
		} elseif($chat['type'] == '4') {
			$msg = "<span class=\"yellow\">【{$chatinfo[$chat['type']]}】{$chat['msg']}".date("\(H:i:s\)",$chat['time']).'</span><br>';
		} elseif($chat['type'] == '5') {
			$msg = "<span class=\"yellow\">【{$chatinfo[$chat['type']]}】{$chat['msg']}".date("\(H:i:s\)",$chat['time']).'</span><br>';
		}
		$chatdata['msg'][$chat['cid']] = $msg;
	}
	return $chatdata;
}

function storyputchat($time,$type){
	global $db,$tablepre,$now,$syschatinfo,$gamestate,$rdown,$bdown,$ldown,$kdown;
	if(!$time){$time = $now;}
	if($type == 'areawarn'){
		if($gamestate == 20){
			$type = 'areawarn20';
		}else{
			$type = 'areawarn40';
		}
	}elseif($type == 'areaadd'){
		if($gamestate == 20){
			$type = 'areaadd20';
		}else{
			$type = 'areaadd40';
		}
	}
	$msgs = Array();
	$chat = $syschatinfo[$type];
	$list = Array('r' => 0, 'b' => 0, 'l' => 0, 'k'=> 0);
	if($rdown){$list['r'] = 1;}
	if($bdown){$list['b'] = 1;}	
	if($ldown){$list['l'] = 1;}
	if($kdown){$list['k'] = 1;}
	foreach($chat as $val){
		$judge = $val[0];
		$flag = true;
		for($i=0;$i < strlen($judge);$i+=2){
			$judge0 = substr($judge,$i,1);
			$judge1 = substr($judge,$i+1,1);
			if($list[$judge0] != $judge1){
				$flag = false;
				break;
			}
		}
		if($flag){$msgs[] = $val;}
	}
	if(!empty($msgs)){
		shuffle($msgs);
		$msgs = $msgs[0];
		$send = $msgs[1];
		$msg = $msgs[2];
		$db->query("INSERT INTO {$tablepre}chat (type,`time`,send,msg) VALUES ('2','$time','$send','$msg')");
	}		
	return;
}

function systemputchat($time,$type,$msg = ''){
	global $db,$tablepre,$now;
	if(!$time){$time = $now;}
	if($type == 'areaadd' || $type == 'areawarn'){
		$alist = $msg;
		$msg = '';
		global $plsinfo;
		foreach($alist as $ar) {
			$msg .= "$plsinfo[$ar] ";
		}
		if($type == 'areaadd'){
			$msg = '增加禁区：'.$msg;
		}elseif($type == 'areawarn'){
			$msg = '警告，以下区域即将成为禁区：'.$msg;
		}
	}elseif($type == 'combo'){
		$msg = '游戏进入连斗阶段！';
	}elseif($type == 'comboupdate'){
		$msg = '连斗死亡判断数修正为'.$msg.'人！';
	}elseif($type == 'duel'){
		$msg = '游戏进入死斗模式！';
	}elseif($type == 'newgame'){
		$msg = '游戏开始！';
	}elseif($type == 'gameover'){
		$msg = '游戏结束！';
	}
	$db->query("INSERT INTO {$tablepre}chat (type,`time`,send,msg) VALUES ('5','$time','','$msg')");
	return;
}

function getmicrotime(){
	list($usec, $sec) = explode(" ",microtime());
	return ((float)$usec + (float)$sec);
}

function putmicrotime($t_s,$t_e,$file,$info)
{
	$mtime = ($t_e - $t_s)*1000;
	writeover( $file.'.txt',"$info ；执行时间：$mtime 毫秒 \n",'ab');
}

?>
