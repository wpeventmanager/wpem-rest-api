<?php
class WPEM_REST_Filter_Users_Controller {

    protected $namespace = 'wpem';
    protected $rest_base = 'filter-users';

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

	/**
	 * This function used to register routes
	 * @since 1.1.0
	 */
    public function register_routes() {
        $auth_controller = new WPEM_REST_Authentication();
        // General filter
        register_rest_route($this->namespace, '/' . $this->rest_base, array(
            'methods'  => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'handle_filter_users'),
            'permission_callback' => array($auth_controller, 'check_authentication'),
            'args' => array(
                'profession'    => array('required' => false, 'type' => 'string'),
                'company_name'  => array('required' => false, 'type' => 'string'),
                'country'       => array('required' => false, 'type' => 'array'),
                'city'          => array('required' => false, 'type' => 'string'),
                'experience'    => array('required' => false),
                'skills'        => array('required' => false, 'type' => 'array'),
                'interests'     => array('required' => false, 'type' => 'array'),
				'event_id'     	=> array('required' => false, 'type' => 'integer'),
				'user_id'     	=> array('required' => true, 'type' => 'integer'),
				'search' 		=> array('required' => false, 'type' => 'string'),
				'per_page'    	=> array('required' => false, 'type' => 'integer', 'default' => 5),
                'page'         	=> array('required' => false, 'type' => 'integer', 'default' => 1),
				
            ),
        ));

