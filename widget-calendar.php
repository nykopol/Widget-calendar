<?php
/*
Plugin Name: Custom Widget Calendar
Description: Ajoute un évènement sur le calendrier depuis les posts à une date différente de la publication.
Version: 1.0
Author: Desmyter Johan
*/

class Custom_Widget_Calendar extends WP_Widget {

	function Custom_Widget_Calendar() {
		$widget_ops = array('classname' => 'custom_widget_calendar', 'description' => __( 'Calendrier des évènements enregistrés') );
		$this->WP_Widget('custom_calendar', __('Custom Calendar'), $widget_ops);
	}

	function widget( $args, $instance ) {
		extract($args);
		$title = apply_filters('widget_title', empty($instance['title']) ? '&nbsp;' : $instance['title'], $instance, $this->id_base);
		echo $before_widget;
		if ( $title )
			echo $before_title . $title . $after_title;
		
		get_custom_calendar();
		
		echo $after_widget;
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);

		return $instance;
	}

	function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, array( 'title' => '' ) );
		$title = strip_tags($instance['title']);
?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" /></p>
<?php
	}
}

add_action('widgets_init', create_function('', 'return register_widget("Custom_Widget_Calendar");'));


function get_custom_calendar($initial = true, $echo = true) {
	global $wpdb, $m, $monthnum, $year, $wp_locale, $posts;
	global $wp_object_cache;
	
	$cache = array();
	$key = md5( $m . $monthnum . $year );
	/*if($cache = get_option('custom_calendar_datas', false)){ 
		if(is_array($cache) && isset($cache[$key])){ 
			if($echo){
				echo apply_filters('get_custom_calendar', $cache[$key]);
				return;
			}else{
				return apply_filters('get_custom_calendar', $cache[$key]);
			}
		}
	}*/

	if (!is_array($cache))
		$cache = array();

	// Quick check. If we have no posts at all, abort!
	/*if ( !$posts ) {
		$gotsome = $wpdb->get_var("SELECT 1 as test 
			FROM $wpdb->posts
			LEFT JOIN $wpdb->term_relationships
				ON $wpdb->posts.ID = $wpdb->term_relationships.object_id
			LEFT JOIN $wpdb->term_taxonomy 
				ON $wpdb->term_taxonomy.term_taxonomy_id = $wpdb->term_relationships.term_taxonomy_id
			WHERE 
				$wpdb->posts.post_status = 'publish'
				AND $wpdb->term_taxonomy.taxonomy = 'calendar'
			LIMIT 1");
		if ( !$gotsome ) {
			$cache[ $key ] = '';
			wp_cache_set( 'get_custom_calendar', $cache, 'calendar' );
			return;
		}
	}*/

	if ( isset($_GET['w']) )
		$w = ''.intval($_GET['w']);

	// week_begins = 0 stands for Sunday
	$week_begins = intval(get_option('start_of_week'));

	// Let's figure out when we are
	if ( !empty($monthnum) && !empty($year) ) {
		$thismonth = ''.zeroise(intval($monthnum), 2);
		$thisyear = ''.intval($year);
	} elseif ( !empty($w) ) {
		// We need to get the month from MySQL
		$thisyear = ''.intval(substr($m, 0, 4));
		$d = (($w - 1) * 7) + 6; //it seems MySQL's weeks disagree with PHP's
		$thismonth = $wpdb->get_var("SELECT DATE_FORMAT((DATE_ADD('${thisyear}0101', INTERVAL $d DAY) ), '%m')");
	} elseif ( !empty($m) ) {
		$thisyear = ''.intval(substr($m, 0, 4));
		if ( strlen($m) < 6 )
				$thismonth = '01';
		else
				$thismonth = ''.zeroise(intval(substr($m, 4, 2)), 2);
	} else {
		$thisyear = gmdate('Y', current_time('timestamp'));
		$thismonth = gmdate('m', current_time('timestamp'));
	}

	$unixmonth = mktime(0, 0 , 0, $thismonth, 1, $thisyear);


	// Get the next and previous month and year with at least one post
	/*$previous = $wpdb->get_row("SELECT DISTINCT MONTH($wpdb->term_taxonomy.description) AS month, YEAR($wpdb->term_taxonomy.description) AS year
		FROM $wpdb->posts
		LEFT JOIN $wpdb->term_relationships
			ON $wpdb->posts.ID = $wpdb->term_relationships.object_id
		LEFT JOIN $wpdb->term_taxonomy 
			ON $wpdb->term_taxonomy.term_taxonomy_id = $wpdb->term_relationships.term_taxonomy_id
		WHERE $wpdb->term_taxonomy.description < '$thisyear-$thismonth-01'
			AND $wpdb->term_taxonomy.taxonomy = 'calendar'
			AND $wpdb->posts.post_type = 'post' 
			AND $wpdb->posts.post_status = 'publish'
			ORDER BY $wpdb->term_taxonomy.description DESC
			LIMIT 1");

	$next = $wpdb->get_row("SELECT	DISTINCT MONTH($wpdb->term_taxonomy.description) AS month, YEAR($wpdb->term_taxonomy.description) AS year
		FROM $wpdb->posts
		LEFT JOIN $wpdb->term_relationships
			ON $wpdb->posts.ID = $wpdb->term_relationships.object_id
		LEFT JOIN $wpdb->term_taxonomy 
			ON $wpdb->term_taxonomy.term_taxonomy_id = $wpdb->term_relationships.term_taxonomy_id
		WHERE $wpdb->term_taxonomy.description >	'$thisyear-$thismonth-01'
			AND $wpdb->term_taxonomy.taxonomy = 'calendar'
			AND $wpdb->posts.post_type = 'post' 
			AND $wpdb->posts.post_status = 'publish'
			AND MONTH( $wpdb->term_taxonomy.description ) != MONTH( '$thisyear-$thismonth-01' )
			ORDER	BY $wpdb->term_taxonomy.description ASC
			LIMIT 1");*/
	
	$previous = new DateTime("$thisyear-$thismonth-01");
	$previous->modify('-1 month');
	
	$next = new DateTime("$thisyear-$thismonth-01");
	$next->modify('+1 month');

	/* translators: Calendar caption: 1: month name, 2: 4-digit year */
	$calendar_caption = _x('%1$s %2$s', 'calendar caption');
	
	$calendar_output = '<div class="calendar"><table>';
	/*if($previous){
		$calendar_output .= '<a href="'.get_month_link($previous->year, $previous->month).'" class="ui-datepicker-prev ui-corner-all" title="'.sprintf(__('View posts for %1$s %2$s'), $wp_locale->get_month($previous->month), date('Y', mktime(0, 0 , 0, $previous->month, 1, $previous->year))).'"><span class="ui-icon ui-icon-circle-triangle-w">&lt;Préc</span></a>';
	}
	if($next){
		$calendar_output .= '<a href="'.get_month_link($next->year, $next->month).'" class="ui-datepicker-next ui-corner-all" title="'.sprintf(__('View posts for %1$s %2$s'), $wp_locale->get_month($next->month), date('Y', mktime(0, 0 , 0, $next->month, 1, $next->year))).'"><span class="ui-icon ui-icon-circle-triangle-e">Suiv&gt;</span></a>';
	}*/
	$calendar_output .= '<caption>'.ucfirst(sprintf($calendar_caption, $wp_locale->get_month($thismonth), date('Y', $unixmonth))).'</caption>';

	$calendar_output .= '<thead><tr><th><span title="Lundi">Lu</span></th><th><span title="Mardi">Ma</span></th><th><span title="Mercredi">Me</span></th><th><span title="Jeudi">Je</span></th><th><span title="Vendredi">Ve</span></th><th class="ui-datepicker-week-end"><span title="Samedi">Sa</span></th><th class="ui-datepicker-week-end"><span title="Dimanche">Di</span></th></tr></thead>';
	
	$calendar_output .= '<tfoot><tr><td colspan="2" class="before">';
	
	$calendar_output .= '<span id="date-'.$previous->format('Y-m').'">&laquo;</span>';
		
	$calendar_output .= '</td><td colspan="3" class="loading"><img src="'.get_bloginfo('template_url').'/images/loader.gif" style="display:none;height:13px;" alt="chargement" /></td><td colspan="2" class="after">';
	
	$calendar_output .= '<span id="date-'.$next->format('Y-m').'">&raquo;</span>';
	
	$calendar_output .= '</td></tr></tfoot><tbody><tr>';
	
	/* TODO : mettre les jours bien avec le code suivant;
	$myweek = array();

	for ( $wdcount=0; $wdcount<=6; $wdcount++ ) {
		$myweek[] = $wp_locale->get_weekday(($wdcount+$week_begins)%7);
	}

	foreach ( $myweek as $wd ) {
		$day_name = (true == $initial) ? $wp_locale->get_weekday_initial($wd) : $wp_locale->get_weekday_abbrev($wd);
		$wd = esc_attr($wd);
		$calendar_output .= "\n\t\t<th scope=\"col\" title=\"$wd\">$day_name</th>";
	}*/

	

	
	$sql = "SELECT DISTINCT DAYOFMONTH($wpdb->term_taxonomy.description)
		FROM $wpdb->posts
			LEFT JOIN $wpdb->term_relationships
				ON $wpdb->posts.ID = $wpdb->term_relationships.object_id
			LEFT JOIN $wpdb->term_taxonomy 
				ON $wpdb->term_taxonomy.term_taxonomy_id = $wpdb->term_relationships.term_taxonomy_id
		WHERE MONTH($wpdb->term_taxonomy.description) = '$thismonth'
			AND $wpdb->posts.post_status = 'publish'
			AND $wpdb->term_taxonomy.taxonomy = 'calendar'
			AND YEAR($wpdb->term_taxonomy.description) = '$thisyear'
			AND $wpdb->posts.post_type = 'post'";
	
	$dayswithposts = $wpdb->get_results($sql, ARRAY_N);
	
	if ( $dayswithposts ) {
		foreach ( (array) $dayswithposts as $daywith ) {
			$daywithpost[] = $daywith[0];
		}
	} else {
		$daywithpost = array();
	}

	$ak_title_separator = "\n";

	$ak_titles_for_day = array();
	$ak_post_titles = $wpdb->get_results("SELECT 
			$wpdb->posts.ID AS ID, 
			$wpdb->posts.post_title AS post_title, 
			DAYOFMONTH($wpdb->term_taxonomy.description) as dom 
		FROM $wpdb->posts
		LEFT JOIN $wpdb->term_relationships
			ON $wpdb->posts.ID = $wpdb->term_relationships.object_id
		LEFT JOIN $wpdb->term_taxonomy 
			ON $wpdb->term_taxonomy.term_taxonomy_id = $wpdb->term_relationships.term_taxonomy_id
		WHERE YEAR($wpdb->term_taxonomy.description) = '$thisyear' 
			AND $wpdb->term_taxonomy.taxonomy = 'calendar'
			AND MONTH($wpdb->term_taxonomy.description) = '$thismonth' 
			AND $wpdb->term_taxonomy.description < '".current_time('mysql')."' 
			AND $wpdb->posts.post_type = 'post' 
			AND $wpdb->posts.post_status = 'publish'"
	);
	if ( $ak_post_titles ) {
		foreach ( (array) $ak_post_titles as $ak_post_title ) {

				$post_title = esc_attr( apply_filters( 'the_title', $ak_post_title->post_title, $ak_post_title->ID ) );

				if ( empty($ak_titles_for_day['day_'.$ak_post_title->dom]) )
					$ak_titles_for_day['day_'.$ak_post_title->dom] = '';
				if ( empty($ak_titles_for_day["$ak_post_title->dom"]) ) // first one
					$ak_titles_for_day["$ak_post_title->dom"] = $post_title;
				else
					$ak_titles_for_day["$ak_post_title->dom"] .= $ak_title_separator . $post_title;
		}
	}


	// See how much we should pad in the beginning
	$pad = calendar_week_mod(date('w', $unixmonth)-$week_begins);
	if ( 0 != $pad )
		$calendar_output .= "\n\t\t".'<td colspan="'. esc_attr($pad) .'" class="pad">&nbsp;</td>';

	$daysinmonth = intval(date('t', $unixmonth));
	for ( $day = 1; $day <= $daysinmonth; ++$day ) {
		if ( isset($newrow) && $newrow )
			$calendar_output .= "\n\t</tr>\n\t<tr>\n\t\t";
		$newrow = false;

		if ( $day == gmdate('j', current_time('timestamp')) && $thismonth == gmdate('m', current_time('timestamp')) && $thisyear == gmdate('Y', current_time('timestamp')) )
			$calendar_output .= '<td class="today">';
		elseif(in_array($day, $daywithpost))
			$calendar_output .= '<td class="has_post">';
		else
			$calendar_output .= '<td>';

		if ( in_array($day, $daywithpost)){  // any posts today?
			$calendar_output .= '<a href="' . get_calendar_day_link($thisyear, $thismonth, $day) . "\">$day 
				<span class='calendar_desc'><span class='calendar_title'>$day ".sprintf($calendar_caption, $wp_locale->get_month($thismonth), date('Y', $unixmonth)).'</span>'.nl2br($ak_titles_for_day[$day])."</span></a>";
		}else
			$calendar_output .= $day;
			
		$calendar_output .= '</td>';

		if ( 6 == calendar_week_mod(date('w', mktime(0, 0 , 0, $thismonth, $day, $thisyear))-$week_begins) )
			$newrow = true;
	}
	

	$pad = 7 - calendar_week_mod(date('w', mktime(0, 0 , 0, $thismonth, $day, $thisyear))-$week_begins);
	if ( $pad != 0 && $pad != 7 )
		$calendar_output .= "\n\t\t".'<td class="pad" colspan="'. esc_attr($pad) .'">&nbsp;</td>';

	$calendar_output .= "\n\t</tr>\n\t</tbody>\n\t</table>";
	$calendar_output .= '</div>';

	$cache[$key] = $calendar_output;
	update_option('custom_calendar_datas', $cache);

	if ( $echo )
		echo apply_filters( 'get_custom_calendar',  $calendar_output );
	else
		return apply_filters( 'get_custom_calendar',  $calendar_output );

}


