# Purge old assignments (local_purgeoldassignments)

## What is this?
This plugin allows a site manager to delete historical files stored within an assignment, at
intervals from **1 up to 10 years**. Note: it deletes the files but does not remove grades,
submission records or feedback comments.

3 standard file areas are currently supported:
* `assignfeedback_editpdf` (annotated PDFs; editpdf stamps are always kept)
* `assignfeedback_file`
* `assignsubmission_file`

Purging files can be done in two ways:
- **Manual purge**: schedules a one-off ad hoc task for the selected file area and age
  (1 to 10 years). The purge happens the next time cron runs ad hoc tasks.
- **Ongoing automated purge**: can be enabled per file area, each with its own age (1 to 10 years).
  When the scheduled task (`\local_purgeoldassignments\task\purge_old_assignments`, daily at 04:00
  by default) runs, each configured file area is checked and files older than the set age are purged.

This was developed for a specific use-case and there is room for improvement - pull requests are always welcome.

## Requirements

| Plugin version | Moodle versions        | PHP                         |
|----------------|------------------------|-----------------------------|
| 2026112200     | 4.5 to 5.3             | As required by Moodle core  |
| 2025051300     | 3.4 to 4.5 (legacy)    | As required by Moodle core  |

Moodle 5.1 and later use the new `public/` directory layout. Install the plugin into
`public/local/purgeoldassignments` on those versions, or `local/purgeoldassignments` on Moodle 4.5 and 5.0.

## Installation
1. Copy this repository into `local/purgeoldassignments` (Moodle 4.5 / 5.0) or
   `public/local/purgeoldassignments` (Moodle 5.1+).
2. Visit *Site administration > Notifications*, or run `php admin/cli/upgrade.php`.
3. Make sure cron is running, as both purge modes run from tasks.

## Usage
1. Open an assignment and go to *More > Purge old assignments* (the link is added to the
   assignment's settings navigation).
2. For each file area you can see the total size and how much is older than each number of years.
   - Click *manual purge* next to an age to queue a one-off purge (a confirmation is shown first).
   - Tick *Enable ongoing purge*, pick an age in *Set for files older than* and click
     *Save changes* to purge continuously.

### Permissions
`local/purgeoldassignments:purgeassignments` (module context, `RISK_DATALOSS`) is required to view
the page and to purge. It is only granted to the *Manager* archetype by default.

### Command line
Files can also be purged from the command line, which runs immediately:

```bash
sudo -u www-data php local/purgeoldassignments/cli/purge.php --cmid=12345 --component=assignsubmission_file --minageyears=2
```

Use `--help` for all options. On Moodle 5.1+ the path is `public/local/purgeoldassignments/cli/purge.php`.

## Privacy
The plugin stores the ID of the user who last changed an ongoing purge schedule. It implements the
Moodle privacy API: that data is included in data exports, and deletion requests anonymise the user
reference while keeping the schedule (which is site configuration).

## Security notes
- All actions need login, the capability above in the assignment's context, and a valid session key.
  Purges are confirmed and submitted by POST.
- The component, age and context are validated on the page and again in the purge function, which
  only deletes files from the 3 allowed components inside an assignment module context.

## Changelog
### 2026112200
- Supports Moodle 4.5 to 5.3 (needs Moodle 4.5 or later).
- Purge ages extended from 1–3 years to 1–10 years (database field widened, unique index added).
- Privacy API provider for the stored `usermodified` data (was a null provider).
- Hardening: stricter validation of component, age and context; parameterised SQL;
  `require_sesskey()`; duplicate one-off purge tasks are not queued.
- UI: all components are listed (so schedules can be set before files exist), translatable labels,
  accessible checkbox/select labels, page title.
- PHPUnit tests and PHPUnit enabled in CI.

## Example usage
![image](https://github.com/user-attachments/assets/bf20c6cc-3a9c-498a-9c88-a247c791159b)
