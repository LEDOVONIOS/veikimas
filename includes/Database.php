<?php
/**
 * Database class for MySQL connections
 */
class Database {
    private static $instance = null;
    private $connection;
    private $last_error = '';
    
    private function __construct() {
        $this->connect();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function connect() {
        $this->connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($this->connection->connect_error) {
            die("Connection failed: " . $this->connection->connect_error);
        }
        
        $this->connection->set_charset("utf8mb4");
    }
    
    public function query($sql, $params = []) {
        if (!empty($params)) {
            $stmt = $this->connection->prepare($sql);
            if (!$stmt) {
                $this->last_error = $this->connection->error;
                return false;
            }
            
            $types = '';
            $values = [];
            
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                }
                $values[] = $param;
            }
            
            $stmt->bind_param($types, ...$values);
            $result = $stmt->execute();
            
            if (!$result) {
                $this->last_error = $stmt->error;
                return false;
            }
            
            $result = $stmt->get_result();
            $stmt->close();
            
            return $result;
        } else {
            $result = $this->connection->query($sql);
            if (!$result) {
                $this->last_error = $this->connection->error;
            }
            return $result;
        }
    }
    
    public function fetch($result) {
        return $result->fetch_assoc();
    }
    
    public function fetchAll($result) {
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    }
    
    public function fetchOne($sql, $params = []) {
        $result = $this->query($sql, $params);
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        return null;
    }
    
    public function fetchValue($sql, $params = []) {
        $row = $this->fetchOne($sql, $params);
        return $row ? reset($row) : null;
    }
    
    public function fetchAllArray($sql, $params = []) {
        $result = $this->query($sql, $params);
        if ($result) {
            return $this->fetchAll($result);
        }
        return [];
    }
    
    public function insert($table, $data) {
        $fields = array_keys($data);
        $values = array_values($data);
        $placeholders = array_fill(0, count($fields), '?');
        
        $sql = "INSERT INTO `$table` (`" . implode('`, `', $fields) . "`) VALUES (" . implode(', ', $placeholders) . ")";
        
        $result = $this->query($sql, $values);
        if ($result) {
            return $this->connection->insert_id;
        }
        return false;
    }
    
    public function update($table, $data, $where, $whereParams = []) {
        $fields = [];
        $values = [];
        
        foreach ($data as $field => $value) {
            $fields[] = "`$field` = ?";
            $values[] = $value;
        }
        
        $sql = "UPDATE `$table` SET " . implode(', ', $fields) . " WHERE $where";
        $values = array_merge($values, $whereParams);
        
        return $this->query($sql, $values);
    }
    
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM `$table` WHERE $where";
        return $this->query($sql, $params);
    }
    
    public function escape($value) {
        return $this->connection->real_escape_string($value);
    }
    
    public function getLastError() {
        return $this->last_error;
    }
    
    public function beginTransaction() {
        return $this->connection->begin_transaction();
    }
    
    public function commit() {
        return $this->connection->commit();
    }
    
    public function rollback() {
        return $this->connection->rollback();
    }
    
    public function close() {
        if ($this->connection) {
            $this->connection->close();
        }
    }
    
    public function __destruct() {
        $this->close();
    }
}