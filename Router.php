<?php

/**
 * Mikrotik API - RouterBoard
 * @author Dobr@CZek
 * @link http://webscript.cz
 * @version 1.0
 */

namespace MikrotikApi;

class Router
{
    var $debuger    = false;
    var $connected  = false;
    var $ssl        = false;
    var $port       = 8728;
    var $attempts   = 5;
    var $delay      = 3;
    var $timeout    = 3;
    var $socket;
    var $error_no;
    var $error_str;
    
    
    public function debuger($string)
    {
        if ($this->debuger)
            echo $string . "<br />";
    }
    
    public function encodeLg($lg)
    {
        if ($lg < 0x80)
            $lg = chr($lg);
        
        else if ($lg < 0x4000)
        {
            $lg |= 0x8000;
            $lg = chr(($lg >> 8) & 0xFF) . chr($lg & 0xFF);
        }
        else if ($lg < 0x200000)
        {
            $lg |= 0xC00000;
            $lg = chr(($lg >> 16) & 0xFF) . chr(($lg >> 8) & 0xFF) . chr($lg & 0xFF);
        }
        else if ($lg < 0x10000000)
        {
            $lg |= 0xE0000000;
            $lg = chr(($lg >> 24) & 0xFF) . chr(($lg >> 16) & 0xFF) . chr(($lg >> 8) & 0xFF) . chr($lg & 0xFF);
        }
        else if ($lg >= 0x10000000)
            $lg = chr(0xF0) . chr(($lg >> 24) & 0xFF) . chr(($lg >> 16) & 0xFF) . chr(($lg >> 8) & 0xFF) . chr($lg & 0xFF);
        
        return $lg;
    }
    
    
    public function connect($ip, $login, $password)
    {
        for ($ATTEMPT = 1; $ATTEMPT <= $this->attempts; $ATTEMPT++)
        {
            $this->connected = false;
            
            $protocol = ($this->ssl ? 'ssl://' : '' );
            
            $context = stream_context_create(array('ssl' => array('ciphers' => 'ADH:ALL', 'verify_peer' => false, 'verify_peer_name' => false)));
            $this->debuger('Pokus o pripojeni k ' . $protocol . $ip . ':' . $this->port . '...');
            $this->socket = @stream_socket_client($protocol . $ip.':'. $this->port, $this->error_no, $this->error_str, $this->timeout, STREAM_CLIENT_CONNECT,$context);
            
            if ($this->socket)
            {
                socket_set_timeout($this->socket, $this->timeout);
                
                $this->write('/login', false);
                $this->write('=name=' . $login, false);
                $this->write('=password=' . $password);
                
                $RESPONSE = $this->read(false);
                
                if (isset($RESPONSE[0]))
                {
                    if ($RESPONSE[0] == '!done')
                    {
                        if (!isset($RESPONSE[1]))
                        {
                            $this->connected = true;
                            break;
                        }
                        else
                        {
                            $MATCHES = array();
                            if (preg_match_all('/[^=]+/i', $RESPONSE[1], $MATCHES))
                            {
                                if ($MATCHES[0][0] == 'ret' && strlen($MATCHES[0][1]) == 32)
                                {
                                    $this->write('/login', false);
                                    $this->write('=name=' . $login, false);
                                    $this->write('=response=00' . md5(chr(0) . $password . pack('H*', $MATCHES[0][1])));
                                   
                                    $RESPONSE = $this->read(false);
                                    
                                    if (isset($RESPONSE[0]) && $RESPONSE[0] == '!done')
                                    {
                                        $this->connected = true;
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }
                
                fclose($this->socket);
            }
            
            sleep($this->delay);
        }
        
        if ($this->connected)
            $this->debuger('Pripojeno...');
        else
            $this->debuger('Chyba pripojeni...');
        
        return $this->connected;
    }
    
    
    public function disconnect()
    {
        if(is_resource($this->socket))
            fclose($this->socket);
        
        $this->connected = false;
        $this->debuger('Odpojeno...');
    }
    
    
    public function parseResponse($response)
    {
        if (is_array($response))
        {
            $PARSED = array();
            $CURRENT = null;
            $singlevalue = null;
            
            foreach ($response as $x)
            {
                if(in_array($x, array('!fatal','!re','!trap')))
                {
                    if ($x == '!re')
                        $CURRENT =& $PARSED[];
                    else
                        $CURRENT =& $PARSED[$x][];
                    
                }
                else if($x != '!done')
                {
                    $MATCHES = array();
                    if (preg_match_all('/[^=]+/i', $x, $MATCHES)) {
                        if ($MATCHES[0][0] == 'ret') {
                            $singlevalue = $MATCHES[0][1];
                        }
                        $CURRENT[$MATCHES[0][0]] = (isset($MATCHES[0][1]) ? $MATCHES[0][1] : '');
                    }
                }
            }
            
            if (empty($PARSED) && !is_null($singlevalue))
                $PARSED = $singlevalue;
            
            return $PARSED;
            
        } else
            return array();
    }
    
    public function arrayChangeKeyName(&$array)
    {
        if (is_array($array))
        {
            foreach ($array as $k => $v)
            {
                $tmp = str_replace("-", "_", $k);
                $tmp = str_replace("/", "_", $tmp);
                
                if ($tmp)
                    $array_new[$tmp] = $v;
                else
                    $array_new[$k] = $v;
                
            }
            
            return $array_new;
        
        }    
        else
            return $array;
        
    }
    
    public function isIterable($var)
    {
        return $var !== null
        && (is_array($var)
            || $var instanceof Traversable
            || $var instanceof Iterator
            || $var instanceof IteratorAggregate
            );
    }
    
    public function comm($com, $aray = array())
    {
        $count = count($aray);
        $this->write($com, !$aray);
        $i = 0;
        
        if ($this->isIterable($aray))
        {
            foreach ($aray as $k => $v)
            {
                switch ($k[0])
                {
                    case "~":
                        $el = "$k~$v";
                        break;
                    case "?":
                        $el = "$k=$v";
                        break;
                    default:
                        $el = "=$k=$v";
                        break;
                }
                
                $last = ($i++ == $count - 1);
                $this->write($el, $last);
            }
        }
        
        return $this->read();
    }
    
    
    public function read($parse = true)
    {
        $responce     = array();
        $receiveddone = false;
        
        while (true)
        {
            $BYTE   = ord(fread($this->socket, 1));
            $LENGTH = 0;
         
            if ($BYTE & 128)
            {
                if (($BYTE & 192) == 128)
                    $LENGTH = (($BYTE & 63) << 8) + ord(fread($this->socket, 1));
                else
                {
                    if (($BYTE & 224) == 192)
                    {
                        $LENGTH = (($BYTE & 31) << 8) + ord(fread($this->socket, 1));
                        $LENGTH = ($LENGTH << 8) + ord(fread($this->socket, 1));
                    }
                    else
                    {
                        if (($BYTE & 240) == 224)
                        {
                            $LENGTH = (($BYTE & 15) << 8) + ord(fread($this->socket, 1));
                            $LENGTH = ($LENGTH << 8) + ord(fread($this->socket, 1));
                            $LENGTH = ($LENGTH << 8) + ord(fread($this->socket, 1));
                        }
                        else
                        {
                            $LENGTH = ord(fread($this->socket, 1));
                            $LENGTH = ($LENGTH << 8) + ord(fread($this->socket, 1));
                            $LENGTH = ($LENGTH << 8) + ord(fread($this->socket, 1));
                            $LENGTH = ($LENGTH << 8) + ord(fread($this->socket, 1));
                        }
                    }
                }
                
            } else
                $LENGTH = $BYTE;
            
            $_ = "";
            
            
            if ($LENGTH > 0)
            {
                $_      = "";
                $retlen = 0;
                
                while ($retlen < $LENGTH)
                {
                    $toread = $LENGTH - $retlen;
                    $_ .= fread($this->socket, $toread);
                    $retlen = strlen($_);
                }
                
                $responce[] = $_;
                $this->debuger('> (' . $retlen . '/' . $LENGTH . ') b.');
            }
            
            if ($_ == "!done")
                $receiveddone = true;
            
            $STATUS = socket_get_status($this->socket);
            
            if ($LENGTH > 0)
                $this->debuger('> (' . $LENGTH . ', ' . $STATUS['unread_bytes'] . ')' . $_);
            
            if ((!$this->connected && !$STATUS['unread_bytes']) || ($this->connected && !$STATUS['unread_bytes'] && $receiveddone))
                break;
            
        }
        
        if ($parse)
            $responce = $this->parseResponse($responce);
        
        return $responce;
    }
    
    
    public function write($command, $param2 = true)
    {
        if ($command)
        {
            $data = explode("\n", $command);
            
            foreach ($data as $com)
            {
                $com = trim($com);
                fwrite($this->socket, $this->encodeLg(strlen($com)) . $com);
                $this->debuger('< (' . strlen($com) . ') ' . $com);
            }
            
            if (gettype($param2) == 'integer')
            {
                fwrite($this->socket, $this->encodeLg(strlen('.tag=' . $param2)) . '.tag=' . $param2 . chr(0));
                $this->debuger('< (' . strlen('.tag=' . $param2) . ') .tag=' . $param2);
            }
            elseif (gettype($param2) == 'boolean')
            {
                fwrite($this->socket, ($param2 ? chr(0) : ''));
            }
            
            return true;
            
        } else
            return false;
        
    }
    
    
    public function __destruct()
    {
        $this->disconnect();
    }
    
}
?>
