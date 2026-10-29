<?php
require __DIR__ . '/../src/bootstrap.php';
require ROOT . '/src/view.php';

$pcols = 'p.id,p.province_id,p.name_en,p.name_kh,p.image_url,p.images,c.color cat_color,c.name_en cat_en,c.name_kh cat_kh, coalesce((select views from item_views v where v.item_type=\'place\' and v.item_id=p.id),0) views';
$provinces = rows('select * from provinces order by sort_order,id');
$places    = rows("select $pcols from places p left join categories c on c.id=p.category_id where p.is_active=1 order by p.sort_order,p.id");
$top       = rows("select $pcols from top_places t join places p on p.id=t.place_id left join categories c on c.id=p.category_id where p.is_active=1 order by t.sort_order,p.id");
$sponsors  = rows("select id,title,image_urls,link_url from sponsors where is_active=1 and (starts_on is null or starts_on<=current_date) and (ends_on is null or ends_on>=current_date) order by sort_order,id");
$fests     = rows('select * from festivals where is_active=1 order by sort_order,id');
$stats     = [
    t('stat_prov')  => count($provinces),
    t('stat_attr')  => count($places) . '+',
    t('stat_users') => (int)val('select count(*) from users'),
    t('stat_cat')   => (int)val('select count(*) from categories'),
    t('stat_lang')  => 2,
];
$byProv = []; $bySlug = [];
foreach ($places as $p) $byProv[$p['province_id']][] = $p;
foreach ($provinces as $pv) $bySlug[$pv['slug']] = $pv;
$regions = [];
foreach ($provinces as $pv) $regions[$pv['region'] ?: 'Other'][] = $pv;

$points = ['battambang'=>[28,35,22],'phnom-penh'=>[52,67,14],'siem-reap'=>[37,25,17],'kampot'=>[46,88,14],'kep'=>[51,94,10],'preah-sihanouk'=>[34,91,14],'kampong-thom'=>[52,36,20],'mondulkiri'=>[85,40,18],'ratanakiri'=>[88,18,15],'kratie'=>[72,42,17],'pursat'=>[34,47,17],'kampong-cham'=>[61,55,15],'kandal'=>[55,70,13],'takeo'=>[56,86,13],'koh-kong'=>[22,74,17],'kampong-speu'=>[43,65,13],'prey-veng'=>[62,67,13],'svay-rieng'=>[72,76,11],'preah-vihear'=>[51,15,14],'stung-treng'=>[75,23,15],'oddar-meanchey'=>[36,9,13],'banteay-meanchey'=>[24,20,14],'pailin'=>[22,40,9],'tboung-khmum'=>[68,59,12],'kampong-chhnang'=>[48,56,12]];
$settings = array_column(rows("select key,value from app_settings where key like 'contact_%'"), 'value', 'key');

if (!$top) {
    $top = rows("select $pcols from places p left join categories c on c.id=p.category_id where p.is_active=1 order by p.is_featured desc, views desc, p.sort_order, p.id limit 12");
}
$GLOBALS['popular'] = array_map('intval', array_column(rows("select item_id from item_views where item_type='place' and views > 0 order by views desc limit 3"), 'item_id'));
$slidesS = [];
foreach ($sponsors as $s) foreach ((images_of(['images' => $s['image_urls']]) ?: ['']) as $pic) $slidesS[] = ['pic' => $pic, 'title' => $s['title'], 'link' => $s['link_url']];

// --- DYNAMIC FESTIVAL ALERT ---
$current_alert = row("select * from festival_alerts where is_active=1 and (start_date is null or start_date<=current_date) and (end_date is null or end_date>=current_date) order by sort_order, id limit 1");
$alert_imgs = $current_alert ? array_values(array_filter(array_map('trim', explode('|', (string)($current_alert['images'] ?? ''))))) : [];
if ($current_alert && !$alert_imgs) $alert_imgs = ['images/Water Festival.jpg'];

page_head('Discover Cambodia — Complete Travel Guide');
nav();
?>
<main id="top">

