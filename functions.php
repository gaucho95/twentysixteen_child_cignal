<?php
/**
 * Use Wordpress Approved way to load Child Theme
 */

add_action( 'wp_enqueue_scripts', 'theme_enqueue_styles' );
function theme_enqueue_styles() {
    wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
}

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
		$result = '';
		if ( isset( $post->ID ) ) {
			if ( Groups_Post_Access::user_can_read_post( $post->ID ) ) {
				$result = $output;
			} else {
	
				// show the excerpt
				$result .= '<div>';
				remove_filter( 'the_content', 'groups_excerpts_the_content', 1 );
				$result .= apply_filters( 'get_the_excerpt', $post->post_excerpt );
				add_filter( 'the_content', 'groups_excerpts_the_content', 1 );
				$result .= '</div>';
	
				// and add information to show that the content requires special access
				$result .= '<div>';
				$result .= '<p>';
				$result .= __( '<i>(Subscription Required)</i>', 'groups-excerpts' );
				$result .= '</p>';
				$result .= '</div>';
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
