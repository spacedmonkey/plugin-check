<?php

namespace WordPress\Plugin_Check\Test_Data;

use WordPress\Plugin_Check\Checker\Check;
use WordPress\Plugin_Check\Checker\Check_Result;

class Empty_Check implements Check {
	public function run( Check_Result $check_result ) {
		return;
	}
}
