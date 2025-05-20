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
$ php compare.php 
NAS Backup Task Comparison Tool
-----------------------------

Connecting to nas-a.local...
✓ Retrieved 22 tasks from nas-a.local
Connecting to nas-b.local...
✓ Retrieved 23 tasks from nas-b.local

Task Name Comparison:
✗ 2 tasks from NAS A are missing in NAS B:
  - backup-web-prod
  - backup-mobile-app

Schedule Verification (12-hour Offset):
✓ Task 'intranet' is properly scheduled
  NAS A: 20:00 (Weekly)
  NAS B: 08:00 (Weekly)
✓ Task 'mail' is properly scheduled
  NAS A: 23:30 (Weekly)
  NAS B: 11:30 (Weekly)
✗ Task 'forum' is NOT scheduled 12 hours apart
  NAS A: 15:00 (Weekly)
  NAS B: 17:30 (Weekly)
...

24-Hour Backup Task Timeline (Task Count Per Hour):
Hour : 00 01 02 03 04 05 06 07 08 09 10 11 12 13 14 15 16 17 18 19 20 21 22 23
NAS A:  1  0  1  2  0  2  3  0  2  2  0  1  0  1  1  2  1  0  0  0  1  0  1  1
NAS B:  0  1  1  1  1  0  0  0  1  0  1  1  1  0  1  2  1  2  3  0  2  2  0  2

Hourly Task Breakdown:
Hour  | NAS A Tasks                                      | NAS B Tasks
-----------------------------------------------------------------------------------
00    | www-backup                                       | 
01    |                                                  | cloud-img-1
02    | finance-db                                       | cloud-img-2
03    | backup-web-prod, aws-export                     | cloud-img-3
...
23    | mail                                             | mobile-app-old, mobile-app-v2

Task Schedule Overview:
Task                         | NAS A             | NAS B             | Status
-------------------------------------------------------------------------------
aws-export                   | 03:30 (Weekly)    | 15:30 (Weekly)    | ✓
cloud-img-1                  | 13:13 (Weekly)    | 01:11 (Weekly)    | ✓
cloud-img-2                  | 14:14 (Weekly)    | 02:22 (Weekly)    | ⚠
cloud-img-3                  | 15:15 (Weekly)    | 03:33 (Weekly)    | ⚠
cloud-img-4                  | 16:16 (Weekly)    | 04:44 (Weekly)    | ⚠
forum                        | 15:00 (Weekly)    | 17:30 (Weekly)    | ⚠
intranet                     | 20:00 (Weekly)    | 08:00 (Weekly)    | ✓
mail                         | 23:30 (Weekly)    | 11:30 (Weekly)    | ✓
mobile-app                   | 11:00 (Weekly)    | -                 | ✗
mobile-app-old               | -                 | 23:00 (Weekly)    | ✗
...

> ✓ OK (12h offset)    ⚠ mismatch offset    ✗ task missing

Summary:
✓ 15 tasks properly scheduled
⚠ 5 tasks with incorrect time offset
✗ 5 tasks missing on one NAS
```


## Safety Considerations

This tool operates in read-only mode and does not make any changes to your NAS configurations. It only queries the ABB database to retrieve task information.

## Troubleshooting

- If you encounter connection issues, verify SSH connectivity to both NAS devices
- Ensure the provided SSH key has proper permissions (chmod 600)
- Verify the database path is correct for your Synology DSM version

## Inspirations

- https://github.com/righter83/checkmk-synology-activebackup/tree/main
- https://github.com/Glonki/Zabbix-SynologyABB
- https://github.com/WAdama/nas_ab_status