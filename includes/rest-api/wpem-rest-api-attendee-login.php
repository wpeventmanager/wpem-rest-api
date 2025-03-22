<?php
defined( 'ABSPATH' ) || exit;

class WPEM_REST_Attendee_Login_Controller {

    protected $namespace = 'wpem/v1'; // Make sure version is included
    protected $rest_base = 'attendee-login';

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'handle_login' ),
                    'permission_callback' => '__return_true',
                    'args'                => array(
                        'email' => array(
                            'required' => true,
                            'type'     => 'string'
                        ),
                        'password' => array(
                            'required' => true,
                            'type'     => 'string'
                        )
                    )
                )
            )
        );
    }

    public function handle_login( $request ) {
        $email = sanitize_email( $request->get_param( 'email' ) );
        $password = $request->get_param( 'password' );

        if ( empty( $email ) || empty( $password ) ) {
            return new WP_REST_Response( array(
                'success' => false,
                'message' => 'Email and password are required.'
            ), 400 );
        }

        $user = get_user_by( 'email', $email );

        if ( ! $user || ! wp_check_password( $password, $user->user_pass, $user->ID ) ) {
            return new WP_REST_Response( array(
                'success' => false,
                'message' => 'Invalid email or password.'
            ), 401 );
        }

        return new WP_REST_Response( array(
            'success' => true,
            'message' => 'Login successful.',
            'data'    => array(
                'id'    => $user->ID,
                'name'  => $user->display_name,
                'email' => $user->user_email
            )
        ), 200 );
    }
}

new WPEM_REST_Attendee_Login_Controller();