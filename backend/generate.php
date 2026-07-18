<?php
/**
 * generate.php
 * ---------------------------------------------------------
 * Receives the landing-page request form (multipart/form-data),
 * fills the HTML/PHP templates with the submitted content and
 * images, zips the result together with the static /assets
 * folder, and returns a JSON response with a download link.
 *
 * Host this file + the /templates, /assets, /output folders
 * on any normal PHP hosting (PHP 7.4+, ZipArchive extension).
 * ---------------------------------------------------------
 */

header('Content-Type: application/json');

// ---- CORS: allow the web app (hosted elsewhere) to call this ----
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

function fail($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}

// ---------------------------------------------------------
// 1. Read and validate incoming fields
// ---------------------------------------------------------
$projectName = trim($_POST['projectName'] ?? '');
$statusBadge = trim($_POST['statusBadge'] ?? '');
$priceRange  = trim($_POST['priceRange'] ?? '');
$address     = trim($_POST['address'] ?? '');
$landArea    = trim($_POST['landArea'] ?? '');
$totalUnits  = trim($_POST['totalUnits'] ?? '');
$floors      = trim($_POST['floors'] ?? '');
$phone       = trim($_POST['phone'] ?? '');
$toEmail     = trim($_POST['toEmail'] ?? '');
$ccEmail     = trim($_POST['ccEmail'] ?? '');
$bccEmail    = trim($_POST['bccEmail'] ?? '');
$mapLink     = trim($_POST['mapLink'] ?? '');
$refNumber   = trim($_POST['refNumber'] ?? ('REQ-' . time()));

// New: configurations heading (shown near price / above the pricing table)
$configHeading = trim($_POST['configHeading'] ?? '');

// New: editable disclaimer text (falls back to the original default copy)
$defaultDisclaimer = 'The content is for information purposes only and does not constitute an offer to avail of any service. Prices mentioned are subject to change without notice and properties mentioned are subject to availability. Images for representation purposes only. This is the official website of Authorized Marketing Partner - PROPERTY MATRIMONY | RERA No: PRM/KA/RERA/1251/446/AG/230412/003574. We may share data with RERA registered brokers/companies for further processing. We may also send updates to the mobile number/email id registered with us. All Rights Reserved.';
$disclaimerText = trim($_POST['disclaimerText'] ?? '');
if ($disclaimerText === '') $disclaimerText = $defaultDisclaimer;
$disclaimerTextEscaped = e($disclaimerText);

// New: click behaviour toggles — '1' = open lightbox image, '0'/absent = open Enquiry modal (previous default behaviour)
$floorplanLightbox = ($_POST['floorplanLightbox'] ?? '0') === '1';
$amenityLightbox   = ($_POST['amenityLightbox'] ?? '0') === '1';
$galleryLightbox   = ($_POST['galleryLightbox'] ?? '1') === '1'; // gallery previously always opened lightbox
$masterplanLightbox = ($_POST['masterplanLightbox'] ?? '0') === '1';

// New: lead delivery method — 'email' (mail only) or 'crm' (mail + CRM push)
$crmOption  = ($_POST['crmOption'] ?? 'email') === 'crm' ? 'crm' : 'email';
$crmApiKey  = trim($_POST['crmApiKey'] ?? '');

// New: which page sections to include (nav-driven) — default to included when not sent
$includePrice     = ($_POST['includePrice'] ?? '1') === '1';
$includeFloorplan = ($_POST['includeFloorplan'] ?? '1') === '1';
$includeGallery   = ($_POST['includeGallery'] ?? '1') === '1';
$includeLocation  = ($_POST['includeLocation'] ?? '1') === '1';

// New: About Builder — optional, shown only when text is actually provided
// (no picker checkbox for this one; presence of content is the toggle)
$aboutBuilderHeading = trim($_POST['aboutBuilderHeading'] ?? '');
$aboutBuilderText    = trim($_POST['aboutBuilderText'] ?? '');
$includeDeveloper    = ($aboutBuilderText !== '');

// New: Google gtag.js + Google Ads conversion tracking — both optional and
// blank by default (previously a stray hardcoded Google Ads ID shipped on
// every generated page; that's been removed in favor of this per-project setting)
$gtagId           = trim($_POST['gtagId'] ?? '');
$conversionSendTo = trim($_POST['conversionSendTo'] ?? '');

$highlights         = json_decode($_POST['highlights'] ?? '[]', true) ?: [];
$locationAdvantages = json_decode($_POST['locationAdvantages'] ?? '[]', true) ?: [];
$priceRows          = json_decode($_POST['priceRows'] ?? '[]', true) ?: [];

