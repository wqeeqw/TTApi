<?php

namespace Library\Http;


class UrlParser
{
    static public function pathInfo($path = null){
        if($path == null){
            $path = Request::getInstance()->getUri()->getPath();
        }
        $basePath = dirname($path);
        $info = pathInfo($path);
        if($info['filename'] != 'index'){
            if($basePath == '/'){
                $basePath = $basePath.$info['filename'];
            }else{
                $basePath = $basePath.'/'.$info['filename'];
            }
        }
        return $basePath;
    }

    static public function generateURL($controllerClass,$action = 'index',$query = array()){
        $controllerClass = substr($controllerClass,14);
        $controllerClass = explode('\\',$controllerClass);
        $path =  implode("/",$controllerClass);
        if($action == 'index'){
            $path =  $path."/index.html";
        }else{
            $path =  $path."/{$action}/index.html";
        }
        if(!empty($query)){
            return $path."?".http_build_cookie($query);
        }else{
            return $path;
        }
    }
}