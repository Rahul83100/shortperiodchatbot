<?php
namespace local_chatbot;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observer for local_chatbot.
 */
class observer
{

    /**
     * Triggered when a course module is created or updated.
     * Starts the sync task immediately.
     * 
     * @param \core\event\base $event
     */
    public static function sync_on_change($event)
    {
        $task = new \local_chatbot\task\sync_adhoc_task();
        \core\task\manager::queue_adhoc_task($task);

        // Real-time sync: execute immediately but silently (suppress mtrace output)
        $sync_task = new \local_chatbot\task\sync_task();
        $sync_task->sync_files(false);
    }
}
