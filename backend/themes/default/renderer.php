<?php
require_once __DIR__ . '/components/DefaultThemeComponents.php';

function renderDefaultThemeTokens(array $c): array {
    $highlightsHtml = implode('<br>', array_map('e', array_filter($c['highlights'])));

    $locationAdvItems = array_filter(array_map('trim', $c['locationAdvantages']));
    if (count($locationAdvItems) > 0) {
        $locationAdvHtml = "<div class=\"location-adv-block mt-3\">\n";
        $locationAdvHtml .= "    <span class=\"d-block section-heading-sub text-capitalize\">Location Advantages</span>\n";
        $locationAdvHtml .= "    <ul class=\"location-adv-list\">\n";
        foreach ($locationAdvItems as $adv) $locationAdvHtml .= "        <li>" . e($adv) . "</li>\n";
        $locationAdvHtml .= "    </ul>\n</div>\n";
    } else $locationAdvHtml = '';

    $aboutBuilderHtml = '';
    if ($c['includeDeveloper']) {
        $headingText = $c['aboutBuilderHeading'] !== '' ? $c['aboutBuilderHeading'] : 'About the Builder';
        $aboutBuilderHtml = '<span class="d-block section-heading-sub text-capitalize">' . e($headingText) . '</span>' . "\n<p>" . nl2br(e($c['aboutBuilderText'])) . '</p>';
    }

    $priceTableRows = '';
    foreach ($c['priceRows'] as $row) {
        $type = e($row['type'] ?? ''); $area = e($row['area'] ?? ''); $price = e($row['price'] ?? '');
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

    $sliderIndicators = ''; $sliderItems = '';
    foreach ($c['sliderFiles'] as $idx => $file) {
        $activeLi = $idx === 0 ? ' class="active"' : ''; $activeItem = $idx === 0 ? ' active' : '';
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

    $masterplanLinkOpen = $c['masterplanLightbox'] ? '<a href="assets/img/masterplan.jpg" class="js-lightbox text-decoration-none" data-lightbox-group="masterplan-gallery">' : '<a href="#" class="text-decoration-none enqModal" data-form="lg" data-title="Send me costing details" data-btn="Send now" data-enquiry="Plan Details" data-toggle="modal" data-target="#enqModal">';
    $masterplanBtnText = $c['masterplanLightbox'] ? 'View Master Plan' : 'Enquire Now';

    $amenityItems = '';
    foreach (array_chunk($c['amenityEntries'], 2) as $chunk) {
        $amenityItems .= "                    <div class=\"item-wrp\">\n";
        foreach ($chunk as $entry) {
            $img = $entry['file']; $label = e($entry['label'] !== '' ? $entry['label'] : 'Amenity');
            $amenityItems .= $c['amenityLightbox']
                ? "                        <a href=\"assets/img/{$img}\" class=\"js-lightbox ami-block-link\" data-lightbox-group=\"amenity-gallery\">\n                            <div class=\"ami-block-bg\" style=\"background-image: url(assets/img/{$img});\">\n                                <div class=\"ami-block-bg-overlay\"><div class=\"ami-bg-name\">{$label}</div></div>\n                            </div>\n                        </a>\n\n"
                : "                        <a href=\"#\" class=\"ami-block-link enqModal\" data-form=\"lg\" data-title=\"Send me amenity details\" data-btn=\"Send now\" data-enquiry=\"Amenity\" data-redirect=\"floorplan\" data-toggle=\"modal\" data-target=\"#enqModal\">\n                            <div class=\"ami-block-bg\" style=\"background-image: url(assets/img/{$img});\">\n                                <div class=\"ami-block-bg-overlay\"><div class=\"ami-bg-name\">{$label}</div></div>\n                            </div>\n                        </a>\n\n";
        }
        $amenityItems .= "                    </div>\n";
    }

    $configHeadingBlock = $c['configHeading'] !== '' ? '<span class="d-block pro-title text-capitalize" style="font-size:14px;font-weight:700;">' . e($c['configHeading']) . '</span>' : '';
    $configHeadingSub = $c['configHeading'] !== '' ? '<span class="d-block section-heading-sub text-capitalize">' . e($c['configHeading']) . '</span>' : '';

    return [
        '{{PAGE_TITLE}}' => e("BOOK NOW - {$c['projectName']} - {$c['address']}"), '{{STATUS_BADGE}}' => e($c['statusBadge']), '{{PROJECT_NAME}}' => e($c['projectName']), '{{ADDRESS}}' => e($c['address']),
        '{{LAND_AREA}}' => e($c['landArea']), '{{TOTAL_UNITS}}' => e($c['totalUnits']), '{{FLOORS}}' => e($c['floors']), '{{HIGHLIGHTS_HTML}}' => $highlightsHtml,
        '{{CONFIG_HEADING_BLOCK}}' => $configHeadingBlock, '{{CONFIG_HEADING_SUB}}' => $configHeadingSub, '{{PRICE_RANGE}}' => e($c['priceRange']), '{{PRICE_TABLE_ROWS}}' => $priceTableRows,
        '{{SLIDER_INDICATORS}}' => $sliderIndicators, '{{SLIDER_ITEMS}}' => $sliderItems, '{{FLOORPLAN_SECTION}}' => renderDefaultFloorplanSection($c['floorplanEntries'], $c['floorplanLightbox']),
        '{{MASTERPLAN_LINK_OPEN}}' => $masterplanLinkOpen, '{{MASTERPLAN_LINK_CLOSE}}' => '</a>', '{{MASTERPLAN_BTN_TEXT}}' => e($masterplanBtnText), '{{GALLERY_SECTION}}' => renderDefaultGallerySection($c['galleryEntries'], $c['galleryLightbox']),
        '{{AMENITY_ITEMS}}' => $amenityItems, '{{MAP_IFRAME_SRC}}' => $c['mapEmbedSrc'], '{{LOCATION_ADV_HTML}}' => $locationAdvHtml, '{{ABOUT_BUILDER_HTML}}' => $aboutBuilderHtml,
        '{{GTAG_HEAD_SNIPPET}}' => $c['gtagHeadSnippet'], '{{PHONE_TEL}}' => $c['phoneTel'], '{{PHONE_DISPLAY}}' => e($c['phoneDisplay']), '{{COLOR_PRIMARY}}' => $c['colors']['primary'],
        '{{COLOR_SECONDARY}}' => $c['colors']['secondary'], '{{COLOR_BTN}}' => $c['colors']['button'], '{{COLOR_CARD}}' => $c['colors']['card'], '{{COLOR_OVERLAY}}' => $c['colors']['overlay'], '{{DISCLAIMER_BLOCK}}' => $c['disclaimerBlock'],
    ];
}
