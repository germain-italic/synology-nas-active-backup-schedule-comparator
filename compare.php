<?php
/**
 * NAS Backup Task Comparison Tool
 * 
 * This script compares Active Backup for Business tasks between two Synology NAS devices,
 * verifies proper scheduling (12 hours apart), and generates a timeline visualization.
 * 
 * Usage: php nas_backup_compare.php
 */

// Load environment variables from .env file
function loadEnv() {
    if (!file_exists('.env')) {
        die("Error: .env file not found\n");
    }
    
    $lines = file('.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

// Load environment variables
loadEnv();

// Configuration from environment variables
$config = [
    'nas1' => [
        'host' => $_ENV['NAS1_HOST'],
        'user' => $_ENV['NAS1_USER'],
        'ssh_key' => $_ENV['NAS1_SSH_KEY'],
        'db_path' => $_ENV['NAS1_DB_PATH'],
    ],
    'nas2' => [
        'host' => $_ENV['NAS2_HOST'],
        'user' => $_ENV['NAS2_USER'],
        'ssh_key' => $_ENV['NAS2_SSH_KEY'],
        'db_path' => $_ENV['NAS2_DB_PATH'],
    ],
];

// ANSI color codes for output formatting
define('RESET', "\033[0m");
define('RED', "\033[31m");
define('GREEN', "\033[32m");
define('YELLOW', "\033[33m");
define('BLUE', "\033[34m");
define('MAGENTA', "\033[35m");
define('CYAN', "\033[36m");
define('BOLD', "\033[1m");

/**
 * Main function
 */
function main() {
    global $config;
    
    echo BOLD . "NAS Backup Task Comparison Tool\n" . RESET;
    echo "-----------------------------\n\n";
    
    // Get tasks from both NAS systems
    $nas1Tasks = getNasTasks($config['nas1']);
    $nas2Tasks = getNasTasks($config['nas2']);

    // print_r($nas1Tasks);
    // print_r($nas2Tasks);

    if ($nas1Tasks === false || $nas2Tasks === false) {
        echo RED . "Could not retrieve tasks from one or both NAS devices. Exiting.\n" . RESET;
        exit(1);
    }
    
    // Compare tasks and verify schedules
    compareTaskNames($nas1Tasks, $nas2Tasks);
    $scheduleIssues = verifyTaskSchedules($nas1Tasks, $nas2Tasks);
    
    // Generate timeline visualization
    generateTimeline($nas1Tasks, $nas2Tasks);

    // Generate hourly task breakdown
    generateHourlyTasksTable($nas1Tasks, $nas2Tasks);

    // Generate task schedule overview
    generateTaskScheduleTable($nas1Tasks, $nas2Tasks);
    
    [$ok, $mismatch, $missing] = generateTaskScheduleTable($nas1Tasks, $nas2Tasks);

    echo "\nSummary:\n";
    echo GREEN . "✓ {$ok} tasks properly scheduled (12h offset)\n" . RESET;
    echo YELLOW . "⚠ {$mismatch} tasks with incorrect time offset\n" . RESET;
    echo RED . "✗ {$missing} tasks missing on one NAS\n" . RESET;

}

/**
 * Connect to a NAS device and retrieve Active Backup for Business tasks
 */
function getNasTasks($nasConfig) {
    echo "Connecting to " . BOLD . $nasConfig['host'] . RESET . "...\n";
    
    // Construct command to dump SQLite database contents to stdout over SSH
    $command = sprintf(
        'ssh -i %s %s@%s "sqlite3 -json %s \'SELECT task_name, task_id, sched_content FROM task_table WHERE backup_type = 4\'"',
        $nasConfig['ssh_key'],
        $nasConfig['user'],
        $nasConfig['host'],
        $nasConfig['db_path']
    );
    
    // Execute the command and capture output
    exec($command, $output, $returnCode);
    
    if ($returnCode !== 0) {
        echo RED . "Error connecting to {$nasConfig['host']} or running SQLite query\n" . RESET;
        return false;
    }
    
    // Parse the JSON output into an array of tasks
    $tasks = [];
    $jsonData = json_decode(implode('', $output), true);
    
    foreach ($jsonData as $task) {
        $scheduleInfo = json_decode($task['sched_content'], true);
        if (!$scheduleInfo) continue;
        
        $tasks[$task['task_name']] = [
            'task_name' => $task['task_name'],
            'task_id' => $task['task_id'],
            'schedule' => [
                'type' => $scheduleInfo['repeat_type'],
                'hour' => $scheduleInfo['run_hour'],
                'minute' => $scheduleInfo['run_min'],
                'weekdays' => isset($scheduleInfo['run_weekday']) ? $scheduleInfo['run_weekday'] : [],
            ],
            'formatted_time' => sprintf('%02d:%02d', $scheduleInfo['run_hour'], $scheduleInfo['run_min']),
        ];
    }
    
    echo GREEN . "✓ Retrieved " . count($tasks) . " tasks from {$nasConfig['host']}\n" . RESET;
    return $tasks;
}

/**
 * Compare task names between NAS1 and NAS2
 */
function compareTaskNames($nas1Tasks, $nas2Tasks) {
    echo "\n" . BOLD . "Task Name Comparison:\n" . RESET;
    
    $missingTasks = [];
    
    // Check if all NAS1 tasks exist in NAS2
    foreach ($nas1Tasks as $taskName => $task) {
        if (!isset($nas2Tasks[$taskName])) {
            $missingTasks[] = $taskName;
        }
    }
    
    if (empty($missingTasks)) {
        echo GREEN . "✓ All tasks from NAS1 exist in NAS2\n" . RESET;
    } else {
        echo RED . "✗ " . count($missingTasks) . " tasks from NAS1 are missing in NAS2:\n" . RESET;
        foreach ($missingTasks as $taskName) {
            echo "  - $taskName\n";
        }
    }
}

/**
 * Verify that tasks are scheduled 12 hours apart
 */
function verifyTaskSchedules($nas1Tasks, $nas2Tasks) {
    echo "\n" . BOLD . "Schedule Verification (12-hour Offset):\n" . RESET;
    
    $issues = [];
    
    foreach ($nas1Tasks as $taskName => $nas1Task) {
        if (!isset($nas2Tasks[$taskName])) continue;
        
        $nas2Task = $nas2Tasks[$taskName];
        
        // Convert times to minutes since midnight
        $nas1Minutes = ($nas1Task['schedule']['hour'] * 60) + $nas1Task['schedule']['minute'];
        $nas2Minutes = ($nas2Task['schedule']['hour'] * 60) + $nas2Task['schedule']['minute'];
        
        // Calculate time difference (considering 24-hour cycle)
        $timeDiff = abs($nas1Minutes - $nas2Minutes);
        $timeDiff = min($timeDiff, 1440 - $timeDiff); // Get shortest time difference
        
        // Check if approximately 12 hours apart (720 minutes ± 30 minutes)
        if (abs($timeDiff - 720) <= 30) {
            echo GREEN . "✓ Task '$taskName' is properly scheduled\n" . RESET;
            echo "  NAS1: {$nas1Task['formatted_time']} ({$nas1Task['schedule']['type']})\n";
            echo "  NAS2: {$nas2Task['formatted_time']} ({$nas2Task['schedule']['type']})\n";
        } else {
            echo RED . "✗ Task '$taskName' is NOT scheduled 12 hours apart\n" . RESET;
            echo "  NAS1: {$nas1Task['formatted_time']} ({$nas1Task['schedule']['type']})\n";
            echo "  NAS2: {$nas2Task['formatted_time']} ({$nas2Task['schedule']['type']})\n";
            $issues[] = $taskName;
        }
    }
    
    return $issues;
}

/**
 * Generate a 24-hour timeline visualization of the tasks
 */
function generateTimeline(array $nas1Tasks, array $nas2Tasks): void {
    echo "\n24-Hour Backup Task Timeline (Task Count Per Hour):\n";
    echo "Hour :";
    for ($i = 0; $i < 24; $i++) {
        echo " " . str_pad($i, 2, "0", STR_PAD_LEFT);
    }
    echo "\n";

    $countPerHour = function (array $tasks): array {
        $counts = array_fill(0, 24, 0);
        foreach ($tasks as $task) {
            if (!is_array($task) || !isset($task['formatted_time'])) {
                continue;
            }
            $hour = (int)substr($task['formatted_time'], 0, 2);
            $counts[$hour]++;
        }
        return $counts;
    };

    $nas1Counts = $countPerHour($nas1Tasks);
    $nas2Counts = $countPerHour($nas2Tasks);

    echo "NAS1 :";
    foreach ($nas1Counts as $count) {
        echo "  " . $count;
    }
    echo "\nNAS2 :";
    foreach ($nas2Counts as $count) {
        echo "  " . $count;
    }
    echo "\n";
}


/**
 * Generate a table of tasks scheduled by hour
 */
function generateHourlyTasksTable(array $nas1Tasks, array $nas2Tasks): void {
    echo "\nHourly Task Breakdown:\n";

    $colWidth = 50;
    echo str_pad("Hour", 6) . "| " . str_pad("NAS1 Tasks", $colWidth) . "| NAS2 Tasks\n";
    echo str_repeat("-", 6 + 2 + $colWidth + 2 + 40) . "\n";

    $byHour = function (array $tasks): array {
        $hourMap = [];
        foreach ($tasks as $name => $task) {
            if (!is_array($task) || !isset($task['formatted_time'])) {
                continue;
            }
            $hour = (int)substr($task['formatted_time'], 0, 2);
            $hourMap[$hour][] = $name;
        }
        return $hourMap;
    };

    $nas1ByHour = $byHour($nas1Tasks);
    $nas2ByHour = $byHour($nas2Tasks);

    for ($h = 0; $h < 24; $h++) {
        $label = str_pad(sprintf("%02d", $h), 6);

        $col1 = isset($nas1ByHour[$h]) ? implode(", ", $nas1ByHour[$h]) : "";
        $col2 = isset($nas2ByHour[$h]) ? implode(", ", $nas2ByHour[$h]) : "";

        // Tronque le contenu si trop long
        $col1 = mb_strimwidth($col1, 0, $colWidth - 3, '...');
        echo $label . "| " . str_pad($col1, $colWidth) . "| " . $col2 . "\n";
    }
}


// Generate a task schedule table for NAS1 and NAS2
function generateTaskScheduleTable(array $nas1Tasks, array $nas2Tasks): array {
    echo "\nTask Schedule Overview:\n";

    $nameWidth = 30;
    echo str_pad("Task", $nameWidth) . "| NAS1               | NAS2               | Status\n";
    echo str_repeat("-", $nameWidth + 1 + 19 + 1 + 19 + 9) . "\n";

    $ok = $mismatch = $missing = 0;

    $allTaskNames = array_unique(array_merge(array_keys($nas1Tasks), array_keys($nas2Tasks)));
    sort($allTaskNames, SORT_NATURAL | SORT_FLAG_CASE);

    foreach ($allTaskNames as $taskName) {
        $nas1 = isset($nas1Tasks[$taskName]) ? sprintf("%s (%s)", $nas1Tasks[$taskName]['formatted_time'], $nas1Tasks[$taskName]['schedule']['type']) : "-";
        $nas2 = isset($nas2Tasks[$taskName]) ? sprintf("%s (%s)", $nas2Tasks[$taskName]['formatted_time'], $nas2Tasks[$taskName]['schedule']['type']) : "-";

        if (!isset($nas1Tasks[$taskName]) || !isset($nas2Tasks[$taskName])) {
            $statusSymbol = "✗";
            $color = "\033[31m"; // red
            $missing++;
        } else {
            $h1 = (int)substr($nas1Tasks[$taskName]['formatted_time'], 0, 2);
            $m1 = (int)substr($nas1Tasks[$taskName]['formatted_time'], 3, 2);
            $h2 = (int)substr($nas2Tasks[$taskName]['formatted_time'], 0, 2);
            $m2 = (int)substr($nas2Tasks[$taskName]['formatted_time'], 3, 2);
            $delta = abs(($h1 * 60 + $m1) - ($h2 * 60 + $m2));

            if (abs($delta - 720) <= 5) {
                $statusSymbol = "✓";
                $color = "\033[32m";
                $ok++;
            } else {
                $statusSymbol = "⚠";
                $color = "\033[33m";
                $mismatch++;
            }
        }

        echo str_pad($taskName, $nameWidth)
           . "| " . str_pad($nas1, 19)
           . "| " . str_pad($nas2, 19)
           . "|  {$color}{$statusSymbol}\033[0m\n";
    }

    // echo "\n> ". GREEN . "✓ OK (12h offset)    " . RESET;
    // echo YELLOW . "⚠ mismatch offset    " . RESET;
    // echo RED . "✗ task missing\n" . RESET;

    return [$ok, $mismatch, $missing];
}


// Run the main function
main();