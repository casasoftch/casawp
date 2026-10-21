<?php
/** Standalone regression checks: php tests/remote-media.php */
define('ABSPATH', dirname(__DIR__) . '/');
define('CASASYNC_PLUGIN_DIR', ABSPATH);
define('CASASYNC_PLUGIN_URL', 'https://site.example/plugins/casawp/');
require_once CASASYNC_PLUGIN_DIR . 'classes/Plugin.php';
$metadata = $mime_types = [];
$options = ['casawp_use_casagateway_cdn' => true];
function get_post_meta($id, $key, $single = false) { return $GLOBALS['metadata'][$id][$key] ?? ''; }
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function get_post_mime_type($id) { return $GLOBALS['mime_types'][$id] ?? false; }
// WordPress requires an attached-file reference before checking the MIME type.
function wp_attachment_is_image($id) {
    return !empty($GLOBALS['metadata'][$id]['_wp_attached_file'])
        && strpos((string) get_post_mime_type($id), 'image/') === 0;
}
function check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
$plugin = (new ReflectionClass(\casawp\Plugin::class))->newInstanceWithoutConstructor();
$pdf = 'https://cdn.casasoft.com/media/provider-8474/property-1578973/c8289b681bdd7a3b0acbd32f245d8562.pdf';
$metadata[42727] = ['_is_remote' => 1, '_origin' => $pdf, '_casawp_import' => 1];
$mime_types[42727] = 'application/pdf';
$icon = ['https://site.example/wp-includes/images/media/document.svg', 48, 64, false];
$placeholder = CASASYNC_PLUGIN_URL . 'plugin-assets/global/img/pdf.svg';
foreach (['thumbnail', 'medium', 'full', [120, 90]] as $size) {
    check($plugin->remoteImageDownsize(false, 42727, $size) === false, 'A PDF URL must never become an image URL');
    check($plugin->modifyGetAttachmentImageSrc(false, 42727, $size, false) === false, 'Documents without previews must not pretend to have images');
    check($plugin->modifyGetAttachmentImageSrc($icon, 42727, $size, true) === [$placeholder, 48, 64, false], 'Remote PDFs need a recognizable placeholder for icon requests');
}
check($plugin->modifyGetAttachmentUrl('broken-local-path.pdf', 42727) === $pdf, 'The document link must continue opening the original working PDF');
$response = ['url' => $pdf, 'icon' => $icon[0], 'type' => 'application', 'subtype' => 'pdf', 'filename' => basename($pdf)];
$prepared = $plugin->prepareRemoteDocumentForJs($response, (object) ['ID' => 42727], false);
check($prepared === array_merge($response, ['icon' => $placeholder]), 'Media Library data must use the PDF placeholder while retaining document type, filename and URL');
$preview = ['https://site.example/uploads/pdf-preview.jpg', 150, 200, true];
check($plugin->modifyGetAttachmentImageSrc($preview, 42727, 'thumbnail', true) === $preview, 'Existing generated PDF previews must survive');
$response['sizes'] = ['thumbnail' => ['url' => $preview[0], 'width' => 150, 'height' => 200]];
check($plugin->prepareRemoteDocumentForJs($response, (object) ['ID' => 42727], ['sizes' => $response['sizes']])['sizes'] === $response['sizes'], 'Existing Media Library previews must survive');
$attributes = ['src' => $preview[0], 'srcset' => 'preview-2x.jpg 2x', 'sizes' => '150px'];
check($plugin->modifyRemoteImageAttributes($attributes, (object) ['ID' => 42727], 'thumbnail') === $attributes, 'Document previews must preserve their own image attributes');

// Previously imported local records served through the CDN also receive the fix.
unset($metadata[42727]['_is_remote']);
check($plugin->remoteImageDownsize(false, 42727, 'thumbnail') === false, 'Legacy CDN PDFs must not be treated as images');
check($plugin->prepareRemoteDocumentForJs($response, (object) ['ID' => 42727], false)['icon'] === $placeholder, 'Existing CDN PDFs must work without a new import');
$options['casawp_use_casagateway_cdn'] = false;
check($plugin->modifyGetAttachmentImageSrc($icon, 42727, 'thumbnail', true) === $icon, 'Local PDFs must retain native WordPress rendering');
check($plugin->prepareRemoteDocumentForJs($response, (object) ['ID' => 42727], false) === $response, 'Local PDF Media Library data must remain unchanged');
$metadata[42727]['_is_remote'] = 1;
check($plugin->prepareRemoteDocumentForJs($response, (object) ['ID' => 42727], false)['icon'] === $placeholder, 'Explicit remote records still need placeholders if the CDN setting changes');

// Images continue using their CDN variants; other plugins and document types retain their fallbacks.
$metadata[21] = ['_is_remote' => 1, '_origin' => str_replace('.pdf', '.jpg', $pdf)];
$mime_types[21] = 'image/jpeg';
check(!wp_attachment_is_image(21), 'The fixture must reproduce WordPress rejecting a CDN-only image with no attached-file metadata');
$image = $plugin->remoteImageDownsize(false, 21, 'thumbnail');
check($image && strpos($image[0], '.jpg') !== false && $image[1] === 240 && $image[2] === 180, 'Remote image thumbnail behavior must remain intact');
check($plugin->modifyGetAttachmentImageSrc(false, 21, 'thumbnail', false) === $image, 'Both image filters must agree on the remote thumbnail');
check(!isset($plugin->modifyRemoteImageAttributes($attributes, (object) ['ID' => 21], 'thumbnail')['srcset']), 'Remote images must continue dropping invalid local srcsets');
check($plugin->prepareRemoteDocumentForJs($response, (object) ['ID' => 21], false) === $response, 'Image Media Library data must remain unchanged');
$mime_types[22] = 'image/jpeg';
check($plugin->remoteImageDownsize($preview, 22, 'thumbnail') === $preview, 'Local image results from other filters must survive');
$metadata[23] = ['_is_remote' => 1, '_origin' => 'https://cdn.casasoft.com/media/document.docx'];
$mime_types[23] = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
check($plugin->remoteImageDownsize(false, 23, 'thumbnail') === false
    && $plugin->modifyGetAttachmentImageSrc($icon, 23, 'thumbnail', true) === $icon, 'Other remote documents must retain native MIME icons rather than broken image URLs');
check(simplexml_load_file(CASASYNC_PLUGIN_DIR . 'plugin-assets/global/img/pdf.svg') !== false, 'The PDF placeholder asset must be valid SVG');
echo "Remote media rendering checks passed.\n";