        // Your matches (with optional user_id param)
        register_rest_route($this->namespace, '/your-matches', array(
            'methods'  => WP_REST_Server::READABLE,
            'callback' => array($this, 'handle_your_matches'),
            'permission_callback' => array($auth_controller, 'check_authentication'),
            'args' => array(
                'user_id' => array('required' => true, 'type' => 'integer'),
            ),
        ));
    }

	/**
	 * This function used to filter users
	 * @since 1.1.0
	 */
    public function handle_your_matches($request) {
        global $wpdb;

        if (!get_option('enable_matchmaking', false)) {
            return new WP_REST_Response([
                'code'    => 403,
                'status'  => 'Disabled',
                'message' => 'Matchmaking functionality is not enabled.',
                'data'    => null
            ], 403);
        }

        $user_id = intval($request->get_param('user_id'));
        
        if (!$user_id) {
            return new WP_REST_Response([
                'code' => 401,
                'status' => 'Unauthorized',
                'message' => 'User Id not found'
            ], 401);
        }

        $table = $wpdb->prefix . 'wpem_matchmaking_users';
        $user_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE user_id = %d", $user_id), ARRAY_A);

        if (!$user_data) {
            return new WP_REST_Response([
                'code' => 404,
                'status' => 'Not Found',
                'message' => 'Matchmaking profile not found for user'
            ], 404);
        }

        // Build a filter request based on user's profile
        $filter_request = new WP_REST_Request('GET');
        $filter_request->set_param('profession', $user_data['profession']);
        $filter_request->set_param('company_name', $user_data['company_name']);
        $filter_request->set_param('country', [$user_data['country']]);
        $filter_request->set_param('city', $user_data['city']);

        $exp = (int)$user_data['experience'];
        $filter_request->set_param('experience', [
            'min' => max(0, $exp - 2),
            'max' => $exp + 2
        ]);

        $skills = maybe_unserialize($user_data['skills']);
        if (is_array($skills)) {
            $filter_request->set_param('skills', $skills);
        }

        $interests = maybe_unserialize($user_data['interests']);
        if (is_array($interests)) {
            $filter_request->set_param('interests', $interests);
        }

		$search = sanitize_text_field($request->get_param('search'));
		if (!empty($search)) {
			$search_like = '%' . $wpdb->esc_like($search) . '%';
			
			$search_conditions = [
				"city LIKE %s",
				"country LIKE %s",
				"profession LIKE %s",
				"skills LIKE %s",
				"interests LIKE %s",
				"company_name LIKE %s"
			];

			// First name / last name from wp_usermeta
			$matching_user_ids = $wpdb->get_col($wpdb->prepare("
				SELECT DISTINCT user_id
				FROM {$wpdb->usermeta}
				WHERE (meta_key = 'first_name' OR meta_key = 'last_name')
				AND meta_value LIKE %s
			", $search_like));

			if (!empty($matching_user_ids)) {
				$placeholders = implode(',', array_fill(0, count($matching_user_ids), '%d'));
				$search_conditions[] = "user_id IN ($placeholders)";
				$query_params = array_merge($query_params, array_fill(0, count($search_conditions) - 1, $search_like), $matching_user_ids);
			} else {
				$query_params = array_merge($query_params, array_fill(0, count($search_conditions), $search_like));
			}

			$where_clauses[] = '(' . implode(' OR ', $search_conditions) . ')';
		}

        // Reuse the filter handler
        $response = $this->handle_filter_users($filter_request);

        // Exclude the user from their own matches
        if ($response instanceof WP_REST_Response) {
			$data = $response->get_data();

			// Exclude self
			$filtered = array_filter($data['data'], function ($item) use ($user_id) {
				return $item['user_id'] != $user_id;
			});

			// Add first_name and last_name
			foreach ($filtered as &$row) {
				$row['first_name'] = get_user_meta($row['user_id'], 'first_name', true);
				$row['last_name']  = get_user_meta($row['user_id'], 'last_name', true);
			}

			$data['data'] = array_values($filtered);
			$response->set_data($data);
		}

        return $response;
    }

	/**
	 * This function used to filter users
	 * @since 1.1.0
	 * @param $request
	 * @return WP_REST_Response
	 */
	public function handle_filter_users($request) {
		global $wpdb;

		if (!get_option('enable_matchmaking', false)) {
			return new WP_REST_Response([
				'code'    => 403,
				'status'  => 'Disabled',
				'message' => 'Matchmaking functionality is not enabled.',
				'data'    => null
			], 403);
		}

		$event_id         = intval($request->get_param('event_id'));
		$user_id  = intval($request->get_param('user_id'));

		// Step 1: Get all registered user IDs for this event (from post_parent)
		$registered_user_ids = [];
		if ($event_id && $user_id) {
			$registration_query = new WP_Query([
				'post_type'      => 'event_registration',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'post_author'         => $user_id,
			]);

			$has_registration = false;
			foreach ($registration_query->posts as $registration_id) {
				if (wp_get_post_parent_id($registration_id) == $event_id) {
					$has_registration = true;
					break;
				}
			}

			if (!$has_registration) {
				return new WP_REST_Response([
					'code'    => 403,
					'status'  => 'Forbidden',
					'message' => 'You are not registered for this event.',
					'data'    => []
				], 403);
			}

			// Now get all users for this event
			$attendee_query = new WP_Query([
				'post_type'      => 'event_registration',
				'posts_per_page' => -1,
				'fields'         => 'ids'
			]);
			
			foreach ($attendee_query->posts as $registration_id) {
				if (wp_get_post_parent_id($registration_id) == $event_id) {
					$uid = get_post_field('post_author', $registration_id);
					if ($uid && !in_array($uid, $registered_user_ids)) {
						$registered_user_ids[] = intval($uid);
					}
				}
			}
		}

		if (empty($registered_user_ids)) {
			return new WP_REST_Response([
				'code'    => 200,
				'status'  => 'OK',
				'message' => 'No attendees found for this event.',
				'data'    => []
			], 200);
		}

		// Step 2: Build the full user data from usermeta
		$users_data = [];
		foreach ($registered_user_ids as $uid) {
			if($uid == $user_id){
				continue;
			}
			$photo = get_wpem_user_profile_photo($uid);
			$organization_logo = get_user_meta( $uid, '_organization_logo', true );
			$organization_logo = maybe_unserialize( $organization_logo );
			if (is_array($organization_logo)) {
				$organization_logo = reset($organization_logo); // get first value in the array
			}
			$skills    = get_user_meta($uid, '_skills', true);
			$interests = get_user_meta($uid, '_interests', true);

			// Remove empty values so [""] becomes []
			$skills    = array_filter((array)$skills, 'strlen');
			$interests = array_filter((array)$interests, 'strlen');

			// Serialize empty array if nothing remains
			$skills    = !empty($skills) ? maybe_serialize($skills) : serialize(array());
			$interests = !empty($interests) ? maybe_serialize($interests) : serialize(array());

			$photo = get_wpem_user_profile_photo($uid);
			$organization_logo = get_user_meta( $uid, '_organization_logo', true );
			$organization_logo = maybe_unserialize( $organization_logo );
			if (is_array($organization_logo)) {
				$organization_logo = reset($organization_logo);
			}
			$users_data[] = [
				'user_id'               => $uid,
				'display_name'          => get_the_author_meta('display_name', $uid),
				'first_name'            => get_user_meta($uid, 'first_name', true),
				'last_name'             => get_user_meta($uid, 'last_name', true),
				'email'                 => get_userdata($uid)->user_email,
				'matchmaking_profile'   => get_user_meta($uid, '_matchmaking_profile', true),
				'profile_photo'         => $photo,
				'profession'            => get_user_meta($uid, '_profession', true),
				'experience'            => get_user_meta($uid, '_experience', true),
				'company_name'          => get_user_meta($uid, '_company_name', true),
				'country'               => get_user_meta($uid, '_country', true),
				'city'                  => get_user_meta($uid, '_city', true),
				'about'                 => get_user_meta($uid, '_about', true),
				'skills'                => $skills,
				'interests'             => $interests,
				'message_notification'  => get_user_meta($uid, '_message_notification', true),
				'organization_name'     => get_user_meta($uid, '_organization_name', true),
				'organization_logo'     => $organization_logo,
				'organization_country'  => get_user_meta($uid, '_organization_country', true),
				'organization_city'     => get_user_meta($uid, '_organization_city', true),
				'organization_description'=> get_user_meta($uid, '_organization_description', true),
				'organization_website'	=> get_user_meta($uid, 'organization_website', true),

				'available_for_meeting'  => get_user_meta($uid, '_available_for_meeting', true),
				'approve_profile_status' => get_user_meta($uid, '_approve_profile_status', true),
			];
		}

		// Step 3: Apply filters
		$profession = sanitize_text_field($request->get_param('profession'));
		$country    = $request->get_param('country');
		$skills     = $request->get_param('skills');
		$interests  = $request->get_param('interests');
		$search     = sanitize_text_field($request->get_param('search'));

		$filtered_users = array_filter($users_data, function($user) use ($profession, $country, $skills, $interests, $search) {
			if ($profession && strtolower($user['profession']) !== strtolower($profession)) {
				return false;
			}
			if (!empty($country) && is_array($country) && !in_array($user['country'], $country)) {
				return false;
			}
			if (!empty($skills) && is_array($skills)) {
				$user_skills = maybe_unserialize($user['skills']);
				if (!array_intersect($skills, $user_skills)) {
					return false;
				}
			}
			if (!empty($interests) && is_array($interests)) {
				$user_interests = maybe_unserialize($user['interests']);
				if (!array_intersect($interests, $user_interests)) {
					return false;
				}
			}
			if ($search) {
				$haystack = strtolower(implode(' ', $user));
				if (strpos($haystack, strtolower($search)) === false) {
					return false;
				}
			}
			return true;
		});

		// Step 4: Pagination
		$per_page = max(1, (int) $request->get_param('per_page'));
		$page     = max(1, (int) $request->get_param('page'));
		$total    = count($filtered_users);
		$offset   = ($page - 1) * $per_page;
		$paged_users = array_slice($filtered_users, $offset, $per_page);

		return new WP_REST_Response([
			'code'     => 200,
			'status'   => 'OK',
			'message'  => 'Users retrieved successfully.',
			'data'     => [
				'total_post_count' => $total,
				'current_page'     => $page,
				'last_page'        => ceil($total / $per_page),
				'total_pages'      => ceil($total / $per_page),
				'users'            => array_values($paged_users),
			],
		], 200);
	}
}
new WPEM_REST_Filter_Users_Controller();
