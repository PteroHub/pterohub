<?php
return [
    // Disabled by default; opt in only after verifying SMTP and queue worker.
    'ticket_emails' => env('PTEROHUB_TICKET_EMAILS', false),
];
