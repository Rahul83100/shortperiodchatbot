<?php
namespace local_chatbot\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task to synchronize Moodle course materials with the Vector DB.
 */
class sync_task extends \core\task\scheduled_task
{

    /**
     * Get a descriptive name for this task.
     *
     * @return string
     */
    public function get_name()
    {
        return get_string('synctask', 'local_chatbot');
    }

    /**
     * Execute the task.
     */
    public function execute()
    {
        $this->sync_files(true);
    }

    /**
     * Synchronize files with the Vector DB backend.
     *
     * @param bool $trace Whether to output progress messages (mtrace).
     */
    public function sync_files($trace = true)
    {
        global $DB, $CFG;

        if ($trace) {
            mtrace("Starting Chatbot Vector Sync...");
        }

        // Robust SQL to find PDFs and their associated Course IDs
        $sql = "SELECT f.id, f.contenthash, f.pathnamehash, f.filename, f.contextid, 
                       CASE 
                           WHEN ctx.contextlevel = 50 THEN ctx.instanceid 
                           WHEN ctx.contextlevel = 70 THEN cm.course 
                           ELSE NULL 
                       END as courseid
                FROM {files} f
                JOIN {context} ctx ON f.contextid = ctx.id
                LEFT JOIN {course_modules} cm ON ctx.contextlevel = 70 AND ctx.instanceid = cm.id
                WHERE f.mimetype = 'application/pdf'
                  AND f.filename != '.'
                  AND (ctx.contextlevel = 50 OR (ctx.contextlevel = 70 AND cm.course IS NOT NULL))";

        try {
            $files = $DB->get_records_sql($sql);
        } catch (\Exception $e) {
            if ($trace) {
                mtrace("Database error during file lookup: " . $e->getMessage());
            }
            return;
        }

        if (!$files) {
            if ($trace) {
                mtrace("No PDF files found to sync.");
            }
            return;
        }

        if ($trace) {
            mtrace("Found " . count($files) . " candidate PDF files.");
        }

        $fs = get_file_storage();

        foreach ($files as $file) {
            try {
                if (empty($file->courseid)) {
                    if ($trace) {
                        mtrace("Skipping {$file->filename}: Course ID is empty.");
                    }
                    continue;
                }

                // Check if already synced or currently syncing using pathnamehash
                $synced = $DB->get_record('local_chatbot_sync', ['pathnamehash' => $file->pathnamehash, 'courseid' => $file->courseid]);

                if ($synced && ($synced->syncstatus == 1 || $synced->syncstatus == 3)) {
                    if ($trace) {
                        mtrace("Skipping {$file->filename}: Already synced or in progress (Status: {$synced->syncstatus}).");
                    }
                    continue;
                }

                // Mark as "syncing" (3) to prevent parallel uploads
                $record = new \stdClass();
                $record->filehash = $file->contenthash;
                $record->pathnamehash = $file->pathnamehash;
                $record->courseid = $file->courseid;
                $record->syncstatus = 3; // Syncing
                $record->timemodified = time();

                if ($synced) {
                    $record->id = $synced->id;
                    $DB->update_record('local_chatbot_sync', $record);
                } else {
                    $record->id = $DB->insert_record('local_chatbot_sync', $record);
                }

                if ($trace) {
                    mtrace("Syncing file: {$file->filename} (Course ID: {$file->courseid}, Hash: " . substr($file->contenthash, 0, 8) . ")...");
                }

                $fileinstance = $fs->get_file_by_id($file->id);

                if (!$fileinstance) {
                    if ($trace) {
                        mtrace("Error: Could not retrieve file instance for ID {$file->id}");
                    }
                    $record->syncstatus = 2; // Failed
                    $DB->update_record('local_chatbot_sync', $record);
                    continue;
                }

                $content = $fileinstance->get_content();

                if ($this->send_to_backend($file->courseid, $file->filename, $content, $trace)) {
                    if ($trace) {
                        mtrace("Successfully synced {$file->filename}");
                    }

                    $record->syncstatus = 1; // Synced
                    $record->timemodified = time();
                    $DB->update_record('local_chatbot_sync', $record);
                } else {
                    if ($trace) {
                        mtrace("Failed to sync {$file->filename}");
                    }
                    $record->syncstatus = 2; // Failed
                    $record->timemodified = time();
                    $DB->update_record('local_chatbot_sync', $record);
                }
            } catch (\Exception $e) {
                if ($trace) {
                    mtrace("Unexpected error processing {$file->filename}: " . $e->getMessage());
                }
            }
        }

        if ($trace) {
            mtrace("Chatbot Vector Sync complete.");
        }
    }

    /**
     * Send file content to the Vector DB backend.
     * Uses native PHP cURL to bypass Moodle's security blocks on internal URLs.
     */
    protected function send_to_backend($courseid, $filename, $content, $trace = true)
    {
        $url = "http://103.105.225.150:8001/api/upload";

        $tmpdir = make_temp_directory('chatbot');
        $tmpfile = tempnam($tmpdir, 'sync');
        file_put_contents($tmpfile, $content);

        $ch = curl_init();

        $data = [
            'index_id' => $courseid,
            'file' => new \CURLFile($tmpfile, 'application/pdf', $filename)
        ];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        // Ensure we don't block on SSL if the user eventually uses HTTPS locally
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);
        @unlink($tmpfile);

        if ($http_code == 200 || $http_code == 202) {
            return true;
        }

        if ($trace) {
            mtrace("Native cURL error (HTTP {$http_code}): " . $response . " " . $error);
        }
        return false;
    }
}
