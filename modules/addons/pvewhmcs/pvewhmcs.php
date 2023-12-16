<?php
/* Proxmox VE for WHMCS
 * Copyright (c) 2016 Cybercoder
 * Copyright (c) 2023 The Network Crew
 * Copyright (c) 2023 NodeSpace Technologies, LLC
 * @license: GPLv3
 * Changes:
 *  - 2023-08-06: Fixed languages and added Proxmox VE 8 CPU options
*/

// FILE: /modules/addons/pvewhmcs/pvewhmcs.php
// TASK: Handles the Admin and Client Area GUIs etc
// NEED: The PHP API Class to interact w/ Proxmox VE API
// REPO: GitHub.com/The-Network-Crew/Proxmox-VE-for-WHMCS

// Pull in the WHMCS database handler Capsule for SQL
use Illuminate\Database\Capsule\Manager as Capsule;

// Define where the module operates in the Admin GUI
define( 'pvewhmcs_BASEURL', 'addonmodules.php?module=pvewhmcs' );

// Require the PHP API Class to interact with Proxmox VE
require_once('proxmox.php');

// CONFIG: Declare key options to the WHMCS Addon Module framework.
function pvewhmcs_config() {
	$configarray = array(
		"name" => "Proxmox VE for WHMCS",
		"description" => "Proxmox VE (Virtual Environment) & WHMCS, integrated & open-source! Provisioning & Management of VMs/CTs.".is_pvewhmcs_outdated(),
		"version" => "1.2.2",
		"author" => "The Network Crew Pty Ltd",
		'language' => 'English'
	);
	return $configarray;
}

// VERSION: also stored in repo/version (for update-available checker)
function pvewhmcs_version(){
    return "1.2.2";
}

// WHMCS MODULE: ACTIVATION of the ADDON MODULE
// This consists of importing the SQL structure, and then crudely returning yay or nay (needs improving)
function pvewhmcs_activate() {
	// Pull in the SQL structure (includes VNC/etc tweaks)
	$sql = file_get_contents(ROOTDIR.'/modules/addons/pvewhmcs/db.sql');
	if (!$sql) {
		return array('status'=>'error','description'=>'The db.sql file not found.');
	}
	// SQL file is good, let's proceed with pulling it in
	$err=false;
	$i=0;
	$query_array=explode(';',$sql) ;
	$query_count=count($query_array) ;
	// Iterate through the SQL commands to finalise init.
	foreach ( $query_array as $query) {
		if ($i<$query_count-1)
			if (!Capsule::statement($query.';'))
		$err=true;
		$i++ ;
	}
	// Return success or error.
	if (!$err)
		return array('status'=>'success','description'=>'Proxmox VE for WHMCS was installed successfuly.');

	return array('status'=>'error','description'=>'Proxmox VE for WHMCS was not activated properly.');

}

// WHMCS MODULE: DEACTIVATION
function pvewhmcs_deactivate() {
	// Drop all module-related tables
	Capsule::statement('drop table mod_pvewhmcs_ip_addresses,mod_pvewhmcs_ip_pools,mod_pvewhmcs_plans,mod_pvewhmcs_vms,mod_pvewhmcs');
	// Return the assumed result (change?)
	return array('status'=>'success','description'=>'Proxmox VE for WHMCS successfuly deactivated and all related tables deleted.');
}

// UPDATE CHECKER: live vs repo
function is_pvewhmcs_outdated(){
    if(get_pvewhmcs_latest_version() > pvewhmcs_version()){
        return "<br><span style='float:right;'><b>Proxmox VE for WHMCS is outdated: <a style='color:red' href='https://github.com/The-Network-Crew/Proxmox-VE-for-WHMCS/releases'>Download the new version!</a></span>";
    }
}

// UPDATE CHECKER: return latest ver
function get_pvewhmcs_latest_version(){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://raw.githubusercontent.com/The-Network-Crew/Proxmox-VE-for-WHMCS/master/version");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close ($ch);

    return str_replace("\n", "", $result);
}

