<?php
function handleAdminUssd($text, $conn) {
    $input = explode("*", $text);
    $level = count($input);
    $choice = $input[0] ?? "";

    // Handle "Back" at any level
    if (in_array("0", $input)) {
        return "CON Welcome Admin.\n1. View Appeals\n2. Update Appeal Status\n3. Update Marks\n4. Exit";
    }

    switch ($level) {
        case 1:
            if ($choice === "") {
                return "CON Welcome Admin.\n1. View Appeals\n2. Update Appeal Status\n3. Update Marks\n4. Exit";
            }

            switch ($choice) {
                case "1":
                    $stmt = $conn->query("
                        SELECT a.appeal_id, a.student_id, a.module_name, a.reason, m.mark 
                        FROM appeals a
                        LEFT JOIN marks m ON a.student_id = m.student_id AND a.module_name = m.module_name
                        ORDER BY a.created_at DESC
                        LIMIT 3
                    ");
                    $msg = "CON Recent Appeals:\n";
                    while ($row = $stmt->fetch()) {
                        $msg .= "{$row['appeal_id']}. {$row['student_id']} - {$row['module_name']}\n";
                        $msg .= "Mark: {$row['mark']} | Reason: {$row['reason']}\n\n";
                    }
                    return $msg . "0. Back";

                case "2":
                    return "CON Enter Appeal ID to update:";

                case "3":
                    return "CON Enter Student ID to update marks:";

                case "4":
                    return "END Thank you, Admin.";

                default:
                    return "END Invalid choice.";
            }

        case 2:
            if ($choice == "2") {
                $appealId = intval($input[1]);
                return "CON Choose new status for Appeal $appealId:\n1. Pending\n2. Under Review\n3. Resolved\n0. Back";
            } elseif ($choice == "3") {
                $studentId = $input[1];

                $stmt = $conn->prepare("SELECT module_name FROM marks WHERE student_id = ?");
                $stmt->execute([$studentId]);
                if ($stmt->rowCount() == 0) {
                    return "END Student ID not found.";
                }

                $modules = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $msg = "CON Select module to update:\n";
                foreach ($modules as $index => $module) {
                    $msg .= ($index + 1) . ". $module\n";
                }
                return $msg . "0. Back";
            }
            break;

        case 3:
            if ($choice == "2") {
                $appealId = intval($input[1]);
                $statusId = intval($input[2]);
                if (!in_array($statusId, [1, 2, 3])) {
                    return "END Invalid status choice.";
                }

                $stmt = $conn->prepare("UPDATE appeals SET status_id = ? WHERE appeal_id = ?");
                $stmt->execute([$statusId, $appealId]);
                return "END Appeal $appealId updated successfully.";
            } elseif ($choice == "3") {
                return "CON Enter new mark:";
            }
            break;

        case 4:
            if ($choice == "3") {
                $studentId = $input[1];
                $moduleIndex = intval($input[2]) - 1;
                $newMark = intval($input[3]);

                $stmt = $conn->prepare("SELECT module_name FROM marks WHERE student_id = ?");
                $stmt->execute([$studentId]);
                $modules = $stmt->fetchAll(PDO::FETCH_COLUMN);

                if (!isset($modules[$moduleIndex])) {
                    return "END Invalid module selected.";
                }

                $moduleName = $modules[$moduleIndex];

                $updateStmt = $conn->prepare("UPDATE marks SET mark = ? WHERE student_id = ? AND module_name = ?");
                $updateStmt->execute([$newMark, $studentId, $moduleName]);

                return "END Mark updated successfully for $moduleName.";
            }
            break;
    }

    return "END Invalid request.";
}
?>
