<?php
// Isolated renderer fixtures, not a WordPress integration environment.
define('ABSPATH', __DIR__);
define('DAY_IN_SECONDS',86400); define('WEEK_IN_SECONDS',604800);
$GLOBALS['options']=['scg_settings'=>['cache_time'=>86400]];
$GLOBALS['transients']=[]; $GLOBALS['queries']=[]; $GLOBALS['head']=false;
function add_action(...$a) {} function register_activation_hook(...$a) {} function add_shortcode(...$a) {}
function get_option($k,$d=false) {return $GLOBALS['options'][$k] ?? $d;}
function update_option($k,$v,$a=null) {$GLOBALS['options'][$k]=$v;}
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
$GLOBALS['empty']=true;check($p->renderGrid($a)==='','empty result');
echo "Renderer/cache fixtures passed\n";