add_action('edit_post', 'check_for_valid_dates_taxo');
function check_for_valid_dates_taxo($post_id){
	global $wp_locale, $wpdb;
	
	$taxonomy = get_object_taxonomies('post');
	if(!in_array('calendar', $taxonomy))
		return;
		
	$tt_calendar = array();
	foreach( (array) wp_get_object_terms($post_id, 'calendar') as $item){
		// term déjà OK
		if(preg_match('`^[0-9]{4}-[0-9]{2}-[0-9]{2}$`', $item->slug) && !empty($item->description)){
			$tt_calendar[] = $item->term_id;
			continue;
		}
			
		// c'est pas un format normal
		if(!preg_match('`^[0-9]{2}-[0-9]{2}-[0-9]{4}$`', $item->slug)){
			wp_delete_term($item->term_id, 'calendar');
			continue;
		}
		
		// reste les nouveaux
		$new_slug = implode('-', array_reverse(explode('-', $item->slug)));
		
		// on vérifi que ça cree pas un doublon
		$id = $wpdb->get_var( $wpdb->prepare( "SELECT term_id FROM $wpdb->terms WHERE slug = %s", $new_slug ) );
		if($id){
			wp_delete_term($item->term_id, 'calendar');
			$existing_term = get_term($id, 'calendar');
			$tt_calendar[] = $existing_term->term_id;
			continue;
		}
		
		// c'est un vrai nouveau
		$date = explode('-', $item->slug);
		$description = gmdate('Y-m-d H:i:s', mktime(0, 0, 0, $date[1], $date[0], $date[2]));
		$name = absint($date[0]).' '.ucfirst($wp_locale->get_month($date[1])).' '.$date[2];
		$return = wp_update_term($item->term_id, 'calendar', array('slug'=>$new_slug, 'description'=>$description, 'name'=>$name));
		if(is_wp_error($return))
			die('Erreur :'.$return->get_error_message());
		else
			$tt_calendar[] = $return['term_id'];
	}
	
	wp_set_object_terms($post_id, null, 'calendar');
	$tt_calendar = array_unique( array_map('intval', $tt_calendar) );
	wp_set_object_terms($post_id, $tt_calendar, 'calendar');
	
	$tt_ids = wp_get_object_terms($post_id, 'calendar', array('fields' => 'tt_ids'));
	wp_update_term_count($tt_ids, 'calendar');
	widget_calendar_clear_cache();
}

