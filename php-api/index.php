<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

require_once "db.php";

$method = $_SERVER["REQUEST_METHOD"];
$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

if ($path === "/tasks" && $method === "GET") {
    $stmt = $pdo->query("SELECT * FROM tasks ORDER BY created_at DESC");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

if ($path === "/tasks" && $method === "POST") {
    $data = json_decode(file_get_contents("php://input"), true);

    $title = $data["title"] ?? "";
    $description = $data["description"] ?? "";
    $estimatedHours = $data["estimated_hours"] ?? 0;
    $scrumPoints = $data["scrum_points"] ?? 0;

    if ($title === "" || $estimatedHours <= 0) {
        http_response_code(400);
        echo json_encode(["error" => "Titulo y horas estimadas son obligatorios"]);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO tasks (title, description, estimated_hours, scrum_points)
        VALUES (:title, :description, :estimated_hours, :scrum_points)
    ");

    $stmt->execute([
        ":title" => $title,
        ":description" => $description,
        ":estimated_hours" => $estimatedHours,
        ":scrum_points" => $scrumPoints
    ]);

    echo json_encode([
        "message" => "Tarea creada correctamente",
        "id" => $pdo->lastInsertId()
    ]);
    exit;
}

if (preg_match("#^/tasks/([0-9]+)/status$#", $path, $matches) && $method === "PUT") {
    $id = $matches[1];
    $data = json_decode(file_get_contents("php://input"), true);
    $status = $data["status"] ?? "";

    $allowedStatuses = ["Pendiente", "En proceso", "Terminada"];

    if (!in_array($status, $allowedStatuses)) {
        http_response_code(400);
        echo json_encode(["error" => "Estado invalido"]);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE tasks SET status = :status WHERE id = :id");
    $stmt->execute([
        ":status" => $status,
        ":id" => $id
    ]);

    echo json_encode(["message" => "Estado actualizado"]);
    exit;
}

http_response_code(404);
echo json_encode(["error" => "Ruta no encontrada"]);
