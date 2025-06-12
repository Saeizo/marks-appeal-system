<?php
require_once '../config/db.php';
require_once '../controllers/marksController.php';
require_once '../controllers/appealsController.php';
require_once '../controllers/adminUssdController.php'; // Admin logic

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Accept both POST and GET
$sessionId   = $_POST["sessionId"] ?? $_GET["sessionId"] ?? '';
$phoneNumber = $_POST["phoneNumber"] ?? $_GET["phoneNumber"] ?? '';
$text        = $_POST["text"] ?? $_GET["text"] ?? '';

// Debug log (optional)
file_put_contents("ussd_debug.log", date('Y-m-d H:i:s') . " | text: $text | phone: $phoneNumber\n", FILE_APPEND);

// Check if the user is an admin
function isAdmin($phone, $conn) {
    $stmt = $conn->prepare("SELECT * FROM admins WHERE phone_number = ?");
    $stmt->execute([$phone]);
    return $stmt->rowCount() > 0;
}

// Admin route
if (isAdmin($phoneNumber, $conn)) {
    $response = handleAdminUssd($text, $conn);
    header('Content-type: text/plain');
    echo $response;
    exit;
}

// Student USSD flow
$input = explode("*", $text);
$level = count($input);
$response = "";

try {
    switch ($level) {
        case 1:
            switch ($input[0]) {
                case "":
                    $response  = "CON Welcome to the Marks Appeal System\n";
                    $response .= "1. Check my marks\n";
                    $response .= "2. Appeal my marks\n";
                    $response .= "3. Exit";
                    break;

                case "1":
                case "2":
                    $response = "CON Enter your Student ID:\n0. Back";
                    break;

                case "3":
                    $response = "END Thank you for using the Marks Appeal System.";
                    break;

                default:
                    $response = "END Invalid choice. Try again.";
                    break;
            }
            break;

        case 2:
            if ($input[1] === "0") {
                $response  = "CON Welcome to the Marks Appeal System\n";
                $response .= "1. Check my marks\n";
                $response .= "2. Appeal my marks\n";
                $response .= "3. Exit";
            } else {
                switch ($input[0]) {
                    case "1":
                        $studentId = trim($input[1]);
                        $response  = checkMarks($studentId, $conn);
                        break;

                    case "2":
                        $studentId = trim($input[1]);
                        $response  = getStudentModules($studentId, $conn);
                        break;

                    default:
                        $response = "END Invalid input.";
                        break;
                }
            }
            break;

        case 3:
            if ($input[2] === "0") {
                $response = "CON Enter your Student ID:\n0. Back";
            } elseif ($input[0] === "2") {
                $response = "CON Enter your reason for appeal:\n0. Back";
            } else {
                $response = "END Invalid input.";
            }
            break;

        case 4:
            if ($input[3] === "0") {
                $response = getStudentModules(trim($input[1]), $conn);
            } elseif ($input[0] === "2") {
                $studentId   = trim($input[1]);
                $moduleIndex = intval($input[2]);
                $reason      = trim($input[3]);

                try {
                    $response = submitAppeal($studentId, $moduleIndex, $reason, $conn);
                } catch (Exception $e) {
                    file_put_contents("ussd_debug.log", date('Y-m-d H:i:s') . " | submitAppeal error: " . $e->getMessage() . "\n", FILE_APPEND);
                    $response = "END An error occurred while submitting your appeal. Please try again later.";
                }
            } else {
                $response = "END Invalid input.";
            }
            break;

        default:
            $response = "END Invalid request.";
            break;
    }
} catch (Exception $e) {
    file_put_contents("ussd_debug.log", date('Y-m-d H:i:s') . " | General error: " . $e->getMessage() . "\n", FILE_APPEND);
    $response = "END An unexpected error occurred. Please try again later.";
}

// Output USSD response
header('Content-type: text/plain');
echo $response;
