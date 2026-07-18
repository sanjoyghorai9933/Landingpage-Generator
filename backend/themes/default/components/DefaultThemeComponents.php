<?php
function renderDefaultFloorplanSection($entries, $lightbox) {
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
        $html = "<div class=\"row row-cols-1 row-cols-md-{$count}\">\n";
        foreach ($entries as $entry) {
            $html .= "                    <div class=\"col\">\n" . $card($entry, $lightbox) . "                    </div>\n\n";
        }
        return $html . "                </div>\n";
    }

    $html = "<div class=\"floorplan-slider owl-carousel owl-theme\">\n";
    foreach ($entries as $entry) {
        $html .= "                    <div class=\"item\">\n" . $card($entry, $lightbox) . "                    </div>\n\n";
    }
    return $html . "                </div>\n";
}

function renderDefaultGallerySection($entries, $lightbox) {
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
        return $html . "                </div>\n";
    }

    $html = "<div class=\"gallery-slider owl-carousel owl-theme\">\n";
    foreach ($entries as $entry) {
        $html .= "                    <div class=\"item\">\n" . $card($entry, $lightbox) . "                    </div>\n\n";
    }
    return $html . "                </div>\n";
}
