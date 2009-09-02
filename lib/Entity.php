<?php

require_once('../config/config.php');

// -----------------------------------------------------------------------------
// Entities
//  An entity is either a problem or a directory of entities
// -----------------------------------------------------------------------------

class Entity {
	private $_parent;     // parent entity
	private $_dir_name;   // dir name (relative to parent)
	private $_path;       // full path (relative to data directory), ends in '/'
	private $_attributes; // key=>value pairs specified in "dir.conf" or NULL if not initialized
	private $_children;   // children array or NULL if not initialized
	private $_testcases; // array of testcases, initialized when $_children is
	
	// ---------------------------------------------------------------------
	// Construction: singleton based
	// ---------------------------------------------------------------------
	
	// the root entity
	private static $root;
	static function get_root() {
		if (!isset($root)) Entity::$root = new Entity(NULL,'');
		return Entity::$root;
	}
	static function clear_cache() {
		Entity::$root = NULL;
	}
	
	// singleton constructor for Entities
	static function get($path, $require_visible = false) {
		$parts = explode('/',$path);
		$here  = Entity::get_root();
		foreach ($parts as $part) {
			if ($part == '') continue;
			$here = $here->get_child($part);
			if ($here === NULL) {
				throw new Exception("Entity not found: $path");
			}
			if (!$here->visible() && $require_visible) {
				throw new Exception("Entity not found: $path");
			}
		}
		return $here;
	}
	
	private function __construct($parent, $dir_name) {
		$parent_path = isset($parent) ? $parent->_path : '';
		$this->_parent   = $parent;
		$this->_dir_name = $dir_name;
		$this->_path     = $parent_path . $dir_name . '/';
	}
	
	// ---------------------------------------------------------------------
	// Specific attributes / properties
	// ---------------------------------------------------------------------
	
	// is this the root?
	function is_root() {
		return $this->_parent === NULL;
	}
	function is_ancestor_of($that) {
		return substr($that->_path,0,strlen($this->_path)) == $this->_path;
	}
	
	// get full path
	function path() {
		return $this->_path;
	}
	
	function parent() {
		return $this->_parent;
	}
	// ancestors of this entity, the root first
	function ancestors() {
		$ancestors = array();
		for ($here = $this ; $here !== NULL ; $here = $here->_parent) {
			$ancestors []= $here;
		}
		return array_reverse($ancestors);
	}
	
	
	// get the visible date range
	function visible_range() {
		return new DateRange(
			$this->attribute("show date"),
			$this->attribute("hide date")
		);
	}
	// get the active date range
	function active_range() {
		return new DateRange(
			$this->attribute("start date"),
			$this->attribute("end date")
		);
	}
	// Is this entity visible?
	function visible() {
		return $this->attribute_bool("visible")
		    && $this->visible_range()->contains_now();
	}
	function active() {
		return $this->active_range()->contains_now();
	}
	
	function submitable() {
		return $this->attribute_bool('submitable');
	}
	
	function title() {
		return $this->attribute("title");
	}
	
	function compile() {
		return $this->attribute_bool('compile');
	}
	function compiler() {
		return $this->attribute('compiler');
	}
	function runner() {
		return $this->attribute('runner');
	}
	function checker() {
		return $this->attribute('checker');
	}
	
	function compile_limits() {
		return array(
			'time limit' => intval($this->attribute('compile time limit'))
		);
	}
	function run_limits() {
		return array(
			'time limit'     => intval($this->attribute('time limit')),
			'memory limit'   => intval($this->attribute('memory limit')),
			'filesize limit' => intval($this->attribute('filesize limit')),
		);
	}
	function filesize_limit() {
		return intval($this->attribute('filesize limit'));
	}
	
