<?php
// Isolated renderer fixtures, not a WordPress integration environment.
define('ABSPATH', __DIR__);
define('HOUR_IN_SECONDS',3600); define('DAY_IN_SECONDS',86400); define('WEEK_IN_SECONDS',604800);
$GLOBALS['options']=['scg_settings'=>['cache_time'=>86400]];
$GLOBALS['transients']=[]; $GLOBALS['queries']=[]; $GLOBALS['head']=false;
$GLOBALS['actions']=[]; $GLOBALS['updates']=0;
$GLOBALS['site_transients']=[]; $GLOBALS['http']=null; $GLOBALS['http_calls']=0;
function add_action(...$a) {} function register_activation_hook(...$a) {} function add_shortcode(...$a) {}
function add_filter(...$a) {}
function plugin_basename($f) {return 'smart-categories-grid/smart-categories-grid.php';}
function get_site_transient($k) {return $GLOBALS['site_transients'][$k] ?? false;}
function set_site_transient($k,$v,$t) {$GLOBALS['site_transients'][$k]=$v;}
function wp_remote_get($u,$a=[]) {$GLOBALS['http_calls']++;return $GLOBALS['http'];}
function wp_remote_retrieve_response_code($r) {return $r['code'];}
function wp_remote_retrieve_body($r) {return $r['body'];}
function did_action($h) {return 0;}
function do_action($h,...$a) {$GLOBALS['actions'][]=$h;}
function get_option($k,$d=false) {return $GLOBALS['options'][$k] ?? $d;}
function update_option($k,$v,$a=null) {$GLOBALS['options'][$k]=$v;$GLOBALS['updates']++;}
function delete_option($k) {unset($GLOBALS['options'][$k]);}
function wp_generate_uuid4() {static $i=0;return 'fixture-'.++$i;}
function get_locale() {return 'en_US';}
function wp_json_encode($a) {return json_encode($a);}
function get_transient($k) {return $GLOBALS['transients'][$k] ?? false;}
function set_transient($k,$v,$t) {$GLOBALS['transients'][$k]=$v;}
function absint($v) {return abs((int)$v);}
function sanitize_hex_color($v) {return preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i',$v)?$v:null;}
function esc_attr($v) {return htmlspecialchars((string)$v,ENT_QUOTES);}
function esc_html($v) {return esc_attr($v);} function esc_url($v) {return esc_attr($v);}
function esc_html__($v,$d) {return $v;}
function wp_style_is($h,$s) {return true;}
function get_block_wrapper_attributes() {return 'class="wp-block"';}
function get_terms($a) {
    $GLOBALS['queries'][]=$a;
    if (!empty($GLOBALS['empty'])) return [];
    return [(object)['term_id'=>2,'name'=>'Beta'],(object)['term_id'=>1,'name'=>'Alpha']];
}
function get_term_link($v,$t=null) {return '/category/'.(is_object($v)?$v->term_id:$v);}
function is_wp_error($v) {return false;}
function wp_lazy_loading_enabled($a,$b) {return true;}
function check($ok,$message) {if(!$ok)throw new RuntimeException($message);}
function lastQuery() {$q=$GLOBALS['queries'];return $q[count($q)-1];}
require dirname(__DIR__,2).'/smart-categories-grid.php';
$p=SmartCategoriesGrid::getInstance();$p->init();
$a=['type'=>'top-level','style'=>'text','hide_empty'=>'true','limit'=>'1'];
$x=$p->renderGrid($a);
check(strpos($x,'Alpha')!==false && strpos($x,'Beta')===false,'limit and alphabetical order');
check($GLOBALS['queries'][0]['parent']===0 && $GLOBALS['queries'][0]['hierarchical']===true && $GLOBALS['queries'][0]['hide_empty']===true,'core hierarchical empty filtering');
check(strpos($x,'<img')===false,'text mode has no images');
check($x===$p->renderGrid($a) && count($GLOBALS['queries'])===1,'cache hit');
$p->onTermCacheClean([1],'post_tag');$p->renderGrid($a);
check(count($GLOBALS['queries'])===1,'unrelated taxonomy does not clear grids');
$n=count($GLOBALS['transients']);
$p->onTermCacheClean([1],'category');$p->renderGrid($a);
check(count($GLOBALS['queries'])===2,'category count update invalidates grids');
check(count($GLOBALS['transients'])===$n,'invalidation overwrites the cache entry instead of adding one');
check($x===$p->renderGrid($a) && count($GLOBALS['queries'])===2,'regenerated entry is served from cache');
check(!isset($GLOBALS['options']['scg_cache_gen']) && $GLOBALS['updates']===0,'generation token is not written before shutdown');
$p->onTermCacheClean([1],'category');$p->onTermCacheClean([2],'category');$p->persistCacheGeneration();
check($GLOBALS['updates']===1 && isset($GLOBALS['options']['scg_cache_gen']) && $GLOBALS['actions']===['scg_cache_cleared'],'several invalidations in one request are stored once');
$p->renderGrid(array_merge($a,['hide_empty'=>'false']));
check(count($GLOBALS['queries'])===3,'hide_empty changes the cache key');
$p->renderGrid(array_merge($a,['force_update'=>'true']));
check(count($GLOBALS['queries'])===4,'force update bypasses cache');
$GLOBALS['options']['scg_settings']=['cache_time'=>0];$p->init();
$p->renderGrid($a);$p->renderGrid($a);
check(count($GLOBALS['queries'])===6,'disabled caching bypasses reads');
$GLOBALS['options']['scg_settings']=['cache_time'=>0,'default_category'=>5];$p->init();
$b=$p->renderBlock(['categoryId'=>0,'type'=>'subcategories','style'=>'text']);
check(lastQuery()['parent']===5 && strpos($b,'class="wp-block"')!==false,'block without a selected parent inherits the default category');
$p->renderBlock(['categoryId'=>7,'type'=>'subcategories','style'=>'text']);
check(lastQuery()['parent']===7,'block uses an explicitly selected parent');
$GLOBALS['actions']=[];$GLOBALS['empty']=true;
$p->onAttachmentChange(77);$p->persistCacheGeneration();
check($GLOBALS['actions']===[],'unrelated attachment edits do not invalidate grids');
$GLOBALS['empty']=false;
$p->onAttachmentChange(77);$p->persistCacheGeneration();
check($GLOBALS['actions']===['scg_cache_cleared'],'category image edits invalidate grids');
$f='smart-categories-grid/smart-categories-grid.php';
$z='https://github.com/gemuzkm/smart-categories-grid/releases/download/v2.9.0/smart-categories-grid-2.9.0.zip';
$GLOBALS['http']=['code'=>200,'body'=>json_encode(['tag_name'=>'v2.9.0','body'=>'Notes','assets'=>[['browser_download_url'=>$z.'.sha256'],['browser_download_url'=>$z]]])];
$u=$p->checkForUpdate(false,[],$f);
check(is_array($u) && $u['version']==='2.9.0' && $u['package']===$z,'update offer uses the packaged release ZIP');
$p->checkForUpdate(false,[],$f);
check($GLOBALS['http_calls']===1,'release lookup is cached');
check($p->checkForUpdate(false,[],'other/other.php')===false,'other plugins are left alone');
$GLOBALS['site_transients']=[];
$GLOBALS['http']=['code'=>200,'body'=>json_encode(['tag_name'=>'v2.9.0','assets'=>[['browser_download_url'=>'https://example.com/smart-categories-grid-2.9.0.zip']]])];
check($p->checkForUpdate(false,[],$f)===false,'packages outside the repository releases are rejected');
$GLOBALS['empty']=true;check($p->renderGrid($a)==='','empty result');
echo "Renderer/cache fixtures passed\n";
