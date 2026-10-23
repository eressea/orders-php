<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of OrderDB
 *
 * @author Enno
 */
class OrderDB {
    const SCHEMA_VERSION = 2;
    /** @var PDO $pdo */
    private $pdo;
    private $stmtInsert = NULL;
    private $stmtUpdateFile = NULL;

    private function update() {
        $stmt = $this->pdo->query("PRAGMA user_version");
        $column = $stmt->fetch(PDO::FETCH_COLUMN);
        $version = $column[0];
        if ($version >= self::SCHEMA_VERSION) {
            return;
        }
        if ($version == 0) {
            $schema = file_get_contents(__DIR__ . '/schema/sqlite.sql');
            $this->pdo->exec($schema);
        }
        else {
            for ($v = $version; $v != self::SCHEMA_VERSION; $v++) {
                $filename = __DIR__ . '/schema/sqlite-' . (1 + $v) . '.sql';
                $schema = file_get_contents($filename);
                if ($schema == false) {
                    echo "cannot update schema from version $version to " . self::SCHEMA_VERSION . PHP_EOL;
                    break;
                }
                $this->pdo->exec($schema);
            }
        }
    }
    
    public function connect(string $dbsource) {
        $this->pdo = new PDO($dbsource);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // $this->update();
    }
    
    public function getFiles() : array {
        $stmt = $this->pdo->query("SELECT `filename`, `language` FROM `submission` ORDER BY `time` ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRows() : PDOStatement {
        return $this->pdo->query("SELECT `time`, `status`, `email`, `filename` FROM `submission` ORDER BY `time` ASC");
    }

    public function setStatus(string $filename, int $status) {
        if (is_null($this->stmtUpdateFile)) {
            $this->stmtUpdateFile = $this->pdo->prepare("UPDATE `submission` SET `status` = ? WHERE `filename` = ?");
        }
        return $this->stmtUpdateFile->execute([$status, $filename]);
    }
    
    public function selectRow() {
        $this->pdo->beginTransaction();
        $stmt = $this->pdo->query("SELECT `language`, `filename`, `email` FROM `submission` WHERE `status` = 0 ORDER BY `time` LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!empty($row)) {
            $stmt = $this->setStatus($row['filename'], 1);
            $this->pdo->commit();
            return $row;
        }
        $this->pdo->rollback();
        return false;
    }
    
    public function addFile(DateTimeInterface $time, string $filename, string $lang, ?string $email = NULL, int $status = 0): int|false {
        $datetime = gmdate('Y-m-d H:i:s', $time->format('U'));
        if (is_null($this->stmtInsert)) {
            $this->stmtInsert = $this->pdo->prepare("INSERT INTO `submission` (`filename`, `time`, `language`, `email`, `status`) VALUES (?, ?, ?, ?, ?)");
        }
        if ($this->stmtInsert->execute([$filename, $datetime, $lang, $email, $status]) !== TRUE) {
            return false;
        }
        return (int) $this->pdo->lastInsertId();
    }
}
