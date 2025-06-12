<?php
function getStudentModules($studentId, $conn) {
    $stmt = $conn->prepare("SELECT module_name, mark FROM marks WHERE student_id = ?");
    $stmt->execute([$studentId]);

    if ($stmt->rowCount() == 0) {
        return "END No marks found for Student ID: $studentId.";
    }

    $response = "CON Select module to appeal:\n";
    $i = 1;
    while ($row = $stmt->fetch()) {
        $response .= "$i. " . $row['module_name'] . " (" . $row['mark'] . ")\n";
        $i++;
    }
    return $response;
}

function submitAppeal($studentId, $moduleIndex, $reason, $conn) {
    // Get module list for this student
    $stmt = $conn->prepare("SELECT module_name FROM marks WHERE student_id = ?");
    $stmt->execute([$studentId]);
    $modules = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($modules)) {
        return "END No modules found for student.";
    }

    // Ensure selected index is valid
    if (!isset($modules[$moduleIndex - 1])) {
        return "END Invalid module selection.";
    }

    $selectedModule = $modules[$moduleIndex - 1];

    // Insert appeal with status_id = 1 (Pending)
    $stmt = $conn->prepare("INSERT INTO appeals (student_id, module_name, reason, status_id) VALUES (?, ?, ?, 1)");
    $success = $stmt->execute([$studentId, $selectedModule, $reason]);

    if ($success) {
        return "END Your appeal for '$selectedModule' has been submitted.";
    } else {
        return "END Failed to submit your appeal. Please try again.";
    }
}
?>
