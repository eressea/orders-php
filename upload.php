<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
$method = getenv('REQUEST_METHOD');
if ($method != 'POST') {
    header('HTTP/1.0 405 Method Not Allowed');
    exit();
}

require_once __DIR__ . '/orders.php';

// TODO: read these from a config file
$config = [
    'game' => 2,
    'lang' => 'de',
    'uploads' => '/home/eressea/www/eressea/files',
    'dbname' => 'orders.db',
];
$email = NULL;
$game = filter_input(INPUT_POST, 'game', FILTER_VALIDATE_INT, ['options' => ['default' => $config['game'], 'min_range' => 1]]);
$lang = filter_input(INPUT_POST, 'lang', FILTER_SANITIZE_STRING, ['options' => ['default' => $config['lang'], 'flags' => FILTER_REQUIRE_SCALAR]]);

class Uploader
{
    private $config;
    private $game;

    public function __construct(array $config, int $game)
    {
        $this->config = $config;
        $this->game = $game;
    }

    protected static function response(int $status = 200, $message = NULL, array $body = NULL)
    {
        return (object)array(
            'status' => $status,
            'message' => $message,
            'body' => $body,
        );
    }

    public function store(string $tmp_name, string $lang, string $email = NULL)
    {
        $input = file_get_contents($tmp_name);
        $encoding = mb_detect_encoding($input, ['ASCII', 'UTF-8']);
        if (FALSE === $encoding) {
            $body = array('errors' => array('input must be a UTF-8 file'));
            return self::response(406, 'Not Acceptable', $body);
        }
        $upload_dir = $config['uploads'] . '/uploads/game-' . $this->game;
        $dbfile = $upload_dir . '/' . $config['dbname'];
        if (!file_exists($dbfile)) {
            $body = array('errors' => array('database not found'));
            return self::response(500, 'Internal Server Error', $body);
        }
        $filename = tempnam($upload_dir, 'upload-');
        if (empty($filename) || !move_uploaded_file($tmp_name, $filename)) {
            return self::response(507, 'Insufficient Storage');
        }
        $dbsource = 'sqlite:' . $dbfile;
        $db = new OrderDB();
        $db->connect($dbsource);
        $time = new DateTime();
        $id = orders::insert($db, $time, $filename, $lang, $email, 3);
        $body = array(
            'data' => array(
                'filename' => $filename,
                'id' => $id,
            ),
        );
        return self::response(201, 'Created', $body);
    }
}

if (isset($_FILES['input'])) {
    $tmp_name = $_FILES['input']['tmp_name'];
    $uploader = new Uploader($config, $game);
    $response = $uploader->store($tmp_name, $lang, $email);
    if ($response->status) {
        $header = 'HTTP/1.0 ' . $status;
        if ($repsonse->message) {
            $header .= ' ' . $repsonse->message;
        }
        header($header);
    }
    $body = $response->body ?? '';
    if ($format == 'json') {
        header('Content-Type: application/javascript');
        if (!is_string($body)) {
            $body = json_encode($body);
        }
    } else {
        header('Content-Type: text/plain; charset=utf-8');
    }
    if (!empty($body)) {
        echo PHP_EOL . $body;
    }
}
else {
    header('HTTP/1.0 400 Bad Request');
}