// ADMIN MODULE GUI: output (HTML etc)
function pvewhmcs_output($vars) {
	$modulelink = $vars['modulelink'];

	// Check for update and report if available
	if (!empty(is_pvewhmcs_outdated())) {
		$_SESSION['pvewhmcs']['infomsg']['title']='Proxmox VE for WHMCS: New version available!' ;
		$_SESSION['pvewhmcs']['infomsg']['message']='Please visit the GitHub repository > Releases page. https://github.com/The-Network-Crew/Proxmox-VE-for-WHMCS/releases' ;
	}
		
	// Print Messages to GUI before anything else
	if (isset($_SESSION['pvewhmcs']['infomsg'])) {
		echo '
		<div class="infobox">
		<strong>
		<span class="title">'.$_SESSION['pvewhmcs']['infomsg']['title'].'</span>
		</strong><br/>
		'.$_SESSION['pvewhmcs']['infomsg']['message'].'
		</div>
		' ;
		unset($_SESSION['pvewhmcs']) ;
	}

	echo '
	<div id="clienttabs">
	<ul class="nav nav-tabs admin-tabs">
	<li class="'.($_GET['tab']=="vmplans" ? "active" : "").'"><a id="tabLink1" data-toggle="tab" role="tab" href="#plans">VM Plans</a></li>
	<li class="'.($_GET['tab']=="ippools" ? "active" : "").'"><a id="tabLink2" data-toggle="tab" role="tab" href="#ippools">IP Pools</a></li>
	<li class="'.($_GET['tab']=="nodes" ? "active" : "").'"><a id="tabLink3" data-toggle="tab" role="tab" href="#nodes">Nodes / Cluster</a></li>
	<li class="'.($_GET['tab']=="actions" ? "active" : "").'"><a id="tabLink4" data-toggle="tab" role="tab" href="#actions">Actions / Logs</a></li>
	<li class="'.($_GET['tab']=="health" ? "active" : "").'"><a id="tabLink5" data-toggle="tab" role="tab" href="#health">Support / Health</a></li>
	<li class="'.($_GET['tab']=="config" ? "active" : "").'"><a id="tabLink6" data-toggle="tab" role="tab" href="#config">Module Config</a></li>
	</ul>
	</div>
	<div class="tab-content admin-tabs">
	' ;


	if (isset($_POST['addnewkvmplan']))
	{
		save_kvm_plan() ;
	}

	if (isset($_POST['updatekvmplan']))
	{
		update_kvm_plan() ;
	}
	if (isset($_POST['updatelxcplan']))
	{
		update_lxc_plan() ;
	}

	if (isset($_POST['addnewlxcplan']))
	{
		save_lxc_plan() ;
	}

	echo '
	<div id="plans" class="tab-pane '.($_GET['tab']=="vmplans" ? "active" : "").'">
	<div class="btn-group" role="group" aria-label="...">
	<a class="btn btn-default" href="'. pvewhmcs_BASEURL .'&amp;tab=vmplans&amp;action=planlist">
	<i class="fa fa-list"></i>&nbsp; List: VM Plans
	</a>
	<a class="btn btn-default" href="'. pvewhmcs_BASEURL .'&amp;tab=vmplans&amp;action=add_kvm_plan">
	<i class="fa fa-plus-square"></i>&nbsp; Add: KVM Plan
	</a>
	<a class="btn btn-default" href="'. pvewhmcs_BASEURL .'&amp;tab=vmplans&amp;action=add_lxc_plan">
	<i class="fa fa-plus-square"></i>&nbsp; Add: LXC Plan
	</a>
	</div>
	';
	if ($_GET['action']=='add_kvm_plan') {
		kvm_plan_add() ;
	}

	if ($_GET['action']=='editplan') {
		if ($_GET['vmtype']=='kvm')
			kvm_plan_edit($_GET['id']) ;
		else
			lxc_plan_edit($_GET['id']) ;
	}

	if($_GET['action']=='removeplan') {
		remove_plan($_GET['id']) ;
	}


	if ($_GET['action']=='add_lxc_plan') {
		lxc_plan_add() ;
	}

	if ($_GET['action']=='planlist') {
		echo '

		<table class="datatable" border="0" cellpadding="3" cellspacing="1" width="100%">
		<tbody>
		<tr>
		<th>
		ID
		</th>
		<th>
		Title
		</th>
		<th>
		VM Type
		</th>
		<th>
		OS Type
		</th>
		<th>
		CPUs
		</th>
		<th>
		Cores
		</th>
		<th>
		RAM
		</th>
		<th>
		Swap
		</th>
		<th>
		Disk
		</th>
		<th>
		Disk Type
		</th>
		<th>
		PVE Store
		</th>
		<th>
		I/O Cap
		</th>
		<th>
		Net Mode
		</th>
		<th>
		Bridge
		</th>
		<th>
		NIC Model
		</th>
		<th>
		VLAN ID
		</th>
		<th>
		Rate
		</th>
		<th>
		BW
		</th>
		<th>
		Actions
		</th>
		</tr>
		';
		foreach (Capsule::table('mod_pvewhmcs_plans')->get() as $vm) {
			echo '<tr>';
			echo '<td>'.$vm->id . PHP_EOL .'</td>';
			echo '<td>'.$vm->title . PHP_EOL .'</td>';
			echo '<td>'.$vm->vmtype . PHP_EOL .'</td>';
			echo '<td>'.$vm->ostype . PHP_EOL .'</td>';
			echo '<td>'.$vm->cpus . PHP_EOL .'</td>';
			echo '<td>'.$vm->cores . PHP_EOL .'</td>';
			echo '<td>'.$vm->memory . PHP_EOL .'</td>';
			echo '<td>'.$vm->swap . PHP_EOL .'</td>';
			echo '<td>'.$vm->disk . PHP_EOL .'</td>';
			echo '<td>'.$vm->disktype . PHP_EOL .'</td>';
			echo '<td>'.$vm->storage . PHP_EOL .'</td>';
			echo '<td>'.$vm->diskio . PHP_EOL .'</td>';
			echo '<td>'.$vm->netmode . PHP_EOL .'</td>';
			echo '<td>'.$vm->bridge.$vm->vmbr . PHP_EOL .'</td>';
			echo '<td>'.$vm->netmodel . PHP_EOL .'</td>';
			echo '<td>'.$vm->vlanid . PHP_EOL .'</td>';
			echo '<td>'.$vm->netrate . PHP_EOL .'</td>';
			echo '<td>'.$vm->bw . PHP_EOL .'</td>';
			echo '<td>
			<a href="'.pvewhmcs_BASEURL.'&amp;tab=vmplans&amp;action=editplan&amp;id='.$vm->id.'&amp;vmtype='.$vm->vmtype.'"><img height="16" width="16" border="0" alt="Edit" src="images/edit.gif"></a>
			<a href="'.pvewhmcs_BASEURL.'&amp;tab=vmplans&amp;action=removeplan&amp;id='.$vm->id.'" onclick="return confirm(\'Plan will be deleted, continue?\')"><img height="16" width="16" border="0" alt="Edit" src="images/delete.gif"></a>
			</td>' ;
			echo '</tr>' ;
		}
		echo '
		';
		echo '
		</tbody>
		</table>
		';
	}
	echo '
	</div>
	';

	echo '
	<div id="ippools" class="tab-pane '.($_GET['tab']=="ippools" ? "active" : "").'" >
	<div class="btn-group">
	<a class="btn btn-default" href="'. pvewhmcs_BASEURL .'&amp;tab=ippools&amp;action=list_ip_pools">
	<i class="fa fa-list"></i>&nbsp; List: IP Pools
	</a>
	<a class="btn btn-default" href="'. pvewhmcs_BASEURL .'&amp;tab=ippools&amp;action=newip">
	<i class="fa fa-plus"></i>&nbsp; Add: IP to Pool
	</a>
	</div>
	';
	if ($_GET['action']=='list_ip_pools') {
		list_ip_pools() ;
	}
	if ($_GET['action']=='new_ip_pool') {
		add_ip_pool() ;
	}
	if ($_GET['action']=='newip') {
		add_ip_2_pool() ;
	}
	if (isset($_POST['newIPpool'])) {
		save_ip_pool() ;
	}
	if ($_GET['action']=='removeippool') {
		removeIpPool($_GET['id']) ;
	}
	if ($_GET['action']=='list_ips') {
		list_ips();
	}
	if ($_GET['action']=='removeip') {
		removeip($_GET['id'],$_GET['pool_id']);
	}
	echo'
	</div>
	';
	// NODES / CLUSTER tab in ADMIN GUI
	echo '<div id="nodes" class="tab-pane '.($_GET['tab']=="nodes" ? "active" : "").'" >' ;
	echo ('<strong><h2>PVE: /cluster/resources</h2></strong>');
	echo ('Coming in v1.3.x');
	echo ('<strong><h2>PVE: Cluster Action Viewer</h2></strong>');
	echo ('Coming in v1.3.x');
	echo ('<strong><h2>PVE: Failed Actions (emailed)</h2></strong>');
	echo ('Coming in v1.3.x<br><br>');
	echo ('<strong><a href=\'https://github.com/The-Network-Crew/Proxmox-VE-for-WHMCS/milestones\' target=\'_blank\'>View the milestones/versions on GitHub</a></strong>');
	echo '</div>';
	// ACTIONS / LOGS tab in ADMIN GUI
	echo '<div id="actions" class="tab-pane '.($_GET['tab']=="actions" ? "active" : "").'" >' ;
	echo ('<strong><h2>Module: Action History</h2></strong>');
	echo ('Coming in v1.3.x');
	echo ('<strong><h2>Module: Failed Actions</h2></strong>');
	echo ('Coming in v1.3.x');
	echo ('<strong><h2>WHMCS: Module Logging</h2></strong>');
	echo ('<u><a href=\'/admin/index.php?rp=/admin/logs/module-log\'>Click here</a></u> (Module Config > Debug Mode = ON)<br><br>');
	echo ('<strong><a href=\'https://github.com/The-Network-Crew/Proxmox-VE-for-WHMCS/milestones\' target=\'_blank\'>View the milestones/versions on GitHub</a></strong>');
	echo '</div>';
	// SUPPORT / HEALTH tab in ADMIN GUI
	echo '<div id="health" class="tab-pane '.($_GET['tab']=="health" ? "active" : "").'" >' ;
	echo ('<strong><h2>System Environment</h2></strong><b>Proxmox VE for WHMCS</b> v' . pvewhmcs_version() . ' (GitHub reports latest as <b>v' . get_pvewhmcs_latest_version() . '</b>)' . '<br><b>PHP</b> v' . phpversion() . ' running on <b>' . $_SERVER['SERVER_SOFTWARE'] . '</b> Web Server (' . $_SERVER['SERVER_NAME'] . ')<br><br>');
	echo ('<strong><h2>Updates & Codebase</h2></strong><b>Proxmox for WHMCS is open-source and free to use & improve on! ❤️</b><br>Repo: <a href="https://github.com/The-Network-Crew/Proxmox-VE-for-WHMCS/" target="_blank">https://github.com/The-Network-Crew/Proxmox-VE-for-WHMCS/</a><br><br>');
	echo ('<strong><h2>Product & Reviewing</h2></strong><b style="color:darkgreen;">Your 5-star review on WHMCS Marketplace will help the module grow!</b><br>*****: <a href="https://marketplace.whmcs.com/product/6935-proxmox-ve-for-whmcs" target="_blank">https://marketplace.whmcs.com/product/6935-proxmox-ve-for-whmcs</a><br><br>');
	echo ('<strong><h2>Issues: Common Causes</h2></strong>1. <b>WHMCS needs to have >100 Services, else it is an illegal Proxmox VMID.</b><br>2. Save your Package (Plan/Pool)! (configproducts.php?action=edit&id=...#tab=3)<br>3. Where possible, we pass-through the exact error to WHMCS Admin. Check it for info!<br><br>');
	echo ('<strong><h2>Module Technical Support</h2></strong>Please raise an <a href="https://github.com/The-Network-Crew/Proxmox-VE-for-WHMCS/issues/new" target="_blank"><u>Issue</u></a> on GitHub - include logs, steps to reproduce, etc.<br>Help is not guaranteed (FOSS). We will need your assistance. <b>Thank you.</b><br><br>');
	echo '</div>';

	// Config Tab
	$config= Capsule::table('mod_pvewhmcs')->where('id', '=', '1')->get()[0];
	echo '<div id="config" class="tab-pane '.($_GET['tab']=="config" ? "active" : "").'" >' ;
	echo '
	<form method="post">
	<table class="form" border="0" cellpadding="3" cellspacing="1" width="100%">
	<tr>
	<td class="fieldlabel">VNC Secret</td>
	<td class="fieldarea">
	<input type="text" size="35" name="vnc_secret" id="vnc_secret" value="'.$config->vnc_secret.'"> Password of "vnc"@"pve" user. Mandatory for VNC proxying. See the <a href="https://github.com/The-Network-Crew/Proxmox-VE-for-WHMCS/wiki" target="_blank">Wiki</a> for more info.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Starting VMID</td>
	<td class="fieldarea">
	<input type="text" size="35" name="start_id" id="start_id" value="'.$config->start_id.'"> The starting VMID for new VMs. Default is 1000.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Debug Mode</td>
	<td class="fieldarea">
	<label class="checkbox-inline">
	<input type="checkbox" name="debug_mode" value="1" '. ($config->debug_mode=="1" ? "checked" : "").'> Whether or not you want Debug Logging enabled (WHMCS Module Log for Debugging >> /admin/logs/module-log)
	</label>
	</td>
	</tr>
	</table>
	<div class="btn-container">
	<input type="submit" class="btn btn-primary" value="Save Changes" name="save_config" id="save_config">
	<input type="reset" class="btn btn-default" value="Cancel Changes">
	</div>
	</form>
	';
	
	echo '</div>';

	echo '</div>'; // end of tab-content

	if (isset($_POST['save_config'])) {
		save_config() ;
	}
}

// MODULE CONFIG: Commit changes to the database
function save_config() {
	try {
		Capsule::connection()->transaction(
			function ($connectionManager)
			{
				/** @var \Illuminate\Database\Connection $connectionManager */
				$connectionManager->table('mod_pvewhmcs')->update(
					[
						'vnc_secret' => $_POST['vnc_secret'],
						'start_id' => $_POST['start_id'],
						'debug_mode' => $_POST['debug_mode'],
					]
				);
			}
		);
		$_SESSION['pvewhmcs']['infomsg']['title']='Module Config saved.' ;
		$_SESSION['pvewhmcs']['infomsg']['message']='New options have been successfully saved.' ;
		header("Location: ".pvewhmcs_BASEURL."&tab=config");
	} catch (\Exception $e) {
		echo "Uh oh! That didn't work, but I was able to rollback. {$e->getMessage()}";
	}
}

