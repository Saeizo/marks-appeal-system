<?php
function checkMarks($studentId, $conn) {
    $stmt = $conn->prepare("SELECT module_name, mark FROM marks WHERE student_id = ?");
    $stmt->execute([$studentId]);
    
    if ($stmt->rowCount() == 0) {
        return "END Error: Student ID not found.";
    }
    
    $response = "END Your Marks:\n";
    while ($row = $stmt->fetch()) {
        $response .= $row['module_name'] . ": " . $row['mark'] . "\n";
    }
    return $response;
}
?>
