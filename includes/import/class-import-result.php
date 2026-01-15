<?php
/**
 * RationalSEO Import Result Class
 *
 * Data object for import operation results.
 *
 * @package RationalSEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Import result data object.
 */
class RationalSEO_Import_Result {

	/**
	 * Whether the operation was successful overall.
	 *
	 * @var bool
	 */
	private $success = true;

	/**
	 * Number of items successfully imported.
	 *
	 * @var int
	 */
	private $imported = 0;

	/**
	 * Number of items skipped (e.g., duplicates).
	 *
	 * @var int
	 */
	private $skipped = 0;

	/**
	 * Number of items that failed to import.
	 *
	 * @var int
	 */
	private $failed = 0;

	/**
	 * Human-readable message about the result.
	 *
	 * @var string
	 */
	private $message = '';

	/**
	 * Array of error messages.
	 *
	 * @var array
	 */
	private $errors = array();

	/**
	 * Array of preview/sample data.
	 *
	 * @var array
	 */
	private $preview_data = array();

	/**
	 * Additional data (e.g., imported items for display).
	 *
	 * @var array
	 */
	private $data = array();

	/**
	 * Constructor.
	 *
	 * @param bool   $success Whether the operation succeeded.
	 * @param string $message Human-readable message.
	 */
	public function __construct( $success = true, $message = '' ) {
		$this->success = $success;
		$this->message = $message;
	}

	/**
	 * Create a success result.
	 *
	 * @param string $message Success message.
	 * @return RationalSEO_Import_Result
	 */
	public static function success( $message = '' ) {
		return new self( true, $message );
	}

	/**
	 * Create an error result.
	 *
	 * @param string $message Error message.
	 * @return RationalSEO_Import_Result
	 */
	public static function error( $message = '' ) {
		return new self( false, $message );
	}

	/**
	 * Check if the operation was successful.
	 *
	 * @return bool
	 */
	public function is_success() {
		return $this->success;
	}

	/**
	 * Get the result message.
	 *
	 * @return string
	 */
	public function get_message() {
		return $this->message;
	}

	/**
	 * Set the result message.
	 *
	 * @param string $message Message to set.
	 * @return RationalSEO_Import_Result
	 */
	public function set_message( $message ) {
		$this->message = $message;
		return $this;
	}

	/**
	 * Get the imported count.
	 *
	 * @return int
	 */
	public function get_imported() {
		return $this->imported;
	}

	/**
	 * Set the imported count.
	 *
	 * @param int $count Count to set.
	 * @return RationalSEO_Import_Result
	 */
	public function set_imported( $count ) {
		$this->imported = absint( $count );
		return $this;
	}

	/**
	 * Increment the imported count.
	 *
	 * @param int $amount Amount to increment by.
	 * @return RationalSEO_Import_Result
	 */
	public function increment_imported( $amount = 1 ) {
		$this->imported += absint( $amount );
		return $this;
	}

	/**
	 * Get the skipped count.
	 *
	 * @return int
	 */
	public function get_skipped() {
		return $this->skipped;
	}

	/**
	 * Set the skipped count.
	 *
	 * @param int $count Count to set.
	 * @return RationalSEO_Import_Result
	 */
	public function set_skipped( $count ) {
		$this->skipped = absint( $count );
		return $this;
	}

	/**
	 * Increment the skipped count.
	 *
	 * @param int $amount Amount to increment by.
	 * @return RationalSEO_Import_Result
	 */
	public function increment_skipped( $amount = 1 ) {
		$this->skipped += absint( $amount );
		return $this;
	}

	/**
	 * Get the failed count.
	 *
	 * @return int
	 */
	public function get_failed() {
		return $this->failed;
	}

	/**
	 * Set the failed count.
	 *
	 * @param int $count Count to set.
	 * @return RationalSEO_Import_Result
	 */
	public function set_failed( $count ) {
		$this->failed = absint( $count );
		return $this;
	}

	/**
	 * Increment the failed count.
	 *
	 * @param int $amount Amount to increment by.
	 * @return RationalSEO_Import_Result
	 */
	public function increment_failed( $amount = 1 ) {
		$this->failed += absint( $amount );
		return $this;
	}

	/**
	 * Get the errors array.
	 *
	 * @return array
	 */
	public function get_errors() {
		return $this->errors;
	}

	/**
	 * Add an error message.
	 *
	 * @param string $error Error message to add.
	 * @return RationalSEO_Import_Result
	 */
	public function add_error( $error ) {
		$this->errors[] = $error;
		$this->success  = false;
		return $this;
	}

	/**
	 * Get the preview data.
	 *
	 * @return array
	 */
	public function get_preview_data() {
		return $this->preview_data;
	}

	/**
	 * Set the preview data.
	 *
	 * @param array $data Preview data to set.
	 * @return RationalSEO_Import_Result
	 */
	public function set_preview_data( $data ) {
		$this->preview_data = $data;
		return $this;
	}

	/**
	 * Get additional data.
	 *
	 * @return array
	 */
	public function get_data() {
		return $this->data;
	}

	/**
	 * Set additional data.
	 *
	 * @param array $data Data to set.
	 * @return RationalSEO_Import_Result
	 */
	public function set_data( $data ) {
		$this->data = $data;
		return $this;
	}

	/**
	 * Add data to the additional data array.
	 *
	 * @param string $key   Data key.
	 * @param mixed  $value Data value.
	 * @return RationalSEO_Import_Result
	 */
	public function add_data( $key, $value ) {
		$this->data[ $key ] = $value;
		return $this;
	}

	/**
	 * Get the total items processed.
	 *
	 * @return int
	 */
	public function get_total() {
		return $this->imported + $this->skipped + $this->failed;
	}

	/**
	 * Convert the result to an array for JSON responses.
	 *
	 * @return array
	 */
	public function to_array() {
		return array(
			'success'      => $this->success,
			'message'      => $this->message,
			'imported'     => $this->imported,
			'skipped'      => $this->skipped,
			'failed'       => $this->failed,
			'total'        => $this->get_total(),
			'errors'       => $this->errors,
			'preview_data' => $this->preview_data,
			'data'         => $this->data,
		);
	}
}