function widget_calendar_clear_cache(){
	delete_option('custom_calendar_datas');
}

register_activation_hook(__FILE__, 'widget_calendar_activation');
function widget_calendar_activation(){ 
	wp_schedule_event(time(), 'hourly', 'widget_calendar_clear_cache');
}
register_deactivation_hook(__FILE__, 'widget_calendar_deactivation');
function widget_calendar_deactivation(){
	wp_clear_scheduled_hook('widget_calendar_clear_cache');
}



function get_calendar_day_link($thisyear, $thismonth, $day){
	global $wp_rewrite, $wpdb;

	$link = $wp_rewrite->get_extra_permastruct('calendar');
	
	if(!empty($link))
		$link = str_replace('%calendar%', $thisyear.'-'.zeroise($thismonth, 2).'-'.zeroise($day, 2), $link);
	else
		$link = "?taxonomy=calendar&amp;term=$thisyear-".zeroise($thismonth, 2).'-'.zeroise($day, 2);
		
	$link = get_bloginfo('url').untrailingslashit($link);
	
	return $link;
}


add_action('init', 'calendar_register_taxonomy');
function calendar_register_taxonomy(){
	register_taxonomy('calendar', 
		'post', 
		array('hierarchical' => false, 
			'label' => 'Calendrier', 
			'query_var' => 'calendar', 
			'with_front' => false,
			'rewrite' => array(
				'slug'			=> 'calendar',
				'with_front'	=> 'calendar'
			)
		)
	);  
}



