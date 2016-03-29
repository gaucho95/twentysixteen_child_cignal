<?php
/**
 * Use Wordpress Approved way to load Child Theme
 */

add_action( 'wp_enqueue_scripts', 'theme_enqueue_styles' );
function theme_enqueue_styles() {
    wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
}


function custom_wp_trim_excerpt($text) 
{
	$raw_excerpt = $text;
	if ( $text == '' ) 
	{
		$text = get_the_content();
	 
		$text = strip_shortcodes( $text );
	 
		$text = apply_filters('the_content', $text);
		$text = str_replace(']]>', ']]&gt;', $text);
		 
		/***Add the allowed HTML tags separated by a comma.***/
		$allowed_tags = '<p>,<a>,<em>,<strong>,<b>,<i>,<br>,<div>';  
		$text = strip_tags($text, $allowed_tags);
		 
		/***Change the excerpt word count.***/
		$excerpt_word_count = 60; 
		$excerpt_length = apply_filters('excerpt_length', $excerpt_word_count); 
		 
		/*** Change the excerpt ending.***/
		$excerpt_end = '<a href="'. get_permalink($post->ID) . '">' . '&raquo; Continue Reading' . '</a>'; 
		$excerpt_more = apply_filters('excerpt_more', ' ' . $excerpt_end);
		 
		$words = preg_split("/[\n\r\t ]+/", $text, $excerpt_length + 1, PREG_SPLIT_NO_EMPTY);
		if ( count($words) > $excerpt_length ) 
		{
			array_pop($words);
			$text = implode(' ', $words);
			if(Groups_Post_Access::user_can_read_post( $post->ID ))
			{
				$text = $text . $excerpt_more;
			}
			else
			{
				$text .= '...';
			}
		} 
		else 
		{
			$text = implode(' ', $words);
		}
	}
	
	return apply_filters('wp_trim_excerpt', $text);
}

remove_filter('get_the_excerpt', 'wp_trim_excerpt', 1);
add_filter('get_the_excerpt', 'custom_wp_trim_excerpt', 1);

/**
 * An example of how to show excerpts for posts protected by Groups.
 */
