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
     * The base of this controller's route.
     *
     * @var string
     */
    protected $rest_base = 'matchmaking/messages';

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
                    'callback'            => array($this, 'create_item'),
                    'permission_callback' => array($this, 'permission_check'),
                    'args'                => $this->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE),
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
     * Permission callback: ensure matchmaking is enabled and user is authorized.
     *
     * Note: This follows the plugin's pattern of returning the standardized
     * error payload via prepare_error_for_response on failure.
     *
     * @param WP_REST_Request $request
     * @return bool|WP_Error True if allowed, or sends JSON error.
     */
    public function permission_check($request) {
        $auth_check = $this->wpem_check_authorized_user();
        if ($auth_check) {
            return $auth_check; // Standardized error already sent
        }
        return true;
    }

    /**
     * Get a collection of messages.
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function get_items($request) {
        // Implementation for getting a collection of messages
        $args = array();
        
        // Add your query parameters here
        $args['page']     = $request['page'];
        $args['per_page'] = $request['per_page'];

        // Return response
        return rest_ensure_response($response);
    }

    /**
     * Get one item from the collection.
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function get_item($request) {
        $message_id = (int) $request['id'];
        
        // Get message logic here
        
        if (empty($message)) {
            return new WP_Error(
                'rest_message_invalid_id',
                __('Invalid message ID.'),
                array('status' => 404)
            );
        }

        return $this->prepare_item_for_response($message, $request);
    }

    /**
     * Create one item from the collection.
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function create_item($request) {
        // Message creation logic here
        
        $message = array(
            'sender_id'   => $request['sender_id'],
            'receiver_id' => $request['receiver_id'],
            'message'     => $request['message'],
            'created_at'  => current_time('mysql'),
        );

        // Save to database
        // $message_id = $this->save_message($message);

        $request->set_param('context', 'edit');
        $response = $this->prepare_item_for_response($message, $request);
        $response = rest_ensure_response($response);
        $response->set_status(201);
        
        return $response;
    }

    /**
     * Get conversation between two users.
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function get_conversation($request) {
        $user_id = (int) $request['user_id'];
        $paged = max(1, $request['paged']);
        $per_page = min(100, $request['per_page']);
        
        // Get conversation logic here
        
        $response = array(
            'total_users'  => 0,
            'current_page' => $paged,
            'per_page'     => $per_page,
            'last_page'    => 1,
            'users'        => array()
        );

        return rest_ensure_response($response);
    }

    /**
     * Prepare a single message output for response.
     *
     * @param array           $message Message data.
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response $response Response data.
     */
    public function prepare_item_for_response($message, $request) {
        $data = array(
            'id'          => $message['id'],
            'sender_id'   => $message['sender_id'],
            'receiver_id' => $message['receiver_id'],
            'message'     => $message['message'],
            'created_at'  => $message['created_at'],
        );

        $context = !empty($request['context']) ? $request['context'] : 'view';
        $data    = $this->add_additional_fields_to_object($data, $request);
        $data    = $this->filter_response_by_context($data, $context);

        $response = rest_ensure_response($data);
        
        return $response;
    }

    /**
     * Get the Message's schema, conforming to JSON Schema.
     *
     * @return array
     */
    public function get_item_schema() {
        $schema = array(
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'message',
            'type'       => 'object',
            'properties' => array(
                'id' => array(
                    'description' => __('Unique identifier for the message.'),
                    'type'        => 'integer',
                    'context'     => array('view', 'edit'),
                    'readonly'    => true,
                ),
                'sender_id' => array(
                    'description' => __('The ID of the user who sent the message.'),
                    'type'        => 'integer',
                    'context'     => array('view', 'edit'),
                    'required'    => true,
                ),
                'receiver_id' => array(
                    'description' => __('The ID of the user who received the message.'),
                    'type'        => 'integer',
                    'context'     => array('view', 'edit'),
                    'required'    => true,
                ),
                'message' => array(
                    'description' => __('The message content.'),
                    'type'        => 'string',
                    'context'     => array('view', 'edit'),
                    'required'    => true,
                    'arg_options' => array(
                        'sanitize_callback' => 'wp_kses_post',
                    ),
                ),
                'created_at' => array(
                    'description' => __("The date the message was created, in the site's timezone."),
                    'type'        => 'string',
                    'format'      => 'date-time',
                    'context'     => array('view', 'edit'),
                    'readonly'    => true,
                ),
            ),
        );

        return $this->add_additional_fields_schema($schema);
    }

    /**
     * Get the query params for collections.
     *
     * @return array
     */
    public function get_collection_params() {
        $params = parent::get_collection_params();

        $params['context']['default'] = 'view';

        $params['sender_id'] = array(
            'description'       => __('Limit results to messages sent by a specific user.'),
            'type'              => 'integer',
            'validate_callback' => 'rest_validate_request_arg',
        );

        $params['receiver_id'] = array(
            'description'       => __('Limit results to messages received by a specific user.'),
            'type'              => 'integer',
            'validate_callback' => 'rest_validate_request_arg',
        );

        return $params;
    }
}
new WPEM_REST_Matchmaking_Messages_Controller();