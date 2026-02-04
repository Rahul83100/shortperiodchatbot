<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = array(
    'local/chatbot:upload' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => array(
            'manager' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW
        )
    )
);