if (
		class_exists( 'Groups_Post_Access' ) &&
		method_exists( 'Groups_Post_Access', 'user_can_read_post' )
) {
	// remove the default filters
	remove_filter( 'posts_where', array( 'Groups_Post_Access', 'posts_where' ), 10 );
	remove_filter( 'get_the_excerpt', array( 'Groups_Post_Access', 'get_the_excerpt' ), 1 );
	remove_filter( 'the_content', array( 'Groups_Post_Access', 'the_content' ), 1 );
	// add a filter that will show the post content for authorized users and the
	// excerpt for those who aren't.
	add_filter( 'the_content', 'groups_excerpts_the_content', 1 );
	
	/**
	 * Content filter that shows the excerpt for unauthorized users.
	 * 
	 * @param string $output
	 * @return string
	 */
	function groups_excerpts_the_content( $output ) {
		global $post;
		$user_id = get_current_user_id();
		$excerpt = "Requires ";
		
		/* Example : $excluded = array('optxr' => 1); */
		$excluded = array();
		
		if ( isset( $post->ID ) ) {
			if ( Groups_Post_Access::user_can_read_post( $post->ID ) ) 
			{
				if(is_single())
				{
					$result = $output;
				}
				else 
				{
					$content = get_post_field( 'post_content', $post->ID );
					$content_parts = get_extended( $content );
					
					if(strpos($content, '<!--more-->'))
					{
						return $content_parts['main'] . '<a href="'. get_permalink($post->ID) . '">' . 'Continue Reading' . '</a><br>';
					}
					
					if($post->post_excerpt != '')
					{			
						$read_caps = Groups_Post_Access::get_read_post_capabilities( $post->ID );
						if(empty($read_caps))
						{
							$result = apply_filters( 'get_the_excerpt', $post->post_excerpt );
							$result .= '<br><a href="'. get_permalink($post->ID) . '">' . 'Continue Reading' . '</a><br>';
						}		
						else
						{
							$result = $output;
						}
					}
					else 
					{
						$result = $output;
					}
				}
			} 
			else 
			{
				$term_id = $post->ID;
				
				$groups_user = new Groups_User( $user_id );
				$read_caps = Groups_Post_Access::get_read_post_capabilities( $term_id );
				
				$read_caps2 = array();
				if ( !empty( $read_caps ) ) {
					foreach( $read_caps as $read_cap ) {
						$read_caps2[$read_cap] = 1;
					}
				}

				$grc_term_read_caps = get_option( 'grc_term_read_capabilities', array() );
				
				$i = 0;
				foreach($grc_term_read_caps as $key=>$read_caps)
				{
					foreach($read_caps as $read_cap)
					{
						if($read_caps2[$read_cap])
						{
							$category = get_category($key, 'OBJECT');
							
							$category_name = $category->name;
							$category_slug = $category->slug;
							
							if(!isset($excluded[$category_slug]) || !$excluded[$category_slug])
							{
								if($i)
								{
									$excerpt .= 'or <a href="' . get_category_link($category->cat_ID). '">' . $category_name . "</a> ";
								}
								else 
								{
									$excerpt .= '<a href="' . get_category_link($category->cat_ID). '">' . $category_name . "</a> ";
								}
								$i++;
							}
							
							$excluded[$category_slug] = 1;
						}
					}
				}
				$excerpt .= "subscription.";
				
				$content = get_post_field( 'post_content', $post->ID );
				$content_parts = get_extended( $content );
				
				if(strpos($content, '<!--more-->'))
				{
					return $content_parts['main'] . '<i>' . $excerpt . '</i>';
				}
				
				if($post->post_excerpt == '')
				{
					// show the excerpt
					$result .= '<div>';
					remove_filter( 'the_content', 'groups_excerpts_the_content', 1 );
					$result .= apply_filters( 'get_the_excerpt', $post->post_excerpt );
					add_filter( 'the_content', 'groups_excerpts_the_content', 1 );
					$result .= '</div>';
					
					// and add information to show that the content requires special access
					$result .= '<div>';
					$result .= '<p>';
					$result .= __( '<i>' . $excerpt . '</i>', 'groups-excerpts' );
					$result .= '</p>';
					$result .= '</div>';
				}
				else 
				{
					remove_filter( 'the_content', 'groups_excerpts_the_content', 1 );
					$result = apply_filters( 'get_the_excerpt', $post->post_excerpt );
					add_filter( 'the_content', 'groups_excerpts_the_content', 1 );
					$result .= '<a href="'. get_permalink($post->ID) . '">' . '</a><br>' . '<i>' . $excerpt . '</i>';
				}
			}
		} else {
			// not a post, don't interfere
			$result = $output;
		}
		return $result;
	}
}

/**
 * Exclude Pages from Search Results
 */

add_action('pre_get_posts','exclude_all_pages_search');
function exclude_all_pages_search($query) {
    if (
        ! is_admin()
        && $query->is_main_query()
        && $query->is_search
    )
        $query->set( 'post_type', 'post' );
}

/**
 * Add to group based on email domain. Create group if it does not exist.
 * Derived from https://github.com/itthinx/groups-role-registration/blob/master/groups-role-registration.php
 * Customized by http://krumch.com/contacts/
 */

class Groups_Role_Registration {
        /**
         * Adds our action on user_register.
         */
        public static function init() {
                add_action( 'user_register', array( __CLASS__, 'user_register' ) );
        }
        /**
         * Hooked on user_register, add to group by role.
         */
        public static function user_register( $user_id ) {
                global $wp_roles;
                if ( !( class_exists( 'Groups_Group' ) && method_exists( 'Groups_Group', 'read_by_name' ) ) ) {
                        return;
                }
                if ( $user_id != null ) {
                        $user = new WP_User( $user_id );
                        if ( isset($user->user_email) )
                        {
                                list($username, $domain) = explode('@', $user->user_email);
                                if ( $domain ) {
                                        $group = Groups_Group::read_by_name( $domain );
                                        if ( !$group ) {
                                                $group_id = Groups_Group::create( array( 'name' => $domain ) );
                                        } else {
                                                $group_id = $group->group_id;
                                        }
                                        if ( $group_id ) {
                                                if ( !Groups_User_Group::read( $user_id, $group_id ) ) {
                                                        Groups_User_Group::create( array(
                                                                'user_id' => $user_id,
                                                                'group_id' => $group_id
                                                        ) );
                                                }
                                        }
                                }

                        }
                }
        }
}
Groups_Role_Registration::init();
?>
