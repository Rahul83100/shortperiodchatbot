<?php
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\course_module_created',
        'callback'  => '\local_chatbot\observer::sync_on_change',
    ],
    [
        'eventname' => '\core\event\course_module_updated',
        'callback'  => '\local_chatbot\observer::sync_on_change',
    ],
];