// Theme colors (hex only — falls back to the original brand defaults if missing/invalid)
function sanitizeColor($val, $default) {
    $val = trim((string) $val);
    return preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $val) ? $val : $default;
}
$colorPrimary   = sanitizeColor($_POST['colorPrimary'] ?? '', '#8a691c');
$colorSecondary = sanitizeColor($_POST['colorSecondary'] ?? '', '#3D3D3D');
$colorBtn       = sanitizeColor($_POST['colorBtn'] ?? '', '#ffffff');
$colorCard      = sanitizeColor($_POST['colorCard'] ?? '', '#eae5e4');
$colorOverlay   = sanitizeColor($_POST['colorOverlay'] ?? '', '#1a1a1a');

if ($projectName === '' || $address === '' || $phone === '' || $toEmail === '' || $priceRange === '') {
    fail('Missing required fields.');
}
if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
    fail('Lead email address is not valid.');
}
if ($ccEmail !== '' && !filter_var($ccEmail, FILTER_VALIDATE_EMAIL)) {
    fail('CC email address is not valid.');
}
if ($bccEmail !== '' && !filter_var($bccEmail, FILTER_VALIDATE_EMAIL)) {
    fail('BCC email address is not valid.');
}
if (empty($_FILES['slider']['tmp_name'][0])) {
    fail('At least one slider image is required.');
}

// ---------------------------------------------------------
// 2. Prepare a working folder for this submission
// ---------------------------------------------------------
$slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($projectName));
$slug = trim($slug, '-') ?: 'project';
$folderName = $slug . '-' . date('Ymd-His');

$baseDir   = __DIR__;
$workDir   = $baseDir . '/output/' . $folderName;          // temp build folder
$outputDir = $baseDir . '/output/zips';                     // final zips live here (publicly reachable)

if (!mkdir($workDir . '/assets/img', 0755, true) && !is_dir($workDir . '/assets/img')) {
    fail('Could not create working directory.', 500);
}
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}
// Lock down /output/ itself (blocks browsing submissions.csv, temp build
// folders, etc.) then re-open just /output/zips/ so download links work.
// Written on first run since these folders don't exist until the first
// submission — a fresh cPanel deploy won't have them yet.
$outputRootHtaccess = $baseDir . '/output/.htaccess';
if (!file_exists($outputRootHtaccess)) {
    file_put_contents($outputRootHtaccess, "Require all denied\n\n# Apache 2.2 fallback\nOrder deny,allow\nDeny from all\n");
}
$zipsHtaccess = $outputDir . '/.htaccess';
if (!file_exists($zipsHtaccess)) {
    file_put_contents($zipsHtaccess, "Require all granted\n\n# Apache 2.2 fallback\nOrder allow,deny\nAllow from all\n");
}

// ---------------------------------------------------------
// 3. Copy the static, never-changing files as-is
// ---------------------------------------------------------
$templatesDir = $baseDir . '/templates';
$staticAssetsDir = $baseDir . '/assets'; // your real css/js/fonts/img live here once

// thanks.html is written later (after the gtag snippet is built) since it
// now needs token replacement, not a plain copy.
copy($templatesDir . '/SMTPMailer.php', $workDir . '/SMTPMailer.php');
copy($templatesDir . '/config_smtp-template.php', $workDir . '/config_smtp.php');

function copyDirRecursive($src, $dst) {
    if (!is_dir($src)) return;
    if (!is_dir($dst)) mkdir($dst, 0755, true);
    foreach (scandir($src) as $item) {
        if ($item === '.' || $item === '..') continue;
        if ($item === 'PUT_YOUR_ASSETS_HERE.txt') continue;
        $s = $src . '/' . $item;
        $d = $dst . '/' . $item;
        if (is_dir($s)) {
            copyDirRecursive($s, $d);
        } else {
            copy($s, $d);
        }
    }
}
copyDirRecursive($staticAssetsDir, $workDir . '/assets');

// ---------------------------------------------------------
// 4. Save uploaded images into assets/img
// ---------------------------------------------------------
$allowedExt = ['jpg', 'jpeg', 'png', 'webp'];