// MODULE FORM: Add new KVM Plan
function kvm_plan_add() {
	echo '
	<form method="post">
	<table class="form" border="0" cellpadding="3" cellspacing="1" width="100%">
	<tr>
	<td class="fieldlabel">Plan Title</td>
	<td class="fieldarea">
	<input type="text" size="35" name="title" id="title" required>
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">OS - Type</td>
	<td class="fieldarea">
	<select class="form-control select-inline" name="ostype">
	<option value="l26">Linux 6.x - 2.6 Kernel</option>
	<option value="l24">Linux 2.4 Kernel</option>
	<option value="solaris">Solaris Kernel</option>
	<option value="win11">Windows 11 / 2022</option>
	<option value="win10">Windows 10 / 2016 / 2019</option>
	<option value="win8">Windows 8.x / 2012 / 2012r2</option>
	<option value="win7">Windows 7 / 2008r2</option>
	<option value="wvista">Windows Vista / 2008</option>
	<option value="wxp">Windows XP / 2003</option>
	<option value="w2k">Windows 2000</option>
	<option value="other">Other</option>
	</select>
	Kernel type (Linux, Windows, etc).
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">CPU - Emulation</td>
	<td class="fieldarea">
	<select class="form-control select-inline" name="cpuemu">
	<option value="host">(Host) Host</option>
	<option value="kvm32">(QEMU) kvm32</option>
	<option value="kvm64">(QEMU) kvm64</option>
	<option value="max">(QEMU) Max</option>
	<option value="qemu32">(QEMU) qemu32</option>
	<option value="qemu64">(QEMU) qemu64</option>
	<option value="x86-64-v2">(x86-64 psABI) v2 (Nehalem/Opteron_G3 on)</option>
	<option value="x86-64-v2-AES" selected="">(x86-64 psABI) v2-AES (Westmere/Opteron_G4 on)</option>
	<option value="x86-64-v3">(x86-64 psABI) v3 (Broadwell/EPYC on)</option>
	<option value="x86-64-v4">(x86-64 psABI) v4 (Skylake/EPYCv4 on)</option>
	<option value="486">(Intel) 486</option>
	<option value="Broadwell">(Intel) Broadwell</option>
	<option value="Broadwell-IBRS">(Intel) Broadwell-IBRS</option>
	<option value="Broadwell-noTSX">(Intel) Broadwell-noTSX</option>
	<option value="Broadwell-noTSX-IBRS">(Intel) Broadwell-noTSX-IBRS</option>
	<option value="Cascadelake-Server">(Intel) Cascadelake-Server</option>
	<option value="Cascadelake-Server-noTSX">(Intel) Cascadelake-Server-noTSX</option>
	<option value="Cascadelake-Server-v2">(Intel) Cascadelake-Server-v2</option>
	<option value="Cascadelake-Server-v4">(Intel) Cascadelake-Server-v4</option>
	<option value="Cascadelake-Server-v5">(Intel) Cascadelake-Server-v5</option>
	<option value="Conroe">(Intel) Conroe</option>
	<option value="Cooperlake">(Intel) Cooperlake</option>
	<option value="Cooperlake-v2">(Intel) Cooperlake-v2</option>
	<option value="Haswell">(Intel) Haswell</option>
	<option value="Haswell-IBRS">(Intel) Haswell-IBRS</option>
	<option value="Haswell-noTSX">(Intel) Haswell-noTSX</option>
	<option value="Haswell-noTSX-IBRS">(Intel) Haswell-noTSX-IBRS</option>
	<option value="Icelake-Client">(Intel) Icelake-Client</option>
	<option value="Icelake-Client-noTSX">(Intel) Icelake-Client-noTSX</option>
	<option value="Icelake-Server">(Intel) Icelake-Server</option>
	<option value="Icelake-Server-noTSX">(Intel) Icelake-Server-noTSX</option>
	<option value="Icelake-Server-v3">(Intel) Icelake-Server-v3</option>
	<option value="Icelake-Server-v4">(Intel) Icelake-Server-v4</option>
	<option value="Icelake-Server-v5">(Intel) Icelake-Server-v5</option>
	<option value="Icelake-Server-v6">(Intel) Icelake-Server-v6</option>
	<option value="IvyBridge">(Intel) IvyBridge</option>
	<option value="IvyBridge-IBRS">(Intel) IvyBridge-IBRS</option>
	<option value="KnightsMill">(Intel) KnightsMill</option>
	<option value="Nehalem">(Intel) Nehalem</option>
	<option value="Nehalem-IBRS">(Intel) Nehalem-IBRS</option>
	<option value="Penryn">(Intel) Penryn</option>
	<option value="SandyBridge">(Intel) SandyBridge</option>
	<option value="SandyBridge-IBRS">(Intel) SandyBridge-IBRS</option>
	<option value="SapphireRapids">(Intel) SapphireRapids</option>
	<option value="Skylake-Client">(Intel) Skylake-Client</option>
	<option value="Skylake-Client-IBRS">(Intel) Skylake-Client-IBRS</option>
	<option value="Skylake-Client-noTSX-IBRS">(Intel) Skylake-Client-noTSX-IBRS</option>
	<option value="Skylake-Client-v4">(Intel) Skylake-Client-v4</option>
	<option value="Skylake-Server">(Intel) Skylake-Server</option>
	<option value="Skylake-Server-IBRS">(Intel) Skylake-Server-IBRS</option>
	<option value="Skylake-Server-noTSX-IBRS">(Intel) Skylake-Server-noTSX-IBRS</option>
	<option value="Skylake-Server-v4">(Intel) Skylake-Server-v4</option>
	<option value="Skylake-Server-v5">(Intel) Skylake-Server-v5</option>
	<option value="Westmere">(Intel) Westmere</option>
	<option value="Westmere-IBRS">(Intel) Westmere-IBRS</option>
	<option value="pentium">(Intel) Pentium I</option>
	<option value="pentium2">(Intel) Pentium II</option>
	<option value="pentium3">(Intel) Pentium III</option>
	<option value="coreduo">(Intel) Core Duo</option>
	<option value="core2duo">(Intel) Core 2 Duo</option>
	<option value="athlon">(AMD) Athlon</option>
	<option value="phenom">(AMD) Phenom</option>
	<option value="EPYC">(AMD) EPYC</option>
	<option value="EPYC-IBPB">(AMD) EPYC-IBPB</option>
	<option value="EPYC-Milan">(AMD) EPYC-Milan</option>
	<option value="EPYC-Rome">(AMD) EPYC-Rome</option>
	<option value="EPYC-Rome-v2">(AMD) EPYC-Rome-v2</option>
	<option value="EPYC-v3">(AMD) EPYC-v3</option>
	<option value="Opteron_G1">(AMD) Opteron_G1</option>
	<option value="Opteron_G2">(AMD) Opteron_G2</option>
	<option value="Opteron_G3">(AMD) Opteron_G3</option>
	<option value="Opteron_G4">(AMD) Opteron_G4</option>
	<option value="Opteron_G5">(AMD) Opteron_G5</option>
	</select>
	CPU emulation type. Default is x86-64 psABI v2-AES
	</td>
	</tr>

	<tr>
	<td class="fieldlabel">CPU - Sockets</td>
	<td class="fieldarea">
	<input type="text" size="8" name="cpus" id="cpus" value="1" required>
	The number of CPU sockets. 1 - 4.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">CPU - Cores</td>
	<td class="fieldarea">
	<input type="text" size="8" name="cores" id="cores" value="1" required>
	The number of CPU cores per socket. 1 - 32.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">CPU - Limit</td>
	<td class="fieldarea">
	<input type="text" size="8" name="cpulimit" id="cpulimit" value="0" required>
	Limit of CPU usage. Note if the Server has 2 CPUs, it has total of "2" CPU time. Value "0" indicates no CPU limit.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">CPU - Weighting</td>
	<td class="fieldarea">
	<input type="text" size="8" name="cpuunits" id="cpuunits" value="1024" required>
	Number is relative to weights of all the other running VMs. 8 - 500000, recommend 1024. NOTE: Disable fair-scheduler by setting this to 0.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">RAM - Memory</td>
	<td class="fieldarea">
	<input type="text" size="8" name="memory" id="memory" value="2048" required>
	RAM space in Megabyte e.g 1024 = 1GB (default is 2GB)
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">SSD/HDD - Disk</td>
	<td class="fieldarea">
	<input type="text" size="8" name="disk" id="disk" value="10240" required>
	Disk space in Gigabyte e.g 1024 = 1TB (default is 10GB)
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Disk - Format</td>
	<td class="fieldarea">
	<select class="form-control select-inline" name="diskformat">
	<option value="raw">Disk Image (raw)</option>
	<option selected="" value="qcow2">QEMU Image (qcow2)</option>
	<option value="vmdk">VMware Image (vmdk)</option>
	</select>
	Recommend "QEMU/qcow2" (so it can make Snapshots)
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Disk - Cache</td>
	<td class="fieldarea">
	<select class="form-control select-inline" name="diskcache">
	<option selected="" value="">No Cache (Default)</option>
	<option value="directsync">Direct Sync</option>
	<option value="writethrough">Write Through</option>
	<option value="writeback">Write Back</option>
	<option value="unsafe">Write Back (Unsafe)</option>
	<option value="none">No Cache</option>
	</select>
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Disk - Type</td>
	<td class="fieldarea">
	<select class="form-control select-inline" name="disktype">
	<option selected="" value="virtio">Virtio</option>
	<option value="scsi">SCSI</option>
	<option value="sata">SATA</option>
	<option value="ide">IDE</option>
	</select>
	Virtio is the fastest option, then SCSI, then SATA, etc.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">PVE Store - Name</td>
	<td class="fieldarea">
	<input type="text" size="8" name="storage" id="storage" value="local" required>
	Name of VM/CT Storage on Proxmox VE hypervisor. local/local-lvm/etc.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">I/O - Throttling</td>
	<td class="fieldarea">
	<input type="text" size="8" name="diskio" id="diskio" value="0" required>
	Limit of Disk I/O in KiB/s. 0 for unrestricted storage access.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">NIC - Type</td>
	<td class="fieldarea">
	<select class="form-control select-inline" name="netmodel">
	<option selected="" value="e1000">Intel E1000 (Reliable)</option>
	<option value="virtio">VirtIO (Paravirtualized)</option>
	<option value="rtl8139">Realtek RTL8139</option>
	<option value="vmxnet3">VMware vmxnet3</option>
	</select>
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - Rate</td>
	<td class="fieldarea">
	<input type="text" size="8" name="netrate" id="netrate">
	Network Rate Limit in Megabit/Second, Blank means unlimited.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - BW Limit</td>
	<td class="fieldarea">
	<input type="text" size="8" name="bw" id="bw">
	Monthly Bandwidth Limit in Gigabytes, Blank means unlimited.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - Mode</td>
	<td class="fieldarea">
	<select class="form-control select-inline" name="netmode">
	<option value="bridge">Bridge</option>
	<option value="nat">NAT</option>
	<option value="none">No Network</option>
	</select>
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Bridge - Interface</td>
	<td class="fieldarea">
	<input type="text" size="8" name="bridge" id="bridge" value="vmbr">
	Bridge interface name. Proxmox default bridge name is "vmbr".
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Bridge - Int. ID</td>
	<td class="fieldarea">
	<input type="text" size="8" name="vmbr" id="vmbr" value="0">
	Bridge interface number. Proxmox default bridge (vmbr) number is 0, it means "vmbr0".
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Trunk - VLAN ID</td>
	<td class="fieldarea">
	<input type="text" size="8" name="vlanid" id="vlanid">
	VLAN ID for Plan Services. Default forgoes tagging (VLAN ID), leave blank for untagged.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">
	Hardware Virt?
	</td>
	<td class="fieldarea">
	<label class="checkbox-inline">
	<input type="checkbox" name="kvm" value="1" checked> Enable KVM hardware virtualisation. Requires support/enablement in BIOS. (Recommended)
	</label>
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">
	On-boot VM?
	</td>
	<td class="fieldarea">
	<label class="checkbox-inline">
	<input type="checkbox" name="onboot" value="1" checked> Specifies whether a VM will be started during hypervisor boot-up. (Recommended)
	</label>
	</td>
	</tr>
	</table>

	<div class="btn-container">
	<input type="submit" class="btn btn-primary" value="Save Changes" name="addnewkvmplan" id="addnewkvmplan">
	<input type="reset" class="btn btn-default" value="Cancel Changes">
	</div>
	</form>
	';
}

