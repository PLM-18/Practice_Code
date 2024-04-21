<?php

require_once "config.php";

class User extends Database{
    private $type;

    protected function request_type(){
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $this->type = $_POST["type"];
            if ($this->type === "Register") {
                $this->Register();
            } else if ($this->type === "GetAllListings") {
                $this->GetAllListings();
            }
            mysqli_close($this->getConnection());
        }
    }

    protected function Register(){
        $email_validation_regex = "/^[a-z0-9!#$%&'*+\\/=?^_`{|}~-]+(?:\\.[a-z0-9!#$%&'*+\\/=?^_`{|}~-]+)*@(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/";
        $name = $_POST["name"];
        $surname = $_POST["surname"];
        $email = $_POST["email"];
        $password = $_POST["password"];
        $registration = date('Y-m-d hh:i:s');
        $apikey = $this->generateKey();

        /* ----------------------------------------Regex Validations--------------------------------- */
        if (empty($_POST["name"])) {
            echo "Name value is required";
            return;
        } else {
            if (!preg_match("/^[a-zA-Z-' ]*$/", $name)) {
                echo "Invalid name";
                return;
            }
        }

        if (empty($_POST["surname"])) {
            echo "surname value is required";
            return;
        } else {
            if (!preg_match("/^[a-zA-Z-' ]*$/", $surname)) {
                echo "Invalid surname";
            }
            return;
        }

        if (empty($_POST["email"])) {
            echo "email value is required";
            return;
        } else {
            if (!preg_match($email_validation_regex, $email)) {
                echo "Invalid email";
            }
            return;
        }

        if (empty($_POST["password"])) {
            echo "Password value is required";
            return;
        } else {
            if (strlen($password) < 8) {
                echo "Password too short";
                return;
            } else if (!preg_match('/[A-Z]/', $password)) {
                echo "Password should contain atleast one uppercase letter";
                return;
            } else if (!preg_match('/[a-z]/', $password)) {
                echo "Password should contain atleast one lowercase letter";
                return;
            } else if (!preg_match('/\d/', $password)) {
                echo "Password should contain atleast one digit";
                return;
            } else if (!preg_match('/[^a-zA-Z\d]/', $password)) {
                echo "Password should contain atleast one symbol";
                return;
            }
        }

        //user already exists
        $checkquery = "SELECT COUNT(*) AS count FROM users where email = ?";
        $stm = $this->getConnection()->prepare($checkquery);
        $stm->bind_param("s", $email);
        $stm->execute();
        $result = $stm->get_result();
        $row = $result->fetch_assoc();
        if ($row["count"] > 0) {
            echo "User already exists";
            return;
        }

        //avoid sql injections
        $query = "INSERT INTO users (name, surname, email, password, registration_date, apikey) VALUES (?, ?, ?, ?, ?, ?);";

        $userstatements = $this->getConnection()->prepare($query);
        $userstatements->execute([$name, $surname, $email, $password, $registration, $apikey]);
        $pdo = null;
        $userstatements = null;

        //entry was successfull
        $status = "success";
        $timestamp = round(microtime(true) * 1000);
        $data = array(
            "apikey" => $apikey
        );

        $response = array(
            "status" => $status,
            "timestamp" => (string)$timestamp,
            "data" => array($data)
        );

        echo json_encode($response, JSON_PRETTY_PRINT);
    }

    function checker($randstr){
        $sql = "SELECT * FROM users";
        $result = mysqli_query($this->getConnection(), $sql);

        while ($row = mysqli_fetch_assoc($result)) {
            if ($row["apikey"] == $randstr) {
                $keyexist = true;
                break;
            } else {
                $keyexist = false;
            }
        }

        return $keyexist;
    }

    function generateKey(){
        $keylength = 10;
        $str = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";
        $randstr = substr(str_shuffle($str), 0, $keylength);
        $checkkey = $this->checker($randstr);

        while ($checkkey == true) {
            $randstr = substr(str_shuffle($str), 0, $keylength);
            $checkkey = $this->checker($randstr);
        }

        return $randstr;
    }

    function response_error(){
        $timestamp = round(microtime(true) * 1000);

        $response = array(
            "status" => "error",
            "timestamp" => (string)$timestamp,
            "data" => "Post parameters are missing"
        );
        echo json_encode($response, JSON_PRETTY_PRINT);
    }

    function GetAllListings(){
        //required fields
        $apikey = isset($_POST["apikey"]) ? $_POST["apikey"] : null;
        $return = isset($_POST["return"]) ? $_POST["return"] : null;
        //optional fields
        $limit = isset($_POST["limit"]) ? $_POST["limit"] : 100;
        $sort = null;
        if (isset($_POST["sort"])) {
            $sort = $_POST["sort"];
            if (isset($_POST["order"])) {
                $order = $_POST["order"];
            }
        }
        $fuzzy = isset($_POST["fuzzy"]) ? $_POST["fuzzy"] : true;
        $search = isset($_POST["search"]) ? $_POST["search"] : null;

        if (empty($apikey) || empty($return)) {
            $this->response_error();
            return;
        }

        if ($apikey != "c9b5b8a84d5d4928ce64db7b53a5dec1") {
            echo "Invalid API Key";
            return;
        }

        /* [’id’, ’title’,
        ’location’, ’price’, ’bedrooms’, ’bathrooms’, ’url’, ’parking spaces’, ’amenities’,
        ’description’, ’type’, ’images’] */

        $sql = "SELECT ";
        if ($return == "*") {
            $sql .= "*";
        } else {
            $sql .= implode(", ", array_intersect($return, ["id", "title", "location", "price", "bedrooms", "bathrooms", "url", "parking spaces", "amenities", "description", "type", "images"]));
        }

        $sql .= " FROM listings WHERE 1";
        $params = array();
        if (!empty($search)) {
            $mp = array(
                "title" => "title",
                "location" => "location",
                "price_min" => "price >= ?",
                "price_max" => "price <= ?",
                "bedrooms" => "bedrooms = ?",
                "bathrooms" => "bathrooms = ?",
                "parking_spaces" => "parking_spaces = ?",
                "amenities" => "amenities LIKE ?",
                "type" => "type = ?"
            );

            foreach ($search as $key => $value) {
                if (isset($mp[$key])) {
                    $sql .= " AND " . $mp[$key];
                    if ($key === "amenities") {
                        $value = "%" . $value . "%";
                    }

                    $params[] = $value;
                }
            }
        }

        if(!empty($sort)){
            $sql .= " ORDER BY " . $sort;
            if(!empty($order)){
                $sql .= $order;
            }
        }

        $sql .= " LIMIT " . $limit;

        $stm = $this->getConnection()->prepare($sql);
        $stm->execute($params);
        $resultSet = $stm->get_result();

        $json_array = array();
        while ($row = $resultSet->fetch_assoc()) {
            $json_array[] = $row;
        }
        print(json_encode($json_array));
        
    }
}