// -- Single fixed-slot uploads: logo, master plan --
function saveSingleImage($field, $destPath, $allowedExt) {
    if (!empty($_FILES[$field]['tmp_name']) && is_uploaded_file($_FILES[$field]['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt)) return false;
        move_uploaded_file($_FILES[$field]['tmp_name'], $destPath);
        return true;
    }
    return false;
}
saveSingleImage('logo', $workDir . '/assets/logo.png', $allowedExt);
saveSingleImage('masterplan', $workDir . '/assets/img/masterplan.jpg', $allowedExt);
$authPartnerLogoUploaded = saveSingleImage('authPartnerLogo', $workDir . '/assets/img/comman/logo-footer.jpg', $allowedExt);

// Footer disclaimer block: Authorized Partner logo upload is optional.
// - Uploaded  -> two columns: logo (left) + disclaimer text (right)
// - Not uploaded -> disclaimer text alone spans full width, same as the
//   original single-column layout (no gap left behind).
if ($authPartnerLogoUploaded) {
    $disclaimerBlock = <<<HTML
<div class="col-12 col-md-4 text-center disclaimer-logo-col">
    <img src="assets/img/comman/logo-footer.jpg" alt="Authorized Partner" class="disclaimer-logo" style="max-width: 70%; height: auto;">
</div>
<div class="col-12 col-md-8">
     <b>Disclaimer :</b> {$disclaimerTextEscaped} </div>
HTML;
} else {
    $disclaimerBlock = <<<HTML
<div class="col-12 col-md-12">
     <b>Disclaimer :</b> {$disclaimerTextEscaped} </div>
HTML;
}

// -- Unlimited image lists with no text label (slider) --
// Expects field posted as an array input, e.g. name="slider[]"
function saveMultiImages($field, $workDir, $prefix, $allowedExt) {
    $saved = [];
    if (empty($_FILES[$field]) || !is_array($_FILES[$field]['tmp_name'])) return $saved;
    $files = $_FILES[$field];
    foreach ($files['tmp_name'] as $i => $tmp) {
        if (empty($tmp) || !is_uploaded_file($tmp) || ($files['error'][$i] ?? 1) !== UPLOAD_ERR_OK) continue;
        $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt)) continue;
        $n = count($saved) + 1;
        $destName = "{$prefix}{$n}.jpg";
        move_uploaded_file($tmp, $workDir . '/assets/img/' . $destName);
        $saved[] = $destName;
    }
    return $saved;
}

// -- Unlimited image lists paired with a text label (floor plan / gallery / amenities) --
// Expects two array inputs with matching indices, e.g. name="floorplanImage[]" + name="floorplanLabel[]"
function saveLabeledImages($fileField, $labelField, $workDir, $prefix, $allowedExt) {
    $items = [];
    if (empty($_FILES[$fileField]) || !is_array($_FILES[$fileField]['tmp_name'])) return $items;
    $files  = $_FILES[$fileField];
    $labels = $_POST[$labelField] ?? [];
    foreach ($files['tmp_name'] as $i => $tmp) {
        if (empty($tmp) || !is_uploaded_file($tmp) || ($files['error'][$i] ?? 1) !== UPLOAD_ERR_OK) continue;
        $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt)) continue;
        $n = count($items) + 1;
        $destName = "{$prefix}{$n}.jpg";
        move_uploaded_file($tmp, $workDir . '/assets/img/' . $destName);
        $items[] = [
            'file'  => $destName,
            'label' => trim($labels[$i] ?? ''),
        ];
    }
    return $items;
}

$sliderFiles     = saveMultiImages('slider', $workDir, 'slider', $allowedExt);
if (empty($sliderFiles)) {
    fail('At least one valid slider image is required.');
}
$floorplanEntries = saveLabeledImages('floorplanImage', 'floorplanLabel', $workDir, 'floorplan', $allowedExt);
$galleryEntries   = saveLabeledImages('galleryImage', 'galleryCaption', $workDir, 'gallery', $allowedExt);
$amenityEntries   = saveLabeledImages('amenityImage', 'amenityLabel', $workDir, 'amenity', $allowedExt);

