<?php

    if($_SERVER["REQUEST_METHOD"] == "POST"){
        $username = $_POST["Name"];
        $password = $_POST["password"];
        $email = $_POST["email"];

        try{
            require_once "config.php";
            $query = "INSERT INTO users_info (Name, password, email) VALUES (?, ?, ?);";

            $userstatements = $pdo->prepare($query);
            $userstatements->execute([$username, $password, $email]);

            $pdo = null;
            $userstatements = null;

            header("Location: ../api.php");

            die();
        }catch(PDOException $e){
            die("Query failed: " . $e->getMessage());
        }
    }else{
        header("Location: ../api.php");
    }