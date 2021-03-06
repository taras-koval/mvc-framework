<?php

namespace App\Core;

use PDO;

class Database
{
    public PDO $pdo;
    
    public function __construct()
    {
        $config = config('database');
        
        $dsn = sprintf('mysql:host=%s;port=%u;dbname=%s;charset=utf8',
            $config['host'],
            $config['port'],
            $config['database']
        );
        
        $this->pdo = new PDO($dsn, $config['username'], $config['password'], $config['options']);
    }
    
    public function prepare($sql)
    {
        return $this->pdo->prepare($sql);
    }
    
    /**
     * @param  string  $table (example - 'users')
     * @param  array  $where (example - ['id' => 1])
     * @param  string|null  $className
     * @return mixed
     */
    public function find(string $table, array $where, string $className = null)
    {
        $fields = array_keys($where);
        $params = array_map(fn($field) => "$field=:$field", $fields);
        
        $stmt = $this->pdo->prepare("SELECT * FROM $table WHERE ". implode(' AND ', $params));
    
        foreach ($where as $field => $value) {
            $stmt->bindValue(":$field", $value);
        }
        
        $stmt->execute();
    
        if (isset($className)) {
            $stmt->setFetchMode(PDO::FETCH_CLASS, $className);
        }
        return $stmt->fetch();
    }
    
    /**
     * @param  string  $table (example - 'users')
     * @param  array  $where (example - ['username' => 'tom'])
     * @param  string|int  $orderBy
     * @param  int  $limit
     * @param  int  $offset
     * @param  string|null  $className
     * @return array|false
     */
    public function findAll(string $table, array $where = [], $orderBy = 1,
        int $limit = 100, int $offset = 0, string $className = null)
    {
        $fields = array_keys($where);
        
        $sql = sprintf('SELECT * FROM %s ORDER BY %s LIMIT %u OFFSET %u',
            $table, $orderBy, $limit, $offset);
        
        if ($where) {
            $params = array_map(fn($field) => "$field=:$field", $fields);
            $params = implode(' AND ', $params);
    
            $sql = sprintf('SELECT * FROM %s WHERE %s ORDER BY %s LIMIT %u OFFSET %u',
                $table, $params, $orderBy, $limit, $offset);
        }
        
        $stmt = $this->pdo->prepare($sql);
        
        foreach ($where as $field => $value) {
            $stmt->bindValue(":$field", $value);
        }
        
        $stmt->execute();
        
        /*$data = $stmt->fetchAll();
        if ($data && isset($className)) {
            $objects = [];
            foreach ($data as &$item) {
                $obj = new $className();
                snakeCaseToCamelCaseArrayKeys($item);
                setObjectFromArray($obj, $item);
                $objects[] = $obj;
            }
            return $objects;
        }
        return $data;*/
    
        if (isset($className)) {
            $stmt->setFetchMode(PDO::FETCH_CLASS, $className);
        }
        return $stmt->fetchAll();
    }
    
    public function create(string $table, array $data): ?int
    {
        $fields = array_keys($data);
        $params = array_map(fn($param) => ":$param", $fields);
        
        $fieldsImploded = implode(', ', $fields);
        $paramsImplided = implode(', ', $params);
        
        $sql = "INSERT INTO $table ($fieldsImploded) VALUES ($paramsImplided)";
        $stmt = $this->pdo->prepare($sql);
    
        foreach ($fields as $field) {
            $stmt->bindValue(":$field", $data[$field]);
        }
        
        return $stmt->execute() ? intval($this->pdo->lastInsertId()) : null;
    }
    
    public function update(string $table, array $data): bool
    {
        $fields = array_keys($data);
        $params = array_map(fn($param) => "$param=:$param", $fields);
        $params = implode(', ', $params);
        
        $sql = "UPDATE $table SET $params WHERE $table.id=" . $fields['id'];
        $stmt = $this->pdo->prepare($sql);
    
        foreach ($fields as $field) {
            $stmt->bindValue(":$field", $data[$field]);
        }
    
        return $stmt->execute();
    }
    
    public function delete(string $table, int $id): bool
    {
        $sql = "DELETE FROM $table WHERE $table.id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(":id", $id);
        return $stmt->execute();
    }
    
    public function applyMigrations()
    {
        $this->createMigrationsTable();
        $appliedMigrations = $this->getAppliedMigrations();
        
        $files = scandir(ROOT.'/database/migrations');
        $toApplyMigrations = array_diff($files, $appliedMigrations);
        
        $newMigrations = [];
        
        foreach ($toApplyMigrations as $migration) {
            
            if ($migration === '.' || $migration === '..') {
                continue;
            }
            
            require_once ROOT.'/database/migrations/'.$migration;
            
            $className = pathinfo($migration, PATHINFO_FILENAME);
            $instance = new $className();
            $instance->up();
            
            $this->log("Applied migration $migration");
            
            $newMigrations[] = $migration;
        }
        
        if (!empty($newMigrations)) {
            $this->saveMigrations($newMigrations);
        } else {
            $this->log("All migrations are applied");
        }
    }
    
    private function createMigrationsTable()
    {
        $sql = <<<SQL
            CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=INNODB;
        SQL;
        $this->pdo->exec($sql);
    }
    
    private function getAppliedMigrations()
    {
        $stmt = $this->pdo->prepare("SELECT migration FROM migrations");
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    private function saveMigrations(array $migrations)
    {
        $values = implode(', ', array_map(fn($value) => "('$value')", $migrations));
        
        $stmt = $this->pdo->prepare("INSERT INTO migrations (migration) VALUES $values");
        $stmt->execute();
    }
    
    private function log($message)
    {
        echo '[' . date('Y-m-d H:i') . '] - ' . $message . PHP_EOL;
    }
}