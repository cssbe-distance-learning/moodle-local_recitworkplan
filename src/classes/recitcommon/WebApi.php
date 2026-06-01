<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package   local_recitworkplan
 * @copyright 2019 RÉCIT 
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace recitworkplan;

use stdClass;
use DateTime;
use Exception;
use DateTimeZone;

require_once(dirname(__FILE__)."/PersistCtrl.php");

class WebApiResult
{
    public $success = false;
    public $data = null;
    public $msg = "";
    public $contentType = 'json';
    
    public function __construct($success, $data = null, $msg = "", $contentType = 'json'){
        $this->success = $success;
        $this->data = $data;
        $this->msg = $msg;
        $this->contentType = $contentType;
    }
}

abstract class AWebApi
{
    protected $request = null;
    protected $lastResult = null;
    public static $lastError = null;
    public static $httpOrigin = "";
    
    public static function onPhpError(){
        if(AWebApi::$lastError == null){
            AWebApi::$lastError = error_get_last(); 
        }

        if(AWebApi::$lastError != NULL) {
            $headers = AWebApi::getDefaultHeaders();
            $headers[] = 'Status: 500 Internal Server Error';
            $headers[] = "Content-type: application/json; charset=utf-8";  
            foreach($headers as $header){ header($header); }
            
            if(ob_get_length() > 0){
                ob_clean();
            }
            
            flush();
            echo json_encode( new WebApiResult(false, null, AWebApi::$lastError['message']));
        }
    }

    public static function getDefaultHeaders(){
        $result = array();
        $result[] = "Access-Control-Allow-Origin: ". AWebApi::$httpOrigin;
        $result[] = 'Access-Control-Allow-Credentials: true';
        $result[] = 'Access-Control-Max-Age: 86400';    // cache for 1 day
        $result[] = "Access-Control-Allow-Methods: GET, POST, OPTIONS";         
        $result[] = "Access-Control-Allow-Headers: Origin, Accept, Content-Type";
        return $result;
    }

    public function getRequest(){
        if(empty($_REQUEST)){
            $this->request = json_decode(file_get_contents('php://input'), true);
            if($this->request == null){
                $this->request = array();
            }
        }
        else{
            $this->request = $_REQUEST;
        }
    }

    public function preProcessRequest(){
        $sesskey = (isset($this->request['sesskey']) ? clean_param($this->request['sesskey'], PARAM_TEXT) : 'nosesskey'); 

        if(!confirm_sesskey($sesskey)){
            $this->lastResult = new WebApiResult(false, null, get_string('invalidsesskey','local_recitworkplan'));
            return false;
        }

        if(!isset($this->request['service'])){
            $msg = get_string('servicenotfound', 'local_recitworkplan');
            $success = false;

            if(isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == "OPTIONS"){
                $msg = "Replying OPTIONS request";
                $success = true;
            }

            $this->lastResult = new WebApiResult($success, null, $msg);
			return false;
        }
		
        return true;
    }

    protected function getAllowedServices() {
        return [];
    }

    public function processRequest(){
        if(!$this->preProcessRequest()){
            return;
        }

        $serviceWanted = clean_param($this->request['service'], PARAM_TEXT);

        $allowed = $this->getAllowedServices();
        if (!empty($allowed) && !in_array($serviceWanted, $allowed, true)) {
            $this->lastResult = new WebApiResult(false, null, get_string('servicenotfound', 'local_recitworkplan'));
            return;
        }

        $result = $this->$serviceWanted($this->request);

        $this->lastResult = $result;
    }
	
	public function replyClient(){
        AWebApi::$lastError = error_get_last();
        if(AWebApi::$lastError != null){ return; }

        $webApiResult = $this->lastResult;
        $headers = AWebApi::getDefaultHeaders(); 
        $result = json_encode($webApiResult);

        switch($webApiResult->contentType){
			case 'json':
				$headers[] = "Content-type: application/json; charset=utf-8";
				break;
			case 'html':
				$headers[] = "Content-type: text/html; charset=utf-8";
				break;
			case 'octet-stream':
			case 'application/csv':
				$headers[] = sprintf("Content-type: %s", $webApiResult->contentType);
				$headers[] = "Content-Description: File Transfer";
				$headers[] = sprintf('Content-Disposition: attachment; filename="%s"', basename($webApiResult->data->filename));
				$headers[] = 'Content-Transfer-Encoding: binary';
				$headers[] = 'Expires: 0';
				$headers[] = 'Cache-Control: must-revalidate';
				$headers[] = 'Pragma: public';
				$headers[] = sprintf('Content-Length: %s', filesize($webApiResult->data->filename));
				$result = file_get_contents($webApiResult->data->filename);
				break;
            default:
				$headers[] = "Content-type: text; charset=utf-8";                        
        }

        foreach($headers as $header){
            header($header);
        }
        
        if(ob_get_length() > 0){
            ob_clean();
        }
        
        flush();
        echo $result;
	}
		
    protected function prepareJson($obj){
        if(is_object($obj)){
            $tmp = get_object_vars($obj);
            foreach($tmp as $attr => $value){
                if($value instanceof DateTime){
                    $obj->$attr = $this->phpDT2JsDT($value);
                }
                else if(is_array($value)){
                    foreach($value as $item){
                        $this->prepareJson($item);
                    }
                }
                else if(is_object($value)){
                    $this->prepareJson($value);
                }
            }
        }
    }

    /**
     * Convert the PHP DateTime Object to be sent to the client (JavaScript date time string)
     */
    protected function phpDT2JsDT($value){
        // force the conversion to UTC date DateTime::ATOM
        return ($value == null ? "" : $value->format("Y-m-d H:i:s"));
    }

    /**
     * Convert the JavaScript date string to PHP DateTime Object
     */
    protected function jsDT2PhpDT($value){
        // force the conversion to UTC date
        return (empty($value) ? null : new DateTime($value, new DateTimeZone("UTC")));
    }

    protected function jsArray2PhpArray($request, $field){
        if(isset($request[$field])){
            if(strlen($request[$field]) > 0){
                return explode(",", $request[$field]);
            }
        }

        return array();
    }

    protected function createCSVFile($filename, $content, $charset = "ISO-8859-1", $delimiter = ";"){
        try{
            $fp = fopen($filename, 'w');
            if(!$fp){ throw new Exception("FAILED: It was not possible to create the temporary file.");}
    
            foreach($content as $row){
                $nbCols = count($row);
                for($iCol = 0; $iCol < $nbCols; $iCol++){
                    $row[$iCol] = utf8_decode($row[$iCol]);
                }
                fputcsv($fp, $row, $delimiter);
            }
            
            fclose($fp);
    
            $data = new stdClass();
            $data->filename = $filename;
            $data->charset = $charset;
            return $data;
        }
        catch(Exception $ex){
            throw $ex;
        }  
    }
}

abstract class MoodleApi extends AWebApi
{
    protected $signedUser = null;
    protected $course = null;
    protected $dbConn = null;

    public function __construct($DB, $COURSE, $USER){
        $this->signedUser = $USER;
        $this->course = $COURSE;
        $this->dbConn = $DB;
        PersistCtrl::getInstance($DB, $USER);
    }
}

register_shutdown_function(function(){ return AWebApi::onPhpError(); });