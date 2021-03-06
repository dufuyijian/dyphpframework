<?php
/**
 * 路由类 
 * @author 大宇 Email:dyphp.com@gmail.com
 * @link http://www.dyphp.com/
 * @copyright Copyright 2011 dyphp.com 
 **/
class DyPhpRoute{
    private static $defaultController = DYPHP_DEFAULT_CONTROLLER;
    private static $regularGetParams = array();

    /**
     * web路由入口
     * $_GET保留key:ca,ext_name
     **/
    public static function runWeb(){  
        $matchArr = self::urlManager();
        if($matchArr){
            if(!isset($matchArr['controller'])){
                DyPhpBase::throwException('urlManager error');
            }
            $action = isset($matchArr['action']) ? $matchArr['action'] : '';
            self::runToController(array('c'=>$matchArr['controller'],'a'=>$action));
        }else{
            if(isset($_GET['ca']) && !empty(strip_tags($_GET['ca']))){
                $ca = strip_tags($_GET['ca']);
                $caArr = explode('.',$ca);
                if(count($caArr) > 2){
                    $action = substr(strrchr($ca,"."),1);
                    array_pop($caArr);
                    $controller = implode('_',$caArr);
                }else{
                    $controller = isset($caArr[0]) ? $caArr[0] : self::$defaultController;
                    $action = isset($caArr[1]) ? $caArr[1] : '';
                }
                self::runToController(array('c'=>$controller,'a'=>$action));
            }else{
                self::runToController();
            }
        }
    }

    /**
     * @brief    console路由入口
     * @return   
     **/
    public static function runConsole(){
        array_shift($_SERVER['argv']);
        if(empty($_SERVER['argv'])){
            die('invoke error: <controller> [<action>] [<param1> <param2> ...]');
        }

        $controller = isset($_SERVER['argv'][0]) ? $_SERVER['argv'][0] : self::$defaultController;
        $action = 'index';
        $params = array();
        if(isset($_SERVER['argv'][1])){
            $action = $_SERVER['argv'][1];
            unset($_SERVER['argv'][0],$_SERVER['argv'][1]);
            sort($_SERVER['argv']);
            foreach($_SERVER['argv'] as $key=>$val){
                $params[] = $val;
            }
        }
        DyPhpBase::app()->cid = $controller;
        DyPhpBase::app()->aid = $action;
        DyPhpController::run($controller,$action,$params);
    }

    /**
     * @brief    获取uri中正则匹配到的参数
     * @param    $paramKey
     * @return   
     **/
    public static function getParam($paramKey=''){
        if(!isset(self::$regularGetParams[$paramKey])){
            return null;
        }

        $value = self::$regularGetParams[$paramKey];
        if (!get_magic_quotes_gpc()){
            $value = function_exists('addslashes') ? addslashes($value) : mysql_real_escape_string($value);
        }
        return $value;
    }

    /**
     * url解析运行controller 
     **/
    private static function runToController($ca=array()){
        if(!empty($ca)){
            DyPhpController::run($ca['c'],$ca['a']);  
            return;
        }

        $controllerArgs = self::urlCrop();
        if($controllerArgs == "" || $controllerArgs == false){
            DyPhpController::run(self::$defaultController);
            return;
        }

        $controllerArgsArr = explode('/',$controllerArgs);
        $pathCutCount = count($controllerArgsArr);
        if($pathCutCount <= 2){
            if($pathCutCount == 1){
                DyPhpController::run($controllerArgsArr[0]);
                return;
            }
            $controller = $controllerArgsArr[0];
            $action = $controllerArgsArr[1];  
        }else{
            //非模块路由处理
            if(count($controllerArgsArr)%2 == 0){ 
                foreach($controllerArgsArr as $key=>$val){
                    if($key>1 && $key%2 == 0 && $val != ''){
                        $_GET[$val] = isset($controllerArgsArr[($key+1)]) ? $controllerArgsArr[($key+1)] : '';
                    }
                }
                $controller = $controllerArgsArr[0];
                $action = $controllerArgsArr[1];
            }else{
                //模块路由处理
                foreach($controllerArgsArr as $key=>$val){
                    if($key>2 && $key%2 == 1 && $val != ''){
                        $_GET[$val] = isset($controllerArgsArr[($key+1)]) ? $controllerArgsArr[($key+1)] : '';
                    }
                }
                $controller = $controllerArgsArr[0].'/'.$controllerArgsArr[1];
                $action = $controllerArgsArr[2];
            }
        }
        DyPhpController::run($controller,$action);            
    }

