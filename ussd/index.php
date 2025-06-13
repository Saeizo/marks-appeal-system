<?php
require_once '../config/db.php';
require_once '../controllers/marksController.php';
require_once '../controllers/appealsController.php';
require_once '../controllers/adminUssdController.php';

// Enable debugging during development
ini_set('display_errors', 1);
error_reporting(E_ALL);

$sessionId   = $_POST['sessionId'] ?? $_GET['sessionId'] ?? '';
$phoneNumber = $_POST['phoneNumber'] ?? $_GET['phoneNumber'] ?? '';
$text        = $_POST['text'] ?? $_GET['text'] ?? '';

file_put_contents("ussd_debug.log", date('Y-m-d H:i:s') . " | text: $text | phone: $phoneNumber\n", FILE_APPEND);

function isAdminUser(string $phone, PDO $conn): bool {
    $stmt = $conn->prepare("SELECT 1 FROM admins WHERE phone_number = ? LIMIT 1");
    $stmt->execute([$phone]);
    return $stmt->fetchColumn() !== false;
}

function getMainMenu(): string {
    return "CON Welcome to the Marks Appeal System\n1. Check my marks\n2. Appeal my marks\n3. Exit";
}

function getStudentIdPrompt(): string {
    return "CON Enter your Student ID:\n0. Back";
}

function handleStudentFlow(array $input, int $level, PDO $conn): string {
    switch ($level) {
        case 1:
            return getMainMenu();

        case 2:
            if ($input[1] === "0") return getMainMenu();
            return match ($input[0]) {
                "1" => checkMarks(trim($input[1]), $conn),
                "2" => getStudentModules(trim($input[1]), $conn),
                default => "END Invalid input."
            };

        case 3:
            if ($input[2] === "0") return getStudentIdPrompt();
            return ($input[0] === "2") ? "CON Enter your reason for appeal:\n0. Back" : "END Invalid input.";

        case 4:
            if ($input[3] === "0") return getStudentModules(trim($input[1]), $conn);
            if ($input[0] !== "2") return "END Invalid input.";

            $studentId = trim($input[1]);
            $moduleIndex = intval($input[2]);
            $reason = trim($input[3]);

            try {
                return submitAppeal($studentId, $moduleIndex, $reason, $conn);
            } catch (Exception $e) {
                file_put_contents("ussd_debug.log", date('Y-m-d H:i:s') . " | submitAppeal error: {$e->getMessage()}\n", FILE_APPEND);
                return "END An error occurred. Please try again later.";
            }

        default:
            return "END Invalid request.";
    }
}

$response = isAdminUser($phoneNumber, $conn)
    ? handleAdminUssd($text, $conn)
    : handleStudentFlow(explode("*", $text), substr_count($text, "*") + 1, $conn);

header('Content-type: text/plain');
echo $response;
