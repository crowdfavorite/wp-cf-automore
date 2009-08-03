<?php
/*
Plugin Name: CF Auto More
Plugin URI: http://crowdfavorite.com/
Description: Gives the admin the ability to trim the content of a post by word count on every page except the single post page
Version: 1.0
Author: Crowd Favorite
Author URI: http://crowdfavorite.com
*/

/**
 * 
 * Admin Functionality
 *
 **/

/**
 * cfam_request_handler - Request handler for saving the admin settings
 *
 * @return void
 */
function cfam_request_handler() {
	if (isset($_POST['cfam_cut']) && is_int((int)$_POST['cfam_cut'])) {
		// Get the shortest cut length and save if it is not 0
		update_option('_cfam_cut', (int)$_POST['cfam_cut']);
	}
	if (isset($_POST['cfam_min']) && is_int((int)$_POST['cfam_min'])) {
		// Get the shortest cut length and save if it is not 0
		update_option('_cfam_min', (int)$_POST['cfam_min']);
	}
	
}
add_action('init', 'cfam_request_handler');

/**
 * cfam_settings - Builds the settings section for the WP Admin->Settings->Reading settings page.  Adds
 * two settings fields, one for setting the length of the string to cut to, and one for setting the minimum
 * length the string has to be to be cut.
 *
 * @return void
 */
function cfam_settings() {
	$cut = get_option('_cfam_cut', true);
	$min = get_option('_cfam_min', true);
	
	?>
	<table class="form-table">
		<tr valign="top">
			<th scope="row">
				<label for="cfam_cut">
					<?php _e('Word Count') ?>
				</label>
			</th>
			<td>
				<input name="cfam_cut" type="text" id="cfam_cut" value="<?php echo esc_attr($cut); ?>" class="regular-text" />
				<span class="description">
					<?php _e('This is the amount of words to be displayed on all pages except the single post page. Please enter only numbers.') ?>
				</span>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row">
				<label for="cfam_min">
					<?php _e('Minimum Word Count') ?>
				</label>
			</th>
			<td>
				<input name="cfam_min" type="text" id="cfam_min" value="<?php echo esc_attr($min); ?>" class="regular-text" />
				<span class="description">
					<?php _e('This is the amount of words that will need to be present for the shortening to take place.') ?>
				</span>
			</td>
		</tr>
	</table>
	<?php
}

/**
 * cfam_admin_head - Adds a settings section to the WP Admin->Settings->Reading settings page
 *
 * @return void
 */
function cfam_admin_head() {
	add_settings_section('cfam-settings', 'CF AutoMore Settings', 'cfam_settings', 'reading');
}
add_action('admin_head', 'cfam_admin_head');

/**
 * 
 * Plugin Functionality
 * 
 **/

/**
 * cfam_word_count - Filtered in function to "the_content" for trimming the content by a
 * set number of words.  The number of words is set in the WP Admin->Settings->Reading.  Function
 * requires the word count to be more than the "_cfam_min" for the string to be trimmed to the "_cfam_cut"
 * length.  
 *
 * @param string $content - Filtered in content
 * @return string $content - Trimmed string of content if it passes checks
 */
