<?php
class WPEM_REST_Filter_Users_Controller {

    protected $namespace = 'wpem';
    protected $rest_base = 'filter-users';

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

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

public function handle_filter_users($request) {
		if (!get_option('enable_matchmaking', false)) {
			return new WP_REST_Response([
				'code'    => 403,
				'status'  => 'Disabled',
				'message' => 'Matchmaking functionality is not enabled.',
				'data'    => null
			], 403);
		}

		$event_id = $request->get_param('event_id');
		$current_user_id = $request->get_param('user_id');
		$user_ids = [];

		// Handle event-specific filtering
		if (!empty($event_id)) {
			if (!$current_user_id) {
				return new WP_REST_Response([
					'code'    => 401,
					'status'  => 'Unauthorized',
					'message' => 'User not logged in.',
					'data'    => []
				], 401);
			}

			// Check if current user is registered for the event
			$registration_query = new WP_Query([
				'post_type'      => 'event_registration',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => [
					['key' => '_attendee_user_id', 'value' => $current_user_id],
					['key' => '_event_id', 'value' => $event_id],
				],
			]);

			if (!$registration_query->have_posts()) {
				return new WP_REST_Response([
					'code'    => 403,
					'status'  => 'Forbidden',
					'message' => 'You are not registered for this event.',
					'data'    => []
				], 403);
			}

			// Get all attendees for this event
			$attendee_query = new WP_Query([
				'post_type'      => 'event_registration',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => [['key' => '_event_id', 'value' => $event_id]],
			]);

			foreach ($attendee_query->posts as $post_id) {
				$uid = get_post_meta($post_id, '_attendee_user_id', true);
				if ($uid && $uid != $current_user_id && !in_array($uid, $user_ids)) {
					$user_ids[] = (int)$uid;
				}
			}

			if (empty($user_ids)) {
				return new WP_REST_Response([
					'code'    => 200,
					'status'  => 'OK',
					'message' => 'No attendees found for this event.',
					'data'    => []
				], 200);
			}
		}

		// Get all users with matchmaking profile first
		$all_users = get_users([
			'meta_key'     => '_matchmaking_profile',
			'meta_value'   => '1',
			'meta_compare' => '=',
		]);

		// Filter users based on criteria
		$filtered_users = array_filter($all_users, function($user) use ($request, $user_ids) {
			$user_meta = get_user_meta($user->ID);
			
			// If event_id provided, only include users in the attendee list
			if (!empty($user_ids) && !in_array($user->ID, $user_ids)) {
				return false;
			}

			// Profession filter
			if ($profession = sanitize_text_field($request->get_param('profession'))) {
				if (empty($user_meta['_profession'][0])) return false;
				if (strcasecmp($user_meta['_profession'][0], $profession) !== 0)
					return false;
			}

			// Experience filter
			$experience = $request->get_param('experience');
			if (is_array($experience)) {
				$user_exp = isset($user_meta['_experience'][0]) ? (float)$user_meta['_experience'][0] : 0;
				if (isset($experience['min']) && is_numeric($experience['min']) && $user_exp < (float)$experience['min']) {
					return false;
				}
				if (isset($experience['max']) && is_numeric($experience['max']) && $user_exp > (float)$experience['max']) {
					return false;
				}
			} elseif (is_numeric($experience)) {
				$user_exp = isset($user_meta['_experience'][0]) ? (float)$user_meta['_experience'][0] : 0;
				if ($user_exp != (float)$experience) {
					return false;
				}
			}

			// Company name filter
			if ($company = sanitize_text_field($request->get_param('company_name'))) {
				if (empty($user_meta['_company_name'][0])) return false;
				if (strcasecmp($user_meta['_company_name'][0], $company) !== 0) return false;
			}

			// Country filter
			$countries = $request->get_param('country');
			if (is_array($countries) && !empty($countries)) {
				if (empty($user_meta['_country'][0])) return false;
				if (!in_array(strtolower($user_meta['_country'][0]), array_map('strtolower', $countries))) {
					return false;
				}
			} elseif (!empty($countries)) {
				if (empty($user_meta['_country'][0])) return false;
				if (strcasecmp($user_meta['_country'][0], $countries) !== 0) return false;
			}

			// City filter
			if ($city = sanitize_text_field($request->get_param('city'))) {
				if (empty($user_meta['_city'][0])) return false;
				if (strcasecmp($user_meta['_city'][0], $city) !== 0) return false;
			}

			// Skills filter
			$skills = $request->get_param('skills');
			if (is_array($skills) && !empty($skills)) {
				if (empty($user_meta['_skills'][0])) return false;
				$user_skills = maybe_unserialize($user_meta['_skills'][0]);
				if (!is_array($user_skills)) return false;
				
				$found = false;
				foreach ($skills as $skill) {
					if (in_array($skill, $user_skills)) {
						$found = true;
						break;
					}
				}
				if (!$found) return false;
			}

			// Interests filter
			$interests = $request->get_param('interests');
			if (is_array($interests) && !empty($interests)) {
				if (empty($user_meta['_interests'][0])) return false;
				$user_interests = maybe_unserialize($user_meta['_interests'][0]);
				if (!is_array($user_interests)) return false;
				
				$found = false;
				foreach ($interests as $interest) {
					if (in_array($interest, $user_interests)) {
						$found = true;
						break;
					}
				}
				if (!$found) return false;
			}

			// Search filter
			$search = sanitize_text_field($request->get_param('search'));
			if (!empty($search)) {
				$found = false;
				
				// Search in profile fields
				$profile_fields = ['_about', '_city', '_country', '_profession', '_company_name'];
				foreach ($profile_fields as $field) {
					if (!empty($user_meta[$field][0]) && stripos($user_meta[$field][0], $search) !== false) {
						$found = true;
						break;
					}
				}
				
				// Search in user name fields
				if (!$found) {
					$first_name = get_user_meta($user->ID, 'first_name', true);
					$last_name = get_user_meta($user->ID, 'last_name', true);
					if (stripos($first_name, $search) !== false || stripos($last_name, $search) !== false) {
						$found = true;
					}
				}
				
				if (!$found) return false;
			}

			// Manual approval filter
			if (get_option('participant_activation') === 'manual') {
				if (empty($user_meta['_approve_profile_status'][0]) || $user_meta['_approve_profile_status'][0] != '1') {
					return false;
				}
			}

			return true;
		});

		// Pagination
		$per_page = max(1, (int) $request->get_param('per_page'));
		$page = max(1, (int) $request->get_param('page'));
		$total = count($filtered_users);
		$offset = ($page - 1) * $per_page;
		$paginated_users = array_slice($filtered_users, $offset, $per_page);

		// Format results
		$formatted_results = [];
		foreach ($paginated_users as $user) {
			$user_meta = get_user_meta($user->ID);
			$formatted_results[] = [
				'user_id' => $user->ID,
				'first_name' => $user_meta['first_name'][0] ?? '',
				'last_name' => $user_meta['last_name'][0] ?? '',
				'profession' => $user_meta['_profession'][0] ?? '',
				'experience' => isset($user_meta['_experience'][0]) ? (float)$user_meta['_experience'][0] : 0,
				'company_name' => $user_meta['_company_name'][0] ?? '',
				'country' => $user_meta['_country'][0] ?? '',
				'city' => $user_meta['_city'][0] ?? '',
				'about' => $user_meta['_about'][0] ?? '',
				'skills' => isset($user_meta['_skills'][0]) ? maybe_unserialize($user_meta['_skills'][0]) : [],
				'interests' => isset($user_meta['_interests'][0]) ? maybe_unserialize($user_meta['_interests'][0]) : [],
				'profile_photo' => $user_meta['_profile_photo'][0] ?? '',
				'organization_name' => $user_meta['_organization_name'][0] ?? '',
				'organization_logo' => isset($user_meta['_organization_logo'][0]) ? maybe_unserialize($user_meta['_organization_logo'][0]) : [],
			];
		}

		return new WP_REST_Response([
			'code'     => 200,
			'status'   => 'OK',
			'message'  => 'Users retrieved successfully.',
			'data'    => [
				'total_post_count' => $total,
				'current_page'     => $page,
				'last_page'        => ceil($total / $per_page),
				'total_pages'      => ceil($total / $per_page),
				'users'           => $formatted_results,
			],
		], 200);
	}
}
new WPEM_REST_Filter_Users_Controller();
