<?php
require_once("Rest.inc.php");

class API extends REST
{

    public $data = "";

    const DB_SERVER = "127.0.0.1";
    const DB_USER = "tcg";
    const DB_PASSWORD = "tcg!@#";
    const DB = "tcg";

    private $mysqli = NULL;

    public function __construct()
    {
        parent::__construct();    // Init parent contructor
        $this->dbConnect();     // Initiate Database connection
    }

    /*
     *  Connect to Database
     */

    private function dbConnect()
    {
        $this->mysqli = new mysqli(self::DB_SERVER, self::DB_USER, self::DB_PASSWORD, self::DB);
    }

    /*
     * Dynamically call the method based on the query string.
     */
    public function processApi()
    {
        $functonName = strtolower(trim(str_replace("/", "", $_REQUEST['x'])));

        $operator = isset($_REQUEST['operator']) ? $_REQUEST['operator'] : "";
        $sdate = isset($_REQUEST['startdate']) ? $_REQUEST['startdate'] : '';
        $edate = isset($_REQUEST['enddate']) ? $_REQUEST['enddate'] : '';

        if ((int) method_exists($this, $functonName) > 0) {
            if (isset($operator) || ( isset($sdate) || isset($edate) )) {
                $this->$functonName($operator, $sdate, $edate);
            } else {
                $this->$functonName();
            }
        } else {
            $this->response('', 404); // If the method not exist with in this class "Page not found".
        }
    }

    /**
     *
     * @$para type string
     * @$sdate type date
     * @$edate type date
     *
     * Method to get data using API call.
     */
    private function mData($para = '', $sdate = '', $edate = '')
    {
        if ($this->getRequestMethod() != "GET") {
            $this->response('', 406);
        }

        $query = "SELECT c.latitude as lat, c.longitude as lng FROM calldrop c";

        //$groupBy = "group by c.latitude, c.longitude";

        if (!empty($para)) {
            $query .= " where c.operator = '$para' ";
        } else if (!empty($sdate) && !empty($edate)) {
            $query .= " where c.date >= '$sdate' AND c.date <=  '$edate'";
        } else if (!empty($sdate)) {
            $query .= " where c.date like '$sdate%' ";
        } else if (!empty($edate)) {
            $query .= " where c.date like '$edate%' ";
        }

        $r = $this->mysqli->query($query) or die($this->mysqli->error . __LINE__);

        if ($r->num_rows > 0) {
            $result = array();
            while ($row = $r->fetch_assoc()) {
                $result[] = $row;
            }
            $this->response($this->json($result), 200); // send user details
        }
        $this->response('', 204); // If no records "No Content" status
    }

    /**
     * Method to post data using api call.
     */
    private function insertData()
    {
        if ($this->getRequestMethod() != "POST") {
            $this->response('', 406);
        }

        $calldrops = json_decode(file_get_contents("php://input"), true);
        $column_names = array('latitude', 'longitude', 'date', 'operator', 'signalstrength');
        $r = array();

        if (!empty($calldrops)) {
            foreach ($calldrops as $mdata) {
                $keys = array_keys($mdata);
                $columns = array();
                $values = array();

                foreach ($column_names as $desired_key) { // Check the customer received. If blank insert blank into the array.
                    if (in_array($desired_key, $keys)) {

                        $value = $mdata[$desired_key];

                        $columns[] = $desired_key;
                        $values[] =  "'" . $value . "'";
                    }
                }

                $query = "INSERT INTO calldrop(" . trim(implode(',', $columns), ',') . ") VALUES(" . trim(implode(',', $values), ',') . ")";
                //echo "$query<br>";
                if (!empty($mdata)) {
                    if ($this->mysqli->query($query)) {
                        $r[].='records inserted';
                    } else {
                        $r[].= die($this->mysqli->error . __LINE__);
                    }
                }
            }

            $success = array('status' => "Success", 'No of Records Inserted' => count($r));
            $this->response($this->json($success), 200);

        } else {

            $this->response('', 204); //"No Content" status
        }
    }

    /*
     * 	Encode array into JSON
     */

    private function json($data)
    {
        if (is_array($data)) {
            return json_encode($data);
        }
    }

}

// Initiate Library
$api = new API;
$api->processApi();
