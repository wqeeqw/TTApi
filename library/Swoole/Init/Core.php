<?php

namespace Library\Swoole\Init;


use Library\Swoole\Conf\Config;
use Library\Swoole\Conf\Event;
use Library\Base\Swoole\AbstractInterface\ErrorHandlerInterface;
use Library\Component\Di;
use Library\Component\Error\Trigger;
//use Library\Component\Sys\ErrorHandler;
//use Library\Component\Spl\SplError;
use Library\Component\SysConst;
use Library\Http\Request;
//use Library\Http\Response;
use Library\Http\Response;
//use Library\Swoole\Server;
use Library\Utility\File;

use Phalcon\Mvc\Application as PhalconApplication;
use Phalcon\Config\Adapter\Ini as PhalconConfigIni;
use Phalcon\Config\Adapter\Php as ConfigPhp;


class Core
{
    protected static $instance;
    private $preCall;
    private $phalconApplication;
    function __construct($preCall)
    {
        $this->preCall = $preCall;
    }

    static function getInstance(callable $preCall = null){
        if(!isset(self::$instance)){
            self::$instance = new static($preCall);
        }
        return self::$instance;
    }

    function run(){
        Server::getInstance()->setPhalconApplication($this->phalconApplication);
        Server::getInstance()->startServer();
    }

    /*
     * initialize frameWork
     */
    function frameWorkInitialize(){
        if(phpversion() < 5.6){
            die("php version must >= 5.6");
        }
        $this->defineSysConst();
        $this->registerAutoLoader();
        $this->registerPhalconApplication();
        $this->registerPhalconDi();
        $this->preHandle();
        Event::getInstance()->frameInitialize();
        $this->sysDirectoryInit();
        Event::getInstance()->frameInitialized();
        $this->registerErrorHandler();
        return $this;
    }

    private function defineSysConst(){
        defined('ROOT') or define('ROOT',dirname(dirname(dirname(dirname(__FILE__)))));
        defined('USER') or define('USER',trim(shell_exec('whoami')));
        defined('USER_GROUP') or define('USER_GROUP',trim(shell_exec('groups '.USER)));
    }
    private function sysDirectoryInit(){
//        //创建临时目录
//        $tempDir = Di::getInstance()->get(SysConst::TEMP_DIRECTORY);
//        if(empty($tempDir)){
//            $tempDir = ROOT."/Temp";
//            Di::getInstance()->set(SysConst::TEMP_DIRECTORY,$tempDir);
//        }
//        if(!File::createDir($tempDir)){
//            die("create Temp Directory:{$tempDir} fail");
//        }else{
//            //创建默认Session存储目录
//            $path = $tempDir."/Session";
//            File::createDir($path);
//            Di::getInstance()->set(SysConst::SESSION_SAVE_PATH,$path);
//        }
        //创建日志目录
        $logDir = Di::getInstance()->get(SysConst::LOG_DIRECTORY);
        if(empty($logDir)){
            $logDir = ROOT . "/runtime/logs";
            Di::getInstance()->setShared(SysConst::LOG_DIRECTORY, function () use ($logDir){
                return $logDir;
            });
        }
        if(!File::createDir($logDir)){
            die("create log Directory:{$logDir} fail");
        }
        Config::getInstance()->setConf("SERVER.CONFIG.log_file",$logDir."/swoole.log");
        Config::getInstance()->setConf("SERVER.CONFIG.pid_file",$logDir."/pid.pid");
    }

    private static function registerAutoLoader(){
        require_once __DIR__ . "/AutoLoader.php";
        $loader = AutoLoader::getInstance();
        $loader->registerNamespaces(
            [
                'Core' => ROOT . "/src/Core/",
                'Conf' => ROOT . "/src/Conf/",
                'Base' => ROOT . "/src/Base/",
                'Library' => ROOT . "/library/",
            ]
        );
        $loader->register();
    }

    /**
     * 注册 phalcon application
     */
    function registerPhalconApplication(){
        try {
            /**
             * Read the configuration
             */
            $config = new PhalconConfigIni(APP_PATH . 'config/config.ini');
            $env = $config->get('appEnv');
            if (is_readable(APP_PATH . 'config/config.ini.dev')) {
                $override = new PhalconConfigIni(APP_PATH . 'config/config.ini.dev');
                $config->merge($override);
            }
            $config->merge(new ConfigPhp(APP_PATH . 'config/env/'.$env.'.php'));
            /**
             * Auto-loader configuration
             */
            require APP_PATH . 'config/loader.php';
            /**
             * Load application services
             */
            require APP_PATH . 'config/services.php';
            $this->phalconApplication = new PhalconApplication($di);
            $this->phalconApplication->setEventsManager($eventsManager);
        } catch (\Exception $e){
            echo $e->getMessage();
            echo $e->getTraceAsString();
        }
    }

    function registerPhalconDi(){
        if (!$this->phalconApplication) {
            return;
        }
        $di = $this->phalconApplication->getDI();
        Di::getInstance()->setPhalconAppDi($di);
    }

    private function registerErrorHandler(){
        $conf = Config::getInstance()->getConf("DEBUG");
        if(true === $conf['ENABLE']){
            ini_set("display_errors", "On");
            error_reporting(E_ALL | E_STRICT);
            set_error_handler(function($errorCode, $description, $file = null, $line = null, $context = null){
                Trigger::error($description,$file,$line,$errorCode,debug_backtrace());
            });
            register_shutdown_function(function (){
                $error = error_get_last();
                if(!empty($error)){
                    Trigger::error($error['message'],$error['file'],$error['line'],E_ERROR,debug_backtrace());
                    //HTTP下，发送致命错误时，原有进程无法按照预期结束链接,强制执行end
                    if(Request::getInstance()){
                        Response::getInstance()->end(true);
                    }
                }
            });
        }
    }
    private function preHandle(){
        if(is_callable($this->preCall)){
            call_user_func($this->preCall);
        }
        Di::getInstance()->set(SysConst::SESSION_NAME, function () {
            return 'TT';
        }, true);
        Di::getInstance()->set(SysConst::VERSION, function () {
            return '0.0.1';
        }, true);
        Di::getInstance()->set('config', function () {
            return Di::getInstance()->getPhalconAppDi()->getShare('config');
        }, true);
    }
}