	function show_compile_errors() {
		return $this->attribute_bool('show compile errors');
	}
	function show_runtime_errors_for($case) {
		return Entity::is_allowed_testcase($case,$this->attribute('show run errors'));
	}
	function show_input_output_for($case) {
		return Entity::is_allowed_testcase($case,$this->attribute('show input/output'));
	}
	private static function is_allowed_testcase($case, $pattern) {
		if ($pattern == 'all')  return true;
		if ($pattern == 'none') return false;
		if (in_array($case,explode(' ',$pattern))) return true;
		return false;
	}
	
	// ---------------------------------------------------------------------
	// Children
	// ---------------------------------------------------------------------
	
	// gives an array of child entities
	function children() {
		$this->load_children();
		return $this->_children;
	}
	// gets a single child entity, if it exists (null otherwise)
	function get_child($name) {
		$this->load_children(); // a bit overkill
		return isset($this->_children[$name]) ? $this->_children[$name] : NULL;
	}
	
	// this, children, children of children, etc.
	function descendants() {
		$out = array();
		$this->get_descendants($out);
		return $out;
	}
	private function get_descendants(&$out) {
		$this->load_children();
		$out []= $this;
		foreach ($this->_children as $child) {
			$child->get_descendants($out);
		}
	}
	
	// load the directory listing of children
	private function load_children() {
		if (isset($this->_children)) return;
		$this->_children = array();
		$this->_testcases = array();
		foreach (new DirectoryIterator($this->data_path()) as $child) {
			$filename = $child->getFilename();
			if ($child->isDot() ||  $filename[0] == '.') {
				// skip hidden files and ..
			} else if ($child->isDir()) {
				// subdirectory = child entity
				$this->_children[$filename] = new Entity($this, $filename);
			} else if (substr($filename,-3) == '.in') {
				// ".in" file = testcase
				$this->_testcases []= substr($filename,0,-3);
			}
		}
		sort($this->_testcases);
		ksort($this->_children);
	}
	
	// ---------------------------------------------------------------------
	// Testcases
	// ---------------------------------------------------------------------
	
	function has_testcases() {
		return count($this->testcases()) > 0;
	}
	
	function testcases() {
		$this->load_children();
		return $this->_testcases;
	}
	
	function testcase_input($case) {
		return $this->data_path() . "$case.in";
	}
	function testcase_reference_output($case) {
		$path = $this->data_path() . "$case.out";
		if (file_exists($path)) return $path;
		$path = $this->data_path() . ".generated/$case.out";
		return $path;
	}
	function testcase_reference_output_exists() {
		foreach($this->testcases() as $case) {
			if (!file_exists($this->testcase_reference_output($case))) {
				return false;
			}
		}
		return true;
	}
	
	function reference_implementation($warn = true) {
		// from attribute
		$impl = $this->attribute("reference implementation");
		if ($impl != '') return $impl;
		// from compilable files
		$code_files = array();
		foreach (new DirectoryIterator($this->data_path()) as $child) {
			$filename = $child->getFilename();
			if ($filename{0} == '.') continue;
			// for convenience, pick the first program source file as the reference impl, if none is set
			if (Util::is_code($filename)) {
				$code_files []= $filename;
			}
		}
		// found any?
		if (count($code_files) == 1) {
			return $code_files[0];
		} else {
			if ($warn) {
				if (count($code_files) == 0) {
					echo "No reference implementation found.\n";
				} else {
					echo "Multiple source files found, not sure which one to use.\n";
				}
			}
			return false;
		}
	}
	
	// ---------------------------------------------------------------------
	// Submissions
	// ---------------------------------------------------------------------
	
	// Is submission A more (or equally) interesting than B?
	function is_more_interesting_submission($subm_a, $subm_b) {
		if ($subm_b === false || $subm_b === null) return true;
		// keep the last/best one
		if ($this->attribute_bool('keep best')) {
			$status_a = Status::base_status_group($subm_a->status);
			$status_b = Status::base_status_group($subm_b->status);
			if ($status_a > $status_b) return true;
			if ($status_a < $status_b) return false;
		}
		return $subm_a->time >= $subm_b->time;
	}
	
