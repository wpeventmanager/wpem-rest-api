<?php
/**
 * WP Event Manager - REST API: Matchmaking Messages Controller
 *
 * @package wp-event-manager
 * @since   1.1.0
 */

defined('ABSPATH') || exit;

/**
 * Class WPEM_REST_Matchmaking_Messages_Controller
 *
 * @since 1.1.0
 */
class WPEM_REST_Matchmaking_Messages_Controller extends WPEM_REST_CRUD_Controller {

    /**
     * Endpoint namespace.
     *
     * @var string
     */
    protected $namespace = 'wpem';

    /**
     * Route base.
     *
     * @var string
     */
    protected $rest_base = 'matchmaking-messages';

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'), 10);
    }

    /**
     * Register the routes for the objects of the controller.
     *
     * @since 1.1.0
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array($this, 'get_items'),
                    'permission_callback' => array($this, 'permission_check'),
                    'args'                => $this->get_collection_params(),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array($this, 'send_message'),
                    'permission_callback' => array($this, 'permission_check'),
                    'args'                => array(),
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array($this, 'update_item'),
                    'permission_callback' => array($this, 'permission_check'),
                    'args'                => $this->get_endpoint_args_for_item_schema(WP_REST_Server::EDITABLE),
                ),
                array(
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => array($this, 'delete_item'),
                    'permission_callback' => array($this, 'permission_check'),
                    'args'                => $this->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE),
                ),
                'schema' => array($this, 'get_public_item_schema'),
            )
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\d]+)',
            array(
                'args'   => array(
                    'id' => array(
                        'description' => __('Unique identifier for the message.'),
                        'type'        => 'integer',
                    ),
                ),
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array($this, 'get_item'),
                    'permission_callback' => array($this, 'permission_check'),
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array($this, 'update_item'),
                    'permission_callback' => array($this, 'permission_check'),
                    'args'                => $this->get_endpoint_args_for_item_schema(WP_REST_Server::EDITABLE),
                ),
                array(
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => array($this, 'delete_item'),
                    'permission_callback' => array($this, 'permission_check'),
                ),
                'schema' => array($this, 'get_public_item_schema'),
            )
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/conversation',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array($this, 'get_conversation'),
                    'permission_callback' => array($this, 'permission_check'),
                    'args'                => array(
                        'paged' => array(
                            'description'       => __('Current page of the collection.'),
                            'type'              => 'integer',
                            'default'           => 1,
                            'minimum'           => 1,
                            'validate_callback' => 'rest_validate_request_arg',
                        ),
                        'per_page' => array(
                            'description'       => __('Maximum number of items to be returned in result set.'),
                            'type'              => 'integer',
                            'default'           => 10,
                            'minimum'           => 1,
                            'maximum'           => 100,
                            'validate_callback' => 'rest_validate_request_arg',
                        ),
                    ),
                ),
                'schema' => array($this, 'get_public_item_schema'),
            )
        );
    }

    /**
     * Get a collection of messages.
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function get_items($request) {
        global $wpdb;
        
        $user_id  = wpem_rest_get_current_user_id();
        $partner_id   = intval($request->get_param('partner_id'));

        if ( !$partner_id ) {
            return self::prepare_error_for_response(404);
        }

        // Pagination setup
        $page     = max(1, intval($request->get_param('page') ?: 1));
        $per_page = max(1, min(100, intval($request->get_param('per_page') ?: 10))); // hard cap
        $offset   = ($page - 1) * $per_page;

        $table = WPEM_MATCHMAKING_MESSAGES_TABLE;

        // Total message count (union not needed here)
        $total_messages = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table
            WHERE (sender_id = %d AND receiver_id = %d) 
                OR (sender_id = %d AND receiver_id = %d)",
            $user_id, $partner_id, $partner_id, $user_id
        ));

        // Optimized paginated messages using UNION ALL (index friendly)
        $sql = $wpdb->prepare(
            "(SELECT id, sender_id as user_id, receiver_id as partner_id, message, created_at 
            FROM $table
            WHERE sender_id = %d AND receiver_id = %d)
            UNION ALL
            (SELECT id, sender_id, receiver_id, message, created_at 
            FROM $table
            WHERE sender_id = %d AND receiver_id = %d)
            ORDER BY created_at DESC
            LIMIT %d OFFSET %d",
            $user_id, $partner_id,
            $partner_id, $user_id,
            $per_page, $offset
        );

        $messages = $wpdb->get_results($sql, ARRAY_A);

        // Process messages (split text & image)
        foreach ($messages as &$msg) {
            $parts      = preg_split("/\n+/", trim($msg['message']));
            $text_parts = [];

            foreach ($parts as $part) {
                $part = trim($part);

                if (filter_var($part, FILTER_VALIDATE_URL) &&
                    preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $part)) {
                    $msg['image'] = $part;
                } elseif (!empty($part)) {
                    $text_parts[] = $part;
                }
            }

            // Keep only text in message field
            $msg['message'] = implode(' ', $text_parts);
        }

        $total_pages = max(1, ceil($total_messages / $per_page));

        $response_data = self::prepare_error_for_response(200);
        $response_data['data'] = [
            'total_message_count' => intval($total_messages),
            'current_page'     => $page,
            'last_page'        => $total_pages,
            'total_pages'      => $total_pages,
            'messages'         => array_filter($messages, function ($msg) {
                unset($msg['image']);
                return $msg;
            }),
        ];
        return wp_send_json($response_data);
    }

    /**
     * Create one item from the collection.
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function send_message( WP_REST_Request $request ) {
        global $wpdb;
        // --- Current User ---
        $user_id    = wpem_rest_get_current_user_id();
        $partner_id = intval( $request->get_param( 'partner_id' ) );
        $text_message = sanitize_textarea_field( $request->get_param( 'message' ) );

        // --- Validate Users ---
        $sender_user   = get_user_by( 'id', $user_id );
        $receiver_user = get_user_by( 'id', $partner_id );

        if ( ! $sender_user || ! $receiver_user ) {
            return self::prepare_error_for_response( 400 );
        }

        $sender_name   = trim( get_user_meta( $user_id, 'first_name', true ) . ' ' . get_user_meta( $user_id, 'last_name', true ) );
        $receiver_name = trim( get_user_meta( $partner_id, 'first_name', true ) . ' ' . get_user_meta( $partner_id, 'last_name', true ) );

        // --- Notification Preferences ---
        $sender_notify   = (int) get_user_meta( $user_id, '_message_notification', true );
        $receiver_notify = (int) get_user_meta( $partner_id, '_message_notification', true );

        if ( $sender_notify !== 1 || $receiver_notify !== 1 ) {
            return self::prepare_error_for_response( 403 );
        }

        // --- File Upload (if provided) ---
        $image_url = '';
        if ( ! empty( $_FILES['file']['tmp_name'] ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            $uploaded = wp_handle_upload( $_FILES['file'], [ 'test_form' => false ] );
            if ( isset( $uploaded['url'] ) ) {
                $image_url = esc_url_raw( $uploaded['url'] );
            }
        }

        // --- Ensure at least one of message or image ---
        if ( ! $text_message && ! $image_url ) {
            return self::prepare_error_for_response( 400 );
        }

        // --- Build Final Message ---
        $final_message = $text_message;
        if ( $image_url ) {
            $final_message .= ( $final_message ? "\n\n" : '' ) . $image_url;
        }

        // --- Conversation Parent ID ---
        $table = $wpdb->prefix . 'wpem_matchmaking_users_messages';
        $parent_id = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $table
            WHERE (receiver_id = %d AND sender_id = %d)
                OR (sender_id = %d AND receiver_id = %d)
            ORDER BY created_at ASC LIMIT 1",
            $user_id, $partner_id,
            $partner_id, $user_id
        ) );

        // --- Insert Message ---
        $wpdb->insert(
            $table,
            [
                'parent_id'   => $parent_id ?: 0,
                'sender_id'     => $user_id,
                'receiver_id'  => $partner_id,
                'message'     => $final_message,
                'created_at'  => current_time( 'mysql' ),
            ],
            [ '%d', '%d', '%d', '%s', '%s' ]
        );

        $insert_id = $wpdb->insert_id;

        // --- Email Helper ---
        $send_message_email = function ( $to, $subject, $from_name, $text_message, $image_url ) {
            $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
            $body  = "Hello, this is a message from {$from_name}<br><br>";
            if ( $text_message ) {
                $body .= "Message:<br>" . nl2br( esc_html( $text_message ) ) . "<br><br>";
            }
            if ( $image_url ) {
                $body .= "<p><img src='{$image_url}' alt='Attachment' style='max-width:400px;'></p>";
            }
            $body .= "Thank you.";
            wp_mail( $to, $subject, $body, $headers );
        };

        // --- Send Emails ---
        $send_message_email(
            $receiver_user->user_email,
            'New Message from ' . $sender_name,
            $sender_name,
            $text_message,
            $image_url
        );

        $send_message_email(
            $sender_user->user_email,
            'Your Message to ' . $receiver_name,
            $receiver_name,
            $text_message,
            $image_url
        );

        // --- Response ---
        return new WP_REST_Response( [
            'code'    => 200,
            'status'  => 'OK',
            'message' => 'Message sent successfully.',
            'data'    => [
                'id'         => $insert_id,
                'parent_id'  => $parent_id ?: 0,
                'user_id'    => $user_id,
                'partner_id' => $partner_id,
                'message'    => $text_message ?: null,
                'image'      => $image_url ?: null,
                'created_at' => current_time( 'mysql' ),
            ],
        ], 200 );
    }

    /**
     * Get conversation between two users.
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function get_conversation($request) {
        global $wpdb;
        $user_id  = wpem_rest_get_current_user_id();
        $paged    = max( 1, intval( $request->get_param( 'paged' ) ) );
        $per_page = max( 1, intval( $request->get_param( 'per_page' ) ) );

        $messages_tbl = $wpdb->prefix . 'wpem_matchmaking_users_messages';

        /**
         * Step 1: Find unique conversation partners
         */
        $conversation_user_ids = $wpdb->get_col( $wpdb->prepare( "
            SELECT DISTINCT other_user FROM (
                SELECT receiver_id AS other_user FROM $messages_tbl WHERE sender_id = %d
                UNION
                SELECT sender_id AS other_user FROM $messages_tbl WHERE receiver_id = %d
            ) AS temp
            WHERE other_user != %d
        ", $user_id, $user_id, $user_id ) );

        if ( empty( $conversation_user_ids ) ) {
            return self::prepare_error_for_response(404);
        }

        /**
         * Step 2: Pagination
         */
        $total_count   = count( $conversation_user_ids );
        $last_page     = ceil( $total_count / $per_page );
        $offset        = ( $paged - 1 ) * $per_page;
        $paginated_ids = array_slice( $conversation_user_ids, $offset, $per_page );

        /**
         * Step 3: Build conversation list with last message
         */
        $results = [];
        foreach ( $paginated_ids as $partner_id ) {
            $last_message_row = $wpdb->get_row( $wpdb->prepare( "
                SELECT message, created_at
                FROM $messages_tbl
                WHERE (sender_id = %d AND receiver_id = %d)
                OR (sender_id = %d AND receiver_id = %d)
                ORDER BY created_at DESC
                LIMIT 1
            ", $user_id, $partner_id, $partner_id, $user_id ) );

            // Build display name
            $display_name = get_user_meta( $partner_id, 'display_name', true );
            if ( empty( $display_name ) ) {
                $first_name   = get_user_meta( $partner_id, 'first_name', true );
                $last_name    = get_user_meta( $partner_id, 'last_name', true );
                $display_name = trim( "$first_name $last_name" );
            }

            // Detect if last message is an image
            $is_image = ( $last_message_row && preg_match( '/\.(jpg|jpeg|png|gif|webp)$/i', $last_message_row->message ) ) ? 1 : 0;

            $results[] = [
                'user_id'              => (int) $partner_id,
                'first_name'           => get_user_meta( $partner_id, 'first_name', true ),
                'last_name'            => get_user_meta( $partner_id, 'last_name', true ),
                'display_name'         => $display_name,
                'profile_photo'        => get_wpem_user_profile_photo( $partner_id ),
                'profession'           => get_user_meta( $partner_id, '_profession', true ),
                'company_name'         => get_user_meta( $partner_id, '_company_name', true ),
                'last_message'         => $last_message_row ? $last_message_row->message : null,
                'message_time'         => $last_message_row ? date( 'Y-m-d H:i:s', strtotime( $last_message_row->created_at ) ) : null,
                'last_message_is_image'=> $is_image,
            ];
        }

        /**
         * Step 4: Return formatted response
         */
        $response_data = self::prepare_error_for_response(200);
        $response_data['data'] = [
            'total_users_count' => intval($total_count),
            'current_page' => $paged,
            'per_page'     => $per_page,
            'last_page'    => $last_page,
            'users'        => $results,
            'user_status' => wpem_get_user_login_status(wpem_rest_get_current_user_id())
        ];
        return wp_send_json($response_data);
    }

    /**
     * Get the query params for collections.
     *
     * @return array
     */
    public function get_collection_params() {
        $params = parent::get_collection_params();

        $params['context']['default'] = 'view';

        $params['partner_id'] = array(
            'description'       => __('Limit results to messages sent by a specific user.'),
            'type'              => 'integer',
            'validate_callback' => 'rest_validate_request_arg',
        );

        return $params;
    }
}
new WPEM_REST_Matchmaking_Messages_Controller();