// MODULE FORM: Edit a KVM Plan
function kvm_plan_edit($id) {
	$plan= Capsule::table('mod_pvewhmcs_plans')->where('id', '=', $id)->get()[0];
	if (empty($plan)) {
		echo 'Plan Not found' ;
		return false ;
	}
	echo '<pre>' ;
		//print_r($plan) ;
	echo '</pre>' ;
	echo '
	<form method="post">
	<table class="form" border="0" cellpadding="3" cellspacing="1" width="100%">
	<tr>
	<td class="fieldlabel">Plan Title</td>
	<td class="fieldarea">
	<input type="text" size="35" name="title" id="title" required value="'.$plan->title.'">
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">OS - Type</td>
	<td class="fieldarea">
	<select class="form-control select-inline" name="ostype">
	<option value="l26" ' . ($plan->ostype == "l26" ? "selected" : "") . '>Linux 6.x - 2.6 Kernel</option>
	<option value="l24" ' . ($plan->ostype == "l24" ? "selected" : "") . '>Linux 2.4 Kernel</option>
	<option value="solaris" ' . ($plan->ostype == "solaris" ? "selected" : "") . '>Solaris Kernel</option>
	<option value="win11" ' . ($plan->ostype == "win11" ? "selected" : "") . '>Windows 11 / 2022</option>
	<option value="win10" ' . ($plan->ostype == "win10" ? "selected" : "") . '>Windows 10 / 2016 / 2019</option>
	<option value="win8" ' . ($plan->ostype == "win8" ? "selected" : "") . '>Windows 8.x / 2012 / 2012r2</option>
	<option value="win7" ' . ($plan->ostype == "win7" ? "selected" : "") . '>Windows 7 / 2008r2</option>
	<option value="wvista" ' . ($plan->ostype == "wvista" ? "selected" : "") . '>Windows Vista / 2008</option>
	<option value="wxp" ' . ($plan->ostype == "wxp" ? "selected" : "") . '>Windows XP / 2003</option>
	<option value="w2k" ' . ($plan->ostype == "w2k" ? "selected" : "") . '>Windows 2000</option>
	<option value="other" ' . ($plan->ostype == "other" ? "selected" : "") . '>Other</option>
	</select>
	Kernel type (Linux, Windows, etc).
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">CPU - Emulation</td>
	<td class="fieldarea">
	<select class="form-control select-inline" name="cpuemu">
	<option value="host" ' . ($plan->cpuemu == "host" ? "selected" : "") . '>Host</option>
	<option value="kvm32" ' . ($plan->cpuemu == "kvm32" ? "selected" : "") . '>(QEMU) kvm32</option>
	<option value="kvm64" ' . ($plan->cpuemu == "kvm64" ? "selected" : "") . '>(QEMU) kvm64</option>
	<option value="max" ' . ($plan->cpuemu == "max" ? "selected" : "") . '>(QEMU) Max</option>
	<option value="qemu32" ' . ($plan->cpuemu == "qemu32" ? "selected" : "") . '>(QEMU) qemu32</option>
	<option value="qemu64" ' . ($plan->cpuemu == "qemu64" ? "selected" : "") . '>(QEMU) qemu64</option>
	<option value="x86-64-v2" ' . ($plan->cpuemu == "x86-64-v2" ? "selected" : "") . '>(x86-64 psABI) v2 (Nehalem/Opteron_G3 on)</option>
	<option value="x86-64-v2-AES" ' . ($plan->cpuemu == "x86-64-v2-AES" ? "selected" : "") . '>(x86-64 psABI) v2-AES (Westmere/Opteron_G4 on)</option>
	<option value="x86-64-v3" ' . ($plan->cpuemu == "x86-64-v3" ? "selected" : "") . '>(x86-64 psABI) v3 (Broadwell/EPYC on)</option>
	<option value="x86-64-v4" ' . ($plan->cpuemu == "x86-64-v4" ? "selected" : "") . '>(x86-64 psABI) v4 (Skylake/EPYCv4 on)</option>
	<option value="486" ' . ($plan->cpuemu == "486" ? "selected" : "") . '>(Intel) 486</option>
	<option value="Broadwell" ' . ($plan->cpuemu == "Broadwell" ? "selected" : "") . '>(Intel) Broadwell</option>
	<option value="Broadwell-IBRS" ' . ($plan->cpuemu == "Broadwell-IBRS" ? "selected" : "") . '>(Intel) Broadwell-IBRS</option>
	<option value="Broadwell-noTSX" ' . ($plan->cpuemu == "Broadwell-noTSX" ? "selected" : "") . '>(Intel) Broadwell-noTSX</option>
	<option value="Broadwell-noTSX-IBRS" ' . ($plan->cpuemu == "Broadwell-noTSX-IBRS" ? "selected" : "") . '>(Intel) Broadwell-noTSX-IBRS</option>
	<option value="Cascadelake-Server" ' . ($plan->cpuemu == "Cascadelake-Server" ? "selected" : "") . '>(Intel) Cascadelake-Server</option>
	<option value="Cascadelake-Server-noTSX" ' . ($plan->cpuemu == "Cascadelake-Server-noTSX" ? "selected" : "") . '>(Intel) Cascadelake-Server-noTSX</option>
	<option value="Cascadelake-Server-v2" ' . ($plan->cpuemu == "Cascadelake-Server-v2" ? "selected" : "") . '>(Intel) Cascadelake-Server V2</option>
	<option value="Cascadelake-Server-v4" ' . ($plan->cpuemu == "Cascadelake-Server-v4" ? "selected" : "") . '>(Intel) Cascadelake-Server V4</option>
	<option value="Cascadelake-Server-v5" ' . ($plan->cpuemu == "Cascadelake-Server-v5" ? "selected" : "") . '>(Intel) Cascadelake-Server V5</option>
	<option value="Conroe" ' . ($plan->cpuemu == "Conroe" ? "selected" : "") . '>(Intel) Conroe</option>
	<option value="Cooperlake" ' . ($plan->cpuemu == "Cooperlake" ? "selected" : "") . '>(Intel) Cooperlake</option>
	<option value="Cooperlake-v2" ' . ($plan->cpuemu == "Cooperlake-v2" ? "selected" : "") . '>(Intel) Cooperlake V2</option>
	<option value="Haswell" ' . ($plan->cpuemu == "Haswell" ? "selected" : "") . '>(Intel) Haswell</option>
	<option value="Haswell-IBRS" ' . ($plan->cpuemu == "Haswell-IBRS" ? "selected" : "") . '>(Intel) Haswell-IBRS</option>
	<option value="Haswell-noTSX" ' . ($plan->cpuemu == "Haswell-noTSX" ? "selected" : "") . '>(Intel) Haswell-noTSX</option>
	<option value="Haswell-noTSX-IBRS" ' . ($plan->cpuemu == "Haswell-noTSX-IBRS" ? "selected" : "") . '>(Intel) Haswell-noTSX-IBRS</option>
	<option value="Icelake-Client" ' . ($plan->cpuemu == "Icelake-Client" ? "selected" : "") . '>(Intel) Icelake-Client</option>
	<option value="Icelake-Client-noTSX" ' . ($plan->cpuemu == "Icelake-Client-noTSX" ? "selected" : "") . '>(Intel) Icelake-Client-noTSX</option>
	<option value="Icelake-Server" ' . ($plan->cpuemu == "Icelake-Server" ? "selected" : "") . '>(Intel) Icelake-Server</option>
	<option value="Icelake-Server-noTSX" ' . ($plan->cpuemu == "Icelake-Server-noTSX" ? "selected" : "") . '>(Intel) Icelake-Server-noTSX</option>
	<option value="Icelake-Server-v3" ' . ($plan->cpuemu == "Icelake-Server-v3" ? "selected" : "") . '>(Intel) Icelake-Server V3</option>
	<option value="Icelake-Server-v4" ' . ($plan->cpuemu == "Icelake-Server-v4" ? "selected" : "") . '>(Intel) Icelake-Server V4</option>
	<option value="Icelake-Server-v5" ' . ($plan->cpuemu == "Icelake-Server-v5" ? "selected" : "") . '>(Intel) Icelake-Server V5</option>
	<option value="Icelake-Server-v6" ' . ($plan->cpuemu == "Icelake-Server-v6" ? "selected" : "") . '>(Intel) Icelake-Server V6</option>
	<option value="IvyBridge" ' . ($plan->cpuemu == "IvyBridge" ? "selected" : "") . '>(Intel) IvyBridge</option>
	<option value="IvyBridge-IBRS" ' . ($plan->cpuemu == "IvyBridge-IBRS" ? "selected" : "") . '>(Intel) IvyBridge-IBRS</option>
	<option value="KnightsMill" ' . ($plan->cpuemu == "KnightsMill" ? "selected" : "") . '>(Intel) KnightsMill</option>
	<option value="Nehalem" ' . ($plan->cpuemu == "Nehalem" ? "selected" : "") . '>(Intel) Nehalem</option>
	<option value="Nehalem-IBRS" ' . ($plan->cpuemu == "Nehalem-IBRS" ? "selected" : "") . '>(Intel) Nehalem-IBRS</option>
	<option value="Penryn" ' . ($plan->cpuemu == "Penryn" ? "selected" : "") . '>(Intel) Penryn</option>
	<option value="SandyBridge" ' . ($plan->cpuemu == "SandyBridge" ? "selected" : "") . '>(Intel) SandyBridge</option>
	<option value="SandyBridge-IBRS" ' . ($plan->cpuemu == "SandyBridge-IBRS" ? "selected" : "") . '>(Intel) SandyBridge-IBRS</option>
	<option value="SapphireRapids" ' . ($plan->cpuemu == "SapphireRapids" ? "selected" : "") . '>(Intel) Sapphire Rapids</option>
	<option value="Skylake-Client" ' . ($plan->cpuemu == "Skylake-Client" ? "selected" : "") . '>(Intel) Skylake-Client</option>
	<option value="Skylake-Client-IBRS" ' . ($plan->cpuemu == "Skylake-Client-IBRS" ? "selected" : "") . '>(Intel) Skylake-Client-IBRS</option>
	<option value="Skylake-Client-noTSX-IBRS" ' . ($plan->cpuemu == "Skylake-Client-noTSX-IBRS" ? "selected" : "") . '>(Intel) Skylake-Client-noTSX-IBRS</option>
	<option value="Skylake-Client-v4" ' . ($plan->cpuemu == "Skylake-Client-v4" ? "selected" : "") . '>(Intel) Skylake-Client V4</option>
	<option value="Skylake-Server" ' . ($plan->cpuemu == "Skylake-Server" ? "selected" : "") . '>(Intel) Skylake-Server</option>
	<option value="Skylake-Server-IBRS" ' . ($plan->cpuemu == "Skylake-Server-IBRS" ? "selected" : "") . '>(Intel) Skylake-Server-IBRS</option>
	<option value="Skylake-Server-noTSX-IBRS" ' . ($plan->cpuemu == "Skylake-Server-noTSX-IBRS" ? "selected" : "") . '>(Intel) Skylake-Server-noTSX-IBRS</option>
	<option value="Skylake-Server-v4" ' . ($plan->cpuemu == "Skylake-Server-v4" ? "selected" : "") . '>(Intel) Skylake-Server V4</option>
	<option value="Skylake-Server-v5" ' . ($plan->cpuemu == "Skylake-Server-v5" ? "selected" : "") . '>(Intel) Skylake-Server V5</option>
	<option value="Westmere" ' . ($plan->cpuemu == "Westmere" ? "selected" : "") . '>(Intel) Westmere</option>
	<option value="Westmere-IBRS" ' . ($plan->cpuemu == "Westmere-IBRS" ? "selected" : "") . '>(Intel) Westmere-IBRS</option>
	<option value="pentium" ' . ($plan->cpuemu == "pentium" ? "selected" : "") . '>(Intel) Pentium I</option>
	<option value="pentium2" ' . ($plan->cpuemu == "pentium2" ? "selected" : "") . '>(Intel) Pentium II</option>
	<option value="pentium3" ' . ($plan->cpuemu == "pentium3" ? "selected" : "") . '>(Intel) Pentium III</option>
	<option value="coreduo" ' . ($plan->cpuemu == "coreduo" ? "selected" : "") . '>(Intel) Core Duo</option>
	<option value="core2duo" ' . ($plan->cpuemu == "core2duo" ? "selected" : "") . '>(Intel) Core 2 Duo</option>
	<option value="athlon" ' . ($plan->cpuemu == "athlon" ? "selected" : "") . '>(AMD) Athlon</option>
	<option value="phenom" ' . ($plan->cpuemu == "phenom" ? "selected" : "") . '>(AMD) Phenom</option>
	<option value="EPYC" ' . ($plan->cpuemu == "EPYC" ? "selected" : "") . '>(AMD) EPYC</option>
	<option value="EPYC-IBPB" ' . ($plan->cpuemu == "EPYC-IBPB" ? "selected" : "") . '>(AMD) EPYC-IBPB</option>
	<option value="EPYC-Milan" ' . ($plan->cpuemu == "EPYC-Milan" ? "selected" : "") . '>(AMD) EPYC-Milan</option>
	<option value="EPYC-Rome" ' . ($plan->cpuemu == "EPYC-Rome" ? "selected" : "") . '>(AMD) EPYC-Rome</option>
	<option value="EPYC-Rome-v2" ' . ($plan->cpuemu == "EPYC-Rome-v2" ? "selected" : "") . '>(AMD) EPYC-Rome-v2</option>
	<option value="EPYC-v3" ' . ($plan->cpuemu == "EPYC-v3" ? "selected" : "") . '>(AMD) EPYC-v3</option>
	<option value="Opteron_G1" ' . ($plan->cpuemu == "Opteron_G1" ? "selected" : "") . '>(AMD) Opteron_G1</option>
	<option value="Opteron_G2" ' . ($plan->cpuemu == "Opteron_G2" ? "selected" : "") . '>(AMD) Opteron_G2</option>
	<option value="Opteron_G3" ' . ($plan->cpuemu == "Opteron_G3" ? "selected" : "") . '>(AMD) Opteron_G3</option>
	<option value="Opteron_G4" ' . ($plan->cpuemu == "Opteron_G4" ? "selected" : "") . '>(AMD) Opteron_G4</option>
	<option value="Opteron_G5" ' . ($plan->cpuemu == "Opteron_G5" ? "selected" : "") . '>(AMD) Opteron_G5</option>
	</select>
	CPU emulation type. Default is KVM64 on Proxmox VE < 7.x and x86-64-v2-AES on Proxmox VE > 8.x
	</td>
	</tr>

	<tr>
	<td class="fieldlabel">CPU - Sockets</td>
	<td class="fieldarea">
	<input type="text" size="8" name="cpus" id="cpus" value="'.$plan->cpus.'" required>
	The number of CPU sockets. 1 - 4.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">CPU - Cores</td>
	<td class="fieldarea">
	<input type="text" size="8" name="cores" id="cores" value="'.$plan->cores.'" required>
	The number of CPU cores per socket. 1 - 32.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">CPU - Limit</td>
	<td class="fieldarea">
	<input type="text" size="8" name="cpulimit" id="cpulimit" value="'.$plan->cpulimit.'" required>
	Limit of CPU usage. Note if the computer has 2 CPUs, it has total of "2" CPU time. Value "0" indicates no CPU limit.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">CPU - Weighting</td>
	<td class="fieldarea">
	<input type="text" size="8" name="cpuunits" id="cpuunits" value="'.$plan->cpuunits.'" required>
	Number is relative to weights of all the other running VMs. 8 - 500000 recommended 1024. NOTE: You can disable fair-scheduler by setting this to 0.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">RAM - Memory</td>
	<td class="fieldarea">
	<input type="text" size="8" name="memory" id="memory" required value="'.$plan->memory.'">
	RAM space in Megabytes e.g 1024 = 1GB
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">SSD/HDD - Disk</td>
	<td class="fieldarea">
	<input type="text" size="8" name="disk" id="disk" required value="'.$plan->disk.'">
	Disk space in Gigabytes e.g 1024 = 1TB
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Disk - Format</td>
	<td class="fieldarea">
	<select class="form-control select-inline" name="diskformat">
	<option value="raw" '. ($plan->diskformat=="raw" ? "selected" : "").'>Disk Image (raw)</option>
	<option value="qcow2" '. ($plan->diskformat=="qcow2" ? "selected" : "").'>QEMU image (qcow2)</option>
	<option value="vmdk" '. ($plan->diskformat=="vmdk" ? "selected" : "").'>VMware image (vmdk)</option>
	</select>
	Recommend "QEMU/qcow2 format" (to make Snapshots)
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Disk - Cache</td>
	<td class="fieldarea">
	<select class="form-control select-inline" name="diskcache">
	<option value="" '. ($plan->diskcache=="" ? "selected" : "").'>No Cache (Default)</option>
	<option value="directsync" '. ($plan->diskcache=="directsync" ? "selected" : "").'>Direct Sync</option>
	<option value="writethrough" '. ($plan->diskcache=="writethrough" ? "selected" : "").'>Write Through</option>
	<option value="writeback" '. ($plan->diskcache=="writeback" ? "selected" : "").'>Write Back</option>
	<option value="unsafe" '. ($plan->diskcache=="unsafe" ? "selected" : "").'>Write Back (Unsafe)</option>
	<option value="none" '. ($plan->diskcache=="none" ? "selected" : "").'>No Cache</option>
	</select>
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Disk - Type</td>
	<td class="fieldarea">
	<select class="form-control select-inline" name="disktype">
	<option value="virtio" '. ($plan->disktype=="virtio" ? "selected" : "").'>Virtio</option>
	<option value="scsi" '. ($plan->disktype=="scsi" ? "selected" : "").'>SCSI</option>
	<option value="sata" '. ($plan->disktype=="sata" ? "selected" : "").'>SATA</option>
	<option value="ide" '. ($plan->disktype=="ide" ? "selected" : "").'>IDE</option>
	</select>
	Virtio is the fastest option, then SCSI, then SATA, etc.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">PVE Store - Name</td>
	<td class="fieldarea">
	<input type="text" size="8" name="storage" id="storage" required value="'.$plan->storage.'">
	Name of VM/CT Storage on Proxmox VE hypervisor. local/local-lvm/etc.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">I/O Cap - Write</td>
	<td class="fieldarea">
	<input type="text" size="8" name="diskio" id="diskio" required value="'.$plan->diskio.'">
	Limit of Disk I/O in KiB/s. 0 for unrestricted storage access.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">NIC - Type</td>
	<td class="fieldarea">
	<select class="form-control select-inline" name="netmodel">
	<option value="e1000" '. ($plan->netmodel=="e1000" ? "selected" : "").'>Intel E1000 (Reliable)</option>
	<option value="virtio" '. ($plan->netmodel=="virtio" ? "selected" : "").'>VirtIO (Paravirt)</option>
	<option value="rtl8139" '. ($plan->netmodel=="rtl8139" ? "selected" : "").'>Realtek RTL8139</option>
	<option value="vmxnet3" '. ($plan->netmodel=="vmxnet3" ? "selected" : "").'>VMware vmxnet3</option>
	</select>
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - Rate</td>
	<td class="fieldarea">
	<input type="text" size="8" name="netrate" id="netrate" value="'.$plan->netrate.'">
	Network Rate Limit in Megabit, Blank means unlimited.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - BW Limit</td>
	<td class="fieldarea">
	<input type="text" size="8" name="bw" id="bw" value="'.$plan->bw.'">
	Monthly Bandwidth Limit in Gigabyte, Blank means unlimited.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - Mode</td>
	<td class="fieldarea">
	<select class="form-control select-inline" name="netmode">
	<option value="bridge" '. ($plan->netmode=="bridge" ? "selected" : "").'>Bridge</option>
	<option value="nat" '. ($plan->netmode=="nat" ? "selected" : "").'>NAT</option>
	<option value="none" '. ($plan->netmode=="none" ? "selected" : "").'>No network</option>
	</select>
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Bridge - Interface</td>
	<td class="fieldarea">
	<input type="text" size="8" name="bridge" id="bridge" value="'.$plan->bridge.'">
	Bridge interface name. Proxmox default bridge name is "vmbr".
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Bridge - Int. ID</td>
	<td class="fieldarea">
	<input type="text" size="8" name="vmbr" id="vmbr" value="'.$plan->vmbr.'">
	Bridge interface number. Proxmox default bridge (vmbr) number is 0, It means "vmbr0".
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Trunk - VLAN ID</td>
	<td class="fieldarea">
	<input type="text" size="8" name="vlanid" id="vlanid">
	VLAN ID for Plan Services. Default forgoes tagging (VLAN ID), leave blank for untagged.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">
	Hardware Virt?
	</td>
	<td class="fieldarea">
	<label class="checkbox-inline">
	<input type="checkbox" name="kvm" value="1" '. ($plan->kvm=="1" ? "checked" : "").'> Enable KVM hardware virtualisation. Requires support/enablement in BIOS. (Recommended)
	</label>
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">
	On-boot VM?
	</td>
	<td class="fieldarea">
	<label class="checkbox-inline">
	<input type="checkbox" name="onboot" value="1" '. ($plan->onboot=="1" ? "checked" : "").'> Specifies whether a VM will be started during hypervisor boot-up. (Recommended)
	</label>
	</td>
	</tr>
	</table>

	<div class="btn-container">
	<input type="submit" class="btn btn-primary" value="Save Changes" name="updatekvmplan" id="saveeditedkvmplan">
	<input type="reset" class="btn btn-default" value="Cancel Changes">
	</div>
	</form>
	';
}

