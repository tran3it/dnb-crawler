<?php

error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE | E_STRICT | E_ALL);
include "settings.class.php";
include "mysqli.class.php";

class Redirector
{
    private $database;

    private $html;
    private $body;

    private $rlsId;

    public function __construct()
    {
        $this->database = Database::getInstance();

        $this->doMain();
    }

    private function getGetParams()
    {
        $id = $_GET['id'];
        settype($id, 'integer');

        $this->rlsId = ($id > 0) ? $id : null;
    }

    private function loadRelease()
    {
        $this->database->dbLoadOneRelease( $this->rlsId );
        $this->release = $this->database->getDbContents();
    }

    private function printHtml()
    {
        $html = '<!DOCTYPE html PUBLIC "-//IETF//DTD HTML 2.0//EN">';
        $html.= '<HTML>';
        $html.= '<HEAD>';
        $html.= '<TITLE>Crawler</TITLE>';
        $html.= '<link rel="stylesheet" type="text/css" href="./style.css" media="screen">';
        $html.= '</HEAD>';
        $html.= '<BODY>';
        $html.= $this->body;
        $html.= '</BODY>';
        $html.= '</HTML>';

        $this->html = $html;
        echo $html;
    }

    private function doRedirect( )
    {
        preg_match('/(rusfolder\..*)/i', $this->release[0]['download'], $match);
        $relurl = (count($match) > 0) ? $match[1] : null;

        $url = 'http://ints.rusfolder.com/ints/?'.$relurl.'?ints_code=';

        $this->body = '<meta http-equiv="refresh" content="0; url='.$url.'"';
    }

    private function updateCounter()
    {
        #mark as changed
        $this->release[0]['changed'] = 1;
        $this->release[0]['clicked']++;

        $this->database->setDbContents( $this->release );
        $this->database->dbUpdate();
    }

    private function doMain()
    {
        if(!isset($_GET) || count($_GET) < 0)
            return false;

        $this->getGetParams();
        $this->loadRelease();

        $this->updateCounter();

        $this->doRedirect();
        $this->printHtml();
    }
}

$crawler = new Redirector();

?>