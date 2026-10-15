/// What task attachments, documents and absence orders accept — the same
/// list as the backend's `config/uploads.php`, so the picker never offers
/// a file the server will refuse.
const documentExtensions = [
  'pdf', 'jpg', 'jpeg', 'png', 'webp',
  'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'txt', 'csv',
];