register_activation_hook(__FILE__, 'activation_of_widget_calendar');
function activation_of_widget_calendar(){
	// Flush rewrite rules otherwise all calendar taxonomy will break in 404;
	// No fear, rewrite_rules whill be rebuild like previous with support for
	// the new taxonomy 'calendar'.
	@delete_option('rewrite_rules');
}

add_action('admin_print_styles-post.php', 'add_jquery_ui_datepiker');
add_action('admin_print_styles-post-new.php', 'add_jquery_ui_datepiker');
function add_jquery_ui_datepiker(){
//new-tag-calendar
	wp_register_script('jquery-ui-datepiker-fr', '/wp-content/plugins/Widget-calendar/ui.datepicker-fr.js', array('jquery-ui-core'));
	wp_register_script('jquery-ui-datepiker', '/wp-content/plugins/Widget-calendar/ui.datepicker.js', array('jquery-ui-core', 'jquery-ui-datepiker-fr'));
	
	wp_enqueue_script('jquery-ui-datepiker-fr');
	wp_enqueue_script('jquery-ui-datepiker');
	
	wp_register_style('jquery-ui-1.7.3.custom.css', '/wp-content/plugins/Widget-calendar/ui-lightness/jquery-ui-1.7.3.custom.css');
	wp_enqueue_style('jquery-ui-1.7.3.custom.css');
}

