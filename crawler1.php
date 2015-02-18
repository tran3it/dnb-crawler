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
        $this->referer = "http://electropeople.org/drumnbass/";

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
        $this->node = $this->xpath->query( ".//*[@class='news']/parent::*/parent::*/parent::*/parent::td" );
    }

    public function fetchLinksPageToDom()
    {
        if(is_null($this->url))
            return false;

        $this->snoopy->agent = $this->agent;
        $this->snoopy->referer = $this->referer;

        $this->snoopy->submit( $this->url );

        @$this->dom->loadHTML( $this->snoopy->results );
        $this->xpath = new DOMXPath( $this->dom );
        $this->node = $this->xpath->query( ".//a[contains(@href,'engine/go.php')]" ); // all links
    }

    public function createReleaseList()
    {
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

            $path['title'] = $item->childNodes->item(1)->childNodes->item(0)->childNodes->item(0)->childNodes->item(0)->nextSibling->childNodes->item(0)->childNodes->item(2);
            $path['date'] = $item->childNodes->item(2)->childNodes->item(0)->childNodes->item(0)->getElementsByTagName('a');
            $path['body'] = $item->childNodes->item(4)->childNodes->item(0)->childNodes;

            /* main info */
            for ($i=0; is_object($path['body']->item($i)); $i++)
            {
                $this->getAllNodeContents($path['body']->item($i), $nodeContentsArray);
            }

            $this->getNodeArrayValueByName('label', $nodeContentsArray, $bodyArray);
            $this->getNodeArrayValueByName('catalog', $nodeContentsArray, $bodyArray);
            $this->getNodeArrayValueByName('format', $nodeContentsArray, $bodyArray);
            $this->getNodeArrayValueByName('quality', $nodeContentsArray, $bodyArray);
            $this->getNodeArrayValueByName('size', $nodeContentsArray, $bodyArray);

            /* link */
            $release['href'] = $path['title']->childNodes->item(0)->getAttribute('href');
            $release['title'] = $path['title']->textContent;

            /* date */
            preg_match('#org/([0-9]{4})/([0-9]{2})/([0-9]{2})/#imU', $path['date']->item(0)->getAttribute('href'), $match);
            $release['date'] = sprintf('%s-%s-%s', $match[1], $match[2], $match[3]);

            /* descr */
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

    public function filterLinksList( $array )
    {
        $result = null;
        $nodeContentsArray = array();
        $tracklist = array();

        /* tracklist */
        $path['tlist'] = $this->node->item(0)->parentNode->childNodes;

        for ($i=0; is_object($path['tlist']->item($i)); $i++)
        {
            $this->getAllNodeContents($path['tlist']->item($i), $nodeContentsArray);
        }

        for($i=0; $i < count($nodeContentsArray); $i++)
        {
            if(preg_match('#^[0-9]{1,2}[\s\.\-]#is', $nodeContentsArray[ $i ]))
                $tracklist[] = $nodeContentsArray[ $i ];
        }

        $array['text'] = serialize( $tracklist );


        /* download link */
        foreach($this->node as $item)
        {
            $href = $item->getAttribute('href');

            if(preg_match('~go.php\?url=([A-Za-z0-9+/]*)~', $href, $matches) > 0)
            {
                $decoded = base64_decode($matches[1]);

                if(strstr($decoded, 'rusfolder') !== false)
                    $result = $decoded;
            }
        }

        $array['download'] = $result;
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
        foreach($this->releasesFiltered as &$release)
        {
            /* get link to rusfolder */
            $this->setPageUrl( $release['href'] );
            #$this->setPageUrl( 'http://data/dnbcrawler/1146901281-horizons-music-2014-selection.html' );

            $this->fetchLinksPageToDom();
            $this->filterLinksList( &$release );

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

        /* get pages */
        #$this->setPageUrl( 'http://data/dnbcrawler/page'.$i.'.htm' );
        $this->setPageUrl( 'http://electropeople.org/drumnbass/page/'.$i.'/' );

        /* get main page */
        $this->fetchMainPageToDom();
        $this->createReleaseList();

        sleep(1);

        #print_r($this->releasesAvailable);

        /* filter */
        $this->filterReleaseList();

        /* get rusfolder links */
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