<?php if ($current_alert):
$kh = lang() === 'kh';
$pickA = fn($en, $khk) => ($kh && !empty($current_alert[$khk])) ? $current_alert[$khk] : ($current_alert[$en] ?? '');
$a_title = $pickA('title_en', 'title_kh');
$a_body = $pickA('body_en', 'body_kh');
$a_loc = $pickA('location_en', 'location_kh');
$a_hl = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string)$pickA('highlights_en', 'highlights_kh')))));
$ds = !empty($current_alert['event_date']) ? strtotime($current_alert['event_date']) : null;
$de = !empty($current_alert['event_end']) ? strtotime($current_alert['event_end']) : $ds;
$when = '';
if ($ds) {
    if (date('Y-m-d', $ds) === date('Y-m-d', $de)) $when = date('M j, Y', $ds);
    elseif (date('Y-m', $ds) === date('Y-m', $de)) $when = date('M j', $ds) . '–' . date('j, Y', $de);
    else $when = date('M j', $ds) . ' – ' . date('M j, Y', $de);
}
$side = $ds || $a_hl;
?>
<div id="festival-alert" class="pro-alert" style="display:none;" data-id="<?= (int)$current_alert['id'] ?>" role="dialog" aria-label="<?= e($a_title) ?>">
  <div class="pro-alert-bg">
    <?php foreach ($alert_imgs as $i => $img): ?>
      <div class="pro-alert-img <?= $i === 0 ? 'on' : '' ?>" style="background-image:url('<?= e(img($img)) ?>')"></div>
    <?php endforeach; ?>
    <div class="pro-alert-overlay"></div>
    <span class="pro-spark"></span>
  </div>
  <button type="button" class="pro-alert-close" aria-label="Close">✕</button>
  <div class="pro-alert-content<?= $side ? '' : ' solo' ?>">
    <div class="pro-alert-text">
      <span class="pro-badge">🎉 <?= $kh ? 'ព្រឹត្តិការណ៍ខាងមុខ' : 'Upcoming Event' ?></span>
      <h4><?= e($a_title) ?></h4>
      <?php if ($when || $a_loc): ?>
      <div class="pro-meta">
        <?php if ($when): ?><span class="pro-pill">📅 <?= e($when) ?></span><?php endif; ?>
        <?php if ($a_loc): ?><span class="pro-pill">📍 <?= e($a_loc) ?></span><?php endif; ?>
      </div>
      <?php endif; ?>
      <?php if ($a_body): ?><p><?= e($a_body) ?></p><?php endif; ?>
      <?php if (!empty($current_alert['link_url'])): ?>
        <a href="<?= e($current_alert['link_url']) ?>" class="btn-gold upper"><?= $kh ? 'ស្វែងយល់បន្ថែម' : 'Learn More' ?></a>
      <?php endif; ?>
    </div>
    <?php if ($side): ?>
    <aside class="pro-side">
      <?php if ($ds): ?>
      <div class="pro-count" data-start="<?= e(date('Y-m-d', $ds)) ?>" data-end="<?= e(date('Y-m-d', $de)) ?>">
        <h5><?= $kh ? 'រាប់ថយក្រោយ' : 'Starts in' ?></h5>
        <div class="pro-count-grid">
          <div><b data-u="d">00</b><span><?= $kh ? 'ថ្ងៃ' : 'Days' ?></span></div>
          <div><b data-u="h">00</b><span><?= $kh ? 'ម៉ោង' : 'Hours' ?></span></div>
          <div><b data-u="m">00</b><span><?= $kh ? 'នាទី' : 'Min' ?></span></div>
          <div><b data-u="s">00</b><span><?= $kh ? 'វិនាទី' : 'Sec' ?></span></div>
        </div>
        <div class="pro-live" hidden>🎊 <?= $kh ? 'កំពុងប្រារព្ធឥឡូវនេះ!' : 'Happening now!' ?></div>
      </div>
      <?php endif; ?>
      <?php if ($a_hl): ?>
      <div>
        <h5><?= $kh ? 'ចំណុចលេចធ្លោ' : 'Highlights' ?></h5>
        <ul class="pro-list"><?php foreach ($a_hl as $h): ?><li><?= e($h) ?></li><?php endforeach; ?></ul>
      </div>
      <?php endif; ?>
    </aside>
    <?php endif; ?>
  </div>