    /**
     * URL重写处理
     * 'urlManager'=>array(
     *   'urlStyle'=>array('hideIndex'=>'yes','restCa'=>'yes',), 
     *
     *   '/error'=>array("controller"=>"home","action"=>"error",),
     *
     *   '/admin/globalBase/:action'=>array(
     *       "controller"=>"admin_base",
     *       "param"=>array(
     *           ":action"=>"[a-zA-Z0-9]{1,10}",
     *       ),
     *   ),
     *
     *   '/user/:class/:controller/:action'=>array(
     *       "param"=>array(
     *           ":controller"=>"[a-zA-Z0-9]{1,20}",
     *           ":action"=>"[a-zA-Z0-9]{1,10}",
     *           ":class"=>"[a-zA-Z0-9]{1,10}",
     *       ),
     *   ),
     *
     *   '/ping/aaa/:user/ccc/ddd/:id'=>array(  
     *       "controller"=>"test",
     *       "action"=>"index",
     *       "param"=>array(
     *           ":user"=>"[a-zA-Z0-9]{4,10}",
     *           ":id"=>"\d{1,3}",
     *       ),
     *   ),                                       
     * )
     **/
    private static function urlManager(){
        $urlManager = DyPhpConfig::item('urlManager');

        //去除url风格
        if(isset($urlManager['urlStyle'])){
            unset($urlManager['urlStyle']);
        }

        if(!is_array($urlManager) || count($urlManager)==0){
            return array();
        }
        $pathUrl = self::urlCrop();

        //扩展名处理
        $ext = strrchr($pathUrl, ".");
        $ext = strrchr($ext, "/")  !== false ? false : $ext;
        if($ext !== false){
            $_GET['ext_name'] = $ext;
            $cropPathUrl = substr($pathUrl,0,-strlen($ext));
        }

        //完全匹处理配
        $pathUrlArr = array($pathUrl,'/'.$pathUrl,'/'.$pathUrl.'/');
        foreach($pathUrlArr as $key=>$val){
            if(isset($urlManager[$val])){
                return $urlManager[$val];
            } 
        }

        //正则处理
        $cropPathUrl = isset($cropPathUrl) ? $cropPathUrl : $pathUrl;
        $uriStrArr = explode('/',$cropPathUrl);
        foreach($urlManager as $urlKey=>$urlVal){
            if(!isset($urlVal['param']) || !is_array($urlVal['param'])){
                continue; //未设置param项 设置无效
            }
            $pmatch = str_replace('/','\/',strtr(trim($urlKey,'/'), $urlVal['param']));
            if(preg_match('#^'.$pmatch.'$#i', $cropPathUrl)){
                //pathController为true并设置controller需正则匹配 controller匹配值及其之前的所有项都将做为controller
                $isPathCtr = isset($urlVal['pathController']) && $urlVal['pathController'] == true ? true : false;
                $urlKeyArr = explode('/',trim($urlKey,'/'));
                foreach($urlVal['param'] as $key=>$val){
                    $pkey = array_search($key,$urlKeyArr);
                    if($pkey !== false && isset($uriStrArr[$pkey])){
                        //controller,action需正则匹配 会对配制的controller,action重写或设置
                        if($key == ':controller'){
                            $urlVal['controller'] = $isPathCtr ? implode('_',array_slice($uriStrArr,0,$pkey)).'_'.$uriStrArr[$pkey] : $uriStrArr[$pkey];
                        }elseif($key == ':action'){
                            $urlVal['action'] = $uriStrArr[$pkey];
                        }else{
                            self::$regularGetParams[substr($key,1)] = $uriStrArr[$pkey];
                        }
                    }
                }
                return $urlVal;
            }
        }
        return array();
    }

    /**
     * url解析 获取cotroller action及rest风格的get参数及扩展名 
     * @return string
     **/
    private static function urlCrop(){
        $requestUriStr =  str_replace('index'.EXT,'',trim($_SERVER["REQUEST_URI"],'/'));
        $uriPath = str_replace(array(DyPhpConfig::item('appHttpPath'),$_SERVER["QUERY_STRING"]),'',$requestUriStr);
        $parse = parse_url($uriPath);
        return $parse ? trim($parse['path'],'/') : trim(trim($uriPath,'/'),'?');
    }

}

