<?php

class Database
{
    private static $instance;

    private $path;
    private $db;
    private $mysqli;

    private $settings;

    public $debug;

    private function __construct()
    {
        $this->debug = false;

        $this->settings = Settings::getInstance();

        // Открываем БД (или создаем)
        $this->connect();
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

    public function setFilePath ( $newPath )
    {
        $this->path = $newPath;
    }

    public function setDbContents ( array $db )
    {
        $this->db = $db;
    }

    public function getDbContents ( $year = null )
    {
        if(is_null($year))
        {
            return $this->db;
        }
        else {
            $result = array();
            
            foreach ($this->db as $row)
            {
                if(substr($row['date'], 0, 4) == $year)
                    $result[] = $row;
            }
            
            return $result;
        }
    }

    private function connect()
    {
        $this->mysqli = new mysqli($this->settings->dbInfo['host'], $this->settings->dbInfo['uname'], $this->settings->dbInfo['pass'], $this->settings->dbInfo['name'], $this->settings->dbInfo['port'], $this->settings->dbInfo['socket']);

        if ($this->mysqli->connect_error)
        {
            throw new Exception("MySQL connection error: ".$this->mysqli->connect_error, $this->mysqli->connect_errno);
        }

        $this->mysqli->set_charset("utf8");
    }

   /* public function query( $query )
    {
        $result = false;

        if(!empty($query))
        {
            $time1 = TimeDate::mymicrotime();

            $result = $this->mysqli->query( $query );

            $time2 = TimeDate::mymicrotime();

            if( $this->debug )
                echo '<p class="debug">'.$query.' ( '.(ceil($time2 - $time1)/1000).' )</p>';
        }

        return $result;
    }*/

    private function insert( $query, $params )
    {
        $toreturn = false;
        $paramsref = array();
        $debugstr = '';
        $this->insertid = false;

        if( $this->debug )
        {
            $parts = explode( '?', $query );

            for($i = 0; $i < count($parts)-1; $i++)
            {
                $debugstr.= $parts[ $i ]."'".$params[ $i+1 ]."'";
            }
            $debugstr.= $parts[ $i ];

            echo '<p class="debug">'.$debugstr.'</p>';
        }

        foreach($params as $key => $value)
        {
             $paramsref[ $key ] = &$params[ $key ];
        }

        $stmt = $this->mysqli->prepare( $query );

        $reflect = new ReflectionClass('mysqli_stmt');
        $reflectbind = $reflect->getMethod("bind_param");
        $reflectbind->invokeArgs($stmt, $paramsref);
        $stmt->execute();

        $toreturn = $stmt->affected_rows;

        $stmt->close();

        $this->insertid = $this->mysqli->insert_id;

        return $toreturn;
    }

    private function update( $query, $params )
    {
        $toreturn = false;
        $paramsref = array();
        $debugstr = '';

        if( $this->debug )
        {
            $parts = explode( '?', $query );

            for($i = 0; $i < count($parts)-1; $i++)
            {
                $debugstr.= $parts[ $i ]."'".$params[ $i+1 ]."'";
            }
            $debugstr.= $parts[ $i ];

            echo '<p class="debug">'.$debugstr.'</p>';
        }

        foreach($params as $key => $value)
        {
             $paramsref[ $key ] = &$params[ $key ];
        }

        $stmt = $this->mysqli->prepare( $query );

        $reflect = new ReflectionClass('mysqli_stmt');
        $reflectbind = $reflect->getMethod("bind_param");
        $reflectbind->invokeArgs($stmt, $paramsref);
        $stmt->execute();

        $toreturn = $stmt->affected_rows;

        $stmt->close();

        return $toreturn;
    }


    private function select( $query )
    {
        return $this->mysqli->query( $query );
    }

    private function selectRow( $query )
    {
        if(!empty($query))
        {
            $result = $this->mysqli->query( $query );

            if($result->num_rows === 1)
            {
                $row = $result->fetch_assoc();
                return $row;
            }
        }

        return false;
    }

    private function readDatabase ( )
    {
        $this->db = array();

        /* read from db */
        $result = $this->select('SELECT id, title, descr, text, href, date, added, download, deleted, clicked FROM releases WHERE deleted < 1 ORDER BY added DESC;');

        if($result->num_rows > 0)
        {
            while ($row = $result->fetch_assoc())
            {
                $this->db[] = $row;
            }
        }
    }

    private function readRelease ( $id )
    {
        $this->db = array();

        /* read from db */
        $result = $this->select('SELECT id, title, descr, text, href, date, added, download, deleted, clicked FROM releases WHERE deleted < 1 and id = '.$id.' ORDER BY added DESC;');

        if($result->num_rows > 0)
        {
            while ($row = $result->fetch_assoc())
            {
                $this->db[] = $row;
            }
        }
    }

    private function writeDatabase ( )
    {
        if( is_null($this->db) )
            throw new UnexpectedValueException("Nothing to write");

        $now = date('Y-m-d');

        /* insert into db */
        foreach ($this->db as $row)
        {
            $checkIfExists = $this->selectRow("SELECT 1 FROM releases WHERE href = '".$row['href']."'");

            if($checkIfExists === false)
            {
                $query = "INSERT INTO releases (title, descr, text, href, date, added, download) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $this->insert( $query, array('sssssss', $row['title'], $row['descr'], $row['text'], $row['href'], $row['date'], $now, $row['download']));
            }
        }
    }

    private function updateDatabase ( )
    {
        /* insert into db */
        foreach ($this->db as $row)
        {
            #$checkIfChanged = $this->selectRow("SELECT 1 FROM releases WHERE href = '".$row['href']."'");

            if(isset($row['changed']) && $row['changed'] > 0)
            {
                $query = "UPDATE releases SET title = ?, descr = ?, text = ?, href = ?, date = ?, download = ?, deleted = ?, clicked = ? WHERE id = ?";
                $this->update( $query, array('ssssssiii', $row['title'], $row['descr'], $row['text'], $row['href'], $row['date'], $row['download'], $row['deleted'], $row['clicked'], $row['id']));
            }
        }


    }

    public function dbSave()
    {
        $this->writeDatabase();
    }

    public function dbLoad()
    {
        $this->readDatabase();
    }

    public function dbLoadOneRelease( $id )
    {
        $this->readRelease($id);
    }

    public function dbUpdate()
    {
        $this->updateDatabase();
    }

}

?>
