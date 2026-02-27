<?php
namespace local_chatbot\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Adhoc task to synchronize Moodle course materials with the Vector DB immediately.
 */
class sync_adhoc_task extends \core\task\adhoc_task {

    /**
     * Execute the task.
     */
    public function execute() {
        // Reuse the logic from the scheduled task
        $task = new \local_chatbot\task\sync_task();
        $task->execute();
    }
}
