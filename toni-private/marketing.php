<?php
declare(strict_types=1);
require_once __DIR__.'/reviews.php';

function marketing(): array {
    return query('SELECT * FROM marketing WHERE id=1')->fetch();
}

function idField(string $key, string $pattern, string $example): string {
    $value = field($key, 120, false);
    if ($value !== '' && !preg_match($pattern, $value)) {
        throw new FieldError($key, 'Enter the ID or token only. Example: '.$example);
    }
    return $value;
}

function secureUrl(string $key): string {
    $value = field($key, 2048, false);
    if ($value === '') return '';
    $parts = parse_url($value);
    if (!filter_var($value, FILTER_VALIDATE_URL) || ($parts['scheme'] ?? '') !== 'https'
        || isset($parts['user']) || isset($parts['pass']) || str_contains($value, chr(92))) {
        throw new FieldError($key, 'Enter a full HTTPS link.');
    }
    return $value;
}

function saveMarketing(string $area): void {
    if ($area === 'Ads & Analytics') {
        $keywords = field('keywords', 5000, false);
        query('UPDATE ad_keywords SET keywords=? WHERE id=1', [$keywords]);
        $ads = idField('ads_id', '/^AW-[0-9]{5,20}$/D', 'AW-123456789');
        $label = idField('ads_label', '/^[A-Za-z0-9_-]{1,100}$/D', 'AbCde_Fg123');
        if ($label && !$ads) throw new FieldError('ads_id','Add the Google Ads ID for this conversion label.');
        $analytics = idField('ga_id', '/^G-[A-Z0-9]{4,24}$/D', 'G-ABC1234567');
        $pixel = idField('pixel_id', '/^[0-9]{5,25}$/D', '123456789012345');
        query('UPDATE marketing SET ads_id=?,ads_label=?,ga_id=?,pixel_id=? WHERE id=1', [$ads,$label,$analytics,$pixel]);
    } elseif ($area === 'SEO') {
        $url = secureUrl('canonical');
        if ($url && (parse_url($url, PHP_URL_FRAGMENT) !== null || parse_url($url, PHP_URL_QUERY) !== null)) {
            throw new FieldError('canonical','Use the public homepage URL without a query or fragment.');
        }
        query('UPDATE marketing SET seo_title=?,seo_description=?,canonical=?,verification=? WHERE id=1',[
            field('seo_title',150,false),field('seo_description',350,false),$url,
            idField('verification','/^[A-Za-z0-9_-]{8,120}$/D','the content value from Google Search Console')
        ]);
    } else {
        $url = secureUrl('reviews_url');
        if ($url !== '') {
            $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
            $path = parse_url($url, PHP_URL_PATH) ?? '';
            $valid = in_array($host,['maps.app.goo.gl','g.page','share.google'],true) && strlen($path)>1;
            if (in_array($host,['www.google.com','google.com','www.google.co.in','google.co.in','maps.google.com'],true)) {
                $valid = $path === '/search' || $path === '/maps' || str_starts_with($path,'/maps/');
            }
            if ($host==='search.google.com') $valid=in_array($path,['/local/reviews','/local/writereview'],true);
            if (!$valid || isset(parse_url($url)['port'])) throw new FieldError('reviews_url','Paste the Google Business Profile review link or the Google Maps listing link.');
        }
        saveFeaturedReviews();
        query('UPDATE marketing SET reviews_url=? WHERE id=1',[$url]);
    }
}

function marketingForm(string $area, array $m): void {
    beginForm();
    if ($area === 'Google Reviews') {
        echo '<p>Connect your branch by pasting its Google review or Maps link. Clear the link to hide the reviews section.</p>';
        inputBox('reviews_url','Google review or listing link',$m['reviews_url'],'url',false);
        reviewEditor();
        if ($m['reviews_url']) echo '<p><a class="under" target="_blank" rel="noopener" href="'.esc($m['reviews_url']).'">Open saved Google listing ↗</a></p>';
    } elseif ($area === 'SEO') {
        echo '<p>These settings control the homepage search and sharing metadata. Empty fields use the salon defaults.</p>';
        inputBox('seo_title','Search title',$m['seo_title'],'text',false);
        inputBox('seo_description','Search description',$m['seo_description'],'textarea',false);
        inputBox('canonical','Public homepage URL',$m['canonical'],'url',false,'Use the final HTTPS domain.');
        inputBox('verification','Google Search Console verification token',$m['verification'],'text',false);
    } else {
        echo '<p>Enter the IDs from your accounts. Tags load on the public site after the visitor accepts measurement. Clear an ID to turn it off.</p>';
        inputBox('keywords','Google Ads campaign keywords',query('SELECT keywords FROM ad_keywords WHERE id=1')->fetchColumn(),'textarea',false,'Copy these phrases into your Google Ads search ad group, one per line. Saving here updates your keyword list.');
        inputBox('ads_id','Google Ads conversion ID',$m['ads_id'],'text',false,'Example: AW-123456789');
        inputBox('ads_label','Booking-click conversion label',$m['ads_label'],'text',false,'Copy the label from your Google Ads website conversion action.');
        inputBox('pixel_id','Meta Pixel ID',$m['pixel_id'],'text',false,'Tracks PageView and Contact when someone taps a phone link.');
        inputBox('ga_id','Google Analytics 4 measurement ID',$m['ga_id'],'text',false,'Example: G-ABC1234567');
    }
    saveBtn();
}
