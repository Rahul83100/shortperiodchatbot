<?php
namespace local_chatbot;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observer for local_chatbot.
 */
class observer {

    /**
     * Triggered when a course module is created or updated.
     * Starts the sync task immediately.
     * 
     * @param \core\event\base $event
     */
    public static function sync_on_change($event) {
        $task = new \local_chatbot\task\sync_adhoc_task();
        \core\task\manager::queue_adhoc_task($task);
        
        // Force immediate execution for real-time feel
        try {
            $task->execute();
        } catch (\Exception $e) {
            // Silently fail in the UI, the adhoc task will retry in the background
            debugging("Instant sync error: " . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
