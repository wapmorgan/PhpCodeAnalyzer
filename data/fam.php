<?php
return array(
    'functions' => array(
        'fam_cancel_monitor',
        'fam_close',
        'fam_monitor_collection',
        'fam_monitor_directory',
        'fam_monitor_file',
        'fam_next_event',
        'fam_open',
        'fam_pending',
        'fam_resume_monitor',
        'fam_suspend_monitor',
    ),
    'constants' => array(
        'FAMChanged',
        'FAMDeleted',
        'FAMStartExecuting',
        'FAMStopExecuting',
        'FAMCreated',
        'FAMMoved',
        'FAMAcknowledge',
        'FAMExists',
        'FAMEndExist',
    ),
    'description' => 'File Alteration Monitor',
    'before_php_version' => '5.1.0'
);