</div>
<script>
(function () {
  var box = document.getElementById('festival-alert');
  var key = 'fest_closed_<?= (int)$current_alert['id'] ?>';
  var today = '<?= date('Y-m-d') ?>';
  var closed = false;
  try { closed = localStorage.getItem(key) === today; } catch (e) {}
  if (closed) return;
  box.style.display = 'flex';
  box.querySelector('.pro-alert-close').addEventListener('click', function () {
    box.style.display = 'none';
    try { localStorage.setItem(key, today); } catch (e) {}
  });
  var imgs = box.querySelectorAll('.pro-alert-img');
  if (imgs.length > 1) {
    var idx = 0;
    setInterval(function () {
      imgs[idx].classList.remove('on');
      idx = (idx + 1) % imgs.length;
      imgs[idx].classList.add('on');
    }, 4000);
  }
  var c = box.querySelector('.pro-count');
  if (!c) return;
  var s = new Date(c.dataset.start + 'T00:00:00+07:00').getTime();
  var en = new Date(c.dataset.end + 'T23:59:59+07:00').getTime();
  var grid = c.querySelector('.pro-count-grid');
  var live = c.querySelector('.pro-live');
  var title = c.querySelector('h5');
  function pad(v) { return v < 10 ? '0' + v : '' + v; }
  function set(u, v) { c.querySelector('[data-u="' + u + '"]').textContent = pad(v); }
  var timer = setInterval(tick, 1000);
  function tick() {
    var now = Date.now();
    if (now > en) { c.style.display = 'none'; clearInterval(timer); return; }
    if (now >= s) { grid.style.display = 'none'; title.style.display = 'none'; live.hidden = false; clearInterval(timer); return; }
    var d = s - now;
    set('d', Math.floor(d / 864e5));
    set('h', Math.floor(d % 864e5 / 36e5));
    set('m', Math.floor(d % 36e5 / 6e4));
    set('s', Math.floor(d % 6e4 / 1e3));
  }
  tick();
})();
</script>
<?php endif; ?>

<section class="hero"><i class="orb o1"></i><i class="orb o2"></i><div>
  <p class="kicker upper"><?= e(t('kicker')) ?></p>
  <h1><?= e(t('hero_a')) ?><em><?= e(t('hero_b')) ?></em></h1>
  <p class="lead"><?= e(t('hero_text')) ?></p>
  <div class="hero-cta">
    <a href="#provinces"><button class="btn-gold upper"><?= e(t('explore')) ?></button></a>
    <a href="<?= u('planner.php') ?>"><button class="btn-ghost upper"><?= e(t('planner')) ?></button></a>
  </div>
  <div class="stats"><?php foreach ($stats as $label => $n): ?><div><b data-count="<?= e(is_numeric($n) ? $n : rtrim((string)$n, '+')) ?>"><?= e($n) ?></b><span class="upper"><?= e($label) ?></span></div><?php endforeach ?></div>
  <a class="scroll-down" href="#map" aria-label="Scroll down">⌄</a>
</div></section>

<?php if ($slidesS): ?>
<section class="wrap reveal" style="padding-top:20px"><div class="sponsor" id="sponsor">
  <?php foreach ($slidesS as $k => $s): ?>
    <a class="<?= $k ? '' : 'on' ?>" href="<?= e($s['link'] ?: '#') ?>" target="_blank" rel="noopener sponsored" style="background-image:url('<?= e(img($s['pic'])) ?>')"><span class="upper">Sponsored · <?= e($s['title']) ?></span></a>
  <?php endforeach ?>
  <?php if (count($slidesS) > 1): ?>
    <button class="sarrow prev" aria-label="Previous">‹</button><button class="sarrow next" aria-label="Next">›</button>
    <div class="sdots"><?php foreach ($slidesS as $k => $s): ?><i class="<?= $k ? '' : 'on' ?>"></i><?php endforeach ?></div>
  <?php endif ?>
</div></section>
<?php endif ?>

