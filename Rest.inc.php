<?php

class REST
{
    public $_allow = array();
    public $_content_type = "application/json";
    public $_request = array();
    private $_code = 200;

    public function __construct()
    {
        $this->inputs();
    }

    public function response($data, $status)
    {
        $this->_code = ($status) ? $status : 200;
        $this->setHeaders();
        echo $data;
        exit;
    }

    // For a list of http codes checkout http://en.wikipedia.org/wiki/List_of_HTTP_status_codes
    private function getStatusMessage()
    {
        $status = array(
            200 => 'OK',
            201 => 'Created',
            204 => 'No Content',
            404 => 'Not Found',
            406 => 'Not Acceptable');
        return ($status[$this->_code]) ? $status[$this->_code] : $status[500];
    }

    public function getRequestMethod()
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    private function inputs()
    {
        switch ($this->getRequestMethod()) {
            case "POST":
                $this->_request = $this->cleanInputs($_POST);
                break;
            case "GET":
                $this->_request = $this->cleanInputs($_GET);
                break;
            default:
                $this->response('', 406);
                break;
        }
    }

    private function cleanInputs($data)
    {
        $clean_input = array();
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $clean_input[$k] = $this->cleanInputs($v);
            }
        } else {
            if (get_magic_quotes_gpc()) {
                $data = trim(stripslashes($data));
            }
            $data = strip_tags($data);
            $clean_input = trim($data);
        }
        return $clean_input;
    }

    private function setHeaders()
    {
        header("HTTP/1.1 " . $this->_code . " " . $this->getStatusMessage());
        header("Content-Type:" . $this->_content_type);
    }
}