// MODULE FORM: Add an LXC Plan
function lxc_plan_add() {
	echo '
	<form method="post">
	<table class="form" border="0" cellpadding="3" cellspacing="1" width="100%">
	<tr>
	<td class="fieldlabel">Plan Title</td>
	<td class="fieldarea">
	<input type="text" size="35" name="title" id="title" required>
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">CPU - Limit</td>
	<td class="fieldarea">
	<input type="text" size="8" name="cpulimit" id="cpulimit" value="1" required>
	Limit of CPU usage. Default is 1. Note: if the computer has 2 CPUs, it has total of "2" CPU time. Value "0" indicates no CPU limit.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">CPU - Weighting</td>
	<td class="fieldarea">
	<input type="text" size="8" name="cpuunits" id="cpuunits" value="1024" required>
	Number is relative to weights of all the other running VMs. 8 - 500000, recommend 1024.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">RAM - Memory</td>
	<td class="fieldarea">
	<input type="text" size="8" name="memory" id="memory" required>
	RAM space in Megabytes e.g 1024 = 1GB
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Swap - Space</td>
	<td class="fieldarea">
	<input type="text" size="8" name="swap" id="swap">
	Swap space in Megabytes e.g 1024 = 1GB
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">SSD/HDD - Disk</td>
	<td class="fieldarea">
	<input type="text" size="8" name="disk" id="disk" required>
	Disk space in Gigabytes e.g 1024 = 1TB
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">PVE Store - Name</td>
	<td class="fieldarea">
	<input type="text" size="8" name="storage" id="storage" value="local" required>
	Name of VM/CT Storage on Proxmox VE hypervisor. local/local-lvm/etc.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">I/O - Throttling</td>
	<td class="fieldarea">
	<input type="text" size="8" name="diskio" id="diskio" value="0" required>
	Limit of Disk I/O in KiB/s. 0 for unrestricted storage access.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Bridge - Interface</td>
	<td class="fieldarea">
	<input type="text" size="8" name="bridge" id="bridge" value="vmbr">
	Bridge interface name. Proxmox default bridge name is "vmbr".
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Bridge - Int. ID</td>
	<td class="fieldarea">
	<input type="text" size="8" name="vmbr" id="vmbr" value="0">
	Bridge interface number. Proxmox default bridge (vmbr) number is 0, it means "vmbr0".
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Trunk - VLAN ID</td>
	<td class="fieldarea">
	<input type="text" size="8" name="vlanid" id="vlanid">
	VLAN ID for Plan Services. Default forgoes tagging (VLAN ID), leave blank for untagged.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - Rate</td>
	<td class="fieldarea">
	<input type="text" size="8" name="netrate" id="netrate">
	Network Rate Limit in Megabit/Second, blank means unlimited.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Data - Monthly</td>
	<td class="fieldarea">
	<input type="text" size="8" name="bw" id="bw">
	Monthly Bandwidth Limit in Gigabytes, blank means unlimited.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">
	On-boot CT?
	</td>
	<td class="fieldarea">
	<label class="checkbox-inline">
	<input type="checkbox" name="onboot" value="1" checked> Specifies whether a CT will be started during hypervisor boot-up. (Recommended)
	</label>
	</td>
	</tr>
	</table>

	<div class="btn-container">
	<input type="submit" class="btn btn-primary" value="Save Changes" name="addnewlxcplan" id="addnewlxcplan">
	<input type="reset" class="btn btn-default" value="Cancel Changes">
	</div>
	</form>
	';
}