<section class="section reveal" id="map"><div class="wrap maprow">
  <div class="mapcard">
    <p class="label upper"><?= e(t('map_label')) ?></p>
    <h2 class="sec-title"><?= e(t('map_a')) ?> <em><?= e(t('map_b')) ?></em></h2>
    <p><?= e(t('map_text')) ?></p>
    <div class="chips"><?php foreach (['battambang', 'siem-reap', 'phnom-penh'] as $s) if (isset($bySlug[$s])): ?>
      <button class="chip" data-prov="<?= e($s) ?>"><?= e(pick($bySlug[$s])) ?></button><?php endif ?></div>
  </div>
  <div class="mapbox">
    <div class="mapimg"><img src="<?= u('assets/img/cambodia-province-map.png') ?>" alt="Map of Cambodia">
      <?php foreach ($points as $slug => [$x, $y, $sz]) if (isset($bySlug[$slug])): ?>
        <button class="mdot" style="--x:<?= $x ?>%;--y:<?= $y ?>%;--s:<?= $sz ?>px" data-prov="<?= e($slug) ?>" data-name="<?= e(pick($bySlug[$slug])) ?>" aria-label="<?= e($bySlug[$slug]['name_en']) ?>"></button>
      <?php endif ?>
    </div>
    <div class="msel"><small class="upper"><?= e(t('selected')) ?></small><b id="mselName"><?= e(t('choose')) ?></b><span id="mselHint"><?= e(t('tap')) ?></span><button class="chip" id="mselOpen" data-prov="" disabled><?= e(t('open')) ?></button></div>
  </div>
</div></section>

<?php if ($top): ?>
<section class="section reveal" id="featured">
  <div class="wrap">
    <p class="label upper"><?= e(t('must')) ?></p>
    <h2 class="sec-title"><?= e(t('top_a')) ?> <em><?= e(t('top_b')) ?></em></h2>
    <div class="clip grid" data-clip><?php foreach ($top as $p) place_card($p); ?></div>
    <?php if (count($top) > 2): ?><button type="button" class="clip-more upper" data-clip-btn><?= e(t('show_more')) ?></button><?php endif ?>
  </div>
</section>
<?php endif ?>

<section class="section reveal" id="provinces"><div class="wrap">
  <p class="label upper"><?= e(t('all_prov')) ?></p>
  <h2 class="sec-title"><?= e(t('choose_a')) ?> <em><?= e(t('choose_b')) ?></em></h2>
  <?php foreach ($regions as $region => $list): ?>
    <p class="region upper"><?= e($region) ?></p>
    <div class="clip grid" data-clip>
    <?php foreach ($list as $pv): $c = $pv['cover_color'] ?: '#7A3B10'; ?>
      <div class="card reveal" data-prov="<?= e($pv['slug']) ?>" style="background:linear-gradient(160deg,<?= e($c) ?> 0%,<?= e($c) ?>cc 100%)">
        <div class="skeleton"></div>
        <?php slides($pv); ?><div class="grad"></div>
        <?php if ($pv['is_featured']): ?><span class="pill upper">Featured</span><?php endif ?>
        <div class="txt"><b><?= e($pv['name_en']) ?></b><span><?= e($pv['name_kh']) ?></span><span class="cnt upper"><?= (int)($byProv[$pv['id']] ? count($byProv[$pv['id']]) : 0) ?> <?= e(t('attractions')) ?></span></div>
        <div class="cta"><button class="upper"><?= e(t('open')) ?></button></div>
      </div>
    <?php endforeach ?>
    </div>
    <?php if (count($list) > 2): ?><button type="button" class="clip-more upper" data-clip-btn><?= e(t('show_more')) ?></button><?php endif ?>
  <?php endforeach ?>
</div></section>

<section class="section reveal" id="all-places"><div class="wrap">
  <p class="label upper"><?= e(t('explore_all')) ?></p>
  <h2 class="sec-title"><?= e(t('all_a')) ?> <em><?= e(t('all_b')) ?></em></h2>
  <?php foreach ($provinces as $pv) if (!empty($byProv[$pv['id']])): ?>
    <div class="pgroup">
      <p class="region upper"><?= e(pick($pv)) ?> · <?= count($byProv[$pv['id']]) ?></p>
      <div class="clip grid" data-clip><?php foreach ($byProv[$pv['id']] as $p) place_card($p); ?></div>
      <?php if (count($byProv[$pv['id']]) > 2): ?><button type="button" class="clip-more upper" data-clip-btn><?= e(t('show_more')) ?></button><?php endif ?>
    </div>
  <?php endif ?>
