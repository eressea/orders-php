<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

require_once __DIR__ . '/OrderDB.php';

class orders {
    public static function export(OrderDB $db) {
        $files = $db->getFiles();
        foreach ($files as $row) {
            $filename = $row['filename'];
            if (!file_exists($filename)) {
                continue;
            }
            $content = file_get_contents($filename);
            if ($content !== FALSE) {
                echo $content;
                $language = $row['language'];
                switch ($language) {
                    case 'en':
                        echo "\nNEXT\n";
                        break;
                    default:
                        echo "\nNAECHSTER\n";
                }
            }
        }
    }
    
    public static function list(OrderDB $db) {
        $stmt = $db->getRows();
        $email = NULL;
        $date = NULL;
        $filename = NULL;
        $status = -1;
        $stmt->bindColumn('email', $email, PDO::PARAM_STR);
        $stmt->bindColumn('time', $date, PDO::PARAM_STR);
        $stmt->bindColumn('filename', $filename, PDO::PARAM_STR);
        $stmt->bindColumn('status', $status, PDO::PARAM_INT);
        while ($stmt->fetch(PDO::FETCH_BOUND)) {
            echo "$status\t$date\t$filename\t$email\n";
        }
    }
    
    public static function insert(OrderDB $db, DateTimeInterface $time, string $filename, string $lang, ?string $email = NULL, int $status = 0) {
        return $db->addFile($time, $filename, $lang, $email, $status);
    }
    
    public static function set_status(OrderDB $db, string $filename, int $status) {
        $db->setStatus($filename, $status);
    }

    public static function select(OrderDB $db) {
        $row = $db->selectRow();
        return $row;
    }
}