// MODULE FORM: Edit an LXC Plan
function lxc_plan_edit($id) {
	$plan= Capsule::table('mod_pvewhmcs_plans')->where('id', '=', $id)->get()[0];
	if (empty($plan)) {
		echo 'Plan Not found' ;
		return false ;
	}
	echo '<pre>' ;
		//print_r($plan) ;
	echo '</pre>' ;

	echo '
	<form method="post">
	<table class="form" border="0" cellpadding="3" cellspacing="1" width="100%">
	<tr>
	<td class="fieldlabel">Plan Title</td>
	<td class="fieldarea">
	<input type="text" size="35" name="title" id="title" required value="'.$plan->title.'">
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">CPU - Limit</td>
	<td class="fieldarea">
	<input type="text" size="8" name="cpulimit" id="cpulimit" value="'.$plan->cpulimit.'" required>
	Limit of CPU usage. Default is 1. Note: if the computer has 2 CPUs, it has total of "2" CPU time. Value "0" indicates no CPU limit.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">CPU - Weighting</td>
	<td class="fieldarea">
	<input type="text" size="8" name="cpuunits" id="cpuunits" value="'.$plan->cpuunits.'" required>
	Number is relative to weights of all the other running VMs. 8 - 500000, recommend 1024.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">RAM - Memory</td>
	<td class="fieldarea">
	<input type="text" size="8" name="memory" id="memory" required value="'.$plan->memory.'">
	RAM space in Megabytes e.g 1024 = 1GB
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Swap - Space</td>
	<td class="fieldarea">
	<input type="text" size="8" name="swap" id="swap" value="'.$plan->swap.'">
	Swap space in Megabytes e.g 1024 = 1GB
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">SSD/HDD - Disk</td>
	<td class="fieldarea">
	<input type="text" size="8" name="disk" id="disk" value="'.$plan->disk.'" required>
	Disk space in Gigabytes e.g 1024 = 1TB
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">PVE Store - Name</td>
	<td class="fieldarea">
	<input type="text" size="8" name="storage" id="storage" value="'.$plan->storage.'" required>
	Name of VM/CT Storage on Proxmox VE hypervisor. local/local-lvm/etc.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">I/O - Throttling</td>
	<td class="fieldarea">
	<input type="text" size="8" name="diskio" id="diskio" value="'.$plan->diskio.'" required>
	Limit of Disk I/O in KiB/s. 0 for unrestricted storage access.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Bridge - Interface</td>
	<td class="fieldarea">
	<input type="text" size="8" name="bridge" id="bridge" value="'.$plan->bridge.'">
	Bridge interface name. Proxmox default bridge name is "vmbr".
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Bridge - Int. ID</td>
	<td class="fieldarea">
	<input type="text" size="8" name="vmbr" id="vmbr" value="'.$plan->vmbr.'">
	Bridge interface number. Proxmox default bridge (vmbr) number is 0, It means "vmbr0".
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Trunk - VLAN ID</td>
	<td class="fieldarea">
	<input type="text" size="8" name="vlanid" id="vlanid">
	VLAN ID for Plan Services. Default forgoes tagging (VLAN ID), leave blank for untagged.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - Rate</td>
	<td class="fieldarea">
	<input type="text" size="8" name="netrate" id="netrate" value="'.$plan->netrate.'">
	Network Rate Limit in Megabit/Second, blank means unlimited.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">Network - BW Limit</td>
	<td class="fieldarea">
	<input type="text" size="8" name="bw" id="bw" value="'.$plan->bw.'">
	Monthly Bandwidth Limit in Gigabytes, blank means unlimited.
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">
	On-boot CT?
	</td>
	<td class="fieldarea">
	<label class="checkbox-inline">
	<input type="checkbox" value="1" name="onboot" '. ($plan->onboot=="1" ? "checked" : "").'> Specifies whether a CT will be started during hypervisor boot-up. (Recommended)
	</label>
	</td>
	</tr>
	</table>

	<div class="btn-container">
	<input type="submit" class="btn btn-primary" value="Save Changes" name="updatelxcplan" id="updatelxcplan">
	<input type="reset" class="btn btn-default" value="Cancel Changes">
	</div>
	</form>
	';
}

