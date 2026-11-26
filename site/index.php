<?php
require dirname(__DIR__,2).'/toni-private/app.php';
$s=site();$services=items('services');$photos=items('gallery');
$call=esc(tel($s));$map='https://www.google.com/maps/search/?api=1&query='.rawurlencode('Toni&Guy Hairdressing Thrikkakara, '.$s['address']);
$m=marketing();
$keywordLines = preg_split('/\R/u', (string) query('SELECT keywords FROM ad_keywords WHERE id=1')->fetchColumn());
$keywords = [];
foreach ($keywordLines as $line) {
    $phrase = trim(trim($line), '"[]');
    if ($phrase !== '') $keywords[] = $phrase;
}
$title=$m['seo_title'] ?: 'Toni&Guy Hairdressing Thrikkakara | Hairdressing & Styling';
$description=$m['seo_description'] ?: $s['intro'];
$tracking=(bool)($m['ads_id'] || $m['ga_id'] || $m['pixel_id']);
if($tracking) {
    header("Content-Security-Policy: default-src 'self'; script-src 'self' https://www.googletagmanager.com https://connect.facebook.net https://www.googleadservices.com https://googleads.g.doubleclick.net; img-src 'self' data: https://*.google-analytics.com https://*.google.com https://*.google.co.in https://*.googleadservices.com https://*.googletagmanager.com https://*.doubleclick.net https://www.facebook.com; connect-src 'self' https://*.google-analytics.com https://*.analytics.google.com https://*.google.com https://*.google.co.in https://*.googletagmanager.com https://*.googleadservices.com https://*.doubleclick.net https://www.facebook.com https://connect.facebook.net; frame-src https://*.googletagmanager.com https://*.doubleclick.net; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
}
function book(array $s):string{return '<a class="btn booking" href="'.esc(destination($s['book_target'],$s)).'">'.esc($s['book_label']).' <span aria-hidden="true">↗</span></a>';}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?=esc($title)?></title><meta name="description" content="<?=esc($description)?>">
<?php if ($keywords): ?><meta name="keywords" content="<?=esc(implode(', ', $keywords))?>"><?php endif ?>
<meta property="og:type" content="website"><meta property="og:title" content="<?=esc($title)?>"><meta property="og:description" content="<?=esc($description)?>">
<?php if($m['canonical']):?><link rel="canonical" href="<?=esc($m['canonical'])?>"><meta property="og:url" content="<?=esc($m['canonical'])?>"><meta property="og:image" content="<?=esc(rtrim($m['canonical'],'/').'/'.substr(media($s['hero']),strlen(BASE)))?>"><?php endif?>
<?php if($m['verification']):?><meta name="google-site-verification" content="<?=esc($m['verification'])?>"><?php endif?>
<?php if($tracking):?><script defer src="<?=esc(BASE)?>assets/measurement.js"></script><?php endif?><link rel="stylesheet" href="<?=esc(BASE)?>assets/site.css"><link rel="stylesheet" href="<?=esc(BASE)?>assets/guest-reviews.css?v=1"></head><body id="top"><a class="skip" href="#main">Skip to content</a>
<header class="mast"><a class="brand" href="#top" aria-label="Toni&amp;Guy Hairdressing Thrikkakara home"><img src="<?=esc(media($s['logo']))?>" alt="TONI&amp;GUY Hairdressing"></a><nav aria-label="Main navigation"><a href="#services">Services</a><a href="#salon">The salon</a><a href="#gallery">Gallery</a><a href="#contact">Contact</a></nav><?=book($s)?></header>
<main id="main"><section class="hero"><div class="hero-copy"><p class="eyebrow"><i></i> Thrikkakara, Kerala</p>
<h1><?php if($s['headline']==='Good hair starts in Thrikkakara.'):?>Good hair <br>starts in<br><em>Thrikkakara.</em><?php else:?><?=esc($s['headline'])?><?php endif?></h1><p class="intro"><?=esc($s['intro'])?></p><div class="actions"><?=book($s)?><a class="under" href="<?=esc(destination($s['explore_target'],$s))?>"><?=esc($s['explore_label'])?> <span aria-hidden="true">↓</span></a></div></div>
<picture><?php if($s['hero']==='assets/hero.webp'):?><source media="(max-width:700px)" srcset="<?=esc(BASE)?>assets/hero-mobile.webp"><?php endif?><img fetchpriority="high" width="1300" height="1550" src="<?=esc(media($s['hero']))?>" alt="<?=esc($s['hero_alt'])?>"></picture></section>
<section id="services" class="section"><p class="eyebrow"><i></i> Services</p><div class="service-grid"><div><h2>The service <em>menu.</em></h2><img class="floor" loading="lazy" src="<?=esc(BASE)?>assets/styling-floor.webp" width="844" height="1500" alt="Stylists working beside tall mirrors on the salon floor"></div><div class="menu"><?php foreach($services as $r):?><details><summary><?=esc($r['name'])?><span aria-hidden="true"></span></summary><div class="details"><?php if($r['image']):?><img loading="lazy" src="<?=esc(media($r['image']))?>" alt="<?=esc($r['name'])?>"><?php endif?><p><?=esc($r['description'])?></p><a class="under" href="<?=$call?>" aria-label="Call to book <?=esc($r['name'])?>">Call to book <span aria-hidden="true">↗</span></a></div></details><?php endforeach?></div></div></section>
<section id="salon" class="story section"><p class="eyebrow"><i></i> The salon</p><div><h2>In <em>Thrikkakara.</em></h2><p><?=esc($s['story'])?></p></div></section>
<?php if($photos):?><section id="gallery" class="section gallery"><h2>Salon <em>gallery.</em></h2><div class="photos"><?php foreach($photos as $r):?><figure><img loading="lazy" src="<?=esc(media($r['image']))?>" alt="<?=esc($r['alt'])?>"><?php if($r['caption']):?><figcaption><?=esc($r['caption'])?></figcaption><?php endif?></figure><?php endforeach?></div></section><?php else:?><div id="gallery"></div><?php endif?>
<?php if($m['reviews_url']): $reviewCards=homepageReviews($m['reviews_url']); ?><section class="section review-strip" id="reviews" aria-labelledby="review-heading">
<a class="review-summary" href="<?=esc($m['reviews_url'])?>" target="_blank" rel="noopener"><?php if($reviewCards):?><strong><?=number_format(array_sum(array_column($reviewCards,'stars'))/count($reviewCards),1)?>/5</strong><?php endif?><b><span aria-hidden="true">★</span> Google</b><?php if($reviewCards):?> <span>Based on <?=count($reviewCards)?> featured reviews</span><?php endif?></a>
<div class="review-heading"><span class="quote-mark" aria-hidden="true"><i></i><i></i></span><h2 id="review-heading">What our<br>customers are<br>saying</h2>
<div class="review-controls" hidden><button type="button" aria-controls="review-track" aria-label="Previous reviews">←</button><input type="range" min="1" max="6" value="1" aria-label="Browse reviews" aria-controls="review-track"><button type="button" aria-controls="review-track" aria-label="Next reviews">→</button><span class="review-range" aria-live="polite"></span></div>
</div>
<?php renderReviewCards($reviewCards,$m['reviews_url']); ?>
<dialog class="review-reader" aria-labelledby="reader-name"><form method="dialog"><button autofocus aria-label="Close full review">×</button></form><h3 id="reader-name"></h3><p class="reader-rating"></p><blockquote></blockquote></dialog><script defer src="<?=esc(BASE)?>assets/reviews.js?v=2"></script></section><?php endif?>
<section id="contact" class="contact section"><div><p class="eyebrow"><i></i> Booking &amp; location</p><h2>Plan your <em>visit.</em></h2><p>The salon confirms appointments by phone.</p><a class="number" href="<?=$call?>"><?=esc($s['phone'])?> ↗</a></div><div class="address"><h3>Find the salon</h3><address><?=esc($s['address'])?></address><a class="under" href="<?=esc($map)?>">Get directions ↗</a><?php if($s['email']):?><h3>Email</h3><a href="mailto:<?=esc($s['email'])?>"><?=esc($s['email'])?></a><?php endif?></div></section></main><footer>Toni&amp;Guy Hairdressing Thrikkakara <?php if($tracking):?><button id="privacy-choice" hidden>Privacy choices</button><?php endif?><a href="#top">Back to top ↑</a></footer>
<?php if($tracking):?><aside id="measurement" class="measurement" hidden aria-label="Optional measurement" data-ads="<?=esc($m['ads_id'])?>" data-label="<?=esc($m['ads_label'])?>" data-ga="<?=esc($m['ga_id'])?>" data-pixel="<?=esc($m['pixel_id'])?>">
<p>Allow optional analytics and advertising tags to measure visits and booking clicks?</p><div><button value="deny">Decline</button> <button value="allow">Allow</button></div></aside><?php endif?></body></html>
