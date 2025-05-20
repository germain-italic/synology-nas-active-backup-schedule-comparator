# NAS Backup Task Comparison Tool

This tool compares Active Backup for Business (ABB) tasks between two Synology NAS devices. It verifies that tasks on NAS1 also exist on NAS2, checks if they are scheduled 12 hours apart, and provides a 24-hour timeline visualization of all backup tasks.

## Features

- Task name comparison between NAS1 and NAS2
- Schedule verification (tasks should be 12 hours apart)
- Alert generation for misconfigured task schedules
- 24-hour timeline visualization
- Read-only operation (no modifications to NAS configurations)

## Prerequisites

### Synology NAS

- SSH access to both NAS devices (preferably with key-based authentication)
- The Active Backup for Business package installed on both NAS devices
- Tested with DSM 7.2 and ABB 2.7

### PHP Version

- Command-line access to PHP installed on the machine running the script
- Tested with PHP 8.2.28 (cli).
- You can run the script on the NAS or on your own computer

```
php nas_backup_compare.php
```

## Configuration

Before running the script, update the .env configuration file with your NAS information:

- Hostnames or IP addresses
- SSH username
- Path to SSH key for passwordless authentication (you will be prompted for password otherwise)

## Output Example

```
NAS Backup Task Comparison Tool
-----------------------------

Connecting to nas1.local...
✓ Retrieved 5 tasks from nas1.local
Connecting to nas2.local...
✓ Retrieved 4 tasks from nas2.local

Task Name Comparison:
✗ 1 tasks from NAS1 are missing in NAS2:
  - Server_Backup

Schedule Verification (12-hour Offset):
✓ Task 'Workstations_Backup' is properly scheduled
✗ Task 'Virtual_Machines' is NOT scheduled 12 hours apart
  NAS1: daily at 23:00
  NAS2: daily at 18:00
✓ Task 'File_Server' is properly scheduled
✓ Task 'Database_Backup' is properly scheduled

24-Hour Backup Task Timeline:
Hour: 00 01 02 03 04 05 06 07 08 09 10 11 12 13 14 15 16 17 18 19 20 21 22 23 
      -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- 
NAS1:                         N1                                     ##     N1 
NAS2:                               N1             N1 N1       N2     N1      

Legend:
  N1/N2 - Task running on NAS1/NAS2
  ## - Multiple tasks at the same hour

Summary:
⚠ Found 1 tasks with scheduling issues
```

## Safety Considerations

This tool operates in read-only mode and does not make any changes to your NAS configurations. It only queries the ABB database to retrieve task information.

## Troubleshooting

- If you encounter connection issues, verify SSH connectivity to both NAS devices
- Ensure the provided SSH key has proper permissions (chmod 600)
- Verify the database path is correct for your Synology DSM version