<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * PubNub 3.0 Real-time Push Cloud API for CodeIgniter
 */
class Pubnub {
    private $CI;
    private $settings = array ();

    /**
     * Pubnub
     *
     * Init the Pubnub Client API
     */
    public function __construct() {
	    $this->CI = get_instance();
		$this->CI->load->config('pubnub');
		
		$this->settings['limit']      = 1800;
		$this->settings['server']     = 'http://' . $this->CI->config->item('pubnub_origin');
        $this->settings['pub-key']    = $this->CI->config->item('pubnub_pub_key');
        $this->settings['sub-key']    = $this->CI->config->item('pubnub_sub_key');
        $this->settings['secret-key'] = $this->CI->config->item('pubnub_secret_key');
    }

    /**
     * Publish
     *
     * Send a message to a channel.
     *
     * @param array $args with channel and message.
     * @return array success information.
     */
   public function publish($args) {
        ## Fail if bad input.
        if (!($args['channel'] && $args['message'])) {
            echo('Missing Channel or Message');
            return false;
        }

        ## Capture User Input
        $channel = $args['channel'];
        $message = json_encode($args['message']);

        ## Generate String to Sign
        $string_to_sign = implode( '/', array(
            $this->settings['pub-key'],
            $this->settings['sub-key'],
            $this->settings['secret-key'],
            $channel,
            $message
        ));

        ## Sign Message
        $signature = $this->settings['secret-key'] ? md5($string_to_sign) : '0';

        ## Fail if message too long.
        if (strlen($message) > $this->settings['limit']) {
            echo('Message TOO LONG (' . $this->settings['limit'] . ' LIMIT)');
            return array( 0, 'Message Too Long.' );
        }

        ## Send Message
        return $this->_request(array(
            'publish',
            $this->settings['pub-key'],
            $this->settings['sub-key'],
            $signature,
            $channel,
            '0',
            $message
        ));
    }

    /**
     * Subscribe
     *
     * This is BLOCKING.
     * Listen for a message on a channel.
     *
     * @param array $args with channel and message.
     * @return mixed false on fail, array on success.
     */
    public function subscribe($args) {
        ## Capture User Input
        $channel   = $args['channel'];
        $callback  = $args['callback'];
        $timetoken = isset($args['timetoken']) ? $args['timetoken'] : '0';

        ## Fail if missing channel
        if (!$channel) {
            echo("Missing Channel.\n");
            return false;
        }

        ## Fail if missing callback
        if (!$callback) {
            echo("Missing Callback.\n");
            return false;
        }

        ## Begin Recusive Subscribe
        try {
            ## Wait for Message
            $response = $this->_request(array(
                'subscribe',
                $this->settings['sub-key'],
                $channel,
                '0',
                $timetoken
            ));

            $messages          = $response[0];
            $args['timetoken'] = $response[1];

            ## If it was a timeout
            if (!count($messages)) {
                return $this->subscribe($args);
            }

            ## Run user Callback and Reconnect if user permits.
            foreach ($messages as $message) {
                if (!$callback($message)) return;
            }

            ## Keep Listening.
            return $this->subscribe($args);
        }
        catch (Exception $error) {
            sleep(1);
            return $this->subscribe($args);
        }
    }

    /**
     * History
     *
     * Load history from a channel.
     *
     * @param array $args with 'channel' and 'limit'.
     * @return mixed false on fail, array on success.
     */
    public function history($args) {
        ## Capture User Input
        $limit   = +$args['limit'] ? +$args['limit'] : 10;
        $channel = $args['channel'];

        ## Fail if bad input.
        if (!$channel) {
            echo('Missing Channel');
            return false;
        }

        ## Get History
        return $this->_request(array(
            'history',
            $this->settings['sub-key'],
            $channel,
            '0',
            $limit
        ));
    }

    /**
     * Time
     *
     * Timestamp from PubNub Cloud.
     *
     * @return int timestamp.
     */
    private function time() {
        ## Get History
        $response = $this->_request(array(
            'time',
            '0'
        ));

        return $response[0];
    }

    /**
     * Request URL
     *
     * @param array $request of url directories.
     * @return array from JSON response.
     */
    private function _request($request) {
        $request = array_map( 'Pubnub::_encode', $request );
        array_unshift( $request, $this->settings['server'] );

        $ctx = stream_context_create(array(
            'http' => array( 'timeout' => 200 ) 
        ));

        return json_decode( @file_get_contents(
            implode( '/', $request ), 0, $ctx
        ), true );
    }

    /**
     * Encode
     *
     * @param string $part of url directories.
     * @return string encoded string.
     */
    private static function _encode($part) {
        return implode( '', array_map(
            'Pubnub::_encode_char', str_split($part)
        ));
    }

    /**
     * Encode Char
     *
     * @param string $char val.
     * @return string encoded char.
     */
    private static function _encode_char($char) {
        if (strpos( ' ~`!@#$%^&*()+=[]\\{}|;\':",./<>?', $char ) === false) {
            return $char;
        }
        
        return rawurlencode($char);
    }
}

/* End of file Pubnub.php */
/* Location: ./application/libraries/pubnub.php */
?>
