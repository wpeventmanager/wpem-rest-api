<?php

class WPEM_REST_Create_Meeting_Controller {

    protected $namespace = 'wpem';
    protected $rest_base = 'create-meeting';

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        $auth_controller = new WPEM_REST_Authentication();

        register_rest_route($this->namespace, '/' . $this->rest_base, [
            'methods'  => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'create_meeting'],
            'permission_callback' => [$auth_controller, 'check_authentication'],
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
            'callback' => [$this, 'get_user_meetings'],
            'permission_callback' => [$auth_controller, 'check_authentication'],
        ]);

		register_rest_route($this->namespace, '/cancel-meeting', [
			'methods'  => WP_REST_Server::CREATABLE,
			'callback' => [$this, 'cancel_meeting'],
			'permission_callback' => [$auth_controller, 'check_authentication'],
			'args' => [
				'meeting_id' => ['required' => true, 'type' => 'integer'],
				'user_id'    => ['required' => true, 'type' => 'integer'],
			],
		]);

        register_rest_route($this->namespace, '/update-meeting-status', [
			'methods'  => WP_REST_Server::CREATABLE,
			'callback' => [$this, 'update_meeting_status'],
			'permission_callback' => [$auth_controller, 'check_authentication'],
			'args' => [
				'meeting_id' => ['required' => true, 'type' => 'integer'],
				'user_id'    => ['required' => true, 'type' => 'integer'],
				'status'     => ['required' => true, 'type' => 'integer', 'enum' => [0, 1]],
			],
		]);
		register_rest_route($this->namespace, '/get-availability-slots', [
			'methods'  => WP_REST_Server::READABLE,
			'callback' => [$this, 'get_booked_meeting_slots'],
			'permission_callback' => [$auth_controller, 'check_authentication'],
			'args' => [
				'event_id' => [
					'required' => true,
					'type' => 'integer'
				],
				'user_id' => [
					'required' => false,
					'type' => 'integer'
				]
			]
		]);
		register_rest_route($this->namespace, '/update-availability-slots', [
			'methods'  => WP_REST_Server::CREATABLE,
			'callback' => [$this, 'update_availability_slots_rest'],
			'permission_callback' => [$auth_controller, 'check_authentication'],
			'args' => [
				'event_id' => [
					'required' => true,
					'type' => 'integer'
				],
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
			'callback' => [$this, 'get_common_availability_slots'],
			'permission_callback' => [$auth_controller, 'check_authentication'],
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
			'callback'            => [$this, 'get_matchmaking_settings'],
			'permission_callback' => '__return_true', // if no auth needed
		]);
    }
     public function create_meeting(WP_REST_Request $request) {
		if (!get_option('enable_matchmaking', false)) {
			return new WP_REST_Response([
				'code' => 403,
				'status' => 'Disabled',
				'message' => 'Matchmaking functionality is not enabled.',
				'data' => null
			], 403);
		}

		global $wpdb;

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
			'meeting_start_time' => $slot,
			'meeting_end_time'   => date("H:i", strtotime($slot . " +1 hour")),
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
		$formatted_time = date("h:i A", strtotime($slot));
		$sender_user = get_userdata($user_id);

		$participant_details = [];

		if (!empty($participants)) {
			$profile_table = $wpdb->prefix . 'wpem_matchmaking_users';
			$placeholders = implode(',', array_fill(0, count($participants), '%d'));
			$query = $wpdb->prepare("SELECT * FROM $profile_table WHERE user_id IN ($placeholders)", ...array_keys($participants));
			$results = $wpdb->get_results($query, ARRAY_A);

			foreach ($results as $participant) {
				$participant_user = get_userdata($participant['user_id']);
				$participant_name = $participant_user->display_name ?? 'User';
				$participant_email = $participant_user->user_email ?? '';
				$profile_picture = $participant['profile_photo'] ?: EVENT_MANAGER_REGISTRATIONS_PLUGIN_URL . '/assets/images/user-profile-photo.png';

				$participant_details[] = [
					'name'            => $participant_name,
					'profession'      => $participant['profession'] ?? '',
					'profile_picture' => esc_url($profile_picture)
				];

				// Email to participant
				$host_profile = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}wpem_matchmaking_users WHERE user_id = %d", $user_id), ARRAY_A);
				$host_company = $host_profile['company_name'] ?? '';
				$host_city    = $host_profile['city'] ?? '';
				$all_countries = wpem_registration_get_all_countries();
				$host_country = $all_countries[$host_profile['country']] ?? '';
				$event_name = get_the_title($event_id) ?: '';
				$timezone_abbr = wp_timezone_string();

				$subject = "{$sender_user->display_name} requested a meeting with you";
				$body = "
					<p>Hello {$participant_name},</p>
					<p><strong>{$sender_user->display_name}</strong> has requested a meeting with you.</p>
					<p><strong>Event:</strong> {$event_name}</p>
					<p><strong>Date:</strong> {$formatted_date}</p>
					<p><strong>Time:</strong> {$formatted_time} {$timezone_abbr}</p>
					<p><strong>Company:</strong> {$host_company}<br>
					   <strong>City:</strong> {$host_city}<br>
					   <strong>Country:</strong> {$host_country}</p>
					<p><strong>Message:</strong> {$message}</p>
				";

				wp_mail($participant_email, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
			}
		}

		// Email to sender
		$participant_names = implode(', ', array_column($participant_details, 'name'));
		wp_mail(
			$sender_user->user_email,
			'Your Meeting Request Has Been Sent',
			"
			<p>Hello {$sender_user->display_name},</p>
			<p>Your meeting request has been sent to: <strong>{$participant_names}</strong>.</p>
			<p><strong>Date:</strong> {$formatted_date}</p>
			<p><strong>Time:</strong> {$formatted_time}</p>
			<p><strong>Message:</strong> {$message}</p>
			",
			['Content-Type: text/html; charset=UTF-8']
		);
		// Update booked slot (2) for the host only
		$availability_table = $wpdb->prefix . 'wpem_matchmaking_users';

		$serialized = $wpdb->get_var($wpdb->prepare(
			"SELECT meeting_availability_slot FROM $availability_table WHERE user_id = %d",
			$user_id
		));

		$slot_data = maybe_unserialize($serialized);
		if (!is_array($slot_data)) {
			$slot_data = [];
		}

		if (!isset($slot_data[$event_id])) {
			$slot_data[$event_id] = [];
		}
		if (!isset($slot_data[$event_id][$meeting_date])) {
			$slot_data[$event_id][$meeting_date] = [];
		}

		// Set slot as 2 (booked)
		$slot_data[$event_id][$meeting_date][$slot] = 2;

		// Update DB for host only
		$wpdb->update(
			$availability_table,
			['meeting_availability_slot' => maybe_serialize($slot_data)],
			['user_id' => $user_id],
			['%s'],
			['%d']
		);

		return new WP_REST_Response([
			'code'    => 200,
			'status'  => 'OK',
			'message' => 'Meeting created and emails sent!',
			'data'    => [
				'meeting_date' => $formatted_date,
				'time'         => $formatted_time,
				'participants' => $participant_details,
				'message'      => $message
			]
		], 200);
	}
   public function get_user_meetings(WP_REST_Request $request) {
		if (!get_option('enable_matchmaking', false)) {
			return new WP_REST_Response([
				'code' => 403,
				'status' => 'Disabled',
				'message' => 'Matchmaking functionality is not enabled.',
				'data' => null
			], 403);
		}

		global $wpdb;

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
				//if ((int)$pid === $user_id) continue;

				$user_data = get_userdata($pid);
				$display_name = $user_data ? $user_data->display_name : '';

				$meta = $wpdb->get_row($wpdb->prepare(
					"SELECT profile_photo, profession, company_name FROM {$wpdb->prefix}wpem_matchmaking_users WHERE user_id = %d",
					$pid
				));

				$participants_info[] = [
					'id'         => (int)$pid,
					'status'     => (int)$status,
					'name'       => $display_name,
					'image'      => !empty($meta->profile_photo) ? esc_url($meta->profile_photo) : '',
					'profession' => isset($meta->profession) ? esc_html($meta->profession) : '',
					'company'    => isset($meta->company_name) ? esc_html($meta->company_name) : '',
				];
			}
			$host_id = (int)$meeting['user_id'];
			$host_user = get_userdata($host_id);
			$host_meta = $wpdb->get_row($wpdb->prepare(
				"SELECT profile_photo, profession, company_name FROM {$wpdb->prefix}wpem_matchmaking_users WHERE user_id = %d",
				$host_id
			));

			$host_info = [
				'id'         => $host_id,
				'name'       => $host_user ? $host_user->display_name : '',
				'image'      => !empty($host_meta->profile_photo) ? esc_url($host_meta->profile_photo) : '',
				'profession' => isset($host_meta->profession) ? esc_html($host_meta->profession) : '',
				'company'    => isset($host_meta->company_name) ? esc_html($host_meta->company_name) : '',
			];
			$meeting_data[] = [
				'meeting_id'     => (int)$meeting['id'],
				'meeting_date'   => date_i18n('l, d F Y', strtotime($meeting['meeting_date'])),
				'start_time'     => date_i18n('h:i A', strtotime($meeting['meeting_start_time'])),
				'end_time'       => date_i18n('h:i A', strtotime($meeting['meeting_end_time'])),
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
	public function cancel_meeting(WP_REST_Request $request) {
		if (!get_option('enable_matchmaking', false)) {
			return new WP_REST_Response([
				'code' => 403,
				'status' => 'Disabled',
				'message' => 'Matchmaking functionality is not enabled.',
				'data' => null
			], 403);
		}

		global $wpdb;

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
		$start_time   = date('H:i', strtotime($meeting['meeting_start_time'])); // convert from H:i:s to H:i

		$profile_table = $wpdb->prefix . 'wpem_matchmaking_users';

		foreach ($participant_ids as $pid) {
			$current_slots_serialized = $wpdb->get_var($wpdb->prepare(
				"SELECT meeting_availability_slot FROM $profile_table WHERE user_id = %d",
				$pid
			));

			$current_slots = maybe_unserialize($current_slots_serialized);
			if (!is_array($current_slots)) $current_slots = [];

			if (!isset($current_slots[$event_id])) $current_slots[$event_id] = [];
			if (!isset($current_slots[$event_id][$meeting_date])) $current_slots[$event_id][$meeting_date] = [];

			if (isset($current_slots[$event_id][$meeting_date][$start_time]) && $current_slots[$event_id][$meeting_date][$start_time] == 2) {
				$current_slots[$event_id][$meeting_date][$start_time] = 1;

				$wpdb->update(
					$profile_table,
					['meeting_availability_slot' => maybe_serialize($current_slots)],
					['user_id' => $pid],
					['%s'],
					['%d']
				);
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
	public function update_meeting_status(WP_REST_Request $request) {
		if (!get_option('enable_matchmaking', false)) {
			return new WP_REST_Response([
				'code' => 403,
				'status' => 'Disabled',
				'message' => 'Matchmaking functionality is not enabled.',
				'data' => null
			], 403);
		}

		global $wpdb;

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

		// Update availability slot for this user
		$availability_table = $wpdb->prefix . 'wpem_matchmaking_users';

		$serialized = $wpdb->get_var($wpdb->prepare(
			"SELECT meeting_availability_slot FROM $availability_table WHERE user_id = %d",
			$user_id
		));

		$slot_data = maybe_unserialize($serialized);
		if (!is_array($slot_data)) {
			$slot_data = [];
		}

		if (!isset($slot_data[$event_id])) {
			$slot_data[$event_id] = [];
		}
		if (!isset($slot_data[$event_id][$meeting_date])) {
			$slot_data[$event_id][$meeting_date] = [];
		}

		$slot_data[$event_id][$meeting_date][date('H:i', strtotime($meeting->meeting_start_time))] = ($new_status === 1) ? 2 : 1;

		$wpdb->update(
			$availability_table,
			['meeting_availability_slot' => maybe_serialize($slot_data)],
			['user_id' => $user_id],
			['%s'],
			['%d']
		);

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
    public function get_booked_meeting_slots(WP_REST_Request $request) {
		if (!get_option('enable_matchmaking', false)) {
			return new WP_REST_Response([
				'code' => 403,
				'status' => 'Disabled',
				'message' => 'Matchmaking functionality is not enabled.',
				'data' => null
			], 403);
		}

		global $wpdb;

		$event_id = intval($request->get_param('event_id'));
		$user_id  = intval($request->get_param('user_id')) ?: get_current_user_id();

		if (!$event_id || !$user_id) {
			return new WP_REST_Response([
				'code' => 400,
				'status' => 'error',
				'message' => 'Missing event_id or unauthorized access.',
				'data' => null
			], 400);
		}

		$table = $wpdb->prefix . 'wpem_matchmaking_users';

		// Get availability slot and available flag in single query
		$result = $wpdb->get_row(
			$wpdb->prepare("SELECT meeting_availability_slot, available_for_meeting FROM $table WHERE user_id = %d", $user_id),
			ARRAY_A
		);

		$saved_data = !empty($result['meeting_availability_slot']) ? maybe_unserialize($result['meeting_availability_slot']) : [];
		$available_flag = isset($result['available_for_meeting']) ? (int)$result['available_for_meeting'] : 0;

		$event_slots = $saved_data[$event_id] ?? [];

		if (is_array($event_slots)) {
			ksort($event_slots); // sort by date keys
		}

		return new WP_REST_Response([
			'code' => 200,
			'status' => 'OK',
			'message' => 'Availability slots fetched successfully.',
			'data' => [
				'available_for_meeting' => $available_flag,
				'slots' => $event_slots
			]
		], 200);
	}
	public function update_availability_slots_rest(WP_REST_Request $request) {
		global $wpdb;
		$table = $wpdb->prefix . 'wpem_matchmaking_users';

		$event_id              = intval($request->get_param('event_id'));
		$submitted_slots       = $request->get_param('availability_slots'); // Now: [ '2025-07-07' => [ '07:00' => 0 ] ]
		$available_for_meeting = $request->get_param('available_for_meeting') ? 1 : 0;
		$user_id               = intval($request->get_param('user_id') ?: get_current_user_id());

		if (!$user_id || !$event_id || !is_array($submitted_slots)) {
			return new WP_REST_Response([
				'code'    => 400,
				'status'  => 'ERROR',
				'message' => 'Missing or invalid parameters.'
			], 400);
		}

		$current_data = $wpdb->get_var($wpdb->prepare(
			"SELECT meeting_availability_slot FROM {$table} WHERE user_id = %d",
			$user_id
		));

		$availability_data = [];
		if (!empty($current_data)) {
			$maybe_unserialized = maybe_unserialize($current_data);
			if (is_array($maybe_unserialized)) {
				$availability_data = $maybe_unserialized;
			}
		}

		// Ensure event structure exists
		if (!isset($availability_data[$event_id])) {
			$availability_data[$event_id] = [];
		}

		// Update the passed slots directly
		foreach ($submitted_slots as $date => $slots) {
			if (!isset($availability_data[$event_id][$date])) {
				$availability_data[$event_id][$date] = [];
			}

			foreach ($slots as $time => $value) {
				// Accept only 0, 1, or 2 as valid values
				if (in_array($value, [0, 1, 2], true)) {
					$availability_data[$event_id][$date][$time] = $value;
				}
			}
		}

		$updated = $wpdb->update(
			$table,
			[
				'meeting_availability_slot' => maybe_serialize($availability_data),
				'available_for_meeting'     => $available_for_meeting,
			],
			['user_id' => $user_id],
			['%s', '%d'],
			['%d']
		);

		if ($updated !== false) {
			return new WP_REST_Response([
				'code'    => 200,
				'status'  => 'OK',
				'message' => 'Availability updated successfully.'
			], 200);
		} else {
			return new WP_REST_Response([
				'code'    => 500,
				'status'  => 'ERROR',
				'message' => 'Failed to update availability.'
			], 500);
		}
	}
	public function get_common_availability_slots($request) {
		global $wpdb;

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

		$placeholders = implode(',', array_fill(0, count($user_ids), '%d'));
		$query = "
			SELECT user_id, meeting_availability_slot 
			FROM {$wpdb->prefix}wpem_matchmaking_users 
			WHERE user_id IN ($placeholders)
		";
		$prepared = $wpdb->prepare($query, $user_ids);
		$results = $wpdb->get_results($prepared);

		if (empty($results)) {
			return new WP_REST_Response([
				'code'    => 200,
				'status'  => 'OK',
				'message' => 'No common slots found.',
				'data'    => ['common_slots' => []],
			]);
		}

		$all_user_slots = [];

		foreach ($results as $row) {
			$data = maybe_unserialize($row->meeting_availability_slot);

			if (
				empty($data) ||
				!isset($data[$event_id]) ||
				!isset($data[$event_id][$date]) ||
				!is_array($data[$event_id][$date])
			) {
				return new WP_REST_Response([
					'code'    => 200,
					'status'  => 'OK',
					'message' => 'No common slots found.',
					'data'    => ['common_slots' => []],
				]);
			}

			// Filter only available slots (value === 1)
			$available_slots = array_keys(array_filter($data[$event_id][$date], function($v) {
				return $v === 1;
			}));

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

		// Only one user: return their available slots
		if (count($all_user_slots) === 1) {
			sort($all_user_slots[0]);
			return new WP_REST_Response([
				'code'    => 200,
				'status'  => 'OK',
				'message' => 'Availability slots retrieved successfully.',
				'data'    => [
					'event_id'     => $event_id,
					'date'         => $date,
					'common_slots' => array_values($all_user_slots[0]),
				],
			]);
		}

		// Multiple users: intersect available time slots
		$common_slots = array_shift($all_user_slots);
		foreach ($all_user_slots as $slots) {
			$common_slots = array_intersect($common_slots, $slots);
		}

		sort($common_slots);

		return new WP_REST_Response([
			'code'    => 200,
			'status'  => 'OK',
			'message' => empty($common_slots) ? 'No common slots found.' : 'Common availability slots retrieved successfully.',
			'data'    => [
				'event_id'     => $event_id,
				'date'         => $date,
				'common_slots' => array_values($common_slots),
			],
		]);
	}
	public function get_matchmaking_settings(WP_REST_Request $request) {
		if (!get_option('enable_matchmaking', false)) {
			return new WP_REST_Response([
				'code'    => 403,
				'status'  => 'Disabled',
				'message' => 'Matchmaking is disabled.',
				'data'    => null
			], 403);
		}

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

new WPEM_REST_Create_Meeting_Controller();
