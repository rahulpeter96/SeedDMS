<?php
/**
 * Implementation of WorkspaceMgr view
 *
 * @category   REPO
 * @package    MediaREPO
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */

/**
 * Include parent class
 */
require_once("class.Bootstrap.php");

/**
 * Class which outputs the html page for WorkspaceMgr view
 *
 * @category   REPO
 * @package    MediaREPO
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class mediarepo_View_WorkflowMgr extends mediarepo_Bootstrap_Style {

	function js() { /* {{{ */
		header('Content-Type: application/javascript; charset=UTF-8');
?>

function checkForm(num)
{
	msg = new Array();
	eval("var formObj = document.form" + num + ";");

	if (formObj.name.value == "") msg.push("<?php printMLText("js_no_name");?>");
	if (msg != "")
	{
  	noty({
  		text: msg.join('<br />'),
  		type: 'error',
      dismissQueue: true,
  		layout: 'topRight',
  		theme: 'defaultTheme',
			_timeout: 1500,
  	});
		return false;
	}
	else
		return true;
}

$(document).ready(function() {
	$('body').on('submit', '#form1', function(ev){
		if(checkForm()) return;
		ev.preventDefault();
	});
	$( "#selector" ).change(function() {
		$('div.ajax').trigger('update', {workflowid: $(this).val()});
	});
});
<?php
	} /* }}} */

	function info() { /* {{{ */
		$repo = $this->params['repo'];
		$user = $this->params['user'];
		$selworkflow = $this->params['selworkflow'];
		if($selworkflow) { ?>
<div id="workflowgraph">
<iframe src="out.WorkflowGraph.php?workflow=<?php echo $selworkflow->getID(); ?>" width="100%" height="661" style="border: 1px solid #e3e3e3; border-radius: 4px; margin: -1px;"></iframe>
</div>
<?php }
	} /* }}} */

	function showWorkflowForm($workflow) { /* {{{ */
		$repo = $this->params['repo'];
		$user = $this->params['user'];
		$workflows = $this->params['allworkflows'];
		$workflowstates = $this->params['allworkflowstates'];

		if($workflow) {
			$path = $workflow->checkForCycles();
			if($path) {
				$names = array();
				foreach($path as $state) {
					$names[] = $state->getName();
				}
				$this->errorMsg(getMLText('workflow_has_cycle').": ".implode(' <i class="icon-arrow-right"></i> ', $names));
			}

			$transitions = $workflow->getTransitions();
			$initstate = $workflow->getInitState();
			$hasinitstate = true;
			$hasreleased = true;
			$hasrejected = true;
			$missesug = false;
			if($transitions) {
				$hasinitstate = false;
				$hasreleased = false;
				$hasrejected = false;
				foreach($transitions as $transition) {
					$transusers = $transition->getUsers();
					$transgroups = $transition->getGroups();
					if(!$transusers && !$transgroups) {
						$missesug = true;
					}
					if($transition->getNextState()->getDocumentStatus() == S_RELEASED)
						$hasreleased = true;
					if($transition->getNextState()->getDocumentStatus() == S_REJECTED)
						$hasrejected = true;
					if($transition->getState()->getID() == $initstate->getID())
						$hasinitstate = true;
				}
			}
			if($missesug)
				$this->errorMsg(getMLText('workflow_transition_without_user_group'));
			if(!$hasinitstate)
				$this->errorMsg(getMLText('workflow_no_initial_state'));
			if(!$hasreleased)
				$this->errorMsg(getMLText('workflow_no_doc_released_state'));
			if(!$hasrejected)
				$this->errorMsg(getMLText('workflow_no_doc_rejected_state'));

			if($workflow->isUsed()) {
				$this->infoMsg(getMLText('workflow_in_use'));
			}
		}
?>
	<div class="well">
	<form class="form-horizontal" action="../op/op.WorkflowMgr.php" method="post" enctype="multipart/form-data">
<?php
	if($workflow) {
		echo createHiddenFieldWithKey('editworkflow');
?>
	<input type="hidden" name="workflowid" value="<?php print $workflow->getID();?>">
	<input type="hidden" name="action" value="editworkflow">
<?php
	} else {
		echo createHiddenFieldWithKey('addworkflow');
?>
		<input type="hidden" name="action" value="addworkflow">
<?php
	}
?>

<?php
		if($workflow && !$workflow->isUsed()) {
?>
		<div class="controls">
			  <a class="standardText btn" href="../out/out.RemoveWorkflow.php?workflowid=<?php print $workflow->getID();?>"><i class="icon-remove"></i> <?php printMLText("rm_workflow");?></a>
		</div>
<?php
		}
?>
		<div class="control-group">
			<label class="control-label"><?php printMLText("workflow_name");?>:</label>
			<div class="controls">
				<input type="text" name="name" value="<?php print ($workflow ? htmlspecialchars($workflow->getName()) : "");?>">
			</div>
		</div>
		<div class="control-group">
			<label class="control-label"><?php printMLText("workflow_initstate");?>:</label>
			<div class="controls">
				<select name="initstate">
<?php
			foreach($workflowstates as $workflowstate) {
				echo "<option value=\"".$workflowstate->getID()."\"";
				if($workflow && $workflow->getInitState()->getID() == $workflowstate->getID())
					echo " selected=\"selected\"";
				echo ">".htmlspecialchars($workflowstate->getName())."</option>\n";
			}
?>
			</select>
			</div>
		</div>

		<div class="controls">
			<button type="submit" class="btn"><i class="icon-save"></i> <?php printMLText("save")?></button>
		</div>

	</form>
	</div>
<?php
		if($workflow) {
		$actions = $repo->getAllWorkflowActions();
		if($actions) {
		$transitions = $workflow->getTransitions();
		echo "<table class=\"table table-condensed\"><thead>";
		echo "<tr><th>".getMLText('state_and_next_state')."</th><th>".getMLText('action')."</th><th>".getMLText('users_and_groups')."</th><th></th></tr></thead><tbody>";
		if($transitions) {
			foreach($transitions as $transition) {
				$state = $transition->getState();
				$nextstate = $transition->getNextState();
				$action = $transition->getAction();
				$transusers = $transition->getUsers();
				$transgroups = $transition->getGroups();
				echo "<tr";
				if(!$transusers && !$transgroups) {
					echo " class=\"error\"";
				}
				echo "><td>".'<i class="icon-circle'.($workflow->getInitState()->getId() == $state->getId() ? ' initstate' : ' in-workflow').'"></i> '.$state->getName()."<br />";
				$docstatus = $nextstate->getDocumentStatus();
				echo '<i class="icon-circle'.($docstatus == S_RELEASED ? ' released' : ($docstatus == S_REJECTED ? ' rejected' : ' in-workflow')).'"></i> '.$nextstate->getName();
				if($docstatus == S_RELEASED || $docstatus == S_REJECTED) {
					echo "<br /><i class=\"icon-arrow-right\"></i> ".getOverallStatusText($docstatus);
				}
				echo "</td>";
				echo "<td><i class=\"icon-sign-blank workflow-action\"></i> ".$action->getName()."</td>";
				echo "<td>";
				foreach($transusers as $transuser) {
					$u = $transuser->getUser();
					echo getMLText('user').": ".$u->getFullName();
					echo "<br />";
				}
				foreach($transgroups as $transgroup) {
					$g = $transgroup->getGroup();
					echo getMLText('at_least_n_users_of_group',
						array("number_of_users" => $transgroup->getNumOfUsers(),
							"group" => $g->getName()));
					echo "<br />";
				}
				echo "</td>";
				echo "<td>";
?>
<form class="form-inline" action="../op/op.RemoveTransitionFromWorkflow.php" method="post">
  <?php echo createHiddenFieldWithKey('removetransitionfromworkflow'); ?>
	<input type="hidden" name="workflow" value="<?php print $workflow->getID();?>">
	<input type="hidden" name="transition" value="<?php print $transition->getID(); ?>">
	<button type="submit" class="btn"><i class="icon-remove"></i> <?php printMLText("delete");?></button>
</form>
<?php
				echo "</td>";
				echo "</tr>\n";
			}
		}
		echo "</tbody></table>";
?>
<form class="form-inline" action="../op/op.AddTransitionToWorkflow.php" method="post">
<?php
		echo "<table class=\"table table-condensed\"><thead></thead><tbody>";
			echo "<tr>";
			echo "<td>";
			echo "<select name=\"state\">";
			$states = $repo->getAllWorkflowStates();
			foreach($states as $state) {
				echo "<option value=\"".$state->getID()."\">".$state->getName()."</option>";
			}
			echo "</select><br />";
			echo "<select name=\"nextstate\">";
			$states = $repo->getAllWorkflowStates();
			foreach($states as $state) {
				echo "<option value=\"".$state->getID()."\">".$state->getName()."</option>";
			}
			echo "</select>";
			echo "</td>";
			echo "<td>";
			echo "<select name=\"action\">";
			foreach($actions as $action) {
				echo "<option value=\"".$action->getID()."\">".$action->getName()."</option>";
			}
			echo "</select>";
			echo "</td>";
			echo "<td>";
      echo "<select class=\"chzn-select\" name=\"users[]\" multiple=\"multiple\" data-placeholder=\"".getMLText('select_users')."\" data-no_results_text=\"".getMLText('unknown_user')."\">";
			$allusers = $repo->getAllUsers();
			foreach($allusers as $usr) {
				print "<option value=\"".$usr->getID()."\">". htmlspecialchars($usr->getLogin()." - ".$usr->getFullName())."</option>";
			}
			echo "</select>";
			echo "<br />";
      echo "<select class=\"chzn-select\" name=\"groups[]\" multiple=\"multiple\" data-placeholder=\"".getMLText('select_groups')."\" data-no_results_text=\"".getMLText('unknown_group')."\">";
			$allgroups = $repo->getAllGroups();
			foreach($allgroups as $grp) {
				print "<option value=\"".$grp->getID()."\">". htmlspecialchars($grp->getName())."</option>";
			}
			echo "</select>";
			echo "</td>";
			echo "<td>";
?>
  <?php echo createHiddenFieldWithKey('addtransitiontoworkflow'); ?>
	<input type="hidden" name="workflow" value="<?php print $workflow->getID();?>">
	<input type="submit" class="btn" value="<?php printMLText("add");?>">
<?php
			echo "</td>";
			echo "</tr>\n";
		echo "</tbody></table>";
?>
</form>
<?php
		}
		}
	} /* }}} */

	function form() { /* {{{ */
		$selworkflow = $this->params['selworkflow'];

		$this->showWorkflowForm($selworkflow);
	} /* }}} */

	function show() { /* {{{ */
		$repo = $this->params['repo'];
		$user = $this->params['user'];
		$selworkflow = $this->params['selworkflow'];
		$workflows = $this->params['allworkflows'];
		$workflowstates = $this->params['allworkflowstates'];

		$this->htmlStartPage(getMLText("admin_tools"));
		$this->globalNavigation();
		$this->contentStart();
		$this->pageNavigation(getMLText("admin_tools"), "admin_tools");
		$this->contentHeading(getMLText("workflow_management"));
?>

<div class="row-fluid">
<div class="span5">
<div class="well">
<form class="form-horizontal">
	<div class="control-group">
		<label class="control-label" for="login"><?php printMLText("selection");?>:</label>
		<div class="controls">
<select id="selector" class="span9">
<option value="-1"><?php echo getMLText("choose_workflow")?>
<option value="0"><?php echo getMLText("add_workflow")?>
<?php
		foreach ($workflows as $currWorkflow) {
			print "<option value=\"".$currWorkflow->getID()."\" ".($selworkflow && $currWorkflow->getID()==$selworkflow->getID() ? 'selected' : '').">" . htmlspecialchars($currWorkflow->getName());
		}
?>
</select>
		</div>
	</div>
</form>
</div>
<div class="ajax" data-view="WorkflowMgr" data-action="info" <?php echo ($selworkflow ? "data-query=\"workflowid=".$selworkflow->getID()."\"" : "") ?>></div>
</div>

<div class="span7">
		<div class="ajax" data-view="WorkflowMgr" data-action="form" <?php echo ($selworkflow ? "data-query=\"workflowid=".$selworkflow->getID()."\"" : "") ?>></div>
</div>
</div>

<?php
		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>