<?php
declare(strict_types=1);

const FEATURED_REVIEW_SLOTS = 6;

function reviewEditor(): void {
    $stored = query('SELECT * FROM featured_reviews ORDER BY id')->fetchAll();
    $bySlot = array_column($stored, null, 'id');
    echo '<h3>Reviews shown on the homepage</h3><p>Copy the reviewer’s name, star rating and original wording from this branch’s Google listing. Leave a slot empty to remove it.</p>';
    for ($slot = 1; $slot <= FEATURED_REVIEW_SLOTS; $slot++) {
        $r = $bySlot[$slot] ?? [];
        echo '<fieldset><legend>Review '.$slot.'</legend>';
        inputBox("author_$slot",'Reviewer name',$r['author'] ?? '','text',false);
        inputBox("stars_$slot",'Stars (1–5)',$r['stars'] ?? '','number',false);
        inputBox("body_$slot",'Review text',$r['body'] ?? '','textarea',false);
        echo '</fieldset>';
    }
    echo '<p>For automatic updates, configure GOOGLE_PLACES_API_KEY on the server with Places API (New) enabled. A listing URL containing placeid is required. Google can return up to five reviews.</p>';
}

function saveFeaturedReviews(): void {
    $rows=[];
    for ($slot=1; $slot<=FEATURED_REVIEW_SLOTS; $slot++) {
        $author=field("author_$slot",160,false);
        $stars=field("stars_$slot",2,false);
        $body=field("body_$slot",5000,false);
        if ($author==='' && $stars==='' && $body==='') continue;
        if ($author==='') throw new FieldError("author_$slot",'Enter the reviewer’s name.');
        if (!in_array($stars,['1','2','3','4','5'],true)) throw new FieldError("stars_$slot",'Enter a whole number from 1 to 5.');
        if ($body==='') throw new FieldError("body_$slot",'Enter the review text.');
        $rows[]=[$slot,$author,(int)$stars,$body];
    }
    query('DELETE FROM featured_reviews');
    foreach ($rows as $row) query('INSERT INTO featured_reviews(id,author,stars,body) VALUES(?,?,?,?)',$row);
}

function homepageReviews(string $listing): array {
    parse_str(parse_url($listing,PHP_URL_QUERY) ?: '',$params);
    $id=$params['placeid'] ?? $params['query_place_id'] ?? '';
    $key=getenv('GOOGLE_PLACES_API_KEY');
    if ($key && is_string($id) && preg_match('/^[A-Za-z0-9_-]{10,250}$/D',$id) && function_exists('curl_init')) {
        $curl=curl_init('https://places.googleapis.com/v1/places/'.rawurlencode($id));
        curl_setopt_array($curl,[
            CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>3, CURLOPT_CONNECTTIMEOUT=>1,
            CURLOPT_HTTPHEADER=>['X-Goog-Api-Key: '.$key,'X-Goog-FieldMask: reviews']
        ]);
        $response=curl_exec($curl);
        $ok=curl_getinfo($curl,CURLINFO_RESPONSE_CODE)===200;
        curl_close($curl);
        $place=$ok && is_string($response) ? json_decode($response,true) : null;
        $cards=[];
        foreach ($place['reviews'] ?? [] as $r) {
            $body=$r['originalText']['text'] ?? $r['text']['text'] ?? '';
            $author=$r['authorAttribution']['displayName'] ?? '';
            if ($body==='' || $author==='') continue;
            $cards[]=['author'=>$author,'body'=>$body,'stars'=>(int)$r['rating'],
                      'profile'=>$r['authorAttribution']['uri'] ?? '', 'url'=>$r['googleMapsUri'] ?? $listing];
        }
        if ($cards) return $cards;
    }
    return query('SELECT author,stars,body FROM featured_reviews ORDER BY id')->fetchAll();
}

function renderReviewCards(array $cards,string $listing): void {
    if (!$cards) return;
    echo '<div id="review-track" class="review-cards" role="region" aria-label="Customer reviews" tabindex="0">';
    foreach ($cards as $card) {
        $stars=max(1,min(5,(int)$card['stars']));
        $url=$card['url'] ?? $listing;
        if (!str_starts_with($url,'https://')) $url=$listing;
        $name=trim($card['author']);
        $words=preg_split('/\s+/u',$name);
        $initials=mb_substr($words[0],0,1);
        if(count($words)>1) $initials.=mb_substr(end($words),0,1);
        echo '<figure class="review-card"><div class="review-bubble"><blockquote dir="auto">'.nl2br(esc($card['body'])).'</blockquote>';
        echo '<button class="read-review" type="button" hidden>Read more</button>';
        echo '<span class="review-stars" role="img" aria-label="'.$stars.' out of 5 stars">'.str_repeat('★',$stars).str_repeat('☆',5-$stars).'</span></div>';
        echo '<figcaption><span class="review-initials" aria-hidden="true">'.esc(mb_strtoupper($initials)).'</span><span class="review-person">' ;
        if (!empty($card['profile']) && str_starts_with($card['profile'],'https://')) echo '<a href="'.esc($card['profile']).'">'.esc($name).'</a>';
        else echo esc($name);
        echo '<a class="review-source" href="'.esc($url).'" target="_blank" rel="noopener">Google Maps ↗</a></span></figcaption></figure>';
    }
    echo '</div>';
}
