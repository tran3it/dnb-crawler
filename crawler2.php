<?php

error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE | E_STRICT | E_ALL);

include "settings.class.php";
include "snoopy.class.php";
include "mysqli.class.php";

class Crawler
{
    private $settings;
    
    private $url;
    public $agent;
    public $referer;

    private $snoopy;
    private $dom;
    private $xpath;
    private $node;
    private $database;

    private $releasesAvailable;
    private $releasesFiltered;

    private $whiteList;
    private $minDate;

    public function __construct()
    {
        $this->settings = Settings::getInstance();
        
        $this->agent = "Mozilla/5.0 (Windows; U; Windows NT 6.1; uk; rv:1.9.2.13) Gecko/20101203 Firefox/3.6.13 Some plugins";
        $this->referer = "http://freake.ru/music/style/drum-bass/";

        $this->releasesAvailable = array();
        $this->releasesFiltered = array();

        $this->snoopy = new Snoopy();
        $this->dom = new DOMDocument();
        $this->database = Database::getInstance();

        $this->whiteList = $this->settings->whiteList;
    }

    public function setPageUrl( $url )
    {
        $this->url = is_string($url) ? $url : null;
    }

    public function setMinDate( $date )
    {
        $this->minDate = is_string($date) ? $date : null;
    }

    public function fetchMainPageToDom()
    {
        if(is_null($this->url))
            return false;

        $this->snoopy->agent = $this->agent;
        $this->snoopy->referer = $this->referer;

        $this->snoopy->submit( $this->url );

        @$this->dom->loadHTML( $this->snoopy->results );
        $this->xpath = new DOMXPath( $this->dom );
        $this->node = $this->xpath->query( ".//div[@class='post']/div[@class='post-text']/parent::*" );
    }

    public function fetchInfoPageToDom()
    {
        if(is_null($this->url))
            return false;

        $this->snoopy->agent = $this->agent;
        $this->snoopy->referer = $this->referer;

        $this->snoopy->submit( $this->url );

        @$this->dom->loadHTML( $this->snoopy->results );
        $this->xpath = new DOMXPath( $this->dom );
        $this->node = $this->xpath->query( ".//div[@class='unreset']" ); // all links
    }

    public function fetchDownloadLink( $id )
    {
        if(is_null($this->url))
            return false;

        $this->node = null;

        $this->snoopy->agent = $this->agent;
        $this->snoopy->referer = $this->referer;
        $this->snoopy->httpmethod = "POST";

        $post = array('id' => $id);

        $this->snoopy->submit( $this->url, $post );
        preg_match('#<a.*>(.*rusfolder.*)<\/a>#imU', stripcslashes($this->snoopy->results), $match);

        @$this->dom->loadHTML( $match[0] );
        $this->xpath = new DOMXPath( $this->dom );
        $this->node = $this->xpath->query( ".//*" );
    }

    public function createReleaseList()
    {
        $path = array();

        foreach($this->node as $item)
        {
            $release = array(
                            'title' => null,
                            'descr' => null,
                            'text' => null,
                            'href' => null,
                            'date' => null,
                            'added' => null,
                            'download' => null,
                            'deleted' => null
                        );
            $bodyArray = array();
            $nodeContentsArray = array();

            $path['title'] = $item->childNodes->item(1)->childNodes->item(1);
            $path['body'] = $item->childNodes->item(3)->childNodes->item(1)->childNodes->item(3)->childNodes;

            /* main info */
            for ($i=0; is_object($path['body']->item($i)); $i++)
            {
                $this->getAllNodeContents($path['body']->item($i), $nodeContentsArray);
            }

            $this->getNodeArrayValueByName('artist', $nodeContentsArray, $bodyArray);
            $this->getNodeArrayValueByName('label', $nodeContentsArray, $bodyArray);
            $this->getNodeArrayValueByName('catalog', $nodeContentsArray, $bodyArray);
            $this->getNodeArrayValueByName('type', $nodeContentsArray, $bodyArray);
            $this->getNodeArrayValueByName('released', $nodeContentsArray, $dateArray);

            /* link */
            $lastItem = $item->childNodes->item(1)->childNodes->item(1)->childNodes->length - 1;
            $release['href'] = 'http://freake.ru'.$item->childNodes->item(1)->childNodes->item(1)->childNodes->item($lastItem)->getAttribute('href');
            $release['title'] = $path['title']->textContent;

            /* date */
            preg_match('#([0-9]{2})\.([0-9]{2})\.([0-9]{4})#imU', $dateArray['released'], $match);
            $release['date'] = sprintf('%s-%s-%s', $match[3], $match[2], $match[1]);

            /* description */
            $release['descr'] = serialize($bodyArray);

            $release['added'] = date('Y-m-d');

            $this->releasesAvailable[] = $release;
        }
    }