// ---------------------------------------------------------
// 5. Build the dynamic HTML fragments
// ---------------------------------------------------------
function e($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// Highlights -> "line<br>line<br>line"
$highlightsHtml = implode('<br>', array_map('e', array_filter($highlights)));

// Location Advantages -> bulleted list shown below the map, only if any were given
$locationAdvItems = array_filter(array_map('trim', $locationAdvantages));
if (count($locationAdvItems) > 0) {
    $locationAdvHtml = "<div class=\"location-adv-block mt-3\">\n";
    $locationAdvHtml .= "    <span class=\"d-block section-heading-sub text-capitalize\">Location Advantages</span>\n";
    $locationAdvHtml .= "    <ul class=\"location-adv-list\">\n";
    foreach ($locationAdvItems as $adv) {
        $locationAdvHtml .= "        <li>" . e($adv) . "</li>\n";
    }
    $locationAdvHtml .= "    </ul>\n</div>\n";
} else {
    $locationAdvHtml = '';
}

// About Builder -> nav tab + section body, only included when text was provided
$aboutBuilderHtml = '';
if ($includeDeveloper) {
    $headingText = $aboutBuilderHeading !== '' ? $aboutBuilderHeading : 'About the Builder';
    $aboutBuilderHtml = '<span class="d-block section-heading-sub text-capitalize">' . e($headingText) . '</span>'
        . "\n<p>" . nl2br(e($aboutBuilderText)) . '</p>';
}

// Google gtag.js + Google Ads conversion tracking — both fields optional.
// If only the conversion "send_to" ID is given, its AW- prefix (the part
// before the "/") is reused as the gtag.js load/config ID so one field is
// enough for the common case. Leaving both blank ships zero tracking code
// (previously a stray hardcoded Google Ads ID was baked into every page).
function buildGtagSnippet($gtagId, $conversionSendTo, $fireConversion) {
    $loadId = $gtagId;
    if ($loadId === '' && $conversionSendTo !== '') {
        $parts = explode('/', $conversionSendTo);
        $loadId = $parts[0] ?? '';
    }
    if ($loadId === '') return '';
    $loadIdJs = addslashes($loadId);
    $snippet  = "<!-- Google tag (gtag.js) -->\n";
    $snippet .= "<script async src=\"https://www.googletagmanager.com/gtag/js?id={$loadIdJs}\"></script>\n";
    $snippet .= "<script>\n";
    $snippet .= "  window.dataLayer = window.dataLayer || [];\n";
    $snippet .= "  function gtag(){dataLayer.push(arguments);}\n";
    $snippet .= "  gtag('js', new Date());\n";
    $snippet .= "  gtag('config', '{$loadIdJs}');\n";
    if ($fireConversion && $conversionSendTo !== '') {
        $sendToJs = addslashes($conversionSendTo);
        $snippet .= "  gtag('event', 'conversion', {'send_to': '{$sendToJs}'});\n";
    }
    $snippet .= "</script>\n";
    return $snippet;
}
$gtagHeadSnippet   = buildGtagSnippet($gtagId, $conversionSendTo, false); // index.html: page tracking only
$thanksGtagSnippet = buildGtagSnippet($gtagId, $conversionSendTo, true);  // thanks.html: fires the conversion too

$thanksTemplate = file_get_contents($templatesDir . '/thanks.html');
$thanksHtml = strtr($thanksTemplate, ['{{GTAG_HEAD_SNIPPET}}' => $thanksGtagSnippet]);
file_put_contents($workDir . '/thanks.html', $thanksHtml);

// Price table rows
$priceTableRows = '';
foreach ($priceRows as $row) {
    $type  = e($row['type'] ?? '');
    $area  = e($row['area'] ?? '');
    $price = e($row['price'] ?? '');
    if ($type === '' && $area === '' && $price === '') continue;
    $priceTableRows .= <<<HTML
                        <tr>
                           <td class="border border-left-0 border-top-0 border-bottom-0 price-type">{$type}</td>
                           <td class="border border-left-0 border-top-0 border-bottom-0 price-carpet">{$area}</td>
                           <td class="price-amt"><i class="mi mi-rs-light"></i> {$price}</td>
                           <td><button class="btn btn-sm btn-info effetGradient effectScale enqModal" data-form="lg" data-title="Send me costing details" data-btn="Send now" data-enquiry="Request Price" data-redirect="floorplan" data-toggle="modal" data-target="#enqModal">Price Breakup</button></td>
                        </tr>

HTML;
}

// Slider indicators + carousel items (unlimited, built from the uploaded slider[] list)
$sliderIndicators = '';
$sliderItems = '';
foreach ($sliderFiles as $idx => $file) {
    $activeLi   = $idx === 0 ? ' class="active"' : '';
    $activeItem = $idx === 0 ? ' active' : '';
    $sliderIndicators .= "<li data-target=\"#home\" data-slide-to=\"{$idx}\"{$activeLi}></li>\n                    ";
    $sliderItems .= <<<HTML
                    <div class="carousel-item{$activeItem}">
                        <picture>
                            <source class="lazyload d-block micro-main-slider-img" media="(max-width: 750px)" data-srcset="assets/img/{$file}" type="image/webp" />
                            <source class="lazyload d-block micro-main-slider-img" media="(min-width: 751px)" data-srcset="assets/img/{$file}" type="image/webp" />
                            <img data-sizes="auto" class="lazyload d-block micro-main-slider-img" data-srcset="assets/img/{$file}" />
                        </picture>
                    </div>

HTML;
}

// Floor-plan section (unlimited, one card per uploaded floor plan image + its own label)
// - 1 to 3 images: responsive grid (columns adjust to the count)
// - 4+ images: automatically becomes an owl-carousel slider
// - $floorplanLightbox controls whether clicking opens a lightbox image or the Enquiry modal
function buildFloorplanSection($entries, $lightbox) {
    $count = count($entries);
    if ($count === 0) return '';

    $card = function ($entry, $lightbox) {
        $img   = $entry['file'];
        $label = e($entry['label'] !== '' ? $entry['label'] : 'Floor Plan');
        if ($lightbox) {
            return <<<HTML
                        <a href="assets/img/{$img}" class="js-lightbox text-decoration-none" data-lightbox-group="floorplan-gallery">
                            <div class="at-property-item shadow-sm border border-grey mt-1">
                                <div class="at-property-img">
                                    <picture>
                                        <source class="lazyload floor-plan-img blur" data-srcset="assets/img/{$img}" type="image/webp" />
                                        <img data-sizes="auto" class="lazyload floor-plan-img blur" data-srcset="assets/img/{$img}" />
                                    </picture>
                                    <div class="at-property-overlayer"></div>
                                    <span class="btn btn-default at-property-btn" role="button">View Plan</span>
                                </div>
                                <div class="at-property-dis effetGradient"><h5>{$label}</h5></div>
                            </div>
                        </a>

HTML;
        }
        return <<<HTML
                        <a href="#" class="text-decoration-none enqModal" data-form="lg" data-title="Send me plan details" data-btn="Send now" data-enquiry="Floor Plan" data-redirect="floorplan" data-toggle="modal" data-target="#enqModal">
                            <div class="at-property-item shadow-sm border border-grey mt-1">
                                <div class="at-property-img">
                                    <picture>
                                        <source class="lazyload floor-plan-img blur" data-srcset="assets/img/{$img}" type="image/webp" />
                                        <img data-sizes="auto" class="lazyload floor-plan-img blur" data-srcset="assets/img/{$img}" />
                                    </picture>
                                    <div class="at-property-overlayer"></div>
                                    <span class="btn btn-default at-property-btn" role="button">Enquire Now</span>
                                </div>
                                <div class="at-property-dis effetGradient"><h5>{$label}</h5></div>
                            </div>
                        </a>

HTML;
    };

    if ($count <= 3) {
        $cols = intdiv(12, $count);
        $html = "<div class=\"row row-cols-1 row-cols-md-{$count}\">\n";
        foreach ($entries as $entry) {
            $html .= "                    <div class=\"col\">\n" . $card($entry, $lightbox) . "                    </div>\n\n";
        }
        $html .= "                </div>\n";
        return $html;
    }

    // 4+ images -> slider
    $html = "<div class=\"floorplan-slider owl-carousel owl-theme\">\n";
    foreach ($entries as $entry) {
        $html .= "                    <div class=\"item\">\n" . $card($entry, $lightbox) . "                    </div>\n\n";
    }
    $html .= "                </div>\n";
    return $html;
}

// Gallery section (unlimited, only includes photos that were actually uploaded)
// - 1 to 3 images: responsive grid (columns adjust to the count)
// - 4+ images: automatically becomes an owl-carousel slider
// - $galleryLightbox controls whether clicking opens a lightbox image or the Enquiry modal
function buildGallerySection($entries, $lightbox) {
    $count = count($entries);
    if ($count === 0) return '';

    $card = function ($entry, $lightbox) {
        $img     = $entry['file'];
        $caption = e($entry['label'] !== '' ? $entry['label'] : 'Gallery Photo');
        if ($lightbox) {
            return <<<HTML
                        <a href="assets/img/{$img}" class="js-lightbox" data-lightbox-group="gallery-0"> <img data-src="./assets/img/{$img}" loading="lazy" class="lazyload gallery-thumb" alt="{$caption}"> </a>

HTML;
        }
        return <<<HTML
                        <a href="#" class="enqModal" data-form="lg" data-title="Send me this photo" data-btn="Send now" data-enquiry="Gallery Photo" data-toggle="modal" data-target="#enqModal"> <img data-src="./assets/img/{$img}" loading="lazy" class="lazyload gallery-thumb" alt="{$caption}"> </a>

HTML;
    };

    if ($count <= 3) {
        $colClass = $count === 1 ? 'col-lg-6 col-md-6 col-sm-8 col-8' : ($count === 2 ? 'col-lg-6 col-md-6 col-sm-6 col-6' : 'col-lg-4 col-md-4 col-sm-6 col-6');
        $html = "<div class=\"row\">\n";
        foreach ($entries as $entry) {
            $html .= "                    <div class=\"{$colClass} mb-2\">\n" . $card($entry, $lightbox) . "                    </div>\n\n";
        }
        $html .= "                </div>\n";
        return $html;
    }

    // 4+ images -> slider
    $html = "<div class=\"gallery-slider owl-carousel owl-theme\">\n";
    foreach ($entries as $entry) {
        $html .= "                    <div class=\"item\">\n" . $card($entry, $lightbox) . "                    </div>\n\n";
    }
    $html .= "                </div>\n";
    return $html;
}

// Master Plan click behaviour — same "Click to View" / "Enquiry Button" pattern as Floor Plan.
// $masterplanLightbox controls whether clicking the master plan image opens a lightbox popup
// or the existing Enquiry modal (default, unchanged behaviour).
if ($masterplanLightbox) {
    $masterplanLinkOpen  = '<a href="assets/img/masterplan.jpg" class="js-lightbox text-decoration-none" data-lightbox-group="masterplan-gallery">';
    $masterplanBtnText   = 'View Master Plan';
} else {
    $masterplanLinkOpen  = '<a href="#" class="text-decoration-none enqModal" data-form="lg" data-title="Send me costing details" data-btn="Send now" data-enquiry="Plan Details" data-toggle="modal" data-target="#enqModal">';
    $masterplanBtnText   = 'Enquire Now';
}
$masterplanLinkClose = '</a>';

$floorplanSection = buildFloorplanSection($floorplanEntries, $floorplanLightbox);
$gallerySection   = buildGallerySection($galleryEntries, $galleryLightbox);

// Amenity items (unlimited, image + name pairs, grouped 2-per-row to match the template layout)
// - $amenityLightbox controls whether clicking opens a lightbox image or the Enquiry modal
//   (same "Click to View" / "Enquiry Button" behaviour as the Floor Plan section)
$amenityItems = '';
foreach (array_chunk($amenityEntries, 2) as $chunk) {
    $amenityItems .= "                    <div class=\"item-wrp\">\n";
    foreach ($chunk as $entry) {
        $img   = $entry['file'];
        $label = e($entry['label'] !== '' ? $entry['label'] : 'Amenity');
        if ($amenityLightbox) {
            $amenityItems .= <<<HTML
                        <a href="assets/img/{$img}" class="js-lightbox ami-block-link" data-lightbox-group="amenity-gallery">
                            <div class="ami-block-bg" style="background-image: url(assets/img/{$img});">
                                <div class="ami-block-bg-overlay"><div class="ami-bg-name">{$label}</div></div>
                            </div>
                        </a>

HTML;
        } else {
            $amenityItems .= <<<HTML
                        <a href="#" class="ami-block-link enqModal" data-form="lg" data-title="Send me amenity details" data-btn="Send now" data-enquiry="Amenity" data-redirect="floorplan" data-toggle="modal" data-target="#enqModal">
                            <div class="ami-block-bg" style="background-image: url(assets/img/{$img});">
                                <div class="ami-block-bg-overlay"><div class="ami-bg-name">{$label}</div></div>
                            </div>
                        </a>

HTML;
        }
    }
    $amenityItems .= "                    </div>\n";
}

// Google Maps embed src — turns whatever the user pasted (the "Embed a map"
// iframe code, a plain share link, a coordinates link, or a plain address)
// into an actual iframe-embeddable URL. Google's regular "share" links
// (maps.app.goo.gl/... or /maps/place/...@lat,lng URLs) are NOT embeddable
// as-is — Google blocks them from being framed — so we pull the useful part
// (place name or lat/lng) out of them and build a proper `output=embed` URL.
function toEmbedSrc($mapLink) {
    $mapLink = trim($mapLink);
    if ($mapLink === '') {
        return 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3888.0!2d77.7!3d12.97!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2sIndia!5e0!3m2!1sen!2sin';
    }

    // 1) User pasted the full <iframe ...> code from "Share > Embed a map"
    //    — pull the real embeddable src straight out of it.
    if (stripos($mapLink, '<iframe') !== false && preg_match('/src=["\']([^"\']+)["\']/i', $mapLink, $m)) {
        return html_entity_decode($m[1]);
    }

    // 2) Already an embeddable Maps URL (e.g. the src copied directly).
    if (strpos($mapLink, 'google.com/maps/embed') !== false) {
        return $mapLink;
    }

    // 3) A normal Google Maps "share" link (place/search page or a
    //    maps.app.goo.gl short link) — these can't be framed directly,
    //    so extract the place name or coordinates and build an embed URL.
    if (preg_match('#^https?://#i', $mapLink)) {
        if (preg_match('#/maps/place/([^/@]+)#i', $mapLink, $m)) {
            return 'https://www.google.com/maps?q=' . urlencode(urldecode(str_replace('+', ' ', $m[1]))) . '&output=embed';
        }
        if (preg_match('#[@,]\s*(-?\d{1,3}\.\d+)\s*,\s*(-?\d{1,3}\.\d+)#', $mapLink, $m)) {
            return 'https://www.google.com/maps?q=' . urlencode($m[1] . ',' . $m[2]) . '&output=embed';
        }
        if (preg_match('#[?&]q=([^&]+)#i', $mapLink, $m)) {
            return 'https://www.google.com/maps?q=' . $m[1] . '&output=embed';
        }
        // Shortened link we can't parse locally (e.g. maps.app.goo.gl/xxxx) —
        // best effort: let Google's embed endpoint try to resolve it directly.
        return 'https://www.google.com/maps?q=' . urlencode($mapLink) . '&output=embed';
    }

    // 4) Plain address / place name text.
    return 'https://www.google.com/maps?q=' . urlencode($mapLink) . '&output=embed';
}
// Configurations heading (e.g. "Premium 3/4/5 BHK Apartments") — shown above the price in the
// hero box, and repeated as a sub-heading in the pricing table section, if provided.
$configHeadingBlock = '';
$configHeadingSub   = '';
if ($configHeading !== '') {
    $configHeadingBlock = '<span class="d-block pro-title text-capitalize" style="font-size:14px;font-weight:700;">' . e($configHeading) . '</span>';
    $configHeadingSub   = '<span class="d-block section-heading-sub text-capitalize">' . e($configHeading) . '</span>';
}
$mapEmbedSrc = toEmbedSrc($mapLink);

// Phone formatting
$phoneDisplay = $phone;
$phoneDigits  = preg_replace('/[^0-9+]/', '', $phone);
$phoneTel     = 'tel:' . $phoneDigits;

// ---------------------------------------------------------
// 6. Fill index.html
// ---------------------------------------------------------
$indexTemplate = file_get_contents($templatesDir . '/index-template.html');

$tokens = [
    '{{PAGE_TITLE}}'          => e("BOOK NOW - {$projectName} - {$address}"),
    '{{STATUS_BADGE}}'        => e($statusBadge),
    '{{PROJECT_NAME}}'        => e($projectName),
    '{{ADDRESS}}'             => e($address),
    '{{LAND_AREA}}'           => e($landArea),
    '{{TOTAL_UNITS}}'         => e($totalUnits),
    '{{FLOORS}}'              => e($floors),
    '{{HIGHLIGHTS_HTML}}'     => $highlightsHtml,
    '{{CONFIG_HEADING_BLOCK}}'=> $configHeadingBlock,
    '{{CONFIG_HEADING_SUB}}'  => $configHeadingSub,
    '{{PRICE_RANGE}}'         => e($priceRange),
    '{{PRICE_TABLE_ROWS}}'    => $priceTableRows,
    '{{SLIDER_INDICATORS}}'   => $sliderIndicators,
    '{{SLIDER_ITEMS}}'        => $sliderItems,
    '{{FLOORPLAN_SECTION}}'   => $floorplanSection,
    '{{MASTERPLAN_LINK_OPEN}}'  => $masterplanLinkOpen,
    '{{MASTERPLAN_LINK_CLOSE}}' => $masterplanLinkClose,
    '{{MASTERPLAN_BTN_TEXT}}'   => e($masterplanBtnText),
    '{{GALLERY_SECTION}}'     => $gallerySection,
    '{{AMENITY_ITEMS}}'       => $amenityItems,
    '{{MAP_IFRAME_SRC}}'      => $mapEmbedSrc,
    '{{LOCATION_ADV_HTML}}'   => $locationAdvHtml,
    '{{ABOUT_BUILDER_HTML}}'  => $aboutBuilderHtml,
    '{{GTAG_HEAD_SNIPPET}}'   => $gtagHeadSnippet,
    '{{PHONE_TEL}}'           => $phoneTel,
    '{{PHONE_DISPLAY}}'       => e($phoneDisplay),
    '{{COLOR_PRIMARY}}'       => $colorPrimary,
    '{{COLOR_SECONDARY}}'     => $colorSecondary,
    '{{COLOR_BTN}}'           => $colorBtn,
    '{{COLOR_CARD}}'          => $colorCard,
    '{{COLOR_OVERLAY}}'       => $colorOverlay,
    '{{DISCLAIMER_BLOCK}}'    => $disclaimerBlock,
];

$indexHtml = strtr($indexTemplate, $tokens);

// ---------------------------------------------------------
// 6b. Strip out any page sections the user deselected in the
//     "Which sections do you want?" nav picker on the request form.
//     Sections are wrapped in <!--SECTION:NAME_START/END--> markers
//     in the template — when included we just drop the markers,
//     when excluded we drop the marker AND everything between them.
// ---------------------------------------------------------
function applySectionToggle($html, $marker, $include) {
    $start = "<!--SECTION:{$marker}_START-->";
    $end   = "<!--SECTION:{$marker}_END-->";
    if ($include) {
        return str_replace([$start, $end], '', $html);
    }
    $pattern = '/' . preg_quote($start, '/') . '.*?' . preg_quote($end, '/') . '/s';
    return preg_replace($pattern, '', $html);
}
$sectionToggles = [
    'PRICE'      => $includePrice,
    'FLOORPLAN'  => $includeFloorplan,
    'GALLERY'    => $includeGallery,
    'LOCATION'   => $includeLocation,
    'DEVELOPER'  => $includeDeveloper,
];
foreach ($sectionToggles as $marker => $include) {
    $indexHtml = applySectionToggle($indexHtml, $marker, $include);
}

file_put_contents($workDir . '/index.html', $indexHtml);

// ---------------------------------------------------------
// 7. Fill crm_connect.php
// ---------------------------------------------------------
$crmTemplate = file_get_contents($templatesDir . '/crm_connect-template.php');

// Lead delivery: 'email' -> mail only (skip the CRM push entirely).
// 'crm' -> mail + push the lead to the CRM (LeadRat) using the given API key
// (falls back to the original default key if none was supplied).
$crmBlock = '';
if ($crmOption === 'crm') {
    $apiKeyPhp = addslashes($crmApiKey !== '' ? $crmApiKey : 'NDM1ODdmNjAtNzQzZi00Zjk1LTgwMzktOTM1OTEzMDIxY2U3');
    $crmBlock = <<<PHP
sendGoogleAdsData(\$nameField, \$MobileField, \$projectField, \$messageField, \$EmailField);

function sendGoogleAdsData(\$name, \$mobile, \$project, \$notes, \$email) {
    \$curl = curl_init();

    \$postData = json_encode(array(array(
        'name' => \$name,
        'mobile' => \$mobile,
        'project' => \$project,
        'notes' => \$notes,
        'email' => \$email
    )));

    curl_setopt_array(\$curl, array(
        CURLOPT_URL => 'https://connect.leadrat.com/api/v1/integration/Website',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => \$postData,
        CURLOPT_HTTPHEADER => array(
            'API-Key: {$apiKeyPhp}',
            'Content-Type: application/json'
        ),
    ));

    \$response = curl_exec(\$curl);

    if (curl_errno(\$curl)) {
        echo 'Error:' . curl_error(\$curl);
    } else {
        echo \$response;
    }

    curl_close(\$curl);
}
PHP;
}

$crmTokens = [
    '{{PROJECT_NAME}}' => addslashes($projectName),
    '{{TO_EMAIL}}'     => addslashes($toEmail),
    '{{CC_EMAIL}}'     => addslashes($ccEmail),
    '{{BCC_EMAIL}}'    => addslashes($bccEmail),
    '{{CRM_BLOCK}}'    => $crmBlock,
];
$crmPhp = strtr($crmTemplate, $crmTokens);
file_put_contents($workDir . '/crm_connect.php', $crmPhp);

// ---------------------------------------------------------
// 8. Zip everything up
// ---------------------------------------------------------
$zipName = $folderName . '.zip';
$zipPath = $outputDir . '/' . $zipName;

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fail('Could not create zip file.', 500);
}

