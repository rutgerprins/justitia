<?php

require_once('../lib/bootstrap.inc');

// -----------------------------------------------------------------------------
// Results overview table
// -----------------------------------------------------------------------------

function layered_descendants($entity) {
	// returns array(array())
	$layers = new LayerTree();
	$layers->add_leaf('User');
	layered_descendants_rec($entity, $layers);
	return $layers;
}
function layered_descendants_rec($entity, &$layers) {
	$layers->parent_begin($entity);
	if ($entity->submitable()) {
		$layers->add_leaf($entity);
	}
	foreach ($entity->children() as $child) {
		layered_descendants_rec($child, $layers);
	}
	$layers->parent_end($entity);
}

function print_layers_header($layers) {
	foreach ($layers->get() as $i=>$layer) {
		echo "<tr>";
		foreach ($layer as $it) {
			if (!is_object($it)) continue;
			
			if ($it->value == 'User') {
				$extra = ' class="user"';
				$value = 'User';
			} else if ($it->type == LayerItem::LEAF) {
				$extra = ' class="submitable-parent"';
				$value = '';
			} else {
				$extra = '';
				$value = "<a href=\"admin_results.php" . htmlspecialchars($it->value->path()) ."\">"
				       . htmlspecialchars($it->value->title()) . "</a>";
			}
			echo "<th colspan=\"$it->colspan\" rowspan=\"$it->rowspan\"$extra>$value</th>";
		}
		echo "</tr>";
	}
}


class Timer {
	private static $t = false;
	private static $times = array();
	static function after($name) {
		$t_end = microtime(true);
		if (Timer::$t) {
			Timer::$times[$name] = $t_end - Timer::$t;
		}
		Timer::$t = $t_end;
	}
	static function write() {
		echo "<pre>";
		print_r(Timer::$times);
		echo "</pre>";
	}
}
Timer::after("");


class View extends PageWithEntity {
	
	function __construct() {
		Authentication::require_admin();
		$this->is_admin_page = true;
		// find active entity
		parent::__construct();
		// debugging
		$this->debug = false;
	}
	
	function title() {
		return "Results for " . parent::title();
	}
	
	function nav_script($entity) {
		return 'admin_results.php';
	}
	
	function write_body() {
		function is_submitable($e) {
			return $e->submitable();
		}
		if ($this->entity->allow_view_results() || isset($_REQUEST['force'])) {
			Timer::after("init");
			$entities = array_filter($this->entity->descendants(),'is_submitable');
			Timer::after("find entities");
			$this->write_get_submission_results($entities);
			if ($this->debug) Timer::write();
		} else {
			// top level results are slow, and often not intended
			//echo "<em>You are probably looking for the results of one of the courses</em>";
			echo "<a href=\"admin_results.php".$this->entity->path()."?force\">view results for all courses</a>";
			foreach ($this->entity->children() as $e) {
				$this->write_block_begin($e->title(), 'collapsed linky block', 'admin_results.php' . $e->path());
				$this->write_block_end();
			}
		}
	}
	
	
	function write_get_submission_results($entities) {
		// statistics
		$num_submissions = 0;
		$num_passed = 0;
		$num_failed = 0;
		// find submissions
		$users = array();
		foreach($entities as $e => $entity) {
			// *all* submissions
			$all_subms = $entity->all_submissions();
			foreach ($all_subms as $subm) {
				$num_submissions++;
				if (Status::is_passed($subm->status)) $num_passed++;
				if (Status::is_failed($subm->status)) $num_failed++;
			}
			// for each userid => subm
			$subms = $entity->all_final_submissions_from($all_subms);
			foreach ($subms as $userid => $subm) {
				if (!isset($users[$userid])) {
					$users[$userid]['user'] = User::by_id($userid);
				}
				$users[$userid]['subms'][$e] = $subm;
			}
		}
		Timer::after("find submissions");
		// sort users by name
		$users_sorted = array();
		foreach($users as $user) {
			$users_sorted[$user['user']->name_for_sort()] = $user;
		}
		ksort($users_sorted);
		Timer::after("sort");
		// write statistics
		echo "<table>\n";
		echo "<tr><th>Number of submissions</th><td>$num_submissions</td>";
		echo "<tr><th>Number passed</th><td>$num_passed</td>";
		echo "<tr><th>Number failed</th><td>$num_failed</td>";
		echo "</table>\n";
		// write table
		$this->write_submission_results($entities,$users_sorted);
		Timer::after("write");
	}
	
	// write a table, given
	//  mapping userid -> array(submission,submission,..)
	function write_submission_results($entities,$users) {
		echo "<table class=\"results\">\n";
		// heading
		echo "<thead>";
		/*
		echo "<tr><th>User</th>";
		foreach($entities as $entity) {
			echo "<th><a href=\"admin_results.php" . htmlspecialchars($entity->path()) ."\">"
			   . htmlspecialchars($entity->title()) . "</a></th>";
		}
		echo "</tr>\n";
		*/
		$entity_layers = layered_descendants($this->entity);
		print_layers_header($entity_layers);
		echo "</thead><tbody>\n";
		// user results
		foreach ($users as $userinfo) {
			$subms = $userinfo['subms'];
			$user  = $userinfo['user'];
			echo "<tr>";
			// username
			echo '<td><a href="admin_user.php?edit='.urlencode($user->userid).'">' . htmlspecialchars($user->name_and_login()) . '</a></td>';
			// submissions
			foreach($entities as $e => $entity) {
				$subm = isset($subms[$e]) ? $subms[$e] : false;
				$status = Status::to_status($subm);
				echo '<td class="'.Status::to_css_class($status).'">';
				if ($subm !== false) echo '<a href="admin_user_submission.php' . htmlspecialchars($entity->path()) . '?userid='.$user->userid.'">';
				echo Status::to_short_text($status);
				if ($subm !== false) echo '</a>';
				echo '</td>';
			}
			echo "</tr>\n";
		}
		echo "</tbody></table>\n";
	}
	
}

$view = new View();
$view->write();