    public function filterReleaseList( )
    {
        foreach( $this->releasesAvailable as $releaseArray)
        {
            # remove all spaces
            $string = preg_replace('/\s+/', '', implode(' ', $releaseArray));

            foreach($this->whiteList as $criteria)
            {
                if(stripos($string, $criteria) !== false)
                {
                    $this->releasesFiltered[] = $releaseArray;
                    break;
                }
            }
        }
    }

    public function filterInfo( $k )
    {
        /* tracklist */
        $nodeContentsArray = array();
        $tracklist = array();

        /* tracklist */
        $path['tlist'] = $this->node;

        for ($i=0; is_object($path['tlist']->item($i)); $i++)
        {
            $this->getAllNodeContents($path['tlist']->item($i), $nodeContentsArray);
        }

        for($i=0; $i < count($nodeContentsArray); $i++)
        {
            if(preg_match('#^[0-9]{1,2}[\s\.\-]#is', $nodeContentsArray[ $i ]))
                $tracklist[] = $nodeContentsArray[ $i ];
        }

        $this->releasesFiltered[$k]['text'] = serialize( $tracklist );

    }

    public function filterDownloadLink( $i )
    {
        /* rusfolder */
        $this->releasesFiltered[$i]['download'] = $this->node->item(1)->getAttribute('href');
    }

    public function dateIsTooOld ( $date )
    {
        # y-m-d
        $dateArray = explode("-", $date);
        $minDateArray = explode("-", $this->minDate);

        if( gmmktime(0,0,0,$dateArray[1],$dateArray[2],$dateArray[0]) <= gmmktime(0,0,0,$minDateArray[1],$minDateArray[2],$minDateArray[0])  )
            return true;

        return false;
    }

    private function getAdditionalInfo ( )
    {
        for($i=0; $i<count($this->releasesFiltered); $i++)
        {
            preg_match('#([0-9]*)/?$#im', $this->releasesFiltered[$i]['href'], $match);
            $pageId = $match[1];

            /* get additional info */
            $this->setPageUrl( $this->releasesFiltered[$i]['href'] );
            #$this->setPageUrl( 'http://data/dnbcrawler/31421' );
            $this->fetchInfoPageToDom();
            $this->filterInfo( $i );
            sleep(1);

            /* get rusfolder links */
            $this->setPageUrl( 'http://freake.ru/engine/modules/ajax/music.link.php' );
            $this->fetchDownloadLink( $pageId );
            $this->filterDownloadLink( $i );
            sleep(1);
        }
    }

    private function getAllNodeContents($node, &$array)
    {
        if ($node->childNodes)
        {
            foreach ($node->childNodes as $child)
            {
                $this->getAllNodeContents($child, &$array);
            }
        }
        else
        {
            #echo $node->nodeName . " - " . $node->nodeValue . "<br>";
            $array[] = $node->nodeValue;
        }
    }

    private function getNodeArrayValueByName( $string, $array, &$result)
    {
        for($i=0; $i < count($array); $i++)
        {
            if(stripos($array[ $i ], $string) !== false)
            {
                $result[ $string ] = $array[ $i+1 ];
            }
        }

        return false;
    }

    public function doMain()
    {
        if(isset($_GET['i']) && $_GET['i'] > 0)
        {
            $i = $_GET['i'];
        }
        else {
            $i = 1;
        }

        /* get main page */
        #$this->setPageUrl( 'http://data/dnbcrawler/freake'.$i.'.htm' );
        $this->setPageUrl( 'http://freake.ru/music/style/drum-bass?p='.$i );
        $this->fetchMainPageToDom();
        $this->createReleaseList();

        #print_r($this->releasesAvailable);

        /* filter */
        $this->filterReleaseList();

        /* get download links */
        $this->getAdditionalInfo();

        /* save */
        $this->database->setDbContents( $this->releasesFiltered );
        $this->database->dbSave();

        #print_r($this->releasesFiltered);
    }
}

$crawler = new Crawler();
$crawler->doMain();

?>