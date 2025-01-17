<?php
/**
 * Class WordPress\Plugin_Check\Checker\Checks\PHP_CodeSniffer_Check
 *
 * @package plugin-check
 */

namespace WordPress\Plugin_Check\Checker\Checks;

use WordPress\Plugin_Check\Checker\Static_Check;
use WordPress\Plugin_Check\Checker\Check_Result;
use Exception;

/**
 * Check for running one or more PHP CodeSniffer sniffs.
 *
 * @since n.e.x.t
 */
abstract class Abstract_PHP_CodeSniffer_Check implements Static_Check {

	/**
	 * List of allowed PHPCS arguments.
	 *
	 * @since n.e.x.t
	 * @var array
	 */
	protected $allowed_args = array(
		'standard'   => true,
		'extensions' => true,
		'sniffs'     => true,
		'exclude'    => true,
	);

	/**
	 * Returns an associative array of arguments to pass to PHPCS.
	 *
	 * @since n.e.x.t
	 *
	 * @return array {
	 *    An associative array of PHPCS CLI arguments. Can include one or more of the following options.
	 *
	 *    @type string $standard   The name or path to the coding standard to check against.
	 *    @type string $extensions A comma separated list of file extensions to check against.
	 *    @type string $sniffs     A comma separated list of sniff codes to include from checks.
	 *    @type string $exclude    A comma separated list of sniff codes to exclude from checks.
	 * }
	 */
	abstract protected function get_args();

	/**
	 * Amends the given result by running the check on the associated plugin.
	 *
	 * @since n.e.x.t
	 *
	 * @param Check_Result $result The check result to amend, including the plugin context to check.
	 *
	 * @throws Exception Thrown when the check fails with a critical error (unrelated to any errors detected as part of
	 *                   the check).
	 */
	public function run( Check_Result $result ) {
		// Include the PHPCS autoloader.
		$autoloader = WP_PLUGIN_CHECK_PLUGIN_DIR_PATH . '/vendor/squizlabs/php_codesniffer/autoload.php';

		if ( file_exists( $autoloader ) ) {
			include_once $autoloader;
		}

		// Backup the original command line arguments.
		$orig_cmd_args = $_SERVER['argv'];

		// Create the default arguments for PHPCS.
		$defaults = array(
			'',
			$result->plugin()->path( '' ),
			'--report=Json',
			'--report-width=9999',
		);

		// Set the check arguments for PHPCS.
		$_SERVER['argv'] = $this->parse_argv( $this->get_args(), $defaults );

		// Run PHPCS.
		try {
			ob_start();
			$runner = new \PHP_CodeSniffer\Runner();
			$runner->runPHPCS();
			$reports = ob_get_clean();
		} catch ( Exception $e ) {
			$_SERVER['argv'] = $orig_cmd_args;
			throw $e;
		}

		// Parse the reports into data to add to the overall $result.
		$reports = json_decode( trim( $reports ), true );

		if ( empty( $reports['files'] ) ) {
			return;
		}

		foreach ( $reports['files'] as $file_name => $file_results ) {
			if ( empty( $file_results['messages'] ) ) {
				continue;
			}

			foreach ( $file_results['messages'] as $file_message ) {
				$result->add_message(
					strtoupper( $file_message['type'] ) === 'ERROR',
					$file_message['message'],
					array(
						'code'   => $file_message['source'],
						'file'   => $file_name,
						'line'   => $file_message['line'],
						'column' => $file_message['column'],
					)
				);
			}
		}
	}

	/**
	 * Parse the command arguments.
	 *
	 * @since n.e.x.t
	 *
	 * @param array $argv     An array of arguments to pass.
	 * @param array $defaults An array of default arguments.
	 * @return array An indexed array of PHPCS CLI arguments.
	 */
	private function parse_argv( $argv, $defaults ) {
		// Only accept allowed PHPCS arguments from check arguments array.
		$check_args = array_intersect_key( $argv, $this->allowed_args );

		// Format check arguments for PHPCS.
		foreach ( $check_args as $key => $value ) {
			$defaults[] = "--{$key}=$value";
		}

		return $defaults;
	}
}
