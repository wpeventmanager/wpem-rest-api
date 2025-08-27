<?php

class WPEM_REST_Create_Meeting_Controller extends WPEM_REST_CRUD_Controller{

    protected $namespace = 'wpem';
    protected $rest_base = 'create-meeting';

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        $auth_controller = new WPEM_REST_Authentication();

        register_rest_route($this->namespace, '/' . $this->rest_base, [
            'methods'  => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'wpem_create_matchmaking_meeting'],
            'args' => [
                'user_id'              => ['required' => true, 'type' => 'integer'],
                'event_id'             => ['required' => true, 'type' => 'integer'],
                'meeting_date'         => ['required' => true, 'type' => 'string'],
                'slot'     			   => ['required' => true, 'type' => 'string'],
                'meeting_participants' => ['required' => true, 'type' => 'array'],
                'write_a_message'      => ['required' => false, 'type' => 'string'],
            ],
        ]);

        register_rest_route($this->namespace, '/get-meetings', [
            'methods'  => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'wpem_get_matchmaking_user_meetings'],
        ]);

		register_rest_route($this->namespace, '/cancel-meeting', [
			'methods'  => WP_REST_Server::CREATABLE,
			'callback' => [$this, 'wpem_cancel_matchmaking_meetings'],
			'args' => [
				'meeting_id' => ['required' => true, 'type' => 'integer'],
				'user_id'    => ['required' => true, 'type' => 'integer'],
			],
		]);

        register_rest_route($this->namespace, '/update-meeting-status', [
			'methods'  => WP_REST_Server::CREATABLE,
			'callback' => [$this, 'wpem_update_matchmaking_meeting_status'],
			'args' => [
				'meeting_id' => ['required' => true, 'type' => 'integer'],
				'user_id'    => ['required' => true, 'type' => 'integer'],
				'status'     => ['required' => true, 'type' => 'integer', 'enum' => [0, 1]],
			],
		]);
	register_rest_route($this->namespace, '/get-availability-slots', [
			'methods'  => WP_REST_Server::READABLE,
			'callback' => [$this, 'wpem_get_available_matchmaking_meeting_slots'],
			'args' => [
				'user_id' => [
					'required' => false,
					'type' => 'integer'
				]
			]
		]);
		register_rest_route($this->namespace, '/update-availability-slots', [
			'methods'  => WP_REST_Server::CREATABLE,
			'callback' => [$this, 'wpem_update_matchmaking_availability_slots'],
			'args' => [
				'availability_slots' => [
					'required' => true,
					'type' => 'object'  
				],
				'available_for_meeting' => [
					'required' => false,
					'type' => 'boolean' 
				],
				'user_id' => [
					'required' => false,
					'type' => 'integer'
				]
			]
		]);
		register_rest_route($this->namespace, '/common-availability-slots', [
			'methods'  => WP_REST_Server::CREATABLE,
			'callback' => [$this, 'wpem_get_common_availability_slots'],
			'args' => [
				'event_id' => [
					'required' => true,
					'type' => 'integer',
				],
				'user_ids' => [
					'required' => true,
					'type' => 'array',
				],
				'date' => [
					'required' => true,
					'type' => 'string', // expected in Y-m-d format
				],
			]
		]);
		register_rest_route($this->namespace, '/matchmaking-settings', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [$this, 'wpem_get_general_matchmaking_settings'],
		]);
    }
	/**
	 * create a matchmaking meetings with the other participants.
	 *
	 * Accepts the following parameters: user_id, event_id, meeting_date, slot, meeting_participants, write_a_message.
	 *
	 * Creates a new meeting with the given parameters. If the meeting is created successfully, sends a styled email to each participant with the meeting details.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 * @since 1.1.0
	 */
    public function wpem_create_matchmaking_meeting(WP_REST_Request $request) {
		global $wpdb;
		if (!get_option('enable_matchmaking', false)) {
			return new WP_REST_Response([
				'code' => 403,
				'status' => 'Disabled',
				'message' => 'Matchmaking functionality is not enabled.',
				'data' => null
			], 403);
		}
		$auth_check = $this->wpem_check_authorized_user();
		if ($auth_check) {
			return self::prepare_error_for_response(405);
		} else {

			$user_id      = intval($request->get_param('user_id'));
			$event_id     = intval($request->get_param('event_id'));
			$meeting_date = sanitize_text_field($request->get_param('meeting_date'));
			$slot         = sanitize_text_field($request->get_param('slot'));
			$participants = $request->get_param('meeting_participants');
			$message      = sanitize_textarea_field($request->get_param('write_a_message'));

			if (
				!$user_id || !get_userdata($user_id) ||
				empty($meeting_date) || empty($slot) ||
				empty($participants) || !is_array($participants)
			) {
				return new WP_REST_Response([
					'code' => 400,
					'status' => 'Bad Request',
					'message' => 'Missing or invalid parameters.',
					'data' => []
				], 400);
			}

			// Filter out the user themselves from participant list
			$participants = array_filter(array_map('intval', $participants), fn($pid) => $pid !== $user_id);
			$participants = array_fill_keys($participants, -1); // -1 = pending
			
			$table = $wpdb->prefix . 'wpem_matchmaking_users_meetings';
			$inserted = $wpdb->insert($table, [
				'user_id'            => $user_id,
				'event_id'           => $event_id,
				'participant_ids'    => serialize($participants),
				'meeting_date'       => $meeting_date,
				'meeting_start_time' => date("H:i", strtotime($slot)), // 24-hour
				'meeting_end_time'   => date("H:i", strtotime($slot . " +1 hour")), // 24-hour
				'message'            => $message,
				'meeting_status'     => 0
			], ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d']);

			if (!$inserted) {
				return new WP_REST_Response([
					'code' => 500,
					'status' => 'Internal Server Error',
					'message' => 'Could not create meeting.',
					'data' => []
				], 500);
			}

			$meeting_id = $wpdb->insert_id;
			$formatted_date = date("l, d F Y", strtotime($meeting_date));
			//$formatted_time = date("H:i", strtotime($slot)) ; // 24-hour
			$start_time = date("H:i", strtotime($slot));
			$end_time   = date("H:i", strtotime($slot . " +1 hour"));

			$formatted_time = $start_time . ' to ' . $end_time;
			$sender_user = get_userdata($user_id);

			$participant_details = [];

			if (!empty($participants)) {
				foreach (array_keys($participants) as $participant_id) {
					$participant_user = get_userdata($participant_id);
					if (!$participant_user) continue;

					$participant_meta = get_user_meta($participant_id);
					
					$participant_name = $participant_user->display_name ?? 'User';
					$participant_email = $participant_user->user_email ?? '';
					$profile_picture = $participant_meta['_profile_photo'][0] ?? EVENT_MANAGER_REGISTRATIONS_PLUGIN_URL . '/assets/images/user-profile-photo.png';

					// Get all profession terms [slug => name]
					$profession_terms = get_event_registration_taxonomy_list('event_registration_professions');
					// Get saved profession value
					$profession_value = $participant_meta['_profession'][0] ?? '';
					$profession_slug = $profession_value;
					// If it's a name, convert to slug
					if ($profession_value && !isset($profession_terms[$profession_value])) {
						$found_slug = array_search($profession_value, $profession_terms);
						if ($found_slug) {
							$profession_slug = $found_slug;
						}
					}
					$participant_details[] = [
						'name'        => $participant_name,
						'profession'  => $profession_slug,
						'profile_photo' => esc_url($profile_picture)
					];

					// Email to participant
					$host_meta = get_user_meta($user_id);
					$host_company = $host_meta['_company_name'][0] ?? '';
					$host_city = $host_meta['_city'][0] ?? '';
					$all_countries = wpem_get_all_countries();
					$host_country = $all_countries[$host_meta['_country'][0]] ?? '';
					$event_name = get_the_title($event_id) ?: '';
					$status_text = "Pending";
					$meeting_description = $message;
					$company_name = $participant_meta['_company_name'][0] ?? '';
					$participant_city = $participant_meta['_city'][0] ?? '';
					$participant_country = $all_countries[$participant_meta['_country'][0]] ?? '';
					$profession = (object) ['name' => $participant_meta['_profession'][0] ?? ''];
					
					// Action buttons
					$calendar_button = "<a href='#' style='background:#0073aa;color:#fff;padding:8px 15px;text-decoration:none;border-radius:4px;margin-right:10px;'>Add to Calendar</a>";
					$view_meeting_button = "<a href='#' style='background:#444;color:#fff;padding:8px 15px;text-decoration:none;border-radius:4px;'>View Meeting</a>";

					$subject = "{$sender_user->display_name} requested a meeting with you";

					// Styled HTML email body
					$body = sprintf(
						wp_kses(
							__(
								"<div style='font-family: Arial, sans-serif; font-size: 15px; line-height: 1.5; background-color: #f6f6f6; padding: 20px;'>
									<div style='max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 6px; overflow: hidden; border: 1px solid #e0e0e0;'>
										
										<div style='background-color: #0073aa; color: #ffffff; padding: 15px 20px; font-size: 18px; font-weight: bold;'>
											New Meeting Request
										</div>

										<div style='padding: 20px; color: #333333;'>
											<p>Hello %1\$s,</p>
											<p><strong>%2\$s</strong> has requested a meeting with you.</p>

											<h3 style='margin-top: 25px; font-size: 16px; color: #0073aa;'>Meeting Information</h3>
											<p><strong>Event:</strong> %3\$s</p>
											<p><strong>Date:</strong> %16\$s</p>
											<p><strong>Time:</strong> %4\$s</p>

											<h4 style='margin-top: 20px; font-size: 15px; color: #0073aa;'>Host Details</h4>
											<p><strong>Name:</strong> %2\$s<br>
											<strong>Company:</strong> %5\$s<br>
											<strong>City:</strong> %6\$s<br>
											<strong>Country:</strong> %7\$s</p>

											<h4 style='margin-top: 20px; font-size: 15px; color: #0073aa;'>Receiver (You)</h4>
											<p><strong>Name:</strong> %1\$s<br>
											<strong>Title:</strong> %8\$s<br>
											<strong>Organization:</strong> %9\$s<br>
											<strong>Location:</strong> %10\$s, %11\$s<br>
											<strong>Status:</strong> %12\$s</p>

											<p style='margin-top: 20px;'><strong>Message:</strong><br>%13\$s</p>

											<div style='margin-top: 25px;'>%14\$s %15\$s</div>
										</div>
									</div>
								</div>",
								'wp-event-manager-registrations'
							),
							[
								'div' => ['style' => true],
								'p'   => ['style' => true],
								'strong' => [],
								'br' => [],
								'h3' => ['style' => true],
								'h4' => ['style' => true],
								'a' => ['href' => true, 'target' => true, 'style' => true]
							]
						),
						esc_html($participant_name),
						esc_html($sender_user->display_name),
						esc_html($event_name),
						esc_html($formatted_time),
						esc_html($host_company),
						esc_html($host_city),
						esc_html($host_country),
						esc_html($profession->name ?? ''),
						esc_html($company_name),
						esc_html($participant_city),
						esc_html($participant_country),
						esc_html($status_text),
						nl2br(esc_html($meeting_description)),
						$calendar_button,
						$view_meeting_button,
						esc_html($formatted_date)
					);

					// Allow custom filter
					$body = apply_filters(
						'wpem_registration_meeting_request_email_body',
						$body,
						$sender_user,
						$participant_user,
						$event_name,
						$formatted_time,
						$host_company,
						$host_city,
						$host_country,
						$profession,
						$company_name,
						$participant_city,
						$participant_country,
						$status_text,
						$meeting_description
					);

					wp_mail($participant_email, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
				}
			}

			return new WP_REST_Response([
				'code'    => 200,
				'status'  => 'OK',
				'message' => 'Meeting created and styled emails sent!',
				'data'    => [
					'meeting_date' => $formatted_date,
					'time'         => $formatted_time,
					'participants' => $participant_details,
					'message'      => $message
				]
			], 200);
		}
	}
	/**
	 * Get all meetings for a given user in a given event.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response The response object.
	 * @since 1.1.0
	 */
	 public function wpem_get_matchmaking_user_meetings(WP_REST_Request $request) {
		global $wpdb;
		if (!get_option('enable_matchmaking', false)) {
			return new WP_REST_Response([
				'code' => 403,
				'status' => 'Disabled',
				'message' => 'Matchmaking functionality is not enabled.',
				'data' => null
			], 403);
		}

		$auth_check = $this->wpem_check_authorized_user();
		if ($auth_check) {
			return self::prepare_error_for_response(405);
		} else {
			$event_id = intval($request->get_param('event_id'));
			$user_id  = intval($request->get_param('user_id'));

			if (!$event_id || !$user_id) {
				return new WP_REST_Response([
					'code' => 400,
					'status' => 'error',
					'message' => 'Missing event_id or user_id.',
					'data' => null
				], 400);
			}

			$table = $wpdb->prefix . 'wpem_matchmaking_users_meetings';

			// Fetch ALL meetings for this event
			$all_meetings = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE event_id = %d", $event_id), ARRAY_A);

			if (empty($all_meetings)) {
				return new WP_REST_Response([
					'code' => 404,
					'status' => 'error',
					'message' => 'No meetings found for this event.',
					'data' => null
				], 404);
			}

			$meeting_data = [];

			foreach ($all_meetings as $meeting) {
				$participant_statuses = maybe_unserialize($meeting['participant_ids']);
				if (!is_array($participant_statuses)) {
					$participant_statuses = [];
				}

				// Check if current user is host or in participant list
				if ((int)$meeting['user_id'] !== $user_id && !array_key_exists($user_id, $participant_statuses)) {
					continue;
				}

				$participants_info = [];
				foreach ($participant_statuses as $pid => $status) {
					// Get all user data from user meta
					$display_name = get_user_meta($pid, 'display_name', true);
					if (empty($display_name)) {
						$first_name = get_user_meta($pid, 'first_name', true);
						$last_name = get_user_meta($pid, 'last_name', true);
						$display_name = trim("$first_name $last_name");
					}

					// Get profile data from user meta (assuming these are stored as meta)
					$profile_photo = get_wpem_user_profile_photo($pid) ?: EVENT_MANAGER_REGISTRATIONS_PLUGIN_URL . '/assets/images/user-profile-photo.png';
					$company_name = get_user_meta($pid, '_company_name', true);
					$profession_terms = get_event_registration_taxonomy_list('event_registration_professions');
					$profession_value = get_user_meta($pid, '_profession', true);
					$profession_slug = $profession_value;
					if ($profession_value && !isset($profession_terms[$profession_value])) {
						$found_slug = array_search($profession_value, $profession_terms);
						if ($found_slug) {
							$profession_slug = $found_slug;
						}
					}
					$participants_info[] = [
						'id'         => (int)$pid,
						'status'     => (int)$status,
						'name'       => $display_name,
						'profile_photo'      => $profile_photo,
						'profession' => $profession_slug,
						'company_name'    => !empty($company_name) ? esc_html($company_name) : '',
					];
				}
				
				$host_id = (int)$meeting['user_id'];
				// Get host data from user meta
				$host_display_name = get_user_meta($host_id, 'display_name', true);
				if (empty($host_display_name)) {
					$first_name = get_user_meta($host_id, 'first_name', true);
					$last_name = get_user_meta($host_id, 'last_name', true);
					$host_display_name = trim("$first_name $last_name");
				}

				// Get host profile data from user meta
				$host_profile_photo = get_wpem_user_profile_photo($host_id) ?: EVENT_MANAGER_REGISTRATIONS_PLUGIN_URL . '/assets/images/user-profile-photo.png';
				$host_profession_value = get_user_meta($host_id, '_profession', true);
				$host_profession_slug = $host_profession_value;
				if ($host_profession_value && !isset($profession_terms[$host_profession_value])) {
					$found_slug = array_search($host_profession_value, $profession_terms);
					if ($found_slug) {
						$host_profession_slug = $found_slug;
					}
				}
				$host_company_name = get_user_meta($host_id, '_company_name', true);

				$host_info = [
					'id'         => $host_id,
					'name'       => $host_display_name,
					'profile_photo'      => !empty($host_profile_photo) ? esc_url($host_profile_photo) : '',
					'profession' => $host_profession_slug,
					'company_name'    => !empty($host_company_name) ? esc_html($host_company_name) : '',
				];
				
				$meeting_data[] = [
					'meeting_id'     => (int)$meeting['id'],
					'meeting_date'   => date_i18n('l, d F Y', strtotime($meeting['meeting_date'])),
					'start_time'     => date_i18n('H:i', strtotime($meeting['meeting_start_time'])),
					'end_time'       => date_i18n('H:i', strtotime($meeting['meeting_end_time'])),
					'message'        => $meeting['message'],
					'host_info'      => $host_info,
					'participants'   => $participants_info,
					'meeting_status' => (int)$meeting['meeting_status']
				];
			}

			if (empty($meeting_data)) {
				return new WP_REST_Response([
					'code' => 404,
					'status' => 'error',
					'message' => 'No meetings found for this user.',
					'data' => null
				], 404);
			}

			return new WP_REST_Response([
				'code'    => 200,
				'status'  => 'OK',
				'message' => 'Meetings retrieved successfully.',
				'data'    => $meeting_data
			], 200);
		}
	}
	/**
	 * Cancel upcomming matchmaking meetings by host.
	 *
	 * @since 1.1.0
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function wpem_cancel_matchmaking_meetings(WP_REST_Request $request) {
		global $wpdb;
        if (!get_option('enable_matchmaking', false)) {
            return new WP_REST_Response([
                'code' => 403,
                'status' => 'Disabled',
                'message' => 'Matchmaking functionality is not enabled.',
                'data' => null
            ], 403);
        }
		$auth_check = $this->wpem_check_authorized_user();
		if ($auth_check) {
			return self::prepare_error_for_response(405);
		} else {
			$meeting_id = intval($request->get_param('meeting_id'));
			$user_id    = intval($request->get_param('user_id'));

			if (!$meeting_id || !$user_id) {
				return new WP_REST_Response(['code' => 400, 'status' => 'error', 'message' => 'Missing meeting_id or user_id.'], 400);
			}

			$table = $wpdb->prefix . 'wpem_matchmaking_users_meetings';
			$meeting = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $meeting_id), ARRAY_A);

			if (!$meeting) {
				return new WP_REST_Response(['code' => 404, 'status' => 'error', 'message' => 'Meeting not found.'], 404);
			}

			if ((int)$meeting['user_id'] !== $user_id) {
				return new WP_REST_Response(['code' => 403, 'status' => 'error', 'message' => 'Only the host can cancel the meeting.'], 403);
			}

			$updated = $wpdb->update($table, ['meeting_status' => -1], ['id' => $meeting_id], ['%d'], ['%d']);

			if (!$updated) {
				return new WP_REST_Response(['code' => 500, 'status' => 'error', 'message' => 'Failed to cancel meeting.'], 500);
			}

			// Participant + host IDs
			$participant_ids = maybe_unserialize($meeting['participant_ids']);
			if (!is_array($participant_ids)) $participant_ids = [];
			$participant_ids = array_keys($participant_ids);
			$participant_ids[] = (int)$meeting['user_id'];
			$participant_ids = array_unique($participant_ids);

			// === Availability reset ===
			$event_id     = (int)$meeting['event_id'];
			$meeting_date = $meeting['meeting_date'];
			$start_time   = date('H:i', strtotime($meeting['meeting_start_time']));

			foreach ($participant_ids as $pid) {
				$slot_data = maybe_unserialize(get_user_meta($pid, '_meeting_availability_slot', true));
				if (!is_array($slot_data)) {
					$slot_data = [];
				}

				if (!isset($slot_data[$event_id])) $slot_data[$event_id] = [];
				if (!isset($slot_data[$event_id][$meeting_date])) $slot_data[$event_id][$meeting_date] = [];

				if (isset($slot_data[$event_id][$meeting_date][$start_time]) && $slot_data[$event_id][$meeting_date][$start_time] == 2) {
					$slot_data[$event_id][$meeting_date][$start_time] = 1;
					update_user_meta($pid, '_meeting_availability_slot', $slot_data);
				}
			}

			// Notify participants
			foreach ($participant_ids as $pid) {
				if ($pid == $user_id) continue;

				$user = get_userdata($pid);
				if ($user) {
					wp_mail(
						$user->user_email,
						'Meeting Cancelled',
						"<p>Hello {$user->display_name},</p>
						<p>The meeting scheduled on <strong>{$meeting_date}</strong> has been <strong>cancelled</strong>.</p>",
						['Content-Type: text/html; charset=UTF-8']
					);
				}
			}

			// Notify host
			$host = get_userdata($user_id);
			if ($host) {
				wp_mail(
					$host->user_email,
					'You Cancelled a Meeting',
					"<p>Hello {$host->display_name},</p>
					<p>You have cancelled the meeting scheduled on <strong>{$meeting_date}</strong>.</p>",
					['Content-Type: text/html; charset=UTF-8']
				);
			}

			return new WP_REST_Response([
				'code'    => 200,
				'status'  => 'OK',
				'message' => 'Meeting cancelled successfully.',
				'data'    => ['meeting_id' => $meeting_id],
			], 200);
		}
    }
        /**
         * Update a meeting's status according to participant accept or decline the meeting.
         *
         * @param WP_REST_Request $request
         * @return WP_REST_Response
         * @since 1.1.0
         */

	public function wpem_update_matchmaking_meeting_status(WP_REST_Request $request) {
		global $wpdb;
        if (!get_option('enable_matchmaking', false)) {
            return new WP_REST_Response([
                'code' => 403,
                'status' => 'Disabled',
                'message' => 'Matchmaking functionality is not enabled.',
                'data' => null
            ], 403);
        }
		$auth_check = $this->wpem_check_authorized_user();
		if ($auth_check) {
			return self::prepare_error_for_response(405);
		} else {
			$meeting_id = intval($request->get_param('meeting_id'));
			$user_id    = intval($request->get_param('user_id'));
			$new_status = intval($request->get_param('status'));

			if (!$meeting_id || !$user_id || !in_array($new_status, [0, 1], true)) {
				return new WP_REST_Response(['code' => 400, 'message' => 'Invalid parameters.'], 400);
			}

			$table = $wpdb->prefix . 'wpem_matchmaking_users_meetings';

			$meeting = $wpdb->get_row($wpdb->prepare("
				SELECT participant_ids, event_id, meeting_date, meeting_start_time 
				FROM $table 
				WHERE id = %d", $meeting_id
			));

			if (!$meeting) {
				return new WP_REST_Response(['code' => 404, 'message' => 'Meeting not found.'], 404);
			}

			$participant_data = maybe_unserialize($meeting->participant_ids);
			if (!is_array($participant_data)) {
				$participant_data = [];
			}

			if (!array_key_exists($user_id, $participant_data)) {
				return new WP_REST_Response(['code' => 403, 'message' => 'You are not a participant of this meeting.'], 403);
			}

			// If user is accepting, check for conflict in same slot
			if ($new_status === 1) {
				$event_id     = $meeting->event_id;
				$meeting_date = $meeting->meeting_date;
				$slot         = date('H:i', strtotime($meeting->meeting_start_time));

				$conflicting_meeting = $wpdb->get_row($wpdb->prepare("
					SELECT id FROM $table 
					WHERE id != %d
					AND event_id = %d 
					AND meeting_date = %s 
					AND meeting_start_time = %s 
					AND meeting_status != -1
				", $meeting_id, $event_id, $meeting_date, $meeting->meeting_start_time));

				if ($conflicting_meeting) {
					$existing_participants = $wpdb->get_var($wpdb->prepare("
						SELECT participant_ids FROM $table WHERE id = %d
					", $conflicting_meeting->id));

					$existing_participant_data = maybe_unserialize($existing_participants);
					if (is_array($existing_participant_data) && isset($existing_participant_data[$user_id]) && $existing_participant_data[$user_id] == 1) {
						return new WP_REST_Response([
							'code' => 409,
							'message' => 'You already have a confirmed meeting scheduled at this time slot.',
						], 409);
					}
				}
			}

			// Update this user's status
			$participant_data[$user_id] = $new_status;

			// Determine overall meeting status
			$meeting_status = (in_array(1, $participant_data, true)) ? 1 : 0;

			// Update meeting record
			$updated = $wpdb->update(
				$table,
				[
					'participant_ids' => maybe_serialize($participant_data),
					'meeting_status'  => $meeting_status,
				],
				['id' => $meeting_id],
				['%s', '%d'],
				['%d']
			);

			if ($updated === false) {
				return new WP_REST_Response(['code' => 500, 'message' => 'Failed to update meeting status.'], 500);
			}

			return new WP_REST_Response([
				'code' => 200,
				'status' => 'OK',
				'message' => $new_status ? 'Meeting accepted.' : 'Meeting declined.',
				'data' => [
					'meeting_id'         => $meeting_id,
					'participant_id'     => $user_id,
					'participant_status' => $new_status,
					'meeting_status'     => $meeting_status,
				]
			], 200);
		}
    }

    /**
     * Get available meeting slots of user
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     * @since 1.1.0
     */
    public function wpem_get_available_matchmaking_meeting_slots(WP_REST_Request $request) {
        if (!get_option('enable_matchmaking', false)) {
            return new WP_REST_Response([
                'code' => 403,
                'status' => 'Disabled',
                'message' => 'Matchmaking functionality is not enabled.',
                'data' => null
            ], 403);
        }
		$auth_check = $this->wpem_check_authorized_user();
		if ($auth_check) {
			return self::prepare_error_for_response(405);
		} else {
			$user_id  = intval($request->get_param('user_id')) ?: get_current_user_id();
			$default_slots = get_wpem_default_meeting_slots_for_user($user_id);
			$meta = get_user_meta($user_id, '_available_for_meeting', true);
			$meeting_available = ($meta !== '' && $meta !== null) ? ((int)$meta === 0 ? 0 : 1) : 1;

			return new WP_REST_Response([
				'code' => 200,
				'status' => 'OK',
				'message' => 'Availability slots fetched successfully.',
				'data' => [
					'available_for_meeting' => $meeting_available,
					'slots' => $default_slots
				]
			], 200);
		}
    }
	/**
	 * Update user's matchmaking availability slots and availability flag for meetings.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 * @since 1.1.0
	 */
	public function wpem_update_matchmaking_availability_slots(WP_REST_Request $request) {
		if (!get_option('enable_matchmaking', false)) {
            return new WP_REST_Response([
                'code' => 403,
                'status' => 'Disabled',
                'message' => 'Matchmaking functionality is not enabled.',
                'data' => null
            ], 403);
        }
		$auth_check = $this->wpem_check_authorized_user();
		if ($auth_check) {
			return self::prepare_error_for_response(405);
		} else {
			$submitted_slots       = $request->get_param('availability_slots'); // This is the "slots" array from GET
			
			$available_for_meeting = $request->get_param('available_for_meeting') ? 1 : 0;
			$user_id               = intval($request->get_param('user_id') ?: get_current_user_id());

			if (!$user_id || !is_array($submitted_slots)) {
				return new WP_REST_Response([
					'code'    => 400,
					'status'  => 'ERROR',
					'message' => 'Missing or invalid parameters.'
				], 400);
			}

			// Save directly in the same structure
			update_user_meta($user_id, '_meeting_availability_slot', $submitted_slots);
			update_user_meta($user_id, '_available_for_meeting', $available_for_meeting);

			return new WP_REST_Response([
				'code'    => 200,
				'status'  => 'OK',
				'message' => 'Availability updated successfully.',
				'data'    => [
					'available_for_meeting' => $available_for_meeting,
					'slots' => $submitted_slots
				]
			], 200);
		}
	}
	/**
	 * Retrieve common availability slots for meeting for given users .
	 *
	 * @param WP_REST_Request $request {
	 * @type int $event_id Event ID.
	 * @type array $user_ids User IDs.
	 * @since 1.1.0
	 *
	 * @return WP_REST_Response
	 * @throws Exception
	 */
	public function wpem_get_common_availability_slots($request) {
		global $wpdb;
		if (!get_option('enable_matchmaking', false)) {
            return new WP_REST_Response([
                'code' => 403,
                'status' => 'Disabled',
                'message' => 'Matchmaking functionality is not enabled.',
                'data' => null
            ], 403);
        }
		$auth_check = $this->wpem_check_authorized_user();
		if ($auth_check) {
			return self::prepare_error_for_response(405);
		} else {
			$event_id = intval($request->get_param('event_id'));
			$user_ids = $request->get_param('user_ids');
			$date     = sanitize_text_field($request->get_param('date'));

			if (!is_array($user_ids) || empty($user_ids) || !$event_id || !$date) {
				return new WP_REST_Response([
					'code'    => 400,
					'status'  => 'ERROR',
					'message' => 'Invalid parameters.',
					'data'    => [],
				], 400);
			}

			$all_user_slots = [];

			// Step 1: Get available slots for each user (value === 1)
			foreach ($user_ids as $user_id) {
				$raw_data = get_wpem_default_meeting_slots_for_user($user_id, $date);
				
				$available_slots = array_keys(array_filter(
					$raw_data,
					fn($v) => $v == 1
				));
				
				if (empty($available_slots)) {
					return new WP_REST_Response([
						'code'    => 200,
						'status'  => 'OK',
						'message' => 'No common slots found.',
						'data'    => ['common_slots' => []],
					]);
				}

				$all_user_slots[] = $available_slots;
			}

			// Step 2: Find intersection of slots between all users
			$common_slots = array_shift($all_user_slots);
			foreach ($all_user_slots as $slots) {
				$common_slots = array_intersect($common_slots, $slots);
			}

			sort($common_slots);

			// Step 3: Find booked slots for given date
			$table = $wpdb->prefix . 'wpem_matchmaking_users_meetings';
			$rows = $wpdb->get_results($wpdb->prepare(
				"SELECT meeting_start_time, participant_ids, user_id 
				FROM {$table} 
				WHERE meeting_date = %s",
				$date
			), ARRAY_A);

			$booked_slots = [];
			foreach ($rows as $row) {
				$meeting_time    = date('H:i', strtotime($row['meeting_start_time']));
				$creator_id      = intval($row['user_id']);
				$participant_ids = maybe_unserialize($row['participant_ids']);

				if (!is_array($participant_ids)) {
					$participant_ids = [];
				}

				foreach ($user_ids as $u_id) {
					$u_id = intval($u_id);

					// Condition 1: Creator is the user
					if ($creator_id === $u_id) {
						$booked_slots[] = $meeting_time;
						break;
					}

					// Condition 2: User is a participant with status 0 or 1
					if (isset($participant_ids[$u_id]) && in_array($participant_ids[$u_id], [0, 1], true)) {
						$booked_slots[] = $meeting_time;
						break;
					}
				}
			}

			// Step 4: Build combined slot list
			$combined_slots = [];
			foreach ($common_slots as $slot) {
				$combined_slots[] = [
					'time'      => $slot,
					'is_booked' => in_array($slot, $booked_slots, true),
				];
			}

			return new WP_REST_Response([
				'code'    => 200,
				'status'  => 'OK',
				'message' => count($combined_slots) ? 'Common availability slots retrieved successfully.' : 'No common slots found.',
				'data'    => [
					'event_id'     => $event_id,
					'date'         => $date,
					'common_slots' => $combined_slots,
				],
			], 200);
		}
	}
	/**
	 * Retrieve general matchmaking settings.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 * @since 1.1.0
	 */
	public function wpem_get_general_matchmaking_settings(WP_REST_Request $request) {
		if (!get_option('enable_matchmaking', false)) {
			return new WP_REST_Response([
				'code'    => 403,
				'status'  => 'Disabled',
				'message' => 'Matchmaking is disabled.',
				'data'    => null
			], 403);
		}
		$auth_check = $this->wpem_check_authorized_user();
		if ($auth_check) {
			return self::prepare_error_for_response(405);
		} else {
			$settings = [
				'request_mode'     => get_option('wpem_meeting_request_mode'), 
				'scheduling_mode'     => get_option('wpem_meeting_scheduling_mode'),
				'attendee_limit'   => get_option('wpem_meeting_attendee_limit'),
				'meeting_expiration' => get_option('wpem_meeting_expiration'),
				'enable_matchmaking' => get_option('enable_matchmaking'),
				'participant_activation' => get_option('participant_activation'),
			];

			return new WP_REST_Response([
				'code'    => 200,
				'status'  => 'OK',
				'message' => 'Matchmaking settings retrieved.',
				'data'    => $settings
			], 200);
		}
	}
}

new WPEM_REST_Create_Meeting_Controller();