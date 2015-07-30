<?php

class FileSessionHandler {
    protected $sSavePath;
    protected $sSessionId;

    public static function start($sSessionId) {
        $oHandler = new FileSessionHandler($sSessionId);
        session_set_save_handler(
            array($oHandler, 'open'),
            array($oHandler, 'close'),
            array($oHandler, 'read'),
            array($oHandler, 'write'),
            array($oHandler, 'destroy'),
            array($oHandler, 'gc')
        );

        // the following prevents unexpected effects when using objects as save handlers
        register_shutdown_function('session_write_close');

        session_start();
    }

    public function __construct($sSessionId) {
        $this->sSavePath = __DIR__.'/../stored_messages';
        $this->sSessionId = $sSessionId;
    }

    public function open($sSavePath, $sSessionName) {
        //$this->sSavePath = $sSavePath;
        if(!is_dir($this->sSavePath)) {
            mkdir($this->sSavePath, 0777, true);
        }

        return true;
    }

    public function close() {
        return true;
    }

    public function read($sId) {
        return (string)@file_get_contents($this->sSavePath.'/sess_'.$this->sSessionId);
    }

    public function write($id, $data) {
        return file_put_contents($this->sSavePath.'/sess_'.$this->sSessionId, $data) === false ? false : true;
    }

    public function destroy($id) {
        $file = $this->sSavePath.'/sess_'.$this->sSessionId;
        if(file_exists($file)) {
            unlink($file);
        }

        return true;
    }

    public function gc($maxlifetime) {
        foreach(glob($this->sSavePath.'/sess_*') as $file) {
            if(filemtime($file) + $maxlifetime < time() && file_exists($file)) {
                unlink($file);
            }
        }

        return true;
    }
}
