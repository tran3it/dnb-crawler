<?php

class Settings
{
    private static $instance;

    public $baseUrl;

    public $whiteList;
    public $dbInfo;

    private function __construct()
    {
        $this->initWhiteList();
        $this->initDbInfo();

        $this->baseUrl = 'http://httpd-tran3it.7e14.starter-us-west-2.openshiftapps.com/';
    }

    private function  __wakeup()
    {
        self::$instance = self::getInstance();
    }

    public final function __clone()
    {
        throw new BadMethodCallException("Clone is not allowed");
    }

    public function  __destruct()
    {
        self::$instance = null;
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    private function initWhiteList()
    {
        $this->whiteList = array(
                                # labels
                                'hospital',
                                'ram',
                                'shogun',
                                'technique',
                                'metalheadz',
                                'spearhead',
                                'owsla',
                                'playaz',
                                '3beat',
                                'fokuz',
                                'audioporn',
                                'renegade',
                                'viper',
                                'horizons',
                                'deepsoul',
                                'liquid',
                                'kosmos',
                                'medschool',
                                'critical',
                                # artists
                                'black sun',
                                'skrillex',
                                'vortex',
                                'andyc',
                                'spectrasoul',
                                'hamilton',
                                'spor',
                                'calibre',
                                'mefjus',
                                'fourward',
                                'tantrum',
                                'prolix',
                                'edrush',
                                's.p.y',
                                'ulterior',
                                'hybridminds',
                                'nelver',
                                'break',
                                'sigma',
                                'technimatic',
                                'icicle',
                                'wilkinson',
                                'friction',
                                'subfocus',
                                'fredv',
                                'tc',
                                'loadstar',
                                'prototypes',
                                'lenzman',
                                'deltaheavy',
                                'wickaman',
                                'lplus',
                                'joeford',
                                'insideinfo',
                                'nu:tone',
                                'calyx',
                                'lynx',
                                'metrik',
                                'krooked',
                                'logistics',
                                'highcontrast',
                                'reso',
                                'dcbreaks',
                                'noisia',
                                'phace',
                                'renelavice',
                                'johnb',
                                'futurebound',
                                'trei',
                                'seba',
                                'subwave',
                                'lomax',
                                'xample',
                                'chase',
                                'dannybyrd',
                                'netsky',
                                'apex',
                                'nero',
                                'brookesbrothers',
                                'eveson',
                                'cultureshock'
                            );
    }

    private function initDbInfo()
    {
        $this->dbInfo = array(
                                'uname' => $_ENV['MYSQL_USER'],
                                'pass' => $_ENV['MYSQL_PASSWORD'],
                                'name' => $_ENV['MYSQL_DATABASE'],
                                'host' => $_ENV['MYSQL_SERVICE_HOST'],
                                'port' => $_ENV['MYSQL_SERVICE_PORT']
                                //'socket' => $_ENV['OPENSHIFT_MYSQL_DB_SOCKET']
                            );

        /*$this->dbInfo = array(
                                'uname' => 'root',
                                'pass' => '',
                                'name' => 'dnb',
                                'host' => 'localhost',
                                'port' => ini_get("mysqli.default_port"),
                                'socket' => ini_get("mysqli.default_socket")
                            );*/
    }
}

?>