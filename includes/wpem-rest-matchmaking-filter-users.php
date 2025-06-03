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
            'methods'  => WP_REST_Server::READABLE,
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
				'event_id'     => array('required' => false, 'type' => 'integer'),
				'user_id'     => array('required' => true, 'type' => 'integer'),
				
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
		global $wpdb;

		if (!get_option('enable_matchmaking', false)) {
			return new WP_REST_Response([
				'code'    => 403,
				'status'  => 'Disabled',
				'message' => 'Matchmaking functionality is not enabled.',
				'data'    => null
			], 403);
		}

		$table = $wpdb->prefix . 'wpem_matchmaking_users';
		$where_clauses = [];
		$query_params = [];

		$user_ids = [];

		$event_id = $request->get_param('event_id');
		if (!empty($event_id)) {
			$current_user_id = $request->get_param('user_id');
			if (!$current_user_id) {
				return new WP_REST_Response([
					'code'    => 401,
					'status'  => 'Unauthorized',
					'message' => 'User not logged in.',
					'data'    => []
				], 401);
			}

			// Step 1: Check if current user is registered for the given event
			$registration_query = new WP_Query([
				'post_type'      => 'event_registration',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => [
					[
						'key'   => '_attendee_user_id',
						'value' => $current_user_id,
					],
					[
						'key'   => '_event_id',
						'value' => $event_id,
					],
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

			// Step 2: Fetch all other attendees for the same event
			$attendee_query = new WP_Query([
				'post_type'      => 'event_registration',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => [
					[
						'key'   => '_event_id',
						'value' => $event_id,
					],
				],
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

			// Restrict SQL to only these user_ids
			$placeholders = implode(',', array_fill(0, count($user_ids), '%d'));
			$where_clauses[] = "user_id IN ($placeholders)";
			$query_params = array_merge($query_params, $user_ids);
		}
		if ($profession = sanitize_text_field($request->get_param('profession'))) {
			$where_clauses[] = "profession = %s";
			$query_params[] = $profession;
		}
		$experience = $request->get_param('experience');
		if (is_array($experience) && isset($experience['min'], $experience['max'])) {
			$where_clauses[] = "experience BETWEEN %d AND %d";
			$query_params[] = (int)$experience['min'];
			$query_params[] = (int)$experience['max'];
		} elseif (is_numeric($experience)) {
			$where_clauses[] = "experience = %d";
			$query_params[] = (int)$experience;
		}
		if ($company = sanitize_text_field($request->get_param('company_name'))) {
			$where_clauses[] = "company_name = %s";
			$query_params[] = $company;
		}
		$countries = $request->get_param('country');
		if (is_array($countries)) {
			$placeholders = implode(',', array_fill(0, count($countries), '%s'));
			$where_clauses[] = "country IN ($placeholders)";
			$query_params = array_merge($query_params, array_map('sanitize_text_field', $countries));
		} elseif (!empty($countries)) {
			$country = sanitize_text_field($countries);
			$where_clauses[] = "country = %s";
			$query_params[] = $country;
		}
		if ($city = sanitize_text_field($request->get_param('city'))) {
			$where_clauses[] = "city = %s";
			$query_params[] = $city;
		}
		$skills = $request->get_param('skills');
		if (is_array($skills) && !empty($skills)) {
			foreach ($skills as $skill) {
				$like = '%' . $wpdb->esc_like(serialize($skill)) . '%';
				$where_clauses[] = "skills LIKE %s";
				$query_params[] = $like;
			}
		}
		$interests = $request->get_param('interests');
		if (is_array($interests) && !empty($interests)) {
			foreach ($interests as $interest) {
				$like = '%' . $wpdb->esc_like(serialize($interest)) . '%';
				$where_clauses[] = "interests LIKE %s";
				$query_params[] = $like;
			}
		}

		if (get_option('participant_activation') === 'manual') {
			$where_clauses[] = "approve_profile_status = '1'";
		}

		$where_sql = $where_clauses ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
		$sql = "SELECT * FROM $table $where_sql";
		$prepared_sql = $wpdb->prepare($sql, $query_params);
		$results = $wpdb->get_results($prepared_sql, ARRAY_A);

		foreach ($results as &$row) {
			$user_id = $row['user_id'];
			$row['first_name'] = get_user_meta($user_id, 'first_name', true);
			$row['last_name']  = get_user_meta($user_id, 'last_name', true);
		}

		return new WP_REST_Response([
			'code'    => 200,
			'status'  => 'OK',
			'message' => 'Users retrieved successfully.',
			'data'    => $results
		], 200);
	}
	
}
new WPEM_REST_Filter_Users_Controller();