// MODULE FORM ACTION: Save KVM Plan
function save_kvm_plan() {
	try {
		Capsule::connection()->transaction(
			function ($connectionManager)
			{
				/** @var \Illuminate\Database\Connection $connectionManager */
				$connectionManager->table('mod_pvewhmcs_plans')->insert(
					[
						'title' => $_POST['title'],
						'vmtype' => 'kvm',
						'ostype' => $_POST['ostype'],
						'cpus' => $_POST['cpus'],
						'cpuemu' => $_POST['cpuemu'],
						'cores' => $_POST['cores'],
						'cpulimit' => $_POST['cpulimit'],
						'cpuunits' => $_POST['cpuunits'],
						'memory' => $_POST['memory'],
						'disk' => $_POST['disk'],
						'diskformat' => $_POST['diskformat'],
						'diskcache' => $_POST['diskcache'],
						'disktype' => $_POST['disktype'],
						'storage' => $_POST['storage'],
						'diskio' => $_POST['diskio'],
						'netmode' => $_POST['netmode'],
						'bridge' => $_POST['bridge'],
						'vmbr' => $_POST['vmbr'],
						'netmodel' => $_POST['netmodel'],
						'vlanid' => $_POST['vlanid'],
						'netrate' => $_POST['netrate'],
						'bw' => $_POST['bw'],
						'kvm' => $_POST['kvm'],
						'onboot' => $_POST['onboot'],
					]
				);
			}
		);
		$_SESSION['pvewhmcs']['infomsg']['title']='KVM Plan added.' ;
		$_SESSION['pvewhmcs']['infomsg']['message']='Saved the KVM Plan successfuly.' ;
		header("Location: ".pvewhmcs_BASEURL."&tab=vmplans&action=planlist");
	} catch (\Exception $e) {
		echo "Uh oh! Inserting didn't work, but I was able to rollback. {$e->getMessage()}";
	}
}

// MODULE FORM ACTION: Update KVM Plan
function update_kvm_plan() {
	Capsule::table('mod_pvewhmcs_plans')
	->where('id', $_GET['id'])
	->update(
		[
			'title' => $_POST['title'],
			'vmtype' => 'kvm',
			'ostype' => $_POST['ostype'],
			'cpus' => $_POST['cpus'],
			'cpuemu' => $_POST['cpuemu'],
			'cores' => $_POST['cores'],
			'cpulimit' => $_POST['cpulimit'],
			'cpuunits' => $_POST['cpuunits'],
			'memory' => $_POST['memory'],
			'disk' => $_POST['disk'],
			'diskformat' => $_POST['diskformat'],
			'diskcache' => $_POST['diskcache'],
			'disktype' => $_POST['disktype'],
			'storage' => $_POST['storage'],
			'diskio' => $_POST['diskio'],
			'netmode' => $_POST['netmode'],
			'bridge' => $_POST['bridge'],
			'vmbr' => $_POST['vmbr'],
			'netmodel' => $_POST['netmodel'],
			'vlanid' => $_POST['vlanid'],
			'netrate' => $_POST['netrate'],
			'bw' => $_POST['bw'],
			'kvm' => $_POST['kvm'],
			'onboot' => $_POST['onboot'],
		]
	);
	$_SESSION['pvewhmcs']['infomsg']['title']='KVM Plan updated.' ;
	$_SESSION['pvewhmcs']['infomsg']['message']='Updated the KVM Plan successfuly.' ;
	header("Location: ".pvewhmcs_BASEURL."&tab=vmplans&action=planlist");
}

// MODULE FORM ACTION: Remove Plan
function remove_plan($id) {
	Capsule::table('mod_pvewhmcs_plans')->where('id', '=', $id)->delete();
	header("Location: ".pvewhmcs_BASEURL."&tab=vmplans&action=planlist");
	$_SESSION['pvewhmcs']['infomsg']['title']='Plan Deleted.' ;
	$_SESSION['pvewhmcs']['infomsg']['message']='Selected Item deleted successfuly.' ;
}

// MODULE FORM ACTION: Save LXC Plan
function save_lxc_plan() {
	try {
		Capsule::connection()->transaction(
			function ($connectionManager)
			{
				/** @var \Illuminate\Database\Connection $connectionManager */
				$connectionManager->table('mod_pvewhmcs_plans')->insert(
					[
						'title' => $_POST['title'],
						'vmtype' => 'lxc',
						'cores' => $_POST['cores'],
						'cpulimit' => $_POST['cpulimit'],
						'cpuunits' => $_POST['cpuunits'],
						'memory' => $_POST['memory'],
						'swap' => $_POST['swap'],
						'disk' => $_POST['disk'],
						'storage' => $_POST['storage'],
						'diskio' => $_POST['diskio'],
						'bridge' => $_POST['bridge'],
						'vmbr' => $_POST['vmbr'],
						'netmodel' => $_POST['netmodel'],
						'vlanid' => $_POST['vlanid'],
						'netrate' => $_POST['netrate'],
						'bw' => $_POST['bw'],
						'onboot' => $_POST['onboot'],
					]
				);
			}
		);
		$_SESSION['pvewhmcs']['infomsg']['title']='New LXC Plan added.' ;
		$_SESSION['pvewhmcs']['infomsg']['message']='Saved the LXC Plan successfuly.' ;
		header("Location: ".pvewhmcs_BASEURL."&tab=vmplans&action=planlist");
	} catch (\Exception $e) {
		echo "Uh oh! Inserting didn't work, but I was able to rollback. {$e->getMessage()}";
	}
}

