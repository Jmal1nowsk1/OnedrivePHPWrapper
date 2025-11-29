<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require __DIR__ . '/../vendor/autoload.php';
use App\OneDriveClient;
$dotenv = Dotenv\Dotenv::createImmutable('../');
$dotenv->load();

//header('Content-Type: application/json');

$client = new OneDriveClient();
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$headers = getallheaders();
$auth = $headers['Authorization'] ?? '';


if ($auth !== $_ENV['TOKEN']) {
    http_response_code(401);
    echo json_encode([
        'error' => 'Invalid token',
        'token' => $_ENV['TOKEN'],
        'got' => $headers
    ]);
    exit;
}

switch (true) {
    case $method === 'POST' && $path === '/mkdir':
        $data = json_decode(file_get_contents("php://input"), true);
        $dir = $data['directory'] ?? '';

        if (empty($dir)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Brak nazwy katalogu']);
            break;
        }

        if ($client->directoryExists($dir)) {
            echo json_encode(['success' => false, 'error' => 'Katalog już istnieje na OD.']);
        } else {
            $result = $client->mkdir($dir);
            echo json_encode($result);
        }
        break;

    case $method === 'GET' && $path === '/exists':
        $dir = $_GET['directory'] ?? '';
        if (empty($dir)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Brak nazwy katalogu']);
            break;
        }
        $result = $client->directoryExists($dir);
        echo json_encode($result);
        break;

    case $method === 'GET' && $path === '/list':
        $dir = $_GET['directory'] ?? '';
        $depth = $_GET['depth'] ?? 'infinity';

        if (empty($dir)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Brak nazwy katalogu']);
            break;
        }

        $result = $client->listDirectories($dir, $depth);
        echo json_encode($result);
        break;

    case $method === 'POST' && $path === '/upload':
        if (!isset($_FILES['file'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Brak pliku w żądaniu.']);
            break;
        }

        $tmpFile = $_FILES['file']['tmp_name'];
        $directory = $_POST['directory'] ?? '';
        $originalName = basename($_FILES['file']['name']);

        $result = $client->saveFile($tmpFile, $directory, $originalName);

        if ($result['success']) {
            echo json_encode(['success' => true, 'message' => 'Plik zapisany']);
        } else {
            http_response_code(500);
            echo json_encode($result);
        }
        break;

    case $method === 'DELETE' && $path === '/delete':
        $data = json_decode(file_get_contents("php://input"), true);
        $pathToDelete = $data['path'] ?? '';

        if (empty($pathToDelete)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Brak ścieżki pliku']);
            break;
        }

        $result = $client->deleteFile($pathToDelete);
        echo json_encode($result);
        break;


    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Endpoint nie istnieje']);
}