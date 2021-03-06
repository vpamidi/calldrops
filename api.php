<?php
require_once("Rest.inc.php");

class API extends REST
{

    public $data = "";

    const DB_SERVER = "127.0.0.1";
    const DB_USER = "root";
    const DB_PASSWORD = "";
    const DB = "calldrop";

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

        $operator = isset($_REQUEST['operator']) ? $_REQUEST['operator'] : '';
        $sdate = isset($_REQUEST['startdate']) ? $_REQUEST['startdate'] : '';
        $edate = isset($_REQUEST['enddate']) ? $_REQUEST['enddate'] : '';
        $lat = isset($_REQUEST['lat']) ? $_REQUEST['lat'] : 17.449444;
        $lng = isset($_REQUEST['lng']) ? $_REQUEST['lng'] : 78.372506;

        if ((int) method_exists($this, $functonName) > 0) {
            if (isset($operator) || ( isset($sdate) || isset($edate) )) {
                $this->$functonName($operator, $sdate, $edate, $lat, $lng);
            } else {
                $this->$functonName();
            }
        } else {
            $this->response('', 404); // If the method not exist with in this class "Page not found".
        }
    }

    private function generateData()
    {
        if ($this->getRequestMethod() != "POST") {
            $this->response('', 406);
        }

        $params = json_decode(file_get_contents("php://input"), true);

        $result = array('success' => true);
        $count = $params['count'];
        $operators = array('idea', 'airtel', 'vodafone', 'reliance', 'tata', 'bsnl');
        $query = "INSERT INTO calldrop(latitude, longitude, `date`, operator, signalstrength) VALUES ";
        $values = array();

        for ($i = 1; $i <= $count; $i++) {
            $lat = '17.'.rand(400000, 420000);
            $lng = '78.'.rand(450000, 500000);
            $date = $this->randomDate('2016-03-01', '2016-03-15');
            $key = array_rand($operators);

            $values[] = "($lat, $lng, '$date', '$operators[$key]', " . rand(1, 12).  ")";
        }
        $query .= implode(',', $values);

        $this->mysqli->query($query);
        $this->response($this->json($result), 200);
    }

    private function randomDate($start_date, $end_date)
    {
        // Convert to timetamps
        $min = strtotime($start_date);
        $max = strtotime($end_date);

        // Generate random number using above bounds
        $val = rand($min, $max);

        // Convert back to desired date format
        return date('Y-m-d H:i:s', $val);
    }

    /**
     *
     * @$para type string
     * @$sdate type date
     * @$edate type date
     *
     * Method to get data using API call.
     */
    private function mData($operator = '', $sdate = '', $edate = '', $lat = 17.449444, $lng = 78.372506)
    {

        if ($this->getRequestMethod() != "GET") {
            $this->response('', 406);
        }

        //$query = "SELECT c.latitude as lat, c.longitude as lng FROM calldrop c where c.operator != '' ";

        $query = "SELECT latitude as lat, longitude as lng FROM `calldrop` "
            . "WHERE (6371 * ACOS(SIN(RADIANS( $lat )) * SIN(RADIANS(`latitude`)) + COS(RADIANS( $lat )) * "
            . "COS(RADIANS(`latitude`)) * COS(RADIANS(`longitude`) - RADIANS( $lng )))) "
            . "<= 10";

        if (!empty($operator)) {
            $query .= " and operator = '$operator' ";
        } else if (!empty($sdate) && !empty($edate)) {
            $query .= " and date >= '$sdate' AND c.date <=  '$edate'";
        } else if (!empty($sdate)) {
            $query .= " and date like '$sdate%' ";
        } else if (!empty($edate)) {
            $query .= " and date like '$edate%' ";
        }

        $r = $this->mysqli->query($query) or die($this->mysqli->error . __LINE__);

        $result = array('count' => 0, 'locations' => array());
        if ($r->num_rows > 0) {
            while ($row = $r->fetch_assoc()) {
                $result['locations'][] = $row;
            }
            $result['count'] = $r->num_rows;
        }

        $this->response($this->json($result), 200); // send user details
    }


    /**
     *
     * @$para type string
     * @$sdate type date
     * @$edate type date
     *
     * Method to get data using API call.
     */
    private function mDataNearestMultiplePoints($operator = '', $sdate = '', $edate = '', $lat = 17.449444, $lng = 78.372506, $distance = 0.5)
    {

        if ($this->getRequestMethod() != "GET") {
            $this->response('', 406);
        }

       /* $query = "SELECT latitude as lat, longitude as lng FROM `calldrop` "
            . "WHERE (6371 * ACOS(SIN(RADIANS( $lat )) * SIN(RADIANS(`latitude`)) + COS(RADIANS( $lat )) * "
            . "COS(RADIANS(`latitude`)) * COS(RADIANS(`longitude`) - RADIANS( $lng )))) "
            . "<= 10";*/
        $query = "SELECT a.id as fid, a.latitude as flat, a.longitude as flng, b.id as sid, b.latitude as slat, b.longitude as slng, "
            . "(((acos(sin((b.latitude*pi()/180)) * sin((a.latitude*pi()/180))+cos((b.latitude*pi()/180)) * cos((a.latitude*pi()/180)) * cos(((b.longitude- a.longitude)*pi()/180))))*180/pi())*60*1.1515*1.609344) AS distance "
            . "FROM (SELECT id,latitude,longitude FROM `calldrop` WHERE "
            . "(6371 * ACOS(SIN(RADIANS( ".$lat." )) * SIN(RADIANS(latitude)) + COS(RADIANS( ".$lat." )) * COS(RADIANS(latitude)) * COS(RADIANS(longitude) - RADIANS( ".$lng." )))) <= 10 ) a, "
            . "(SELECT id,latitude,longitude FROM `calldrop` WHERE"
            ." (6371 * ACOS(SIN(RADIANS( ".$lat." )) * SIN(RADIANS(latitude)) + COS(RADIANS( ".$lat." )) * COS(RADIANS(latitude)) * COS(RADIANS(longitude) - RADIANS( ".$lng." )))) <= 10 ) b"
            . " where a.id != b.id Having distance <= ".$distance;

        if (!empty($operator)) {
            $query .= " and operator = '$operator' ";
        } else if (!empty($sdate) && !empty($edate)) {
            $query .= " and date >= '$sdate' AND c.date <=  '$edate'";
        } else if (!empty($sdate)) {
            $query .= " and date like '$sdate%' ";
        } else if (!empty($edate)) {
            $query .= " and date like '$edate%' ";
        }

        $r = $this->mysqli->query($query) or die($this->mysqli->error . __LINE__);

        $result = array('count' => 0, 'locations' => array());
        if ($r->num_rows > 0) {
            while ($row = $r->fetch_assoc()) {
                $result['locations'][] = $row;
            }
            $result['count'] = $r->num_rows;
        }

        $responseData = array();
        $responseData['locations'] = $this->getNearestPoints($result);
        $responseData['count'] = count($responseData['locations']);

        $this->response($this->json($responseData), 200); // send user details
    }

    /**
     *
     * @$result array
     *
     * Method to get distance between points.
     */
    private function getNearestPoints($result) {

        $pointsData = $result['locations'];
        $idsData = array();
        $results = array();
         if($result['count'] > 0 ) {
            $i = 0;
            foreach($pointsData as $row) {
                 if(!in_array($row['fid'], $idsData)) {
                     $idsData[] = $row['fid'];
                     $results[$i]['lat'] = $row['flat'];
                     $results[$i]['lng'] = $row['flng'];
                     $i++;
                 }
                 if(!in_array($row['sid'], $idsData)) {
                     $idsData[] = $row['sid'];
                     $results[$i]['lat'] = $row['slat'];
                     $results[$i]['lng'] = $row['slng'];
                     $i++;
                 }
            }
         }
        // $results = array_map("unserialize", array_unique(array_map("serialize", $results)));
         return $results;
    }

    /**
     *
     * @$lat1 type float
     * @$lon1 type float
     * @$lat2 type float
     * @$lon2 type float
     *
     * Method to get distance between two calls.
     */
    private function getDistanceBetweenPoints($lat1, $lon1, $lat2, $lon2) {
        $theta = $lon1 - $lon2;
        $miles = (sin(deg2rad($lat1)) * sin(deg2rad($lat2))) + (cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta)));
        $miles = acos($miles);
        $miles = rad2deg($miles);
        $miles = $miles * 60 * 1.1515;
        $kilometers = $miles * 1.609344;
        return $kilometers;
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