// MODULE FORM ACTION: Update LXC Plan
function update_lxc_plan() {
	Capsule::table('mod_pvewhmcs_plans')
	->where('id', $_GET['id'])
	->update(
		[
			'title' => $_POST['title'],
			'vmtype' => 'lxc',
			'cores' => $_POST['cores'],
			'cpulimit' => $_POST['cpulimit'],
			'cpuunits' => $_POST['cpuunits'],
			'memory' => $_POST['memory'],
			'swap' => $_POST['swap'],
			'disk' => $_POST['disk'],
			'storage' => $_POST['storage'],
			'diskio' => $_POST['diskio'],
			'bridge' => $_POST['bridge'],
			'vmbr' => $_POST['vmbr'],
			'netmodel' => $_POST['netmodel'],
			'vlanid' => $_POST['vlanid'],
			'netrate' => $_POST['netrate'],
			'bw' => $_POST['bw'],
			'onboot' => $_POST['onboot'],
		]
	);
	$_SESSION['pvewhmcs']['infomsg']['title']='LXC Plan updated.' ;
	$_SESSION['pvewhmcs']['infomsg']['message']='Updated the LXC Plan successfully. (Updating plans will not effect on current VMs.)' ;
	header("Location: ".pvewhmcs_BASEURL."&tab=vmplans&action=planlist");
}

// IP POOLS: List all Pools
function list_ip_pools() {
	echo '<a class="btn btn-default" href="'. pvewhmcs_BASEURL .'&amp;tab=ippools&amp;action=new_ip_pool"><i class="fa fa-plus-square"></i>&nbsp; New IP Pool</a>';
	echo '<table class="datatable"><tr><th>ID</th><th>Pool</th><th>Gateway</th><th>Action</th></tr>';
	foreach (Capsule::table('mod_pvewhmcs_ip_pools')->get() as $pool) {
		echo '<tr>';
		echo '<td>'.$pool->id . PHP_EOL .'</td>';
		echo '<td>'.$pool->title . PHP_EOL .'</td>';
		echo '<td>'.$pool->gateway . PHP_EOL .'</td>';
		echo '<td>
		<a href="'.pvewhmcs_BASEURL.'&amp;tab=ippools&amp;action=list_ips&amp;id='.$pool->id.'"><img height="16" width="16" border="0" alt="Info" src="images/edit.gif"></a>
		<a href="'.pvewhmcs_BASEURL.'&amp;tab=ippools&amp;action=removeippool&amp;id='.$pool->id.'" onclick="return confirm(\'Pool and all IP Addresses assigned to it will be deleted, are you sure to continue?\')"><img height="16" width="16" border="0" alt="Remove" src="images/delete.gif"></a>
		</td>' ;
		echo '</tr>' ;
	}
	echo '</table>';
}

// IP POOL FORM: Add IP Pool
function add_ip_pool() {
	echo '
	<form method="post">
	<table class="form" border="0" cellpadding="3" cellspacing="1" width="100%">
	<tr>
	<td class="fieldlabel">Pool Title</td>
	<td class="fieldarea">
	<input type="text" size="35" name="title" id="title" required>
	</td>
	<td class="fieldlabel">Gateway</td>
	<td class="fieldarea">
	<input type="text" size="25" name="gateway" id="gateway" required>
	Gateway address of the pool
	</td>
	</tr>
	</table>
	<input type="submit" class="btn btn-primary" name="newIPpool" value="Save"/>
	</form>
	';
}

// IP POOL FORM ACTION: Save Pool
function save_ip_pool() {
	try {
		Capsule::connection()->transaction(
			function ($connectionManager)
			{
				/** @var \Illuminate\Database\Connection $connectionManager */
				$connectionManager->table('mod_pvewhmcs_ip_pools')->insert(
					[
						'title' => $_POST['title'],
						'gateway' => $_POST['gateway'],
					]
				);
			}
		);
		$_SESSION['pvewhmcs']['infomsg']['title']='New IP Pool added.' ;
		$_SESSION['pvewhmcs']['infomsg']['message']='New IP Pool saved successfully.' ;
		header("Location: ".pvewhmcs_BASEURL."&tab=ippools&action=list_ip_pools");
	} catch (\Exception $e) {
		echo "Uh oh! Inserting didn't work, but I was able to rollback. {$e->getMessage()}";
	}
}

// IP POOL FORM ACTION: Remove Pool
function removeIpPool($id) {
	Capsule::table('mod_pvewhmcs_ip_addresses')->where('pool_id', '=', $id)->delete();
	Capsule::table('mod_pvewhmcs_ip_pools')->where('id', '=', $id)->delete();

	header("Location: ".pvewhmcs_BASEURL."&tab=ippools&action=list_ip_pools");
	$_SESSION['pvewhmcs']['infomsg']['title']='IP Pool Deleted.' ;
	$_SESSION['pvewhmcs']['infomsg']['message']='Deleted the IP Pool successfully.' ;
}

// IP POOL FORM ACTION: Add IP to Pool
function add_ip_2_pool() {
	require_once(ROOTDIR.'/modules/addons/pvewhmcs/Ipv4/Subnet.php');
	echo '<form method="post">
	<table class="form" border="0" cellpadding="3" cellspacing="1" width="100%">
	<tr>
	<td class="fieldlabel">IP Pool</td>
	<td class="fieldarea">
	<select class="form-control select-inline" name="pool_id">';
	foreach (Capsule::table('mod_pvewhmcs_ip_pools')->get() as $pool) {
		echo '<option value="'.$pool->id.'">'.$pool->title.'</option>';
		$gateways[]=$pool->gateway ;
	}
	echo '</select>
	</td>
	</tr>
	<tr>
	<td class="fieldlabel">IP Block</td>
	<td class="fieldarea">
	<input type="text" name="ipblock"/>
	IP Block with CIDR e.g. 172.16.255.230/27, for single IP address just don\'t use CIDR
	</td>
	</tr>
	</table>
	<input type="submit" name="assignIP2pool" value="Save"/>
	</form>';
	if (isset($_POST['assignIP2pool'])) {
			// check if single IP address
		if ((strpos($_POST['ipblock'],'/'))!=false) {
			$subnet=Ipv4_Subnet::fromString($_POST['ipblock']);
			$ips = $subnet->getIterator();
			foreach($ips as $ip) {
				if (!in_array($ip, $gateways)) {
					Capsule::table('mod_pvewhmcs_ip_addresses')->insert(
						[
							'pool_id' => $_POST['pool_id'],
							'ipaddress' => $ip,
							'mask' => $subnet->getNetmask(),
						]
					);
				}
			}
		}
		else {
			if (!in_array($_POST['ipblock'], $gateways)) {
				Capsule::table('mod_pvewhmcs_ip_addresses')->insert(
					[
						'pool_id' => $_POST['pool_id'],
						'ipaddress' => $_POST['ipblock'],
						'mask' => '255.255.255.255',
					]
				);
			}
		}
		header("Location: ".pvewhmcs_BASEURL."&tab=ippools&action=list_ips&id=".$_POST['pool_id']);
		$_SESSION['pvewhmcs']['infomsg']['title']='IP Address/Blocks added to Pool.' ;
		$_SESSION['pvewhmcs']['infomsg']['message']='You can remove IP Addresses from the pool.' ;
	}
}

// IP POOL FORM: List IPs in Pool
function list_ips() {
		//echo '<script>$(function() {$( "#dialog" ).dialog();});</script>' ;
		//echo '<div id="dialog">' ;
	echo '<table class="datatable"><tr><th>IP Address</th><th>Subnet Mask</th><th>Action</th></tr>' ;
	foreach (Capsule::table('mod_pvewhmcs_ip_addresses')->where('pool_id', '=', $_GET['id'])->get() as $ip) {
		echo '<tr><td>'.$ip->ipaddress.'</td><td>'.$ip->mask.'</td><td>';
		if (count(Capsule::table('mod_pvewhmcs_vms')->where('ipaddress','=',$ip->ipaddress)->get())>0)
			echo 'is in use' ;
		else
			echo '<a href="'.pvewhmcs_BASEURL.'&amp;tab=ippools&amp;action=removeip&amp;pool_id='.$ip->pool_id.'&amp;id='.$ip->id.'" onclick="return confirm(\'IP Address will be deleted from the pool, continue?\')"><img height="16" width="16" border="0" alt="Edit" src="images/delete.gif"></a>';
		echo '</td></tr>';
	}
	echo '</table>' ;

}

// IP POOL FORM ACTION: Remove IP from Pool
function removeip($id,$pool_id) {
	Capsule::table('mod_pvewhmcs_ip_addresses')->where('id', '=', $id)->delete();
	header("Location: ".pvewhmcs_BASEURL."&tab=ippools&action=list_ips&id=".$pool_id);
	$_SESSION['pvewhmcs']['infomsg']['title']='IP Address deleted.' ;
	$_SESSION['pvewhmcs']['infomsg']['message']='Deleted selected item successfuly.' ;
}
?>
