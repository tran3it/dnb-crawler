<?php

error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE | E_STRICT | E_ALL);
include "settings.class.php";
include "mysqli.class.php";

class Viewer
{
    private $database;

    private $releasesSaved;

    private $html;
    private $body;

    public function __construct()
    {
        $this->database = Database::getInstance();
    }

    private function loadReleases()
    {
        $this->database->dbLoad();
        $this->releasesSaved = $this->database->getDbContents();
    }

    private function prepareBody()
    {
        $header = '<tr><th width=35%>Title</th><th width=20%>Descr</th><th width=34%>Text</th><th width=5%>Date</th><th width=5%>Added</th><th width=1%>S</th></tr>';
        $row = null;

        foreach($this->releasesSaved as $release)
        {
            $descr = '';
            $text = '';
            preg_match('#(freake|electropeople)#im', $release['href'],$m);

            foreach (unserialize($release['descr']) as $key => $val)
            {
                $descr.= sprintf('<p>%s: %s</p>', ucfirst($key), $val);
            }

            foreach (unserialize($release['text']) as $val)
            {
                $text.= sprintf('<p>%s</p>', $val);
            }

            $tr = '<tr>';
            $tr.= '<td><a href="'.$release['download'].'" target="_blank">'.$release['title'].'</a></td>';
            $tr.= '<td>'.$descr.'</td>';
            $tr.= '<td>'.$text.'</td>';
            $tr.= '<td>'.$release['date'].'</td>';
            $tr.= '<td>'.$release['added'].'</td>';
            $tr.= '<td>'.substr($m[0],0,1).'</td>';
            #$tr.= '<td> </td>';
            $tr.= '</tr>';

            $row.= $tr;
        }

        $this->body = '<table id="rls" width=99%>'.$header.$row.'</table>';
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

    public function doMain()
    {
        $this->loadReleases();
        $this->prepareBody();
        $this->printHtml();
    }
}

$crawler = new Viewer();
$crawler->doMain();

?>