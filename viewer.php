<?php

error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE | E_STRICT | E_ALL);
include "settings.class.php";
include "mysqli.class.php";

class Viewer
{
    private $database;
    private $settings;

    private $releasesSaved;

    private $html;
    private $body;

    private $year;

    public function __construct()
    {
        #$this->year = date('Y');
        $this->year = null;

        $this->database = Database::getInstance();
        $this->settings = Settings::getInstance();
    }

    private function loadReleases()
    {
        $this->database->dbLoad();
        $this->releasesSaved = $this->database->getDbContents( $this->year );
    }

    private function prepareBody()
    {
        $header = '<tr><th class="col1">Title</th><th class="col2">Descr</th><th class="col3">Text</th><th class="col4">Date</th><th class="col5">S</th><th class="col6">D</th></tr>';
        $row = null;

        foreach($this->releasesSaved as $release)
        {
            $descr = '';
            $text = '';
            $dload = '';

            preg_match('#(freake|electropeople)#im', $release['href'],$m);

            /* description */
            foreach (unserialize($release['descr']) as $key => $val)
            {
                $descr.= sprintf('<p>%s: %s</p>', ucfirst($key), $val);
            }
            /* track names */
            foreach (unserialize($release['text']) as $val)
            {
                $text.= sprintf('<p>%s</p>', $val);
            }
            /* download links */
            foreach (unserialize($release['download']) as $key => $val)
            {
                $dload.= sprintf('<p><a href="%s">%s</a></p>', $val, 'dnl');
            }

            $tr = '<tr>';
            #$tr.= '<td class="col1"><a href="http://ints.rusfolder.com/ints/?'.$relurl.'?ints_code=" target="_blank">'.$release['title'].'</a></td>';
            $tr.= '<td class="col1'.(($release['clicked']>0)?' visited':'').'"><a href="./golink/'.$release['id'].'" target="_blank">'.$release['title'].'</a></td>';
            $tr.= '<td class="col2">'.$descr.'</td>';
            $tr.= '<td class="col3">'.$text.'</td>';
            $tr.= '<td class="col4"><p>rls: '.$release['date'].'</p><p>add: '.$release['added'].'</p><p>dnl: '.$release['clicked'].'</p></td>';
            $tr.= '<td class="col5"><p><a href="'.$release['href'].'">'.substr($m[0],0,2).'</a></p>'.$dload.'</td>';
            $tr.= '<td class="col6"><input type="checkbox" name="todelete[]" value="'.$release['id'].'"></td>';
            $tr.= '</tr>';

            $row.= $tr;
        }

        $this->body = '<form action="" method="post">';
        $this->body.= '<input id="delbtn" type="submit" value="Delete">';
        $this->body.= '<table id="rls" width=99%>'.$header.$row.'</table>';
        $this->body.= '</form>';
    }

    private function printHtml()
    {
        $html = '<!DOCTYPE html PUBLIC "-//IETF//DTD HTML 2.0//EN">';
        $html.= '<HTML>';
        $html.= '<HEAD>';
        $html.= '<TITLE>Crawler</TITLE>';
        $html.= '<base href="'.$this->settings->baseUrl.'" />';
        $html.= '<link rel="stylesheet" type="text/css" href="./style.css" media="screen">';
        $html.= '</HEAD>';
        $html.= '<BODY>';
        $html.= $this->body;
        $html.= '</BODY>';
        $html.= '</HTML>';

        $this->html = $html;
        echo $html;
    }

    private function doRedirect()
    {
        $this->body = '<meta http-equiv="refresh" content="1; url='.$this->settings->baseUrl.'view/"';
    }

    private function deleteRelease( $delArray )
    {
        foreach ($delArray as $toDeleteId)
        {
            settype($toDeleteId, 'integer');

            foreach($this->releasesSaved as &$release)
            {
                if($release['id'] == $toDeleteId)
                {
                    $release['deleted'] = 1;
                    $release['changed'] = 1;
                }
            }
        }

        $this->database->setDbContents( $this->releasesSaved );
        $this->database->dbUpdate();
    }

    public function doMain()
    {
        # GET variables
        if(isset($_GET['year']))
        {
            $this->year = $_GET['year'];
            settype($this->year, 'integer');
        }

        $this->loadReleases();

        if(isset($_POST) && count($_POST) > 0)
        {
            $this->deleteRelease( $_POST['todelete'] );
            $this->doRedirect();
        }
        else
        {
            $this->prepareBody();
        }

        $this->printHtml();
    }
}

$crawler = new Viewer();
$crawler->doMain();

?>