function cfam_word_count($content) {
	// We don't want to do the filtering when it should be displaying the full content
	if (is_single() || is_page()) { return $content; }
	
	global $post;
	$cut = get_option('_cfam_cut', true);
	$min = get_option('_cfam_min', true);
	
	// Check to see if the current post is excluded from the limiting of content
	$exclude = get_post_meta($post->ID, '_cfam_exclude', true);
	if (isset($exclude) && $exclude == 'yes') { return $content; }
	
	if (!$cut) { return $content; }
	if (!$min) { $min = $cut; }
	$read_more = '';
	
	// Check and make sure that we have a post id before we go ahead and use it.
	if (isset($post->ID) && !empty($post->ID)) {
		$read_more = ' <a href="'.get_permalink($post->ID).'" title="Permanent Link to '.esc_attr(get_the_title($post->ID)).'">Read More</a>&hellip;';
	}
	
	// Get the count of the words from the content
	$count = str_word_count(strip_tags($content));
	$words = str_word_count(strip_tags($content), 2);
	$length = strlen($content);
	
	// Check and see if the count of words in the content is greater than the set for cutting
	// And also greater than the minimum amount for cutting
	if ($count >= $cut && $count > $min) {
		// Get the trimmed string with the proper word count
		$trimmed = cfam_word_split($content, $cut);
		$trimmed_length = strlen($trimmed);
		
		// Get the rest of the current paragraph to keep styling
		$trimmed_plus = substr($content, $trimmed_length, $length);
		$trimmed_plus = substr($trimmed_plus, 0, strpos($trimmed_plus, '</p>')).$read_more.'</p>';
		
		$content = cf_close_opened_tags($trimmed.$trimmed_plus);
	}
	return $content;
}
add_filter('the_content', 'cfam_word_count', 12, 1);

/**
 * cfam_word_split - Inputs a string and a word count, and returns the string with the proper amount of words
 *
 * @param string $str - String to be trimmed
 * @param int $words - Count of words to trim string to
 * @return join(' ', $arr) - Trimmed string
 */
function cfam_word_split($str, $words=15) {
	$arr = preg_split("/[\s]+/", $str, $words+1);
	$arr = array_slice($arr, 0, $words);
	return join(' ', $arr);
}

/**
 * Function to close any opened tags in a string
 * Makes no attempt to put them in the proper place, just makes sure that everything closes
 *
 * @param string $string 
 * @return string
 */
if (!function_exists('cf_close_opened_tags')) {
	function cf_close_opened_tags($string) {
		preg_match_all('/<(\w+)/',$string,$open_tags);
		preg_match_all('/<\/(\w+)/',$string,$close_tags);

		// if open & close match then get out quickly
		if(count($open_tags[1]) == count($close_tags[1])) { 
			return $string;
		}

		// log found open tags
		$tags = array();
		foreach($open_tags[1] as $found) {
			if(!isset($tags[$found])) {
				$tags[$found] = 0;
			}
			$tags[$found]++;
		}

		// process found close tags
		foreach($close_tags[1] as $found) {
			$tags[$found]--;
			if($tags[$found] == 0) { unset($tags[$found]); }
		}

		// feeble attempt to get a semblance of order
		$tags = array_reverse($tags,true);
		foreach($tags as $tagname => $tag_count) {
			if($tag_count) {
				$string .= '</'.$tagname.'>';
			}
		}
		return $string;
	}
}

/**
 * 
 * Post Functions
 * 
 **/

/**
 * cfam_admin_init - This function adds the meta box to the post page
 *
 * @return void
 */
function cfam_admin_init() {
	add_meta_box('cfam_exclude', __('CF AutoMore Exclude', 'cfam'), 'cfam_exclude', 'post', 'side', 'low');
}
add_action('admin_init', 'cfam_admin_init');

/**
 * cfam_exclude - This function builds the post meta box in the side of the post edit screen
 *
 * @return void
 */
function cfam_exclude() {
	global $post;
	
	$exclude = get_post_meta($post->ID, '_cfam_exclude', true);

	$checked = '';
	if (isset($exclude) && $exclude == 'yes') {
		$checked = ' checked="checked"';		
	}
	
	?>
	<input type="checkbox" name="cfam_exclude" id="cfam_exclude"<?php echo $checked; ?> />
	<label for="cfam_exclude">
		Exclude from AutoMore
	</label>
	<?php
}

/**
 * cfam_exclude_save_post - This function saves the status of the checkbox from the post meta fied
 *
 * @param string $post_id 
 * @return void
 */
function cfam_exclude_save_post($post_id) {
	if (isset($_POST['cfam_exclude'])) {
		update_post_meta($post_id, '_cfam_exclude', 'yes');
	}
	else {
		update_post_meta($post_id, '_cfam_exclude', 'no');
	}
	return;
}
add_action('save_post', 'cfam_exclude_save_post');









?>