</div></section>

<?php if ($fests): ?>
<section class="section reveal" id="festivals"><div class="wrap">
  <p class="label upper"><?= e(t('fest_label')) ?></p>
  <h2 class="sec-title"><?= e(t('fest_a')) ?> <em><?= e(t('fest_b')) ?></em></h2>
  <div class="clip grid fest" data-clip>
  <?php foreach ($fests as $f): ?>
    <div class="fcard reveal" <?= $f['place_id'] ? 'data-place="' . (int)$f['place_id'] . '"' : '' ?> style="border-top:3px solid <?= e($f['accent_color'] ?: 'var(--gold)') ?>;<?= $f['place_id'] ? 'cursor:pointer' : '' ?>">
      <b><?= e($f['name_en']) ?></b><span><?= e($f['name_kh']) ?></span>
    </div>
  <?php endforeach ?>
  </div>
  <?php if (count($fests) > 2): ?><button type="button" class="clip-more upper" data-clip-btn><?= e(t('show_more')) ?></button><?php endif ?>
</div></section>
<?php endif ?>

<section class="section reveal" id="services"><div class="wrap">
  <p class="label upper"><?= e(t('svc_label')) ?></p>
  <h2 class="sec-title"><?= e(t('svc_title')) ?></h2>
  <div class="clip grid svc" data-clip>
  <?php foreach ([1, 2, 3, 4, 5] as $n): ?>
    <div class="scard reveal"><h3><?= e(t("svc$n")) ?></h3>
      <a class="btn-gold upper" href="<?= [1 => u('guides.php'), 2 => u('apply.php?type=guide'), 3 => u('transport.php'), 4 => u('apply.php?type=transport'), 5 => u('contact.php')][$n] ?>"><?= e(t("svc{$n}b")) ?></a></div>
  <?php endforeach ?>
  </div>
  <button type="button" class="clip-more upper" data-clip-btn><?= e(t('show_more')) ?></button>
</div></section>

<section class="section donate reveal"><div class="wrap"><h2 class="sec-title"><?= e(t('donate_t')) ?></h2><a class="btn-gold upper" href="<?= u('donate.php') ?>"><?= e(t('donate_b')) ?></a></div></section>
</main>

<footer id="contact">
  <div class="foot-grid">
    <div>
      <h4>Discover Cambodia</h4>
      <p><?= e(t('footer')) ?></p>
      <p><?= e(t('built')) ?> · ខ្មែរ</p>
    </div>
    <div>
      <h4><?= e(t('places')) ?></h4>
      <a href="#provinces"><?= e(t('provinces')) ?></a>
      <a href="#map"><?= e(t('map')) ?></a>
      <a href="#featured"><?= e(t('featured')) ?></a>
      <a href="#festivals"><?= e(t('festivals')) ?></a>
    </div>
    <div>
      <h4><?= e(t('services')) ?></h4>
      <a href="<?= u('guides.php') ?>"><?= e(t('svc1')) ?></a>
      <a href="<?= u('transport.php') ?>"><?= e(t('svc3')) ?></a>
      <a href="<?= u('planner.php') ?>"><?= e(t('planner')) ?></a>
      <a href="<?= u('donate.php') ?>"><?= e(t('donate_b')) ?></a>
    </div>
    <div>
      <h4><?= e(t('contact')) ?></h4>
      <a href="<?= u('contact.php') ?>"><?= e(t('contact')) ?></a>
      <?php if (!empty($settings['contact_telegram'])): ?><a href="<?= e($settings['contact_telegram']) ?>" target="_blank" rel="noopener">Telegram</a><?php endif ?>
      <?php if (!empty($settings['contact_email'])): ?><a href="mailto:<?= e($settings['contact_email']) ?>"><?= e($settings['contact_email']) ?></a><?php endif ?>
    </div>
  </div>
  <div class="flag-strip"><i></i><i></i><i></i></div>
  <p class="foot-copy">© <?= date('Y') ?> Discover Cambodia</p>
</footer>
<?php page_foot();