function addFolderToZip($zip, $folder, $zipRoot = '') {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($folder, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($files as $file) {
        if ($file->isDir()) continue;
        $filePath = $file->getRealPath();
        $relativePath = $zipRoot . substr($filePath, strlen($folder) + 1);
        $zip->addFile($filePath, $relativePath);
    }
}
addFolderToZip($zip, $workDir);
$zip->close();

// ---------------------------------------------------------
// 8b. Copy a safe, public preview (index.html + assets only —
//     never the backend PHP/SMTP files) so it can be viewed
//     in-browser right after generating, before downloading.
// ---------------------------------------------------------
function copyFolder($source, $dest) {
    if (!is_dir($dest)) {
        mkdir($dest, 0755, true);
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($items as $item) {
        $target = $dest . '/' . substr($item->getRealPath(), strlen($source) + 1);
        if ($item->isDir()) {
            if (!is_dir($target)) mkdir($target, 0755, true);
        } else {
            copy($item->getRealPath(), $target);
        }
    }
}

$previewsDir = $baseDir . '/output/previews';
if (!is_dir($previewsDir)) {
    mkdir($previewsDir, 0755, true);
}
$previewsHtaccess = $previewsDir . '/.htaccess';
$previewsHtaccessContent = "Require all granted\n\n# Apache 2.2 fallback\nOrder allow,deny\nAllow from all\n\n<FilesMatch \"\\.php$\">\n    Require all denied\n    Order deny,allow\n    Deny from all\n</FilesMatch>\n";
// Write if missing, or repair an older version of this file that was
// written before the Apache 2.2 fallback existed (that older version is
// why preview links 403 on hosts still using mod_access_compat, even
// though download links work fine).
if (!file_exists($previewsHtaccess) || strpos((string) file_get_contents($previewsHtaccess), 'Apache 2.2 fallback') === false) {
    file_put_contents($previewsHtaccess, $previewsHtaccessContent);
}

$previewDir = $previewsDir . '/' . $folderName;
mkdir($previewDir, 0755, true);
copy($workDir . '/index.html', $previewDir . '/index.html');
if (is_dir($workDir . '/assets')) {
    copyFolder($workDir . '/assets', $previewDir . '/assets');
}

// ---------------------------------------------------------
// 9. Clean up the temp working folder (zip + preview copy already hold what's needed)
// ---------------------------------------------------------
function deleteFolder($folder) {
    if (!is_dir($folder)) return;
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($folder, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getRealPath()) : unlink($item->getRealPath());
    }
    rmdir($folder);
}
deleteFolder($workDir);

// ---------------------------------------------------------
// 10. Log the submission (simple CSV — swap for a DB if you prefer)
// ---------------------------------------------------------
$logLine = implode(',', array_map(function ($v) {
    return '"' . str_replace('"', '""', $v) . '"';
}, [date('Y-m-d H:i:s'), $refNumber, $projectName, $toEmail, $phone, $zipName]));
file_put_contents($baseDir . '/output/submissions.csv', $logLine . "\n", FILE_APPEND);

// ---------------------------------------------------------
// 11. Respond with the download link + a live preview link
// ---------------------------------------------------------
$protocol   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host       = $_SERVER['HTTP_HOST'];
$scriptDir  = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$downloadLink = "{$protocol}://{$host}{$scriptDir}/output/zips/{$zipName}";
$previewLink  = "{$protocol}://{$host}{$scriptDir}/output/previews/{$folderName}/index.html";

echo json_encode([
    'success'      => true,
    'refNumber'    => $refNumber,
    'downloadLink' => $downloadLink,
    'previewLink'  => $previewLink,
]);
