<?php
include_once 'environment.php';

class Database
{
    private $config;
    public $conn;

    public function __construct()
    {
        $this->config = Environment::getDbConfig();
    }

    public function getConnection()
    {
        $this->conn = null;
        try {
            $dsn = "pgsql:host=" . $this->config['host'] .
                ";port=" . $this->config['port'] .
                ";dbname=" . $this->config['dbname'] .
                ";sslmode=require" .
                ";sslrootcert=" . __DIR__ . "/../ssl/ca.pem";

            $this->conn = new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                )
            );
        } catch (PDOException $exception) {
            error_log("Connection error: " . $exception->getMessage());
            error_log("DSN: " . $dsn);
            throw new Exception("Database connection failed: " . $exception->getMessage());
        }
        return $this->conn;
    }

    public function executeQuery($sql, $params = [])
    {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage());
        }
    }
}