add_action('admin_footer-post.php', 'add_calendar_js_support');
add_action('admin_footer-post-new.php', 'add_calendar_js_support');
function add_calendar_js_support(){
	?>
<script type="text/javascript">
jQuery(function($) {
	$('#calendar .taghint').remove();
	$.datepicker.setDefaults($.datepicker.regional[ "fr" ]);
	$('#new-tag-calendar').datepicker({
		dateFormat: 'dd-mm-yy'
	});
}); 
</script>
	<?php 
}


//add_action('init', 'register_ui_style_for_calendar');
function register_ui_style_for_calendar(){
	wp_register_style('ui-start', '/wp-content/plugins/Widget-calendar/start/jquery-ui-1.7.3.custom.css', array(), 1);
	wp_enqueue_style('ui-start');
}


add_action('wp_enqueue_scripts', 'register_calendar_scripts');
function register_calendar_scripts(){
	
	wp_enqueue_script('calendar-front-script', WP_PLUGIN_URL.'/Widget-calendar/front-script.js', array('jquery'));
	?>
<script type="text/javascript">
<!--
var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
//-->
</script>
	<?php 
}

add_action('wp_ajax_custom_widget_calendar', 'ajax_custom_widget_calendar');
add_action('wp_ajax_nopriv_custom_widget_calendar', 'ajax_custom_widget_calendar');
function ajax_custom_widget_calendar(){
	global $monthnum, $year;
	
	if(!preg_match('#^date-(\d+)-(\d+)$#', $_POST['date'], $match)){
		$match[1] = date('Y');
		$match[2] = date('m');
	}
	
	$monthnum = $match[2];
	$year = $match[1];
		
	get_custom_calendar();
	die();
}

