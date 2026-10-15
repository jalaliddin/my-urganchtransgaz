<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Attachment file types
    |--------------------------------------------------------------------------
    |
    | What task attachments, employee documents and absence orders accept:
    | scans and photos plus the office files people actually work with.
    | Checked by extension, not by sniffing the content — content sniffing
    | reads many valid .xlsx/.docx files (Google Sheets, 1C exports) as a
    | plain zip and rejects them. That's safe here because every download
    | is sent as an attachment, never rendered inline, and Laravel's
    | extension rule still refuses PHP files.
    |
    */

    'document_extensions' => [
        'pdf', 'jpg', 'jpeg', 'png', 'webp',
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'txt', 'csv',
    ],

];
