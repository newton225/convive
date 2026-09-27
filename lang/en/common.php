<?php

return [
    'actions' => [
        'save' => 'Save',
        'cancel' => 'Cancel',
        'confirm' => 'Confirm',
        'close' => 'Close',
        'delete' => 'Delete',
        'edit' => 'Edit',
        'view' => 'View',
        'copy' => 'Copy',
        'copied' => 'Copied',
        'send' => 'Send',
        'accept' => 'Accept',
        'decline' => 'Decline',
        'continue' => 'Continue',
        'back' => 'Back',
    ],

    'states' => [
        'loading' => 'Loading',
        'saving' => 'Saving',
        'empty' => 'Nothing to show yet',
        'offline' => 'You are offline',
    ],

    'language' => [
        'label' => 'Language',
        'switch' => 'Change language',
    ],

    'pagination' => [
        'page_of' => 'Page :current of :last',
        'previous' => 'Previous',
        'next' => 'Next',
    ],

    'sample' => [
        'title' => 'Sample data',
        'description' => 'These figures are made up: the display is ready, the server for this screen comes in a later step.',
    ],

    'install' => [
        'title' => 'Install the app',
        'description' => 'Add Convive to your home screen to open it like an app, even on a weak network.',
        'action' => 'Install',
        'dismiss' => 'Later',
    ],

    'feedback' => [
        'forbidden' => "You don't have permission to do this. Ask the organisation's Owner for it.",
        'not_found' => 'This item no longer exists. It may have been deleted in the meantime: reload the page.',
        'session_expired' => 'Your session has expired. Reload the page, then try again.',
        'server_error' => 'Something went wrong on our side. Try again in a moment; if it persists, let the Convive team know.',
        'unexpected' => 'This action did not go through. Try again in a moment.',
        'network_error' => 'The network is not responding. Check your connection, then try again.',
    ],

    'rate_limit' => [
        'title' => 'Please wait a moment',
        'retry_in' => '{1} This action was repeated too often in a short time. Try again in 1 minute.|[2,*] This action was repeated too often in a short time. Try again in :minutes minutes.',
        'back' => 'Back to home',
    ],

    'pdf' => [
        'watermark' => 'Exported by :name on :date',
    ],
];
