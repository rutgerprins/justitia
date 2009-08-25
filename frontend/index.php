<?php

require_once('./bootstrap.inc');
require_once('./template.inc');

class Page extends Template {
function title() {
	return "Welcome";
}
function write_body() {

$user = Authentication::require_user();
echo "Hello " . $user->name();


echo "Welcome to the Apollo programming assigment verification system";

$ignore= <<<EOF

<ul>
 <li>Home
 <li>User settings
 <li>Course X
   <ul>
     <li>Quick overview
     <li>Problem X
      <ul>
        <li>Submit
        <li>Submission X details
      </ul>
   </ul>
</ul>

Admin interface
<ul>
 <li>Config overview
 <li>Users
   <ul>
     <li>List
     <li>Find
     <li>Add user(s)
   </ul>
 <li>Courses
   <ul>
     <li>Course X
      <ul>
        <li>Rescan (when problem set has changed)
        <li>Users overview
        <li>Submissions overview
        <li>Problem X
         <ul>
           <li>Users overview
           <li>Submissions overview
            <ul>
              <li>Submission X details
            </ul>
         </ul>
      </ul>
   </ul>
</ul>


EOF;



// -----------------------------------------------------------------------------
// Directory listing
// -----------------------------------------------------------------------------

//require_once('template.inc');

function write_tree($e) {
	echo "<ul>";
	echo "<pre>"; print_r($e->attributes()); echo "</pre>";
	echo "<pre>"; print_r(Authentication::current_user()->submissions_to($e)); echo "</pre>";
	foreach($e->children() as $n => $d) {
		echo "<li><a href='index.php". $d->path() ."'>" . htmlspecialchars($d->attribute("title")) .  "</a>";
		echo $d->visible()    ? 'V+ ' : 'V- ' ;
		echo $d->active() ? 'A+ ' : 'A- ' ;
		//echo $d->submitable() ? 'S+ ' : 'S- ' ;
		write_tree($d);
		
		echo "</li>";
	}
	echo "</ul>";
}

//write_tree(Entity::get_root());
//write_tree(Entity::get(""));

new Testset(Entity::get("impprog0910/week1/welkom"));

echo "<hr>";

function write_nav($here) {
	echo "<ul>";
	foreach ($here->ancestors() as $e) {
		echo "<li>";
		echo '<a href="index.php' . $e->path() .'">' . htmlspecialchars($e->attribute("title")) . '</a>';
		echo "</li>";
	}
	echo "</ul>";
}

function write_children_nav($e, $here) {
	if (count($e->children()) == 0) return;
	echo "<li><ul>";
	foreach ($e->children() as $e) {
		if (!$e->visible()) continue;
		$class = '';
		if ($e->is_ancestor_of($here)) {
			$class .= 'ancestor ';
		}
		if ($e->attribute('submitable')) {
			$subm = Authentication::current_user()->last_submission_to($e);
			if (!$subm) $class .= 'no-submission ';
			else if ($subm->status == Submission::STATUS_FAILED)  $class .= 'failed ';
			else if ($subm->status == Submission::STATUS_PASSED)  $class .= 'passed ';
			else if ($subm->status == Submission::STATUS_PENDING) $class .= 'pending ';
		}
		if (!$e->active()) {
			$class .= 'inactive ';
		}
		
		echo "<li>";
		echo '<a href="index.php' . $e->path() .'"'
			. ($class ? ' class="'.$class.'"' : '')
			. '>'
			. htmlspecialchars($e->attribute("title")) . '</a>';
		echo "</li>";
	}
	echo "</ul></li>";
}
function write_nav2($here) {
	echo '<ul id="nav">';
	foreach ($here->ancestors() as $e) {
		write_children_nav($e,$here);
	}
	echo '</ul>';
}

$here = Entity::get(@$_SERVER['PATH_INFO']);
write_nav2($here);


function write_submit_form() {
	
	$suffix = @$_SERVER['PATH_INFO'];
	
?><form action="submit.php<?php echo $suffix; ?>" method="post" enctype="multipart/form-data">
  <label>Select file</label> <input type="file" name="file" id="file"><br>
  <input type="submit" name="submit" value="Submit" id="submit">
</form>
<script type="text/javascript">
<!--
  var file_control = document.getElementById('file');
  file_control.onchange = function() {
	var ok = file_control.value.match(/\.java$/);
	document.getElementById('submit').style.backgroundColor = ok ? 'white' : 'red';
  }
//-->
</script><?php

}

function write_submission($i, $subm) {
	$path = "download.php/" . $subm->submissionid . '/' . $subm->file_name;
	echo '<div style="float:left;font-size:400%;font-family:sans-serif;color:green;">'.$i.'</div>';
	echo "<table>";
	echo "<tr><td>Submitted on</td><td>" . format_date($subm->time) . "</td>";
	echo "<tr><td>Submitted by</td><td>" . User::names_html($subm->users()) . "</td>";
	echo '<tr><td>Files</td><td><a href="'.$path.'">Download submitted files</a></td>';
	echo "<tr><td>Status</td><td>"       . $subm->textual_status() . "</td>";
	echo "</table>";
}

if ($here->attribute('submitable')) {
	// submission form
	echo "<h2>Submit</h2>";
	write_submit_form();
	
	echo "<h2>Submissions</h2>";
	$submissions = Authentication::current_user()->submissions_to($here);
	if (empty($submissions)) {
		echo "<em>no submissions have been made for this assignment.</em>";
	} else {
		echo '<ul class="submissions">';
		$i = count($submissions);
		foreach($submissions as $subm) {
			echo '<li>';
			write_submission($i, $subm);
			$i--;
			echo '</li>';
		}
		echo '</ul>';
	}
	
}

// -----------------------------------------------------------------------------
// Authentication stuff
// -----------------------------------------------------------------------------

/*new Authentication();

$h = make_salted_password_hash("password");
echo $h;

echo "<br>",check_salted_password_hash("password","44d762454ab6ceebfcca586edc3342d7f9eb6c443yVtEjzEbs");

echo $u->check_password('password');
*/

// -----------------------------------------------------------------------------
// Submission stuff
// -----------------------------------------------------------------------------

//print_r($user->all_submissions());

}}new Page();
