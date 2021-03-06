<?php
date_default_timezone_set('Europe/Moscow');

class DynamicIp {

    private $args;

    public function __construct()
    {
        $this->args =   array(
                            'act' => null,
                            'name' => null,
                            'ip' => null
                        );

        $this->setAction();

        if($this->args['act'] == 'set')
        {
            $this->setName();
            $this->setIpAddr();
            $this->saveListToFile();
        }

        if($this->args['act'] == 'get')
        {
            $this->loadListFromFile();
        }
    }

    private function saveListToFile()
    {
        $fp = fopen('list.txt', 'a');
        fwrite($fp, sprintf("%s::%s::%s\r\n", $this->args['name'], $this->args['ip'], date('H:i:s d.m.Y')));
        fclose($fp);

        print 'success: '.$this->args['ip'];
    }

    private function loadListFromFile()
    {
        $farray = file('list.txt');
        $length = count($farray);

        for($i=$length; $i>=$length-5; $i--)
        {
            echo nl2br( $farray[ $i ] );
        }
    }

    private function setName()
    {
        if(isset($_GET['name']))
        {
            $this->args['name'] = filter_input(INPUT_GET, 'name', FILTER_SANITIZE_STRING);
        }
        else {
            $name = filter_input(INPUT_SERVER, 'HTTP_USER_AGENT', FILTER_SANITIZE_STRING);
            $this->args['name'] = substr($name, 0, strpos($name,' '));
        }
    }

    private function setIpAddr()
    {
        $ip = null;

        $client  = @$_SERVER['HTTP_CLIENT_IP'];
        $forward = @$_SERVER['HTTP_X_FORWARDED_FOR'];
        $remote  = $_SERVER['REMOTE_ADDR'];

        # default
        if(filter_var($client, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
        {
            $ip = $client;
        }
        elseif(filter_var($forward, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
        {
            $ip = $forward;
        }
        elseif(filter_var($remote, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
        {
            $ip = $remote;
        }

        #real
        if(isset($_GET['ip']))
        {
            if(filter_var($_GET['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
            {
                $ip = $_GET['ip'];
            }
        }

        $this->args['ip'] = $ip;
    }

    private function setAction()
    {
        $this->args['act'] = ($_GET['act'] == 'set') ? 'set' : 'get';
    }

}

new DynamicIp();
?>