	// All submissions, oldest one FIRST
	function all_submissions($min_status = 0) {
		if (!$this->submitable()) return array();
		static $query;
		DB::prepare_query($query,
			"SELECT * FROM `submission`".
			" WHERE `entity_path` = ? AND `status` >= ?".
			" ORDER BY `time` ASC"
		);
		$query->execute(array($this->path(), $min_status));
		return Submission::fetch_all($query);
	}
	
	// All last/best submissions for each user
	//  returns an array (userid => submission)
	function all_final_submissions($min_status = 0) {
		return $this->all_final_submissions_from( $this->all_submissions($min_status) );
	}
	function all_final_submissions_from($subms) {
		$result = array();
		foreach ($subms as $subm) {
			$userids = $subm->userids();
			foreach($userids as $userid) {
				$use = $this->is_more_interesting_submission($subm, @$result[$userid]);
				// is this it?
				if ($use) $result[$userid] = $subm;
			}
		}
		return $result;
	}
	
	// Are there pending submissions?
	function count_pending_submissions() {
		if (!$this->submitable()) return 0;
		static $query;
		DB::prepare_query($query,
			"SELECT COUNT(*) FROM `submission`".
			" WHERE `entity_path`=? AND `status` = ".Status::PENDING
		);
		$query->execute(array($this->path()));
		return $query->fetchColumn();
	}
	
	// ---------------------------------------------------------------------
	// Attributes
	// ---------------------------------------------------------------------
	
	// gets an array with all attributes
	// does not inherit all from parents!
	// can contain NULLs
	function attributes() {
		$this->load_attributes();
		return $this->_attributes;
	}
	
	// gets a specific attribute
	function attribute($key) {
		$this->load_attributes();
		if (!array_key_exists($key, $this->_attributes)) {
			if (isset($this->_parent)) {
				// if it is not set, look in the parent
				// note: key not set != null value
				//       the latter indicates the parent doesn't have the attribute either
				// "child key" overrides "key"
				$attr = $this->_parent->attribute("child $key");
				if ($attr === NULL) {
					$attr = $this->_parent->attribute($key);
				}
			} else {
				$attr = NULL;
			}
			$this->_attributes[$key] = $attr;
		} else {
			$attr = $this->_attributes[$key];
		}
		if (is_null($attr)) {
			global $attribute_defaults;
			return isset($attribute_defaults[$key]) ? $attribute_defaults[$key] : NULL;
		} else {
			return $attr;
		}
	}
	function attribute_bool($key) {
		$attr = $this->attribute($key);
		return intval($attr) != 0 || $attr == "true" || $attr == "yes";
	}
	
	// load the attributes from a file
	private function load_attributes() {
		if (isset($this->_attributes)) return;
		// default attributes
		$this->_attributes = array(
			'title' => $this->_dir_name
		);
		// load info file
		parse_attribute_file($this->_attributes, $this->data_path() . "info");
	}
	
	function data_path() {
		return COURSE_DIR . $this->_path;
	}
	
};

function parse_attribute_file(&$attributes, $filename) {
	$lines = @file($filename, FILE_IGNORE_NEW_LINES);
	if (!isset($lines) || $lines == false) {
		// No info file, not an error
		return;
	}
	// parse it
	foreach($lines as $i => $line) {
		$line = trim($line, " \r\n\0"); // keep tabs
		if ($line == '' || $line{0} == '#') {
			// comment, ignore
			unset($key);
		} else if ($line{0} == "\t") {
			// continuation of previous line
			if (!isset($key)) {
				throw new Exception("Error on line ".($i+1)." in info '$filename':\n$line");
			}
			$attributes[$key] .= ($key_first ? '' : "\n") .  substr($line,1);
			$key_first = false;
		} else {
			$kv = explode(':',$line,2);
			if (count($kv) < 2) {
				throw new Exception("Error on line ".($i+1)." in info '$filename':\n$line");
			}
			$key   = trim($kv[0]);
			$value = trim($kv[1]);
			$attributes[$key] = $value;
			$key_first = $value == '';
		}
	}
	return $attributes;
}
