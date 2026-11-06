<?php
// Class for database connection via PDO.
// PDO
//   - Supports multiple databases
//   - Security thanks to prepared statements (anti SQL Injection)
//   - Error handling with try/catch
//   - Object-oriented interface


declare(strict_types=1);

class DbConnector
{
    private string $servername;
    private string $username;
    private string $password;
    private string $dbname;
    private ?PDO $connection = null;

    public function __construct(
        string $servername,
        string $username,
        string $password,
        string $dbname
    ) {
        $this->servername = $servername;
        $this->username   = $username;
        $this->password   = $password;
        $this->dbname     = $dbname;
    }

    // Prepares a PDO statement (used for prepared statements).
    public function prepare(string $query): PDOStatement
    {
        // try/catch: better error handling compared to mysqli
        try {
            $connection = $this->getConnection();
            return $connection->prepare($query);
        } catch (PDOException $e) {
            // Never show raw error (information disclosure!)
            // In production: log to file, don't echo
            exit("Database connection error.");
        }
    }

    // Returns the raw PDO connection (used to call
    // Stored Procedures with CALL and manage transactions).
    public function getConnection(): PDO
    {
        if ($this->connection === null) {
            try {
                $this->connection = new PDO(
                    "mysql:host={$this->servername};dbname={$this->dbname};charset=utf8mb4",
                    $this->username,
                    $this->password,
                    [
                        // Throws exceptions on DB error
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ]
                );
            } catch (PDOException $e) {
                // Never show raw error (information disclosure!)
                // In production: log to file, don't echo
                exit("Database connection error.");
            }
        }
        return $this->connection;
    }
}