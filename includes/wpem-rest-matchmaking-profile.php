<?php
defined('ABSPATH') || exit;

class WPEM_REST_MatchMaking_Profile_Controller extends WPEM_REST_CRUD_Controller {
    protected $namespace = 'wpem';

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'), 10);
    }

    public function register_routes() {
        $auth_controller = new WPEM_REST_Authentication();
        register_rest_route(
            $this->namespace,
            '/attendee-profile',
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_attendee_profile'),
                'permission_callback' => array($auth_controller, 'check_authentication'),
                'args' => array(
                    'attendeeId' => array(
                        'required' => false,
                        'type' => 'integer',
                    )
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/attendee-profile/update',
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_attendee_profile'),
                'permission_callback' => array($auth_controller, 'check_authentication'),
                'args' => array(
                    'user_id' => array(
                        'required' => true,
                        'type' => 'integer',
                    ),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/upload-user-file',
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'upload_user_file'),
                'permission_callback' => array($auth_controller, 'check_authentication'),
                'args' => array(
                    'user_id' => array(
                        'required' => true,
                        'type' => 'integer',
                    ),
                ),
            )
        );
    }

   public function get_attendee_profile($request) {
		if (!get_option('enable_matchmaking', false)) {
			return new WP_REST_Response(array(
				'code' => 403,
				'status' => 'Disabled',
				'message' => 'Matchmaking functionality is not enabled.',
				'data' => null
			), 403);
		}

		$attendee_id = $request->get_param('attendeeId');
		$countries = wpem_get_all_countries();
		if ($attendee_id) {
			// Check if user exists
			$user = get_user_by('ID', $attendee_id);
			if (!$user) {
				return new WP_REST_Response(array(
					'code' => 404,
					'status' => 'Not Found',
					'message' => 'Attendee not found.',
					'data' => null
				), 404);
			}

			// Get all user meta
			$user_meta = get_user_meta($attendee_id);
			$photo =  get_wpem_user_profile_photo($attendee_id) ?: EVENT_MANAGER_REGISTRATIONS_PLUGIN_URL . '/assets/images/user-profile-photo.png';
			$organization_logo = get_user_meta( $attendee_id, '_organization_logo', true );
			$organization_logo = maybe_unserialize( $organization_logo );
			if (is_array($organization_logo)) {
				$organization_logo = reset($organization_logo); // get first value in the array
			}
			$organization_logo = $organization_logo ?: EVENT_MANAGER_REGISTRATIONS_PLUGIN_URL . '/assets/images/organisation-icon.jpg';
			$country_value = isset($user_meta['_country'][0]) ? sanitize_text_field($user_meta['_country'][0]) : '';
			$country_code = '';
			if ($country_value) {
				if (isset($countries[$country_value])) {
					$country_code = $country_value;
				} else {
					$country_code = array_search($country_value, $countries);
				}
			}
			// Get organization country value from user meta
			$org_country_value = isset($user_meta['_organization_country'][0]) ? sanitize_text_field($user_meta['_organization_country'][0]) : '';
			$org_country_code = '';
			if ($org_country_value) {
				if (isset($countries[$org_country_value])) {
					$org_country_code = $org_country_value;
				} else {
					$org_country_code = array_search($org_country_value, $countries);
				}
			}
			$meta = get_user_meta($attendee_id, '_available_for_meeting', true);
			$meeting_available = ($meta !== '' && $meta !== null) ? ((int)$meta === 0 ? 0 : 1) : 1;
			// Get all profession terms [slug => name]
			$professions = get_event_registration_taxonomy_list('event_registration_professions');

			// Get saved profession value
			$profession_value = isset($user_meta['_profession'][0]) ? sanitize_text_field($user_meta['_profession'][0]) : '';
			$profession_slug = $profession_value;

			// If it's a name, convert to slug
			if ($profession_value && !isset($professions[$profession_value])) {
				$found_slug = array_search($profession_value, $professions);
				if ($found_slug) {
					$profession_slug = $found_slug;
				}
			}
			$skills_slugs = array();
			if (!empty($user_meta['_skills'][0])) {
				$skills = maybe_unserialize($user_meta['_skills'][0]);
				if (is_array($skills)) {
					foreach ($skills as $skill) {
						$term = get_term_by('name', $skill, 'event_registration_skills');
						if (!$term) {
							$term = get_term_by('id', $skill, 'event_registration_skills');
						}
						if ($term) {
							$skills_slugs[] = $term->slug;
						}
					}
				}
			}
			$skills_slugs = array_filter($skills_slugs); // remove blanks
			$skills_serialized = serialize($skills_slugs);

			// Convert interests to slugs and serialize
			$interests_slugs = array();
			if (!empty($user_meta['_interests'][0])) {
				$interests = maybe_unserialize($user_meta['_interests'][0]);
				if (is_array($interests)) {
					foreach ($interests as $interest) {
						$term = get_term_by('name', $interest, 'event_registration_interests');
						if (!$term) {
							$term = get_term_by('id', $interest, 'event_registration_interests');
						}
						if ($term) {
							$interests_slugs[] = $term->slug;
						}
					}
				}
			}
			$interests_slugs = array_filter($interests_slugs);
			$interests_serialized = serialize($interests_slugs);
			
			// Format the profile data
			$profile = array(
				'user_id' => $attendee_id,
				'display_name' => $user->display_name,
				'first_name' => isset($user_meta['first_name'][0]) ? sanitize_text_field($user_meta['first_name'][0]) : '',
				'last_name' => isset($user_meta['last_name'][0]) ? sanitize_text_field($user_meta['last_name'][0]) : '',
				'email' => $user->user_email,
				'matchmaking_profile' => isset($user_meta['_matchmaking_profile'][0]) ? (int)$user_meta['_matchmaking_profile'][0] : 0,
				'profile_photo' => $photo,
				'profession' => $profession_slug,
				'experience' => isset($user_meta['_experience'][0]) ? (float)$user_meta['_experience'][0] : 0,
				'company_name' => isset($user_meta['_company_name'][0]) ? sanitize_text_field($user_meta['_company_name'][0]) : '',
				'country' => $country_code,
				'city' => isset($user_meta['_city'][0]) ? sanitize_text_field($user_meta['_city'][0]) : '',
				'about' => isset($user_meta['_about'][0]) ? sanitize_textarea_field($user_meta['_about'][0]) : '',
				//'skills'    => maybe_serialize($skills_slugs),
				//'interests' => maybe_serialize($interests_slugs),
				'skills'    => $skills_serialized,
				'interests' => $interests_serialized,
				'message_notification' => isset($user_meta['_message_notification'][0]) ? (int)$user_meta['_message_notification'][0] : 0,
				'organization_name' => isset($user_meta['_organization_name'][0]) ? sanitize_text_field($user_meta['_organization_name'][0]) : '',
				'organization_logo' => $organization_logo,
				'organization_country' => $org_country_code,
				'organization_city' => isset($user_meta['_organization_city'][0]) ? sanitize_text_field($user_meta['_organization_city'][0]) : '',
				'organization_description' => isset($user_meta['_organization_description'][0]) ? sanitize_textarea_field($user_meta['_organization_description'][0]) : '',
				'organization_website' =>  isset($user_meta['_organization_website'][0]) ? sanitize_text_field($user_meta['_organization_website'][0]) : '',
				'approve_profile_status' => isset($user_meta['_approve_profile_status'][0]) ? (int)$user_meta['_approve_profile_status'][0] : 0,
				'wpem_meeting_request_mode' => isset($user_meta['_wpem_meeting_request_mode'][0]) ? $user_meta['_wpem_meeting_request_mode'][0] : 'approval',
				'available_for_meeting' => (int)$meeting_available,
			);

			return new WP_REST_Response(array(
				'code' => 200,
				'status' => 'OK',
				'message' => 'Profile retrieved successfully.',
				'data' => $profile
			), 200);
		} else {
			// Get all users with matchmaking profiles
			$args = array(
				'meta_key' => '_matchmaking_profile',
				'meta_value' => '1',
				'meta_compare' => '='
			);
			$users = get_users($args);
			
			$profiles = array();
			foreach ($users as $user) {
				$user_meta = get_user_meta($user->ID);
				$photo = get_wpem_user_profile_photo($user->ID) ?: EVENT_MANAGER_REGISTRATIONS_PLUGIN_URL . '/assets/images/user-profile-photo.png';
				$organization_logo = get_user_meta( $user->ID, '_organization_logo', true );
				$organization_logo = maybe_unserialize( $organization_logo );
				if (is_array($organization_logo)) {
					$organization_logo = reset($organization_logo);
				}
				$organization_logo = $organization_logo ?: EVENT_MANAGER_REGISTRATIONS_PLUGIN_URL . '/assets/images/organisation-icon.jpg';
				$country_value = isset($user_meta['_country'][0]) ? sanitize_text_field($user_meta['_country'][0]) : '';
				$country_code = '';
				if ($country_value) {
					if (isset($countries[$country_value])) {
						$country_code = $country_value;
					} else {
						$country_code = array_search($country_value, $countries);
					}
				}
				$org_country_value = isset($user_meta['_organization_country'][0]) ? sanitize_text_field($user_meta['_organization_country'][0]) : '';
				$org_country_code = '';
				if ($org_country_value) {
					if (isset($countries[$org_country_value])) {
						$org_country_code = $org_country_value;
					} else {
						$org_country_code = array_search($org_country_value, $countries);
					}
				}
				$meta = get_user_meta($user->ID, '_available_for_meeting', true);
				$meeting_available = ($meta !== '' && $meta !== null) ? ((int)$meta === 0 ? 0 : 1) : 1;
				// Profession slug logic
				$professions = get_event_registration_taxonomy_list('event_registration_professions');
				$profession_value = isset($user_meta['_profession'][0]) ? sanitize_text_field($user_meta['_profession'][0]) : '';
				$profession_slug = $profession_value;
				if ($profession_value && !isset($professions[$profession_value])) {
					$found_slug = array_search($profession_value, $professions);
					if ($found_slug) {
						$profession_slug = $found_slug;
					}
				}
				$skills_slugs = array();
				if (!empty($user_meta['_skills'][0])) {
					$skills = maybe_unserialize($user_meta['_skills'][0]);
					if (is_array($skills)) {
						foreach ($skills as $skill) {
							$term = get_term_by('name', $skill, 'event_registration_skills');
							if (!$term) {
								$term = get_term_by('id', $skill, 'event_registration_skills');
							}
							if ($term) {
								$skills_slugs[] = $term->slug;
							}
						}
					}
				}
				$skills_slugs = array_filter($skills_slugs);
				$skills_serialized = maybe_serialize($skills_slugs);

				// Convert interests
				$interests_slugs = array();
				if (!empty($user_meta['_interests'][0])) {
					$interests = maybe_unserialize($user_meta['_interests'][0]);
					if (is_array($interests)) {
						foreach ($interests as $interest) {
							$term = get_term_by('name', $interest, 'event_registration_interests');
							if (!$term) {
								$term = get_term_by('id', $interest, 'event_registration_interests');
							}
							if ($term) {
								$interests_slugs[] = $term->slug;
							}
						}
					}
				}
				$interests_slugs = array_filter($interests_slugs);
				$interests_serialized = maybe_serialize($interests_slugs);
				$profiles[] = array(
					'user_id' => $user->ID,
					'display_name' => $user->display_name,
					'first_name' => isset($user_meta['first_name'][0]) ? sanitize_text_field($user_meta['first_name'][0]) : '',
					'last_name' => isset($user_meta['last_name'][0]) ? sanitize_text_field($user_meta['last_name'][0]) : '',
					'email' => $user->user_email,
					'matchmaking_profile' => isset($user_meta['_matchmaking_profile'][0]) ? (int)$user_meta['_matchmaking_profile'][0] : 0,
					'profile_photo' => $photo,
					'profession' => $profession_slug ,
					'experience' => isset($user_meta['_experience'][0]) ? (float)$user_meta['_experience'][0] : 0,
					'company_name' => isset($user_meta['_company_name'][0]) ? sanitize_text_field($user_meta['_company_name'][0]) : '',
					'country' => $country_code,
					'city' => isset($user_meta['_city'][0]) ? sanitize_text_field($user_meta['_city'][0]) : '',
					'about' => isset($user_meta['_about'][0]) ? sanitize_textarea_field($user_meta['_about'][0]) : '',
					'skills'    => $skills_serialized,
					'interests' => $interests_serialized,
					'message_notification' => isset($user_meta['_message_notification'][0]) ? (int)$user_meta['_message_notification'][0] : 0,
					'organization_name' => isset($user_meta['_organization_name'][0]) ? sanitize_text_field($user_meta['_organization_name'][0]) : '',
					'organization_logo' => $organization_logo,
					'organization_country' => $org_country_code,
					'organization_city' => isset($user_meta['_organization_city'][0]) ? sanitize_text_field($user_meta['_organization_city'][0]) : '',
					'organization_description' => isset($user_meta['_organization_description'][0]) ? sanitize_textarea_field($user_meta['_organization_description'][0]) : '',
					'organization_website' =>  isset($user_meta['_organization_website'][0]) ? sanitize_text_field($user_meta['_organization_website'][0]) : '',
					'approve_profile_status' => isset($user_meta['_approve_profile_status'][0]) ? (int)$user_meta['_approve_profile_status'][0] : 0,
					'wpem_meeting_request_mode' => isset($user_meta['_wpem_meeting_request_mode'][0]) ? $user_meta['_wpem_meeting_request_mode'][0] : 'approval',
					'available_for_meeting' => (int)$meeting_available,
				);
			}

			return new WP_REST_Response(array(
				'code' => 200,
				'status' => 'OK',
				'message' => 'All profiles retrieved successfully.',
				'data' => $profiles
			), 200);
		}
	}

    /**
     * Update profile including handling file upload from device for profile_photo
     */
    public function update_attendee_profile($request) {
		if (!get_option('enable_matchmaking', false)) {
			return new WP_REST_Response([
				'code' => 403,
				'status' => 'Disabled',
				'message' => 'Matchmaking functionality is not enabled.'
			], 403);
		}

		$user_id = $request->get_param('user_id');
		$user = get_user_by('id', $user_id);
		
		if (!$user) {
			return new WP_REST_Response([
				'code' => 404, 
				'status' => 'Not Found', 
				'message' => 'User not found.'
			], 404);
		}

		// List of all meta fields we can update
		$meta_fields = [
			'profession', 'experience', 'company_name', 'country',
			'city', 'about', 'skills', 'interests', 'organization_name', 
			'organization_logo', 'organization_city', 'organization_country', 
			'organization_description', 'organization_website', 'message_notification', 'matchmaking_profile'
		];

		// Handle normal meta fields
		
		foreach ($meta_fields as $field) {
			if ($request->get_param($field) !== null) {
				$value = $request->get_param($field);
				 // If it's an array, filter out blanks
				if (is_array($value)) {
					$value = array_filter($value, function($v) {
						return $v !== null && $v !== '';
					});
					$value = array_values($value); // reindex after filtering
				}

				// Only update if not completely empty
				if (!empty($value)) {
					update_user_meta($user_id, '_' . $field, $value);
				} else {
					update_user_meta($user_id, '_' . $field, ''); // cleanup if blank
				}
			}
		}

		// Handle profile_photo file upload
		if (!empty($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			$upload_overrides = ['test_form' => false];
			$movefile = wp_handle_upload($_FILES['profile_photo'], $upload_overrides);

			if (isset($movefile['url'])) {
				update_user_meta($user_id, '_profile_photo', esc_url_raw($movefile['url']));
				
			} else {
				return new WP_REST_Response([
					'code' => 500, 
					'status' => 'Error', 
					'message' => 'Profile photo upload failed.'
				], 500);
			}
		} elseif ($request->get_param('profile_photo')) {
			update_user_meta($user_id, '_profile_photo', esc_url_raw($request->get_param('profile_photo')));
			
		}

		// Handle organization_logo file upload
		if (!empty($_FILES['organization_logo']) && $_FILES['organization_logo']['error'] === UPLOAD_ERR_OK) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			$upload_overrides = ['test_form' => false];
			$movefile = wp_handle_upload($_FILES['organization_logo'], $upload_overrides);

			if (isset($movefile['url'])) {
				update_user_meta($user_id, '_organization_logo', esc_url_raw($movefile['url']));
			} else {
				return new WP_REST_Response([
					'code' => 500, 
					'status' => 'Error', 
					'message' => 'Organization logo upload failed.'
				], 500);
			}
		} elseif ($request->get_param('organization_logo')) {
			update_user_meta($user_id, '_organization_logo', esc_url_raw($request->get_param('organization_logo')));
		}

		// Update basic WP user fields
		if ($request->get_param('first_name')) {
			update_user_meta($user_id, 'first_name', sanitize_text_field($request->get_param('first_name')));
		}
		
		if ($request->get_param('last_name')) {
			update_user_meta($user_id, 'last_name', sanitize_text_field($request->get_param('last_name')));
		}
		
		if ($request->get_param('email')) {
			$email = sanitize_email($request->get_param('email'));
			$email_exists = email_exists($email);
			
			if ($email_exists && $email_exists != $user_id) {
				return new WP_REST_Response([
					'code' => 400, 
					'status' => 'Error', 
					'message' => 'Email already in use.'
				], 400);
			}
			
			$result = wp_update_user([
				'ID' => $user_id, 
				'user_email' => $email
			]);
			
			if (is_wp_error($result)) {
				return new WP_REST_Response([
					'code' => 500, 
					'status' => 'Error', 
					'message' => $result->get_error_message()
				], 500);
			}
		}

		return new WP_REST_Response([
			'code' => 200, 
			'status' => 'OK', 
			'message' => 'Profile updated successfully.'
		], 200);
	}

    public function upload_user_file($request) {
		if (!get_option('enable_matchmaking', false)) {
			return new WP_REST_Response([
				'code' => 403,
				'status' => 'Disabled',
				'message' => 'Matchmaking functionality is not enabled.'
			], 403);
		}

		$user_id = $request->get_param('user_id');
		$user = get_user_by('id', $user_id);
		
		if (!$user) {
			return new WP_REST_Response([
				'code' => 404, 
				'status' => 'Not Found', 
				'message' => 'User not found.'
			], 404);
		}

		if (empty($_FILES['file'])) {
			return new WP_REST_Response([
				'code' => 400, 
				'status' => 'Error', 
				'message' => 'No file uploaded.'
			], 400);
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$file = $_FILES['file'];
		$upload_overrides = ['test_form' => false];
		$movefile = wp_handle_upload($file, $upload_overrides);
		
		if (!isset($movefile['url'])) {
			return new WP_REST_Response([
				'code' => 500, 
				'status' => 'Error', 
				'message' => 'File upload failed.'
			], 500);
		}

		$file_url = esc_url_raw($movefile['url']);

		// Update both profile photo meta fields
		update_user_meta($user_id, '_profile_photo', $file_url);
		

		return new WP_REST_Response([
			'code' => 200,
			'status' => 'OK',
			'message' => 'File uploaded and stored successfully.',
			'data' => [
				'profile_photo' => $file_url,
				'meta_updated' => true
			]
		], 200);
	}

}
new WPEM_REST_MatchMaking_Profile_Controller();